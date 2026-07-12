<?php
declare(strict_types=1);

/**
 * check_stock_179.php — URGENT one-off: live BigBuy stock check for our
 * product id=179 (the blackhead vacuum / ad product) only. Standalone —
 * does not touch bigbuy.php (that comes later, once this is confirmed).
 * ---------------------------------------------------------------------------
 * HOW TO RUN (browser): khurmistore.es/check_stock_179.php?key=khurmi2026
 * DELETE this file after use.
 */

if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/supabase.php';
$cfg = require __DIR__ . '/config.php';

echo "==========================================\n";
echo " URGENT: live BigBuy stock check — product id=179\n";
echo "==========================================\n\n";

$rows = sb_get($cfg, 'products?id=eq.179&select=id,name,cj_pid,stock');
$p    = $rows[0] ?? null;

if (!$p) {
    exit("FAIL: no product with id=179 found in Supabase.\n");
}

echo "Product:        " . ($p['name'] ?? '(no name)') . "\n";
echo "Current DB stock (fake placeholder): " . ($p['stock'] ?? '?') . "\n";

$cjPid = trim((string)($p['cj_pid'] ?? ''));
if ($cjPid === '') {
    exit("FAIL: product id=179 has no cj_pid — cannot look up BigBuy stock.\n");
}
echo "cj_pid (BigBuy product id): $cjPid\n\n";

$base = $cfg['bigbuy_sandbox']
    ? 'https://api.sandbox.bigbuy.eu'
    : 'https://api.bigbuy.eu';

$url = $base . '/rest/catalog/productstockbyhandlingdays/' . rawurlencode($cjPid) . '.json';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $cfg['bigbuy_api_key'],
        'Content-Type: application/json',
    ],
]);
$body    = curl_exec($ch);
$status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

echo "Endpoint: GET $url\n";
echo "HTTP status: $status\n";
if ($curlErr !== '') {
    echo "curl error: $curlErr\n";
}
echo "\nRaw response:\n" . ($body === false ? '(no body)' : $body) . "\n\n";

if ($body === false || $status < 200 || $status >= 300) {
    echo "==========================================\n";
    echo "RESULT: COULD NOT VERIFY — treat as UNKNOWN/AT RISK until confirmed.\n";
    echo "==========================================\n";
    exit;
}

$data = json_decode($body, true);
$rec  = (is_array($data) && isset($data[0]) && is_array($data[0])) ? $data[0] : $data;

if (!is_array($rec) || !isset($rec['stocks']) || !is_array($rec['stocks'])) {
    echo "==========================================\n";
    echo "RESULT: UNEXPECTED RESPONSE SHAPE — no 'stocks' array found. Treat as UNKNOWN/AT RISK.\n";
    echo "==========================================\n";
    exit;
}

$totalQty  = 0;
$minDays   = null;
$maxDays   = null;
foreach ($rec['stocks'] as $s) {
    $qty = (int)($s['quantity'] ?? 0);
    $totalQty += $qty;
    if (isset($s['minHandlingDays'])) {
        $minDays = $minDays === null ? (int)$s['minHandlingDays'] : min($minDays, (int)$s['minHandlingDays']);
    }
    if (isset($s['maxHandlingDays'])) {
        $maxDays = $maxDays === null ? (int)$s['maxHandlingDays'] : max($maxDays, (int)$s['maxHandlingDays']);
    }
}

echo "==========================================\n";
if ($totalQty > 0) {
    echo "RESULT: IN STOCK — total quantity = $totalQty\n";
} else {
    echo "RESULT: OUT OF STOCK — total quantity = 0\n";
}
echo "Handling days: " . ($minDays ?? '?') . "-" . ($maxDays ?? '?') . "\n";
echo "==========================================\n";
