<?php
declare(strict_types=1);

/**
 * bigbuy_stock_sync.php — Syncs real BigBuy stock into Supabase `products`
 * (stock, is_active, stock_synced_at). Reuses BigBuy::getProductStock()
 * (already in bigbuy.php, hits /rest/catalog/productstock/{id}.json) — no
 * new method added there.
 * ---------------------------------------------------------------------------
 * BEFORE running with &apply=1, run this SQL yourself in Supabase:
 *   alter table public.products add column if not exists is_active boolean default true;
 *   alter table public.products add column if not exists stock_synced_at timestamptz;
 *
 * HOW TO RUN:
 *   Browser (preview, no DB writes): khurmistore.es/bigbuy_stock_sync.php?key=khurmi2026&start=0&count=20
 *   Browser (apply):                 khurmistore.es/bigbuy_stock_sync.php?key=khurmi2026&start=0&count=20&apply=1
 *   CLI (cron):                      php bigbuy_stock_sync.php apply=1   (no &key= needed — CLI bypasses the guard)
 *
 * Preview-then-apply is not something you explicitly asked for this time,
 * but every other write-capable sync script in this project uses it, and
 * your ad is live — added as a safety default. Pass &apply=1 once you've
 * checked the preview output.
 *
 * Batched via &start=/&count= (default 20) so a big catalog + 250ms pacing
 * + possible 429 retries doesn't hit the server's max execution time.
 *
 * Never deletes products — only PATCHes stock/is_active/stock_synced_at.
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
 * Extract total stock quantity from getProductStock()'s response. Same
 * fallback chain sync_products.php already uses for this endpoint, EXCEPT
 * the final fallback here is 0, not 1 — sync_products.php's version treats
 * "couldn't parse the response" as "assume 1 in stock" (fine for an initial
 * import). For THIS script — the thing standing between "BigBuy said
 * nothing" and "we still let someone order it" — an unparseable or failed
 * response must never be silently treated as in-stock. Deliberate deviation
 * from the existing pattern, flagged rather than silently copied.
 */
function bb_extract_stock_quantity(array $stockRes): int
{
    if (!($stockRes['success'] ?? false)) {
        return 0;
    }
    $sd   = $stockRes['data'];
    $srec = (is_array($sd) && isset($sd[0]) && is_array($sd[0])) ? $sd[0] : $sd;
    if (!is_array($srec)) {
        return 0;
    }
    return (int)($srec['quantity'] ?? ($srec['stocks'][0]['quantity'] ?? ($srec[0]['quantity'] ?? 0)));
}

/**
 * getProductStock() with 429 retry/backoff — same wait pattern
 * sync_products.php already uses for this exact endpoint.
 */
function bb_get_stock_with_retry(BigBuy $bb, int $cjPid, int $maxRetries = 5): array
{
    $attempts = 0;
    $res      = ['success' => false, 'status' => 0];
    do {
        $res      = $bb->getProductStock($cjPid);
        $httpCode = $res['status'] ?? null;
        if ($httpCode == 429) {
            $attempts++;
            echo "   429 rate-limited, waiting 20s (retry $attempts/$maxRetries)...\n";
            @ob_flush(); @flush();
            sleep(20);
        }
    } while (($res['status'] ?? null) == 429 && $attempts < $maxRetries);
    return $res;
}

/**
 * PATCH one product row. Same curl pattern as apply_titles.php's
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
$count = isset($_GET['count']) ? (int)$_GET['count'] : 20;
$apply = ($_GET['apply'] ?? '') === '1';

$products = sb_get($cfg, 'products?cj_pid=not.is.null&select=id,name,cj_pid,stock,is_active&order=id.asc');
if (empty($products)) {
    exit("No products with cj_pid found — check Supabase credentials/connection.\n");
}
$batch = array_slice($products, $start, $count);

echo "==========================================\n";
echo " BigBuy stock sync — " . ($apply ? "APPLY to Supabase" : "PREVIEW ONLY (no DB writes)") . "\n";
echo " Batch: products $start.." . ($start + count($batch)) . " of " . count($products) . "\n";
echo "==========================================\n\n";

$updated       = 0;
$nowInStock    = 0;
$nowOutOfStock = 0;
$failed        = 0;
$failedIds     = [];
$syncedAt      = date('c'); // ISO 8601 — safe for a timestamptz column

foreach ($batch as $p) {
    $id    = (int)($p['id'] ?? 0);
    $name  = (string)($p['name'] ?? '');
    $cjPid = trim((string)($p['cj_pid'] ?? ''));

    if (!$id || $cjPid === '') {
        continue; // shouldn't happen given the cj_pid filter above, but stay defensive
    }

    $stockRes = bb_get_stock_with_retry($bb, (int)$cjPid);
    $qty      = bb_extract_stock_quantity($stockRes);
    $isActive = $qty > 0;

    if ($isActive) {
        $nowInStock++;
    } else {
        $nowOutOfStock++;
    }

    if (!($stockRes['success'] ?? false)) {
        echo "WARN  id=$id \"$name\" (cj_pid=$cjPid) — BigBuy lookup failed (HTTP " . ($stockRes['status'] ?? '?') . "), treating as OUT OF STOCK (stock=0)\n";
    }

    if ($apply) {
        $patchResult = supabase_patch_product($cfg, $id, [
            'stock'           => $qty,
            'is_active'       => $isActive,
            'stock_synced_at' => $syncedAt,
        ]);
        if ($patchResult['success']) {
            $updated++;
            echo "OK    id=$id \"$name\" — stock=$qty, is_active=" . ($isActive ? 'true' : 'false') . " (saved)\n";
        } else {
            $failed++;
            $failedIds[] = $id;
            echo "FAIL  id=$id \"$name\" (Supabase PATCH HTTP {$patchResult['status']}" . ($patchResult['error'] ? ": {$patchResult['error']}" : '') . ")\n";
        }
    } else {
        $updated++;
        echo "WOULD UPDATE  id=$id \"$name\" — stock=$qty, is_active=" . ($isActive ? 'true' : 'false') . "\n";
    }

    usleep(250000); // 250ms pacing between BigBuy calls
}

echo "\n==========================================\n";
echo ($apply ? "Updated & saved: " : "Would update: ") . "$updated\n";
echo "Now in-stock:     $nowInStock\n";
echo "Now out-of-stock: $nowOutOfStock\n";
echo "Failed:    " . count($failedIds) . (empty($failedIds) ? '' : ' (ids: ' . implode(', ', $failedIds) . ')') . "\n";
if (!$apply) {
    echo "\nPreview only — re-run with &apply=1 on the same &start/&count to save.\n";
}
echo "==========================================\n";
