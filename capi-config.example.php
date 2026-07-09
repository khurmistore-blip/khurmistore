<?php
declare(strict_types=1);

/**
 * capi-config.example.php — Copy this file to capi-config.php and fill in
 * your real Meta (Facebook) Conversions API credentials.
 * capi-config.php is gitignored — never commit real credentials there.
 *
 * pixel_id / access_token: Meta Events Manager -> your Pixel -> Settings ->
 * Conversions API -> Generate access token.
 * test_event_code: Events Manager -> Test Events tab. Set it while testing,
 * then remove it (or leave blank) for production traffic.
 */
return [
    'pixel_id'        => 'YOUR_META_PIXEL_ID',
    'access_token'    => 'YOUR_META_CAPI_ACCESS_TOKEN',
    'test_event_code' => '',
];
