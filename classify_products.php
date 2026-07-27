<?php
declare(strict_types=1);

/**
 * classify_products.php — Bulk-tags existing products with subcategoria /
 * sub_subcategoria by asking the Anthropic Messages API to classify each
 * one, using the approved Spanish classifier prompt below verbatim.
 * ---------------------------------------------------------------------------
 * HOW TO RUN (browser):
 *   khurmistore.es/classify_products.php?key=khurmi2026&limit=10
 *
 * Only relojes/belleza products are fetched — electronica, auriculares, and
 * accesorios-movil have no subcategories in categories_config.php, so there
 * is nothing for them to be tagged with.
 *
 * PAGING — read before using &offset=: this script queries "products still
 * missing subcategoria" every time it runs. Once a batch is tagged, those
 * rows stop matching that filter and drop out of the NEXT run's query —
 * so leaving &offset= at its default of 0 (i.e. just re-running the same
 * URL) always picks up the next untouched products automatically. If you
 * pass an explicit &offset=N yourself across separate runs, be aware that
 * N needs to account for rows that already dropped out from earlier
 * tagging, or you will silently skip untouched products. The progress
 * output below tells you the safe next command every time.
 *
 * BEFORE running, these columns must exist (run once in Supabase if not
 * already done for this project):
 *   ALTER TABLE products ADD COLUMN IF NOT EXISTS subcategoria text;
 *   ALTER TABLE products ADD COLUMN IF NOT EXISTS sub_subcategoria text;
 *
 * Side effect for cosmetics: when the model flags a product "cosmetic"
 * (cream/serum/lotion/sunscreen/topical makeup — anything that would need
 * EU CPNP registration), this script also sets that row's approval_status
 * to 'needs_review'. Since categoria.php/producto.php only ever show
 * approval_status='approved' products, this immediately UNPUBLISHES the
 * product from the live site until you manually review/re-approve or
 * remove it — that's the intended effect, not a side bug.
 *
 * DELETE or protect this file after use, same as the other one-off
 * migration/tagging scripts in this project.
 */

if (php_sapi_name() !== 'cli') {
    if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
    header('Content-Type: text/plain; charset=utf-8');
}
set_time_limit(0);

require_once __DIR__ . '/supabase.php';
$cfg          = require __DIR__ . '/config.php';
$categoryTree = require __DIR__ . '/categories_config.php'; // used only by validate_classification() below, not by the prompt

if (empty($cfg['anthropic_api_key'])) {
    exit("ANTHROPIC_API_KEY is not set in .env — add it and try again.\n");
}

const MODEL = 'claude-haiku-4-5-20251001';

/**
 * Approved classifier system prompt — used EXACTLY as given, verbatim.
 * If categories_config.php's tree ever changes, this constant must be
 * updated by hand to match (it is intentionally NOT generated from the
 * config file this time, per instruction).
 */
const SYSTEM_PROMPT = <<<SYS
Eres un clasificador de productos para KhurmiStore, una tienda española de tecnología y belleza.

Recibirás un producto con: nombre, descripción y categoría principal (categoria).

Tu tarea: asignar la SUBCATEGORÍA (subcategoria) y, si aplica, la SUB-SUBCATEGORÍA (sub_subcategoria) correctas, usando ÚNICAMENTE los slugs de la siguiente estructura. No inventes slugs nuevos. No modifiques ni "corrijas" ningún slug — cópialos exactamente como aparecen aquí, carácter por carácter.

ESTRUCTURA VÁLIDA (categoria → subcategoria → sub_subcategoria):

relojes:
  - analogicos
  - digitales
  (relojes no tiene sub_subcategoria — ese campo siempre queda vacío para esta categoría)

