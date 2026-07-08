<?php
declare(strict_types=1);

/**
 * sync_images_auto.php — Self-continuing multi-image backfill for belleza
 * products. Processes a SMALL batch per page load (so each request finishes
 * well under the server's timeout — no more 503s), then the page itself
 * auto-refreshes to the next batch. Each run re-queries "products still
 * needing images" fresh from Supabase, so there's no start/offset
 * bookkeeping — PATCHed products simply stop matching the next query.
 *
 * HOW TO RUN (browser): khurmistore.es/sync_images_auto.php?key=khurmi2026
 * Optional: &count=N to change the batch size (default 4).
 *
 * Open it once, leave the tab open, and it auto-refreshes itself every 5s
 * until every belleza product has a full (comma-separated) image gallery.
 *
 * DELETE this file from the server once the run completes.
 */

if (php_sapi_name() !== 'cli') {
    if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
}
set_time_limit(0); // guard only — batches stay small so each request finishes fast on its own

require_once __DIR__ . '/bigbuy.php';
require_once __DIR__ . '/supabase.php';
$cfg = require __DIR__ . '/config.php';
$bb  = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

$key   = (string)($_GET['key'] ?? '');
$count = isset($_GET['count']) ? max(1, (int)$_GET['count']) : 4;
$cycle = isset($_GET['cycle']) ? (int)$_GET['cycle'] : 0;

// Hard safety cap against an infinite auto-refresh loop if some product
// permanently fails (e.g. BigBuy has no images for it) and can never leave
// the "needs images" query no matter how many times we retry it.
const MAX_CYCLES = 300;

/**
 * PATCH one product's image_url in Supabase. Service key, same PATCH/curl
 * pattern already used elsewhere (order_lib.php, sync_products.php-style
 * upserts) — just targeting one column via an id filter.
 */
function supabase_patch_image(array $cfg, int $id, string $imageUrl): bool
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
        CURLOPT_POSTFIELDS => json_encode(['image_url' => $imageUrl], JSON_UNESCAPED_UNICODE),
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status >= 200 && $status < 300;
}

// Fetch every belleza product's id/cj_pid/image_url and decide "needs
// images" in PHP rather than via a compound PostgREST filter — a plain
// `not.ilike.*,*` filter would silently exclude rows with a NULL
// image_url, which is exactly the case we most need to catch. The belleza
// category is small enough that fetching it all is cheap either way.
$allBelleza = sb_get($cfg, 'products?category=eq.belleza&select=id,cj_pid,image_url&order=id.asc');

$needsImages = array_values(array_filter($allBelleza, function ($p) {
    $img = $p['image_url'] ?? '';
    return $img === null || $img === '' || strpos((string)$img, ',') === false;
}));

$totalBefore = count($needsImages);
$batch       = array_slice($needsImages, 0, $count);

ob_start(); // buffer the per-product log so it can be embedded in the HTML page below

$processed = 0;
$failed    = 0;

