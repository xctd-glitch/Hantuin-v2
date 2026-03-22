<?php

declare(strict_types=1);

namespace SRP\Controllers;

use SRP\Config\Cache;
use SRP\Config\Environment;
use SRP\Models\Settings;
use SRP\Models\TrafficLog;
use SRP\Models\Validator;

class DecisionController
{
    // ── Device type labels ─────────────────────────────────
    private const DEVICE_BOT    = 'BOT';
    private const DEVICE_TABLET = 'TABLET';
    private const DEVICE_WAP    = 'WAP';
    private const DEVICE_WEB    = 'WEB';

    // ── Decision B reason codes ────────────────────────────
    private const REASON_OK          = 'ok';
    private const REASON_SYSTEM_OFF  = 'system_off';
    private const REASON_MUTED       = 'muted';
    private const REASON_BOT         = 'bot';
    private const REASON_NOT_MOBILE  = 'not_mobile';
    private const REASON_VPN         = 'vpn';
    private const REASON_COUNTRY     = 'country_blocked';
    private const REASON_NO_REDIRECT = 'no_redirect_url';

    // ── VPN APCu cache TTL ─────────────────────────────────
    private const VPN_CACHE_TTL = 3600;

    public static function handleDecision(): void
    {
        // #1 — CORS headers + OPTIONS preflight must be handled BEFORE auth,
        //        so browsers can complete the preflight without a key.
        self::handleCors();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'OPTIONS') {
            http_response_code(204);
            header('Content-Length: 0');
            exit;
        }

        // #2 — Auth: missing or wrong key → 401
        $apiKey = Environment::get('SRP_API_KEY');
        if ($apiKey === '') {
            http_response_code(500);
            header('Content-Type: application/json');
            exit('{"ok":false,"error":"API key not configured"}');
        }

        $providedKey = (string)($_SERVER['HTTP_X_API_KEY'] ?? '');
        if ($providedKey === '' || !hash_equals($apiKey, $providedKey)) {
            http_response_code(401);
            header('Content-Type: application/json');
            exit('{"ok":false,"error":"Unauthorized"}');
        }

        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        if ($method !== 'POST') {
            http_response_code(405);
            exit('{"ok":false,"error":"Method not allowed"}');
        }

        $raw = (string)file_get_contents('php://input');
        if (strlen($raw) > 10240) {
            http_response_code(413);
            exit('{"ok":false,"error":"Payload too large"}');
        }

        // #3 — Empty body is valid (treated as {}), but malformed JSON → 400
        $in = json_decode($raw !== '' ? $raw : '{}', true);
        if (!is_array($in)) {
            http_response_code(400);
            exit('{"ok":false,"error":"Invalid JSON"}');
        }

        try {
            $result = self::resolve($in);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            exit(json_encode(['ok' => false, 'error' => $e->getMessage()]));
        }

        echo json_encode([
            'ok'       => true,
            'decision' => $result['decision'],
            'target'   => $result['target'],
            'reason'   => $result['reason'],
            'ts'       => time(),
        ]);

        // Flush response to client before post-response work
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        // #7 — TrafficLog exception must NOT corrupt the already-sent response
        try {
            TrafficLog::create([
                'ip' => $result['ip'], 'ua' => $result['ua'], 'cid' => $result['cid'],
                'cc' => $result['cc'], 'lp' => $result['lp'], 'decision' => $result['decision'],
            ]);
        } catch (\Throwable $e) {
            error_log('DecisionController: TrafficLog::create() failed — ' . $e->getMessage());
        }

