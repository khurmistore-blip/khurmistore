<?php
/**
 * list_products.php — Fetch BigBuy products in SMALL pages (no full catalog).
 * The full catalog (100k+) times out, so we request a small page at a time.
 * -------------------------------------------------------------------------
 * Open (first 15):
 *   khurmistore.es/list_products.php?key=khurmi2026
 *
 * Next pages:
 *   khurmistore.es/list_products.php?key=khurmi2026&page=1   (products 15-30)
 *   khurmistore.es/list_products.php?key=khurmi2026&page=2   (30-45) ...
 *
 * Change page size:
 *   &size=10
 *
 * DELETE this file after use.
 */

if (($_GET['key'] ?? '') !== 'khurmi2026') {
    http_response_code(403);
    exit('Forbidden');
}
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(120);

$cfg  = require __DIR__ . '/config.php';
$page = max(0, (int)($_GET['page'] ?? 0));
$size = (int)($_GET['size'] ?? 15);
$firstRow = $page * $size + 1; // BigBuy uses 1-based firstRow

$base = ($cfg['bigbuy_sandbox'] ?? true)
    ? 'https://api.sandbox.bigbuy.eu'
    : 'https://api.bigbuy.eu';

echo "==========================================\n";
echo " BigBuy Products - page $page (size $size)\n";
echo " Sandbox: " . (($cfg['bigbuy_sandbox'] ?? true) ? 'TRUE' : 'FALSE') . "\n";
echo "==========================================\n\n";

// BigBuy pagination params: firstRow + maxResults
$url = "$base/rest/catalog/products.json?isoCode=es&firstRow=$firstRow&maxResults=$size";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . ($cfg['bigbuy_api_key'] ?? ''),
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => 60,
]);
$resp   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err    = curl_error($ch);
curl_close($ch);

if ($err) {
    echo "cURL error: $err\n";
    exit;
}
if ($status < 200 || $status >= 300) {
    echo "HTTP $status\n";
    echo "Response (first 500 chars):\n" . substr($resp, 0, 500) . "\n\n";
    echo "If this is a huge/slow response, tell me and I'll switch to category endpoint.\n";
    exit;
}

$products = json_decode($resp, true);
if (!is_array($products)) {
    echo "Unexpected response:\n" . substr($resp, 0, 500) . "\n";
    exit;
}

echo "Returned " . count($products) . " products on this page:\n\n";
echo str_pad("ID", 12) . str_pad("SKU", 18) . "Wholesale\n";
echo str_repeat("-", 45) . "\n";

foreach ($products as $p) {
    $id    = $p['id']  ?? '?';
    $sku   = $p['sku'] ?? '?';
    $price = $p['wholesalePrice'] ?? $p['retailPrice'] ?? '?';
    echo str_pad((string)$id, 12) . str_pad((string)$sku, 18) . "EUR $price\n";
}

echo "\n==========================================\n";
echo " Pick an ID and test it:\n";
echo "   test_bigbuy.php?key=khurmi2026&id=<ID>\n\n";
echo " Next page:\n";
echo "   list_products.php?key=khurmi2026&page=" . ($page + 1) . "\n";
echo "==========================================\n";
