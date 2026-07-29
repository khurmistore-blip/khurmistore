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
$categoryTree = require __DIR__ . '/categories_config.php';

$cat  = isset($_GET['cat']) ? trim($_GET['cat']) : '';
$sub  = isset($_GET['sub']) ? trim($_GET['sub']) : '';
$sub2 = isset($_GET['sub2']) ? trim($_GET['sub2']) : '';
// sub/sub2 are meaningless without their parent — an orphaned ?sub2= with no
// ?sub= (or ?sub= with no ?cat=) is treated as if it weren't passed at all.
if ($cat === '') { $sub = ''; }
if ($sub === '') { $sub2 = ''; }

// SMART-WATCH-ONLY PIVOT (2026-07-28): any ?cat= slug no longer present in
// categories_config.php's tree (belleza/electronica/auriculares/
// accesorios-movil — retired, product rows hidden in the DB but the old
// URLs are still out there in search results/bookmarks/backlinks) gets a
// permanent redirect to the one category the store still sells, instead of
// rendering a 200 OK page with zero products for Google to crawl as thin
// content. Must run before any output (nothing is echoed above this).
if ($cat !== '') {
    $catStillExists = false;
    foreach ($categoryTree as $node) {
        if ($node['slug'] === $cat) { $catStillExists = true; break; }
    }
    if (!$catStillExists) {
        header('Location: /categoria.php?cat=relojes', true, 301);
        exit;
    }
}

// Excludes refurbished/"Reacondicionado" listings — a reviewer flagged one as
// looking out of place; hidden from display only, never deleted from the DB.
$query = 'products?is_active=is.true&approval_status=eq.approved&name=not.ilike.*Reacondicionado*&order=created_at.desc';
if ($cat !== '') {
    $query .= '&category=eq.' . rawurlencode($cat);
}
if ($sub !== '') {
    $query .= '&subcategoria=eq.' . rawurlencode($sub);
}
if ($sub2 !== '') {
    $query .= '&sub_subcategoria=eq.' . rawurlencode($sub2);
}
$products = sb_get($cfg, $query);

// Resolves sub/sub2 display names from the same tree the header nav renders
// from — an unknown/stale slug just yields '', which every use below treats
// as "not set" rather than erroring.
$catPath   = find_category_path($categoryTree, $cat, $sub, $sub2);
$subLabel  = $catPath[1]['name'] ?? '';
$sub2Label = $catPath[2]['name'] ?? '';

// Per-category SEO config: display label, <title>, meta description
// (120-155 chars), and a short keyword-rich intro paragraph shown under the
// H1. Keeps categoria.php's existing single-file-covers-every-category
// approach — this dict is the only thing that grows as categories change.
// SMART-WATCH-ONLY PIVOT (2026-07-28): auriculares/accesorios-movil/belleza/
// electronica entries removed — those ?cat= values now redirect above
// before this array is ever consulted, so keeping their config here would
// just be unreachable dead weight.
$categoryConfig = [
    'relojes' => [
        'label'       => 'Relojes',
        'title'       => 'Relojes Inteligentes | Comprar Online | KhurmiStore',
        'description' => 'Compra relojes inteligentes online en España. Diseño, salud y tecnología en tu muñeca, con envío rápido y pago 100% seguro.',
        'intro'       => 'Nuestra colección de relojes inteligentes combina diseño, salud y tecnología en tu muñeca: monitoriza tu actividad, recibe notificaciones y mucho más. Encuentra el modelo perfecto y recíbelo rápido en cualquier punto de España.',
    ],
];

