<?php
declare(strict_types=1);

/**
 * order_lib.php — Shared order-saving core, used by THREE callers:
 * save_order.php (PayPal, called from the browser after capture),
 * stripe_return.php (Stripe Checkout success_url, browser-dependent), and
 * stripe_webhook.php (Stripe checkout.session.completed, browser-independent
 * backup — fires even if the customer's browser never returns to the site).
 * Persists to CSV + Supabase, then notifies via email + WhatsApp. Every side
 * effect except the CSV write is best-effort — failures are collected into
 * the returned 'errors' array instead of throwing, so one bad channel never
 * blocks the others or the customer-facing flow.
 *
 * saveOrder() is idempotent per paymentId (see order_number_exists()) so that
 * stripe_return.php and stripe_webhook.php racing for the same payment never
 * produce two orders or two notifications — whichever calls first wins.
 */

require_once __DIR__ . '/config.php';

define('OWNER_EMAIL', env('OWNER_EMAIL', ''));
define('OWNER_PHONE', env('OWNER_PHONE', ''));
define('CALLMEBOT_KEY', env('CALLMEBOT_KEY', ''));
const CSV_DIR  = __DIR__ . '/orders';
const CSV_FILE = CSV_DIR . '/orders.csv';
const ORDER_ERROR_LOG = __DIR__ . '/order_errors.log';

/**
 * Appends one line to order_errors.log. Nothing in saveOrder() relied on
 * this before — Supabase failures were only ever recorded in an in-memory
 * $sideErrors array that most callers never inspected, so they vanished.
 */
function log_order_event(string $line): void
{
    @file_put_contents(ORDER_ERROR_LOG, '[' . date('c') . '] ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Idempotency guard: true if an order with this exact order_number already
 * exists in Supabase. Used so that stripe_return.php and stripe_webhook.php
 * can both fire for the same payment (browser redirect + webhook race)
 * without creating a duplicate order or duplicate email/WhatsApp notification.
 * Fails OPEN (returns false) on a lookup error — we'd rather risk a rare
 * duplicate than silently drop a legitimate order because Supabase hiccuped.
 */
function order_number_exists(string $orderId): bool
{
    $url = rtrim(SUPABASE_URL, '/') . '/rest/v1/orders?order_number=eq.' . rawurlencode($orderId) . '&select=id&limit=1';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
        ],
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300 || $body === false) {
        log_order_event("IDEMPOTENCY CHECK FAILED orderId=$orderId status=$status — proceeding as not-a-duplicate");
        return false;
    }
    $rows = json_decode((string)$body, true);
    return is_array($rows) && count($rows) > 0;
}

/**
 * @param array{
 *   paymentId?: string, name?: string, email?: string, phone?: string,
 *   address?: string, total?: float, products?: array, notes?: string,
 *   paymentMethod?: string
 * } $data
 * @return array{success: bool, orderId: string, csvOk: bool, errors: array, duplicate?: bool}
 */
