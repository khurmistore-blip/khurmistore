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
 * - Sub/sub-sub slugs map to the `subcategoria`/`sub_subcategoria` columns
 *   (see the ALTER TABLE below) — lowercase, ascii, hyphenated.
 *
 * SMART-WATCH-ONLY PIVOT (2026-07-28): the store now sells only relojes —
 * belleza/electronica/auriculares/accesorios-movil were removed from this
 * tree (and from script.js's CATEGORY_TREE mirror below), so they no longer
 * appear in nav or on the homepage. Their product ROWS still exist in
 * Supabase with those `category` values and are still directly reachable
 * via /categoria.php?cat=belleza (etc.) — categoria.php's own $categoryConfig
 * wasn't touched, this pivot only covers nav/homepage/config. Unpublishing
 * or removing those rows, and cleaning up categoria.php/blog/legal pages
 * that still reference them, is explicitly a separate later step.
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

return [
    [
        'slug' => 'relojes',
        'name' => 'Relojes',
        'children' => [
            ['slug' => 'analogicos', 'name' => 'Analógicos', 'children' => []],
            ['slug' => 'digitales',  'name' => 'Digitales / Smartwatch', 'children' => []],
        ],
    ],
];
