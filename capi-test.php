<?php
require_once __DIR__ . '/capi.php';
header('Content-Type: text/plain; charset=utf-8');
$fakeOrderId = 'TEST-' . date('YmdHis');
$result = MetaCAPI::purchase([
    'order_id'   => $fakeOrderId,
    'value'      => 49.99,
    'currency'   => 'EUR',
    'email'      => 'test@khurmistore.es',
    'phone'      => '+34600111222',
    'first_name' => 'Test',
    'last_name'  => 'Cliente',
    'city'       => 'Valencia',
    'zip'        => '46001',
    'country'    => 'es',
]);
echo "Order ID  : {$fakeOrderId}\n";
echo "Event ID  : {$result['event_id']}\n";
echo "HTTP code : {$result['http']}\n";
echo "Success   : " . ($result['ok'] ? 'YES' : 'NO') . "\n\n";
echo "Meta response:\n{$result['body']}\n";
