<?php
/**
 * producto.php — Single product detail page (live from Supabase).
 * URL: producto.php?id=5
 * -------------------------------------------------------------
 * Uses the SAME design/markup as the old product-details.html page.
 * That page's gallery, "Añadir al carrito", "Comprar Ahora", PayPal
 * checkout and payment methods are all rendered client-side by
 * renderProductDetails() in script.js, reading from a global `products`
 * array. Instead of touching script.js, we seed that array with the
 * real Supabase product (see the <script> block near the bottom) and
 * re-run renderProductDetails() — same approach already used on index.php.
 */

require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/shipping_lib.php';
$cfg = require __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rows = $id ? sb_get($cfg, "products?id=eq.$id&status=eq.active&limit=1") : [];
$p = $rows[0] ?? null;

if (!$p) {
    http_response_code(404);
}

// A few more active products (excluding this one) so the "Productos Relacionados" grid has real data too.
// Excludes refurbished/"Reacondicionado" listings — see categoria.php/index.php for the same filter.
$related = $p ? sb_get($cfg, "products?status=eq.active&id=neq.$id&name=not.ilike.*Reacondicionado*&limit=5") : [];

$pageTitle = $p ? ($p['seo_title'] ?: $p['name']) : 'Producto no encontrado';

// <title>: "<name> | KhurmiStore", truncated so the total stays ~60 chars
// (Google truncates longer titles in search results).
$titleSuffix   = ' | KhurmiStore';
$maxTitleChars = 60;
$titleName     = $pageTitle;
if (mb_strlen($titleName . $titleSuffix) > $maxTitleChars) {
    $available = max(10, $maxTitleChars - mb_strlen($titleSuffix) - 1); // -1 for the ellipsis
    $titleName = rtrim(mb_substr($titleName, 0, $available)) . '…';
}
$fullPageTitle = $titleName . $titleSuffix;

/**
 * Builds a 120-155 char Spanish meta description from real product data
 * (HTML-stripped), padded with a relevant generic sentence when the
 * product's own description is too short/empty so it's never under ~120
 * chars, and hard-capped at ~155 since Google truncates search snippets
 * around there.
 */
function build_product_meta_description(?array $p): string
{
    if (!$p) {
        return 'El producto que buscas no está disponible en KhurmiStore. Descubre miles de productos de belleza, tecnología y electrónica con envío rápido a España.';
    }

    $name      = (string)($p['name'] ?? 'este producto');
    $descPlain = trim(strip_tags((string)($p['description'] ?? '')));

    $snippet = '';
    if ($descPlain !== '') {
        $snippet = mb_substr($descPlain, 0, 100);
        if (mb_strlen($descPlain) > 100) {
            $lastSpace = mb_strrpos($snippet, ' ');
            if ($lastSpace !== false && $lastSpace > 40) {
                $snippet = mb_substr($snippet, 0, $lastSpace);
            }
        }
        $snippet = rtrim($snippet, " .,;:") . '.';
    }

    $desc = "Compra {$name} al mejor precio" . ($snippet !== '' ? ". {$snippet}" : ' en KhurmiStore.')
        . ' Envío rápido a España. ¡Pídelo ya en KhurmiStore!';

    // Guarantee a minimum length (~120 chars) when the real description was
    // too short to reach it on its own.
    if (mb_strlen($desc) < 120) {
        $desc = "Compra {$name} al mejor precio en KhurmiStore." . ($snippet !== '' ? " {$snippet}" : '')
            . ' Envío rápido a toda España, pago 100% seguro y atención en español. ¡Pídelo ya!';
    }

    if (mb_strlen($desc) > 155) {
        $desc = mb_substr($desc, 0, 152);
        $lastSpace = mb_strrpos($desc, ' ');
        if ($lastSpace !== false && $lastSpace > 100) {
            $desc = mb_substr($desc, 0, $lastSpace);
        }
        $desc = rtrim($desc, " .,;:") . '...';
    }

    return $desc;
}

// Respect a manually-curated seo_description if the store owner set one and
// it already meets the length target; otherwise build one from real data.
$pageDesc = ($p && !empty($p['seo_description']) && mb_strlen(trim($p['seo_description'])) >= 120)
    ? $p['seo_description']
    : build_product_meta_description($p);

