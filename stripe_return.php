<?php
declare(strict_types=1);

/**
 * stripe_return.php — Stripe Checkout success_url landing page.
 * Never trusts the redirect alone: re-fetches the session from Stripe and
 * only saves the order if payment_status is actually 'paid'. Rebuilds the
 * order from the session's metadata (stashed by stripe_checkout.php) and
 * amount_total, then saves it via the same saveOrder() core the PayPal path
 * uses (order_lib.php), with payment_method='stripe' and the Stripe
 * session/payment_intent id as the order-id seed.
 */

require_once __DIR__ . '/order_lib.php';

$sessionId = trim((string)($_GET['session_id'] ?? ''));
if ($sessionId === '') {
    header('Location: https://khurmistore.es/?pago=fallido');
    exit;
}

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . STRIPE_SECRET_KEY,
    ],
]);
$response = curl_exec($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$session = ($response !== false) ? json_decode($response, true) : null;

// Never save an unpaid/unverified order.
if ($status < 200 || $status >= 300 || !is_array($session) || ($session['payment_status'] ?? '') !== 'paid') {
    header('Location: https://khurmistore.es/?pago=fallido');
    exit;
}

$metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];

// Full product list lives in the staged file (see stripe_checkout.php),
// referenced by the short order_ref token — metadata itself only ever
// carries a short summary now. Fall back to an empty list if the staged
// file is missing (e.g. cleaned up, or this session predates the change).
$products = [];
$orderRef = (string)($metadata['order_ref'] ?? '');
if ($orderRef !== '') {
    $pendingFile = __DIR__ . '/stripe_pending/' . $orderRef . '.json';
    $pendingRaw  = @file_get_contents($pendingFile);
    if ($pendingRaw !== false) {
        $pending = json_decode($pendingRaw, true);
        if (is_array($pending) && is_array($pending['products'] ?? null)) {
            $products = $pending['products'];
        }
    }
}

$addressParts = array_filter([$metadata['address'] ?? '', $metadata['city'] ?? '']);
$fullAddress  = implode(', ', $addressParts);

$paymentId = (string)($session['payment_intent'] ?? $session['id'] ?? $sessionId);
$total     = (float)(($session['amount_total'] ?? 0) / 100);

// Never save an order with a NULL customer name — Stripe's own
// customer_details (populated from the card/payment method) is a
// guaranteed-present safety net if our metadata ever fails again.
$customerDetails = is_array($session['customer_details'] ?? null) ? $session['customer_details'] : [];
$name  = (string)($metadata['name']  ?? '');
if ($name === '')  { $name  = (string)($customerDetails['name']  ?? ''); }
$phone = (string)($metadata['phone'] ?? '');
if ($phone === '') { $phone = (string)($customerDetails['phone'] ?? ''); }
$email = (string)($metadata['email'] ?? ($customerDetails['email'] ?? ''));

$shippingAmount = isset($metadata['shipping_amount']) ? (float)$metadata['shipping_amount'] : null;

$result = saveOrder([
    'paymentId'      => $paymentId,
    'name'           => $name,
    'email'          => $email,
    'phone'          => $phone,
    'address'        => $fullAddress,
    'total'          => $total,
    'shippingAmount' => $shippingAmount,
    'products'       => $products,
    'notes'          => $metadata['notes'] ?? '',
    'paymentMethod'  => 'stripe',
]);
if (!empty($result['errors'])) {
    log_order_event("STRIPE RETURN sessionId=$sessionId orderId={$result['orderId']} had errors: " . implode('; ', $result['errors']));
}

header('Location: https://khurmistore.es/?pago=exitoso');
exit;
