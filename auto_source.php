<?php
set_time_limit(0);
/**
 * auto_source.php - Auto-discover NEW in-stock BigBuy products (OPTIMIZED).
 * ---------------------------------------------------------------------------
 * KEY OPTIMIZATION: checks STOCK FIRST (cheap), skips out-of-stock immediately.
 * Only fetches info/price/images/video for products that ARE in stock.
 * This avoids wasting 6+ seconds on out-of-stock products.
 *
 * Also caps how many candidates it will stock-check per run (scan_limit),
 * so the script always finishes before the server timeout.
 *
 * 3 CATEGORIES (auto-rotate, remembers page per category):
 *   belleza 19650 | electronica 19653 | gaming 19685
 *
 * RUN:
 *   khurmistore.es/auto_source.php?key=khurmi2026&count=5           (auto-rotate)
 *   khurmistore.es/auto_source.php?key=khurmi2026&cat=gaming&count=5
 *
 * APPROVE: Supabase -> products -> approval_status='pending' -> set 'approved'
 * is_active is set here from the just-confirmed stock check (the same
 * boolean column bigbuy_stock_sync.php keeps updated day-to-day).
 * approval_status is NEVER set by this script — new rows insert with no
 * approval_status field at all, so they pick up the DB column's default
 * ('pending'). The old text `status` column is no longer filtered/written
 * anywhere — is_active is the single source of truth for stock-state now.
 */

if (php_sapi_name() !== 'cli') {
    if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
    header('Content-Type: text/plain; charset=utf-8');
} else {
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
    if (($_GET['key'] ?? '') !== 'khurmi2026') { exit("Forbidden\n"); }
}

@ini_set('output_buffering', 'off');
while (ob_get_level() > 0) { ob_end_flush(); }
ob_implicit_flush(true);
function flush2() { @ob_flush(); @flush(); }

require_once __DIR__ . '/bigbuy.php';
$cfg = require __DIR__ . '/config.php';
$bb  = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

$CATS  = ['belleza' => 19650, 'electronica' => 19653, 'gaming' => 19685];
$ORDER = array_keys($CATS);

$STATE_FILE = __DIR__ . '/.auto_source_state.json';
$state = @json_decode(@file_get_contents($STATE_FILE), true);
if (!is_array($state)) $state = ['pages' => [], 'last_cat_index' => -1];

$cat = trim($_GET['cat'] ?? '');
if ($cat === '') {
    $idx = ((int)($state['last_cat_index'] ?? -1) + 1) % count($ORDER);
    $cat = $ORDER[$idx];
    $state['last_cat_index'] = $idx;
}
if (!isset($CATS[$cat])) exit("Unknown cat '$cat'.\n");
$TAXONOMY = $CATS[$cat];
$COUNT      = max(1, min(10, (int)($_GET['count'] ?? 5)));
$SCAN_LIMIT = max(5, min(40, (int)($_GET['scan'] ?? 25)));  // max products to stock-check per run

$PAGE = isset($_GET['page']) ? max(1, (int)$_GET['page']) : (int)($state['pages'][$cat] ?? 1);
$PAGESIZE = 100;

echo "==========================================\n";
echo " AUTO SOURCE (optimized) | cat=$cat page=$PAGE\n";
echo " want=$COUNT  scan_limit=$SCAN_LIMIT\n";
echo "==========================================\n\n"; flush2();

echo "[1/4] Product list ...\n"; flush2();
$listRes = bbRetry($cfg, "/rest/catalog/products.json?isoCode=es&parentTaxonomy=$TAXONOMY&page=$PAGE&pageSize=$PAGESIZE");
if (!($listRes['success'] ?? false)) $listRes = bbRetry($cfg, "/rest/catalog/products.json?isoCode=es&page=$PAGE&pageSize=$PAGESIZE");
if (!($listRes['success'] ?? false)) exit("FAILED list (HTTP " . ($listRes['status'] ?? '?') . ").\n");
$list = $listRes['data'];
if (!is_array($list) || empty($list)) {
    $state['pages'][$cat] = 1; @file_put_contents($STATE_FILE, json_encode($state));
    exit("No products page $PAGE. Reset to 1.\n");
}
echo "   " . count($list) . " products.\n\n"; flush2();

