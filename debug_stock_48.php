<?php
declare(strict_types=1);

/**
 * debug_stock_48.php — Finds BigBuy's REAL working stock endpoint/shape for
 * product id=48 (cj_pid=1300776). getProductStock()'s current endpoint
 * (/rest/catalog/productstock/{id}.json) doesn't exist in BigBuy's current
 * API (confirmed against their OpenAPI spec) — this tries the two endpoints
 * that DO exist and prints the full raw response from each, so we can see
 * BigBuy's actual error/success shape instead of guessing twice.
 * ---------------------------------------------------------------------------
 * HOW TO RUN (browser): khurmistore.es/debug_stock_48.php?key=khurmi2026
 * DELETE this file after use.
 */

if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/bigbuy.php';
require_once __DIR__ . '/supabase.php';
$cfg = require __DIR__ . '/config.php';
$bb  = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

$base = $cfg['bigbuy_sandbox'] ? 'https://api.sandbox.bigbuy.eu' : 'https://api.bigbuy.eu';

function raw_get(string $url, string $key): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['status' => $status, 'body' => $body, 'error' => $err];
}

function raw_post(string $url, string $key, array $payload): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['status' => $status, 'body' => $body, 'error' => $err, 'sent' => json_encode($payload)];
}

echo "==========================================\n";
echo " Debug: BigBuy stock endpoints for id=48 (cj_pid=1300776)\n";
echo " Sandbox: " . ($cfg['bigbuy_sandbox'] ? 'TRUE' : 'FALSE') . "\n";
echo "==========================================\n\n";

$rows = sb_get($cfg, 'products?id=eq.48&select=id,name,cj_pid');
$p = $rows[0] ?? null;
$cjPid = $p ? trim((string)($p['cj_pid'] ?? '')) : '1300776';
echo "Product: " . ($p['name'] ?? '(not found in Supabase, using cj_pid=1300776 from your message)') . "\n";
echo "cj_pid used: $cjPid\n\n";

// ---- TEST 1: OLD endpoint (expected to fail — confirms it's really gone) ----
echo "---- TEST 1: OLD single-product endpoint (expected 400/404) ----\n";
$old = $bb->getProductStock((int)$cjPid);
echo "GET $base/rest/catalog/productstock/$cjPid.json\n";
echo "HTTP status: " . ($old['status'] ?? '?') . "\n";
echo "Body: " . json_encode($old['data'] ?? null) . "\n\n";

// ---- TEST 2: resolve cj_pid -> sku via getProduct() (already-working method) ----
echo "---- TEST 2: resolve SKU via getProduct() ----\n";
$prodRes = $bb->getProduct((int)$cjPid, 'es');
$pd = $prodRes['data'] ?? null;
$prec = (is_array($pd) && isset($pd[0]) && is_array($pd[0])) ? $pd[0] : $pd;
$sku = is_array($prec) ? ($prec['sku'] ?? null) : null;
echo "getProduct() success: " . (($prodRes['success'] ?? false) ? 'yes' : 'no') . "\n";
echo "Resolved SKU: " . ($sku ?? '(none found)') . "\n\n";

// ---- TEST 3: NEW single-product endpoint by reference (POST) ----
echo "---- TEST 3: productsstockbyreference (POST, guessed body shape) ----\n";
if ($sku) {
    $url3 = "$base/rest/catalog/productsstockbyreference.json";
    $payload = ['products' => [['reference' => $sku]]];
    $r3 = raw_post($url3, $cfg['bigbuy_api_key'], $payload);
    echo "POST $url3\n";
    echo "Body sent: {$r3['sent']}\n";
    echo "HTTP status: {$r3['status']}\n";
    echo "Response body:\n" . ($r3['body'] === false ? '(curl error: ' . $r3['error'] . ')' : $r3['body']) . "\n\n";
} else {
    echo "SKIPPED — no SKU resolved from TEST 2.\n\n";
}

// ---- TEST 4: bulk stock endpoint (GET, paginated, all products) ----
echo "---- TEST 4: productsstock bulk endpoint (GET, page 1, small pageSize) ----\n";
$url4 = "$base/rest/catalog/productsstock.json?page=1&pageSize=20";
$r4 = raw_get($url4, $cfg['bigbuy_api_key']);
echo "GET $url4\n";
echo "HTTP status: {$r4['status']}\n";
$body4 = $r4['body'] === false ? '' : $r4['body'];
echo "Response (first 1500 chars):\n" . substr($body4, 0, 1500) . "\n\n";

// If this page happens to contain our product, show that one entry.
$decoded4 = json_decode($body4, true);
if (is_array($decoded4)) {
    foreach ($decoded4 as $item) {
        if (is_array($item) && (
            (isset($item['id']) && (string)$item['id'] === (string)$cjPid) ||
            (isset($item['sku']) && $sku && $item['sku'] === $sku)
        )) {
            echo "MATCH for our product found on this page:\n" . json_encode($item, JSON_PRETTY_PRINT) . "\n\n";
        }
    }
}

echo "==========================================\n";
echo "Done. Paste this whole output back for the actual fix.\n";
echo "==========================================\n";
