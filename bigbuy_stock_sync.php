<?php
declare(strict_types=1);

/**
 * bigbuy_stock_sync.php — Syncs real BigBuy stock into Supabase `products`
 * (stock, is_active, stock_synced_at).
 *
 * Uses the SINGLE-product endpoint (BigBuy::getProductStockByHandlingDays()),
 * one call per product, paced 7s apart (bumped from 5.5s — BigBuy's
 * documented limit is 1 req/5s but real-world overhead needs more margin).
 * The batch endpoint (getProductsStockByHandlingDays()) was tried first but
 * hits HTTP 429 around page 11 (~10,000 products) — paging through BigBuy's
 * entire catalog to find our ~55 products isn't viable.
 *
 * 429 handling is TWO-LEVEL and status-aware:
 *  - HTTP 404 (confirmed not found/discontinued) -> stock=0, is_active=false,
 *    written immediately. This is the ONLY case that ever writes stock=0.
 *  - HTTP 429 (rate limited) -> a few quick inner retries first; if still
 *    429, the product is queued and retried again in a SEPARATE pass after
 *    the whole batch finishes (giving the rate-limit window real time to
 *    reset) — NEVER written as stock=0 just because of a 429. If it's still
 *    429 after that final pass too, it's left completely untouched in the
 *    DB and reported as "still pending" — never guessed at.
 *  - Any other failure (5xx, curl error, unexpected shape) -> also skipped
 *    (no DB write), reported separately as "failed" (not auto-retried,
 *    since it's not confirmed to be the same kind of transient issue a 429 is).
 * ---------------------------------------------------------------------------
 * BEFORE running with &apply=1, run this SQL yourself in Supabase:
 *   alter table public.products add column if not exists is_active boolean default true;
 *   alter table public.products add column if not exists stock_synced_at timestamptz;
 *
 * HOW TO RUN:
 *   Browser, manual batch (preview): khurmistore.es/bigbuy_stock_sync.php?key=khurmi2026&start=0&count=3
 *   Browser, manual batch (apply):   khurmistore.es/bigbuy_stock_sync.php?key=khurmi2026&start=0&count=20&apply=1
 *   Browser, FULL catalog (apply):   khurmistore.es/bigbuy_stock_sync.php?key=khurmi2026&apply=1   (no &start/&count -> all products)
 *   CLI (cron):                      php bigbuy_stock_sync.php   (no &key needed, apply mode is AUTOMATIC, ALL products, every run)
 *
 * CLI runs always process every product and always write to Supabase —
 * there's no CLI preview mode. Test with the browser batch mode first.
 *
 * A ~55-product full run takes ~7-10 minutes (7s/product + any end-of-run
 * retries) — set_time_limit(0) below so it isn't killed partway through.
 *
 * Every run appends a timestamped summary to stock_sync.log (same
 * directory) — check that after a cron run instead of relying on cron's
 * own output capture.
 *
 * Never deletes products — only PATCHes stock/is_active/stock_synced_at,
 * and only for products this run actually got a definitive answer for.
 */

$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
    header('Content-Type: text/plain; charset=utf-8');
}
set_time_limit(0);

$runStartedAt = date('c');

require_once __DIR__ . '/bigbuy.php';
require_once __DIR__ . '/supabase.php';
$cfg = require __DIR__ . '/config.php';
$bb  = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

const STOCK_CALL_DELAY_SECONDS = 7; // bumped from 5.5s — more margin over BigBuy's 1 req/5s limit

/**
 * getProductStockByHandlingDays() with a couple of QUICK inner retries on
 * 429 (light backoff — the real recovery mechanism is the outer end-of-run
 * retry queue in the main loop below, which benefits from much more elapsed
 * time than a few inline sleeps can afford without stalling the whole run).
 */
function bb_get_single_stock_with_retry(BigBuy $bb, int $cjPid, int $maxRetries = 2): array
{
    $attempts = 0;
    do {
        $res      = $bb->getProductStockByHandlingDays($cjPid);
        $httpCode = $res['status'] ?? null;
        if ($httpCode == 429) {
            $attempts++;
            $wait = 10 * $attempts; // 10s, 20s
            echo "   429 rate-limited, waiting {$wait}s (inner retry $attempts/$maxRetries)...\n";
            @ob_flush(); @flush();
            sleep($wait);
        }
    } while (($res['status'] ?? null) == 429 && $attempts < $maxRetries);
    return $res;
}

/**
 * Sums stocks[].quantity from getProductStockByHandlingDays()'s response.
 * Only called once success is already confirmed.
 */
