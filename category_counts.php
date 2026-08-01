<?php
declare(strict_types=1);

/**
 * category_counts.php — Lightweight JSON endpoint: how many live (active,
 * approved) products exist at each node of the category tree, keyed by path
 * ("relojes", "relojes>hombre", "relojes>hombre>analogicos"). Fetched once
 * by script.js before rendering the header/mobile nav, so a category,
 * gender, or sub-type with zero products can be hidden instead of rendering
 * as a dead link — see initCategoryMenus() in script.js.
 *
 * Best-effort only: on any failure this returns {} (empty object), which
 * script.js treats as "counts unavailable, leave the nav as originally
 * rendered" — never as "hide everything". A missing/failed fetch must never
 * make the whole nav disappear.
 */

require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/categories_config.php'; // defines build_category_counts()
$cfg = require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300'); // nav doesn't need second-fresh counts

$counts = [];
try {
    $rows = sb_get($cfg, 'products?is_active=is.true&approval_status=eq.approved&select=category,subcategoria,sub_subcategoria');
    if (is_array($rows)) {
        $counts = build_category_counts($rows);
    }
} catch (Throwable $e) {
    error_log('category_counts.php: failed - ' . $e->getMessage());
}

echo json_encode($counts, JSON_UNESCAPED_UNICODE);
