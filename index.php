<?php
// Fetch featured products for the "Destacados" homepage grid (real Supabase data).
// Excludes refurbished/"Reacondicionado" listings — a reviewer flagged one as
// looking out of place; hidden from display only, never deleted from the DB.
require_once __DIR__ . '/supabase.php';
$cfg = require __DIR__ . '/config.php';
$featured = sb_get($cfg, 'products?status=eq.active&name=not.ilike.*Reacondicionado*&order=created_at.desc&limit=8');

// Per-category homepage sliders — one row per header "Categorías" dropdown entry.
// Slugs/labels match categoria.php's $categoryLabels so "Ver Todo" lands on the same page.
$categorySliderDefs = [
    ['slug' => 'auriculares',        'label' => 'Auriculares y Audio'],
    ['slug' => 'relojes',            'label' => 'Relojes'],
    ['slug' => 'accesorios-movil', 'label' => 'Accesorios para Móvil'],
    ['slug' => 'belleza',            'label' => 'Belleza'],
    ['slug' => 'electronica',        'label' => 'Electrónica'],
];
$categorySliders = [];
foreach ($categorySliderDefs as $def) {
    $catProducts = sb_get($cfg, 'products?status=eq.active&stock=gt.0&name=not.ilike.*Reacondicionado*&category=eq.' . rawurlencode($def['slug']) . '&order=created_at.desc');
    if (!empty($catProducts)) {
        $categorySliders[] = ['slug' => $def['slug'], 'label' => $def['label'], 'products' => $catProducts];
    }
}

// Hero slider — 3 PINNED real products (not a random/latest category pick,
// which is what kept breaking: a category query could land on a product
// whose image_url was dead). Each slide is now tied to a specific product
// id, using that exact row's image_url — the SAME field/value categoria.php
// already renders successfully — so the image is confirmed working, not
// guessed at.

/** First image from a product's comma-separated image_url column. */
function hero_product_image(?array $p): string
{
    if (!$p) {
        return '';
    }
    return trim(explode(',', $p['image_url'] ?? '')[0] ?? '');
}

/**
 * Finds ONE active, photographed, in-stock product in $category whose name
 * matches $nameLike (case-insensitive substring) — used to prefer a
 * specific well-known product (e.g. "L'Occitane", "Xiaomi") without hard-
 * coding its id. Falls back to any active photographed product in that
 * category if no name match exists, so the slide is never empty.
 */
function hero_find_product(array $cfg, string $category, string $nameLike = ''): ?array
{
    $base = 'products?status=eq.active&image_url=not.is.null&stock=gt.0'
        . '&name=not.ilike.*Reacondicionado*&category=eq.' . rawurlencode($category)
        . '&order=created_at.desc&limit=1';

    if ($nameLike !== '') {
        $rows = sb_get($cfg, $base . '&name=ilike.*' . rawurlencode($nameLike) . '*');
        if (!empty($rows)) {
            return $rows[0];
        }
    }

    $rows = sb_get($cfg, $base);
    return $rows[0] ?? null;
}

// Slide 1 — pinned to a specific product: id=179, Hidrolimpiador Facial Hyser.
$heroP1Rows = sb_get($cfg, 'products?id=eq.179&status=eq.active&limit=1');
$heroP1     = $heroP1Rows[0] ?? null;

// Slide 2 — belleza, prefer a named perfume/L'Occitane product, else any
// active photographed belleza product.
$heroP2 = hero_find_product($cfg, 'belleza', "Occitane")
    ?? hero_find_product($cfg, 'belleza', 'Perfume')
    ?? hero_find_product($cfg, 'belleza');

// Slide 3 — electronica, prefer the Xiaomi router or a Startech cable, else
// any active photographed electronica product.
$heroP3 = hero_find_product($cfg, 'electronica', 'Xiaomi')
    ?? hero_find_product($cfg, 'electronica', 'Startech')
    ?? hero_find_product($cfg, 'electronica');

$heroSlide1Image = hero_product_image($heroP1);
$heroSlide1Id    = $heroP1['id'] ?? null;
$heroSlide2Image = hero_product_image($heroP2);
$heroSlide2Id    = $heroP2['id'] ?? null;
$heroSlide3Image = hero_product_image($heroP3);
$heroSlide3Id    = $heroP3['id'] ?? null;

