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

/**
 * First image URL from a product's comma-separated image_url column
 * (e.g. "url1.jpg,url2.jpg,url3.jpg" -> "url1.jpg"). Used everywhere a
 * product CARD shows a single image (the full gallery lives on producto.php).
 * Falls back to a small inline SVG placeholder (dark navy, matches the
 * site's --dark token) so a product with no image never shows a broken
 * <img> icon. Always pass the result through htmlspecialchars() at the
 * call site, same as any other attribute value.
 */
function first_product_image(?string $imageUrl): string
{
    $first = trim(explode(',', $imageUrl ?? '')[0] ?? '');
    if ($first !== '') {
        return $first;
    }
    return 'data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 400%22><rect width=%22100%25%22 height=%22100%25%22 fill=%22%230a0e27%22/></svg>';
}
