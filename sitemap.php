<?php
declare(strict_types=1);

/**
 * sitemap.php — Dynamic XML sitemap, the single source of truth for search
 * engines (replaces the old hand-maintained sitemap.xml). Category and
 * product URLs are pulled live from Supabase so new/removed products are
 * reflected automatically — no manual sitemap editing after each sync.
 *
 * products.html / categorias.html / product-details.html are intentionally NOT
 * listed here — those legacy static demo pages (fake Unsplash catalog) have
 * been deleted, superseded by the real category/product pages (categoria.php /
 * producto.php) that pull live Supabase data.
 */

require_once __DIR__ . '/supabase.php';
$cfg = require __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');

$today = date('Y-m-d');

// Real category slugs — same list used by the header "Categorías" dropdown.
$categorySlugs = ['auriculares', 'relojes', 'accesorios-movil', 'belleza', 'electronica'];

// All active products, for individual producto.php?id=X pages.
$products = sb_get($cfg, 'products?status=eq.active&approval_status=eq.approved&select=id');

$urls = [];

// ── Página principal ────────────────────────────────────────────────────
$urls[] = ['loc' => 'https://khurmistore.es/', 'changefreq' => 'weekly', 'priority' => '1.0'];
$urls[] = ['loc' => 'https://khurmistore.es/categoria.php', 'changefreq' => 'weekly', 'priority' => '0.9'];

// ── Categorías (contenido real, vía categoria.php) ──────────────────────
foreach ($categorySlugs as $slug) {
    $urls[] = ['loc' => 'https://khurmistore.es/categoria.php?cat=' . $slug, 'changefreq' => 'daily', 'priority' => '0.9'];
}

// ── Fichas de producto reales (vía producto.php) ────────────────────────
foreach ($products as $p) {
    if (empty($p['id'])) {
        continue;
    }
    $urls[] = ['loc' => 'https://khurmistore.es/producto.php?id=' . (int)$p['id'], 'changefreq' => 'weekly', 'priority' => '0.8'];
}

// ── Blog (índice + artículos) ────────────────────────────────────────────
$urls[] = ['loc' => 'https://khurmistore.es/blog.html', 'changefreq' => 'weekly', 'priority' => '0.8'];
$blogPosts = [
    'blog-elegir-auriculares-bluetooth.html',
    'blog-mejores-auriculares-inalambricos.html',
    'blog-mejores-auriculares-deporte.html',
    'blog-auriculares-vs-cascos-gaming.html',
    'blog-como-conectar-auriculares-bluetooth.html',
    'blog-como-limpiar-auriculares-inalambricos.html',
    'blog-conectar-auriculares-bluetooth-tv.html',
    'blog-conectar-cascos-gaming.html',
    'blog-alargar-bateria-smartwatch.html',
    'blog-smartwatch-barato-espana.html',
    'blog-conectar-smartwatch-movil.html',
    'blog-como-elegir-reloj-inteligente.html',
    'blog-como-elegir-raton-inalambrico.html',
    'blog-como-elegir-funda-movil.html',
    'blog-manos-libres-coche.html',
    'blog-protector-pantalla-sin-burbujas.html',
];
foreach ($blogPosts as $slug) {
    $urls[] = ['loc' => 'https://khurmistore.es/' . $slug, 'changefreq' => 'monthly', 'priority' => '0.7'];
}

// ── Páginas institucionales ───────────────────────────────────────────────
$urls[] = ['loc' => 'https://khurmistore.es/sobre-nosotros.html', 'changefreq' => 'monthly', 'priority' => '0.7'];
$urls[] = ['loc' => 'https://khurmistore.es/contacto.html', 'changefreq' => 'monthly', 'priority' => '0.6'];
$urls[] = ['loc' => 'https://khurmistore.es/preguntas-frecuentes.html', 'changefreq' => 'monthly', 'priority' => '0.6'];

// ── Páginas legales ────────────────────────────────────────────────────────
$legalPages = [
    'aviso-legal.html',
    'politica-de-privacidad.html',
    'politica-de-cookies.html',
    'politica-de-envios.html',
    'politica-de-devoluciones.html',
    'terminos-y-condiciones.html',
];
foreach ($legalPages as $slug) {
    $urls[] = ['loc' => 'https://khurmistore.es/' . $slug, 'changefreq' => 'yearly', 'priority' => '0.3'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "    <url>\n";
    echo '        <loc>' . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
    echo '        <lastmod>' . $today . "</lastmod>\n";
    echo '        <changefreq>' . $u['changefreq'] . "</changefreq>\n";
    echo '        <priority>' . $u['priority'] . "</priority>\n";
    echo "    </url>\n";
}
echo '</urlset>' . "\n";
