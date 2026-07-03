<?php
/**
 * list_categories.php — Show BigBuy categories (id + name) so we can pick the
 * ones matching KhurmiStore's niches: tech accessories, smart home, gaming.
 * Single light call (categories only) — safe for rate limits.
 * ---------------------------------------------------------------------------
 * Open:  khurmistore.es/list_categories.php?key=khurmi2026
 * Filter by name: &search=audio
 * DELETE after use.
 */

if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(90);

require_once __DIR__ . '/bigbuy.php';
$cfg = require __DIR__ . '/config.php';
$bb  = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

$search = strtolower(trim($_GET['search'] ?? ''));

echo "==========================================\n";
echo " BigBuy Categories\n";
echo " Sandbox: " . ($cfg['bigbuy_sandbox'] ? 'TRUE' : 'FALSE') . "\n";
echo "==========================================\n\n";

$res = $bb->getCategories('es');
if (!($res['success'] ?? false)) {
    echo "FAILED (HTTP " . ($res['status'] ?? '?') . ")\n";
    echo substr(json_encode($res['data'] ?? $res), 0, 400) . "\n";
    echo "(If 429 = rate limit, wait and retry.)\n";
    exit;
}

$cats = $res['data'];
// unwrap if needed
if (is_array($cats) && isset($cats[0]) && !isset($cats[0]['id']) && isset($cats['categories'])) {
    $cats = $cats['categories'];
}
if (!is_array($cats)) { print_r($cats); exit; }

echo "Total categories: " . count($cats) . "\n";
echo ($search ? "Filter: '$search'\n" : "") . "\n";
echo str_pad("CAT ID", 12) . "NAME\n";
echo str_repeat("-", 55) . "\n";

$shown = 0;
foreach ($cats as $c) {
    $id   = $c['id']   ?? '?';
    $name = $c['name'] ?? ($c['title'] ?? '?');
    if ($search && stripos($name, $search) === false) continue;
    echo str_pad((string)$id, 12) . $name . "\n";
    $shown++;
}

echo "\nShown: $shown\n";
echo "\nTip: search niches e.g.\n";
echo "  &search=audio   &search=gaming   &search=casa   &search=smart\n";
echo "  &search=movil   &search=cargador &search=auricular\n";