$canonicalUrl = $p ? "https://khurmistore.es/producto.php?id={$p['id']}" : 'https://khurmistore.es/producto.php';

// image_url stores multiple images comma-separated; social-share crawlers need
// a single real HTTP image (not the SVG placeholder used for card thumbnails),
// so fall back to the site's default og-image.jpg instead when there's none.
$socialImage = $p ? trim(explode(',', $p['image_url'] ?? '')[0] ?? '') : '';
if ($socialImage === '') {
    $socialImage = 'https://khurmistore.es/og-image.jpg';
}

// Product JSON-LD (schema.org) for rich results — uses the same data already
// fetched above, no extra Supabase query.
$productSchema = null;
if ($p) {
    $priceValue = number_format((float)$p['price'], 2, '.', '');
    $productSchema = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        'name'        => $p['name'],
        'description' => $pageDesc,
        'image'       => $socialImage,
        'sku'         => (string)$p['id'],
        'brand'       => [
            '@type' => 'Brand',
            'name'  => 'KhurmiStore',
        ],
        'offers' => [
            '@type'         => 'Offer',
            'url'           => $canonicalUrl,
            'priceCurrency' => 'EUR',
            'price'         => $priceValue,
            'availability'  => ((int)($p['stock'] ?? 0) > 0)
                ? 'https://schema.org/InStock'
                : 'https://schema.org/OutOfStock',
        ],
    ];
}

