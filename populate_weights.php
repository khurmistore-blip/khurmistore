<?php
declare(strict_types=1);

/**
 * populate_weights.php — Fills Supabase `products.weight` (kg) from BigBuy's
 * getProduct() response, for use by shipping_lib.php's weight-based Spain
 * shipping lookup table (NOT the live BigBuy shipping API, which is
 * unreliable — see sync_shipping.php from the earlier live-API attempt,
 * now superseded for this purpose).
 * ---------------------------------------------------------------------------
 * BEFORE running with &apply=1, run this SQL yourself in Supabase:
 *   alter table public.products add column if not exists weight numeric;
 *
 * HOW TO RUN (browser):
 *   PREVIEW (default, no DB writes):
 *     khurmistore.es/populate_weights.php?key=khurmi2026&start=0&count=10
 *   APPLY (writes to Supabase):
 *     khurmistore.es/populate_weights.php?key=khurmi2026&start=0&count=10&apply=1
 *
 * IMPORTANT — the 'weight' field name on BigBuy's getProduct() response is
 * NOT independently confirmed (bigbuy.php's own docblock only mentions
 * wholesalePrice/sku/retailPrice). The FIRST product processed in any run
 * dumps its full raw response keys to the output so you can visually
 * confirm 'weight' is really the right key before trusting the rest of the
 * batch — if it's actually named something else, stop and tell me the real
 * key name from that dump.
 *
 * On failure or missing weight -> left null (shipping_lib.php's
 * calcShipping() then falls back to the EUR 4.99 default). Never writes 0.
 *
 * DELETE this file from the server after you're done using it.
 */

if (php_sapi_name() !== 'cli') {
    if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
    header('Content-Type: text/plain; charset=utf-8');
}
set_time_limit(0);

require_once __DIR__ . '/bigbuy.php';
require_once __DIR__ . '/supabase.php';
$cfg = require __DIR__ . '/config.php';
$bb  = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

/**
 * PATCH one product row in Supabase. Same curl pattern as apply_titles.php's
 * supabase_patch_product() (service key, PATCH + id filter).
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

/* ------------------------------------------------------------------ *
 *  Batch/pagination + mode
 * ------------------------------------------------------------------ */
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$count = isset($_GET['count']) ? (int)$_GET['count'] : 10;
$apply = ($_GET['apply'] ?? '') === '1';

$products = sb_get($cfg, 'products?select=id,name,cj_pid,weight&order=id.asc');
if (empty($products)) {
    exit("No products fetched — check Supabase credentials/connection.\n");
}
$batch = array_slice($products, $start, $count);

echo "==========================================\n";
echo " BigBuy weight sync — " . ($apply ? "APPLY to Supabase" : "PREVIEW ONLY (no DB writes)") . "\n";
echo " Batch: products $start.." . ($start + count($batch)) . " of " . count($products) . "\n";
echo "==========================================\n\n";

$updated    = 0;
$unchanged  = 0;
$failed     = 0;
$skipped    = 0;
$failedIds  = [];
$dumpedFirst = false;

foreach ($batch as $p) {
    $id      = (int)($p['id'] ?? 0);
    $name    = (string)($p['name'] ?? '');
    $cjPid   = trim((string)($p['cj_pid'] ?? ''));
    $current = $p['weight'] ?? null;

    if (!$id || $cjPid === '') {
        $skipped++;
        echo "SKIP  id=$id \"$name\" (no cj_pid)\n";
        continue;
    }

    $res = $bb->getProduct((int)$cjPid, 'es');
    sleep(1); // rate limit: 1 request/second

    if (!($res['success'] ?? false)) {
        $failed++;
        $failedIds[] = $id;
        echo "FAIL  id=$id \"$name\" (cj_pid=$cjPid) — getProduct() HTTP " . ($res['status'] ?? '?') . "\n";
        continue;
    }

    $data = $res['data'];
    $rec  = (is_array($data) && isset($data[0]) && is_array($data[0])) ? $data[0] : $data;

    if (!$dumpedFirst) {
        $dumpedFirst = true;
        echo "---- First product raw response (verify 'weight' is the real key) ----\n";
        echo is_array($rec) ? json_encode($rec, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n" : "(not an array)\n";
        echo "-------------------------------------------------------------------------\n\n";
    }

    $weight = is_array($rec) ? ($rec['weight'] ?? null) : null;

    if ($weight === null) {
        $failed++;
        $failedIds[] = $id;
        echo "FAIL  id=$id \"$name\" (cj_pid=$cjPid) — no 'weight' field in response\n";
        continue;
    }

    $newWeight = (float)$weight;
    $currentDisplay = $current === null ? '(none)' : (string)$current;

    if ($current !== null && abs((float)$current - $newWeight) < 0.001) {
        $unchanged++;
        echo "SAME  id=$id \"$name\" — {$newWeight}kg (unchanged)\n";
        continue;
    }

    if ($apply) {
        $patchResult = supabase_patch_product($cfg, $id, ['weight' => $newWeight]);
        if ($patchResult['success']) {
            $updated++;
            echo "OK    id=$id \"$name\" — {$currentDisplay}kg -> {$newWeight}kg (saved)\n";
        } else {
            $failed++;
            $failedIds[] = $id;
            echo "FAIL  id=$id \"$name\" (Supabase PATCH HTTP {$patchResult['status']}" . ($patchResult['error'] ? ": {$patchResult['error']}" : '') . ")\n";
        }
    } else {
        $updated++;
        echo "WOULD UPDATE  id=$id \"$name\" — {$currentDisplay}kg -> {$newWeight}kg\n";
    }
}

echo "\n==========================================\n";
echo ($apply ? "Updated & saved: " : "Would update: ") . "$updated\n";
echo "Unchanged: $unchanged (already correct)\n";
echo "Skipped:   $skipped (no cj_pid)\n";
echo "Failed:    " . count($failedIds) . (empty($failedIds) ? '' : ' (ids: ' . implode(', ', $failedIds) . ')') . " (left null -> EUR 4.99 default applies)\n";
if (!$apply) {
    echo "\nPreview only — re-run with &apply=1 on the same &start/&count to save.\n";
}
echo "==========================================\n";
