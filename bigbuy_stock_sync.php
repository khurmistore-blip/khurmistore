<?php
declare(strict_types=1);

/**
 * bigbuy_stock_sync.php — Syncs real BigBuy stock into Supabase `products`
 * (stock, is_active, stock_synced_at).
 *
 * Uses the SINGLE-product endpoint (BigBuy::getProductStockByHandlingDays()),
 * one call per product, paced 5.5s apart. The batch endpoint
 * (getProductsStockByHandlingDays()) was tried first but hits HTTP 429
 * around page 11 (~10,000 products) — paging through BigBuy's entire
 * catalog to find our ~55 products isn't viable. For ~55 products at 5.5s
 * spacing, a full sync takes ~5 minutes — acceptable for a once-daily cron.
 * ---------------------------------------------------------------------------
 * BEFORE running with &apply=1, run this SQL yourself in Supabase:
 *   alter table public.products add column if not exists is_active boolean default true;
 *   alter table public.products add column if not exists stock_synced_at timestamptz;
 *
 * HOW TO RUN:
 *   Browser (preview, no DB writes): khurmistore.es/bigbuy_stock_sync.php?key=khurmi2026&start=0&count=3
 *   Browser (apply):                 khurmistore.es/bigbuy_stock_sync.php?key=khurmi2026&start=0&count=55&apply=1
 *   CLI (cron):                      php bigbuy_stock_sync.php apply=1   (no &key= needed — CLI bypasses the guard)
 *
 * &count=3 now genuinely only costs 3 BigBuy calls (~16s) — unlike the
 * batch-endpoint version, per-product calls make &start=/&count= testing
 * cheap again.
 *
 * Preview-then-apply stays the default — pass &apply=1 once the preview
 * looks right. Never deletes products — only PATCHes stock/is_active/
 * stock_synced_at.
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

const STOCK_CALL_DELAY_SECONDS = 5.5; // endpoint's own limit is 1 req/5sec — stay above it

/**
 * getProductStockByHandlingDays() with 429 retry/backoff. Since the delay
 * between calls (STOCK_CALL_DELAY_SECONDS) already respects the documented
 * 1-req/5sec limit, a 429 here means something unexpected (clock drift,
 * concurrent caller, stricter real-world limit) — back off harder than the
 * normal pacing rather than hammering it again immediately.
 */
function bb_get_single_stock_with_retry(BigBuy $bb, int $cjPid, int $maxRetries = 4): array
{
    $attempts = 0;
    do {
        $res      = $bb->getProductStockByHandlingDays($cjPid);
        $httpCode = $res['status'] ?? null;
        if ($httpCode == 429) {
            $attempts++;
            $wait = 15 * $attempts; // 15s, 30s, 45s, 60s
            echo "   429 rate-limited, waiting {$wait}s (retry $attempts/$maxRetries)...\n";
            @ob_flush(); @flush();
            sleep($wait);
        }
    } while (($res['status'] ?? null) == 429 && $attempts < $maxRetries);
    return $res;
}

/**
 * Sums stocks[].quantity from getProductStockByHandlingDays()'s response.
 * Returns 0 on any failure/unexpected shape — never silently "in stock".
 */
function bb_extract_stock_quantity(array $stockRes): int
{
    if (!($stockRes['success'] ?? false)) {
        return 0;
    }
    $data = $stockRes['data'];
    $rec  = (is_array($data) && isset($data[0]) && is_array($data[0])) ? $data[0] : $data;
    if (!is_array($rec) || !isset($rec['stocks']) || !is_array($rec['stocks'])) {
        return 0;
    }
    $total = 0;
    foreach ($rec['stocks'] as $s) {
        $total += (int)($s['quantity'] ?? 0);
    }
    return $total;
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
echo " BigBuy stock sync (single-product endpoint, " . STOCK_CALL_DELAY_SECONDS . "s spacing) — " . ($apply ? "APPLY to Supabase" : "PREVIEW ONLY (no DB writes)") . "\n";
echo " Batch: products $start.." . ($start + count($batch)) . " of " . count($products) . "\n";
echo " Estimated time: ~" . round(count($batch) * STOCK_CALL_DELAY_SECONDS) . "s\n";
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

    $stockRes = bb_get_single_stock_with_retry($bb, (int)$cjPid);
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
            echo "OK    id=$id \"$name\" (cj_pid=$cjPid) — stock=$qty, is_active=" . ($isActive ? 'true' : 'false') . " (saved)\n";
        } else {
            $failed++;
            $failedIds[] = $id;
            echo "FAIL  id=$id \"$name\" (Supabase PATCH HTTP {$patchResult['status']}" . ($patchResult['error'] ? ": {$patchResult['error']}" : '') . ")\n";
        }
    } else {
        $updated++;
        echo "WOULD UPDATE  id=$id \"$name\" (cj_pid=$cjPid) — stock=$qty, is_active=" . ($isActive ? 'true' : 'false') . "\n";
    }

    @ob_flush(); @flush();
    usleep((int)(STOCK_CALL_DELAY_SECONDS * 1000000));
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
