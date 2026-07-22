<?php
/**
 * test_email.php — ONE-OFF diagnostic: confirms send_email() (Resend API)
 * actually delivers. Sends a single test email and prints the result.
 * DELETE after use.
 */

if (php_sapi_name() !== 'cli') {
    if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/email_lib.php';

$to      = 'qamarshahzad320@gmail.com';
$subject = 'KhurmiStore — test de envío de email';
$html    = '<p>Este es un email de prueba desde <strong>test_email.php</strong>.</p>'
    . '<p>Si lo recibes, send_email() y Resend están funcionando correctamente.</p>';

$ok = send_email($to, $subject, $html);

echo "to: $to\n";
echo "success: " . ($ok ? 'true' : 'false') . "\n";
echo "\nCheck email_errors.log for the full request/response detail.\n";
