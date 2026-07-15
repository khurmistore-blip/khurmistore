<?php
declare(strict_types=1);

/**
 * stripe_webhook.php — Server-to-server backup for saving orders, independent
 * of whether the customer's browser ever makes it back to stripe_return.php.
 * A closed tab, lost connection, or an ad-blocker interfering with the
 * success_url redirect all silently drop an order under a return-page-only
 * design. Stripe calls this URL directly from its own servers when a
 * Checkout Session completes, so it fires regardless of what happens in the
 * customer's browser.
 *
 * Idempotency: saveOrder() (order_lib.php) computes a deterministic
 * order_number from the Stripe payment_intent/session id and checks Supabase
 * for an existing row with that order_number before writing anything. So if
 * BOTH this webhook AND stripe_return.php fire for the same payment, only
 * whichever runs first actually writes/notifies — no duplicate order, no
 * duplicate email/WhatsApp message.
 *
 * Does NOT touch Stripe Checkout Session creation or payment verification —
 * stripe_checkout.php and stripe_return.php are unchanged and keep working
 * exactly as before; this is purely an additional, independent save path.
 *
 * Stripe dashboard setup required (see chat for full instructions):
 *   Endpoint URL: https://khurmistore.es/stripe_webhook.php
 *   Event to send: checkout.session.completed
 *   Signing secret -> STRIPE_WEBHOOK_SECRET in .env
 */

require_once __DIR__ . '/order_lib.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Verifies a Stripe webhook signature without the Stripe SDK, following
 * Stripe's documented scheme (https://stripe.com/docs/webhooks/signatures):
 * the Stripe-Signature header looks like "t=<timestamp>,v1=<hex hmac>[,v1=...]"
 * and the expected signature is HMAC-SHA256("{t}.{raw body}", webhook secret).
 */
function stripe_verify_webhook_signature(string $payload, string $sigHeader, string $secret, int $toleranceSeconds = 300): bool
{
    if ($secret === '' || $sigHeader === '') {
        return false;
    }
    $parts = [];
    foreach (explode(',', $sigHeader) as $piece) {
        $kv = explode('=', $piece, 2);
        if (count($kv) === 2) {
            $parts[$kv[0]][] = $kv[1];
        }
    }
    $timestamp  = $parts['t'][0] ?? null;
    $signatures = $parts['v1'] ?? [];
    if ($timestamp === null || empty($signatures)) {
        return false;
    }
    if (abs(time() - (int)$timestamp) > $toleranceSeconds) {
        return false; // too old — possible replay
    }
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) {
            return true;
        }
    }
    return false;
}

$rawBody   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($rawBody === false || $rawBody === '' || $sigHeader === '') {
    log_order_event('WEBHOOK REJECTED missing body or Stripe-Signature header');
    http_response_code(400);
    echo json_encode(['error' => 'Missing payload or signature']);
    exit;
}

if (!stripe_verify_webhook_signature($rawBody, $sigHeader, STRIPE_WEBHOOK_SECRET)) {
    log_order_event('WEBHOOK REJECTED invalid signature');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$event = json_decode($rawBody, true);
if (!is_array($event)) {
    log_order_event('WEBHOOK REJECTED invalid JSON');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$eventType = (string)($event['type'] ?? '');
log_order_event("WEBHOOK RECEIVED type=$eventType id=" . ($event['id'] ?? '?'));

// Only checkout.session.completed carries our custom metadata (name, email,
// address, products) — it's set on the Checkout Session at creation time in
// stripe_checkout.php. Stripe does not automatically copy Checkout Session
// metadata onto the underlying PaymentIntent, so payment_intent.succeeded
// would arrive without name/email/address/products and can't rebuild an
// order on its own — that's why this only listens for the session event.
if ($eventType !== 'checkout.session.completed') {
    http_response_code(200); // acknowledge so Stripe doesn't retry — we just don't act on it
    echo json_encode(['received' => true, 'skipped' => true]);
    exit;
}

$session = $event['data']['object'] ?? [];
if (!is_array($session) || ($session['payment_status'] ?? '') !== 'paid') {
    log_order_event('WEBHOOK SKIPPED session not paid, session_id=' . ($session['id'] ?? '?'));
    http_response_code(200);
    echo json_encode(['received' => true, 'skipped' => true]);
    exit;
}

$metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
$products = json_decode((string)($metadata['products'] ?? '[]'), true);
if (!is_array($products)) {
    $products = [];
}

$addressParts = array_filter([$metadata['address'] ?? '', $metadata['city'] ?? '']);
$fullAddress  = implode(', ', $addressParts);

$paymentId      = (string)($session['payment_intent'] ?? $session['id'] ?? '');
$total          = (float)(($session['amount_total'] ?? 0) / 100);
$email          = (string)($metadata['email'] ?? ($session['customer_details']['email'] ?? ''));
$shippingAmount = isset($metadata['shipping_amount']) ? (float)$metadata['shipping_amount'] : null;

$result = saveOrder([
    'paymentId'      => $paymentId,
    'name'           => $metadata['name']  ?? '',
    'email'          => $email,
    'phone'          => $metadata['phone'] ?? '',
    'address'        => $fullAddress,
    'total'          => $total,
    'shippingAmount' => $shippingAmount,
    'products'       => $products,
    'notes'          => $metadata['notes'] ?? '',
    'paymentMethod'  => 'stripe',
]);

log_order_event(
    'WEBHOOK PROCESSED session_id=' . ($session['id'] ?? '?') . ' orderId=' . $result['orderId']
    . (!empty($result['duplicate']) ? ' (duplicate, skipped writes)' : '')
    . (!empty($result['errors']) ? ' errors=' . implode('; ', $result['errors']) : '')
);

http_response_code(200);
echo json_encode(['received' => true, 'orderId' => $result['orderId']]);
