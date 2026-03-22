<?php

declare(strict_types=1);

namespace SRP\Controllers;

use SRP\Models\SrpClient;
use SRP\Models\TrafficLog;

class LandingController
{
    /**
     * Evaluate routing decision from GET URL parameters and act immediately:
     *  - Decision A → 302 redirect to configured redirect URL
     *  - Decision B → 302 redirect to /_meetups/?...&_f=1  (one retry)
     *                 If ?_f=1 already present → render landing page (loop guard)
     *
     * Parameters extracted from the request:
     *  click_id     — $_GET['click_id']       (auto-generated if absent)
     *  user_lp      — $_GET['user_lp']        (campaign / landing-page ID)
     *  country_code — CF-IPCountry header or $_GET['country_code']
     *  ip_address   — detected from trusted proxy headers
     *  user_agent   — $_SERVER['HTTP_USER_AGENT'] (or ?ua= shorthand for testing)
     */
    public static function route(): void
    {
        // ── 0. Loop guard: ?_f=1 means we already redirected for Decision B ──
        if (($_GET['_f'] ?? '') === '1') {
            self::index();
            return;
        }

        // ── 1. Extract click_id + user_lp from GET URL ────────────────────
        $clickId = trim((string)($_GET['click_id'] ?? ''));
        if ($clickId === '') {
            $clickId = 'AUTO_' . bin2hex(random_bytes(4));
        }
        $lp = trim((string)($_GET['user_lp'] ?? ''));

        // ── 2. Server-detect IP, country, User-Agent ──────────────────────
        $ip = SrpClient::getClientIP();
        $cc = SrpClient::getCountryCode();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Optional ?ua= or ?user_agent= shorthand override (wap/mobile/desktop/web/tablet/bot)
        $uaOverride = strtolower(trim((string)($_GET['ua'] ?? $_GET['user_agent'] ?? '')));
        if (
            $uaOverride !== ''
            && in_array($uaOverride, ['wap', 'web', 'mobile', 'desktop', 'tablet', 'ipad', 'bot', 'crawler'], true)
        ) {
            $ua = $uaOverride;
        }

        // ── 3. Resolve decision locally (no outbound HTTP round-trip) ──────
        try {
            $result = DecisionController::resolve([
                'click_id'     => $clickId,
                'country_code' => $cc,
                'user_agent'   => $ua,
                'ip_address'   => $ip,
                'user_lp'      => $lp,
            ]);
        } catch (\InvalidArgumentException) {
            // Unexpected validation failure — serve landing page as safe fallback
            self::index();
            return;
        }

        // ── 4. Log traffic entry (non-fatal — DB failure must not block) ───
        try {
            TrafficLog::create([
                'ip'       => $result['ip'],
                'ua'       => $result['ua'],
                'cid'      => $result['cid'],
                'cc'       => $result['cc'],
                'lp'       => $result['lp'],
                'decision' => $result['decision'],
            ]);
        } catch (\Throwable $e) {
            error_log('LandingController: TrafficLog::create() failed — ' . $e->getMessage());
        }

        // ── 5. Act on decision ────────────────────────────────────────────
        if ($result['decision'] === 'A') {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Location: ' . $result['target'], true, 302);

            // Flush redirect to browser before post-response work
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            exit;
        }

        // Decision B — redirect to /_meetups/?QUERY&_f=1 (one retry, then render)
        // Whitelist: only visitor-facing params — strip internal (ip, cc, ua)
        $params = array_filter([
            'click_id' => $clickId,
            'user_lp'  => $lp,
            '_f'       => '1',
        ], static function (string $value): bool {
            return $value !== '';
        });
        $fallbackUrl = '/_meetups/?' . http_build_query($params);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Location: ' . $fallbackUrl, true, 302);
        exit;
    }

    public static function index(): void
    {
        require __DIR__ . '/../Views/landing.view.php';
    }
}
