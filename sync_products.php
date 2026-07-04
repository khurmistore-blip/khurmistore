<?php
/**
 * sync_products.php — Sync selected BigBuy products into Supabase `products`.
 * Handles the [0]-wrapped response, uses SKU lookup (reliable), and spaces out
 * calls to respect BigBuy rate limits.
 * ---------------------------------------------------------------------------
 * HOW TO RUN (browser):
 *   khurmistore.es/sync_products.php?key=khurmi2026
 *
 * Put your product SKUs in $SKUS below (SKU is the "REF / SKU" on BigBuy pages).
 * DELETE or protect this file after use.
 */

if (php_sapi_name() !== 'cli') {
    if (($_GET['key'] ?? '') !== 'khurmi2026') {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}
set_time_limit(300);

require_once __DIR__ . '/bigbuy.php';
$cfg = require __DIR__ . '/config.php';
$bb  = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

/* ------------------------------------------------------------------ *
 *  PUT YOUR PRODUCT SKUs HERE (start with 3-5)
 *  SKU = the "REF / SKU" shown on each BigBuy product page.
 * ------------------------------------------------------------------ */
$SKUS = [
    'M0808894',
    'S55322255',
    'S55305477',
    'S55324371',
    'S55322941',
    'M0811107',
    'M0810852',
    'M0812359',
    'M0808260',
    'M0812344',
    'M0812341',
    'M0812356',
    'M0812346',
    'M0812336',
    'M0812335',
];

echo "==========================================\n";
echo " BigBuy -> Supabase SYNC\n";
echo " Sandbox: " . ($cfg['bigbuy_sandbox'] ? 'TRUE' : 'FALSE') . "\n";
echo "==========================================\n\n";

$ok = 0; $fail = 0;

foreach ($SKUS as $sku) {
    echo "-> SKU $sku ... ";

    // 1) Product info by SKU (name, description, id) — response is wrapped in [0]
    $infoRes = $bb->getProductInfoBySku($sku, 'es');
    if (!($infoRes['success'] ?? false)) {
        echo "FAIL info (HTTP " . ($infoRes['status'] ?? '?') . ")\n";
        $fail++; sleep(3); continue;
    }
    $info = $infoRes['data'];
    // unwrap [0]
    $rec = (is_array($info) && isset($info[0]) && is_array($info[0])) ? $info[0] : $info;

    $bbId = $rec['id']   ?? null;
    $name = $rec['name'] ?? ($rec['title'] ?? "Product $sku");
    $desc = $rec['description'] ?? '';

    if (!$bbId) {
        echo "FAIL (no id in response)\n"; $fail++; sleep(3); continue;
    }

    // ---- TEMPORARY DEBUG: only for the first SKU, to find which field/endpoint
    // holds the category name before we wire up real mapping. Remove once confirmed. ----
    if ($sku === $SKUS[0]) {
        echo "\n\n===== DEBUG (SKU $sku, BigBuy id $bbId) =====\n";
        echo "--- raw getProductInfoBySku() response ---\n";
        echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

        sleep(2); // rate-limit spacing before the extra debug call

        echo "--- raw GET /rest/catalog/productscategories/$bbId.json?isoCode=es ---\n";
        $catDebug = bb_raw_get($cfg, "/rest/catalog/productscategories/$bbId.json?isoCode=es");
        echo "HTTP " . $catDebug['status'] . "\n";
        echo json_encode($catDebug['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        echo "===== END DEBUG =====\n\n";
    }
    // ---- END TEMPORARY DEBUG ----

    sleep(2); // rate-limit spacing

    // 2) Price via getProduct (catalog object). May be [0]-wrapped too.
    $cost = 0.0; $sku2 = $sku;
    $prodRes = $bb->getProduct((int)$bbId, 'es');
    if ($prodRes['success'] ?? false) {
        $pd = $prodRes['data'];
        $prec = (is_array($pd) && isset($pd[0]) && is_array($pd[0])) ? $pd[0] : $pd;
        $cost = (float)($prec['wholesalePrice'] ?? $prec['retailPrice'] ?? 0);
        $sku2 = $prec['sku'] ?? $sku;
    }

    sleep(2);

    // 3) Images
    $mainImage = null;
    $imgRes = $bb->getProductImages((int)$bbId);
    if ($imgRes['success'] ?? false) {
        $imgData = $imgRes['data'];
        $imgRec  = (is_array($imgData) && isset($imgData[0]) && is_array($imgData[0])) ? $imgData[0] : $imgData;
        $imgList = $imgRec['images'] ?? $imgRec;
        if (is_array($imgList)) {
            foreach ($imgList as $img) {
                $url = is_array($img) ? ($img['url'] ?? $img['image'] ?? null) : $img;
                if ($url) { $mainImage = $url; break; }
            }
        }
    }

    sleep(2);

    // 4) Stock (optional — skip if it errors, default 1 so product shows)
    $stock = 1;
    $stockRes = $bb->getProductStock((int)$bbId);
    if ($stockRes['success'] ?? false) {
        $sd = $stockRes['data'];
        $srec = (is_array($sd) && isset($sd[0]) && is_array($sd[0])) ? $sd[0] : $sd;
        if (is_array($srec)) {
            $stock = (int)($srec['quantity']
                   ?? ($srec['stocks'][0]['quantity'] ?? ($srec[0]['quantity'] ?? 1)));
        }
    }

    // ---- pricing: x3 then .99 ----
    $sell = $cost > 0 ? (ceil($cost * $cfg['price_multiplier']) - (1 - $cfg['price_ending'])) : 0;

    // ---- row (only source columns; AI content columns untouched) ----
    $row = [
        'cj_pid'      => (string)$bbId,
        'cj_price'    => number_format($cost, 2, '.', ''),
        'name'        => $name,
        'category'    => null,
        'price'       => round($sell, 2),
        'description' => $desc,
        'image_url'   => $mainImage,
        'stock'       => $stock,
        'status'      => 'active',
    ];

    if (supabaseUpsert($cfg, 'products', $row, 'cj_pid')) {
        echo "OK  (id $bbId | " . mb_substr($name,0,30) . " | cost EUR $cost -> sell EUR " . number_format($sell,2) . " | stock $stock)\n";
        $ok++;
    } else {
        echo "FAIL (Supabase upsert)\n"; $fail++;
    }

    sleep(2); // spacing before next SKU
}

echo "\nDone. $ok synced, $fail failed.\n";
echo "Check Supabase > products, and categoria.php on the site.\n";


// Raw BigBuy GET call for endpoints not yet wrapped by the BigBuy class (debug only).
function bb_raw_get(array $cfg, string $endpoint): array
{
    $base = $cfg['bigbuy_sandbox'] ? 'https://api.sandbox.bigbuy.eu' : 'https://api.bigbuy.eu';
    $ch = curl_init($base . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $cfg['bigbuy_api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'data' => json_decode($body, true)];
}

function supabaseUpsert(array $cfg, string $table, array $row, string $conflictCol): bool
{
    $url = rtrim($cfg['supabase_url'], '/') . "/rest/v1/$table?on_conflict=$conflictCol";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => json_encode($row),
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . $cfg['supabase_service_key'],
            'Authorization: Bearer ' . $cfg['supabase_service_key'],
            'Content-Type: application/json',
            'Prefer: resolution=merge-duplicates,return=minimal',
        ],
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status >= 200 && $status < 300;
}
