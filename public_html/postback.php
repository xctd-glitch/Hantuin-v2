<?php

declare(strict_types=1);

// ═══════════════════════════════════════════════════════════════════════════════
// SRP — Postback Receiver
//
// Called by your traffic network after a lead/conversion.
// URL format (configure in dashboard → Routing Config):
//
//   https://trackng.us/postback.php
//     ?token={POSTBACK_TOKEN}
//     &click_id={click_id}
//     &payout={payout}          ← optional, decimal (e.g. 3.50)
//     &currency={currency}      ← optional, default USD
//     &status={status}          ← optional, default approved
//
// Supported methods: GET, POST
// Returns: 200 OK | 400 Bad Request | 403 Forbidden | 405 Method Not Allowed
// ═══════════════════════════════════════════════════════════════════════════════

require dirname(__DIR__) . '/src/bootstrap.php';

use SRP\Middleware\SecurityHeaders;
use SRP\Models\Conversion;
use SRP\Models\Settings;

SecurityHeaders::send();

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Merge GET + POST params (POST takes priority)
$params = array_merge($_GET, $method === 'POST' ? $_POST : []);

// ── Token validation ───────────────────────────────────────────────────────────
$cfg   = Settings::get();
$token = trim((string)($cfg['postback_token'] ?? ''));
$given = trim((string)($params['token'] ?? ''));

if ($token === '' || $given === '' || !hash_equals($token, $given)) {
    http_response_code(403);
    exit('Forbidden');
}

// ── Required params ────────────────────────────────────────────────────────────
$clickId = substr(trim((string)($params['click_id'] ?? '')), 0, 100);

if ($clickId === '') {
    http_response_code(400);
    exit('Missing click_id');
}

// ── Optional params ────────────────────────────────────────────────────────────
$payout   = max(0.0, round((float)($params['payout']   ?? 0), 4));
$currency = strtoupper(substr(trim((string)($params['currency'] ?? 'USD')), 0, 10));
$status   = strtolower(substr(trim((string)($params['status']   ?? 'approved')), 0, 50));
// Accept ?country=ID or ?country_code=ID (ISO Alpha-2)
$country  = strtoupper(substr(trim((string)($params['country'] ?? $params['country_code'] ?? '')), 0, 10)) ?: null;

// Normalize network-specific status aliases → standard values
// iMonetizeit: success | rejected | pending | chargeback
$statusMap = [
    'success'   => 'approved',
    'convert'   => 'approved',
    'lead'      => 'approved',
    'confirmed' => 'approved',
    'complete'  => 'approved',
];
$status = $statusMap[$status] ?? $status;

// Sender IP (support common forwarding headers)
$ip = trim((string)(
    $_SERVER['HTTP_CF_CONNECTING_IP'] ??
    $_SERVER['HTTP_X_FORWARDED_FOR']  ??
    $_SERVER['REMOTE_ADDR']           ??
    ''
));
$ip = substr(explode(',', $ip)[0], 0, 45); // take first IP if list

// Raw params for audit (token excluded)
$raw = $params;
unset($raw['token']);

// ── Store ──────────────────────────────────────────────────────────────────────
try {
    Conversion::create([
        'click_id' => $clickId,
        'payout'   => $payout,
        'currency' => $currency,
        'status'   => $status,
        'country'  => $country,
        'ip'       => $ip,
        'raw'      => json_encode($raw, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]);
} catch (\Throwable) {
    http_response_code(500);
    exit('Internal error');
}

http_response_code(200);
echo 'OK';
