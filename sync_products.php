<?php
set_time_limit(0);
/**
 * sync_products.php — Sync selected BigBuy products into Supabase `products`.
 * Assigns a CATEGORY to the whole batch (so products land in the right category).
 * ---------------------------------------------------------------------------
 * HOW TO RUN (browser):
 *   khurmistore.es/sync_products.php?key=khurmi2026&cat=CATEGORY_SLUG
 *
 * Example:
 *   ...&cat=auriculares    -> all SKUs below go into the "auriculares" category
 *   ...&cat=gaming         -> into "gaming"
 *   ...&cat=smart-home     -> into "smart-home"
 *
 * Put the SKUs for THIS category in $SKUS, set &cat=..., run. Then change
 * $SKUS + &cat for the next category and run again.
 *
 * DELETE or protect this file after use.
 */

if (php_sapi_name() !== 'cli') {
    if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
    header('Content-Type: text/plain; charset=utf-8');
}
set_time_limit(0); // was 300 — that silently overrode the set_time_limit(0) above it

require_once __DIR__ . '/bigbuy.php';
$cfg = require __DIR__ . '/config.php';
$bb  = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

/* ------------------------------------------------------------------ *
 *  Category for THIS run (from URL ?cat=...). Applied to all SKUs below.
 * ------------------------------------------------------------------ */
$CATEGORY = trim($_GET['cat'] ?? '');
if ($CATEGORY === '') {
    exit("Add &cat=CATEGORY_SLUG to the URL (e.g. &cat=auriculares).\n");
}

/* ------------------------------------------------------------------ *
 *  SKUs for THIS category (replace for each category run)
 * ------------------------------------------------------------------ */
$SKUS = [
    'V0103944',
];

/* ------------------------------------------------------------------ *
 *  Batch/pagination support (avoids hitting the server's max execution
 *  time when syncing large SKU lists with sleep() rate-limit delays).
 * ------------------------------------------------------------------ */
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$count = isset($_GET['count']) ? (int)$_GET['count'] : 5;
$batch = array_slice($SKUS, $start, $count);

echo "==========================================\n";
echo " BigBuy -> Supabase SYNC\n";
echo " Category for this run: $CATEGORY\n";
echo " Sandbox: " . ($cfg['bigbuy_sandbox'] ? 'TRUE' : 'FALSE') . "\n";
echo "==========================================\n\n";

$ok = 0; $fail = 0;

