<?php
declare(strict_types=1);

/**
 * bigbuy_stock_sync.php — Syncs real BigBuy stock into Supabase `products`
 * (stock, is_active, stock_synced_at).
 *
 * Uses the BATCH endpoint (BigBuy::getProductsStockByHandlingDays(), added
 * to bigbuy.php), NOT a per-product call — the old per-product endpoint
 * (/rest/catalog/productstock/{id}.json) doesn't exist in BigBuy's current
 * API and 400s on every call. The batch endpoint's own single-product
 * sibling (productstockbyhandlingdays/{id}) is rate-limited to 1 req/5sec,
 * which would make a 60+ product catalog painfully slow anyway — fetching
 * everything once via the batch endpoint avoids that entirely.
 * ---------------------------------------------------------------------------
 * BEFORE running with &apply=1, run this SQL yourself in Supabase:
 *   alter table public.products add column if not exists is_active boolean default true;
 *   alter table public.products add column if not exists stock_synced_at timestamptz;
 *
 * HOW TO RUN:
 *   Browser (preview, no DB writes): khurmistore.es/bigbuy_stock_sync.php?key=khurmi2026&start=0&count=3
 *   Browser (apply):                 khurmistore.es/bigbuy_stock_sync.php?key=khurmi2026&start=0&count=20&apply=1
 *   CLI (cron):                      php bigbuy_stock_sync.php apply=1   (no &key= needed — CLI bypasses the guard)
 *
 * NOTE: every run fetches BigBuy's FULL stock list (paginated) regardless
 * of &count= — there's no per-product call to make anymore, so testing
 * with &count=3 still costs the same BigBuy API calls as a full run; only
 * the number of Supabase rows written differs.
 *
 * Preview-then-apply stays the default (no explicit request for it this
 * time either, but the ad is live and this writes is_active — kept as a
 * safety default same as before). Pass &apply=1 once the preview looks right.
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
 * Fetches BigBuy's ENTIRE stock list via the batch endpoint, paginating
 * with &page= until a page returns fewer than $pageSize items. Returns a
 * map of [bigbuy_id (string) => summed quantity across all stocks[]].
 * Stops (with whatever it has so far) if a page request fails outright.
 */
function bb_fetch_full_stock_map(BigBuy $bb, int $pageSize = 1000): array
{
    $map  = [];
    $page = 1;

    do {
        echo "  fetching stock page $page (pageSize=$pageSize)...\n";
        @ob_flush(); @flush();

        $res = $bb->getProductsStockByHandlingDays($page, $pageSize);
        if (!($res['success'] ?? false)) {
            echo "  FAIL fetching page $page (HTTP " . ($res['status'] ?? '?') . ") — stopping pagination, using what we have so far.\n";
            break;
        }

        $items = $res['data'];
        if (!is_array($items)) {
            echo "  Unexpected response shape on page $page — stopping pagination.\n";
            break;
        }
        // Defensive: a single-object response (not wrapped in a list) shouldn't
        // happen on this endpoint, but don't silently drop it if it does.
        if (isset($items['id'])) {
            $items = [$items];
        }

        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }
            $bbId = (string)$item['id'];
            $qty  = 0;
            if (isset($item['stocks']) && is_array($item['stocks'])) {
                foreach ($item['stocks'] as $s) {
                    $qty += (int)($s['quantity'] ?? 0);
                }
            }
            $map[$bbId] = $qty;
        }

        echo "  page $page: " . count($items) . " products\n";

        $hasMore = count($items) >= $pageSize;
        $page++;
        if ($hasMore) {
            sleep(1); // pace between page fetches — no confirmed rate limit for this endpoint, staying conservative
        }
    } while ($hasMore);

    return $map;
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
 *  Mode + batch (DB-row batching only — the BigBuy fetch itself always
 *  pulls everything, see note above)
 * ------------------------------------------------------------------ */
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$count = isset($_GET['count']) ? (int)$_GET['count'] : 20;
$apply = ($_GET['apply'] ?? '') === '1';

echo "==========================================\n";
echo " BigBuy stock sync — " . ($apply ? "APPLY to Supabase" : "PREVIEW ONLY (no DB writes)") . "\n";
echo "==========================================\n\n";

echo "Fetching full BigBuy stock list (batch endpoint)...\n";
$stockMap = bb_fetch_full_stock_map($bb);
echo "Total BigBuy products in stock map: " . count($stockMap) . "\n\n";

$products = sb_get($cfg, 'products?cj_pid=not.is.null&select=id,name,cj_pid,stock,is_active&order=id.asc');
if (empty($products)) {
    exit("No products with cj_pid found — check Supabase credentials/connection.\n");
}
$batch = array_slice($products, $start, $count);

echo "DB batch: products $start.." . ($start + count($batch)) . " of " . count($products) . "\n";
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

    $foundInBigBuy = array_key_exists($cjPid, $stockMap);
    $qty           = $foundInBigBuy ? $stockMap[$cjPid] : 0;
    $isActive      = $qty > 0;

    if ($isActive) {
        $nowInStock++;
    } else {
        $nowOutOfStock++;
    }

    if (!$foundInBigBuy) {
        echo "WARN  id=$id \"$name\" (cj_pid=$cjPid) — not found in BigBuy's stock list, treating as OUT OF STOCK (stock=0)\n";
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