belleza:
  - unas-y-herramientas
  - alimentacion-y-salud → cuidado-de-la-salud
  - cabello-y-accesorios → diademas-y-cintas, horquillas, cabello-humano
  - cabello-sintetico → cabello-para-cosplay
  - cuidado-de-la-piel → maquinillas-de-afeitar, mascarillas-faciales, proteccion-solar, aceites-esenciales, cuidado-corporal, cuidado-facial
  - mechones-de-cabello → paquete-pre-coloreado, tejido-de-cabello, estilismo-de-cabello, mechones-de-salon, mechon-pre-coloreado
  - maquillaje → lapiz-de-cejas, set-de-maquillaje, sombra-de-ojos, brochas-de-maquillaje, pestanas-postizas, pintalabios
  - pelucas-y-extensiones → peluca-cabello-humano, postizo-sintetico, peluca-encaje-sintetica, peluca-encaje-cabello-humano, trenzas, pelucas-sinteticas
  - herramientas-de-belleza → espejo, planchas-de-pelo, limpiador-facial-electrico, herramientas-cuidado-facial, rizador-de-pelo, vaporizador-facial

electronica: (sin subcategorías — deja subcategoria y sub_subcategoria vacíos)
auriculares: (sin subcategorías — deja subcategoria y sub_subcategoria vacíos)
accesorios-movil: (sin subcategorías — deja subcategoria y sub_subcategoria vacíos)

REGLAS DE ASIGNACIÓN:
- Solo los productos de categoria="belleza" reciben subcategoria/sub_subcategoria de la lista de belleza.
- Si categoria="relojes": asigna subcategoria="analogicos" (reloj de agujas/manecillas) o subcategoria="digitales" (reloj digital o smartwatch). sub_subcategoria siempre "".
- Si categoria es "electronica", "auriculares" o "accesorios-movil": subcategoria="" y sub_subcategoria="" siempre, sin excepción.
- Si el producto de belleza encaja en una subcategoria pero ninguna de sus sub_subcategoria describe bien el producto, deja sub_subcategoria como "".
- Si el producto no encaja claramente en ninguna subcategoria de la lista, deja subcategoria="" y sub_subcategoria="".
- Usa el nombre y la descripción para decidir. Elige siempre la opción MÁS específica y precisa disponible en la estructura.

REGLA DEL CAMPO "flag" (cosmético):
- Si el producto es un COSMÉTICO — crema, sérum, loción, protector solar, o maquillaje tópico (pintalabios, sombra de ojos, base de maquillaje, y similares), o cualquier producto de aplicación tópica sobre piel/labios/rostro que legalmente requeriría registro CPNP en la UE — clasifícalo normalmente en subcategoria/sub_subcategoria como corresponda, y ADEMÁS devuelve "flag": "cosmetic".
- Si el producto NO es un cosmético tópico (por ejemplo: herramientas, dispositivos eléctricos, accesorios, pelucas, extensiones, relojes, auriculares, fundas, etc.), devuelve "flag": "".

FORMATO DE SALIDA (obligatorio):
- Devuelve SOLO un objeto JSON, sin texto adicional, sin explicaciones, sin markdown, sin backticks.
- Forma exacta: {"subcategoria": "", "sub_subcategoria": "", "flag": ""}
- Las claves deben aparecer siempre en ese orden y no debe haber ninguna clave adicional.
SYS;

function build_user_prompt(string $name, string $category, string $description): string
{
    $desc = trim(strip_tags($description));
    $desc = mb_substr($desc, 0, 1500); // keep prompt/token size bounded, same convention as rewrite_descriptions.php
    return "Producto: {$name}\n"
        . "Categoria: {$category}\n"
        . "Descripción: {$desc}\n\n"
        . "Clasifica este producto siguiendo todas las reglas y devuelve solo el JSON.";
}

/**
 * Guarantees nothing invalid ever reaches the DB, regardless of what the
 * model returns — re-checks the model's subcategoria/sub_subcategoria
 * against the REAL tree in categories_config.php and silently downgrades
 * anything that doesn't match to "" (with a warning string the caller can
 * print), rather than trusting the model's output as-is. This is a safety
 * net on the DATA WRITTEN, independent of the prompt text above.
 *
 * @return array{subcategoria: string, sub_subcategoria: string, warning: string}
 */
