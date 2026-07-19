<?php
declare(strict_types=1);

/**
 * backfill_videos.php — Backfills `video_id` on Supabase `products` from
 * BigBuy's "video" field (GET /rest/catalog/product/{id}.json).
 *
 * Same safety pattern as bigbuy_stock_sync.php: single-product endpoint,
 * one call per product, paced 7s apart, two-level 429 handling (quick inner
 * retries, then a separate end-of-run retry pass), &key= gate, &apply=1
 * gating, optional &start=/&count= batching, optional &cj_pid= single-
 * product filter, and line-by-line logging so a mid-run timeout doesn't
 * lose completed work.
 *
 * video_id rules:
 *  - BigBuy "0" (string), empty, or missing -> video_id = NULL (no video).
 *  - Any other non-empty value -> stored as-is (raw BigBuy video ID).
 *  - HTTP 404 (product not found) -> skipped entirely, NOT written (unlike
 *    stock sync's 404->0, there's no safe "confirmed absence" value for
 *    video — we simply don't know, so the existing column is left alone).
 *  - HTTP 429 -> never written; queued for the end-of-run retry pass.
 *  - Any other error -> skipped, reported as failed, not auto-retried.
 *
 * HOW TO RUN:
 *   Preview, one product:   khurmistore.es/backfill_videos.php?key=khurmi2026&cj_pid=1070220
 *   Apply, one product:     khurmistore.es/backfill_videos.php?key=khurmi2026&cj_pid=1070220&apply=1
 *   Preview, manual batch:  khurmistore.es/backfill_videos.php?key=khurmi2026&start=0&count=20
 *   Apply, manual batch:    khurmistore.es/backfill_videos.php?key=khurmi2026&start=0&count=20&apply=1
 *   Apply, FULL catalog:    khurmistore.es/backfill_videos.php?key=khurmi2026&apply=1   (no &start/&count -> all products)
 *   CLI:                    php backfill_videos.php   (no &key needed, apply mode is AUTOMATIC, ALL products)
 *
 * Every run appends a timestamped summary to backfill_videos.log (same
 * directory) — check that after a run instead of relying on output capture.
 *
 * Never deletes products — only PATCHes video_id, and only for products
 * this run actually got a definitive answer for.
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

const VIDEO_CALL_DELAY_SECONDS = 7; // same margin as bigbuy_stock_sync.php over BigBuy's 1 req/5s limit

/**
 * getProduct() with a couple of QUICK inner retries on 429 (light backoff —
 * same pattern as bigbuy_stock_sync.php's bb_get_single_stock_with_retry();
 * the real recovery mechanism is the end-of-run retry queue below).
 */
