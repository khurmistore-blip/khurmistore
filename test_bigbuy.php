<?php
/**
 * test_bigbuy.php — ONE-PRODUCT TEST (browser se chalao)
 * ------------------------------------------------------
 * Kholo:  khurmistore.es/test_bigbuy.php?key=khurmi2026&id=1500770
 *
 * Yeh dikhata hai:
 *  1) BigBuy se product ka data aa raha hai ya nahi (raw)
 *  2) Field mapping theek hai ya nahi
 *  3) Supabase mein product insert hua ya nahi
 *
 * Test ke baad is file ko DELETE kar dena (security).
 */

// ---- simple protection ----
if (($_GET['key'] ?? '') !== 'khurmi2026') {
    http_response_code(403);
    exit('Forbidden — add ?key=khurmi2026 to the URL');
}

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/bigbuy.php';
$cfg = require __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 1500770);

echo "==========================================\n";
echo " BigBuy → Supabase TEST\n";
echo " Product ID: $id\n";
echo " Sandbox mode: " . ($cfg['bigbuy_sandbox'] ? 'TRUE' : 'FALSE') . "\n";
echo "==========================================\n\n";

$bb = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

// ---- STEP 1: connection test ----
echo "[1] Connection test... ";
$test = $bb->testConnection();
if ($test['success']) {
    echo "OK (BigBuy API key working)\n\n";
} else {
    echo "FAILED (status " . ($test['status'] ?? '?') . ")\n";
    echo "    -> API key galat hai ya sandbox/production mismatch.\n";
    echo "    Response: " . substr(json_encode($test['data'] ?? $test), 0, 300) . "\n";
    exit;
}

// ---- STEP 2: fetch product ----
echo "[2] Fetching product data...\n";
$catalog = $bb->getProduct($id)['data']       ?? null;
$info    = $bb->getProductInfo($id)['data']   ?? null;
$stockR  = $bb->getProductStock($id)['data']  ?? null;
$imagesR = $bb->getProductImages($id)['data'] ?? null;

echo "    getProduct:       " . (is_array($catalog) ? "OK" : "no data") . "\n";
echo "    getProductInfo:   " . (is_array($info) ? "OK" : "no data") . "\n";
echo "    getProductStock:  " . (is_array($stockR) ? "OK" : "no data") . "\n";
echo "    getProductImages: " . (is_array($imagesR) ? "OK" : "no data") . "\n\n";

if (!$catalog || !$info) {
    echo "PROBLEM: product data nahi mila. Wajah:\n";
    echo "  - Yeh product ID sandbox mein maujood nahi (sandbox=true) — production try karo\n";
    echo "  - Ya field names alag hain. Neeche RAW data dekho:\n\n";
    echo "=== RAW getProduct ===\n";  print_r($catalog);
    echo "\n=== RAW getProductInfo ===\n"; print_r($info);
    exit;
}

// ---- STEP 3: show what we extracted ----
$cost     = (float)($catalog['wholesalePrice'] ?? $catalog['retailPrice'] ?? 0);
$name     = $info['name']        ?? ($info['title'] ?? "Product $id");
$descHtml = $info['description']  ?? '';
$category = $catalog['category']  ?? ($info['category'] ?? null);

$stock = 0;
if (is_array($stockR)) {
    $stock = (int)($stockR['quantity']
           ?? ($stockR['stocks'][0]['quantity'] ?? ($stockR[0]['quantity'] ?? 0)));
}

$mainImage = null;
if (is_array($imagesR)) {
    $imgList = $imagesR['images'] ?? $imagesR;
    foreach ($imgList as $img) {
        $url = is_array($img) ? ($img['url'] ?? $img['image'] ?? null) : $img;
        if ($url) { $mainImage = $url; break; }
    }
}

$sell = ceil($cost * $cfg['price_multiplier']) - (1 - $cfg['price_ending']);

echo "[3] Extracted data (mapping check):\n";
echo "    Name:      $name\n";
echo "    Category:  " . ($category ?? '(none)') . "\n";
echo "    Cost:      EUR $cost\n";
echo "    Sell (x3): EUR " . number_format($sell, 2) . "\n";
echo "    Stock:     $stock\n";
echo "    Image:     " . ($mainImage ?? '(none)') . "\n\n";

if ($cost <= 0) {
    echo "WARNING: price 0 aaya — 'wholesalePrice' field ka naam alag ho sakta hai.\n";
    echo "=== RAW getProduct (price field dhundo) ===\n";
    print_r($catalog);
    echo "\n";
}

// ---- STEP 4: insert into Supabase ----
echo "[4] Inserting into Supabase 'products' table...\n";

$row = [
    'cj_pid'      => (string)$id,
    'cj_price'    => number_format($cost, 2, '.', ''),
    'name'        => $name,
    'category'    => $category,
    'price'       => round($sell, 2),
    'description' => $descHtml,
    'image_url'   => $mainImage,
    'stock'       => $stock,
    'status'      => $stock > 0 ? 'active' : 'inactive',
];

$url = rtrim($cfg['supabase_url'], '/') . "/rest/v1/products?on_conflict=cj_pid";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_POSTFIELDS     => json_encode($row),
    CURLOPT_HTTPHEADER     => [
        'apikey: ' . $cfg['supabase_service_key'],
        'Authorization: Bearer ' . $cfg['supabase_service_key'],
        'Content-Type: application/json',
        'Prefer: resolution=merge-duplicates,return=representation',
    ],
]);
$resp   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status >= 200 && $status < 300) {
    echo "    SUCCESS! Product Supabase mein aa gaya. (HTTP $status)\n\n";
    echo "==========================================\n";
    echo " TEST PASSED\n";
    echo " Ab Supabase Table Editor > products mein\n";
    echo " yeh product dikhega. categoria.php pe bhi.\n";
    echo "==========================================\n";
} else {
    echo "    FAILED (HTTP $status)\n";
    echo "    Response: $resp\n\n";
    echo "    Common wajah:\n";
    echo "    - service_key galat (Legacy service_role honi chahiye)\n";
    echo "    - stock column add nahi hua (bigbuy_prep.sql chalao)\n";
    echo "    - cj_pid unique index nahi bana\n";
}
