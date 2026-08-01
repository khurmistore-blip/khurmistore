<?php
declare(strict_types=1);

/**
 * feed.php — RSS 2.0 product feed for Google Merchant Center.
 * Public URL, no ?key= guard (Google's crawler fetches this directly):
 *   https://khurmistore.es/feed.php
 *
 * Field naming follows Google's documented Merchant Center RSS feed spec:
 * https://support.google.com/merchants/answer/7052112 — standard RSS
 * elements (title, description, link) stay UNPREFIXED (Google reuses the
 * RSS 2.0 spec's own elements for those), while Google-specific fields
 * (id, price, availability, brand, condition, image_link, identifier_exists,
 * shipping) use the g: namespace. This differs slightly from the g:title/
 * g:description/g:link naming in the original request — flagging that
 * choice here since a non-standard tag name can cause Merchant Center to
 * reject or warn on the whole feed.
 */

require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/shipping_lib.php';
$cfg = require __DIR__ . '/config.php';
$categoryTree = require_once __DIR__ . '/categories_config.php'; // also defines find_category_path()

header('Content-Type: application/xml; charset=utf-8');

// Same "active products" convention used everywhere else in this codebase
// (producto.php, categoria.php, index.php, sitemap.php) — there is no
// `is_active` boolean column; `status` is a text column, filtered here the
// same way. Using the real existing convention instead of a column that
// doesn't exist.
// TWO-BRANCH CATALOGUE (2026-07-31): category=in.(...) backstop is now built
// from categories_config.php's live top-level slugs (relojes, joyeria, and
// whatever's added later) instead of a hardcoded "relojes" — so a new
// category's products flow into this feed automatically, while still never
// submitting a belleza/electronica/auriculares/accesorios-movil product
// regardless of whatever is_active/approval_status state those retired-
// category rows are actually in at the DB level.
$topLevelSlugs = array_column($categoryTree, 'slug');
$catFilter = implode(',', array_map('rawurlencode', $topLevelSlugs));
$products = sb_get($cfg, 'products?is_active=is.true&approval_status=eq.approved&category=in.(' . $catFilter . ')&order=id.asc');

// Manual exclusion list by product id — add more ids here as needed.
$excluded_ids = [176, 178, 183, 185]; // CBD products

/** XML-safe CDATA block; guards against a literal "]]>" breaking out early. */
function xml_cdata(string $value): string
{
    return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $value) . ']]>';
}

/** XML-safe plain text (for attributes/short values, not wrapped in CDATA). */
function xml_esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** Strip HTML tags + collapse whitespace — descriptions contain <b> tags, titles shouldn't have markup in a feed. */
function clean_feed_text(string $value): string
{
    $value = strip_tags($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return trim($value);
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
echo "<channel>\n";
echo '<title>' . xml_esc('KhurmiStore - Product Feed') . "</title>\n";
echo '<link>' . xml_esc('https://khurmistore.es/') . "</link>\n";
echo '<description>' . xml_esc('KhurmiStore product feed for Google Merchant Center') . "</description>\n";

foreach ($products as $p) {
    $id = (int)($p['id'] ?? 0);
    if (!$id) {
        continue;
    }

    // Manual exclusion by id, or any product whose title/description
    // mentions CBD (case-insensitive) — checked against the raw DB values,
    // before HTML-stripping/cleanup.
    $rawName = (string)($p['name'] ?? '');
    $rawDesc = (string)($p['description'] ?? '');
    if (
        in_array($id, $excluded_ids, true)
        || stripos($rawName, 'CBD') !== false
        || stripos($rawDesc, 'CBD') !== false
    ) {
        continue;
    }

    // Price is required — skip rather than output an invalid item.
    $price = isset($p['price']) ? (float)$p['price'] : 0.0;
    if ($price <= 0) {
        continue;
    }

    // Image is required — take the first of the comma-separated list
    // (same convention as first_product_image() in supabase.php), but
    // skip the item entirely instead of falling back to a placeholder.
    $rawImage = trim(explode(',', (string)($p['image_url'] ?? ''))[0] ?? '');
    if ($rawImage === '') {
        continue;
    }
    $imageLink = str_starts_with($rawImage, 'http')
        ? $rawImage
        : 'https://khurmistore.es/' . ltrim($rawImage, '/');

    $name = clean_feed_text((string)($p['name'] ?? ''));
    if ($name === '') {
        continue; // no usable title -> not a valid item
    }

    $description = clean_feed_text((string)($p['description'] ?? ''));
    if ($description === '') {
        $description = $name;
    }

    $cjPid   = trim((string)($p['cj_pid'] ?? ''));
    $googleId = $cjPid !== '' ? $cjPid : (string)$id;

    $stock        = (int)($p['stock'] ?? 0);
    $availability = $stock > 0 ? 'in_stock' : 'out_of_stock';

    $weight       = isset($p['weight']) && $p['weight'] !== null ? (float)$p['weight'] : null;
    $shippingCost = calcShipping($weight);

    $link = 'https://khurmistore.es/producto.php?id=' . $id;

    // g:product_type, e.g. "Relojes > Hombre > Analógicos" — derived live
    // from categories_config.php via find_category_path(), so a future tree
    // edit (renamed/added/removed category, gender, or sub-type) flows into
    // this feed automatically, without touching feed.php again. A product
    // whose subcategoria/sub_subcategoria don't match any current tree node
    // (e.g. not yet reclassified under the new hombre/mujer split) just
    // yields a shorter path (or none) — never an error, never omits the item.
    $catPath = find_category_path(
        $categoryTree,
        (string)($p['category'] ?? ''),
        (string)($p['subcategoria'] ?? ''),
        (string)($p['sub_subcategoria'] ?? '')
    );
    $productType = implode(' > ', array_column($catPath, 'name'));

    echo "<item>\n";
    echo '<g:id>' . xml_esc($googleId) . "</g:id>\n";
    echo '<title>' . xml_cdata($name) . "</title>\n";
    echo '<description>' . xml_cdata($description) . "</description>\n";
    echo '<link>' . xml_esc($link) . "</link>\n";
    echo '<g:image_link>' . xml_esc($imageLink) . "</g:image_link>\n";
    echo '<g:price>' . xml_esc(number_format($price, 2, '.', '') . ' EUR') . "</g:price>\n";
    echo '<g:availability>' . xml_esc($availability) . "</g:availability>\n";
    echo '<g:brand>' . xml_esc('KhurmiStore') . "</g:brand>\n";
    echo '<g:condition>' . xml_esc('new') . "</g:condition>\n";
    echo '<g:identifier_exists>' . xml_esc('no') . "</g:identifier_exists>\n";
    if ($productType !== '') {
        echo '<g:product_type>' . xml_cdata($productType) . "</g:product_type>\n";
    }
    echo "<g:shipping>\n";
    echo '<g:country>' . xml_esc('ES') . "</g:country>\n";
    echo '<g:price>' . xml_esc(number_format($shippingCost, 2, '.', '') . ' EUR') . "</g:price>\n";
    echo "</g:shipping>\n";
    echo "</item>\n";
}

echo "</channel>\n";
echo "</rss>\n";
