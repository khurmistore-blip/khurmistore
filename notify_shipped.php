<?php
declare(strict_types=1);

// ============================================================
// KhurmiStore — notify_shipped.php
// Sends a WhatsApp "order shipped" template to the customer.
// Called by admin.html when an order is marked as "shipped".
// Upload this to public_html (same folder as admin.html).
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ---- Config ----
require_once __DIR__ . '/config.php';
// SUPABASE_URL, SUPABASE_SERVICE_KEY, WA_PHONE_NUMBER_ID, WA_ACCESS_TOKEN,
// WA_API_VERSION, WA_TEMPLATE_LANG are all defined in config.php from .env

// The template you create & get approved in Meta (see setup guide)
const WA_TEMPLATE_NAME   = 'order_shipped';
// ----------------

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
$orderId = trim((string)($data['order_id'] ?? ''));

if ($orderId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'order_id required']);
    exit;
}

// 1. Fetch the order from Supabase (server-side, trusted data)
$ch = curl_init(SUPABASE_URL . '/rest/v1/orders?id=eq.' . urlencode($orderId) . '&select=*');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
    ],
]);
$res = curl_exec($ch);
curl_close($ch);

$orders = json_decode($res, true);
if (!is_array($orders) || count($orders) === 0) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}
$o = $orders[0];

// 2. Format phone (Spain default)
$phone = preg_replace('/[^0-9]/', '', (string)($o['phone'] ?? ''));
if ($phone === '') { echo json_encode(['success' => false, 'error' => 'Order has no phone number']); exit; }
if (substr($phone, 0, 2) === '00') { $phone = substr($phone, 2); }
if (strlen($phone) === 9) { $phone = '34' . $phone; }

$name        = (string)($o['customer_name'] ?? 'Cliente');
$orderNumber = (string)($o['order_number'] ?? '');

// 3. Send WhatsApp template message
$url = 'https://graph.facebook.com/' . WA_API_VERSION . '/' . WA_PHONE_NUMBER_ID . '/messages';
$payload = [
    'messaging_product' => 'whatsapp',
    'to'                => $phone,
    'type'              => 'template',
    'template'          => [
        'name'       => WA_TEMPLATE_NAME,
        'language'   => ['code' => WA_TEMPLATE_LANG],
        'components' => [[
            'type'       => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => $name],
                ['type' => 'text', 'text' => $orderNumber],
            ],
        ]],
    ],
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . WA_ACCESS_TOKEN,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
]);
$response = curl_exec($ch);
$code     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code === 200) {
    echo json_encode(['success' => true, 'to' => $phone]);
} else {
    echo json_encode([
        'success'  => false,
        'error'    => 'WhatsApp API error',
        'httpCode' => $code,
        'response' => json_decode($response, true),
    ]);
}