function validate_classification(array $tree, string $category, string $subcategoria, string $sub_subcategoria): array
{
    $catNode = null;
    foreach ($tree as $node) {
        if ($node['slug'] === $category) { $catNode = $node; break; }
    }
    if (!$catNode || empty($catNode['children'])) {
        $warning = $subcategoria !== ''
            ? "model returned subcategoria=\"$subcategoria\" for category=\"$category\" (no valid subcategories) — discarded"
            : '';
        return ['subcategoria' => '', 'sub_subcategoria' => '', 'warning' => $warning];
    }

    $subNode = null;
    foreach ($catNode['children'] as $sub) {
        if ($sub['slug'] === $subcategoria) { $subNode = $sub; break; }
    }
    if (!$subNode) {
        $warning = $subcategoria !== ''
            ? "model returned invalid subcategoria=\"$subcategoria\" for category=\"$category\" — discarded"
            : '';
        return ['subcategoria' => '', 'sub_subcategoria' => '', 'warning' => $warning];
    }

    if ($sub_subcategoria === '' || empty($subNode['children'])) {
        $warning = ($sub_subcategoria !== '' && empty($subNode['children']))
            ? "model returned sub_subcategoria=\"$sub_subcategoria\" but subcategoria=\"$subcategoria\" has no valid children — discarded"
            : '';
        return ['subcategoria' => $subNode['slug'], 'sub_subcategoria' => '', 'warning' => $warning];
    }

    foreach ($subNode['children'] as $sub2) {
        if ($sub2['slug'] === $sub_subcategoria) {
            return ['subcategoria' => $subNode['slug'], 'sub_subcategoria' => $sub2['slug'], 'warning' => ''];
        }
    }
    return [
        'subcategoria'     => $subNode['slug'],
        'sub_subcategoria' => '',
        'warning'          => "model returned invalid sub_subcategoria=\"$sub_subcategoria\" for subcategoria=\"$subcategoria\" — discarded",
    ];
}

/** Same shape/purpose as rewrite_descriptions.php's anthropic_extract_error(). */
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
        return mb_substr($body, 0, 500);
    }
    return $curlErr !== '' ? "curl error: $curlErr" : "HTTP $status (no response body)";
}

/**
 * Calls the Anthropic Messages API for one product (system = SYSTEM_PROMPT
 * above, verbatim) and parses its JSON reply. Retries on 429/5xx/curl
 * failure with linear backoff, same pattern as rewrite_descriptions.php's
 * anthropic_rewrite(). Never throws.
 *
 * @return array{success: bool, subcategoria?: string, sub_subcategoria?: string, flag?: string, status?: int, error?: string}
 */
