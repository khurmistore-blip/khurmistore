<?php
/**
 * test_video.php — ONE-OFF diagnostic: does BigBuy return a usable "video"
 * field for a single product? Calls GET /rest/catalog/product/{id}.json for
 * ONLY cj_pid 1070220 (no catalog loop). Reuses the exact same auth as
 * sync_products.php (BigBuy class, config.php key). DELETE after use.
 */

if (php_sapi_name() !== 'cli') {
    if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/bigbuy.php';
$cfg = require __DIR__ . '/config.php';
$bb  = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

$productId = 1070220;

$res = $bb->getProduct($productId, 'es');

echo "HTTP status: " . ($res['status'] ?? '?') . "\n";
echo "success: " . (($res['success'] ?? false) ? 'true' : 'false') . "\n\n";

if (!($res['success'] ?? false)) {
    echo "Request failed — raw response:\n";
    print_r($res);
    exit;
}

$data = $res['data'];
$rec  = (is_array($data) && isset($data[0]) && is_array($data[0])) ? $data[0] : $data;

echo "Full record for product $productId:\n";
print_r($rec);

echo "\n----------------------------------------\n";
if (array_key_exists('video', $rec)) {
    $video = $rec['video'];
    echo "video field raw value: " . var_export($video, true) . "\n";
    if ($video === '0' || $video === 0) {
        echo "=> (a) \"0\" — no video.\n";
    } elseif ($video === null || $video === '') {
        echo "=> (b) null/empty — no video.\n";
    } else {
        echo "=> (c) actual value present — see above.\n";
    }
} else {
    echo "'video' key NOT present in response at all.\n";
}
