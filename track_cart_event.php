<?php
declare(strict_types=1);

/**
 * track_cart_event.php — Best-effort "Add to Cart" event logger. Looks up
 * the product SERVER-SIDE (never trusts a client-sent name/price) and
 * inserts one row into cart_events. script.js's addToCart() fires this
 * fire-and-forget and ignores the response either way — a failure here
 * must never be visible to the shopper or affect the cart.
 */

require_once __DIR__ . '/supabase.php';
$cfg = require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw       = file_get_contents('php://input');
$data      = json_decode($raw, true);
$productId = (int)($data['product_id'] ?? 0);

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing product_id']);
    exit;
}

// Look up the real product server-side — never trust a client-sent name/price.
$rows    = sb_get($cfg, 'products?id=eq.' . $productId . '&select=id,name,price&limit=1');
$product = $rows[0] ?? null;

if (!$product) {
    // Unknown id — nothing to log, but still report success so a stale/bad
    // id from the client never surfaces as a tracking error.
    echo json_encode(['success' => true, 'skipped' => 'product not found']);
    exit;
}

$row = [
    'product_id'   => (int)$product['id'],
    'product_name' => $product['name'],
    'price'        => (float)$product['price'],
];

$ch = curl_init(rtrim($cfg['supabase_url'], '/') . '/rest/v1/cart_events');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => [
        'apikey: ' . $cfg['supabase_service_key'],
        'Authorization: Bearer ' . $cfg['supabase_service_key'],
        'Content-Type: application/json',
        'Prefer: return=minimal',
    ],
    CURLOPT_POSTFIELDS => json_encode($row, JSON_UNESCAPED_UNICODE),
]);
$body   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo json_encode(['success' => $body !== false && $status >= 200 && $status < 300]);
