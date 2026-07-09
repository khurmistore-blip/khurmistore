<?php
declare(strict_types=1);

/**
 * capi.php — Meta (Facebook) Conversions API client. Sends server-side
 * Purchase events to the Graph API, hashed per Meta's spec, deduplicated
 * against the browser-side Pixel Purchase event via a shared event_id
 * (eventIdForOrder() — the SAME id the frontend must pass as fbq's eventID).
 *
 * Credentials come from capi-config.php (gitignored — copy
 * capi-config.example.php to create it), not from .env, so this file has no
 * dependency on config.php/env.php.
 *
 * Every public entry point (purchase()) throws on failure instead of
 * swallowing errors — callers are expected to wrap it in try/catch so a CAPI
 * failure never breaks the order flow (see save_order.php / stripe_return.php).
 */
class MetaCAPI
{
    private const GRAPH_VERSION = 'v19.0';

    private static function config(): array
    {
        static $cfg = null;
        if ($cfg === null) {
            $path = __DIR__ . '/capi-config.php';
            $cfg  = is_file($path) ? (require $path) : [];
        }
        return is_array($cfg) ? $cfg : [];
    }

    /**
     * Deterministic event id for one order's Purchase event — used both here
     * and by the browser-side fbq('track','Purchase', ..., {eventID: ...})
     * call so Meta dedupes the two into a single event.
     */
    public static function eventIdForOrder(string $orderId): string
    {
        return 'purchase_' . $orderId;
    }

    private static function hash(string $value): string
    {
        return hash('sha256', strtolower(trim($value)));
    }

    /**
     * @param array{
     *   order_id: string, value: float, currency?: string,
     *   email?: string, phone?: string, first_name?: string, last_name?: string,
     *   city?: string, zip?: string, country?: string
     * } $data
     */
    public static function purchase(array $data): void
    {
        $cfg         = self::config();
        $pixelId     = trim((string)($cfg['pixel_id'] ?? ''));
        $accessToken = trim((string)($cfg['access_token'] ?? ''));

        if ($pixelId === '' || $accessToken === '') {
            throw new RuntimeException('Meta CAPI not configured (missing pixel_id/access_token in capi-config.php)');
        }

        $orderId = trim((string)($data['order_id'] ?? ''));
        if ($orderId === '') {
            throw new InvalidArgumentException('MetaCAPI::purchase() requires an order_id');
        }
        $eventId = self::eventIdForOrder($orderId);

        // Personally-identifying fields must be SHA-256 hashed per Meta's
        // spec; only include a key when we actually have a value.
        $userData = [];
        if (!empty($data['email']))      { $userData['em'] = [self::hash((string)$data['email'])]; }
        if (!empty($data['phone']))      { $userData['ph'] = [self::hash(preg_replace('/[^0-9]/', '', (string)$data['phone']))]; }
        if (!empty($data['first_name'])) { $userData['fn'] = [self::hash((string)$data['first_name'])]; }
        if (!empty($data['last_name']))  { $userData['ln'] = [self::hash((string)$data['last_name'])]; }
        if (!empty($data['city']))       { $userData['ct'] = [self::hash((string)$data['city'])]; }
        if (!empty($data['zip']))        { $userData['zp'] = [self::hash((string)$data['zip'])]; }
        if (!empty($data['country']))    { $userData['country'] = [self::hash((string)$data['country'])]; }

        // Not hashed — sent as-is per Meta's spec, best-effort (may be absent
        // when purchase() runs from a redirect landing with no fresh request).
        if (!empty($_SERVER['REMOTE_ADDR']))     { $userData['client_ip_address'] = $_SERVER['REMOTE_ADDR']; }
        if (!empty($_SERVER['HTTP_USER_AGENT'])) { $userData['client_user_agent'] = $_SERVER['HTTP_USER_AGENT']; }

        $event = [
            'event_name'    => 'Purchase',
            'event_time'    => time(),
            'event_id'      => $eventId,
            'action_source' => 'website',
            'user_data'     => $userData,
            'custom_data'   => [
                'currency' => (string)($data['currency'] ?? 'EUR'),
                'value'    => (float)($data['value'] ?? 0),
                'order_id' => $orderId,
            ],
        ];

        $payload = ['data' => [$event]];
        if (!empty($cfg['test_event_code'])) {
            $payload['test_event_code'] = (string)$cfg['test_event_code'];
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new RuntimeException('MetaCAPI::purchase() json_encode failed: ' . json_last_error_msg());
        }

        $url = 'https://graph.facebook.com/' . self::GRAPH_VERSION . "/{$pixelId}/events?access_token=" . rawurlencode($accessToken);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => $body,
        ]);
        $respBody = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        $logLine = date('Y-m-d H:i:s') . " | event_id={$eventId} | order_id={$orderId} | HTTP {$status}"
            . ($curlErr !== '' ? " | curl_error: {$curlErr}" : '')
            . ($respBody !== false ? ' | response: ' . mb_substr($respBody, 0, 500) : '')
            . "\n";
        @file_put_contents(__DIR__ . '/capi.log', $logLine, FILE_APPEND);

        if ($respBody === false || $status < 200 || $status >= 300) {
            throw new RuntimeException(
                "Meta CAPI request failed (HTTP {$status}"
                . ($curlErr !== '' ? ": {$curlErr}" : '')
                . ($respBody !== false ? ': ' . mb_substr($respBody, 0, 300) : '')
                . ')'
            );
        }
    }
}