// Fallback used both when ?cat= is missing (the "todos los productos" view)
// and when it's set to an unrecognized/old slug — never an empty title,
// description, or H1.
$defaultCatConfig = [
    'label'       => 'Todos los Productos',
    'title'       => 'Tienda Online | Relojes Inteligentes | KhurmiStore',
    'description' => 'Descubre toda la tienda de KhurmiStore: relojes inteligentes con envío rápido a toda España y pago 100% seguro.',
    'intro'       => 'Bienvenido a la tienda de KhurmiStore, especialistas en relojes inteligentes. Compra con confianza: envío rápido a toda España y pago 100% seguro.',
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

// Most specific known level (sub2 -> sub -> category) drives the H1 and
// <title> — falls back up the chain so an unrecognized sub/sub2 slug still
// shows a sensible heading instead of a blank one.
$displayLabel = $sub2Label !== '' ? $sub2Label : ($subLabel !== '' ? $subLabel : $catLabel);

// Slug -> label map for the product cards' category tag, derived from the
// same config above (previously a separate, incomplete list missing
// "electronica" entirely).
$categoryLabels = array_combine(array_keys($categoryConfig), array_column($categoryConfig, 'label'));

// <title>: cap at ~60 chars (Google truncates longer titles), same
// truncate-with-ellipsis approach used on producto.php. When a sub/sub2 is
// selected, lead with its name so it's the part Google actually shows.
$pageTitle = $displayLabel !== $catLabel
    ? $displayLabel . ' | ' . $catLabel . ' | KhurmiStore'
    : $catConfig['title'];
if (mb_strlen($pageTitle) > 60) {
    $pageTitle = rtrim(mb_substr($pageTitle, 0, 59)) . '…';
}

$canonicalUrl = 'https://khurmistore.es/categoria.php';
if ($cat !== '') {
    $canonicalUrl .= '?cat=' . rawurlencode($cat);
    if ($sub !== '') {
        $canonicalUrl .= '&sub=' . rawurlencode($sub);
        if ($sub2 !== '') {
            $canonicalUrl .= '&sub2=' . rawurlencode($sub2);
        }
    }
}

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
$breadcrumbItems = [
    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Inicio', 'item' => 'https://khurmistore.es/'],
    ['@type' => 'ListItem', 'position' => 2, 'name' => $catLabel, 'item' => 'https://khurmistore.es/categoria.php' . ($cat !== '' ? '?cat=' . rawurlencode($cat) : '')],
];
$breadcrumbPosition = 3;
if ($subLabel !== '') {
    $breadcrumbItems[] = [
        '@type'    => 'ListItem',
        'position' => $breadcrumbPosition++,
        'name'     => $subLabel,
        'item'     => 'https://khurmistore.es/categoria.php?cat=' . rawurlencode($cat) . '&sub=' . rawurlencode($sub),
    ];
}
if ($sub2Label !== '') {
    // Sub2 is always the deepest selected level, so its breadcrumb URL is
    // exactly the canonical URL already built above.
    $breadcrumbItems[] = ['@type' => 'ListItem', 'position' => $breadcrumbPosition++, 'name' => $sub2Label, 'item' => $canonicalUrl];
}
$breadcrumbSchema = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => $breadcrumbItems,
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
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="stylesheet" href="/style.min.css">
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
                    <li><a href="/index.php">Inicio</a></li>
                    <li><a href="/categoria.php">Tienda</a></li>

                    <!-- Categorías: each main category is its own independent nav item, rendered by initCategoryMenus() in script.js -->
                    <li id="categoryNavSlot"></li>

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
                    <li><a href="/index.php" onclick="closeMobileMenu()">Inicio</a></li>
                    <li><a href="/categoria.php" onclick="closeMobileMenu()">Tienda</a></li>

                    <!-- Categorías: each main category is its own independent accordion row, rendered by initCategoryMenus() in script.js -->
                    <li id="mobileCategoryNavSlot"></li>

                </ul>
            </nav>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div class="wrap" style="max-width:1180px;margin:0 auto;padding:18px 20px;color:#9AA0BC;font-size:13px;">
        <a href="/index.php" style="color:inherit;text-decoration:none;">Inicio</a>
        <i class="fas fa-chevron-right" style="font-size:10px;margin:0 6px;"></i>
        <?php if ($subLabel === ''): ?>
            <span><?= htmlspecialchars($catLabel) ?></span>
        <?php else: ?>
            <a href="/categoria.php?cat=<?= rawurlencode($cat) ?>" style="color:inherit;text-decoration:none;"><?= htmlspecialchars($catLabel) ?></a>
            <i class="fas fa-chevron-right" style="font-size:10px;margin:0 6px;"></i>
            <?php if ($sub2Label === ''): ?>
                <span><?= htmlspecialchars($subLabel) ?></span>
            <?php else: ?>
                <a href="/categoria.php?cat=<?= rawurlencode($cat) ?>&amp;sub=<?= rawurlencode($sub) ?>" style="color:inherit;text-decoration:none;"><?= htmlspecialchars($subLabel) ?></a>
                <i class="fas fa-chevron-right" style="font-size:10px;margin:0 6px;"></i>
                <span><?= htmlspecialchars($sub2Label) ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Productos de la Categoría -->
    <section class="products" id="products">
        <div class="section-header">
            <span class="subtitle"><?php
                if ($sub2Label !== '') { echo 'SUB-SUBCATEGORÍA'; }
                elseif ($subLabel !== '') { echo 'SUBCATEGORÍA'; }
                elseif ($cat !== '') { echo 'CATEGORÍA'; }
                else { echo 'CATÁLOGO COMPLETO'; }
            ?></span>
            <h1><?= htmlspecialchars($displayLabel) ?></h1>
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
                <br><a href="/categoria.php" class="btn-primary" style="display:inline-block;margin-top:16px;">Ver todos los productos</a>
            </div>
        <?php else: ?>
            <div class="products-grid" id="categoryProductsGrid">
                <?php foreach ($products as $p): ?>
                    <div class="product-card" onclick="window.location.href='/producto.php?id=<?= (int)$p['id'] ?>'">
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
        <div class="feature-box"><i class="fas fa-truck-fast"></i><h3>Envío Gratis</h3><p>Sin mínimo, 2-5 días laborables</p></div>
        <div class="feature-box"><i class="fas fa-shield-halved"></i><h3>Pago Seguro</h3><p>100% Protegido</p></div>
        <div class="feature-box"><i class="fas fa-rotate-left"></i><h3>Devolución Fácil</h3><p>Política de 14 días</p></div>
        <div class="feature-box"><i class="fas fa-headset"></i><h3>Soporte 24/7</h3><p>Siempre disponibles</p></div>
    </section>

    <!-- Pie de Página -->
    <footer class="footer" id="contact">
        <div class="footer-content">
            <div class="footer-col">
                <div class="logo"><i class="fas fa-wave-square"></i><div class="logo-text"><span class="letter">K</span><span class="letter">h</span><span class="letter">u</span><span class="letter">r</span><span class="letter">m</span><span class="letter">i</span><span class="letter">S</span><span class="letter">t</span><span class="letter">o</span><span class="letter">r</span><span class="letter">e</span></div></div>
                <p>Tu tienda online de relojes inteligentes en España.</p>
                <p class="footer-legal-info">KhurmiStore &mdash; Calle Doctor Bellido, 46, Bajo, 28018 Madrid, España<br>Titular: Khuram Shahzad &middot; NIE: Y5243613H<br>Tel: +34 662 24 18 60 &middot; info@khurmistore.es</p>
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
                    <li><a href="/index.php">Inicio</a></li>
                    <li><a href="/categoria.php">Tienda</a></li>
                    <li><a href="/sobre-nosotros.html">Sobre Nosotros</a></li>
                    <li><a href="/contacto.html">Contacto</a></li>
                    <li><a href="/preguntas-frecuentes.html">Preguntas Frecuentes</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Relojes</h3>
                <ul>
                    <li><a href="/categoria.php?cat=relojes&amp;sub=analogicos">Analógicos</a></li>
                    <li><a href="/categoria.php?cat=relojes&amp;sub=digitales">Digitales / Smartwatch</a></li>
                    <li><a href="/categoria.php?cat=relojes">Todos los relojes</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Legal</h3>
                <ul>
                    <li><a href="/aviso-legal.html">Aviso Legal</a></li>
                    <li><a href="/politica-de-privacidad.html">Política de Privacidad</a></li>
                    <li><a href="/politica-de-cookies.html">Política de Cookies</a></li>
                    <li><a href="/terminos-y-condiciones.html">Términos y Condiciones</a></li>
                    <li><a href="/politica-de-envios.html">Política de Envíos</a></li>
                    <li><a href="/politica-de-devoluciones.html">Política de Devoluciones</a></li>
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
            <p class="footer-copyright">&copy; 2026 KhurmiStore España. Todos los derechos reservados.</p>
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
                    <div class="order-summary">
                        <h4>Resumen del Pedido</h4>
                        <div id="summaryShippingBreakdown"></div>
                        <div class="summary-row"><span>Subtotal:</span><span id="summarySubtotal">€0,00</span></div>
                        <div class="summary-row"><span>Envío:</span><span id="summaryShipping">Gratis</span></div>
                        <div class="summary-row total"><span>Total:</span><span id="summaryTotal">€0,00</span></div>
                    </div>
                    <button type="button" class="btn-primary full-btn" id="stripeCheckoutBtn"><i class="fas fa-credit-card"></i> Pagar con Tarjeta</button>
                    <div class="payment-divider"><span>— o paga con PayPal —</span></div>
                    <div id="paypal-button-container"></div>
                    <p class="payment-trust-line"><i class="fas fa-lock"></i> Pago 100% seguro · Cifrado SSL</p>
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
            <p>Usamos cookies de an&#225;lisis. Consulta nuestra <a href="/politica-de-cookies.html" style="color:#ff6b35;">Pol&#237;tica de Cookies</a>.</p>
        </div>
        <div class="cookie-buttons">
            <button class="btn-cookie-accept" onclick="acceptCookies()">Aceptar</button>
            <button class="btn-cookie-decline" onclick="declineCookies()">Rechazar</button>
        </div>
    </div>
</div>

    <!-- Chatbot Widget -->
    <div class="chat-widget">
        <button class="chat-widget-button" id="chatWidgetToggle" aria-label="Abrir chat de KhurmiStore">
            <i class="fas fa-comments"></i>
        </button>
        <div class="chat-widget-panel" id="chatWidgetPanel" aria-hidden="true">
            <div class="chat-header">
                <div>
                    <div class="chat-title">KhurmiStore</div>
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
    <script src="/script.js" defer></script>
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
