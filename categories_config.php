<?php
declare(strict_types=1);

/**
 * categories_config.php — Single source of truth for the site's category
 * tree. Returns a nested array; nothing else should hardcode this tree — add
 * new categories/subcategories here only.
 *
 * Each node: ['slug' => ..., 'name' => ..., 'children' => [...]]
 * - Top-level slugs match the EXISTING live `category` column values in
 *   Supabase — do not rename these without also migrating existing product
 *   rows.
 * - Sub slugs map to the `subcategoria` column, sub-sub slugs map to
 *   `sub_subcategoria` (see the ALTER TABLE below) — lowercase, ascii,
 *   hyphenated.
 *
 * TWO-BRANCH CATALOGUE (2026-07-31): relojes and joyeria, each split by
 * gender (hombre/mujer) at the `subcategoria` level, with the actual
 * product sub-type (analogicos/digitales for relojes; pulseras/collares/
 * anillos for joyeria) pushed down to `sub_subcategoria`. This is a DEEPER
 * tree than before — relojes previously had analogicos/digitales directly
 * as its `subcategoria` values; those are now `sub_subcategoria` values
 * nested under a gender. Existing relojes product rows whose `subcategoria`
 * is still literally "analogicos"/"digitales" will NOT match either new
 * "hombre"/"mujer" node until reclassified (a data migration, not a code
 * change) — until then they still count toward the top-level "Relojes" nav
 * item (see build_category_counts() below, which counts top-level by
 * `category` alone) but won't populate the new Hombre/Mujer/sub-type nav
 * links, which will correctly stay hidden as "empty" in the meantime.
 * joyeria is a brand-new top-level category with zero product rows today —
 * expected to render as hidden in nav until real joyeria products exist.
 *
 * categoria.php requires this file to resolve breadcrumb/H1 labels via
 * find_category_path() below. The actual on-page nav (desktop per-category
 * dropdowns + mobile accordion) is rendered client-side in script.js from a
 * hand-kept JSON mirror of this same tree (CATEGORY_TREE) — most of this
 * site's 29 pages are static .html and can't run PHP, so this file alone
 * can't drive the markup. Keep both trees in sync when editing categories.
 */

/**
 * Run this in Supabase (not run automatically — you asked to run it
 * yourself):
 *
 *   ALTER TABLE products ADD COLUMN IF NOT EXISTS subcategoria text;
 *   ALTER TABLE products ADD COLUMN IF NOT EXISTS sub_subcategoria text;
 */

/**
 * Resolves display names for a cat/sub/sub2 slug triple against the tree —
 * used by categoria.php to build its breadcrumb and page heading without
 * duplicating this data. Returns one entry per level actually found
 * (['slug' => ..., 'name' => ...]); stops as soon as a slug doesn't match,
 * so an unknown/stale sub or sub2 value just falls back to whatever level
 * above it was still valid instead of erroring.
 */
function find_category_path(array $tree, string $catSlug, string $subSlug = '', string $sub2Slug = ''): array
{
    $path = [];
    if ($catSlug === '') {
        return $path;
    }

    $catNode = null;
    foreach ($tree as $node) {
        if ($node['slug'] === $catSlug) { $catNode = $node; break; }
    }
    if (!$catNode) {
        return $path;
    }
    $path[] = ['slug' => $catNode['slug'], 'name' => $catNode['name']];
    if ($subSlug === '') {
        return $path;
    }

    $subNode = null;
    foreach ($catNode['children'] ?? [] as $sub) {
        if ($sub['slug'] === $subSlug) { $subNode = $sub; break; }
    }
    if (!$subNode) {
        return $path;
    }
    $path[] = ['slug' => $subNode['slug'], 'name' => $subNode['name']];
    if ($sub2Slug === '') {
        return $path;
    }

    foreach ($subNode['children'] ?? [] as $sub2) {
        if ($sub2['slug'] === $sub2Slug) {
            $path[] = ['slug' => $sub2['slug'], 'name' => $sub2['name']];
            break;
        }
    }
    return $path;
}

/**
 * Builds a flat product-count map keyed by tree path ("cat", "cat>sub",
 * "cat>sub>sub2") from a list of product rows, each expected to have
 * category/subcategoria/sub_subcategoria keys (exactly what
 * `select=category,subcategoria,sub_subcategoria` returns from Supabase).
 * Shared by category_counts.php (nav "hide empty category" endpoint) and
 * sitemap.php (skip emitting empty category URLs), so both agree on exactly
 * what "has products" means for a given path — a top-level count includes
 * EVERY product under that category regardless of whether subcategoria is
 * set, so an existing category with not-yet-reclassified products still
 * shows up even while its gender/sub-type children are still empty.
 */
function build_category_counts(array $products): array
{
    $counts = [];
    foreach ($products as $p) {
        $cat  = trim((string)($p['category'] ?? ''));
        $sub  = trim((string)($p['subcategoria'] ?? ''));
        $sub2 = trim((string)($p['sub_subcategoria'] ?? ''));
        if ($cat === '') {
            continue;
        }
        $counts[$cat] = ($counts[$cat] ?? 0) + 1;
        if ($sub === '') {
            continue;
        }
        $subKey = $cat . '>' . $sub;
        $counts[$subKey] = ($counts[$subKey] ?? 0) + 1;
        if ($sub2 === '') {
            continue;
        }
        $sub2Key = $subKey . '>' . $sub2;
        $counts[$sub2Key] = ($counts[$sub2Key] ?? 0) + 1;
    }
    return $counts;
}

return [
    [
        'slug' => 'relojes',
        'name' => 'Relojes',
        'children' => [
            [
                'slug' => 'hombre',
                'name' => 'Hombre',
                'children' => [
                    ['slug' => 'analogicos', 'name' => 'Analógicos', 'children' => []],
                    ['slug' => 'digitales',  'name' => 'Digitales', 'children' => []],
                ],
            ],
            [
                'slug' => 'mujer',
                'name' => 'Mujer',
                'children' => [
                    ['slug' => 'analogicos', 'name' => 'Analógicos', 'children' => []],
                    ['slug' => 'digitales',  'name' => 'Digitales', 'children' => []],
                ],
            ],
        ],
    ],
    [
        'slug' => 'joyeria',
        'name' => 'Joyería',
        'children' => [
            [
                'slug' => 'hombre',
                'name' => 'Hombre',
                'children' => [
                    ['slug' => 'pulseras', 'name' => 'Pulseras', 'children' => []],
                    ['slug' => 'collares', 'name' => 'Collares', 'children' => []],
                    ['slug' => 'anillos',  'name' => 'Anillos', 'children' => []],
                ],
            ],
            [
                'slug' => 'mujer',
                'name' => 'Mujer',
                'children' => [
                    ['slug' => 'pulseras', 'name' => 'Pulseras', 'children' => []],
                    ['slug' => 'collares', 'name' => 'Collares', 'children' => []],
                    ['slug' => 'anillos',  'name' => 'Anillos', 'children' => []],
                ],
            ],
        ],
    ],
];
