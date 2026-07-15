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
$products = json_decode((string)($metadata['products'] ?? '[]'), true);
if (!is_array($products)) {
    $products = [];
}

$addressParts = array_filter([$metadata['address'] ?? '', $metadata['city'] ?? '']);
$fullAddress  = implode(', ', $addressParts);

$paymentId      = (string)($session['payment_intent'] ?? $session['id'] ?? $sessionId);
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
if (!empty($result['errors'])) {
    log_order_event("STRIPE RETURN sessionId=$sessionId orderId={$result['orderId']} had errors: " . implode('; ', $result['errors']));
}

header('Location: https://khurmistore.es/?pago=exitoso');
exit;
