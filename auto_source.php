<?php
set_time_limit(0);
/**
 * auto_source.php — Auto-discover NEW BigBuy products for KhurmiStore.
 * ---------------------------------------------------------------------------
 * WHAT IT DOES:
 *   1. Fetches BigBuy products for a given taxonomy (category).
 *   2. Skips products already in Supabase (matched by cj_pid).
 *   3. For up to N new candidates: fetches info, price, images, video, REAL
 *      stock (via the WORKING getProductStockByHandlingDays endpoint).
 *   4. Inserts them into Supabase with status='pending' — they will NOT show
 *      on the site until you approve them (change status to 'active').
 *
 * HOW TO RUN (browser):
 *   khurmistore.es/auto_source.php?key=khurmi2026&taxonomy=TAXONOMY_ID&cat=CATEGORY_SLUG&count=3
 *
 *   Example:
 *   ...?key=khurmi2026&taxonomy=2507&cat=belleza&count=3
 *
 * FIND TAXONOMY IDs (run once, pick the IDs you want):
 *   khurmistore.es/auto_source.php?key=khurmi2026&list_taxonomies=1
 *
 * CRON (daily auto, Hostinger):
 *   php /home/USER/public_html/auto_source.php key=khurmi2026 taxonomy=2507 cat=belleza count=3
 *   (or use the browser URL with wget/curl in Hostinger cron)
 *
 * APPROVE PRODUCTS:
 *   Supabase -> products -> filter status='pending' -> change to 'active'.
 *
 * DELETE or protect this file when not in use.
 */

if (php_sapi_name() !== 'cli') {
    if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
    header('Content-Type: text/plain; charset=utf-8');
} else {
    // allow CLI: php auto_source.php key=khurmi2026 taxonomy=123 cat=belleza count=3
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
    if (($_GET['key'] ?? '') !== 'khurmi2026') { exit("Forbidden\n"); }
}

require_once __DIR__ . '/bigbuy.php';
$cfg = require __DIR__ . '/config.php';
$bb  = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

/* ------------------------------------------------------------------ *
 *  MODE 1: list taxonomies (run once to find your category IDs)
 * ------------------------------------------------------------------ */
if (isset($_GET['list_taxonomies'])) {
    echo "Fetching BigBuy taxonomies (categories)...\n\n";
    $res = bbRequestRaw($cfg, '/rest/catalog/taxonomies.json?isoCode=es&firstLevel');
    if (!($res['success'] ?? false)) {
        // fallback: full tree
        $res = bbRequestRaw($cfg, '/rest/catalog/taxonomies.json?isoCode=es');
    }
    if (!($res['success'] ?? false)) {
        exit("FAILED to fetch taxonomies (HTTP " . ($res['status'] ?? '?') . ")\n");
    }
    $taxos = $res['data'];
    if (!is_array($taxos)) exit("Unexpected response.\n");
    foreach ($taxos as $t) {
        if (!is_array($t)) continue;
        $id   = $t['id'] ?? '?';
        $name = $t['name'] ?? '?';
        $parent = $t['parentTaxonomy'] ?? ($t['parent'] ?? '');
        echo str_pad($id, 8) . " | " . $name . ($parent ? "  (parent: $parent)" : "") . "\n";
    }
    echo "\nPick a taxonomy ID and run:\n";
    echo "auto_source.php?key=khurmi2026&taxonomy=ID&cat=CATEGORY_SLUG&count=3\n";
    exit;
}

/* ------------------------------------------------------------------ *
 *  MODE 2: discover + import (main mode)
 * ------------------------------------------------------------------ */
$TAXONOMY = trim($_GET['taxonomy'] ?? '');
$CATEGORY = trim($_GET['cat'] ?? '');
$COUNT    = max(1, min(5, (int)($_GET['count'] ?? 3)));   // 1..5 per run (rate-limit safe)

if ($TAXONOMY === '' || $CATEGORY === '') {
    exit("Usage: auto_source.php?key=khurmi2026&taxonomy=TAXONOMY_ID&cat=CATEGORY_SLUG&count=3\n" .
         "First run ...&list_taxonomies=1 to find taxonomy IDs.\n");
}

echo "==========================================\n";
echo " KhurmiStore AUTO SOURCE (BigBuy -> pending)\n";
echo " Taxonomy: $TAXONOMY  ->  site category: $CATEGORY\n";
echo " Max new products this run: $COUNT\n";
echo " Sandbox: " . ($cfg['bigbuy_sandbox'] ? 'TRUE' : 'FALSE') . "\n";
echo "==========================================\n\n";

