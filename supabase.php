<?php
declare(strict_types=1);

/**
 * Read-only Supabase REST helper for public pages. Uses the anon key
 * (safe to expose — access is governed by Supabase RLS policies), never
 * the service_role key.
 *
 * @return array<int, array<string, mixed>>
 */
function sb_get(array $cfg, string $path): array
{
    $baseUrl = rtrim((string)($cfg['supabase_url'] ?? ''), '/');
    $anonKey = (string)($cfg['supabase_anon_key'] ?? '');
    if ($baseUrl === '' || $anonKey === '') {
        return [];
    }

    $ch = curl_init($baseUrl . '/rest/v1/' . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . $anonKey,
            'Authorization: Bearer ' . $anonKey,
        ],
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false || $status < 200 || $status >= 300) {
        return [];
    }

    $data = json_decode($body, true);
    return is_array($data) ? $data : [];
}

function price_es(float $price, string $symbol = '€'): string
{
    return number_format($price, 2, ',', '.') . ' ' . $symbol;
}
