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
 *
 * IMPORTANT: this endpoint must output ONLY valid XML — no shared header/
 * footer/nav includes, nothing echoed before the <?xml declaration. Google
 * Search Console reports "Couldn't fetch / Unknown" type the moment even one
 * stray byte (a PHP warning, a deprecation notice, whitespace) precedes it —
 * that's why display_errors is forced off and the header is sent before any
 * require/fetch runs, rather than after.
 */

// Must run before ANY require/echo — nothing may be sent to the browser
// ahead of the XML content-type header.
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
header('Content-Type: application/xml; charset=utf-8');
http_response_code(200);

require_once __DIR__ . '/supabase.php';
$cfg = require __DIR__ . '/config.php';

$today = date('Y-m-d');

// Real category slugs — same list used by the header "Categorías" dropdown.
// SMART-WATCH-ONLY PIVOT (2026-07-28): reduced to relojes — the other 4
// categories' product rows are hidden in the DB now, and their /category/
// slug URLs were left in this list until this fix, which is exactly the
// "thin/empty content" risk this pivot's cleanup pass was meant to close.
$categorySlugs = ['relojes'];

// All active, approved products, for individual producto.php?id=X pages.
// Wrapped defensively so a Supabase error/warning can never leak text into
// the XML body — worst case the sitemap just omits product URLs this run.
// category=eq.relojes is a defensive backstop, not just relying on
// is_active/approval_status to have been set correctly for the retired
// categories' rows — belt and suspenders for what Google/Merchant Center see.
$products = [];
try {
    $fetched = sb_get($cfg, 'products?is_active=is.true&approval_status=eq.approved&category=eq.relojes&select=id');
    if (is_array($fetched)) {
        $products = $fetched;
    }
} catch (Throwable $e) {
    error_log('sitemap.php: product fetch failed - ' . $e->getMessage());
}

$urls = [];

// ── Página principal ────────────────────────────────────────────────────
$urls[] = ['loc' => 'https://khurmistore.es/', 'changefreq' => 'weekly', 'priority' => '1.0'];
$urls[] = ['loc' => 'https://khurmistore.es/categoria.php', 'changefreq' => 'weekly', 'priority' => '0.9'];

// ── Categorías (contenido real, vía categoria.php, URL amigable sin query
// string — /category/slug ya reescribe internamente a categoria.php?cat=slug
// según .htaccess) ────────────────────────────────────────────────────────
foreach ($categorySlugs as $slug) {
    $urls[] = ['loc' => 'https://khurmistore.es/category/' . $slug, 'changefreq' => 'daily', 'priority' => '0.9'];
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
