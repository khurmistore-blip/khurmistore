<?php
declare(strict_types=1);

/**
 * bigbuy_pet_categories.php — Fetches the full BigBuy category tree and
 * prints only the categories related to pets (mascotas). Read-only —
 * makes no writes to BigBuy, Supabase, or anywhere else.
 * ---------------------------------------------------------------------------
 * HOW TO RUN (CLI): php bigbuy_pet_categories.php
 */

if (php_sapi_name() !== 'cli') {
    if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/bigbuy.php';
$cfg = require __DIR__ . '/config.php';

if (empty($cfg['bigbuy_api_key'])) {
    exit("BIGBUY_API_KEY is not set in .env — add it and try again.\n");
}

const PET_KEYWORDS = ['mascota', 'perro', 'gato', 'pet', 'animal', 'acuari', 'pez', 'ave', 'roedor'];

/**
 * BigBuy category names sometimes come back as a plain string, sometimes as
 * a per-language array (e.g. [{"languageCode":"es","name":"..."}]) — handle
 * both instead of assuming one shape.
 */
function extract_category_name($rawName): string
{
    if (is_string($rawName)) {
        return $rawName;
    }
    if (is_array($rawName)) {
        foreach ($rawName as $entry) {
            if (is_array($entry) && ($entry['languageCode'] ?? '') === 'es' && isset($entry['name'])) {
                return (string)$entry['name'];
            }
        }
        $first = reset($rawName);
        if (is_array($first) && isset($first['name'])) {
            return (string)$first['name'];
        }
        if (is_string($first)) {
            return $first;
        }
    }
    return '';
}

echo "==========================================\n";
echo " BigBuy -> Pet category finder\n";
echo "==========================================\n\n";

echo "Fetching category tree from BigBuy (GET /rest/catalog/categories.json?isoCode=es)...\n";

// getCategories() -> BigBuy::request(): builds the exact call this script
// needs (Bearer auth header, isoCode=es), reusing the project's existing
// helper instead of hand-rolling a new cURL call.
$bb     = new BigBuy((string)$cfg['bigbuy_api_key'], (bool)$cfg['bigbuy_sandbox']);
$result = $bb->getCategories('es');

$status = (int)($result['status'] ?? 0);
if ($status !== 200) {
    echo "FAIL — HTTP {$status}\n";
    echo "Response body:\n";
    $body = $result['data'] ?? ($result['error'] ?? '(no body)');
    echo (is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE)) . "\n";
    exit(1);
}

$categories = $result['data'];
if (!is_array($categories)) {
    echo "Unexpected response shape (expected an array) — got:\n";
    echo json_encode($categories, JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

$total = count($categories);
echo "OK — fetched {$total} categories.\n\n";
echo "Total category count: {$total}\n\n";

echo "Scanning for pet-related categories (keywords: " . implode(', ', PET_KEYWORDS) . ")...\n\n";

$matches = 0;
foreach ($categories as $cat) {
    if (!is_array($cat)) {
        continue;
    }

    $id     = $cat['id'] ?? '?';
    $name   = extract_category_name($cat['name'] ?? '');
    $parent = $cat['parentCategory'] ?? '?';

    $nameLower = mb_strtolower($name);
    foreach (PET_KEYWORDS as $keyword) {
        if (str_contains($nameLower, $keyword)) {
            $matches++;
            echo "id={$id}  name=\"{$name}\"  parentCategory={$parent}\n";
            break;
        }
    }
}

echo "\n==========================================\n";
echo "Done. {$matches} pet-related categories found out of {$total} total.\n";
echo "==========================================\n";
