<?php
declare(strict_types=1);

/**
 * email_lib.php — Minimal Resend API wrapper (raw cURL, no SDK), matching
 * the rest of this codebase's pattern (bigbuy.php, supabase.php, etc.).
 * Every call is best-effort: failures are logged and return false, never
 * thrown — callers must not let a failed email block or roll back the
 * order flow (same contract as order_lib.php's email/WhatsApp side effects).
 */

require_once __DIR__ . '/config.php';

const EMAIL_ERROR_LOG = __DIR__ . '/email_errors.log';

function log_email_event(string $line): void
{
    @file_put_contents(EMAIL_ERROR_LOG, '[' . date('c') . '] ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Sends one HTML email via Resend's API (POST /emails). Always returns a
 * bool, never throws — treat a false return as best-effort failure, same
 * as order_lib.php already does for mail()/WhatsApp.
 */
function send_email(string $to, string $subject, string $html): bool
{
    $to = trim($to);
    if ($to === '') {
        log_email_event("SKIPPED subject=\"$subject\" — empty recipient");
        return false;
    }
    if (RESEND_API_KEY === '') {
        log_email_event("SKIPPED subject=\"$subject\" to=$to — RESEND_API_KEY not configured");
        return false;
    }

    $payload = [
        'from'    => RESEND_FROM_EMAIL,
        'to'      => [$to],
        'subject' => $subject,
        'html'    => $html,
    ];

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 300) {
        log_email_event(
            "FAIL subject=\"$subject\" to=$to status=$status"
            . ($err ? " curlErr=$err" : '')
            . ' response=' . substr((string)$body, 0, 500)
        );
        return false;
    }

    log_email_event("OK subject=\"$subject\" to=$to status=$status");
    return true;
}