function anthropic_classify(string $apiKey, string $userPrompt, int $maxRetries = 3): array
{
    $payload = json_encode([
        'model'      => MODEL,
        'max_tokens' => 200,
        'system'     => SYSTEM_PROMPT,
        'messages'   => [
            ['role' => 'user', 'content' => $userPrompt],
        ],
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        return ['success' => false, 'status' => 0, 'error' => 'json_encode failed: ' . json_last_error_msg()];
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
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
            $text = trim((string)$text);

            $parsed = json_decode($text, true);
            if (is_array($parsed) && array_key_exists('subcategoria', $parsed)) {
                return [
                    'success'          => true,
                    'subcategoria'     => (string)($parsed['subcategoria'] ?? ''),
                    'sub_subcategoria' => (string)($parsed['sub_subcategoria'] ?? ''),
                    'flag'             => (string)($parsed['flag'] ?? ''),
                ];
            }
            $err = 'unparseable JSON from model: ' . mb_substr($text, 0, 300);
        } else {
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

/** Same PATCH pattern as rewrite_descriptions.php's supabase_patch_product(). */
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
 *  Batch via PostgREST limit/offset (see the "PAGING" note in the
 *  header comment above before relying on a nonzero &offset=).
 * ------------------------------------------------------------------ */
$limit  = isset($_GET['limit'])  ? max(1, (int)$_GET['limit'])  : 10;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

// Only relojes/belleza ever get a subcategoria in categories_config.php —
// electronica/auriculares/accesorios-movil have none, so they're excluded
// from this query entirely rather than burning an API call every run to
// re-confirm an answer that can never change.
$untaggedFilter = 'category=in.(relojes,belleza)&or=(subcategoria.is.null,subcategoria.eq.)';

// Lightweight count-only query (id column only) just for the progress
// readout — kept separate from the real batch fetch below so the batch
// query itself only ever pulls the columns it needs.
$totalUntagged = count(sb_get($cfg, "products?select=id&$untaggedFilter"));

$batch = sb_get($cfg, "products?select=id,name,description,category,subcategoria,sub_subcategoria&$untaggedFilter&order=id.asc&limit=$limit&offset=$offset");

if (empty($batch)) {
    exit("No untagged relojes/belleza products found at offset=$offset (or the Supabase connection failed). Total untagged: $totalUntagged.\n");
}

echo "==========================================\n";
echo " Product subcategory classifier (Anthropic)\n";
echo " Model: " . MODEL . "\n";
echo " Untagged relojes/belleza products total: $totalUntagged\n";
echo " This batch: limit=$limit offset=$offset (" . count($batch) . " products)\n";
echo "==========================================\n\n";

$ok = 0; $failed = 0; $flagged = 0; $failedIds = [];

foreach ($batch as $p) {
    $id          = (int)($p['id'] ?? 0);
    $name        = (string)($p['name'] ?? '');
    $category    = (string)($p['category'] ?? '');
    $description = (string)($p['description'] ?? '');
    if (!$id) {
        continue;
    }

    $userPrompt = build_user_prompt($name, $category, $description);
    $result     = anthropic_classify((string)$cfg['anthropic_api_key'], $userPrompt);

    if (!$result['success']) {
        $failed++;
        $failedIds[] = $id;
        echo "FAIL | id=$id | \"$name\" | (HTTP {$result['status']}" . ($result['error'] ? ": {$result['error']}" : '') . ")\n";
        sleep(1);
        continue;
    }

    $validated = validate_classification($categoryTree, $category, $result['subcategoria'], $result['sub_subcategoria']);
    if ($validated['warning'] !== '') {
        echo "   WARN id=$id: {$validated['warning']}\n";
    }

    $flag = ($result['flag'] === 'cosmetic') ? 'cosmetic' : '';

    $fields = [
        'subcategoria'     => $validated['subcategoria'],
        'sub_subcategoria' => $validated['sub_subcategoria'],
    ];
    if ($flag === 'cosmetic') {
        $fields['approval_status'] = 'needs_review';
    }

    $patch = supabase_patch_product($cfg, $id, $fields);
    if ($patch['success']) {
        $ok++;
        if ($flag === 'cosmetic') {
            $flagged++;
        }
        echo "OK | id=$id | \"" . mb_substr($name, 0, 45) . "\" | subcategoria=\"{$validated['subcategoria']}\" | sub_subcategoria=\"{$validated['sub_subcategoria']}\" | flag=\"$flag\"\n";
    } else {
        $failed++;
        $failedIds[] = $id;
        echo "FAIL | id=$id | \"$name\" | (Supabase PATCH HTTP {$patch['status']}" . ($patch['error'] ? ": {$patch['error']}" : '') . ")\n";
    }

    sleep(1); // rate-limit pacing between API calls, same convention as rewrite_descriptions.php
}

$remaining = $totalUntagged - count($batch);

echo "\n==========================================\n";
echo "Tagged:           $ok\n";
echo "Flagged cosmetic: $flagged (approval_status set to 'needs_review' -> unpublished from the live site until reviewed)\n";
echo "Failed:           " . count($failedIds) . (empty($failedIds) ? '' : ' (ids: ' . implode(', ', $failedIds) . ')') . "\n";
if ($remaining > 0) {
    echo "\nSafe next run (recommended — offset stays 0 since tagged rows drop out of this query):\n";
    echo "  classify_products.php?key=khurmi2026&limit=$limit\n";
    echo "\n(If you'd rather page explicitly, &offset=" . ($offset + count($batch)) . " would continue from here in THIS snapshot,\n";
    echo " but once these rows are tagged they stop matching the filter — so re-running with an incrementing\n";
    echo " offset across separate runs will skip untouched products. Omitting &offset= is always safe.)\n";
} else {
    echo "\nAll untagged relojes/belleza products processed for this run.\n";
    echo "Re-run later (same URL, no &offset=) to catch any newly-imported untagged products.\n";
}
echo "==========================================\n";