/* ---- 1) Get product list for this taxonomy ----------------------- */
echo "[1/4] Fetching product list for taxonomy $TAXONOMY ...\n";
$listRes = bbRetry($cfg, "/rest/catalog/products.json?isoCode=es&parentTaxonomy=$TAXONOMY");
if (!($listRes['success'] ?? false)) {
    exit("FAILED product list (HTTP " . ($listRes['status'] ?? '?') . "). " .
         "If 404/400, taxonomy filter may not be supported on your plan — tell Claude, we'll switch strategy.\n");
}
$list = $listRes['data'];
if (!is_array($list) || empty($list)) {
    exit("No products returned for taxonomy $TAXONOMY.\n");
}
echo "   BigBuy returned " . count($list) . " products in this taxonomy.\n\n";

/* ---- 2) Get existing cj_pids from Supabase ------------------------ */
echo "[2/4] Fetching existing products from Supabase ...\n";
$existing = supabaseGetAllPids($cfg);
if ($existing === null) {
    exit("FAILED to read existing products from Supabase.\n");
}
echo "   Store already has " . count($existing) . " products.\n\n";

/* ---- 3) Pick new candidates --------------------------------------- */
echo "[3/4] Selecting up to $COUNT NEW products (not in store, in stock per catalog)...\n";
$candidates = [];
foreach ($list as $p) {
    if (!is_array($p)) continue;
    $pid = (string)($p['id'] ?? '');
    if ($pid === '' || isset($existing[$pid])) continue;      // already in store
    // prefer items the catalog itself marks as having stock, if field present
    if (isset($p['stock']) && (int)$p['stock'] <= 0) continue;
    $candidates[] = $p;
    if (count($candidates) >= $COUNT * 3) break;              // small buffer; final stock check below
}
if (empty($candidates)) {
    exit("No NEW products found in this taxonomy — everything is already in your store (or out of stock).\n");
}
echo "   Candidates found: " . count($candidates) . "\n\n";

/* ---- 4) Import each candidate as PENDING -------------------------- */
echo "[4/4] Importing (max $COUNT) as status='pending' ...\n\n";
$ok = 0; $fail = 0;

foreach ($candidates as $p) {
    if ($ok >= $COUNT) break;
    $bbId = (int)$p['id'];
    $sku  = (string)($p['sku'] ?? '');
    echo "-> BigBuy ID $bbId (SKU $sku) ... ";

    // -- info (name/description) --
    $infoRes = bbCall($bb, 'getProductInfo', [$bbId, 'es']);
    if (!($infoRes['success'] ?? false)) { echo "SKIP (no info)\n"; $fail++; sleep(3); continue; }
    $info = $infoRes['data'];
    $rec  = (is_array($info) && isset($info[0]) && is_array($info[0])) ? $info[0] : $info;
    $name = $rec['name'] ?? ($rec['title'] ?? "Product $bbId");
    $desc = $rec['description'] ?? '';
    sleep(2);

    // -- price + video --
    $cost = 0.0; $videoId = null;
    $prodRes = bbCall($bb, 'getProduct', [$bbId, 'es']);
    if ($prodRes['success'] ?? false) {
        $pd   = $prodRes['data'];
        $prec = (is_array($pd) && isset($pd[0]) && is_array($pd[0])) ? $pd[0] : $pd;
        $cost = (float)($prec['wholesalePrice'] ?? $prec['retailPrice'] ?? 0);
        $videoRaw = trim((string)($prec['video'] ?? ''));
        $videoId  = ($videoRaw !== '' && $videoRaw !== '0') ? $videoRaw : null;
        if ($sku === '') $sku = (string)($prec['sku'] ?? '');
    }
    if ($cost <= 0) { echo "SKIP (no price)\n"; $fail++; sleep(3); continue; }
    sleep(2);

    // -- images (up to 7, comma separated) --
    $mainImage = null;
    $imgRes = bbCall($bb, 'getProductImages', [$bbId]);
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
                if ($url) { $urls[] = $url; if (count($urls) >= 7) break; }
            }
            if (!empty($urls)) $mainImage = implode(',', $urls);
        }
    }
    if (!$mainImage) { echo "SKIP (no images)\n"; $fail++; sleep(3); continue; }
    sleep(2);

    // -- REAL stock via the WORKING endpoint (5s pacing required) --
    $stock = 0;
    $stockRes = bbCall($bb, 'getProductStockByHandlingDays', [$bbId]);
    if ($stockRes['success'] ?? false) {
        $sd   = $stockRes['data'];
        $srec = (is_array($sd) && isset($sd[0]) && is_array($sd[0])) ? $sd[0] : $sd;
        if (is_array($srec)) {
            $stock = (int)($srec['stocks'][0]['quantity'] ?? ($srec['quantity'] ?? 0));
        }
    }
    if ($stock <= 0) { echo "SKIP (no stock: $stock)\n"; $fail++; sleep(5); continue; }
    sleep(5); // rate limit: 1 req / 5 sec on this endpoint

    // -- price formula (same as sync_products.php) --
    $sell = ceil($cost * $cfg['price_multiplier']) - (1 - $cfg['price_ending']);

    $row = [
        'cj_pid'      => (string)$bbId,
        'cj_price'    => number_format($cost, 2, '.', ''),
        'name'        => $name,
        'category'    => $CATEGORY,
        'price'       => round($sell, 2),
        'description' => $desc,
        'image_url'   => $mainImage,
        'stock'       => $stock,
        'status'      => 'pending',          // <-- NOT live until you approve
        'video_id'    => $videoId,
    ];

    if (supabaseUpsert($cfg, 'products', $row, 'cj_pid')) {
        echo "PENDING OK  (" . mb_substr($name, 0, 30) . " | EUR " . number_format($sell, 2) . " | stock $stock)\n";
        $ok++;
    } else {
        echo "FAIL (Supabase upsert)\n"; $fail++;
    }
    sleep(3);
}