// Renders one .product-card — same markup as the "Destacados" grid cards — for the
// category sliders below. Kept as a helper since each slider repeats its card set
// twice (real row + duplicate row) to create a seamless infinite-scroll loop.
function render_category_slider_card(array $p, array $cfg): string
{
    ob_start();
    ?>
    <div class="product-card category-slider-card" onclick="window.location.href='producto.php?id=<?= (int)$p['id'] ?>'">
        <a href="producto.php?id=<?= (int)$p['id'] ?>" class="product-img" style="display:block">
            <img src="<?= htmlspecialchars(first_product_image($p['image_url'] ?? '')) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
            <div class="quick-view"><i class="fas fa-eye"></i> Ver Detalles</div>
        </a>
        <div class="product-info">
            <a href="producto.php?id=<?= (int)$p['id'] ?>" style="color:inherit;text-decoration:none;display:block"><h3><?= htmlspecialchars($p['name']) ?></h3></a>
            <span class="badge-nuevo">Nuevo</span>
            <div class="product-price">
                <span class="price"><?= price_es((float)$p['price'], $cfg['currency_symbol']) ?></span>
                <button type="button" class="add-cart" onclick="event.stopPropagation(); addToCart(<?= (int)$p['id'] ?>)" aria-label="Añadir <?= htmlspecialchars($p['name']) ?> al carrito">
                    <i class="fas fa-cart-plus"></i> Añadir al carrito
                </button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="es-ES">
<head>
    <!-- Core Meta Tags -->
    <meta charset="UTF-8">
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-CTNSGTD2JS"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-CTNSGTD2JS');
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="p:domain_verify" content="decfe186d976a1afeea0c9540eca6cec"/>
    <?php if ($heroSlide1Image !== ''): ?>
    <link rel="preload" as="image" href="<?= htmlspecialchars($heroSlide1Image) ?>" fetchpriority="high">
    <?php endif; ?>
    
    <!-- SEO Meta Tags -->
    <title>KhurmiStore | Belleza, Accesorios Móvil y Electrónica</title>
    <meta name="description" content="Tienda online de productos de belleza, accesorios para móvil, relojes y electrónica. Envío rápido a toda España y pago seguro. ¡Descubre nuestras ofertas!">
    <meta name="keywords" content="belleza, accesorios para móvil, relojes inteligentes, auriculares inalámbricos, electrónica, fundas para móvil, tecnología premium España">
    <meta name="theme-color" content="#ff6b35">
    <meta name="author" content="KhurmiStore">
    <link rel="canonical" href="https://khurmistore.es/">
    
    <!-- Open Graph Tags -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="KhurmiStore | Belleza, Accesorios Móvil y Electrónica">
    <meta property="og:description" content="Tienda online de productos de belleza, accesorios para móvil, relojes y electrónica. Envío rápido a toda España y pago seguro. ¡Descubre nuestras ofertas!">
    <meta property="og:url" content="https://khurmistore.es/">
    <meta property="og:image" content="https://khurmistore.es/og-image.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630"> 
    <meta property="og:locale" content="es_ES">
    
    <!-- Twitter Card Tags -->  
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="KhurmiStore | Belleza, Accesorios Móvil y Electrónica">
    <meta name="twitter:description" content="Tienda online de productos de belleza, accesorios para móvil, relojes y electrónica. Envío rápido a toda España y pago seguro. ¡Descubre nuestras ofertas!">
    <meta name="twitter:image" content="https://khurmistore.es/og-image.jpg">
    
    <!-- Robots Meta -->
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="googlebot" content="index, follow">
    
    <!-- Additional SEO -->
    <meta name="language" content="Spanish">
    <meta name="revisit-after" content="7 days">
    
    <!-- CSS and Fonts -->
    <link rel="stylesheet" href="style.min.css">
    <!-- Inline CSS for the homepage's per-category product sliders (Change 3).
         Reuses existing design tokens (--primary, --gradient2, .product-card, .slider-btn)
         so it stays visually consistent with the rest of the site. -->
    <style>
        .category-slider-section{padding:60px 30px 0;max-width:1400px;margin:0 auto}
        .category-slider-header{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;flex-wrap:wrap;text-align:left;margin-bottom:30px}
        .category-slider-header .subtitle{display:block}
        .category-slider-viewall{color:var(--primary);font-weight:600;text-decoration:none;white-space:nowrap;transition:0.3s}
        .category-slider-viewall:hover{opacity:0.8}
        .category-slider-wrap{position:relative}
        .category-slider-track{display:flex;flex-wrap:nowrap;gap:24px;overflow-x:auto;overflow-y:hidden;scrollbar-width:none;-ms-overflow-style:none;-webkit-overflow-scrolling:touch}
        .category-slider-track::-webkit-scrollbar{display:none}
        .category-slider-row{display:flex;flex-wrap:nowrap;flex:0 0 auto;gap:24px;width:max-content}
        .category-slider-card{flex:0 0 260px;width:260px}
        .category-slider-wrap .slider-btn{position:absolute;top:40%}
        .category-slider-wrap .slider-btn.prev{left:-6px}
        .category-slider-wrap .slider-btn.next{right:-6px}
        @media (max-width: 768px) {
            .category-slider-section{padding:40px 16px 0}
            .category-slider-card{flex-basis:200px;width:200px}
            .category-slider-wrap .slider-btn{width:38px;height:38px;font-size:14px}
            .category-slider-wrap .slider-btn.prev{left:2px}
            .category-slider-wrap .slider-btn.next{right:2px}
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=optional">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=optional" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
    <noscript>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=optional" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </noscript>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='75' font-size='75' fill='%23ff6b35'>K</text></svg>">
    
    <!-- Preload Critical Resources -->
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <!-- ============================================================
     ORGANIZATION SCHEMA (JSON-LD) — for GEO / SEO
     WHERE TO PASTE: in index.html, just before the closing  tag.
     This is invisible on the page — only Google & AI engines read it.
     REPLACE the items marked with  <<< REPLACE  below.
     ============================================================ -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Khurmi Store",
  "url": "https://khurmistore.es",
  "logo": "https://khurmistore.es/images/logo.png",
  "description": "Tienda online multicategoría en España: belleza, accesorios para móvil, relojes inteligentes, electrónica y mucho más.",
  "email": "info@khurmistore.es",
  "contactPoint": {
    "@type": "ContactPoint",
    "telephone": "+34662241860",
    "contactType": "customer service",
    "areaServed": "ES",
    "availableLanguage": ["Spanish"]
  },
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Calle Doctor Bellido, 46, Bajo",
    "addressLocality": "Madrid",
    "addressRegion": "Madrid",
    "postalCode": "28018",
    "addressCountry": "ES"
  },
  "sameAs": [
    "https://www.facebook.com/profile.php?id=61590018628529",
    "https://www.instagram.com/khurmistore.es/",
    "https://es.trustpilot.com/review/khurmistore.es",
    "https://www.pinterest.com/khurmistore/",
    "https://www.linkedin.com/company/130474406"
  ]
}
</script>

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
<!-- ===== HEADER / NAVIGATION ===== -->
    <header class="header">
        <div class="nav-container">
            <!-- Mobile Hamburger Button -->
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Abrir menú" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
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

        </div>
    </header>

    <!-- Mobile Navigation Drawer -->
    <div class="mobile-nav-overlay" id="mobileNavOverlay"></div>
    <nav class="mobile-nav-drawer" id="mobileNavDrawer" aria-label="Menú móvil">
        <div class="mobile-nav-header">
            <span class="mobile-nav-logo">Khurmi Store</span>
            <button class="mobile-nav-close" id="mobileNavClose" aria-label="Cerrar menú">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <ul class="mobile-nav-links">
            <li><a href="#home"><i class="fas fa-home"></i> Inicio</a></li>
            <li class="mobile-nav-dropdown">
                <button type="button" class="mobile-nav-dropdown-toggle" id="mobileCategoriesToggle" aria-expanded="false" aria-controls="mobileCategoriesSubmenu">
                    <i class="fas fa-th-large"></i> Categorías <i class="fas fa-chevron-down mobile-dropdown-chevron"></i>
                </button>
                <!-- Populated at runtime from the SAME #categoryDropdown list the desktop nav uses (see script.js), so there is one source of truth for categories. -->
                <ul class="mobile-nav-submenu" id="mobileCategoriesSubmenu"></ul>
            </li>
            <li><a href="#products"><i class="fas fa-box"></i> Productos</a></li>
            <li><a href="#contact"><i class="fas fa-envelope"></i> Contacto</a></li>
            <li><a href="blog.html"><i class="fas fa-newspaper"></i> Blog</a></li>
            <li><a href="sobre-nosotros.html"><i class="fas fa-info-circle"></i> Sobre Nosotros</a></li>
            <li><a href="preguntas-frecuentes.html"><i class="fas fa-question-circle"></i> Preguntas Frecuentes</a></li>
            <li class="divider"></li>
            <li><a href="#"><i class="fas fa-shopping-cart"></i> Carrito</a></li>
        </ul>
    </nav>

    <!-- Slider Principal -->
    <section class="hero" id="home">
        <div class="hero-bg"></div>
        <div class="slider-container">
            <div class="slide active">
                <div class="hero-content">
                    <div class="hero-text">
                        <span class="badge">CUIDADO FACIAL</span>
                        <h1>Limpieza <span class="highlight">Facial</span><br>Profunda</h1>
                        <p>Hidrolimpiador facial recargable para una piel radiante</p>
                        <div class="hero-buttons">
                            <button class="btn-primary" onclick="<?= $heroSlide1Id ? "window.location.href='producto.php?id=" . (int)$heroSlide1Id . "'" : 'scrollToProducts()' ?>">Comprar Ahora <i class="fas fa-arrow-right"></i></button>
                            <button class="btn-secondary">Explorar</button>
                        </div>
                    </div>
                    <div class="hero-image">
                        <div class="floating-3d"><img src="<?= htmlspecialchars($heroSlide1Image) ?>" alt="Hidrolimpiador facial Hyser" fetchpriority="high" decoding="async" onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 400%22%3E%3Crect width=%22100%25%22 height=%22100%25%22 fill=%22%230a0e27%22/%3E%3C/svg%3E';"></div>
                        <div class="glow-circle"></div>
                    </div>
                </div>
            </div>
            <div class="slide">
                <div class="hero-content">
                    <div class="hero-text">
                        <span class="badge">BELLEZA PREMIUM</span>
                        <h2>Perfumería y <span class="highlight">Belleza</span><br>Para Ti</h2>
                        <p>Descubre nuestra selección de perfumería y cuidado personal</p>
                        <div class="hero-buttons">
                            <button class="btn-primary" onclick="<?= $heroSlide2Id ? "window.location.href='producto.php?id=" . (int)$heroSlide2Id . "'" : 'scrollToProducts()' ?>">Comprar Ahora <i class="fas fa-arrow-right"></i></button>
                            <button class="btn-secondary">Explorar</button>
                        </div>
                    </div>
                    <div class="hero-image">
                        <div class="floating-3d"><img src="<?= htmlspecialchars($heroSlide2Image) ?>" alt="<?= htmlspecialchars($heroP2['name'] ?? 'Producto de belleza') ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 400%22%3E%3Crect width=%22100%25%22 height=%22100%25%22 fill=%22%230a0e27%22/%3E%3C/svg%3E';"></div>
                        <div class="glow-circle"></div>
                    </div>
                </div>
            </div>
            <div class="slide">
                <div class="hero-content">
                    <div class="hero-text">
                        <span class="badge">TECNOLOGÍA</span>
                        <h2>Electrónica y <span class="highlight">Gadgets</span><br>Para tu Día a Día</h2>
                        <p>Tecnología de calidad al mejor precio, envío rápido a toda España</p>
                        <div class="hero-buttons">
                            <button class="btn-primary" onclick="<?= $heroSlide3Id ? "window.location.href='producto.php?id=" . (int)$heroSlide3Id . "'" : 'scrollToProducts()' ?>">Comprar Ahora <i class="fas fa-arrow-right"></i></button>
                            <button class="btn-secondary">Explorar</button>
                        </div>
                    </div>
                    <div class="hero-image">
                        <div class="floating-3d"><img src="<?= htmlspecialchars($heroSlide3Image) ?>" alt="<?= htmlspecialchars($heroP3['name'] ?? 'Producto de electrónica') ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 400 400%22%3E%3Crect width=%22100%25%22 height=%22100%25%22 fill=%22%230a0e27%22/%3E%3C/svg%3E';"></div>
                        <div class="glow-circle"></div>
                    </div>
                </div>
            </div>
            <button class="slider-btn prev" onclick="changeSlide(-1)" aria-label="Producto anterior"><i class="fas fa-chevron-left"></i></button>
            <button class="slider-btn next" onclick="changeSlide(1)" aria-label="Siguiente producto"><i class="fas fa-chevron-right"></i></button>
            <div class="slider-dots">
                <span class="dot active" onclick="goToSlide(0)"></span>
                <span class="dot" onclick="goToSlide(1)"></span>
                <span class="dot" onclick="goToSlide(2)"></span>
            </div>
        </div>
    </section>

    <!-- Sliders de Categoría (uno por cada categoría del menú, datos reales de Supabase) -->
    <?php foreach ($categorySliders as $slider): ?>
        <section class="category-slider-section" aria-label="<?= htmlspecialchars($slider['label']) ?>">
            <div class="section-header category-slider-header">
                <div>
                    <span class="subtitle">CATEGORÍA</span>
                    <h2><?= htmlspecialchars($slider['label']) ?></h2>
                </div>
                <a href="categoria.php?cat=<?= htmlspecialchars($slider['slug']) ?>" class="category-slider-viewall">Ver Todo <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="category-slider-wrap">
                <button type="button" class="slider-btn category-slider-arrow prev" aria-label="Desplazar a la izquierda"><i class="fas fa-chevron-left"></i></button>
                <div class="category-slider-track" data-autoscroll>
                    <div class="category-slider-row">
                        <?php foreach ($slider['products'] as $p): ?>
                            <?= render_category_slider_card($p, $cfg) ?>
                        <?php endforeach; ?>
                    </div>
                    <!-- Duplicated set of the same cards so the auto-scroll can loop seamlessly -->
                    <div class="category-slider-row" aria-hidden="true">
                        <?php foreach ($slider['products'] as $p): ?>
                            <?= render_category_slider_card($p, $cfg) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="button" class="slider-btn category-slider-arrow next" aria-label="Desplazar a la derecha"><i class="fas fa-chevron-right"></i></button>
            </div>
        </section>
    <?php endforeach; ?>

    <!-- Características -->
    <section class="features">
        <div class="feature-box benefit-card" data-benefit="envio" role="button" tabindex="0"><i class="fas fa-truck-fast"></i><h3>Envío Gratis</h3><p>Sin mínimo, 2-5 días laborables</p></div>
        <div class="feature-box benefit-card" data-benefit="pago" role="button" tabindex="0"><i class="fas fa-shield-halved"></i><h3>Pago Seguro</h3><p>100% Protegido</p></div>
        <div class="feature-box benefit-card" data-benefit="devolucion" role="button" tabindex="0"><i class="fas fa-rotate-left"></i><h3>Devolución Fácil</h3><p>Política de 14 días</p></div>
        <a class="feature-box support-link" href="https://wa.me/34662241860?text=Hola,%20necesito%20ayuda" target="_blank" rel="noopener noreferrer"><i class="fas fa-headset"></i><h3>Soporte 24/7</h3><p>Siempre disponibles</p></a>
    </section>

    <!-- Lo que dicen nuestros clientes -->
    <section style="padding:70px 20px; background:#0d1228;">
        <div style="max-width:700px; margin:0 auto; text-align:center;">
            <p class="subtitle">OPINIONES</p>
            <h2 class="section-title">Lo que dicen nuestros <span>clientes</span></h2>
            <p style="color:#888; margin-top:16px; font-size:1rem; line-height:1.6;">Sé el primero en dejar tu opinión sobre nuestros productos.</p>
        </div>
    </section>

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
                <p><i class="fas fa-phone"></i> <a href="tel:+34662241860">+34 662 24 18 60</a></p>
                <p><i class="fas fa-envelope"></i> info@khurmistore.es</p>
            </div>
            
        </div>
        <div class="footer-bottom">
            <div class="trust-row footer-trust-row">
                <span><i class="fas fa-lock"></i> Pago 100% seguro</span>
                <span><i class="fas fa-truck-fast"></i> Envío gratis desde España</span>
                <span><i class="fas fa-rotate-left"></i> Devoluciones en 14 días</span>
                <span><i class="fas fa-headset"></i> Atención al cliente en España</span>
            </div>
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
            <div class="cart-summary" id="cartSummary">
                <h3>Resumen del carrito</h3>
                <div class="cart-summary-items" id="cartSummaryItems"></div>
                <div class="cart-summary-totals">
                    <div><span>Subtotal</span><span id="cartSummarySubtotal">€0,00</span></div>
                    <div class="cart-summary-total"><span>Total</span><span id="cartSummaryTotal">€0,00</span></div>
                </div>
            </div>
            <div class="cart-totals">
                <div class="cart-line"><span>Subtotal:</span><span id="cartSubtotal">€0,00</span></div>
                <div class="cart-line total"><span>Total:</span><span class="total-price" id="cartTotal">€0,00</span></div>
            </div>
            <button class="btn-primary checkout-btn" onclick="openCheckout()">Finalizar Compra <i class="fas fa-arrow-right"></i></button>
        </div>
    </div>

    <div id="orderConfirmationBanner" class="order-confirmation-banner" role="status" aria-live="polite"></div>

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

    <!-- Modal de Beneficio -->
    <div class="modal-overlay" id="benefitModal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="benefitModalTitle">Beneficio</h2>
                <button class="close-btn" type="button" id="closeBenefitModal" aria-label="Cerrar"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p id="benefitModalText"></p>
            </div>
        </div>
    </div>

    <!-- Search Modal -->
    <div class="search-overlay" id="searchOverlay" onclick="closeSearch(event)">
        <div class="search-modal">
            <div class="search-header">
                <h2>Buscar Productos</h2>
                <button class="search-close-btn" onclick="closeSearch()" aria-label="Cerrar búsqueda"><i class="fas fa-times"></i></button>
            </div>
            <div class="search-input-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Escribe el nombre del producto..." autocomplete="off">
            </div>
            <div class="search-results" id="searchResults"></div>
        </div>
    </div>

    <!-- Popup de Bienvenida -->
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

    <a href="https://wa.me/34662241860?text=Hola,%20tengo%20una%20pregunta%20sobre%20un%20producto" class="whatsapp-float" target="_blank" rel="noopener noreferrer" aria-label="Chat de WhatsApp">
        <i class="fab fa-whatsapp"></i>
        <span class="whatsapp-tooltip">¿Necesitas ayuda?</span>
    </a>

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
    // The "Destacados" grid above is now server-rendered by PHP when real Supabase
    // products exist (see #featuredProductsGrid). We still seed script.js's global
    // `products` array with the same real data so "Añadir al carrito" on those
    // server-rendered cards can find them by real id.
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
        }, $featured), JSON_UNESCAPED_UNICODE) ?>;

        if (realProducts.length > 0) {
            // products is declared with const in script.js; mutate in place instead of reassigning.
            products.length = 0;
            Array.prototype.push.apply(products, realProducts);
        }
    });
    </script>
    <script>
    // Per-category homepage sliders (Change 3): continuous right-to-left auto-scroll,
    // paused on hover/touch, plus manual prev/next arrows. Pure vanilla JS, no libraries.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.category-slider-track').forEach(function (track) {
            var firstRow = track.querySelector('.category-slider-row');
            var loopWidth = 0;

            function measure() {
                // Width of one full (non-duplicated) set of cards, used to reset the
                // scroll position seamlessly once the auto-scroll passes it.
                loopWidth = firstRow ? firstRow.scrollWidth + 24 : track.scrollWidth / 2;
            }
            measure();
            window.addEventListener('resize', measure);

            var paused = false;
            var speed = 0.6; // px per animation frame

            function autoScrollStep() {
                if (!paused && loopWidth > 0) {
                    track.scrollLeft += speed;
                    if (track.scrollLeft >= loopWidth) {
                        track.scrollLeft -= loopWidth;
                    }
                }
                requestAnimationFrame(autoScrollStep);
            }
            requestAnimationFrame(autoScrollStep);

            // Pause on hover (desktop) and while the user is touching/dragging (mobile).
            track.addEventListener('mouseenter', function () { paused = true; });
            track.addEventListener('mouseleave', function () { paused = false; });
            track.addEventListener('touchstart', function () { paused = true; }, { passive: true });
            track.addEventListener('touchend', function () { paused = false; });

            // Manual left/right arrows for this slider.
            var wrap = track.closest('.category-slider-wrap');
            var prevBtn = wrap ? wrap.querySelector('.category-slider-arrow.prev') : null;
            var nextBtn = wrap ? wrap.querySelector('.category-slider-arrow.next') : null;
            var resumeTimer = null;

            function manualScroll(direction) {
                paused = true;
                track.scrollBy({ left: direction * 300, behavior: 'smooth' });
                clearTimeout(resumeTimer);
                resumeTimer = setTimeout(function () { paused = false; }, 1500);
            }
            if (prevBtn) prevBtn.addEventListener('click', function () { manualScroll(-1); });
            if (nextBtn) nextBtn.addEventListener('click', function () { manualScroll(1); });
        });
    });
    </script>
</body>
</html>

