<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

// ── WhatsApp Cloud API ────────────────────────────────────────────────────────
define('WA_PHONE_NUMBER_ID', env('WA_PHONE_NUMBER_ID', ''));
define('WA_ACCESS_TOKEN',    env('WA_ACCESS_TOKEN', ''));
define('WA_API_VERSION',     env('WA_API_VERSION', 'v25.0'));
define('WA_TEMPLATE_LANG',   env('WA_TEMPLATE_LANG', 'es'));

// ── Supabase ──────────────────────────────────────────────────────────────────
define('SUPABASE_URL',          env('SUPABASE_URL', ''));
define('SUPABASE_ANON_KEY',     env('SUPABASE_ANON_KEY', ''));
define('SUPABASE_SERVICE_KEY',  env('SUPABASE_SERVICE_KEY', ''));
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
            "name" => "order_confirmation",
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

// ── Site-wide display config (used by producto.php, categoria.php, ...) ───────
return [
    // Store / frontend
    'store_name'           => env('STORE_NAME', 'KhurmiStore'),
    'currency_symbol'      => env('CURRENCY_SYMBOL', '€'),
    'whatsapp_number'      => env('WHATSAPP_ORDER_NUMBER', ''),

    // Supabase
    'supabase_url'         => SUPABASE_URL,
    'supabase_anon_key'    => SUPABASE_ANON_KEY,
    'supabase_service_key' => SUPABASE_SERVICE_KEY,

    // BigBuy (needed by sync_products.php)
    'bigbuy_api_key'       => env('BIGBUY_API_KEY', ''),
    'bigbuy_sandbox'       => filter_var(env('BIGBUY_SANDBOX', 'true'), FILTER_VALIDATE_BOOLEAN),

    // Pricing rule (BigBuy cost x3, .99 ending)
    'price_multiplier'     => 3.0,
    'price_ending'         => 0.99,
];
