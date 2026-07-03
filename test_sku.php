<?php
/**
 * test_sku.php — Test a BigBuy product by SKU (the code on the product page).
 * SKU is reliable: it's shown on every BigBuy product page as "REF / SKU".
 * This avoids the heavy full-catalog call that hits rate limits.
 * -------------------------------------------------------------------------
 * Open:  khurmistore.es/test_sku.php?key=khurmi2026&sku=V0710248
 *
 * DELETE this file after use.
 */

if (($_GET['key'] ?? '') !== 'khurmi2026') {
    http_response_code(403);
    exit('Forbidden');
}
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(60);

require_once __DIR__ . '/bigbuy.php';
$cfg = require __DIR__ . '/config.php';

$sku = trim($_GET['sku'] ?? '');
if ($sku === '') { exit("Add ?sku=V0710248 to the URL\n"); }

$bb = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

echo "==========================================\n";
echo " Test product by SKU: $sku\n";
echo " Sandbox: " . ($cfg['bigbuy_sandbox'] ? 'TRUE' : 'FALSE') . "\n";
echo "==========================================\n\n";

// 1) Get product info by SKU -> gives us the real BigBuy product ID + name
echo "[1] getProductInfoBySku...\n";
$infoRes = $bb->getProductInfoBySku($sku, 'es');

if (!($infoRes['success'] ?? false)) {
    echo "    FAILED (HTTP " . ($infoRes['status'] ?? '?') . ")\n";
    echo "    " . substr(json_encode($infoRes['data'] ?? $infoRes), 0, 300) . "\n";
    echo "    (If 429 = rate limit, wait 10 min and retry.)\n";
    exit;
}

$info = $infoRes['data'];
echo "    RAW response:\n";
print_r($info);
echo "\n";

// try to find the real id + name in the response
$realId = null; $name = null;
if (is_array($info)) {
    $realId = $info['id']   ?? ($info['sku'] ?? null);
    $name   = $info['name'] ?? ($info['title'] ?? null);
    // some responses wrap in [0]
    if ($realId === null && isset($info[0])) {
        $realId = $info[0]['id']   ?? null;
        $name   = $info[0]['name'] ?? null;
    }
}

echo "----------------------------------------\n";
echo "Real BigBuy product ID: " . ($realId ?? '(not found - see RAW above)') . "\n";
echo "Name: " . ($name ?? '(not found)') . "\n";
echo "----------------------------------------\n\n";

if ($realId) {
    echo "Now you can sync this product using ID: $realId\n";
    echo "Test full data:\n";
    echo "  test_bigbuy.php?key=khurmi2026&id=$realId\n";
} else {
    echo "Could not auto-detect the ID. Send me the RAW response above\n";
    echo "and I'll map the correct field.\n";
}