foreach ($batch as $p) {
    $id    = (int)($p['id'] ?? 0);
    $cjPid = (int)($p['cj_pid'] ?? 0);

    if (!$id || !$cjPid) {
        echo "-> id=$id SKIP (no cj_pid on record)\n";
        continue;
    }

    echo "-> id=$id (BigBuy #$cjPid) ... ";

    // Same 429 retry logic already used in sync_products.php.
    $attempts = 0;
    do {
        $imgRes   = $bb->getProductImages($cjPid);
        $httpCode = $imgRes['status'] ?? null;
        if ($httpCode == 429) {
            $attempts++;
            echo "\n   429 rate-limited, waiting 20s (retry $attempts/5)...\n";
            @ob_flush(); @flush();
            sleep(20);
        }
    } while ($httpCode == 429 && $attempts < 5);

    // Same multi-image collection logic as sync_products.php (up to 7
    // images, preferring the largest URL variant per image).
    $mainImage = null;
    if ($imgRes['success'] ?? false) {
        $imgData = $imgRes['data'];
        $imgRec  = (is_array($imgData) && isset($imgData[0]) && is_array($imgData[0])) ? $imgData[0] : $imgData;
        $imgList = $imgRec['images'] ?? $imgRec;
        if (is_array($imgList)) {
            $urls = [];
            foreach ($imgList as $img) {
                $url = is_array($img)
                    ? ($img['urlMkt'] ?? $img['largeUrl'] ?? $img['url'] ?? $img['image'] ?? null)
                    : $img;
                if ($url) {
                    $urls[] = $url;
                    if (count($urls) >= 7) {
                        break;
                    }
                }
            }
            if (!empty($urls)) {
                $mainImage = implode(',', $urls);
            }
        }
    }

    if ($mainImage === null) {
        echo "FAIL (no images returned, HTTP " . ($imgRes['status'] ?? '?') . ")\n";
        $failed++;
        sleep(2);
        continue;
    }

    if (supabase_patch_image($cfg, $id, $mainImage)) {
        $imgCount = substr_count($mainImage, ',') + 1;
        echo "OK ($imgCount images)\n";
        $processed++;
    } else {
        echo "FAIL (Supabase PATCH failed)\n";
        $failed++;
    }

    sleep(2); // same pacing as sync_products.php
}

$logOutput = ob_get_clean();

// Successfully-patched products leave the "needs images" set; failed ones
// remain and will be retried on the next auto-refresh cycle.
$stillRemaining = max(0, $totalBefore - $processed);
$nextCycle      = $cycle + 1;
$hitCycleCap    = $stillRemaining > 0 && $nextCycle >= MAX_CYCLES;
$keepGoing      = $stillRemaining > 0 && !$hitCycleCap;

$refreshUrl = 'sync_images_auto.php?key=' . urlencode($key) . '&count=' . (int)$count . '&cycle=' . $nextCycle;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sync de imágenes — Belleza</title>
<?php if ($keepGoing): ?>
<meta http-equiv="refresh" content="5;url=<?= htmlspecialchars($refreshUrl) ?>">
<?php endif; ?>
<style>
body{background:#0a0e27;color:#fff;font-family:Arial,sans-serif;padding:40px;line-height:1.6}
h1{color:#ff6b35}
pre{background:#0f1430;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:20px;white-space:pre-wrap;font-size:14px}
.status{font-size:18px;font-weight:600;margin:20px 0}
.done{color:#4ade80}
.pending{color:#ffd700}
.warn{color:#ff6b35}
</style>
</head>
<body>
<h1>🖼️ Sync de imágenes — Belleza</h1>
<pre><?= htmlspecialchars($logOutput !== '' ? $logOutput : "(lote vacío)\n") ?></pre>
<p class="status <?= $keepGoing ? 'pending' : ($hitCycleCap ? 'warn' : 'done') ?>">
<?php if ($keepGoing): ?>
    Procesados <?= $processed ?> en este lote (<?= $failed ?> fallos). Aún quedan <?= $stillRemaining ?> productos sin imágenes completas.<br>
    Recargando automáticamente en 5 segundos... (ciclo <?= $nextCycle ?>)
<?php elseif ($hitCycleCap): ?>
    ⚠️ Detenido tras <?= MAX_CYCLES ?> ciclos con <?= $stillRemaining ?> productos aún pendientes.
    Probablemente alguno falla de forma persistente (revisa el log de arriba). Vuelve a abrir esta URL manualmente si quieres reintentar.
<?php else: ?>
    ✅ Listo — todos los productos de belleza ya tienen su galería completa de imágenes.
<?php endif; ?>
</p>
<?php if ($keepGoing): ?>
<script>
setTimeout(function () {
    window.location.href = <?= json_encode($refreshUrl) ?>;
}, 5000);
</script>
<?php endif; ?>
</body>
</html>