        exit;
    }

    /**
     * Core routing decision — pure logic, no HTTP side-effects.
     *
     * @param  array<string,mixed> $input  click_id, country_code, user_agent, ip_address, user_lp
     * @return array{decision:string, target:string, reason:string, cid:string, cc:string, ua:string, ip:string, lp:string}
     * @throws \InvalidArgumentException  on missing click_id or invalid ip_address
     */
    public static function resolve(array $input): array
    {
        $cid = strtoupper(preg_replace('/[^a-zA-Z0-9_-]/', '', Validator::sanitizeString($input['click_id']    ?? '', 100)));
        $cc  = strtoupper(preg_replace('/[^a-zA-Z]/', '', Validator::sanitizeString($input['country_code'] ?? 'XX', 10)));
        $ua  = Validator::sanitizeString($input['user_agent']  ?? '', 500);
        $ip  = Validator::sanitizeString($input['ip_address']  ?? '', 45);
        $lp  = strtoupper(preg_replace('/[^a-zA-Z0-9_-]/', '', Validator::sanitizeString($input['user_lp'] ?? '', 100)));

        // #4 — click_id is required
        if ($cid === '') {
            throw new \InvalidArgumentException('click_id is required');
        }

        if ($ip !== '' && !Validator::isValidIp($ip)) {
            throw new \InvalidArgumentException('Invalid IP address format');
        }

        // #8 — Device detection: BOT → TABLET → WAP → WEB (order matters — iPad before mobile)
        $device = self::detectDevice($ua);
        // #5 — VPN check respects SRP_VPN_CHECK_ENABLED env flag
        $vpn    = self::checkVpn($ip);

        if ($cc !== '' && $cc !== 'XX' && !Validator::isValidCountryCode($cc)) {
            $cc = 'XX';
        }

        // #9 — Fallback URL: only click_id + user_lp, no PII (ip, ua, cc)
        $fallback = '/_meetups/?' . http_build_query([
            'click_id' => strtolower($cid),
            'user_lp'  => strtolower($lp),
        ], '', '&', PHP_QUERY_RFC3986);

        $cfg = Settings::get();

        $countryAllowed = ($cc === '' || $cc === 'XX')
            ? true
            : Validator::isCountryAllowed($cc, (string)$cfg['country_filter_mode'], (string)$cfg['country_filter_list']);

        // #10 — redirect_url must be a valid, reachable HTTPS URL
        $redirectUrl  = trim((string)($cfg['redirect_url'] ?? ''));
        $redirectValid = $redirectUrl !== ''
            && str_starts_with($redirectUrl, 'https://')
            && filter_var($redirectUrl, FILTER_VALIDATE_URL) !== false;

        // Evaluate decision with sequential reason tracking
        $systemOn = !empty($cfg['system_on']);
        $decision = 'B';
        $target   = $fallback;

        if (!$systemOn) {
            $reason = self::REASON_SYSTEM_OFF;
        } elseif (self::isSystemMuted($systemOn)) {
            $reason = self::REASON_MUTED;
        } elseif ($device === self::DEVICE_BOT) {
            $reason = self::REASON_BOT;
        } elseif ($device !== self::DEVICE_WAP && $device !== self::DEVICE_TABLET) {
            $reason = self::REASON_NOT_MOBILE;
        } elseif ($vpn) {
            $reason = self::REASON_VPN;
        } elseif (!$countryAllowed) {
            $reason = self::REASON_COUNTRY;
        } elseif (!$redirectValid) {
            $reason = self::REASON_NO_REDIRECT;
        } else {
            $decision = 'A';
            $target   = rtrim($redirectUrl, '/');
            $reason   = self::REASON_OK;
        }

        return [
            'decision'     => $decision,
            'target'       => $target,
            'reason'       => $reason,
            'cid'          => $cid,
            'cc'           => $cc,
            'ua'           => $ua,
            'ip'           => $ip,
            'lp'           => $lp,
        ];
    }

    /**
     * #8 — Device classification.
     * Order: BOT → TABLET (iPad before mobile catch) → WAP → WEB.
     */
    private static function detectDevice(string $ua): string
    {
        $lower = strtolower($ua);

        // Shorthand aliases
        if ($lower === 'wap'    || $lower === 'mobile') {
            return self::DEVICE_WAP;
        }
        if ($lower === 'web'    || $lower === 'desktop') {
            return self::DEVICE_WEB;
        }
        if ($lower === 'tablet' || $lower === 'ipad') {
            return self::DEVICE_TABLET;
        }
        if ($lower === 'bot'    || $lower === 'crawler') {
            return self::DEVICE_BOT;
        }

        // Full UA string — BOT first so crawler-mobiles don't slip through
        if (preg_match('~bot|crawl|spider|slurp|baiduspider~i', $ua)) {
            return self::DEVICE_BOT;
        }
        // Tablet / iPad before mobile so iPad UA strings always → TABLET
        if (preg_match('/tablet|ipad/i', $ua)) {
            return self::DEVICE_TABLET;
        }
        if (preg_match('/mobile|android|iphone|ipod|blackberry|iemobile|opera mini|windows phone/i', $ua)) {
            return self::DEVICE_WAP;
        }

        return self::DEVICE_WEB;
    }

    /**
     * #5 — VPN/proxy check.
     * Returns false immediately if SRP_VPN_CHECK_ENABLED=0.
     * #6 — Network errors and timeouts return false (safe default), never 500.
     */
    private static function checkVpn(string $ip): bool
    {
        // #5 — Opt-out: SRP_VPN_CHECK_ENABLED=0 disables all outbound calls
        if (Environment::get('SRP_VPN_CHECK_ENABLED') === '0') {
            return false;
        }

        if (!Validator::isValidIp($ip)) {
            return false;
        }

        // Private / reserved ranges are never VPNs
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        $cacheKey = 'vpn_' . md5($ip);
        $cached   = Cache::get($cacheKey);
        if (is_bool($cached)) {
            return $cached;
        }

        // #6 — Any network error or timeout → treat as no VPN (never let this 500)
        try {
            $ctx  = stream_context_create([
                'http' => ['timeout' => 2, 'ignore_errors' => true],
                'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $body = @file_get_contents('https://blackbox.ipinfo.app/lookup/' . urlencode($ip), false, $ctx);
        } catch (\Throwable $e) {
            $body = false;
        }

        $isVpn = ($body !== false && trim((string)$body) === 'Y');

        Cache::set($cacheKey, $isVpn, self::VPN_CACHE_TTL);

        return $isVpn;
    }

    /**
     * Returns true when the system should suppress Decision A traffic.
     * Time-slot based: 2 min A (slots 0–1), 3 min B (slots 2–4), repeating.
     */
    private static function isSystemMuted(bool $systemOn): bool
    {
        return $systemOn && ((int)(time() / 60) % 5) >= 2;
    }

    private static function handleCors(): void
    {
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
        $appUrl  = \SRP\Config\Environment::getAppUrl();
        $allowed = $appUrl !== '' ? [$appUrl] : [];

        if ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        } elseif (in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true)) {
            header('Access-Control-Allow-Origin: *');
        }

        header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Max-Age: 86400');
    }
}