function bb_extract_stock_quantity(array $stockRes): int
{
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

/**
 * Processes one product: calls BigBuy, and returns a classified outcome
 * instead of a bare quantity, so the caller can tell "confirmed 0 stock"
 * apart from "we don't actually know yet".
 *   ['outcome' => 'ok',      'qty' => int]
 *   ['outcome' => 'not_found']              (HTTP 404 -> stock=0 is safe)
 *   ['outcome' => 'rate_limited']           (HTTP 429 -> never write stock=0)
 *   ['outcome' => 'error', 'status' => int] (anything else -> never write stock=0)
 */
function bb_check_product_stock(BigBuy $bb, int $cjPid): array
{
    $res    = bb_get_single_stock_with_retry($bb, $cjPid);
    $status = $res['status'] ?? 0;

    if ($res['success'] ?? false) {
        return ['outcome' => 'ok', 'qty' => bb_extract_stock_quantity($res)];
    }
    if ($status === 404) {
        return ['outcome' => 'not_found'];
    }
    if ($status === 429) {
        return ['outcome' => 'rate_limited'];
    }
    return ['outcome' => 'error', 'status' => $status];
}

/* ------------------------------------------------------------------ *
 *  Batch/pagination + mode
 *  - CLI: always ALL products, always apply=true, no &key needed.
 *  - Browser with no &start/&count: also ALL products (still requires
 *    &apply=1 to actually write — no CLI-style auto-apply for browser).
 *  - Browser with &start/&count: manual batch mode, unchanged from before.
 * ------------------------------------------------------------------ */
$noRangeSpecified = !isset($_GET['start']) && !isset($_GET['count']);
$processAll       = $isCli || $noRangeSpecified;
$apply            = $isCli || ($_GET['apply'] ?? '') === '1';

// &limit=1000 — Supabase/PostgREST's default max-rows setting was capping
// this at 60 despite no filter excluding the rest (confirmed via SQL: DB
// actually has 83+ products with a cj_pid). Explicit limit overrides that.
$products = sb_get($cfg, 'products?cj_pid=not.is.null&select=id,name,cj_pid,stock,is_active&order=id.asc&limit=1000');
if (empty($products)) {
    exit("No products with cj_pid found — check Supabase credentials/connection.\n");
}
// DIAGNOSTIC (temporary): written immediately, not deferred to end-of-run,
// so progress survives even if the process gets killed mid-run (e.g. by a
// hosting/cron timeout) — the normal end-of-run summary below writes nothing
// in that case since it never gets reached.
@file_put_contents(__DIR__ . '/stock_sync.log', "\n=== Run started $runStartedAt (" . ($isCli ? 'CLI/cron' : 'browser') . ") — fetched " . count($products) . " products total ===\n", FILE_APPEND | LOCK_EX);

if ($processAll) {
    $start = 0;
    $batch = $products;
} else {
    $start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
    $count = isset($_GET['count']) ? (int)$_GET['count'] : 20;
    $batch = array_slice($products, $start, $count);
}

$logLines = []; // summary-only lines, appended to stock_sync.log at the end
function log_line(string $line, array &$logLines): void
{
    echo $line . "\n";
    $logLines[] = $line;
}

echo "==========================================\n";
echo "=== VERSION: single-product-v5-cron-ready ===\n";
echo " BigBuy stock sync (single-product endpoint, " . STOCK_CALL_DELAY_SECONDS . "s spacing) — " . ($apply ? "APPLY to Supabase" : "PREVIEW ONLY (no DB writes)") . ($isCli ? " [CLI]" : " [browser]") . "\n";
echo " Batch: products $start.." . ($start + count($batch)) . " of " . count($products) . ($processAll ? " (ALL — no &start/&count given)" : '') . "\n";
echo " Estimated time (main pass): ~" . round(count($batch) * STOCK_CALL_DELAY_SECONDS / 60, 1) . " min (+ time for any end-of-run retries)\n";
echo "==========================================\n\n";

$succeeded  = []; // id => ['name'=>..., 'qty'=>int, 'isActive'=>bool]
$pendingIds = []; // ids that are STILL rate-limited after the retry queue pass
$failedIds  = []; // ids that hit a non-429/non-404 error (not auto-retried)
$retryQueue = []; // ['id'=>..., 'name'=>..., 'cjPid'=>...] — 429s from the main pass

function process_one(array $p, BigBuy $bb, array $cfg, bool $apply, string $syncedAt, array &$succeeded, array &$failedIds): string
{
    $id    = (int)($p['id'] ?? 0);
    $name  = (string)($p['name'] ?? '');
    $cjPid = trim((string)($p['cj_pid'] ?? ''));

    $result = bb_check_product_stock($bb, (int)$cjPid);

    if ($result['outcome'] === 'ok' || $result['outcome'] === 'not_found') {
        $qty      = $result['qty'] ?? 0; // not_found -> 0 (confirmed safe per file header)
        $isActive = $qty > 0;

        if ($result['outcome'] === 'not_found') {
            echo "NOT FOUND  id=$id \"$name\" (cj_pid=$cjPid) — HTTP 404, confirmed not found -> stock=0\n";
        }

        if ($apply) {
            $patchResult = supabase_patch_product($cfg, $id, [
                'stock'           => $qty,
                'is_active'       => $isActive,
                'stock_synced_at' => $syncedAt,
            ]);
            if ($patchResult['success']) {
                echo "OK    id=$id \"$name\" (cj_pid=$cjPid) — stock=$qty, is_active=" . ($isActive ? 'true' : 'false') . " (saved)\n";
                $succeeded[$id] = ['name' => $name, 'qty' => $qty, 'isActive' => $isActive];
            } else {
                echo "FAIL  id=$id \"$name\" (Supabase PATCH HTTP {$patchResult['status']}" . ($patchResult['error'] ? ": {$patchResult['error']}" : '') . ")\n";
                $failedIds[] = $id;
            }
        } else {
            echo "WOULD UPDATE  id=$id \"$name\" (cj_pid=$cjPid) — stock=$qty, is_active=" . ($isActive ? 'true' : 'false') . "\n";
            $succeeded[$id] = ['name' => $name, 'qty' => $qty, 'isActive' => $isActive];
        }
        return 'done';
    }

    if ($result['outcome'] === 'rate_limited') {
        echo "QUEUE  id=$id \"$name\" (cj_pid=$cjPid) — still 429 after inner retries, deferred to end-of-run retry pass\n";
        return 'rate_limited';
    }

    // 'error' — some other failure, never write stock=0 for this.
    echo "FAIL  id=$id \"$name\" (cj_pid=$cjPid) — HTTP " . ($result['status'] ?? '?') . ", not auto-retried\n";
    $failedIds[] = $id;
    return 'error';
}

echo "---- MAIN PASS ----\n";
foreach ($batch as $p) {
    $id    = (int)($p['id'] ?? 0);
    $cjPid = trim((string)($p['cj_pid'] ?? ''));
    if (!$id || $cjPid === '') {
        continue; // shouldn't happen given the cj_pid filter above, but stay defensive
    }

    $outcome = process_one($p, $bb, $cfg, $apply, date('c'), $succeeded, $failedIds);
    // DIAGNOSTIC (temporary): immediate per-product write, see note above.
    @file_put_contents(__DIR__ . '/stock_sync.log', date('c') . " MAIN  id=$id \"" . ($p['name'] ?? '') . "\" outcome=$outcome\n", FILE_APPEND | LOCK_EX);
    if ($outcome === 'rate_limited') {
        $retryQueue[] = $p;
    }

    @ob_flush(); @flush();
    usleep((int)(STOCK_CALL_DELAY_SECONDS * 1000000));
}

if (!empty($retryQueue)) {
    echo "\n---- END-OF-RUN RETRY PASS (" . count($retryQueue) . " product(s) still pending from the main pass) ----\n";
    foreach ($retryQueue as $p) {
        $id = (int)($p['id'] ?? 0);
        $outcome = process_one($p, $bb, $cfg, $apply, date('c'), $succeeded, $failedIds);
        // DIAGNOSTIC (temporary): immediate per-product write, see note above.
        @file_put_contents(__DIR__ . '/stock_sync.log', date('c') . " RETRY id=$id \"" . ($p['name'] ?? '') . "\" outcome=$outcome\n", FILE_APPEND | LOCK_EX);
        if ($outcome === 'rate_limited') {
            $pendingIds[] = $id;
        }
        @ob_flush(); @flush();
        usleep((int)(STOCK_CALL_DELAY_SECONDS * 1000000));
    }
}

$nowInStock    = count(array_filter($succeeded, fn($r) => $r['isActive']));
$nowOutOfStock = count($succeeded) - $nowInStock;

echo "\n";
log_line("==========================================", $logLines);
log_line("Run finished " . date('c') . " (" . ($isCli ? 'CLI/cron' : 'browser') . ", " . ($apply ? 'APPLY' : 'PREVIEW') . ", " . ($processAll ? 'all products' : "start=$start") . ")", $logLines);
log_line(($apply ? "Updated & saved: " : "Would update: ") . count($succeeded), $logLines);
log_line("Now in-stock:     $nowInStock", $logLines);
log_line("Now out-of-stock: $nowOutOfStock", $logLines);
log_line("Still pending (429, untouched in DB, re-run the script for these): " . count($pendingIds) . (empty($pendingIds) ? '' : ' (ids: ' . implode(', ', $pendingIds) . ')'), $logLines);
log_line("Failed (other errors, not auto-retried): " . count($failedIds) . (empty($failedIds) ? '' : ' (ids: ' . implode(', ', $failedIds) . ')'), $logLines);
if (!$apply) {
    echo "\nPreview only — re-run with &apply=1 on the same &start/&count to save.\n";
}
echo "==========================================\n";

$logEntry = "\n=== Run started $runStartedAt ===\n" . implode("\n", $logLines) . "\n";
@file_put_contents(__DIR__ . '/stock_sync.log', $logEntry, FILE_APPEND | LOCK_EX);
