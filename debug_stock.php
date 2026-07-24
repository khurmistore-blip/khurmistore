<?php
set_time_limit(0);
/**
 * debug_stock.php — Shows the EXACT raw response from BigBuy's stock endpoints
 * for one product, so we can see the real JSON shape and fix parsing.
 *
 * RUN:
 *   khurmistore.es/debug_stock.php?key=khurmi2026&id=1299171
 *   (use any BigBuy product ID from the auto_source output, e.g. 1299171)
 */

if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

$cfg = require __DIR__ . '/config.php';
$id  = (int)($_GET['id'] ?? 1299171);

function bbGet(array $cfg, string $endpoint): array {
    $base = $cfg['bigbuy_sandbox'] ? 'https://api.sandbox.bigbuy.eu' : 'https://api.bigbuy.eu';
    $ch = curl_init($base . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $cfg['bigbuy_api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 60,
    ]);
    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'raw' => $resp];
}

echo "==========================================\n";
echo " STOCK DEBUG for BigBuy product ID: $id\n";
echo "==========================================\n\n";

// Endpoint A: productstockbyhandlingdays (the one we use)
echo "--- A) /rest/catalog/productstockbyhandlingdays/$id.json ---\n";
$a = bbGet($cfg, "/rest/catalog/productstockbyhandlingdays/$id.json");
echo "HTTP: " . $a['status'] . "\n";
echo "RAW RESPONSE:\n" . $a['raw'] . "\n\n";

sleep(6); // pacing

// Endpoint B: the plain productstock (known broken, but let's confirm)
echo "--- B) /rest/catalog/productstock/$id.json ---\n";
$b = bbGet($cfg, "/rest/catalog/productstock/$id.json");
echo "HTTP: " . $b['status'] . "\n";
echo "RAW RESPONSE:\n" . $b['raw'] . "\n\n";

echo "==========================================\n";
echo "Copy this whole output and send it to Claude.\n";
echo "==========================================\n";
