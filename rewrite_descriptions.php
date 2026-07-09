<?php
declare(strict_types=1);

/**
 * rewrite_descriptions.php — Rewrites product descriptions into unique,
 * SEO-optimized Spanish copy via the Anthropic Messages API, replacing the
 * repeated generic BigBuy template text.
 * ---------------------------------------------------------------------------
 * HOW TO RUN (browser):
 *   PREVIEW (default, no DB writes):
 *     khurmistore.es/rewrite_descriptions.php?key=khurmi2026&start=0&count=10
 *   APPLY (writes to Supabase — run PREVIEW first and read description_preview.txt):
 *     khurmistore.es/rewrite_descriptions.php?key=khurmi2026&start=0&count=10&apply=1
 *
 * Run in small batches (default count=10) and increase &start= each time to
 * avoid server timeouts. Re-running the same batch overwrites its preview
 * output / re-applies (idempotent from the DB's point of view, but each run
 * costs API calls — don't re-run a batch you've already applied).
 *
 * BEFORE running with &apply=1, run this SQL yourself in Supabase:
 *   alter table public.products add column if not exists description_original text;
 *   update public.products set description_original = description where description_original is null;
 *
 * DELETE this file from the server after you're done using it — it's a
 * write-capable one-off migration script, not meant to stay live in public_html.
 */

if (php_sapi_name() !== 'cli') {
    if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
    header('Content-Type: text/plain; charset=utf-8');
}
set_time_limit(0);

$cfg = require __DIR__ . '/config.php';

if (empty($cfg['anthropic_api_key'])) {
    exit("ANTHROPIC_API_KEY is not set in .env — add it and try again.\n");
}

const MODEL = 'claude-sonnet-4-6';

/* ------------------------------------------------------------------ *
 *  Self-test mode (?test=1): ONE bare API call, no Supabase, no
 *  products. Prints the exact request payload plus the full raw
 *  response (status + body) so API/key/model problems are isolated
 *  from anything product-related.
 * ------------------------------------------------------------------ */
