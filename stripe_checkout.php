<?php
declare(strict_types=1);

/**
 * stripe_checkout.php — Creates a Stripe Checkout Session (hosted redirect)
 * via direct cURL against the Stripe API — no stripe-php library, no
 * Composer. Mirrors the same POST JSON shape the PayPal path already uses
 * (see script.js's initPayPalButtons / stripeCheckoutBtn handler).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/shipping_lib.php';

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
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ── Leer y validar JSON ───────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

$name     = trim((string)($data['name']    ?? ''));
$email    = trim((string)($data['email']   ?? ''));
$phone    = trim((string)($data['phone']   ?? ''));
$address  = trim((string)($data['address'] ?? ''));
$city     = trim((string)($data['city']    ?? ''));
$notes    = trim((string)($data['notes']   ?? ''));
$products = is_array($data['products'] ?? null) ? $data['products'] : [];

if ($email === '' || empty($products)) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan datos del pedido']);
    exit;
}

// Never trust a total sent from the client — recompute server-side using the
// SAME formula as updateSummary()/calcCartShippingJS() in script.js:
// subtotal + MAX(per-item shipping, from weight via shipping_lib.php).
// (Note: item `price` itself is still taken from the client here, same as
// before this change — a pre-existing characteristic of this endpoint, not
// something newly introduced by the shipping calculation.)
$subtotal      = 0.0;
$productsClean = [];
$weightItems   = [];
foreach ($products as $p) {
    $pName   = trim((string)($p['name']   ?? '?'));
    $pQty    = max(1, (int)($p['qty']     ?? 1));
    $pPrice  = (float)($p['price']        ?? 0.0);
    $pWeight = isset($p['weight']) && $p['weight'] !== null ? (float)$p['weight'] : null;
    $subtotal += $pPrice * $pQty;
    $productsClean[] = ['name' => $pName, 'qty' => $pQty, 'price' => $pPrice];
    $weightItems[]   = ['weight' => $pWeight];
}
$shippingAmount = calcCartShipping($weightItems);
$total          = round($subtotal + $shippingAmount, 2);
$amountCents    = (int)round($total * 100);

if ($amountCents <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'El importe del pedido no es válido']);
    exit;
}

// Stash everything needed to rebuild the order after payment — Checkout
// Session metadata is the only thing guaranteed to survive the redirect.
$metadata = [
    'name'            => $name,
    'email'           => $email,
    'phone'           => $phone,
    'address'         => $address,
    'city'            => $city,
    'notes'           => $notes,
    'products'        => json_encode($productsClean, JSON_UNESCAPED_UNICODE),
    'shipping_amount' => number_format($shippingAmount, 2, '.', ''),
];

$params = [
    'mode'           => 'payment',
    'customer_email' => $email,
    'success_url'    => 'https://khurmistore.es/stripe_return.php?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'     => 'https://khurmistore.es/?pago=cancelado',
    'line_items'     => [[
        'price_data' => [
            'currency'     => 'eur',
            'product_data' => ['name' => 'Pedido KhurmiStore'],
            'unit_amount'  => $amountCents,
        ],
        'quantity' => 1,
    ]],
    'metadata' => $metadata,
];

// http_build_query renders nested arrays as PHP-style brackets
// (line_items[0][price_data][unit_amount]=..., metadata[name]=...),
// which is exactly the form-encoding the Stripe API expects.
$body = http_build_query($params);

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . STRIPE_SECRET_KEY,
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_POSTFIELDS => $body,
]);
$response = curl_exec($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo conectar con Stripe' . ($curlErr ? ": {$curlErr}" : '')]);
    exit;
}

$session = json_decode($response, true);

if ($status < 200 || $status >= 300 || !is_array($session) || empty($session['url'])) {
    $stripeMsg = $session['error']['message'] ?? "HTTP {$status}";
    http_response_code(502);
    echo json_encode(['error' => 'Stripe: ' . $stripeMsg]);
    exit;
}

echo json_encode(['url' => $session['url']]);