foreach ($batch as $sku) {
    echo "-> SKU $sku ... ";

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
        echo "FAIL info (HTTP " . ($infoRes['status'] ?? '?') . ")\n"; $fail++; sleep(3); continue;
    }
    $info = $infoRes['data'];
    $rec  = (is_array($info) && isset($info[0]) && is_array($info[0])) ? $info[0] : $info;

    $bbId = $rec['id']   ?? null;
    $name = $rec['name'] ?? ($rec['title'] ?? "Product $sku");
    $desc = $rec['description'] ?? '';
    if (!$bbId) { echo "FAIL (no id)\n"; $fail++; sleep(3); continue; }

    sleep(2);

    // price
    $cost = 0.0;
    $videoId = null;
    $attempts = 0;
    do {
        $prodRes  = $bb->getProduct((int)$bbId, 'es');
        $httpCode = $prodRes['status'] ?? null;
        if ($httpCode == 429) {
            $attempts++;
            echo "   429 rate-limited (price), waiting 20s (retry $attempts/5)...\n";
            @ob_flush(); @flush();
            sleep(20);
        }
    } while ($httpCode == 429 && $attempts < 5);
    if ($prodRes['success'] ?? false) {
        $pd = $prodRes['data'];
        $prec = (is_array($pd) && isset($pd[0]) && is_array($pd[0])) ? $pd[0] : $pd;
        $cost = (float)($prec['wholesalePrice'] ?? $prec['retailPrice'] ?? 0);
        // BigBuy returns "0" (string) or null when a product has no video —
        // both must become NULL here, never the literal string "0".
        $videoRaw = trim((string)($prec['video'] ?? ''));
        $videoId  = ($videoRaw !== '' && $videoRaw !== '0') ? $videoRaw : null;
    }
    sleep(2);

    // image — fetch ALL images (up to 7), comma-separated, preferring the
    // largest/full-size URL variant per image over a thumbnail field.
    // On failure (429 exhausted, 404, empty list) $mainImage simply stays
    // null, same graceful behavior as the old single-image code — never a crash.
    $mainImage = null;
    $attempts = 0;
    do {
        $imgRes   = $bb->getProductImages((int)$bbId);
        $httpCode = $imgRes['status'] ?? null;
        if ($httpCode == 429) {
            $attempts++;
            echo "   429 rate-limited (images), waiting 20s (retry $attempts/5)...\n";
            @ob_flush(); @flush();
            sleep(20);
        }
    } while ($httpCode == 429 && $attempts < 5);

    if ($imgRes['success'] ?? false) {
        $imgData = $imgRes['data'];
        $imgRec  = (is_array($imgData) && isset($imgData[0]) && is_array($imgData[0])) ? $imgData[0] : $imgData;
        $imgList = $imgRec['images'] ?? $imgRec;
        if (is_array($imgList)) {
            $urls = [];
            foreach ($imgList as $img) {
                $url = is_array($img)
                    ? ($img['urlMkt'] ?? $img['largeUrl'] ?? $img['url'] ?? $img['image'] ?? null)
                    : $img;
                if ($url) {
                    $urls[] = $url;
                    if (count($urls) >= 7) break;
                }
            }
            if (!empty($urls)) {
                $mainImage = implode(',', $urls);
            }
        }
    }
    sleep(5);

    // stock
    $stock = 1;
    $attempts = 0;
    do {
        $stockRes = $bb->getProductStockByHandlingDays((int)$bbId);
        $httpCode = $stockRes['status'] ?? null;
        if ($httpCode == 429) {
            $attempts++;
            echo "   429 rate-limited (stock), waiting 20s (retry $attempts/5)...\n";
            @ob_flush(); @flush();
            sleep(20);
        }
    } while ($httpCode == 429 && $attempts < 5);
    if ($stockRes['success'] ?? false) {
        $sd = $stockRes['data'];
        $srec = (is_array($sd) && isset($sd[0]) && is_array($sd[0])) ? $sd[0] : $sd;
        if (is_array($srec)) {
            $stock = (int)($srec['quantity'] ?? ($srec['stocks'][0]['quantity'] ?? ($srec[0]['quantity'] ?? 1)));
        }
    }

    $sell = $cost > 0 ? (ceil($cost * $cfg['price_multiplier']) - (1 - $cfg['price_ending'])) : 0;

    $row = [
        'cj_pid'      => (string)$bbId,
        'cj_price'    => number_format($cost, 2, '.', ''),
        'name'        => $name,
        'category'    => $CATEGORY,          // <-- category assigned here
        'price'       => round($sell, 2),
        'description' => $desc,
        'image_url'   => $mainImage,
        'stock'       => $stock,
        'video_id'    => $videoId,
    ];

    if (supabaseUpsert($cfg, 'products', $row, 'cj_pid')) {
        echo "OK  ($CATEGORY | " . mb_substr($name,0,28) . " | EUR " . number_format($sell,2) . ")\n";
        $ok++;
    } else {
        echo "FAIL (Supabase upsert)\n"; $fail++;
    }
    sleep(5);
}

$nextStart = $start + count($batch);

echo "\n==========================================\n";
echo "Imported: $ok\n";
echo "Failed:   $fail\n";
echo "Batch done. Processed SKUs $start to $nextStart of " . count($SKUS) . " total.\n";
if ($nextStart < count($SKUS)) {
    echo "\nNext run:\n";
    echo "  sync_products.php?key=khurmi2026&cat=$CATEGORY&start=$nextStart&count=$count\n";
} else {
    echo "\nAll SKUs processed for category '$CATEGORY'.\n";
}
echo "==========================================\n";


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