function saveOrder(array $data): array
{
    $paymentId      = trim((string)($data['paymentId']     ?? ''));
    $name           = trim((string)($data['name']          ?? ''));
    $email          = trim((string)($data['email']         ?? ''));
    $phone          = trim((string)($data['phone']         ?? ''));
    $address        = trim((string)($data['address']       ?? ''));
    $total          = (float)($data['total']                ?? 0.0);
    $shippingAmount = isset($data['shippingAmount']) && $data['shippingAmount'] !== null ? (float)$data['shippingAmount'] : null;
    $products       = is_array($data['products'] ?? null) ? $data['products'] : [];
    $notes          = trim((string)($data['notes']          ?? ''));
    $paymentMethod  = trim((string)($data['paymentMethod']  ?? 'paypal'));
    // Test/diagnostic callers only (e.g. test_save_order.php) — real payment
    // paths never set this, so live orders always send the Purchase event
    // exactly as before. Default is false: unchanged behavior everywhere else.
    $skipMetaCapi   = !empty($data['skipMetaCapi']);
    $datetime       = date('Y-m-d H:i:s');

    // ── Construir resumen de productos ────────────────────────────────────
    $productLines = [];
    $productsJson = [];
    foreach ($products as $p) {
        $pName     = trim((string)($p['name']  ?? '?'));
        $pQty      = max(1, (int)($p['qty']    ?? 1));
        $pPrice    = (float)($p['price']       ?? 0.0);
        $lineTotal = number_format($pPrice * $pQty, 2, ',', '.') . ' €';
        $productLines[] = "{$pName} x{$pQty} – {$lineTotal}";
        $productsJson[] = ['name' => $pName, 'qty' => $pQty, 'price' => $pPrice];
    }
    $productsSummary = implode(' | ', $productLines);
    $productsText    = implode("\n", array_map(static fn($l) => "  • {$l}", $productLines));

    // ── ID de pedido (se usa en CSV-response y Supabase) ──────────────────
    // Deterministic from paymentId alone (not paymentId+datetime like before)
    // so the SAME Stripe payment always produces the SAME order_number,
    // regardless of whether stripe_return.php or stripe_webhook.php computes
    // it — that's what makes the duplicate check below possible. Falls back
    // to a random suffix only when there's no real payment id to key off of.
    $orderId = $paymentId !== ''
        ? 'KW' . date('Ymd') . '-' . strtoupper(substr(md5($paymentId), 0, 6))
        : 'KW' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

    if ($paymentId !== '' && order_number_exists($orderId)) {
        log_order_event("DUPLICATE orderId=$orderId paymentId=$paymentId — order already exists, skipping CSV/Supabase/notifications");
        return [
            'success'   => true,
            'orderId'   => $orderId,
            'csvOk'     => true,
            'errors'    => [],
            'duplicate' => true,
        ];
    }

    log_order_event("ATTEMPT orderId=$orderId paymentId=$paymentId method=$paymentMethod email=$email total=$total");

    // ── 1. Guardar en CSV (siempre, es lo más importante) ─────────────────
    $csvErrors = [];

    if (!is_dir(CSV_DIR) && !mkdir(CSV_DIR, 0755, true) && !is_dir(CSV_DIR)) {
        $csvErrors[] = 'No se pudo crear el directorio orders/';
    }

    if (empty($csvErrors)) {
        $isNew = !file_exists(CSV_FILE) || filesize(CSV_FILE) === 0;
        $fh    = fopen(CSV_FILE, 'ab');

        if ($fh === false) {
            $csvErrors[] = 'No se pudo abrir orders.csv';
        } else {
            if ($isNew) {
                fputcsv($fh, [
                    'fecha', 'payment_id', 'metodo_pago', 'nombre', 'email',
                    'telefono', 'direccion', 'productos', 'total_eur', 'notas',
                ]);
            }
            fputcsv($fh, [
                $datetime,
                $paymentId,
                $paymentMethod,
                $name,
                $email,
                $phone,
                $address,
                $productsSummary,
                number_format($total, 2, '.', ''),
                $notes,
            ]);
            fclose($fh);
        }
    }

    // Si el CSV falla es un error grave — lo devolvemos pero seguimos intentando notificar
    $sideErrors = $csvErrors;
    if (!empty($csvErrors)) {
        log_order_event("CSV FAIL orderId=$orderId errors=" . implode('; ', $csvErrors));
    }

    // ── 2. Guardar en Supabase (Admin Dashboard) ──────────────────────────
    $supabaseOrder = [
        'order_number'   => $orderId,
        'customer_name'  => $name !== '' ? $name : 'Sin nombre',
        'phone'          => $phone,
        'email'          => $email,
        'address'        => $address,
        'products'        => $productsJson,
        'total_amount'    => $total,
        'shipping_amount' => $shippingAmount,
        'payment_method'  => $paymentMethod,
        'payment_status'  => 'paid', // saveOrder() only ever runs after payment is already confirmed
        'source'         => 'website',
        'status'         => 'pending',
        'notes'          => $notes,
    ];

    $sbCh = curl_init(SUPABASE_URL . '/rest/v1/orders');
    curl_setopt_array($sbCh, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_SERVICE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ],
        CURLOPT_POSTFIELDS => json_encode($supabaseOrder, JSON_UNESCAPED_UNICODE),
    ]);
    $sbBody   = curl_exec($sbCh);
    $sbStatus = (int)curl_getinfo($sbCh, CURLINFO_HTTP_CODE);
    $sbErr    = curl_error($sbCh);
    curl_close($sbCh);

    if ($sbStatus < 200 || $sbStatus >= 300) {
        $sideErrors[] = "Supabase no guardado (HTTP {$sbStatus}" . ($sbErr ? ": {$sbErr}" : '') . ')';
        log_order_event(
            "SUPABASE FAIL orderId=$orderId status=$sbStatus curlErr=" . ($sbErr ?: 'none')
            . " response=" . substr((string)$sbBody, 0, 1000)
            . " payload=" . json_encode($supabaseOrder, JSON_UNESCAPED_UNICODE)
        );
    } else {
        log_order_event("SUPABASE OK orderId=$orderId status=$sbStatus");
    }

    // ── 2b. Meta Conversions API — Purchase (server-side, deduplicated with ──
    //        the browser Pixel Purchase event via event_id 'purchase_<paymentId>')
    //        Skipped entirely for test/diagnostic calls ($skipMetaCapi) — a
    //        fake Purchase would poison the pixel's optimization signal.
    if (!$skipMetaCapi) {
        $nameParts = explode(' ', $name, 2);
        $zip = '';
        if (preg_match('/\b(\d{5})\b/', $address, $m)) { $zip = $m[1]; }
        require_once __DIR__ . '/capi.php';
        try {
            MetaCAPI::purchase([
                'order_id'   => $orderId,
                'event_id'   => MetaCAPI::eventIdForOrder($paymentId),
                'value'      => $total,
                'currency'   => 'EUR',
                'email'      => $email,
                'phone'      => $phone,
                'first_name' => $nameParts[0] ?? '',
                'last_name'  => $nameParts[1] ?? '',
                'city'       => '',
                'zip'        => $zip,
                'country'    => 'es',
            ]);
        } catch (Throwable $e) {
            error_log('CAPI failed: ' . $e->getMessage());
        }
    } else {
        log_order_event("CAPI SKIPPED orderId=$orderId (skipMetaCapi=true, test/diagnostic call)");
    }

    // ── 3. Enviar email al dueño ───────────────────────────────────────────
    $emailSubject  = '=?UTF-8?B?' . base64_encode('Nuevo pedido KhurmiStore') . '?=';
    $emailBody     = "Nuevo pedido recibido en KhurmiStore\n";
    $emailBody    .= "====================================\n\n";
    $emailBody    .= "Fecha y hora  : {$datetime}\n";
    $emailBody    .= "Método de pago: {$paymentMethod}\n";
    $emailBody    .= "ID de pago    : {$paymentId}\n\n";
    $emailBody    .= "CLIENTE\n";
    $emailBody    .= "-------\n";
    $emailBody    .= "Nombre    : {$name}\n";
    $emailBody    .= "Email     : {$email}\n";
    $emailBody    .= "Teléfono  : {$phone}\n";
    $emailBody    .= "Dirección : {$address}\n\n";
    $emailBody    .= "PRODUCTOS\n";
    $emailBody    .= "---------\n";
    $emailBody    .= $productsText . "\n\n";
    $emailBody    .= 'TOTAL: ' . number_format($total, 2, ',', '.') . " €\n";
    if ($notes !== '') {
        $emailBody .= "\nNotas: {$notes}\n";
    }

    $emailHeaders  = "From: pedidos@khurmistore.es\r\n";
    $emailHeaders .= "Reply-To: {$email}\r\n";
    $emailHeaders .= "MIME-Version: 1.0\r\n";
    $emailHeaders .= "Content-Type: text/plain; charset=UTF-8\r\n";

    if (!@mail(OWNER_EMAIL, $emailSubject, $emailBody, $emailHeaders)) {
        $sideErrors[] = 'Email no enviado (mail() falló)';
    }

    // ── 4. Enviar WhatsApp al dueño vía CallMeBot ─────────────────────────
    $waLines   = [];
    $waLines[] = '✅ NUEVO PEDIDO KhurmiStore';
    $waLines[] = '💳 Pago: ' . strtoupper($paymentMethod) . " ({$paymentId})";
    $waLines[] = "👤 {$name}  📞 {$phone}";
    $waLines[] = '📦 Productos:';
    foreach ($productLines as $pl) {
        $waLines[] = "  {$pl}";
    }
    $waLines[] = '💶 TOTAL: ' . number_format($total, 2, ',', '.') . ' €';
    if ($address !== '') {
        $waLines[] = "📍 {$address}";
    }

    $waText = implode("\n", $waLines);
    $waUrl  = 'https://api.callmebot.com/whatsapp.php'
            . '?phone='  . urlencode(OWNER_PHONE)
            . '&text='   . urlencode($waText)
            . '&apikey=' . urlencode(CALLMEBOT_KEY);

    $ch = curl_init($waUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $waBody   = curl_exec($ch);
    $waStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($waBody === false || $waStatus < 200 || $waStatus >= 300) {
        $sideErrors[] = "WhatsApp no enviado (HTTP {$waStatus}" . ($curlErr ? ": {$curlErr}" : '') . ')';
    }

    // ── 5. Enviar WhatsApp de confirmación al cliente (WhatsApp Cloud API) ─
    $waPhone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($waPhone, 0, 2) === '00') { $waPhone = substr($waPhone, 2); }
    if (strlen($waPhone) === 9) { $waPhone = '34' . $waPhone; }
    send_whatsapp_order_confirmation($waPhone, $name, $orderId, $total);

    return [
        'success' => true,        // true incluso si email/WhatsApp fallan; el CSV es lo que cuenta
        'orderId' => $orderId,
        'csvOk'   => empty($csvErrors),
        'errors'  => $sideErrors, // vacío = todo fue bien
    ];
}
