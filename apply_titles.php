<?php
declare(strict_types=1);

/**
 * apply_titles.php — Applies the cleaned product titles (same
 * clean_product_title() logic as clean_titles.php, duplicated here so this
 * script has no dependency on that file — both are meant to be deleted
 * independently after use) to the Supabase `products` table.
 *
 * Skips 3 products entirely where the automated cleaner incorrectly
 * stripped real model/shade info (left completely untouched):
 *   - id 55  (Sony MDR-EX15LP — real model number)
 *   - id 187 (Essie "Nº 42-Angora" — real shade name)
 *   - id 217 (Phyto "5-castaño claro" — real hair-color shade)
 *
 * HOW TO RUN (browser): khurmistore.es/apply_titles.php?key=khurmi2026
 *
 * DELETE this file (and clean_titles.php) from the server after running it
 * once — it's a write-capable one-off migration script, not meant to stay
 * live in public_html.
 */

if (php_sapi_name() !== 'cli') {
    if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
    header('Content-Type: text/plain; charset=utf-8');
}
set_time_limit(300);

require_once __DIR__ . '/supabase.php';
$cfg = require __DIR__ . '/config.php';

// Product ids to leave completely untouched — the cleaner over-stripped
// real model/shade info for these; do not compute or update them at all.
const SKIP_IDS = [55, 187, 217];

/**
 * Clean one product title. Identical logic to clean_titles.php's
 * clean_product_title() — duplicated intentionally, see file header.
 */
function clean_product_title(string $name): string
{
    $title = $name;

    // 1. Remove "(Reacondicionado ...)" tags entirely, case-insensitive.
    $title = preg_replace('/\s*\(\s*reacondicionado[^)]*\)/i', '', $title);

    // 2. Join adjacent inch-size ranges like 40" 75" into 40"-75".
    $title = preg_replace('/(\d+)"\s+(\d+)"/', '$1"-$2"', $title);

    // Units that make a nearby long digit run a real measurement rather
    // than a distributor code (e.g. "50000 mAh" should not be stripped).
    $units = ['kg', 'g', 'gr', 'gramos', 'ml', 'l', 'lt', 'litros', 'm', 'cm',
        'mm', 'w', 'v', 'mah', 'gb', 'tb', 'mp', 'hz', 'uds', 'ud', 'pack', 'pulgadas'];

    $tokens = preg_split('/\s+/', trim($title));
    $n = count($tokens);
    $drop = array_fill(0, $n, false);

    for ($i = 0; $i < $n; $i++) {
        $bare = trim($tokens[$i], "(),.");
        if ($bare === '') {
            continue;
        }

        // Pure digit run of 5+ chars -> distributor code, unless the next
        // word is a unit like "50000 mAh".
        if (preg_match('/^[0-9]{5,}$/', $bare)) {
            $next = isset($tokens[$i + 1]) ? strtolower(trim($tokens[$i + 1], "(),.\"")) : '';
            if (!in_array($next, $units, true)) {
                $drop[$i] = true;
            }
            continue;
        }

        // Hyphenated alphanumeric code with a digit on either side.
        if (preg_match('/^[A-Za-z0-9]+-[A-Za-z0-9]+$/', $bare) && preg_match('/[0-9]/', $bare)) {
            $drop[$i] = true;
            continue;
        }

        // Standalone alphanumeric code mixing letters+digits, 5+ chars.
        // Short mixed tokens (S23, 4K, A54, X1...) are left alone.
        if (strlen($bare) >= 5 && (
            preg_match('/^[A-Za-z]+[0-9]+[A-Za-z]*$/', $bare) ||
            preg_match('/^[0-9]+[A-Za-z]+$/', $bare)
        )) {
            $drop[$i] = true;
        }
    }

    // 3. A short ALL-CAPS token immediately followed by a dropped code
    //    token is almost always a product-line prefix, not a real brand.
    for ($i = 0; $i < $n - 1; $i++) {
        $bare = trim($tokens[$i], "(),.");
        if (!$drop[$i] && $drop[$i + 1] && preg_match('/^[A-Z]{2,5}$/', $bare)) {
            $drop[$i] = true;
        }
    }

    $kept = [];
    for ($i = 0; $i < $n; $i++) {
        if (!$drop[$i]) {
            $kept[] = $tokens[$i];
        }
    }
    $title = implode(' ', $kept);

    // 4. Tidy whitespace and stray leading/trailing punctuation.
    $title = preg_replace('/\s+/', ' ', $title);
    $title = trim($title, " ,.-");

    return $title !== '' ? $title : $name; // never produce an empty title
}

/**
 * PATCH one product row in Supabase. Uses the service key (write access) —
 * same curl pattern as order_lib.php's Supabase insert / sync_products.php's
 * supabaseUpsert(), just PATCH + an id filter instead of POST + on_conflict.
 */
function supabase_patch_product(array $cfg, int $id, array $fields): array
{
    $url = rtrim($cfg['supabase_url'], '/') . '/rest/v1/products?id=eq.' . $id;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . $cfg['supabase_service_key'],
            'Authorization: Bearer ' . $cfg['supabase_service_key'],
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ],
        CURLOPT_POSTFIELDS => json_encode($fields, JSON_UNESCAPED_UNICODE),
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    return [
        'success' => $body !== false && $status >= 200 && $status < 300,
        'status'  => $status,
        'error'   => $err,
    ];
}

echo "==========================================\n";
echo " Title cleanup — APPLY to Supabase\n";
echo "==========================================\n\n";

$products = sb_get($cfg, 'products?select=id,name,name_original&order=id.asc');
if (empty($products)) {
    exit("No products fetched — check Supabase credentials/connection.\n");
}

$updated   = 0;
$skipped   = 0;
$unchanged = 0;
$failedIds = [];

foreach ($products as $p) {
    $id = (int)($p['id'] ?? 0);
    if (!$id) {
        continue;
    }

    if (in_array($id, SKIP_IDS, true)) {
        $skipped++;
        echo "SKIP  id=$id (manually excluded — cleaner over-stripped real model/shade info)\n";
        continue;
    }

    $original = (string)($p['name'] ?? '');
    $cleaned  = clean_product_title($original);

    if ($cleaned === $original) {
        $unchanged++;
        continue;
    }

    $fields = ['name' => $cleaned];

    // Safety net: name_original should already be backfilled (per the SQL
    // you ran), but if any row somehow has it empty, set it to the current
    // (pre-clean) name in this same request so the original is never lost.
    $nameOriginal = $p['name_original'] ?? null;
    if ($nameOriginal === null || trim((string)$nameOriginal) === '') {
        $fields['name_original'] = $original;
    }

    $result = supabase_patch_product($cfg, $id, $fields);

    if ($result['success']) {
        $updated++;
        echo "OK    id=$id  \"$original\" -> \"$cleaned\"\n";
    } else {
        $failedIds[] = $id;
        echo "FAIL  id=$id (HTTP {$result['status']}" . ($result['error'] ? ": {$result['error']}" : '') . ")\n";
    }

    usleep(200000); // small pacing delay between writes
}

echo "\n==========================================\n";
echo "Updated:   $updated\n";
echo "Skipped:   $skipped (manually excluded ids)\n";
echo "Unchanged: $unchanged (already clean, nothing to do)\n";
echo "Failed:    " . count($failedIds) . (empty($failedIds) ? '' : ' (ids: ' . implode(', ', $failedIds) . ')') . "\n";
echo "==========================================\n";
