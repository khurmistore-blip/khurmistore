<?php
declare(strict_types=1);

/**
 * debug_stock_single_1300776.php — ONE raw test call to the single-product
 * stock-by-handling-days endpoint, for cj_pid=1300776 (our id=48, the
 * hero-adjacent product). Prints the full raw response so we can confirm
 * whether "id"/"sku" in the response actually matches our cj_pid before
 * trusting bigbuy_stock_sync.php's per-product rewrite.
 * ---------------------------------------------------------------------------
 * HOW TO RUN (browser): khurmistore.es/debug_stock_single_1300776.php?key=khurmi2026
 * DELETE this file after use.
 */

if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/supabase.php';
$cfg = require __DIR__ . '/config.php';

$base = $cfg['bigbuy_sandbox'] ? 'https://api.sandbox.bigbuy.eu' : 'https://api.bigbuy.eu';

echo "==========================================\n";
echo " Debug: single-product stock endpoint, cj_pid=1300776 (id=48)\n";
echo " Sandbox: " . ($cfg['bigbuy_sandbox'] ? 'TRUE' : 'FALSE') . "\n";
echo "==========================================\n\n";

// Confirm what our DB actually has for id=48, for side-by-side comparison.
$rows = sb_get($cfg, 'products?id=eq.48&select=id,name,cj_pid,stock');
$p = $rows[0] ?? null;
if ($p) {
    echo "Our DB row: id={$p['id']}, name=\"{$p['name']}\", cj_pid={$p['cj_pid']}, current stock={$p['stock']}\n\n";
} else {
    echo "WARNING: id=48 not found in Supabase — proceeding with cj_pid=1300776 from your message anyway.\n\n";
}

$cjPid = $p['cj_pid'] ?? '1300776';
$url   = $base . '/rest/catalog/productstockbyhandlingdays/' . rawurlencode((string)$cjPid) . '.json';

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

echo "GET $url\n";
echo "HTTP status: $status\n";
if ($curlErr !== '') {
    echo "curl error: $curlErr\n";
}
echo "\nFULL RAW RESPONSE:\n" . ($body === false ? '(no body)' : $body) . "\n\n";

if ($body !== false && $status >= 200 && $status < 300) {
    $data = json_decode($body, true);
    $rec  = (is_array($data) && isset($data[0]) && is_array($data[0])) ? $data[0] : $data;
    if (is_array($rec)) {
        echo "---- Parsed for quick comparison ----\n";
        echo "Response 'id':  " . ($rec['id']  ?? '(missing)') . "\n";
        echo "Response 'sku': " . ($rec['sku'] ?? '(missing)') . "\n";
        echo "Our cj_pid:     $cjPid\n";
        echo "id MATCHES cj_pid: " . (isset($rec['id']) && (string)$rec['id'] === (string)$cjPid ? 'YES' : 'NO') . "\n";
        if (isset($rec['stocks']) && is_array($rec['stocks'])) {
            $total = 0;
            foreach ($rec['stocks'] as $s) {
                $total += (int)($s['quantity'] ?? 0);
            }
            echo "Summed stocks[].quantity: $total\n";
        } else {
            echo "No 'stocks' array in response.\n";
        }
    }
}

echo "\n==========================================\n";
echo "Done. Paste this whole output back before we touch the sync script.\n";
echo "==========================================\n";