if (($_GET['test'] ?? '') === '1') {
    echo "==========================================\n";
    echo " Anthropic API self-test (no Supabase, no products)\n";
    echo " Model: " . MODEL . "\n";
    echo "==========================================\n\n";

    $payload = json_encode([
        'model'      => MODEL,
        'max_tokens' => 10,
        'messages'   => [
            ['role' => 'user', 'content' => 'Hola'],
        ],
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        exit("json_encode failed building the test request: " . json_last_error_msg() . "\n");
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . $cfg['anthropic_api_key'],
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);
    $body    = curl_exec($ch);
    $status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    echo "Request payload sent:\n$payload\n\n";
    echo "HTTP status: $status\n";
    if ($curlErr !== '') {
        echo "curl error: $curlErr\n";
    }
    echo "Raw response body:\n" . ($body === false ? '(curl_exec returned false — no body)' : $body) . "\n";
    exit;
}

require_once __DIR__ . '/supabase.php';

// Slug -> natural Spanish phrase used inside the prompt (not the raw slug).
const CATEGORY_LABELS = [
    'belleza'          => 'belleza',
    'relojes'          => 'relojes',
    'accesorios-movil' => 'accesorios para móvil',
    'electronica'      => 'electrónica',
    'auriculares'      => 'auriculares y audio',
];

const SYSTEM_PROMPT = <<<SYS
Eres un redactor de copywriting SEO para KhurmiStore, una tienda online española que vende electrónica, belleza, relojes y accesorios para móvil.

Tu tarea: reescribir la descripción de UN producto en español, siguiendo estas reglas estrictas:

1. Entre 120 y 200 palabras.
2. Tono natural y persuasivo, propio de una ficha de producto de e-commerce en España.
3. Empieza con una apertura ÚNICA y específica de ESTE producto. Nunca uses una plantilla genérica ni una apertura que podría servir para cualquier otro producto.
4. Incluye de forma natural (sin forzar ni repetir en exceso) palabras clave SEO: el nombre del producto, su categoría, y frases como "comprar online", "España", "envío rápido", "mejor precio".
5. Menciona entre 2 y 4 beneficios o características CONCRETAS tomadas de la descripción ORIGINAL proporcionada. No inventes especificaciones, certificaciones ni afirmaciones médicas o de salud que no estén en el original.
6. Usa etiquetas <b> ÚNICAMENTE para resaltar el nombre del producto una vez y 1-2 beneficios clave. No uses ninguna otra etiqueta HTML (nada de <p>, <ul>, <br>, etc.).
7. Termina con una llamada a la acción breve que mencione KhurmiStore y el envío a España.
8. Responde ÚNICAMENTE con la descripción reescrita en HTML. Sin preámbulos, sin explicaciones, sin bloques de código markdown, sin comillas envolventes.
SYS;

/**
 * Build the per-product user turn: name/category/price context plus the
 * original description (source of real benefits — the model is told to
 * ignore any generic template opening in it, not copy it).
 */
function build_user_prompt(string $name, string $categoryLabel, float $price, string $originalDescription): string
{
    $priceEs = number_format($price, 2, ',', '.') . ' €';
    $original = trim(strip_tags($originalDescription));
    $original = mb_substr($original, 0, 2000); // keep prompt/token size bounded

    return "Producto: {$name}\n"
        . "Categoría: {$categoryLabel}\n"
        . "Precio: {$priceEs}\n\n"
        . "Descripción ORIGINAL (fuente de los beneficios reales; puede empezar con texto de plantilla genérico que debes ignorar como apertura):\n"
        . "{$original}\n\n"
        . "Reescribe la descripción de este producto siguiendo todas las reglas.";
}

/**
 * Extract a human-readable error message from an Anthropic API error
 * response, e.g. {"type":"error","error":{"type":"invalid_request_error",
 * "message":"..."}}. Falls back to the raw body (truncated) or the curl
 * error when the body isn't that expected shape, so a failure is never
 * silently collapsed down to a bare "HTTP 400" with no explanation.
 */
function anthropic_extract_error($body, string $curlErr, int $status): string
{
    if ($body !== false && $body !== '') {
        $data = json_decode($body, true);
        if (is_array($data) && isset($data['error']) && is_array($data['error'])) {
            $type = $data['error']['type'] ?? 'unknown_error';
            $msg  = $data['error']['message'] ?? '';
            $combined = trim("$type: $msg");
            if ($combined !== ':' && $combined !== '') {
                return $combined;
            }
        }
        return mb_substr($body, 0, 500); // unexpected shape — show the raw body
    }
    return $curlErr !== '' ? "curl error: $curlErr" : "HTTP $status (no response body)";
}

/**
 * Call the Anthropic Messages API for one product. Retries up to $maxRetries
 * times on HTTP 429/5xx (and on curl-level failure) with linear backoff.
 * Never throws — always returns a result array.
 */
function anthropic_rewrite(string $apiKey, string $userPrompt, int $maxRetries = 3): array
{
    // system is a TOP-LEVEL string field per the Messages API spec — NOT a
    // message with role "system" in messages[] (that causes HTTP 400).
    $payload = json_encode([
        'model'      => MODEL,
        'max_tokens' => 800,
        'system'     => SYSTEM_PROMPT,
        'messages'   => [
            ['role' => 'user', 'content' => $userPrompt],
        ],
    ], JSON_UNESCAPED_UNICODE);

    // Spanish text has accents (á, é, í, ó, ú, ñ) — if the source strings
    // aren't valid UTF-8, json_encode() silently returns false and the
    // request body would otherwise go out empty/malformed. Catch it here
    // instead of sending a broken request that Anthropic then 400s on.
    if ($payload === false) {
        $jsonErr = 'json_encode failed: ' . json_last_error_msg();
        echo "   $jsonErr\n";
        return ['success' => false, 'status' => 0, 'error' => $jsonErr];
    }

    $attempt = 0;
    $status  = 0;
    $err     = '';

    while ($attempt < $maxRetries) {
        $attempt++;

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
        ]);
        $body    = curl_exec($ch);
        $status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($body !== false && $status >= 200 && $status < 300) {
            $data = json_decode($body, true);
            $text = trim((string)($data['content'][0]['text'] ?? ''));
            // Strip stray markdown code fences in case the model adds them anyway.
            $text = preg_replace('/^```(?:html)?\s*|\s*```$/i', '', $text);
            $text = trim((string)$text);
            if ($text !== '') {
                return ['success' => true, 'text' => $text];
            }
            $err = 'empty response text (raw body: ' . mb_substr($body, 0, 300) . ')';
        } else {
            // Capture and surface Anthropic's actual JSON error body instead
            // of just the bare HTTP status.
            $err = anthropic_extract_error($body, $curlErr, $status);
        }

        $retryable = $body === false || $status === 429 || $status >= 500;
        if ($retryable && $attempt < $maxRetries) {
            $wait = 5 * $attempt; // 5s, 10s, ...
            echo "   HTTP $status ($err), retrying in {$wait}s (attempt $attempt/$maxRetries)...\n";
            @ob_flush(); @flush();
            sleep($wait);
            continue;
        }

        break;
    }

    return ['success' => false, 'status' => $status, 'error' => $err ?: "HTTP $status"];
}

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

