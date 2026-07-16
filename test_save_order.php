<?php
declare(strict_types=1);

/**
 * test_save_order.php — ONE-OFF diagnostic. Calls saveOrder() directly with
 * dummy data to exercise the exact CSV + Supabase insert path that was
 * broken (missing shipping_amount column, products TEXT-vs-jsonb mismatch),
 * without going through Stripe at all.
 *
 * REAL SIDE EFFECTS this triggers, same as saveOrder() always does — this is
 * not a dry run:
 *   - A real row appended to orders/orders.csv
 *   - A real INSERT into Supabase `orders` (this is the point)
 *   - A real "Nuevo pedido" email to OWNER_EMAIL
 *   - A real WhatsApp ping to OWNER_PHONE via CallMeBot
 * Meta Conversions API is explicitly SKIPPED (skipMetaCapi=true below) — a
 * fake Purchase event with test@test.com would be the pixel's first-ever
 * Purchase signal and poison its optimization target. Nothing reaches Meta
 * from this script.
 * Customer-facing WhatsApp confirmation is skipped on purpose (phone left
 * empty below) so this can't accidentally message a real phone number.
 *
 * Delete this file after use. Protected by a one-off secret key, not the
 * site's shared admin key, since it echoes internal Supabase error detail
 * straight to the browser.
 */

if (($_GET['key'] ?? '') !== 'test-save-9f3a71c2e8') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/order_lib.php';

header('Content-Type: text/plain; charset=utf-8');

$paymentId = 'test_' . time();

echo "==========================================\n";
echo "test_save_order.php — one-off saveOrder() diagnostic\n";
echo "==========================================\n\n";
echo "Calling saveOrder() with dummy data, paymentId=$paymentId ...\n\n";

$result = saveOrder([
    'paymentId'      => $paymentId,
    'name'           => 'TEST ORDER - DELETE ME',
    'email'          => 'test@test.com',
    'phone'          => '', // intentionally blank — see header comment
    'address'        => 'Calle Gran Vía 1, 28013 Madrid, España',
    'total'          => 19.99,
    'shippingAmount' => 3.50, // distinct non-zero value, easy to spot-check in Supabase
    'products'       => [
        ['name' => 'Producto de Prueba', 'qty' => 1, 'price' => 19.99],
    ],
    'notes'          => 'Test order created by test_save_order.php — safe to delete.',
    'paymentMethod'  => 'stripe',
    'skipMetaCapi'   => true, // no Purchase event sent to Meta for this test run
]);

echo "---- saveOrder() return value ----\n";
echo "success:   " . ($result['success'] ? 'true' : 'false') . "\n";
echo "orderId:   " . $result['orderId'] . "\n";
echo "csvOk:     " . ($result['csvOk'] ? 'true' : 'false') . "\n";
echo "duplicate: " . (!empty($result['duplicate']) ? 'true' : 'false') . "\n";
echo "errors:    " . (empty($result['errors']) ? '(none)' : implode(' | ', $result['errors'])) . "\n";

echo "\n---- Matching lines from order_errors.log (full Supabase HTTP status + response body) ----\n";
$logContent = @file_get_contents(ORDER_ERROR_LOG);
if ($logContent === false) {
    echo "(could not read " . ORDER_ERROR_LOG . ")\n";
} else {
    $matched = array_filter(explode("\n", $logContent), fn($line) => str_contains($line, $result['orderId']));
    echo $matched ? implode("\n", $matched) . "\n" : "(no matching lines found — unexpected)\n";
}

echo "\n==========================================\n";
echo "NEXT STEPS\n";
echo "==========================================\n";
echo "1. Check Supabase: select * from orders where order_number = '{$result['orderId']}';\n";
echo "2. Check admin.html — the order should appear with a proper products list and 'paid' badge.\n";
echo "3. Delete this file (test_save_order.php) once done.\n";
echo "4. Clean up the test row with the SQL given in chat.\n";