echo "[2/4] Existing pids ...\n"; flush2();
$existing = supabaseGetAllPids($cfg);
if ($existing === null) exit("FAILED Supabase read.\n");
echo "   store has " . count($existing) . ".\n\n"; flush2();

echo "[3/4] New candidates ...\n"; flush2();
$candidates = [];
foreach ($list as $p) {
    if (!is_array($p)) continue;
    $pid = (string)($p['id'] ?? '');
    if ($pid === '' || isset($existing[$pid])) continue;
    $candidates[] = $p;
}
echo "   " . count($candidates) . " new (not in store).\n\n"; flush2();

echo "[4/4] Stock-check first, import in-stock as pending ...\n\n"; flush2();
$ok = 0; $scanned = 0; $lastScannedIndex = -1;

foreach ($candidates as $i => $p) {
    if ($ok >= $COUNT) break;
    if ($scanned >= $SCAN_LIMIT) { echo "\n(scan limit $SCAN_LIMIT reached)\n"; break; }
    $scanned++; $lastScannedIndex = $i;
    $bbId = (int)$p['id'];
    echo "-> $bbId stock? "; flush2();

    // STOCK FIRST (cheap gate)
    $stock = 0;
    $sRes = bbCall($bb, 'getProductStockByHandlingDays', [$bbId]);
    if ($sRes['success'] ?? false) {
        $sd = $sRes['data'];
        $srec = (is_array($sd) && isset($sd[0]) && is_array($sd[0])) ? $sd[0] : $sd;
        if (is_array($srec)) $stock = (int)($srec['stocks'][0]['quantity'] ?? ($srec['quantity'] ?? 0));
    }
    if ($stock <= 0) { echo "0 skip\n"; flush2(); sleep(5); continue; }
    echo "$stock OK, fetching... "; flush2();
    sleep(5);

    // info
    $infoRes = bbCall($bb, 'getProductInfo', [$bbId, 'es']);
    $info = ($infoRes['success'] ?? false) ? $infoRes['data'] : null;
    $rec  = (is_array($info) && isset($info[0]) && is_array($info[0])) ? $info[0] : $info;
    $name = is_array($rec) ? ($rec['name'] ?? "Product $bbId") : "Product $bbId";
    $desc = is_array($rec) ? ($rec['description'] ?? '') : '';
    sleep(2);

    // price + video
    $cost = 0.0; $videoId = null;
    $prodRes = bbCall($bb, 'getProduct', [$bbId, 'es']);
    if ($prodRes['success'] ?? false) {
        $pd = $prodRes['data'];
        $prec = (is_array($pd) && isset($pd[0]) && is_array($pd[0])) ? $pd[0] : $pd;
        $cost = (float)($prec['wholesalePrice'] ?? $prec['retailPrice'] ?? 0);
        $vr = trim((string)($prec['video'] ?? ''));
        $videoId = ($vr !== '' && $vr !== '0') ? $vr : null;
    }
    if ($cost <= 0) { echo "no price, skip\n"; flush2(); sleep(2); continue; }
    sleep(2);

    // images
    $mainImage = null;
    $imgRes = bbCall($bb, 'getProductImages', [$bbId]);
    if ($imgRes['success'] ?? false) {
        $imgData = $imgRes['data'];
        $imgRec  = (is_array($imgData) && isset($imgData[0]) && is_array($imgData[0])) ? $imgData[0] : $imgData;
        $imgList = $imgRec['images'] ?? $imgRec;
        if (is_array($imgList)) {
            $urls = [];
            foreach ($imgList as $img) {
                $u = is_array($img) ? ($img['urlMkt'] ?? $img['largeUrl'] ?? $img['url'] ?? $img['image'] ?? null) : $img;
                if ($u) { $urls[] = $u; if (count($urls) >= 7) break; }
            }
            if (!empty($urls)) $mainImage = implode(',', $urls);
        }
    }
    if (!$mainImage) { echo "no images, skip\n"; flush2(); sleep(2); continue; }
    sleep(2);

    $sell = ceil($cost * $cfg['price_multiplier']) - (1 - $cfg['price_ending']);
    $row = [
        'cj_pid' => (string)$bbId, 'cj_price' => number_format($cost, 2, '.', ''),
        'name' => $name, 'category' => $cat, 'price' => round($sell, 2),
        'description' => $desc, 'image_url' => $mainImage, 'stock' => $stock,
        'is_active' => $stock > 0, 'is_visible' => false, 'video_id' => $videoId,
    ];
    if (supabaseUpsert($cfg, 'products', $row, 'cj_pid')) {
        echo "PENDING OK (" . mb_substr($name, 0, 26) . " | EUR " . number_format($sell, 2) . " | stk $stock)\n";
        $ok++;
    } else { echo "upsert FAIL\n"; }
    flush2();
    sleep(3);
}