$products = sb_get($cfg, 'products?select=id,name,category,price,description,description_original&order=id.asc');
if (empty($products)) {
    exit("No products fetched — check Supabase credentials/connection.\n");
}
$batch = array_slice($products, $start, $count);

echo "==========================================\n";
echo " Description rewrite — " . ($apply ? "APPLY to Supabase" : "PREVIEW ONLY (no DB writes)") . "\n";
echo " Model: " . MODEL . "\n";
echo " Batch: products $start.." . ($start + count($batch)) . " of " . count($products) . "\n";
echo "==========================================\n\n";

$rewritten  = 0;
$failed     = 0;
$skipped    = 0;
$failedIds  = [];
$previewFh  = null;

if (!$apply) {
    $previewFh = fopen(__DIR__ . '/description_preview.txt', 'a');
    if ($previewFh === false) {
        exit("Could not open description_preview.txt for writing.\n");
    }
}

foreach ($batch as $p) {
    $id = (int)($p['id'] ?? 0);
    if (!$id) {
        continue;
    }

    $name        = (string)($p['name'] ?? '');
    $category    = (string)($p['category'] ?? '');
    $price       = (float)($p['price'] ?? 0);
    $description = (string)($p['description'] ?? '');

    if (trim(strip_tags($description)) === '') {
        $skipped++;
        echo "SKIP  id=$id \"$name\" (empty original description, nothing to rewrite)\n";
        continue;
    }

    $categoryLabel = CATEGORY_LABELS[$category] ?? $category;
    $userPrompt    = build_user_prompt($name, $categoryLabel, $price, $description);

    $result = anthropic_rewrite((string)$cfg['anthropic_api_key'], $userPrompt);

    $originalSnippet = mb_substr(trim(strip_tags($description)), 0, 150);

    if (!$result['success']) {
        $failed++;
        $failedIds[] = $id;
        echo "FAIL  id=$id \"$name\" (HTTP {$result['status']}" . ($result['error'] ? ": {$result['error']}" : '') . ") — original description kept\n";
        if ($previewFh) {
            fwrite($previewFh, "$id | $name | ORIGINAL: $originalSnippet | FAILED (HTTP {$result['status']}: {$result['error']})\n");
        }
        sleep(1);
        continue;
    }

    $newDescription = $result['text'];

    if ($apply) {
        $fields = ['description' => $newDescription];

        // Backfill safety net: description_original should already be set by
        // the SQL you ran, but if a row somehow has it empty, capture the
        // pre-rewrite description in this same PATCH so it's never lost.
        $descOriginal = $p['description_original'] ?? null;
        if ($descOriginal === null || trim((string)$descOriginal) === '') {
            $fields['description_original'] = $description;
        }

        $patchResult = supabase_patch_product($cfg, $id, $fields);
        if ($patchResult['success']) {
            $rewritten++;
            echo "OK    id=$id \"$name\" — rewritten and saved\n";
        } else {
            $failed++;
            $failedIds[] = $id;
            echo "FAIL  id=$id \"$name\" (Supabase PATCH HTTP {$patchResult['status']}" . ($patchResult['error'] ? ": {$patchResult['error']}" : '') . ") — original description kept\n";
        }
    } else {
        $rewritten++;
        echo "OK    id=$id \"$name\" — preview generated\n";
        fwrite($previewFh, "$id | $name | ORIGINAL: $originalSnippet | NEW: " . str_replace(["\r", "\n"], ' ', $newDescription) . "\n");
    }

    sleep(1); // rate-limit pacing between API calls
}

if ($previewFh) {
    fclose($previewFh);
}

echo "\n==========================================\n";
echo ($apply ? "Rewritten & saved: " : "Rewritten (preview): ") . "$rewritten\n";
echo "Skipped:   $skipped (empty original description)\n";
echo "Failed:    " . count($failedIds) . (empty($failedIds) ? '' : ' (ids: ' . implode(', ', $failedIds) . ')') . "\n";
if (!$apply) {
    echo "\nPreview written to description_preview.txt — review it, then re-run with &apply=1 on the same &start/&count to save.\n";
}
echo "==========================================\n";
