<?php
/**
 * cat_debug.php — Shows the RAW categories response so we can see its structure.
 * Open: khurmistore.es/cat_debug.php?key=khurmi2026
 * DELETE after use.
 */
if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(90);

require_once __DIR__ . '/bigbuy.php';
$cfg = require __DIR__ . '/config.php';
$bb  = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

$res = $bb->getCategories('es');
echo "HTTP: " . ($res['status'] ?? '?') . "\n";
echo "success: " . (($res['success'] ?? false) ? 'true' : 'false') . "\n\n";

$data = $res['data'] ?? null;
echo "Type: " . gettype($data) . "\n";
if (is_array($data)) {
    echo "Count: " . count($data) . "\n\n";
    echo "=== First 5 items RAW ===\n";
    $i = 0;
    foreach ($data as $k => $v) {
        echo "[key: $k]\n";
        print_r($v);
        echo "\n";
        if (++$i >= 5) break;
    }
} else {
    echo "RAW (first 1000 chars):\n";
    print_r($data);
}
