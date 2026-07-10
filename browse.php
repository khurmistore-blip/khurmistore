<?php
/**
 * browse.php — Browse BigBuy taxonomies with x2.5 pricing + profit filter.
 * Optional keyword filter to find specific products (e.g. smartwatch/reloj).
 * -------------------------------------------------------------------------
 * STEP 1 - niches:   khurmistore.es/browse.php?key=khurmi2026
 * STEP 2 - products: khurmistore.es/browse.php?key=khurmi2026&tax=TAX_ID&limit=20
 * Cost range:        &min=6 &max=25
 * (Note: catalog only has ID/SKU/price, not names, so keyword filter is by SKU.)
 * DELETE after use.
 */

if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(120);

require_once __DIR__ . '/bigbuy.php';

$cfg  = require __DIR__ . '/config.php';
$base = ($cfg['bigbuy_sandbox'] ?? true) ? 'https://api.sandbox.bigbuy.eu' : 'https://api.bigbuy.eu';
$key  = $cfg['bigbuy_api_key'] ?? '';
$MULT = 2.5;

// products.json (used below) has no name/description field — only
// id/sku/price. Name comes from a SEPARATE per-SKU call, same class method
// sync_products.php already uses. Display-only: no sync, no DB writes.
$bb = new BigBuy($key, $cfg['bigbuy_sandbox'] ?? true);

/**
 * Look up one product's name by SKU via BigBuy::getProductInfoBySku().
 * Same response-unwrapping as sync_products.php (the endpoint sometimes
 * wraps the record in a single-item list). Never throws — falls back to a
 * placeholder so one failed lookup doesn't break the whole listing.
 */
function bb_product_name(BigBuy $bb, string $sku): string
{
    if ($sku === '') {
        return '(sin SKU)';
    }
    $infoRes = $bb->getProductInfoBySku($sku, 'es');
    if (!($infoRes['success'] ?? false)) {
        return '(nombre no disponible)';
    }
    $info = $infoRes['data'];
    $rec  = (is_array($info) && isset($info[0]) && is_array($info[0])) ? $info[0] : $info;
    $name = is_array($rec) ? ($rec['name'] ?? $rec['title'] ?? '') : '';
    return $name !== '' ? $name : '(nombre no disponible)';
}

function bb_get(string $url, string $key): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 90,
    ]);
    $r = curl_exec($ch); $s = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ['status' => $s, 'data' => json_decode($r, true), 'raw' => $r];
}

$tax   = $_GET['tax'] ?? '';
$limit = (int)($_GET['limit'] ?? 20);
$min   = (float)($_GET['min'] ?? 6);
$max   = (float)($_GET['max'] ?? 25);

echo "==========================================\n";
echo " BigBuy Browser  (x$MULT pricing)\n";
echo " Cost filter: EUR $min - EUR $max\n";
echo " Sandbox: " . (($cfg['bigbuy_sandbox'] ?? true) ? 'TRUE' : 'FALSE') . "\n";
echo "==========================================\n\n";

if ($tax === '') {
    $res = bb_get("$base/rest/catalog/taxonomies.json?firstLevel&isoCode=es", $key);
    echo "HTTP " . $res['status'] . "\n\n";
    if ($res['status'] == 429) { echo "Rate limit. Wait ~10 min.\n"; exit; }
    if (!is_array($res['data'])) { echo substr($res['raw'],0,600); exit; }
    echo str_pad("TAX ID",12)."NAME\n".str_repeat("-",55)."\n";
    foreach ($res['data'] as $t) echo str_pad((string)($t['id']??'?'),12).($t['name']??'?')."\n";
    echo "\nNote: for sub-categories (like Smartwatches inside Electronica),\n";
    echo "pick the parent tax id and browse; smartwatches usually sit under\n";
    echo "Electronica (19653) or Informatica (19685).\n";
    echo "\nNext: browse.php?key=khurmi2026&tax=TAX_ID&limit=20\n";
    exit;
}

echo "STEP 2 - Filtered products in taxonomy $tax:\n\n";
$res = bb_get("$base/rest/catalog/products.json?parentTaxonomy=$tax&isoCode=es", $key);
echo "HTTP " . $res['status'] . "\n\n";
if ($res['status'] == 429) { echo "Rate limit. Wait ~10 min.\n"; exit; }
if (!is_array($res['data'])) { echo substr($res['raw'],0,600); exit; }

$products = $res['data'];
echo "Category has " . count($products) . " products. Showing EUR $min-$max cost:\n\n";
echo "(Fetching each shown product's name via a separate BigBuy call — this is slower than before.)\n\n";
echo str_pad("ID",10).str_pad("SKU",14).str_pad("Cost",11).str_pad("Sell(x$MULT)",12).str_pad("Margin",10)."NAME\n";
echo str_repeat("-",90)."\n";

$shown=0; $skus=[];
foreach ($products as $p) {
    if ($shown >= $limit) break;
    if (($p['active'] ?? 1) != 1) continue;
    if (($p['condition'] ?? 'NEW') !== 'NEW') continue;
    $cost = (float)($p['wholesalePrice'] ?? 0);
    if ($cost < $min || $cost > $max) continue;
    $sell   = ceil($cost*$MULT) - 0.01;
    $margin = $sell - $cost;
    $sku    = $p['sku'] ?? '';
    $name   = bb_product_name($bb, $sku);
    echo str_pad((string)($p['id']??'?'),10).str_pad((string)$sku,14)
       . str_pad("EUR ".number_format($cost,2),11)
       . str_pad("EUR ".number_format($sell,2),12)
       . str_pad("EUR ".number_format($margin,2),10)
       . $name."\n";
    $skus[]=$sku; $shown++;
    usleep(300000); // pace the extra per-SKU name lookups, avoid BigBuy 429s
}

if ($shown===0) { echo "(No products in range. Try &min=5&max=40.)\n"; }
else {
    echo "\n----------------------------------------\n";
    echo "Copy these SKUs into sync_products.php:\n\n";
    foreach ($skus as $s) echo "    '$s',\n";
    echo "\nThen run: sync_products.php?key=khurmi2026&cat=relojes\n";
    echo "(change &cat= to the category these belong to)\n";
}