// Map a Supabase product row to the shape script.js's getProductDetails()/renderProductDetails() expect.
function bb_product_to_js(array $p): array
{
    // image_url stores multiple images comma-separated (e.g. "url1,url2,url3");
    // split into a real gallery array instead of repeating a single image.
    $gallery = array_values(array_filter(array_map('trim', explode(',', $p['image_url'] ?? ''))));
    if (empty($gallery)) {
        $gallery = [''];
    }
    $image = $gallery[0];
    $features = [];
    if (!empty($p['bullet_points'])) {
        $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $p['bullet_points']))));
    }
    return [
        'id'          => (int)$p['id'],
        'name'        => $p['name'],
        'category'    => $p['category'] ?? '',
        'price'       => (float)$p['price'],
        'weight'      => isset($p['weight']) && $p['weight'] !== null ? (float)$p['weight'] : null,
        'shippingCost' => calcShipping(isset($p['weight']) && $p['weight'] !== null ? (float)$p['weight'] : null),
        'image'       => $image,
        'gallery'     => $gallery,
        'videoId'     => !empty($p['video_id']) ? trim((string)$p['video_id']) : null,
        'tag'         => '',
        'stock'       => (int)($p['stock'] ?? 0),
        'isActive'    => ($p['is_active'] ?? true) !== false,
        'description' => $p['long_description'] ?: ($p['description'] ?? ''),
        'features'    => $features ?: null, // null lets getProductDetails() fall back to its generic defaults
    ];
}
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
    <title><?= htmlspecialchars($fullPageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>">
    <?php if ($p && !empty($p['seo_keywords'])): ?><meta name="keywords" content="<?= htmlspecialchars($p['seo_keywords']) ?>"><?php endif; ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($fullPageTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($socialImage) ?>">
    <meta property="og:type" content="product">
    <meta property="og:title" content="<?= htmlspecialchars($fullPageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($socialImage) ?>">
    <meta property="og:locale" content="es_ES">
    <?php if ($p): ?>
    <meta property="og:price:amount" content="<?= htmlspecialchars(number_format((float)$p['price'], 2, '.', '')) ?>">
    <meta property="og:price:currency" content="EUR">
    <?php endif; ?>
    <?php if ($productSchema): ?>
    <script type="application/ld+json"><?= json_encode($productSchema, JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>
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
    <style>
    /* BigBuy video thumbnail + player in the product gallery (producto.php only) */
    .thumb-video{position:relative}
    .thumb-video .thumb-play-icon{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(10,14,39,.35);color:#fff;font-size:20px;pointer-events:none}
    .main-image.main-video-wrap iframe{width:100%;height:100%;border:0;display:block}
    .watch-video-btn{position:absolute;left:16px;bottom:16px;z-index:2;display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border:none;border-radius:999px;background:rgba(10,14,39,.72);color:#fff;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;transition:background .2s}
    .watch-video-btn:hover{background:rgba(10,14,39,.88)}
    .watch-video-btn i{color:var(--primary)}
    @media (max-width:480px){.watch-video-btn{left:10px;bottom:10px;padding:8px 14px;font-size:13px}}
    </style>

<script src="/js/meta-pixel.js" defer></script>
<body>
<!-- Barra de Anuncios -->
<div class="announcement-bar" id="announcementBar">
    <div class="announcement-content">
        <span class="scroll-text">
            <span class="ab-item">🚚 Envío GRATIS a toda España</span>
            <span class="ab-dot ab-mobile-hide">·</span>
            <span class="ab-item ab-mobile-hide">📍 Enviado desde España</span>
            <span class="ab-dot">·</span>
            <span class="ab-item">🔒 Pago Seguro</span>
            <span class="ab-dot ab-mobile-hide">·</span>
            <span class="ab-item ab-mobile-hide">↩️ 14 días para devoluciones</span>
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

    <!-- Contenedor de Detalles del Producto -->
    <main class="product-details-page">
        <!-- Server-rendered H1 with the real product name so crawlers that
             don't execute JS still see unique, keyword-relevant content
             immediately. renderProductDetails() below replaces this whole
             container's innerHTML (including its own <h1>) once it runs, so
             the visible page always ends up with exactly one <h1>. -->
        <div id="productDetailsContainer"><h1><?= htmlspecialchars($p ? $p['name'] : 'Producto no encontrado') ?></h1></div>
    </main>

    <!-- Pie de Página -->
    <footer class="footer" id="contact">
        <div class="footer-content">
            <div class="footer-col">
                <div class="logo"><i class="fas fa-wave-square"></i><div class="logo-text"><span class="letter">K</span><span class="letter">h</span><span class="letter">u</span><span class="letter">r</span><span class="letter">m</span><span class="letter">i</span><span class="letter">S</span><span class="letter">t</span><span class="letter">o</span><span class="letter">r</span><span class="letter">e</span></div></div>
                <p>Tu tienda online de belleza, accesorios para móvil, relojes y electrónica en España.</p>
                <p class="footer-legal-info">Khurmi Store &mdash; Calle Doctor Bellido, 46, Bajo, 28018 Madrid, España<br>NIF: Y5243613H &middot; Tel: +34 662 24 18 60 &middot; info@khurmistore.es</p>
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
                <p><i class="fas fa-map-marker-alt"></i> Calle Doctor Bellido, 46, Bajo, 28018 Madrid, España</p>
                <p><i class="fas fa-phone"></i> +34 662 24 18 60</p>
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
                    <div class="form-group"><label>Teléfono *</label><input type="tel" id="custPhone" placeholder="+34 662 24 18 60"></div>
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
    // Seed script.js's global `products` array with the real Supabase product(s)
    // for this page, then re-run renderProductDetails() so the exact same
    // gallery/cart/PayPal template renders with live data. DOMContentLoaded
    // always fires after deferred scripts, so this runs after script.js's own
    // initial (dummy-data) render.
    document.addEventListener('DOMContentLoaded', function () {
        <?php if ($p): ?>
        var mainProduct = <?= json_encode(bb_product_to_js($p), JSON_UNESCAPED_UNICODE) ?>;
        var relatedProducts = <?= json_encode(array_map('bb_product_to_js', $related), JSON_UNESCAPED_UNICODE) ?>;
        products.length = 0;
        products.push(mainProduct);
        Array.prototype.push.apply(products, relatedProducts);
        <?php else: ?>
        // No matching product in Supabase: clear demo data so script.js shows its real "not found" state
        // instead of a random dummy product that happens to share this id.
        products.length = 0;
        <?php endif; ?>
        renderProductDetails();
    });
    </script>

    <a href="https://wa.me/34662241860?text=Hola,%20tengo%20una%20pregunta%20sobre%20un%20producto" class="whatsapp-float" target="_blank" rel="noopener noreferrer" aria-label="Chat de WhatsApp">
        <i class="fab fa-whatsapp"></i>
        <span class="whatsapp-tooltip">¿Necesitas ayuda?</span>
    </a>
</body>
</html>