function bb_get_single_product_with_retry(BigBuy $bb, int $cjPid, int $maxRetries = 2): array
{
    $attempts = 0;
    do {
        $res      = $bb->getProduct($cjPid);
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
 * Extracts video_id from a successful getProduct() response. Normalizes
 * BigBuy's "0"/empty/missing to NULL — never stores the literal string "0".
 */
function bb_extract_video_id(array $productRes): ?string
{
    $data = $productRes['data'];
    $rec  = (is_array($data) && isset($data[0]) && is_array($data[0])) ? $data[0] : $data;
    if (!is_array($rec)) {
        return null;
    }
    $raw = trim((string)($rec['video'] ?? ''));
    return ($raw !== '' && $raw !== '0') ? $raw : null;
}

/**
 * Classified outcome, same shape/spirit as bigbuy_stock_sync.php's
 * bb_check_product_stock():
 *   ['outcome' => 'ok',         'videoId' => ?string]
 *   ['outcome' => 'not_found']              (HTTP 404 -> skip, no write)
 *   ['outcome' => 'rate_limited']           (HTTP 429 -> never write)
 *   ['outcome' => 'error', 'status' => int] (anything else -> never write)
 */
function bb_check_product_video(BigBuy $bb, int $cjPid): array
{
    $res    = bb_get_single_product_with_retry($bb, $cjPid);
    $status = $res['status'] ?? 0;

    if ($res['success'] ?? false) {
        return ['outcome' => 'ok', 'videoId' => bb_extract_video_id($res)];
    }
    if ($status === 404) {
        return ['outcome' => 'not_found'];
    }
    if ($status === 429) {
        return ['outcome' => 'rate_limited'];
    }
    return ['outcome' => 'error', 'status' => $status];
}

/**
 * PATCH one product row. Same curl pattern as bigbuy_stock_sync.php's
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
 *  Batch/pagination + mode — identical shape to bigbuy_stock_sync.php
 * ------------------------------------------------------------------ */
$noRangeSpecified = !isset($_GET['start']) && !isset($_GET['count']);
$processAll       = $isCli || $noRangeSpecified;
$apply            = $isCli || ($_GET['apply'] ?? '') === '1';

// &limit=1000 — same PostgREST default-cap fix as bigbuy_stock_sync.php.
$products = sb_get($cfg, 'products?cj_pid=not.is.null&select=id,name,cj_pid,video_id&order=id.asc&limit=1000');
if (empty($products)) {
    exit("No products with cj_pid found — check Supabase credentials/connection.\n");
}
// DIAGNOSTIC (temporary): written immediately, not deferred to end-of-run,
// so progress survives even if the process gets killed mid-run.
@file_put_contents(__DIR__ . '/backfill_videos.log', "\n=== Run started $runStartedAt (" . ($isCli ? 'CLI/cron' : 'browser') . ") — fetched " . count($products) . " products total ===\n", FILE_APPEND | LOCK_EX);

// Optional single-product filter — position-independent, unlike &start=/
// &count= (which shift if the product list ever changes). Same block as
// bigbuy_stock_sync.php.
if (isset($_GET['cj_pid']) && trim((string)$_GET['cj_pid']) !== '') {
    $filterCjPid = trim((string)$_GET['cj_pid']);
    $products    = array_values(array_filter(
        $products,
        static fn($p) => (string)($p['cj_pid'] ?? '') === $filterCjPid
    ));
    if (empty($products)) {
        exit("No product found with cj_pid=$filterCjPid.\n");
    }
}

if ($processAll) {
    $start = 0;
    $batch = $products;
} else {
    $start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
    $count = isset($_GET['count']) ? (int)$_GET['count'] : 20;
    $batch = array_slice($products, $start, $count);
}

$logLines = []; // summary-only lines, appended to backfill_videos.log at the end
function log_line(string $line, array &$logLines): void
{
    echo $line . "\n";
    $logLines[] = $line;
}

echo "==========================================\n";
echo " BigBuy video_id backfill (single-product endpoint, " . VIDEO_CALL_DELAY_SECONDS . "s spacing) — " . ($apply ? "APPLY to Supabase" : "PREVIEW ONLY (no DB writes)") . ($isCli ? " [CLI]" : " [browser]") . "\n";
echo " Batch: products $start.." . ($start + count($batch)) . " of " . count($products) . ($processAll ? " (ALL — no &start/&count given)" : '') . "\n";
echo " Estimated time (main pass): ~" . round(count($batch) * VIDEO_CALL_DELAY_SECONDS / 60, 1) . " min (+ time for any end-of-run retries)\n";
echo "==========================================\n\n";

$succeeded  = []; // id => ['name'=>..., 'videoId'=>?string]
$pendingIds = []; // ids that are STILL rate-limited after the retry queue pass
$failedIds  = []; // ids that hit a non-429/non-404 error (not auto-retried)
$notFoundIds = []; // ids that hit HTTP 404 (skipped, not written)
$retryQueue = []; // ['id'=>..., 'name'=>..., 'cjPid'=>...] — 429s from the main pass

function process_one(array $p, BigBuy $bb, array $cfg, bool $apply, array &$succeeded, array &$failedIds, array &$notFoundIds): string
{
    $id    = (int)($p['id'] ?? 0);
    $name  = (string)($p['name'] ?? '');
    $cjPid = trim((string)($p['cj_pid'] ?? ''));

    $result = bb_check_product_video($bb, (int)$cjPid);

    if ($result['outcome'] === 'ok') {
        $videoId = $result['videoId'];
        $label   = $videoId === null ? 'NULL' : $videoId;

        if ($apply) {
            $patchResult = supabase_patch_product($cfg, $id, ['video_id' => $videoId]);
            if ($patchResult['success']) {
                echo "OK    id=$id \"$name\" (cj_pid=$cjPid) — video_id=$label (saved)\n";
                $succeeded[$id] = ['name' => $name, 'videoId' => $videoId];
            } else {
                echo "FAIL  id=$id \"$name\" (Supabase PATCH HTTP {$patchResult['status']}" . ($patchResult['error'] ? ": {$patchResult['error']}" : '') . ")\n";
                $failedIds[] = $id;
            }
        } else {
            echo "WOULD SET video_id=$label for id=$id \"$name\" (cj_pid=$cjPid)\n";
            $succeeded[$id] = ['name' => $name, 'videoId' => $videoId];
        }
        return 'done';
    }

    if ($result['outcome'] === 'rate_limited') {
        echo "QUEUE  id=$id \"$name\" (cj_pid=$cjPid) — still 429 after inner retries, deferred to end-of-run retry pass\n";
        return 'rate_limited';
    }

    if ($result['outcome'] === 'not_found') {
        echo "NOT FOUND  id=$id \"$name\" (cj_pid=$cjPid) — HTTP 404, skipped (no write)\n";
        $notFoundIds[] = $id;
        return 'not_found';
    }

    // 'error' — some other failure, never write for this.
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

    $outcome = process_one($p, $bb, $cfg, $apply, $succeeded, $failedIds, $notFoundIds);
    // DIAGNOSTIC (temporary): immediate per-product write, see note above.
    @file_put_contents(__DIR__ . '/backfill_videos.log', date('c') . " MAIN  id=$id \"" . ($p['name'] ?? '') . "\" outcome=$outcome\n", FILE_APPEND | LOCK_EX);
    if ($outcome === 'rate_limited') {
        $retryQueue[] = $p;
    }

    @ob_flush(); @flush();
    usleep((int)(VIDEO_CALL_DELAY_SECONDS * 1000000));
}

if (!empty($retryQueue)) {
    echo "\n---- END-OF-RUN RETRY PASS (" . count($retryQueue) . " product(s) still pending from the main pass) ----\n";
    foreach ($retryQueue as $p) {
        $id = (int)($p['id'] ?? 0);
        $outcome = process_one($p, $bb, $cfg, $apply, $succeeded, $failedIds, $notFoundIds);
        // DIAGNOSTIC (temporary): immediate per-product write, see note above.
        @file_put_contents(__DIR__ . '/backfill_videos.log', date('c') . " RETRY id=$id \"" . ($p['name'] ?? '') . "\" outcome=$outcome\n", FILE_APPEND | LOCK_EX);
        if ($outcome === 'rate_limited') {
            $pendingIds[] = $id;
        }
        @ob_flush(); @flush();
        usleep((int)(VIDEO_CALL_DELAY_SECONDS * 1000000));
    }
}

$withVideo    = count(array_filter($succeeded, fn($r) => $r['videoId'] !== null));
$withoutVideo = count($succeeded) - $withVideo;

echo "\n";
log_line("==========================================", $logLines);
log_line("Run finished " . date('c') . " (" . ($isCli ? 'CLI/cron' : 'browser') . ", " . ($apply ? 'APPLY' : 'PREVIEW') . ", " . ($processAll ? 'all products' : "start=$start") . ")", $logLines);
log_line(($apply ? "Updated & saved: " : "Would update: ") . count($succeeded), $logLines);
log_line("With video:    $withVideo", $logLines);
log_line("Without video: $withoutVideo", $logLines);
log_line("Not found (HTTP 404, skipped): " . count($notFoundIds) . (empty($notFoundIds) ? '' : ' (ids: ' . implode(', ', $notFoundIds) . ')'), $logLines);
log_line("Still pending (429, untouched in DB, re-run the script for these): " . count($pendingIds) . (empty($pendingIds) ? '' : ' (ids: ' . implode(', ', $pendingIds) . ')'), $logLines);
log_line("Failed (other errors, not auto-retried): " . count($failedIds) . (empty($failedIds) ? '' : ' (ids: ' . implode(', ', $failedIds) . ')'), $logLines);
if (!$apply) {
    echo "\nPreview only — re-run with &apply=1 on the same &start/&count to save.\n";
}
echo "==========================================\n";

$logEntry = "\n=== Run started $runStartedAt ===\n" . implode("\n", $logLines) . "\n";
@file_put_contents(__DIR__ . '/backfill_videos.log', $logEntry, FILE_APPEND | LOCK_EX);
