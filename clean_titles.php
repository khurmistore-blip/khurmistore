<?php
declare(strict_types=1);

/**
 * clean_titles.php — PREVIEW ONLY. Fetches all products from Supabase and
 * computes a cleaned display title for each: strips distributor SKU codes
 * and "(Reacondicionado ...)" tags, while keeping sizes/dimensions, colors,
 * and real brand/model names. Writes id | original | cleaned to
 * title_cleanup_preview.txt for manual review.
 *
 * Does NOT write to the database. That is a separate, explicitly-approved
 * follow-up step (an update script) run only after this preview is reviewed
 * and the cleaning looks correct — this file only reads and writes a local
 * text report.
 *
 * HEURISTIC, NOT PERFECT: token-pattern matching can't always tell a
 * distributor code (e.g. "CT7279M") from a genuine short model code
 * (e.g. "GTX1660"). It is deliberately conservative — short alphanumeric
 * tokens (under 5 chars, e.g. "S23", "4K", "A54") are always kept, since
 * those are far more likely to be real model/spec names than codes. Review
 * every row in the preview before approving any database update.
 */

require_once __DIR__ . '/supabase.php';
$cfg = require __DIR__ . '/config.php';

/**
 * Clean one product title: remove distributor SKU codes and refurbished
 * tags, keep sizes/colors/brand names, tidy spacing/punctuation.
 */
function clean_product_title(string $name): string
{
    $title = $name;

    // 1. Remove "(Reacondicionado ...)" tags entirely, case-insensitive.
    $title = preg_replace('/\s*\(\s*reacondicionado[^)]*\)/i', '', $title);

    // 2. Join adjacent inch-size ranges like 40" 75" into 40"-75" for
    //    readability (a range reads more naturally than two bare sizes).
    $title = preg_replace('/(\d+)"\s+(\d+)"/', '$1"-$2"', $title);

    // Units that make a nearby long digit run a real measurement rather
    // than a distributor code (e.g. "50000 mAh" should not be stripped).
    $units = ['kg', 'g', 'gr', 'gramos', 'ml', 'l', 'lt', 'litros', 'm', 'cm',
        'mm', 'w', 'v', 'mah', 'gb', 'tb', 'mp', 'hz', 'uds', 'ud', 'pack', 'pulgadas'];

    $tokens = preg_split('/\s+/', trim($title));
    $n = count($tokens);
    $drop = array_fill(0, $n, false);

    for ($i = 0; $i < $n; $i++) {
        $bare = trim($tokens[$i], "(),.");
        if ($bare === '') {
            continue;
        }

        // Pure digit run of 5+ chars -> distributor code (e.g. "036370",
        // "066102"), unless the next word is a unit like "50000 mAh".
        if (preg_match('/^[0-9]{5,}$/', $bare)) {
            $next = isset($tokens[$i + 1]) ? strtolower(trim($tokens[$i + 1], "(),.\"")) : '';
            if (!in_array($next, $units, true)) {
                $drop[$i] = true;
            }
            continue;
        }

        // Hyphenated alphanumeric code with a digit on either side
        // (e.g. "WL30S-910BL16", "B45-N227", "ADS06-123WH").
        if (preg_match('/^[A-Za-z0-9]+-[A-Za-z0-9]+$/', $bare) && preg_match('/[0-9]/', $bare)) {
            $drop[$i] = true;
            continue;
        }

        // Standalone alphanumeric code mixing letters+digits, 5+ chars
        // (e.g. "CT7279M", "RA611702", "1248S"). Short mixed tokens like
        // "S23", "4K", "A54", "X1" are left alone on purpose.
        if (strlen($bare) >= 5 && (
            preg_match('/^[A-Za-z]+[0-9]+[A-Za-z]*$/', $bare) ||
            preg_match('/^[0-9]+[A-Za-z]+$/', $bare)
        )) {
            $drop[$i] = true;
        }
    }

    // 3. A short ALL-CAPS token (2-5 letters, e.g. "GTX") immediately
    //    followed by a dropped code token is almost always a product-line
    //    prefix belonging to that same code, not a real brand — real
    //    brands in this catalog (Trust, Mobilis, Sony...) aren't all-caps.
    for ($i = 0; $i < $n - 1; $i++) {
        $bare = trim($tokens[$i], "(),.");
        if (!$drop[$i] && $drop[$i + 1] && preg_match('/^[A-Z]{2,5}$/', $bare)) {
            $drop[$i] = true;
        }
    }

    $kept = [];
    for ($i = 0; $i < $n; $i++) {
        if (!$drop[$i]) {
            $kept[] = $tokens[$i];
        }
    }
    $title = implode(' ', $kept);

    // 4. Tidy whitespace and stray leading/trailing punctuation.
    $title = preg_replace('/\s+/', ' ', $title);
    $title = trim($title, " ,.-");

    return $title !== '' ? $title : $name; // never produce an empty title
}

// Fetch ALL products (not just active ones) — title cleanup is a data
// quality pass independent of what's currently for sale.
$products = sb_get($cfg, 'products?select=id,name&order=id.asc');

$lines   = [];
$lines[] = str_pad('ID', 8) . ' | ' . str_pad('ORIGINAL', 70) . ' | CLEANED';
$lines[] = str_repeat('-', 8) . '-+-' . str_repeat('-', 70) . '-+-' . str_repeat('-', 40);

$changed = 0;
foreach ($products as $p) {
    $original = (string)($p['name'] ?? '');
    $cleaned  = clean_product_title($original);
    if ($cleaned !== $original) {
        $changed++;
    }
    $lines[] = str_pad((string)$p['id'], 8) . ' | ' . str_pad($original, 70) . ' | ' . $cleaned;
}

$outPath = __DIR__ . '/title_cleanup_preview.txt';
file_put_contents($outPath, implode("\n", $lines) . "\n");

echo "Total products: " . count($products) . "\n";
echo "Titles that would change: $changed\n";
echo "Preview written to: $outPath\n";
echo "No database writes were made.\n";
