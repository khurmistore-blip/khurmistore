<?php
declare(strict_types=1);

/**
 * send_tracking.php — Saves tracking_number/tracking_url onto an order and
 * notifies the customer: a branded email with the tracking link (Resend),
 * plus the existing WhatsApp "order_shipped" template (name + order_number
 * only — that template's body is Meta-approved and fixed, so it cannot
 * carry the tracking number/link itself). Called by admin.html's
 * "Send Tracking" button.
 */

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

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email_lib.php';

const WA_TEMPLATE_NAME_SHIPPED = 'order_shipped'; // reuses the existing approved template

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

$orderId        = trim((string)($data['order_id'] ?? ''));
$trackingNumber = trim((string)($data['tracking_number'] ?? ''));
$trackingUrl    = trim((string)($data['tracking_url'] ?? ''));

if ($orderId === '' || $trackingNumber === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'order_id and tracking_number required']);
    exit;
}

// 1. Fetch the order (server-side, trusted data — same pattern as notify_shipped.php)
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

// 2. Save tracking_number/tracking_url onto the order
$patchCh = curl_init(SUPABASE_URL . '/rest/v1/orders?id=eq.' . urlencode($orderId));
curl_setopt_array($patchCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'PATCH',
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        'Content-Type: application/json',
        'Prefer: return=minimal',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'tracking_number' => $trackingNumber,
        'tracking_url'    => $trackingUrl !== '' ? $trackingUrl : null,
    ], JSON_UNESCAPED_UNICODE),
]);
curl_exec($patchCh);
$patchStatus = (int)curl_getinfo($patchCh, CURLINFO_HTTP_CODE);
curl_close($patchCh);

if ($patchStatus < 200 || $patchStatus >= 300) {
    echo json_encode(['success' => false, 'error' => 'No se pudo guardar el número de seguimiento', 'httpCode' => $patchStatus]);
    exit;
}

// 3. Format phone (Spain default) — same logic as notify_shipped.php
$phone = preg_replace('/[^0-9]/', '', (string)($o['phone'] ?? ''));
if (substr($phone, 0, 2) === '00') { $phone = substr($phone, 2); }
if (strlen($phone) === 9) { $phone = '34' . $phone; }

$name        = (string)($o['customer_name'] ?? 'Cliente');
$orderNumber = (string)($o['order_number'] ?? '');

// 4. Branded tracking email (Resend) — the actual tracking-info channel
$emailSent     = false;
$customerEmail = trim((string)($o['email'] ?? ''));
if ($customerEmail !== '') {
    $safeName  = htmlspecialchars($name !== '' ? $name : 'cliente');
    $safeOrder = htmlspecialchars($orderNumber);
    $safeTrack = htmlspecialchars($trackingNumber);
    $trackLinkHtml = $trackingUrl !== ''
        ? '<p style="text-align:center;margin:20px 0;"><a href="' . htmlspecialchars($trackingUrl) . '" style="color:#FF6B35;font-weight:700;">Seguir mi pedido &rarr;</a></p>'
        : '';

    $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;background:#fff;">'
        . '<div style="background:#0A0E27;padding:24px;text-align:center;">'
        . '<span style="color:#FF6B35;font-size:22px;font-weight:800;">KhurmiStore</span></div>'
        . '<div style="padding:28px 24px;color:#1a1a2e;">'
        . '<h1 style="color:#FF6B35;font-size:22px;margin:0 0 12px;">&#128666; &iexcl;Tu pedido va en camino!</h1>'
        . '<p style="font-size:14px;line-height:1.6;color:#444;">Hola ' . $safeName . ', tu pedido <strong>' . $safeOrder . '</strong> ya ha sido enviado.</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:20px 0;font-size:14px;">'
        . '<tr><td style="padding:8px 0;color:#888;">N&uacute;mero de seguimiento</td>'
        . '<td style="padding:8px 0;text-align:right;font-weight:700;">' . $safeTrack . '</td></tr></table>'
        . $trackLinkHtml
        . '<hr style="border:none;border-top:1px solid #eee;margin:24px 0;">'
        . '<p style="font-size:13px;color:#888;">&iquest;Dudas sobre tu env&iacute;o? Escr&iacute;benos:<br>'
        . '&#128231; <a href="mailto:info@khurmistore.es" style="color:#FF6B35;">info@khurmistore.es</a><br>'
        . '&#128172; <a href="https://wa.me/34662241860" style="color:#FF6B35;">WhatsApp: +34 662 24 18 60</a></p></div>'
        . '<div style="background:#0A0E27;padding:16px;text-align:center;">'
        . '<span style="color:#8B92B5;font-size:11px;">&copy; 2026 KhurmiStore Espa&ntilde;a</span></div></div>';

    $emailSent = send_email($customerEmail, "Tu pedido {$orderNumber} va en camino", $html);
}

// 5. WhatsApp — reuses the EXISTING approved "order_shipped" template as-is
//    (name + order_number only; see file header note on why it can't carry
//    the tracking number/link).
$waSent  = false;
$waError = null;
if ($phone !== '') {
    $url = 'https://graph.facebook.com/' . WA_API_VERSION . '/' . WA_PHONE_NUMBER_ID . '/messages';
    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $phone,
        'type'              => 'template',
        'template'          => [
            'name'       => WA_TEMPLATE_NAME_SHIPPED,
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
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $waResponse = curl_exec($ch);
    $waCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $waSent = $waCode === 200;
    if (!$waSent) { $waError = json_decode($waResponse, true); }
}

echo json_encode([
    'success'   => true,
    'emailSent' => $emailSent,
    'waSent'    => $waSent,
    'waError'   => $waError,
]);
