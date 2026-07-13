<?php
/**
 * categoria.php — Lists all ACTIVE products from Supabase, inside the site's
 * real header/nav/footer (same chrome as index.php and producto.php).
 * URL: categoria.php            (all products)
 *      categoria.php?cat=auriculares  (one category)
 * -------------------------------------------------------------
 * The card markup below mirrors script.js's renderProducts() card template
 * (.product-card / .product-img / .product-info) so it visually matches the
 * homepage's "Productos Destacados" grid. It's rendered server-side rather
 * than through renderProducts() itself, and deliberately does NOT use the id
 * "productsGrid" — script.js auto-runs renderProducts() on every page load
 * and would overwrite that id's contents with the 38 hardcoded demo products.
 */

require_once __DIR__ . '/supabase.php';
$cfg = require __DIR__ . '/config.php';

$cat   = isset($_GET['cat']) ? trim($_GET['cat']) : '';
// Excludes refurbished/"Reacondicionado" listings — a reviewer flagged one as
// looking out of place; hidden from display only, never deleted from the DB.
$query = 'products?status=eq.active&name=not.ilike.*Reacondicionado*&order=created_at.desc';
if ($cat !== '') {
    $query .= '&category=eq.' . rawurlencode($cat);
}
$products = sb_get($cfg, $query);

// Per-category SEO config: display label, <title>, meta description
// (120-155 chars), and a short keyword-rich intro paragraph shown under the
// H1. Keeps categoria.php's existing single-file-covers-every-category
// approach — this dict is the only thing that grows as categories change.
$categoryConfig = [
    'auriculares' => [
        'label'       => 'Auriculares y Audio',
        'title'       => 'Auriculares y Cascos | Comprar Online en España | KhurmiStore',
        'description' => 'Descubre nuestra selección de auriculares inalámbricos, cascos gaming y accesorios de audio. Envío rápido a toda España y pago seguro en KhurmiStore.',
        'intro'       => 'En KhurmiStore encontrarás una amplia gama de auriculares inalámbricos y cascos gaming pensados para quienes buscan la mejor calidad de sonido. Desde auriculares deportivos hasta modelos con cancelación de ruido, toda nuestra selección de audio llega con envío rápido a toda España.',
    ],
    'relojes' => [
        'label'       => 'Relojes',
        'title'       => 'Relojes de Hombre y Mujer | Comprar Online | KhurmiStore',
        'description' => 'Compra relojes de hombre, mujer y unisex de las mejores marcas al mejor precio. Diseños elegantes y deportivos con envío rápido a toda España.',
        'intro'       => 'Nuestra colección de relojes incluye modelos de hombre, mujer y unisex de marcas reconocidas, combinando diseños elegantes para el día a día con opciones más deportivas. Encuentra el estilo perfecto y recíbelo rápido en cualquier punto de España.',
    ],
    'accesorios-movil' => [
        'label'       => 'Accesorios para Móvil',
        'title'       => 'Accesorios para Móvil: Fundas y Protectores | KhurmiStore',
        'description' => 'Fundas, protectores de pantalla y accesorios para tu móvil y tablet. Protección de calidad al mejor precio, con envío rápido a toda España.',
        'intro'       => 'Protege tu smartphone con nuestra selección de fundas y protectores de pantalla compatibles con los modelos más populares del mercado. Calidad y protección al mejor precio, con envío rápido a toda España.',
    ],
    'belleza' => [
        'label'       => 'Belleza',
        'title'       => 'Productos de Belleza y Cuidado Facial | KhurmiStore',
        'description' => 'Cosmética, cuidado facial, dispositivos de belleza y cuidado del cabello de marcas líderes. Descubre tu rutina ideal con envío rápido a toda España.',
        'intro'       => 'Descubre cosmética y cuidado facial de marcas líderes, además de dispositivos de belleza y productos para el cuidado del cabello pensados para toda rutina. Encuentra tu próximo imprescindible con envío rápido a toda España.',
    ],
    'electronica' => [
        'label'       => 'Electrónica',
        'title'       => 'Electrónica y Gadgets | Comprar Online | KhurmiStore',
        'description' => 'Altavoces, cargadores, cables y gadgets electrónicos al mejor precio. Tecnología de calidad con envío rápido a toda España y pago seguro.',
        'intro'       => 'Explora nuestra selección de altavoces, cargadores, cables y gadgets electrónicos para el día a día. Tecnología de calidad al mejor precio, con envío rápido a toda España y pago 100% seguro.',
    ],
];

