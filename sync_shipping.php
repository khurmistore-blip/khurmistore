<?php
declare(strict_types=1);

/**
 * sync_shipping.php — Fetches each product's lowest shipping cost to Spain
 * from BigBuy and writes it into Supabase's `products.shipping_cost` column.
 * ---------------------------------------------------------------------------
 * BEFORE running with &apply=1, run this SQL yourself in Supabase:
 *   alter table public.products add column if not exists shipping_cost numeric;
 *
 * HOW TO RUN (browser):
 *   PREVIEW (default, no DB writes):
 *     khurmistore.es/sync_shipping.php?key=khurmi2026&start=0&count=10
 *   APPLY (writes to Supabase):
 *     khurmistore.es/sync_shipping.php?key=khurmi2026&start=0&count=10&apply=1
 *
 * Run in small batches (default count=10) and increase &start= each time —
 * this does TWO BigBuy calls per product (resolve SKU via getProduct(),
 * then getLowestShippingCost()), each paced 1 request/second, so a batch of
 * 10 takes ~20s+ minimum.
 *
 * cj_pid is BigBuy's numeric product ID, NOT the SKU/reference the shipping
 * endpoint needs — see bigbuy.php's getLowestShippingCost() docblock. This
 * script resolves cj_pid -> sku via getProduct() first, every run (BigBuy
 * has no bulk lookup for this and the SKU isn't stored anywhere locally).
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

const COUNTRY = 'ES';

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

/**
 * Resolve a product's BigBuy SKU/reference from its numeric cj_pid via
 * getProduct() — the shipping endpoint needs the reference, but only the
 * numeric id is stored locally (see file header). Returns null on failure.
 */
function resolve_sku(BigBuy $bb, int $cjPid): ?string
{
    $res = $bb->getProduct($cjPid, 'es');
    if (!($res['success'] ?? false)) {
        return null;
    }
    $data = $res['data'];
    $rec  = (is_array($data) && isset($data[0]) && is_array($data[0])) ? $data[0] : $data;
    $sku  = is_array($rec) ? ($rec['sku'] ?? null) : null;
    return $sku !== null && $sku !== '' ? (string)$sku : null;
}

/* ------------------------------------------------------------------ *
 *  Batch/pagination + mode
 * ------------------------------------------------------------------ */
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$count = isset($_GET['count']) ? (int)$_GET['count'] : 10;
$apply = ($_GET['apply'] ?? '') === '1';

$products = sb_get($cfg, 'products?select=id,name,cj_pid,shipping_cost&order=id.asc');
if (empty($products)) {
    exit("No products fetched — check Supabase credentials/connection.\n");
}
$batch = array_slice($products, $start, $count);

echo "==========================================\n";
echo " BigBuy shipping cost sync (" . COUNTRY . ") — " . ($apply ? "APPLY to Supabase" : "PREVIEW ONLY (no DB writes)") . "\n";
echo " Batch: products $start.." . ($start + count($batch)) . " of " . count($products) . "\n";
echo "==========================================\n\n";

$updated   = 0;
$unchanged = 0;
$failed    = 0;
$skipped   = 0;
$failedIds = [];

foreach ($batch as $p) {
    $id      = (int)($p['id'] ?? 0);
    $name    = (string)($p['name'] ?? '');
    $cjPid   = trim((string)($p['cj_pid'] ?? ''));
    $current = $p['shipping_cost'] ?? null;

    if (!$id || $cjPid === '') {
        $skipped++;
        echo "SKIP  id=$id \"$name\" (no cj_pid)\n";
        continue;
    }

    $sku = resolve_sku($bb, (int)$cjPid);
    sleep(1); // rate limit: 1 request/second

    if ($sku === null) {
        $failed++;
        $failedIds[] = $id;
        echo "FAIL  id=$id \"$name\" (cj_pid=$cjPid) — could not resolve SKU via getProduct()\n";
        continue;
    }

    $newCost = $bb->getLowestShippingCost($sku, COUNTRY);
    sleep(1); // rate limit: 1 request/second

    if ($newCost === null) {
        $failed++;
        $failedIds[] = $id;
        echo "FAIL  id=$id \"$name\" (sku=$sku) — getLowestShippingCost() failed\n";
        continue;
    }

    $currentDisplay = $current === null ? '(none)' : number_format((float)$current, 2);
    $newDisplay     = number_format($newCost, 2);

    if ($current !== null && abs((float)$current - $newCost) < 0.005) {
        $unchanged++;
        echo "SAME  id=$id \"$name\" — EUR $newDisplay (unchanged)\n";
        continue;
    }

    if ($apply) {
        $patchResult = supabase_patch_product($cfg, $id, ['shipping_cost' => round($newCost, 2)]);
        if ($patchResult['success']) {
            $updated++;
            echo "OK    id=$id \"$name\" — EUR $currentDisplay -> EUR $newDisplay (saved)\n";
        } else {
            $failed++;
            $failedIds[] = $id;
            echo "FAIL  id=$id \"$name\" (Supabase PATCH HTTP {$patchResult['status']}" . ($patchResult['error'] ? ": {$patchResult['error']}" : '') . ")\n";
        }
    } else {
        $updated++;
        echo "WOULD UPDATE  id=$id \"$name\" — EUR $currentDisplay -> EUR $newDisplay\n";
    }
}

echo "\n==========================================\n";
echo ($apply ? "Updated & saved: " : "Would update: ") . "$updated\n";
echo "Unchanged: $unchanged (already correct)\n";
echo "Skipped:   $skipped (no cj_pid)\n";
echo "Failed:    " . count($failedIds) . (empty($failedIds) ? '' : ' (ids: ' . implode(', ', $failedIds) . ')') . "\n";
if (!$apply) {
    echo "\nPreview only — re-run with &apply=1 on the same &start/&count to save.\n";
}
echo "==========================================\n";