echo "\n==========================================\n";
echo "New PENDING products: $ok\n";
echo "Skipped/failed:       $fail\n";
echo "\nAPPROVE: Supabase -> products -> status='pending' -> set to 'active'\n";
echo "==========================================\n";


/* =================================================================== *
 *  Helpers
 * =================================================================== */

/** Call a BigBuy method with 429 retry (up to 5x, 20s wait). */
function bbCall(BigBuy $bb, string $method, array $args): array
{
    $attempts = 0;
    do {
        $res  = $bb->$method(...$args);
        $http = $res['status'] ?? null;
        if ($http == 429) {
            $attempts++;
            echo "   429 rate-limited ($method), waiting 20s (retry $attempts/5)...\n";
            @ob_flush(); @flush();
            sleep(20);
        }
    } while ($http == 429 && $attempts < 5);
    return $res;
}

/** Raw BigBuy GET with 429 retry (for endpoints not in the class). */
function bbRetry(array $cfg, string $endpoint): array
{
    $attempts = 0;
    do {
        $res  = bbRequestRaw($cfg, $endpoint);
        $http = $res['status'] ?? null;
        if ($http == 429) {
            $attempts++;
            echo "   429 rate-limited, waiting 20s (retry $attempts/5)...\n";
            @ob_flush(); @flush();
            sleep(20);
        }
    } while ($http == 429 && $attempts < 5);
    return $res;
}

function bbRequestRaw(array $cfg, string $endpoint): array
{
    $base = $cfg['bigbuy_sandbox'] ? 'https://api.sandbox.bigbuy.eu' : 'https://api.bigbuy.eu';
    $ch = curl_init($base . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $cfg['bigbuy_api_key'],
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 120,
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);
    if ($error) return ['success' => false, 'status' => 0, 'error' => $error];
    return [
        'success' => $status >= 200 && $status < 300,
        'status'  => $status,
        'data'    => json_decode($response, true) ?? $response,
    ];
}

/** All existing cj_pids from Supabase as a lookup map [pid => true]. */
function supabaseGetAllPids(array $cfg): ?array
{
    $url = rtrim($cfg['supabase_url'], '/') . "/rest/v1/products?select=cj_pid&limit=10000";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . $cfg['supabase_service_key'],
            'Authorization: Bearer ' . $cfg['supabase_service_key'],
        ],
    ]);
    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) return null;
    $rows = json_decode($resp, true);
    if (!is_array($rows)) return null;
    $map = [];
    foreach ($rows as $r) {
        if (isset($r['cj_pid'])) $map[(string)$r['cj_pid']] = true;
    }
    return $map;
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