// advance page: if we scanned near the end of candidates, go next page
if ($lastScannedIndex >= count($candidates) - 1 || $ok < $COUNT) {
    $state['pages'][$cat] = $PAGE + 1;
} else {
    $state['pages'][$cat] = $PAGE;
}
@file_put_contents($STATE_FILE, json_encode($state));

echo "\n==========================================\n";
echo "cat=$cat  new pending=$ok  scanned=$scanned\n";
echo "next page for $cat: " . $state['pages'][$cat] . "\n";
echo "APPROVE in Supabase: approval_status='pending' -> 'approved'\n";
echo "==========================================\n";


function bbCall(BigBuy $bb, string $method, array $args): array {
    $a = 0;
    do { $res = $bb->$method(...$args); $h = $res['status'] ?? null;
        if ($h == 429) { $a++; echo "[429 20s] "; flush2(); sleep(20); }
    } while ($h == 429 && $a < 5);
    return $res;
}
function bbRetry(array $cfg, string $endpoint): array {
    $a = 0;
    do { $res = bbRequestRaw($cfg, $endpoint); $h = $res['status'] ?? null;
        if ($h == 429) { $a++; echo "[429 20s] "; flush2(); sleep(20); }
    } while ($h == 429 && $a < 5);
    return $res;
}
function bbRequestRaw(array $cfg, string $endpoint): array {
    $base = $cfg['bigbuy_sandbox'] ? 'https://api.sandbox.bigbuy.eu' : 'https://api.bigbuy.eu';
    $ch = curl_init($base . $endpoint);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $cfg['bigbuy_api_key'], 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 60]);
    $r = curl_exec($ch); $s = curl_getinfo($ch, CURLINFO_HTTP_CODE); $e = curl_error($ch); curl_close($ch);
    if ($e) return ['success' => false, 'status' => 0, 'error' => $e];
    return ['success' => $s >= 200 && $s < 300, 'status' => $s, 'data' => json_decode($r, true) ?? $r];
}
function supabaseGetAllPids(array $cfg): ?array {
    $url = rtrim($cfg['supabase_url'], '/') . "/rest/v1/products?select=cj_pid&limit=10000";
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['apikey: ' . $cfg['supabase_service_key'], 'Authorization: Bearer ' . $cfg['supabase_service_key']]]);
    $r = curl_exec($ch); $s = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($s < 200 || $s >= 300) return null;
    $rows = json_decode($r, true); if (!is_array($rows)) return null;
    $m = []; foreach ($rows as $x) if (isset($x['cj_pid'])) $m[(string)$x['cj_pid']] = true;
    return $m;
}
function supabaseUpsert(array $cfg, string $table, array $row, string $conflictCol): bool {
    $url = rtrim($cfg['supabase_url'], '/') . "/rest/v1/$table?on_conflict=$conflictCol";
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($row),
        CURLOPT_HTTPHEADER => ['apikey: ' . $cfg['supabase_service_key'],
            'Authorization: Bearer ' . $cfg['supabase_service_key'], 'Content-Type: application/json',
            'Prefer: resolution=merge-duplicates,return=minimal']]);
    curl_exec($ch); $s = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $s >= 200 && $s < 300;
}