// Fallback used both when ?cat= is missing (the "todos los productos" view)
// and when it's set to an unrecognized/old slug — never an empty title,
// description, or H1.
$defaultCatConfig = [
    'label'       => 'Todos los Productos',
    'title'       => 'Tienda Online | Belleza, Tech y Electrónica | KhurmiStore',
    'description' => 'Descubre toda la tienda de KhurmiStore: belleza, accesorios para móvil, relojes y electrónica. Envío rápido a toda España y pago 100% seguro.',
    'intro'       => 'Bienvenido a la tienda de KhurmiStore, con una selección cuidada de productos de belleza, accesorios para móvil, relojes y electrónica. Compra con confianza: envío rápido a toda España y pago 100% seguro.',
];

if ($cat !== '' && isset($categoryConfig[$cat])) {
    $catConfig = $categoryConfig[$cat];
} elseif ($cat !== '') {
    // Unknown/old slug (e.g. a deleted category) — keep the attempted slug
    // as the visible label but fall back to generic, still non-empty SEO copy.
    $catConfig = $defaultCatConfig;
    $catConfig['label'] = ucfirst(str_replace('-', ' ', $cat));
} else {
    $catConfig = $defaultCatConfig;
}
$catLabel = $catConfig['label'];

// Slug -> label map for the product cards' category tag, derived from the
// same config above (previously a separate, incomplete list missing
// "electronica" entirely).
$categoryLabels = array_combine(array_keys($categoryConfig), array_column($categoryConfig, 'label'));

// <title>: cap at ~60 chars (Google truncates longer titles), same
// truncate-with-ellipsis approach used on producto.php.
$pageTitle = $catConfig['title'];
if (mb_strlen($pageTitle) > 60) {
    $pageTitle = rtrim(mb_substr($pageTitle, 0, 59)) . '…';
}

$canonicalUrl = 'https://khurmistore.es/categoria.php' . ($cat !== '' ? '?cat=' . rawurlencode($cat) : '');

// og:image: first image of the first product already fetched above for this
// category — zero extra Supabase queries. Falls back to the site's default
// og-image.jpg (not the SVG placeholder used for card thumbnails — social
// crawlers need a real HTTP image).
$catSocialImage = '';
if (!empty($products)) {
    $catSocialImage = trim(explode(',', $products[0]['image_url'] ?? '')[0] ?? '');
}
if ($catSocialImage === '') {
    $catSocialImage = 'https://khurmistore.es/og-image.jpg';
}

// CollectionPage + BreadcrumbList JSON-LD (schema.org) for this category.
$collectionSchema = [
    '@context'    => 'https://schema.org',
    '@type'       => 'CollectionPage',
    'name'        => $catConfig['title'],
    'description' => $catConfig['description'],
    'url'         => $canonicalUrl,
];
$breadcrumbSchema = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => 'https://khurmistore.es/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $catLabel, 'item' => $canonicalUrl],
    ],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-CTNSGTD2JS"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-CTNSGTD2JS');
