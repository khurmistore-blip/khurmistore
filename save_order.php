<?php
declare(strict_types=1);

/**
 * save_order.php — PayPal order-saving endpoint. Called via fetch() from
 * script.js right after paypal.Buttons' onApprove/actions.order.capture()
 * succeeds. The actual saving logic (CSV + Supabase + email + WhatsApp)
 * lives in order_lib.php's saveOrder(), shared with the Stripe path in
 * stripe_return.php.
 */

require_once __DIR__ . '/order_lib.php';

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

// ── Leer y validar JSON ───────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit;
}

$result = saveOrder([
    'paymentId'     => $data['paypalId']  ?? '',
    'name'          => $data['name']      ?? '',
    'email'         => $data['email']     ?? '',
    'phone'         => $data['phone']     ?? '',
    'address'       => $data['address']   ?? '',
    'total'         => $data['total']     ?? 0.0,
    'products'      => $data['products']  ?? [],
    'notes'         => $data['notes']     ?? '',
    'paymentMethod' => 'paypal',
]);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
