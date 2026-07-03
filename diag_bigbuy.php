<?php
/**
 * diag_bigbuy.php — Poora RAW data dikhata hai taake field names confirm hon.
 * Kholo: khurmistore.es/diag_bigbuy.php?key=khurmi2026&id=1500770
 * Test ke baad DELETE kar dena.
 */
if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/bigbuy.php';
$cfg = require __DIR__ . '/config.php';
$id  = (int)($_GET['id'] ?? 1500770);

$bb = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

echo "### getProductInfo($id) — RAW ###\n";
print_r($bb->getProductInfo($id)['data']);

echo "\n\n### getProductStock($id) — RAW ###\n";
print_r($bb->getProductStock($id)['data']);

echo "\n\n### getProductImages($id) — RAW ###\n";
print_r($bb->getProductImages($id)['data']);

echo "\n\n### (price ke liye) getAllProducts se sirf yeh id filter ###\n";
echo "NOTE: getAllProducts bara hota hai; agar yeh slow ho to skip.\n";