</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="<?= htmlspecialchars($catConfig['description']) ?>">
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($catConfig['description']) ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($catSocialImage) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
<meta property="og:description" content="<?= htmlspecialchars($catConfig['description']) ?>">
<meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
<meta property="og:image" content="<?= htmlspecialchars($catSocialImage) ?>">
<meta property="og:locale" content="es_ES">
<script type="application/ld+json"><?= json_encode($collectionSchema, JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/ld+json"><?= json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE) ?></script>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="style.min.css">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='75' font-size='75' fill='%23ff6b35'>K</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=optional">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=optional" media="print" onload="this.media='all'">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
<noscript>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=optional" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</noscript>

<script src="/js/meta-pixel.js" defer></script>
<body>
<!-- Barra de Anuncios -->
<div class="announcement-bar" id="announcementBar">
    <div class="announcement-content">
        <i class="fas fa-bolt"></i>
        <span class="scroll-text">
             🚚 Envío GRATIS a toda España • ¡Mega Rebajas! 50% de DESCUENTO en todos los auriculares • Compra 2 y llévate 1 GRATIS en auriculares inalámbricos • Pago a plazos disponible
        </span>
        <button class="close-announcement" onclick="closeAnnouncement()" aria-label="Cerrar anuncio"><i class="fas fa-times"></i></button>
    </div>
</div>

    <!-- Encabezado -->
    <header class="header">
        <div class="nav-container">
            <!-- Logo -->
            <div class="logo animated-logo">
                <div class="logo-icon">
                    <div class="wave-ring"></div>
                    <div class="wave-ring"></div>
                    <div class="wave-ring"></div>
                    <i class="fas fa-wave-square"></i>
                    <div class="particle p1"></div>
                    <div class="particle p2"></div>
                    <div class="particle p3"></div>
                    <div class="particle p4"></div>
                </div>
                <div class="logo-text"><span class="letter">K</span><span class="letter">h</span><span class="letter">u</span><span class="letter">r</span><span class="letter">m</span><span class="letter">i</span><span class="letter">S</span><span class="letter">t</span><span class="letter">o</span><span class="letter">r</span><span class="letter">e</span></div>
            </div>

            <!-- Desktop Navigation Menu (shown on desktop, hidden on mobile) -->
            <nav class="nav-menu desktop-nav" id="desktopNav">
                <ul>
                    <!-- Inicio (Home) Link -->
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="categoria.php">Tienda</a></li>

                    <!-- Categorías Dropdown - Contains product categories (stays intact) -->
                    <li class="nav-item dropdown">
                        <button class="dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="categoryDropdown" onclick="toggleCategoryDropdown(event)">
                            Categorías <i class="fas fa-chevron-down dropdown-icon"></i>
                        </button>
                        <ul class="dropdown-menu" id="categoryDropdown" role="menu" aria-label="Categorías">
                            <li><a href="categoria.php?cat=auriculares">Auriculares y Audio</a></li>
                            <li><a href="categoria.php?cat=relojes">Relojes</a></li>
                            <li><a href="categoria.php?cat=accesorios-movil">Accesorios para Móvil</a></li>
                            <li><a href="categoria.php?cat=belleza">Belleza</a></li>
                            <li><a href="categoria.php?cat=electronica">Electrónica</a></li>
                            <li><a href="categoria.php">Ver Todo</a></li>
                        </ul>
                    </li>

                    <!-- Blog Link -->
                    <li><a href="blog.html">Blog</a></li>

                    <!-- Sobre Nosotros (About Us) Link -->
                    <li><a href="sobre-nosotros.html">Sobre Nosotros</a></li>

                    <!-- Contacto (Contact) Link -->
                    <li><a href="contacto.html">Contacto</a></li>
                </ul>
            </nav>

            <!-- Navigation Icons (Search, Heart, Cart) -->
            <div class="nav-icons">
                <i class="fas fa-search" onclick="openSearch()" style="cursor: pointer;"></i>
                <i class="fas fa-heart"></i>
                <div class="cart-icon" onclick="openCart()">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count">0</span>
                </div>
            </div>

            <!-- Mobile Hamburger Button (hidden on desktop, shown only on mobile below 768px) -->
            <button class="nav-toggle mobile-nav-toggle" id="navToggle" type="button" aria-controls="mobileNavMenu" aria-expanded="false" onclick="toggleMobileMenu()" aria-label="Abrir menú">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Mobile Navigation Overlay (backdrop for mobile menu) -->
            <div class="mobile-nav-overlay" id="mobileNavOverlay" onclick="closeMobileMenu()"></div>

            <!-- Mobile Navigation Menu (hidden on desktop, shown only on mobile) -->
            <nav class="nav-menu mobile-nav-menu" id="mobileNavMenu">
                <ul>
                    <!-- Inicio (Home) Link -->
                    <li><a href="index.php" onclick="closeMobileMenu()">Inicio</a></li>
                    <li><a href="categoria.php" onclick="closeMobileMenu()">Tienda</a></li>

                    <!-- Categorías Dropdown for Mobile -->
                    <li class="nav-item dropdown">
                        <button class="dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="mobileCategories" onclick="toggleCategoryDropdown(event)">
                            Categorías <i class="fas fa-chevron-down dropdown-icon"></i>
                        </button>
                        <ul class="dropdown-menu" id="mobileCategories" role="menu" aria-label="Categorías">
                            <li><a href="categoria.php?cat=auriculares" onclick="closeMobileMenu()">Auriculares y Audio</a></li>
                            <li><a href="categoria.php?cat=relojes" onclick="closeMobileMenu()">Relojes</a></li>
                            <li><a href="categoria.php?cat=accesorios-movil" onclick="closeMobileMenu()">Accesorios para Móvil</a></li>
                            <li><a href="categoria.php?cat=belleza" onclick="closeMobileMenu()">Belleza</a></li>
                            <li><a href="categoria.php?cat=electronica" onclick="closeMobileMenu()">Electrónica</a></li>
                            <li><a href="categoria.php" onclick="closeMobileMenu()">Ver Todo</a></li>
                        </ul>
                    </li>

                    <!-- Blog Link -->
                    <li><a href="blog.html" onclick="closeMobileMenu()">Blog</a></li>

                    <!-- Sobre Nosotros (About Us) Link -->
                    <li><a href="sobre-nosotros.html" onclick="closeMobileMenu()">Sobre Nosotros</a></li>

                    <!-- Contacto (Contact) Link -->
                    <li><a href="contacto.html" onclick="closeMobileMenu()">Contacto</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div class="wrap" style="max-width:1180px;margin:0 auto;padding:18px 20px;color:#9AA0BC;font-size:13px;">
        <a href="index.php" style="color:inherit;text-decoration:none;">Inicio</a>
        <i class="fas fa-chevron-right" style="font-size:10px;margin:0 6px;"></i>
        <span><?= htmlspecialchars($catLabel) ?></span>
    </div>

    <!-- Productos de la Categoría -->
    <section class="products" id="products">
        <div class="section-header">
            <span class="subtitle"><?= $cat ? 'CATEGORÍA' : 'CATÁLOGO COMPLETO' ?></span>
            <h1><?= htmlspecialchars($catLabel) ?></h1>
            <!-- Indexable intro copy — the grid alone is thin content for SEO. -->
            <p style="color:#9AA0BC;font-size:14px;line-height:1.7;max-width:720px;margin:14px auto 0;">
                <?= htmlspecialchars($catConfig['intro']) ?>
            </p>
            <p style="color:#9AA0BC;margin-top:8px;">
                <?= count($products) ?> producto<?= count($products) === 1 ? '' : 's' ?> disponible<?= count($products) === 1 ? '' : 's' ?>
            </p>
        </div>

        <?php if (empty($products)): ?>
            <div style="padding:60px 20px;text-align:center;color:#9AA0BC;">
                Todavía no hay productos en esta categoría. Vuelve pronto.
                <br><a href="categoria.php" class="btn-primary" style="display:inline-block;margin-top:16px;">Ver todos los productos</a>
            </div>
        <?php else: ?>
            <div class="products-grid" id="categoryProductsGrid">
                <?php foreach ($products as $p): ?>
                    <div class="product-card" onclick="window.location.href='producto.php?id=<?= (int)$p['id'] ?>'">
                        <div class="product-img">
                            <img src="<?= htmlspecialchars(first_product_image($p['image_url'] ?? '')) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                            <div class="quick-view"><i class="fas fa-eye"></i> Ver Detalles</div>
                        </div>
                        <div class="product-info">
                            <h3><?= htmlspecialchars($p['name']) ?></h3>
                            <?php if (!empty($p['category'])): ?><p class="product-cat"><?= htmlspecialchars($categoryLabels[$p['category']] ?? $p['category']) ?></p><?php endif; ?>
                            <span class="badge-nuevo">Nuevo</span>
                            <div class="product-price">
                                <span class="price"><?= price_es((float)$p['price'], $cfg['currency_symbol']) ?></span>
                                <button type="button" class="add-cart" onclick="event.stopPropagation(); addToCart(<?= (int)$p['id'] ?>)" aria-label="Añadir <?= htmlspecialchars($p['name']) ?> al carrito">
                                    <i class="fas fa-cart-plus"></i> Añadir al carrito
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Características -->
    <section class="features">
        <div class="feature-box"><i class="fas fa-truck-fast"></i><h3>Envío Gratis</h3><p>En pedidos +50€</p></div>
        <div class="feature-box"><i class="fas fa-shield-halved"></i><h3>Pago Seguro</h3><p>100% Protegido</p></div>
        <div class="feature-box"><i class="fas fa-rotate-left"></i><h3>Devolución Fácil</h3><p>Política de 14 días</p></div>
        <div class="feature-box"><i class="fas fa-headset"></i><h3>Soporte 24/7</h3><p>Siempre disponibles</p></div>
    </section>

    <!-- Pie de Página -->
    <footer class="footer" id="contact">
        <div class="footer-content">
            <div class="footer-col">
                <div class="logo"><i class="fas fa-wave-square"></i><div class="logo-text"><span class="letter">K</span><span class="letter">h</span><span class="letter">u</span><span class="letter">r</span><span class="letter">m</span><span class="letter">i</span><span class="letter">S</span><span class="letter">t</span><span class="letter">o</span><span class="letter">r</span><span class="letter">e</span></div></div>
                <p>Tu tienda online de belleza, accesorios para móvil, relojes y electrónica en España.</p>
                <div class="social-icons">
                    <a href="https://www.facebook.com/profile.php?id=61590018628529" target="_blank" rel="noopener noreferrer" aria-label="Síguenos en Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="https://www.instagram.com/khurmistore.es/" target="_blank" rel="noopener noreferrer" aria-label="Síguenos en Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="https://wa.me/34662241860?text=Hola,%20tengo%20una%20pregunta" target="_blank" rel="noopener" aria-label="Contáctanos por WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="https://es.trustpilot.com/review/khurmistore.es" target="_blank" rel="noopener noreferrer" aria-label="Síguenos en Trustpilot"><i class="fas fa-star"></i></a>
                    <a href="https://www.pinterest.com/khurmistore/" target="_blank" rel="noopener noreferrer" aria-label="Síguenos en Pinterest"><i class="fab fa-pinterest"></i></a>
                    <a href="https://www.linkedin.com/company/130474406" target="_blank" rel="noopener noreferrer" aria-label="Síguenos en LinkedIn"><i class="fab fa-linkedin"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h3>Información</h3>
                <ul>
                    <li><a href="index.php">Inicio</a></li>
                    <li><a href="categoria.php">Tienda</a></li>
                    <li><a href="sobre-nosotros.html">Sobre Nosotros</a></li>
                    <li><a href="contacto.html">Contacto</a></li>
                    <li><a href="preguntas-frecuentes.html">Preguntas Frecuentes</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Categorías</h3>
                <ul>
                    <li><a href="categoria.php?cat=auriculares">Auriculares y Audio</a></li>
                    <li><a href="categoria.php?cat=relojes">Relojes</a></li>
                    <li><a href="categoria.php?cat=accesorios-movil">Accesorios para Móvil</a></li>
                    <li><a href="categoria.php?cat=belleza">Belleza</a></li>
                    <li><a href="categoria.php?cat=electronica">Electrónica</a></li>
                    <li><a href="categoria.php">Ver todas las categorías</a></li>
                    <li><a href="categoria.php">Todos los productos</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Contacto</h3>
                <p><i class="fas fa-map-marker-alt"></i> Madrid, España</p>
                <p><i class="fas fa-phone"></i> +34 607 35 80 33</p>
                <p><i class="fas fa-envelope"></i> info@khurmistore.es</p>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-seo-grid">
                <nav class="footer-seo-col" aria-label="Guías y Blog">
                    <h4 class="footer-seo-h">Guías y Blog</h4>
                    <ul>
                        <li><a href="blog.html">Blog</a></li>
                        <li><a href="blog-elegir-auriculares-bluetooth.html">Elegir Auriculares Bluetooth</a></li>
                        <li><a href="blog-mejores-auriculares-inalambricos.html">Mejores Auriculares Inalámbricos</a></li>
                        <li><a href="blog-mejores-auriculares-deporte.html">Auriculares para Deporte</a></li>
                        <li><a href="blog-auriculares-vs-cascos-gaming.html">Auriculares vs Cascos Gaming</a></li>
                        <li><a href="blog-como-conectar-auriculares-bluetooth.html">Conectar Auriculares Bluetooth</a></li>
                        <li><a href="blog-como-limpiar-auriculares-inalambricos.html">Limpiar Auriculares</a></li>
                        <li><a href="blog-conectar-auriculares-bluetooth-tv.html">Auriculares en la TV</a></li>
                        <li><a href="blog-conectar-cascos-gaming.html">Conectar Cascos Gaming</a></li>
                        <li><a href="blog-alargar-bateria-smartwatch.html">Batería del Smartwatch</a></li>
                        <li><a href="blog-smartwatch-barato-espana.html">Smartwatch Barato</a></li>
                        <li><a href="blog-conectar-smartwatch-movil.html">Conectar Smartwatch al Móvil</a></li>
                        <li><a href="blog-como-elegir-reloj-inteligente.html">Elegir Reloj Inteligente</a></li>
                        <li><a href="blog-como-elegir-raton-inalambrico.html">Elegir Ratón Inalámbrico</a></li>
                        <li><a href="blog-como-elegir-funda-movil.html">Elegir Funda para Móvil</a></li>
                        <li><a href="blog-manos-libres-coche.html">Manos Libres para el Coche</a></li>
                        <li><a href="blog-protector-pantalla-sin-burbujas.html">Protector de Pantalla sin Burbujas</a></li>                    </ul>
                </nav>
                <nav class="footer-seo-col footer-legal" aria-label="Legal">
                    <h4 class="footer-seo-h">Legal</h4>
                    <ul>
                        <li><a href="aviso-legal.html">Aviso Legal</a></li>
                        <li><a href="politica-de-privacidad.html">Política de Privacidad</a></li>
                        <li><a href="politica-de-cookies.html">Política de Cookies</a></li>
                        <li><a href="terminos-y-condiciones.html">Términos y Condiciones</a></li>
                        <li><a href="politica-de-envios.html">Política de Envíos</a></li>
                        <li><a href="politica-de-devoluciones.html">Política de Devoluciones</a></li>
                    </ul>
                </nav>
            </div>
            <p class="footer-copyright">&copy; 2025 Khurmi Store España. Todos los derechos reservados.</p>
        </div>
    </footer>

    <!-- Carrito Lateral -->
    <div class="cart-overlay" id="cartOverlay" onclick="closeCart()"></div>
    <div class="cart-sidebar" id="cartSidebar">
        <div class="cart-header">
            <h2><i class="fas fa-shopping-cart"></i> Tu Carrito</h2>
            <button class="close-btn" onclick="closeCart()" aria-label="Cerrar carrito"><i class="fas fa-times"></i></button>
        </div>
        <div class="cart-items" id="cartItems"></div>
        <div class="cart-footer">
            <div class="cart-total"><span>Total:</span><span class="total-price" id="cartTotal">€0,00</span></div>
            <button class="btn-primary checkout-btn" onclick="openCheckout()">Finalizar Compra <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>

    <!-- Modal de Pago -->
    <div class="modal-overlay" id="checkoutModal">
        <div class="modal">
            <div class="modal-header">
                <h2><i class="fas fa-credit-card"></i> Finalizar Compra</h2>
                <button class="close-btn" onclick="closeCheckout()" aria-label="Cerrar pago"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="checkout-steps">
                    <div class="step active"><span>1</span>Datos</div>
                    <div class="step"><span>2</span>Pago</div>
                    <div class="step"><span>3</span>Listo</div>
                </div>
                <div class="checkout-step" id="step1">
                    <h3>Información de Envío</h3>
                    <div class="form-group"><label>Nombre Completo *</label><input type="text" id="custName" placeholder="Tu nombre"></div>
                    <div class="form-group"><label>Correo Electrónico *</label><input type="email" id="custEmail" placeholder="tu@email.com"></div>
                    <div class="form-group"><label>Teléfono *</label><input type="tel" id="custPhone" placeholder="+34 607 35 80 33"></div>
                    <div class="form-group"><label>Dirección *</label><textarea id="custAddress" placeholder="Dirección completa"></textarea></div>
                    <div class="form-group"><label>Ciudad *</label><input type="text" id="custCity" placeholder="Tu ciudad"></div>
                    <div class="form-group"><label>Código Postal *</label><input type="text" id="custPostal" placeholder="Tu código postal"></div>
                    <button class="btn-primary full-btn" onclick="goToPayment()">Continuar al Pago <i class="fas fa-arrow-right"></i></button>
                </div>
                <div class="checkout-step" id="step2" style="display:none;">
                    <h3>Pago</h3>
                    <p style="color:#666;font-size:0.9rem;margin-bottom:15px;">Paga de forma segura con tu cuenta PayPal o con tarjeta de crédito/débito.</p>
                    <div id="paypal-button-container"></div>
                    <div style="text-align:center;color:#888;margin:14px 0;font-size:0.85rem;">— o —</div>
                    <button type="button" class="btn-primary full-btn" id="stripeCheckoutBtn"><i class="fas fa-credit-card"></i> Finalizar Compra</button>
                    <div class="order-summary">
                        <h4>Resumen del Pedido</h4>
                        <div id="summaryShippingBreakdown"></div>
                        <div class="summary-row"><span>Subtotal:</span><span id="summarySubtotal">€0,00</span></div>
                        <div class="summary-row"><span>Envío:</span><span id="summaryShipping">Gratis</span></div>
                        <div class="summary-row total"><span>Total:</span><span id="summaryTotal">€0,00</span></div>
                    </div>
                    <div style="margin-top:10px;">
                        <button class="btn-secondary full-btn" onclick="backToDetails()"><i class="fas fa-arrow-left"></i> Volver</button>
                    </div>
                </div>
                <div class="checkout-step" id="step3" style="display:none;">
                    <div class="success-message">
                        <div class="success-icon"><i class="fas fa-check-circle"></i></div>
                        <h2>¡Pedido Realizado!</h2>
                        <p>ID de Pedido: <strong id="orderID">#KW0000</strong></p>
                        <p>✅ Pago recibido con PayPal. Te contactaremos pronto para confirmar el envío.</p>
                        <button class="btn-primary" onclick="closeCheckout()">Seguir Comprando</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Aviso de Cookies RGPD -->
<div class="cookie-consent hidden" id="cookieConsent">
    <div class="cookie-content">
        <div class="cookie-icon"><i class="fas fa-cookie-bite"></i></div>
        <div class="cookie-text">
            <h4>Usamos Cookies</h4>
            <p>Usamos cookies de an&#225;lisis. Consulta nuestra <a href="politica-de-cookies.html" style="color:#ff6b35;">Pol&#237;tica de Cookies</a>.</p>
        </div>
        <div class="cookie-buttons">
            <button class="btn-cookie-accept" onclick="acceptCookies()">Aceptar</button>
            <button class="btn-cookie-decline" onclick="declineCookies()">Rechazar</button>
        </div>
    </div>
</div>

    <!-- Chatbot Widget -->
    <div class="chat-widget">
        <button class="chat-widget-button" id="chatWidgetToggle" aria-label="Abrir chat de Khurmi Store">
            <i class="fas fa-comments"></i>
        </button>
        <div class="chat-widget-panel" id="chatWidgetPanel" aria-hidden="true">
            <div class="chat-header">
                <div>
                    <div class="chat-title">Khurmi Store</div>
                    <div class="chat-subtitle">Asistente en línea</div>
                </div>
                <button class="chat-close-button" id="chatWidgetClose" aria-label="Cerrar chat">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="chat-body" id="chatWidgetBody"></div>
            <div class="chat-input-area">
                <form id="chatForm" class="chat-form">
                    <input type="text" id="chatMessageInput" placeholder="Escribe tu mensaje..." autocomplete="off">
                    <button type="submit" aria-label="Enviar mensaje"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>
            <a class="chat-whatsapp-link" href="https://wa.me/34662241860" target="_blank" rel="noopener noreferrer">Hablar por WhatsApp</a>
        </div>
    </div>
    <script src="https://www.paypal.com/sdk/js?client-id=AcSRbzZiZ0p7nBGqQjPKfcNB6RgdqvJlWnCrSNgtjN9B9_HZwjeHMNfzizsstCGjdWomaNtsBJo0vAEn&amp;currency=EUR&amp;locale=es_ES" defer></script>
    <script src="script.js" defer></script>
    <script>
    // Seed script.js's global `products` array with this category's real Supabase
    // products, so "Añadir al carrito" (if used elsewhere) and the cart drawer can
    // find them by real id. Does not call renderProducts()/renderProductDetails() —
    // the grid above is already server-rendered.
    document.addEventListener('DOMContentLoaded', function () {
        var realProducts = <?= json_encode(array_map(function ($p) {
            return [
                'id'       => (int)$p['id'],
                'name'     => $p['name'],
                'category' => $p['category'] ?? '',
                'price'    => (float)$p['price'],
                'weight'   => isset($p['weight']) && $p['weight'] !== null ? (float)$p['weight'] : null,
                'image'    => first_product_image($p['image_url'] ?? ''),
                'tag'      => '',
                'rating'   => 5,
            ];
        }, $products), JSON_UNESCAPED_UNICODE) ?>;
        if (realProducts.length > 0) {
            products.length = 0;
            Array.prototype.push.apply(products, realProducts);
        }
    });
    </script>

    <a href="https://wa.me/34662241860?text=Hola,%20tengo%20una%20pregunta%20sobre%20un%20producto" class="whatsapp-float" target="_blank" rel="noopener noreferrer" aria-label="Chat de WhatsApp">
        <i class="fab fa-whatsapp"></i>
        <span class="whatsapp-tooltip">¿Necesitas ayuda?</span>
    </a>
</body>
</html>
