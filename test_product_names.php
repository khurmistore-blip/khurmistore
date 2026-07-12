<?php
declare(strict_types=1);

/**
 * test_product_names.php — Confirms getProductInfoBySku() (the SAME method
 * sync_products.php uses) returns real names for specific SKUs, before
 * committing to a 15-product import. Uses the exact same extraction logic
 * as sync_products.php's main loop, so what's printed here is exactly what
 * would land in the `name` column if these SKUs were imported.
 * ---------------------------------------------------------------------------
 * HOW TO RUN (browser): khurmistore.es/test_product_names.php?key=khurmi2026
 * DELETE this file after use. Does NOT write to Supabase — read-only.
 */

if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(120);

require_once __DIR__ . '/bigbuy.php';
$cfg = require __DIR__ . '/config.php';
$bb  = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

$SKUS = [
    'S55320309' => 'DisplayPort adapter (expected)',
    'M0809485'  => 'Xiaomi Router (expected)',
    'S55324450' => 'Neomounts tablet stand (expected)',
];

echo "==========================================\n";
echo " Test: getProductInfoBySku() name extraction\n";
echo " Sandbox: " . ($cfg['bigbuy_sandbox'] ? 'TRUE' : 'FALSE') . "\n";
echo "==========================================\n\n";

foreach ($SKUS as $sku => $expected) {
    echo "-> SKU $sku (expected: $expected)\n";

    // Same 429 retry pattern sync_products.php already uses for this call.
    $attempts = 0;
    do {
        $infoRes  = $bb->getProductInfoBySku($sku, 'es');
        $httpCode = $infoRes['status'] ?? null;
        if ($httpCode == 429) {
            $attempts++;
            echo "   429 rate-limited, waiting 20s (retry $attempts/5)...\n";
            @ob_flush(); @flush();
            sleep(20);
        }
    } while ($httpCode == 429 && $attempts < 5);

    if (!($infoRes['success'] ?? false)) {
        echo "   FAIL — HTTP " . ($infoRes['status'] ?? '?') . "\n";
        echo "   Raw response: " . json_encode($infoRes['data'] ?? null, JSON_UNESCAPED_UNICODE) . "\n\n";
        sleep(3);
        continue;
    }

    $info = $infoRes['data'];
    $rec  = (is_array($info) && isset($info[0]) && is_array($info[0])) ? $info[0] : $info;

    // EXACT same extraction as sync_products.php:104-105 — this is precisely
    // what would be written to the `name` column if this SKU were imported.
    $bbId = $rec['id']   ?? null;
    $name = $rec['name'] ?? ($rec['title'] ?? "Product $sku");

    echo "   HTTP 200 OK\n";
    echo "   BigBuy id: " . ($bbId ?? '(missing)') . "\n";
    echo "   Raw 'name' field:  " . (isset($rec['name'])  ? json_encode($rec['name'], JSON_UNESCAPED_UNICODE)  : '(not present)') . "\n";
    echo "   Raw 'title' field: " . (isset($rec['title']) ? json_encode($rec['title'], JSON_UNESCAPED_UNICODE) : '(not present)') . "\n";
    echo "   ==> sync_products.php WOULD STORE: \"" . $name . "\"\n\n";

    sleep(3); // pacing between calls
}

echo "==========================================\n";
echo "Done. Nothing written to Supabase — read-only test.\n";
echo "==========================================\n";
