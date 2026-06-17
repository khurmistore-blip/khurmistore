<?php
declare(strict_types=1);

// ── WhatsApp Cloud API ────────────────────────────────────────────────────────
define('WA_PHONE_NUMBER_ID', '1199415803251225');
define('WA_ACCESS_TOKEN',    'EAAL7innhQUMBRjDfHVN5709PkHSoJm7w2MyV96fYZC49ok68qdjqg3IYN3SpjEmJ3DtUyRQeLNyZBTPmuBi7YeNgnSqRGvSzUaE4zCJBId4T6psINCZBA7nlAzOgMNBNxf9JLzpziFPZCH92smCVdf46WnIQ9YAEwNJZAPDMgZCiPZCFDHP9kT8cgWhRehKXgZDZD');
define('WA_API_VERSION',     'v25.0');
define('WA_TEMPLATE_NAME',   'order_confirmation');
define('WA_TEMPLATE_LANG',   'es');
// ─────────────────────────────────────────────────────────────────────────────

function send_whatsapp_order_confirmation($customerPhone, $name, $orderNumber, $total) {
    $customerPhone = preg_replace('/[^0-9]/', '', $customerPhone);
    if ($customerPhone === '') return false;
    $url = "https://graph.facebook.com/" . WA_API_VERSION . "/" . WA_PHONE_NUMBER_ID . "/messages";
    $payload = [
        "messaging_product" => "whatsapp",
        "to" => $customerPhone,
        "type" => "template",
        "template" => [
            "name" => WA_TEMPLATE_NAME,
            "language" => ["code" => WA_TEMPLATE_LANG],
            "components" => [[
                "type" => "body",
                "parameters" => [
                    ["type" => "text", "text" => (string)$name],
                    ["type" => "text", "text" => (string)$orderNumber],
                    ["type" => "text", "text" => (string)$total],
                ]
            ]]
        ]
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer " . WA_ACCESS_TOKEN, "Content-Type: application/json"],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 20,
    ]);
    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    error_log("WhatsApp order confirm [$code]: " . $response);
    return $code === 200;
}
