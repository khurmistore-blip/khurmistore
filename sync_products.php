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
set_time_limit(300);

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
    'V0103977','S0900163','V0103319','V0103738','V0103362',
    'V0103294','D2700304','D2700062','S0900104','V0103754',
    'V0104033','V0103416','S0900157','S4312849','V0103739',
    'V0103378','V0103402','D2700327','S05150194','D2700341',
    'D2700068','V0103737','V3401658','S05120338','S3631481',
    'S05111020','S05103065','S0594738','S0576188','D2700311',
    'D2700295','D2700305','D2700301','V0104069','D2700058',
    'D2700066','D2700056','S05148068','S05128195','S05145587',
    'S05145590','S0900151','S0900083','S0464092','S05131359',
    'V0103944','S05127438','S05128499','V0103740','V0103744',
    'S05120796','V0103854','S0454260','V0103840','S3623621',
    'S05101429','S05100393','S05102927','S4258284','V0103208',
    'S0563513','S0578200','V0103107','S0572865','S0572636',
    'V0101241','S0563924','S0543123','V0103966','S0900156',
    'V0104021','V0103750','V0103157','D2700047','D2700310',
    'D2700345','S05150312','V0103749','V0103734','V0104025',
    'S0900158','S4312907','V3401092','V0710087','V0100904',
    'S05148975','S05114155','D2700342','D2700051','V0103743',
    'S0598574','S0590804','S4510356','S0572219','S4604070',
    'S0586380','S0565277','V0103963','S0900155','V0104115',
];

/* ------------------------------------------------------------------ *
 *  Batch/pagination support (avoids hitting the server's max execution
 *  time when syncing large SKU lists with sleep() rate-limit delays).
 * ------------------------------------------------------------------ */
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$count = isset($_GET['count']) ? (int)$_GET['count'] : 20;
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
            echo "   429 rate-limited, waiting 15s (retry $attempts/3)...\n";
            @ob_flush(); @flush();
            sleep(15);
        }
    } while ($httpCode == 429 && $attempts < 3);

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
    $prodRes = $bb->getProduct((int)$bbId, 'es');
    if ($prodRes['success'] ?? false) {
        $pd = $prodRes['data'];
        $prec = (is_array($pd) && isset($pd[0]) && is_array($pd[0])) ? $pd[0] : $pd;
        $cost = (float)($prec['wholesalePrice'] ?? $prec['retailPrice'] ?? 0);
    }
    sleep(2);

    // image
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

    // stock
    $stock = 1;
    $stockRes = $bb->getProductStock((int)$bbId);
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
        'status'      => 'active',
    ];

    if (supabaseUpsert($cfg, 'products', $row, 'cj_pid')) {
        echo "OK  ($CATEGORY | " . mb_substr($name,0,28) . " | EUR " . number_format($sell,2) . ")\n";
        $ok++;
    } else {
        echo "FAIL (Supabase upsert)\n"; $fail++;
    }
    sleep(5);
}

echo "\nDone. $ok synced into '$CATEGORY', $fail failed.\n";
echo "Batch done. Processed SKUs $start to " . ($start + count($batch)) . " of " . count($SKUS) . " total.\n";


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
