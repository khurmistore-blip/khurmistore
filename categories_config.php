<?php
declare(strict_types=1);

/**
 * categories_config.php — Single source of truth for the site's category
 * tree. Returns a nested array; nothing else should hardcode this tree — add
 * new categories/subcategories here only.
 *
 * Each node: ['slug' => ..., 'name' => ..., 'children' => [...]]
 * - Top-level slugs match the EXISTING live `category` column values in
 *   Supabase (auriculares, relojes, accesorios-movil, belleza, electronica)
 *   — do not rename these without also migrating existing product rows.
 * - Sub/sub-sub slugs map to the new `subcategoria`/`sub_subcategoria`
 *   columns (see the ALTER TABLE below) — lowercase, ascii, hyphenated.
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
    [
        'slug' => 'belleza',
        'name' => 'Belleza',
        'children' => [
            [
                'slug' => 'unas-y-herramientas',
                'name' => 'Uñas y Herramientas',
                'children' => [],
            ],
            [
                'slug' => 'alimentacion-y-salud',
                'name' => 'Alimentación y Salud',
                'children' => [
                    ['slug' => 'cuidado-de-la-salud', 'name' => 'Cuidado de la Salud', 'children' => []],
                ],
            ],
            [
                'slug' => 'cabello-y-accesorios',
                'name' => 'Cabello y Accesorios',
                'children' => [
                    ['slug' => 'diademas-y-cintas',  'name' => 'Diademas y Cintas para el Pelo', 'children' => []],
                    ['slug' => 'horquillas',          'name' => 'Horquillas para el Pelo', 'children' => []],
                    ['slug' => 'cabello-humano',      'name' => 'Cabello Humano', 'children' => []],
                ],
            ],
            [
                'slug' => 'cabello-sintetico',
                'name' => 'Cabello Sintético',
                'children' => [
                    ['slug' => 'cabello-para-cosplay', 'name' => 'Cabello para Cosplay', 'children' => []],
                ],
            ],
            [
                'slug' => 'cuidado-de-la-piel',
                'name' => 'Cuidado de la Piel',
                'children' => [
                    ['slug' => 'maquinillas-de-afeitar', 'name' => 'Maquinillas de Afeitar', 'children' => []],
                    ['slug' => 'mascarillas-faciales',   'name' => 'Mascarillas Faciales', 'children' => []],
                    ['slug' => 'proteccion-solar',        'name' => 'Protección Solar', 'children' => []],
                    ['slug' => 'aceites-esenciales',      'name' => 'Aceites Esenciales', 'children' => []],
                    ['slug' => 'cuidado-corporal',        'name' => 'Cuidado Corporal', 'children' => []],
                    ['slug' => 'cuidado-facial',          'name' => 'Cuidado Facial', 'children' => []],
                ],
            ],
            [
                'slug' => 'mechones-de-cabello',
                'name' => 'Mechones de Cabello',
                'children' => [
                    ['slug' => 'paquete-pre-coloreado',   'name' => 'Paquete Pre-Coloreado', 'children' => []],
                    ['slug' => 'tejido-de-cabello',        'name' => 'Tejido de Cabello', 'children' => []],
                    ['slug' => 'estilismo-de-cabello',     'name' => 'Estilismo de Cabello', 'children' => []],
                    ['slug' => 'mechones-de-salon',        'name' => 'Mechones de Salón', 'children' => []],
                    ['slug' => 'mechon-pre-coloreado',     'name' => 'Mechón Pre-Coloreado', 'children' => []],
                ],
            ],
            [
                'slug' => 'maquillaje',
                'name' => 'Maquillaje',
                'children' => [
                    ['slug' => 'lapiz-de-cejas',      'name' => 'Lápiz de Cejas', 'children' => []],
                    ['slug' => 'set-de-maquillaje',   'name' => 'Set de Maquillaje', 'children' => []],
                    ['slug' => 'sombra-de-ojos',      'name' => 'Sombra de Ojos', 'children' => []],
                    ['slug' => 'brochas-de-maquillaje', 'name' => 'Brochas de Maquillaje', 'children' => []],
                    ['slug' => 'pestanas-postizas',   'name' => 'Pestañas Postizas', 'children' => []],
                    ['slug' => 'pintalabios',         'name' => 'Pintalabios', 'children' => []],
                ],
            ],
            [
                'slug' => 'pelucas-y-extensiones',
                'name' => 'Pelucas y Extensiones',
                'children' => [
                    ['slug' => 'peluca-cabello-humano',        'name' => 'Peluca de Cabello Humano', 'children' => []],
                    ['slug' => 'postizo-sintetico',            'name' => 'Postizo Sintético', 'children' => []],
                    ['slug' => 'peluca-encaje-sintetica',      'name' => 'Peluca de Encaje Sintética', 'children' => []],
                    ['slug' => 'peluca-encaje-cabello-humano', 'name' => 'Peluca de Encaje de Cabello Humano', 'children' => []],
                    ['slug' => 'trenzas',                      'name' => 'Trenzas', 'children' => []],
                    ['slug' => 'pelucas-sinteticas',           'name' => 'Pelucas Sintéticas', 'children' => []],
                ],
            ],
            [
                'slug' => 'herramientas-de-belleza',
                'name' => 'Herramientas de Belleza',
                'children' => [
                    ['slug' => 'espejo',                     'name' => 'Espejo', 'children' => []],
                    ['slug' => 'planchas-de-pelo',           'name' => 'Planchas de Pelo', 'children' => []],
                    ['slug' => 'limpiador-facial-electrico', 'name' => 'Limpiador Facial Eléctrico', 'children' => []],
                    ['slug' => 'herramientas-cuidado-facial', 'name' => 'Herramientas de Cuidado Facial', 'children' => []],
                    ['slug' => 'rizador-de-pelo',            'name' => 'Rizador de Pelo', 'children' => []],
                    ['slug' => 'vaporizador-facial',         'name' => 'Vaporizador Facial', 'children' => []],
                ],
            ],
        ],
    ],
    ['slug' => 'electronica',       'name' => 'Electrónica',             'children' => []],
    ['slug' => 'auriculares',       'name' => 'Auriculares y Audio',     'children' => []],
    ['slug' => 'accesorios-movil',  'name' => 'Accesorios para Móvil',   'children' => []],
];
