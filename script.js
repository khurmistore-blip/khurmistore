/* ===== FOOTER LOGO LETTER ANIMATION (match header) ===== */
(function () {
  function wrap(el) {
    if (!el || el.querySelector('.letter')) return;
    var full = el.textContent.replace(/\s+/g, '');
    var idx = full.indexOf('Store');
    var part1 = idx >= 0 ? full.slice(0, idx) : full;
    var part2 = idx >= 0 ? full.slice(idx) : '';
    var html = '';
    for (var i = 0; i < part1.length; i++) html += '<span class="letter">' + part1[i] + '</span>';
    if (part2) {
      html += '<span class="wave-text">';
      for (var j = 0; j < part2.length; j++) html += '<span class="letter">' + part2[j] + '</span>';
      html += '</span>';
    }
    el.innerHTML = html;
  }
  function run() {
    try {
      var footer = document.querySelector('footer');
      if (!footer) return;
      var all = footer.querySelectorAll('*');
      var best = null;
      for (var k = 0; k < all.length; k++) {
        var c = all[k];
        if (c.querySelector('.letter')) continue;
        if (c.textContent.replace(/\s+/g, '') === 'KhurmiStore') {
          if (!best || c.querySelectorAll('*').length < best.querySelectorAll('*').length) best = c;
        }
      }
      if (best) wrap(best);
    } catch (e) {}
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
})();

/* ===== LOGO LETTER ANIMATION (header + footer, every page) ===== */
(function () {
  function wrap(el) {
    if (!el || el.querySelector('.letter')) return;
    var full = el.textContent.replace(/\s+/g, '');
    var idx = full.indexOf('Store');
    var part1 = idx >= 0 ? full.slice(0, idx) : full;
    var part2 = idx >= 0 ? full.slice(idx) : '';
    var html = '';
    for (var i = 0; i < part1.length; i++) html += '<span class="letter">' + part1[i] + '</span>';
    if (part2) {
      html += '<span class="wave-text">';
      for (var j = 0; j < part2.length; j++) html += '<span class="letter">' + part2[j] + '</span>';
      html += '</span>';
    }
    el.innerHTML = html;
  }
  function run() {
    try {
      document.querySelectorAll('.logo h1').forEach(wrap);
      var footer = document.querySelector('footer');
      if (footer) {
        var all = footer.querySelectorAll('*');
        var best = null;
        for (var k = 0; k < all.length; k++) {
          var c = all[k];
          if (c.querySelector('.letter')) continue;
          if (c.textContent.replace(/\s+/g, '') === 'KhurmiStore') {
            if (!best || c.querySelectorAll('*').length < best.querySelectorAll('*').length) best = c;
          }
        }
        if (best) wrap(best);
      }
    } catch (e) {}
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
})();

// Catalogo de Productos - KhurmiStore Espana
// Intentionally empty: this used to hold 48 hardcoded demo products with
// Unsplash stock photos (the "generic dropship template" look a reviewer
// flagged). Real pages (index.php, categoria.php, producto.php) seed this
// array from live Supabase data before calling the render functions below,
// so they are unaffected. Kept as an empty array rather than deleted so
// renderProducts()/renderProductDetails() keep working via their existing
// null-checks/empty-states instead of erroring on a missing global.
const products = [];

let cart = [];

// ===== SHIPPING (Spain, weight-based) =====
// Mirrors shipping_lib.php's calcShipping()/calcCartShipping() — keep both
// in sync if the rate table ever changes. Client-side copy is needed since
// this runs before any server round-trip (cart drawer, checkout summary).
const SHIPPING_RATES_ES = {
    1: 4.94, 2: 5.16, 3: 5.26, 4: 5.26, 5: 6.36,
    6: 6.54, 7: 6.41, 8: 6.49, 9: 6.52, 10: 7.34,
};
const SHIPPING_DEFAULT_ES = 4.99;

// STORE-WIDE FREE SHIPPING: always 0, regardless of weight — mirrors
// shipping_lib.php's calcShipping()/calcCartShipping(). Rate table/default
// above kept in place (unused) in case free shipping is ever reverted.
function calcShippingJS(weightKg) {
    return 0;
}

function calcCartShippingJS(items) {
    return 0;
}

// Formatear precio en Euros
function formatPrice(price) {
    return price.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });
}

// Mostrar Productos
function renderProducts(filter = 'all') {
    const grid = document.getElementById('productsGrid');
    if (!grid) return;
    const filtered = filter === 'all'
        ? products
        : Array.isArray(filter)
            ? products.filter(p => filter.includes(p.category))
            : products.filter(p => p.category === filter);

    grid.innerHTML = filtered.map(p => `
        <div class="product-card" onclick="goToProduct(${p.id})">
            <div class="product-img">
                <span class="product-tag">${p.tag}</span>
                <img src="${p.image}" alt="${p.name}">
                <div class="quick-view"><i class="fas fa-eye"></i> Ver Detalles</div>
            </div>
            <div class="product-info">
                <h3>${p.name}</h3>
                <p class="product-cat">${getCategoryName(p.category)}</p>
                <span class="badge-nuevo">Nuevo</span>
                <div class="product-price">
                    <span class="price">${formatPrice(p.price)}${p.oldPrice ? ` <small style="color:#888;text-decoration:line-through;font-size:13px;">${formatPrice(p.oldPrice)}</small>` : ''}</span>
                    <button type="button" class="add-cart" onclick="event.stopPropagation(); addToCart(${p.id})" aria-label="Añadir ${p.name} al carrito">
                        <i class="fas fa-cart-plus"></i> Añadir al carrito
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

// getCategoryPageConfig() / formatCategorySlug() / renderCategoryPage() removed:
// they only ever rendered categoria.html (the static demo category page,
// deleted). No live page has the categoryTitleHeading/categoryTitle/
// categoryDescription/productsGrid ids this used, so it was 100% dead code.
// Navegar a la página de detalles del producto
function goToProduct(id) {
    window.location.href = `/producto.php?id=${id}`;
}

function getCategoryName(cat) {
    const names = {
        headphones: 'Auriculares',
        smartwatch: 'Relojes Inteligentes',
        earpods: 'Inalámbricos',
        covers: 'Fundas',
        headgear: 'Cascos Gaming',
        handsfree: 'Manos Libres',
        mouse: 'Ratones'
    };
    return names[cat] || cat;
}

// Botones de Filtro
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderProducts(btn.dataset.filter);
    });
});

// Clic en Categoría
document.querySelectorAll('.category-card').forEach(card => {
    card.addEventListener('click', () => {
        const cat = card.dataset.category;
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        document.querySelector(`.filter-btn[data-filter="${cat}"]`)?.classList.add('active');
        renderProducts(cat);
        document.getElementById('products')?.scrollIntoView({behavior: 'smooth'});
    });
});

// Funciones del Carrito (con persistencia en localStorage)
function loadCart() {
    try {
        const saved = localStorage.getItem('kw_cart');
        cart = saved ? JSON.parse(saved) : [];
    } catch (e) { cart = []; }
}

function saveCart() {
    localStorage.setItem('kw_cart', JSON.stringify(cart));
}

function addToCart(id, qty = 1) {
    const product = products.find(p => p.id === id);
    if (!product) return;
    if ((product.stock ?? 1) <= 0 || product.isActive === false) {
        showNotification('Lo sentimos, este producto está agotado.');
        return;
    }
    const existing = cart.find(item => item.id === id);
    if (existing) {
        existing.qty += qty;
    } else {
        cart.push({...product, qty: qty});
    }
    saveCart();
    updateCart();
    showNotification(`¡${product.name} añadido al carrito!`);
    fbq('track','AddToCart',{value: product.price, currency:'EUR'});

    // Best-effort analytics only — never let this affect the cart above.
    try {
        fetch('track_cart_event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: id }),
        }).catch(() => {});
    } catch (e) {}
}

function removeFromCart(id) {
    cart = cart.filter(item => item.id !== id);
    saveCart();
    updateCart();
}

function changeQty(id, change) {
    const item = cart.find(i => i.id === id);
    if (item) {
        item.qty += change;
        if (item.qty <= 0) removeFromCart(id);
        else { saveCart(); updateCart(); }
    }
}

function updateCart() {
    const totalItems = cart.reduce((sum, item) => sum + item.qty, 0);
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const shipping = calcCartShippingJS(cart);
    const totalPrice = subtotal + shipping; // Cart drawer Total now includes shipping, same formula as updateSummary()

    const cartCountEl = document.querySelector('.cart-count');
    const cartSubtotalEl = document.getElementById('cartSubtotal');
    const cartTotalEl = document.getElementById('cartTotal');
    const cartSummaryEl = document.getElementById('cartSummary');
    const cartSummaryItemsEl = document.getElementById('cartSummaryItems');
    const cartSummarySubtotalEl = document.getElementById('cartSummarySubtotal');
    const cartSummaryTotalEl = document.getElementById('cartSummaryTotal');
    if (cartCountEl) cartCountEl.textContent = totalItems;
    if (cartSubtotalEl) cartSubtotalEl.textContent = formatPrice(subtotal);
    if (cartTotalEl) cartTotalEl.textContent = formatPrice(totalPrice);

    if (cartSummaryEl) {
        cartSummaryEl.style.display = cart.length ? 'block' : 'none';
    }

    if (cartSummaryItemsEl) {
        cartSummaryItemsEl.innerHTML = cart.length ? cart.map(item => `
            <div class="cart-summary-item">
                <span>${item.name}</span>
                <span>${item.qty} x ${formatPrice(item.price)}</span>
            </div>
            <div class="cart-summary-item cart-summary-shipping">
                <span>Envío de este producto</span>
                <span>GRATIS</span>
            </div>
        `).join('') : '';
    }
    if (cartSummarySubtotalEl) cartSummarySubtotalEl.textContent = formatPrice(subtotal);
    if (cartSummaryTotalEl) cartSummaryTotalEl.textContent = formatPrice(totalPrice);

    const cartItemsDiv = document.getElementById('cartItems');
    if (!cartItemsDiv) return;
    if (cart.length === 0) {
        cartItemsDiv.innerHTML = `<div class="empty-cart"><i class="fas fa-shopping-cart"></i><p>Tu carrito está vacío</p></div>`;
    } else {
        cartItemsDiv.innerHTML = cart.map(item => `
            <div class="cart-item">
                <img src="${item.image}" alt="${item.name}">
                <div class="cart-item-info">
                    <h4>${item.name}</h4>
                    <span class="price">${formatPrice(item.price)}</span>
                    <span class="cart-item-shipping" style="display:block;font-size:12px;color:var(--muted,#888);"><i class="fas fa-truck-fast"></i> Envío: GRATIS</span>
                    <div class="qty-controls">
                        <button type="button" onclick="changeQty(${item.id}, -1)">-</button>
                        <span>${item.qty}</span>
                        <button type="button" onclick="changeQty(${item.id}, 1)">+</button>
                    </div>
                </div>
                <button type="button" class="remove-btn" onclick="removeFromCart(${item.id})"><i class="fas fa-trash"></i></button>
            </div>
        `).join('');
    }
}

// Carrito Lateral
function openCart() {
    document.getElementById('cartSidebar')?.classList.add('active');
    document.getElementById('cartOverlay')?.classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeCart() {
    document.getElementById('cartSidebar')?.classList.remove('active');
    document.getElementById('cartOverlay')?.classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Menú móvil - Toggle mobile menu
function toggleMobileMenu() {
    const menu = document.getElementById('mobileNavMenu');
    const overlay = document.getElementById('mobileNavOverlay');
    const button = document.getElementById('navToggle');
    if (!menu || !overlay || !button) return;
    
    const isOpening = !menu.classList.contains('active');
    
    menu.classList.toggle('active');
    overlay.classList.toggle('active', isOpening);
    
    // Bloquea el scroll del body cuando el menú está abierto (ISSUE 1)
    document.body.classList.toggle('menu-open', isOpening);
    
    button.setAttribute('aria-expanded', isOpening ? 'true' : 'false');
}

/**
 * Click/tap toggle for a dropdown/accordion caret at ANY nesting level —
 * used by both the desktop top-level category carets and the desktop
 * subcategory-flyout carets (mobile has its own toggleMobileCatPanel below).
 * Needed because CSS :hover doesn't fire reliably on touch-capable devices
 * at desktop width (e.g. a tablet in landscape) — real mouse users never
 * trigger this, CSS :hover handles them directly. Closes any other open
 * sibling at the same level first so only one dropdown is ever open at a
 * time, matching mouse-hover behavior.
 */
function toggleCategoryDropdown(event) {
    const button = event.currentTarget;
    const container = button.closest('.dropdown, .has-children');
    if (!container) return;
    const willOpen = !container.classList.contains('dropdown-open');
    const siblingList = container.parentElement;
    if (siblingList) {
        Array.from(siblingList.children).forEach(function (sibling) {
            if (sibling === container || !sibling.classList || !sibling.classList.contains('dropdown-open')) return;
            sibling.classList.remove('dropdown-open');
            const sibBtn = sibling.querySelector(':scope > .nav-cat-row > .dropdown-toggle-caret, :scope > .submenu-row > .dropdown-toggle-caret');
            if (sibBtn) sibBtn.setAttribute('aria-expanded', 'false');
        });
    }
    container.classList.toggle('dropdown-open', willOpen);
    button.setAttribute('aria-expanded', String(willOpen));
}

// ===== Category nav: each main category is its own top-level nav item =====
// Single source of truth for the on-page menu across all 29 pages (most are
// static .html and can't run PHP, so rendering happens here in shared JS
// instead of server-side). categories_config.php is the PARALLEL PHP-side
// source of truth for categoria.php's server-side filtering/breadcrumb — if
// you add/change a category, update BOTH this tree and categories_config.php.
// There is NO single "Categorías" wrapper — each top-level node below
// renders as its own <li>, inserted directly into the main nav <ul>
// alongside Inicio/Tienda/Blog/etc via the #categoryNavSlot placeholder.
const CATEGORY_TREE = [{"slug":"relojes","name":"Relojes","children":[{"slug":"analogicos","name":"Analógicos","children":[]},{"slug":"digitales","name":"Digitales / Smartwatch","children":[]}]},{"slug":"belleza","name":"Belleza","children":[{"slug":"unas-y-herramientas","name":"Uñas y Herramientas","children":[]},{"slug":"alimentacion-y-salud","name":"Alimentación y Salud","children":[{"slug":"cuidado-de-la-salud","name":"Cuidado de la Salud","children":[]}]},{"slug":"cabello-y-accesorios","name":"Cabello y Accesorios","children":[{"slug":"diademas-y-cintas","name":"Diademas y Cintas para el Pelo","children":[]},{"slug":"horquillas","name":"Horquillas para el Pelo","children":[]},{"slug":"cabello-humano","name":"Cabello Humano","children":[]}]},{"slug":"cabello-sintetico","name":"Cabello Sintético","children":[{"slug":"cabello-para-cosplay","name":"Cabello para Cosplay","children":[]}]},{"slug":"cuidado-de-la-piel","name":"Cuidado de la Piel","children":[{"slug":"maquinillas-de-afeitar","name":"Maquinillas de Afeitar","children":[]},{"slug":"mascarillas-faciales","name":"Mascarillas Faciales","children":[]},{"slug":"proteccion-solar","name":"Protección Solar","children":[]},{"slug":"aceites-esenciales","name":"Aceites Esenciales","children":[]},{"slug":"cuidado-corporal","name":"Cuidado Corporal","children":[]},{"slug":"cuidado-facial","name":"Cuidado Facial","children":[]}]},{"slug":"mechones-de-cabello","name":"Mechones de Cabello","children":[{"slug":"paquete-pre-coloreado","name":"Paquete Pre-Coloreado","children":[]},{"slug":"tejido-de-cabello","name":"Tejido de Cabello","children":[]},{"slug":"estilismo-de-cabello","name":"Estilismo de Cabello","children":[]},{"slug":"mechones-de-salon","name":"Mechones de Salón","children":[]},{"slug":"mechon-pre-coloreado","name":"Mechón Pre-Coloreado","children":[]}]},{"slug":"maquillaje","name":"Maquillaje","children":[{"slug":"lapiz-de-cejas","name":"Lápiz de Cejas","children":[]},{"slug":"set-de-maquillaje","name":"Set de Maquillaje","children":[]},{"slug":"sombra-de-ojos","name":"Sombra de Ojos","children":[]},{"slug":"brochas-de-maquillaje","name":"Brochas de Maquillaje","children":[]},{"slug":"pestanas-postizas","name":"Pestañas Postizas","children":[]},{"slug":"pintalabios","name":"Pintalabios","children":[]}]},{"slug":"pelucas-y-extensiones","name":"Pelucas y Extensiones","children":[{"slug":"peluca-cabello-humano","name":"Peluca de Cabello Humano","children":[]},{"slug":"postizo-sintetico","name":"Postizo Sintético","children":[]},{"slug":"peluca-encaje-sintetica","name":"Peluca de Encaje Sintética","children":[]},{"slug":"peluca-encaje-cabello-humano","name":"Peluca de Encaje de Cabello Humano","children":[]},{"slug":"trenzas","name":"Trenzas","children":[]},{"slug":"pelucas-sinteticas","name":"Pelucas Sintéticas","children":[]}]},{"slug":"herramientas-de-belleza","name":"Herramientas de Belleza","children":[{"slug":"espejo","name":"Espejo","children":[]},{"slug":"planchas-de-pelo","name":"Planchas de Pelo","children":[]},{"slug":"limpiador-facial-electrico","name":"Limpiador Facial Eléctrico","children":[]},{"slug":"herramientas-cuidado-facial","name":"Herramientas de Cuidado Facial","children":[]},{"slug":"rizador-de-pelo","name":"Rizador de Pelo","children":[]},{"slug":"vaporizador-facial","name":"Vaporizador Facial","children":[]}]}]},{"slug":"electronica","name":"Electrónica","children":[]},{"slug":"auriculares","name":"Auriculares y Audio","children":[]},{"slug":"accesorios-movil","name":"Accesorios para Móvil","children":[]}];

function catHref(catSlug, subSlug, sub2Slug) {
    let href = '/categoria.php?cat=' + encodeURIComponent(catSlug);
    if (subSlug) href += '&sub=' + encodeURIComponent(subSlug);
    if (sub2Slug) href += '&sub2=' + encodeURIComponent(sub2Slug);
    return href;
}

/**
 * Builds the top-level category <li>s that sit DIRECTLY in the main desktop
 * nav <ul>, as siblings of Inicio/Tienda/Blog/etc — never wrapped in a
 * single shared "Categorías" container. A leaf category (no children, e.g.
 * Electrónica) is a plain link. A category with children gets its own
 * independent <ul class="submenu"> shown only on hovering/tapping THAT
 * category — hovering "Relojes" never affects "Belleza"'s submenu, since
 * each lives inside its own <li>. A subcategory with children (e.g.
 * "Alimentación y Salud") gets its own nested <ul class="submenu-flyout">
 * shown only on hovering/tapping that specific subcategory.
 */
function renderCategoryNavItems(tree) {
    return tree.map(function (node) {
        if (!node.children || node.children.length === 0) {
            return `<li><a href="${catHref(node.slug)}">${node.name}</a></li>`;
        }
        const subItems = node.children.map(function (sub) {
            if (!sub.children || sub.children.length === 0) {
                return `<li><a class="submenu-link" href="${catHref(node.slug, sub.slug)}">${sub.name}</a></li>`;
            }
            const subSubItems = sub.children.map(function (sub2) {
                return `<li><a class="submenu-link submenu-link-flyout" href="${catHref(node.slug, sub.slug, sub2.slug)}">${sub2.name}</a></li>`;
            }).join('');
            return `<li class="has-children">`
                + `<div class="submenu-row"><a class="submenu-link" href="${catHref(node.slug, sub.slug)}">${sub.name}</a>`
                + `<button type="button" class="dropdown-toggle-caret dropdown-toggle-caret-sm" aria-expanded="false" aria-label="Mostrar más de ${sub.name}" onclick="toggleCategoryDropdown(event)"><i class="fas fa-chevron-right"></i></button></div>`
                + `<ul class="submenu-flyout">${subSubItems}</ul>`
                + `</li>`;
        }).join('');
        return `<li class="nav-item dropdown" data-slug="${node.slug}">`
            + `<div class="nav-cat-row"><a href="${catHref(node.slug)}">${node.name}</a>`
            + `<button type="button" class="dropdown-toggle-caret" aria-expanded="false" aria-label="Mostrar subcategorías de ${node.name}" onclick="toggleCategoryDropdown(event)"><i class="fas fa-chevron-down"></i></button></div>`
            + `<ul class="submenu">${subItems}</ul>`
            + `</li>`;
    }).join('');
}

/**
 * Builds the mobile accordion rows for the SAME categories — one
 * independent <li> per category, no shared wrapper, inserted directly into
 * the mobile nav <ul> alongside Inicio/Tienda/Blog/etc. Tapping a category's
 * own caret expands ONLY its own subcategory panel; tapping a subcategory's
 * caret expands ONLY its own sub-subcategory panel. toggleMobileCatPanel()
 * closes any other open sibling panel at the same level first.
 */
function renderMobileCategoryNavItems(tree) {
    let counter = 0;
    return tree.map(function (node) {
        if (!node.children || node.children.length === 0) {
            return `<li class="mobile-cat-item"><a href="${catHref(node.slug)}" onclick="closeMobileMenu()">${node.name}</a></li>`;
        }
        counter++;
        const panelId = 'mobileCat-' + counter;
        const subItems = node.children.map(function (sub) {
            if (!sub.children || sub.children.length === 0) {
                return `<li class="mobile-cat-subitem"><a class="mobile-cat-link mobile-cat-link-sub" href="${catHref(node.slug, sub.slug)}" onclick="closeMobileMenu()">${sub.name}</a></li>`;
            }
            counter++;
            const subPanelId = 'mobileCat-' + counter;
            const subSubItems = sub.children.map(function (sub2) {
                return `<li><a class="mobile-cat-link mobile-cat-link-subsub" href="${catHref(node.slug, sub.slug, sub2.slug)}" onclick="closeMobileMenu()">${sub2.name}</a></li>`;
            }).join('');
            return `<li class="mobile-cat-subitem has-children">`
                + `<div class="mobile-cat-row"><a class="mobile-cat-link mobile-cat-link-sub" href="${catHref(node.slug, sub.slug)}" onclick="closeMobileMenu()">${sub.name}</a>`
                + `<button type="button" class="mobile-cat-toggle" aria-expanded="false" aria-controls="${subPanelId}" onclick="toggleMobileCatPanel(this)"><i class="fas fa-chevron-down"></i></button></div>`
                + `<ul class="mobile-cat-subsub" id="${subPanelId}">${subSubItems}</ul>`
                + `</li>`;
        }).join('');
        return `<li class="mobile-cat-item has-children">`
            + `<div class="mobile-cat-row"><a href="${catHref(node.slug)}" onclick="closeMobileMenu()">${node.name}</a>`
            + `<button type="button" class="mobile-cat-toggle" aria-expanded="false" aria-controls="${panelId}" onclick="toggleMobileCatPanel(this)"><i class="fas fa-chevron-down"></i></button></div>`
            + `<ul class="mobile-cat-sub" id="${panelId}">${subItems}</ul>`
            + `</li>`;
    }).join('');
}

/**
 * Tap-to-expand/collapse for one level of the mobile category accordion.
 * Closes any other open sibling panel AT THE SAME NESTING LEVEL first, so
 * only one panel is ever open at a time (matches the desktop hover
 * behavior) — e.g. expanding "Belleza" auto-collapses "Relojes" if it was
 * open, and expanding one of Belleza's subcategories auto-collapses any
 * other open subcategory within Belleza, without touching other categories.
 */
function toggleMobileCatPanel(btn) {
    const targetId = btn.getAttribute('aria-controls');
    const target = document.getElementById(targetId);
    if (!target) return;
    const currentLi = target.closest('li');
    const siblingList = currentLi ? currentLi.parentElement : null;
    if (siblingList) {
        Array.from(siblingList.children).forEach(function (siblingLi) {
            if (siblingLi === currentLi) return;
            const openPanel = siblingLi.querySelector(':scope > .mobile-cat-sub.open, :scope > .mobile-cat-subsub.open');
            if (!openPanel) return;
            openPanel.classList.remove('open');
            const openBtn = siblingLi.querySelector(':scope > .mobile-cat-row > .mobile-cat-toggle');
            if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
        });
    }
    const willOpen = !target.classList.contains('open');
    target.classList.toggle('open', willOpen);
    btn.setAttribute('aria-expanded', String(willOpen));
}

/**
 * Replaces the #categoryNavSlot / #mobileCategoryNavSlot placeholder <li>s
 * (present in every page's shared header markup, positioned between Tienda
 * and Blog) with the real per-category <li>s built from CATEGORY_TREE. Runs
 * on every page since most of this site's 29 pages are static .html and
 * can't render this server-side.
 */
function initCategoryMenus() {
    const desktopSlot = document.getElementById('categoryNavSlot');
    if (desktopSlot) {
        desktopSlot.outerHTML = renderCategoryNavItems(CATEGORY_TREE);
    }
    const mobileSlot = document.getElementById('mobileCategoryNavSlot');
    if (mobileSlot) {
        mobileSlot.outerHTML = renderMobileCategoryNavItems(CATEGORY_TREE);
    }
}
document.addEventListener('DOMContentLoaded', initCategoryMenus);

// Close mobile menu - used when a link is clicked
function closeMobileMenu() {
    const menu = document.getElementById('mobileNavMenu');
    const overlay = document.getElementById('mobileNavOverlay');
    const button = document.getElementById('navToggle');
    const dropdowns = document.querySelectorAll('.nav-item.dropdown');
    const dropdownToggles = document.querySelectorAll('.nav-item.dropdown .dropdown-toggle');
    
    if (!menu || !overlay || !button) return;
    
    menu.classList.remove('active');
    overlay.classList.remove('active');
    button.setAttribute('aria-expanded', 'false');
    
    // Restaura el scroll del body al cerrar (ISSUE 1)
    document.body.classList.remove('menu-open');
    
    dropdowns.forEach(dropdown => dropdown.classList.remove('dropdown-open'));
    dropdownToggles.forEach(toggle => toggle.setAttribute('aria-expanded', 'false'));
}

// Close mobile menu when a link is clicked (only on mobile)
function closeMobileMenuLink() {
    if (window.innerWidth <= 768) {
        closeMobileMenu();
    }
}

// Attach click listeners to mobile menu links only
document.addEventListener('DOMContentLoaded', () => {
    const mobileMenu = document.getElementById('mobileNavMenu');
    if (mobileMenu) {
        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', closeMobileMenuLink);
        });
    }
});

// Funciones de Pago
function openCheckout() {
    if (cart.length === 0) {
        showNotification('¡Tu carrito está vacío!');
        return;
    }
    if (!document.getElementById('checkoutModal')) {
        window.location.href = '/index.php?checkout=1';
        return;
    }
    closeCart();
    document.getElementById('checkoutForm')?.reset();
    updateSummary();
    document.getElementById('checkoutModal').classList.add('active');

    // Meta Pixel: InitiateCheckout — fires once per modal open here, not
    // inside updateSummary() (which also runs from goToPayment() and would
    // otherwise double-fire this per checkout attempt).
    if (typeof fbq === 'function') {
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        fbq('track', 'InitiateCheckout', {
            value: subtotal + calcCartShippingJS(cart),
            currency: 'EUR',
            num_items: cart.reduce((sum, item) => sum + item.qty, 0),
            content_ids: cart.map(item => item.id),
            content_type: 'product'
        });
    }
}

function closeCheckout() {
    document.getElementById('checkoutModal')?.classList.remove('active');
}

function updateSummary() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const subtotalEl = document.getElementById('summarySubtotal');
    const totalEl = document.getElementById('summaryTotal');
    if (subtotalEl) subtotalEl.textContent = formatPrice(subtotal);
    if (totalEl) totalEl.textContent = formatPrice(subtotal);
}

function submitWhatsappOrder(event) {
    event.preventDefault();
    const name = document.getElementById('custName')?.value.trim();
    const phone = document.getElementById('custPhone')?.value.trim();
    const address = document.getElementById('custAddress')?.value.trim();
    const city = document.getElementById('custCity')?.value.trim();
    const postal = document.getElementById('custPostal')?.value.trim();
    const notes = document.getElementById('custNotes')?.value.trim();

    if (!name || !phone || !address || !city || !postal) {
        showNotification('Por favor rellena todos los campos obligatorios.');
        return;
    }

    if (cart.length === 0) {
        showNotification('Tu carrito está vacío. Añade productos antes de finalizar.');
        closeCheckout();
        return;
    }

    const total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const itemsText = cart.map(item => `• ${item.name} x${item.qty} - ${formatPrice(item.price * item.qty)}`).join('\n');
    const message = `🛒 NUEVO PEDIDO - KhurmiStore\n\nPedido:\n${itemsText}\n\nTOTAL: ${formatPrice(total)}\n\nDatos del cliente:\nNombre: ${name}\nTeléfono: ${phone}\nDirección: ${address}\nCiudad: ${city}\nCódigo postal: ${postal}\nNotas: ${notes || 'Ninguna'}`;
    const encodedMessage = encodeURIComponent(message);
    const whatsappUrl = `https://wa.me/34662241860?text=${encodedMessage}`;

    window.open(whatsappUrl, '_blank');
    showOrderConfirmation('¡Gracias! Tu pedido se ha enviado por WhatsApp. Te contactaremos pronto para confirmar.');
    closeCheckout();
    cart = [];
    saveCart();
    updateCart();
}

function showOrderConfirmation(message) {
    const banner = document.getElementById('orderConfirmationBanner');
    if (!banner) {
        showNotification(message);
        return;
    }
    banner.textContent = message;
    banner.classList.add('active');
    setTimeout(() => banner.classList.remove('active'), 8000);
}

const benefitDetails = {
    envio: {
        title: 'Envío Gratis',
        text: 'Disfruta de envío gratuito en todos los pedidos, sin mínimo de compra. Entrega en 2-5 días laborables en toda España.'
    },
    pago: {
        title: 'Pago 100% Seguro',
        text: 'Tus pagos están protegidos con cifrado SSL. Aceptamos tarjeta, PayPal y transferencia. Tu información nunca se comparte con terceros.'
    },
    devolucion: {
        title: 'Devolución Fácil',
        text: 'Dispones de 14 días para devolver cualquier producto sin complicaciones. Reembolso completo una vez recibido el artículo en buen estado.'
    },
    garantia: {
        title: 'Garantía 12 meses',
        text: 'Todos nuestros productos incluyen 12 meses de garantía frente a defectos de fabricación. Si tienes cualquier problema, contáctanos y te ayudamos.'
    }
};

function openBenefitModal(key) {
    const detail = benefitDetails[key];
    if (!detail) return;
    const overlay = document.getElementById('benefitModal');
    const title = document.getElementById('benefitModalTitle');
    const text = document.getElementById('benefitModalText');
    if (title) title.textContent = detail.title;
    if (text) text.textContent = detail.text;
    overlay?.classList.add('active');
}

function closeBenefitModal() {
    document.getElementById('benefitModal')?.classList.remove('active');
}

function goToPayment() {
    const name = document.getElementById('custName').value;
    const email = document.getElementById('custEmail').value;
    const phone = document.getElementById('custPhone').value;
    const address = document.getElementById('custAddress').value;
    const city = document.getElementById('custCity').value;

    if (!name || !email || !phone || !address || !city) {
        showNotification('¡Por favor, rellena todos los campos!');
        return;
    }

    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = 'block';
    document.querySelectorAll('.step')[0].classList.remove('active');
    document.querySelectorAll('.step')[1].classList.add('active');
    updateSummary();
    initPayPalButtons();
    initStripeButton();

    // Meta Pixel: AddPaymentInfo — details step -> payment step transition.
    if (typeof fbq === 'function') {
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        fbq('track', 'AddPaymentInfo', { value: subtotal + calcCartShippingJS(cart), currency: 'EUR' });
    }
}

function backToDetails() {
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step1').style.display = 'block';
    document.querySelectorAll('.step')[1].classList.remove('active');
    document.querySelectorAll('.step')[0].classList.add('active');
}

function updateSummary() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const shipping = calcCartShippingJS(cart); // always 0 — store-wide free shipping
    const total = subtotal + shipping;
    const subEl = document.getElementById('summarySubtotal');
    const totEl = document.getElementById('summaryTotal');
    const shipEl = document.getElementById('summaryShipping');
    const breakdownEl = document.getElementById('summaryShippingBreakdown');
    if (subEl) subEl.textContent = formatPrice(subtotal);
    if (shipEl) shipEl.textContent = 'Gratis';
    if (totEl) totEl.textContent = formatPrice(total);
    // Per-item shipping breakdown removed — every line would now redundantly
    // say "Gratis" since shipping is free store-wide; the single "Envío:
    // Gratis" row above already says everything that needs saying.
    if (breakdownEl) breakdownEl.innerHTML = '';
}


async function placeOrder() {
    const orderID = '#KW' + Math.floor(1000 + Math.random() * 9000);
    document.getElementById('orderID').textContent = orderID;

    const cartSnapshot = [...cart];
    sendCJOrderNotification(orderID, cartSnapshot).catch(() => {
        console.warn('CJ Dropshipping notification failed.');
    });

    document.getElementById('step2').style.display = 'none';
    document.getElementById('step3').style.display = 'block';
    document.querySelectorAll('.step')[1].classList.remove('active');
    document.querySelectorAll('.step')[2].classList.add('active');
    cart = [];
    saveCart();
    updateCart();
}

// Slider (solo en home)
let currentSlide = 0;
const slides = document.querySelectorAll('.slide');
const dots = document.querySelectorAll('.dot');

function showSlide(index) {
    if (!slides.length) return;
    slides.forEach(s => s.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));
    slides[index].classList.add('active');
    if (dots[index]) dots[index].classList.add('active');
    currentSlide = index;
}

function changeSlide(direction) {
    if (!slides.length) return;
    let newIndex = currentSlide + direction;
    if (newIndex >= slides.length) newIndex = 0;
    if (newIndex < 0) newIndex = slides.length - 1;
    showSlide(newIndex);
}

function goToSlide(index) { showSlide(index); }

// Auto slide solo si hay slides
if (slides.length) setInterval(() => changeSlide(1), 5000);

// Notificación
function showNotification(msg) {
    const notif = document.createElement('div');
    notif.style.cssText = `position: fixed; top: 100px; right: 30px; background: linear-gradient(135deg, #ff6b35, #f7931e); color: white; padding: 15px 25px; border-radius: 10px; box-shadow: 0 10px 30px rgba(255,107,53,0.5); z-index: 10000; animation: slideIn 0.3s ease;`;
    notif.textContent = msg;
    document.body.appendChild(notif);
    setTimeout(() => notif.remove(), 2500);
}

// Scroll suave a productos
function scrollToProducts() {
    const el = document.getElementById('products');
    if (el) {
        el.scrollIntoView({behavior: 'smooth'});
    } else {
        window.location.href = '/index.html#products';
    }
}

// Header al hacer scroll
window.addEventListener('scroll', () => {
    const header = document.querySelector('.header');
    if (header) header.style.background = window.scrollY > 50 ? 'rgba(10,14,39,0.95)' : 'rgba(10,14,39,0.85)';
});

// Efecto 3D con el ratón
const heroEl = document.querySelector('.hero');
if (heroEl) {
    heroEl.addEventListener('mousemove', (e) => {
        const heroImg = document.querySelector('.slide.active .floating-3d');
        if (heroImg) {
            const x = (e.clientX / window.innerWidth - 0.5) * 20;
            const y = (e.clientY / window.innerHeight - 0.5) * 20;
            heroImg.style.transform = `rotateY(${x}deg) rotateX(${-y}deg)`;
        }
    });
}

// Chatbot Widget
const chatConfig = {
    welcomeText: '¡Hola! 👋 Soy el asistente de KhurmiStore. ¿En qué puedo ayudarte?',
    answers: {
        envio: 'Envío gratis en todos los pedidos, sin mínimo de compra. Entrega en 2-5 días laborables en toda España.',
        pagos: 'Aceptamos tarjeta, PayPal y transferencia. Pago 100% seguro con cifrado SSL.',
        devoluciones: 'Tienes 14 días para devolver cualquier producto. Reembolso completo tras recibir el artículo.',
        categorias: 'Tenemos muchas categorías: electrónica, informática, hogar, belleza, ropa y más. ¡Explóralas en el menú!',
        contacto: 'Puedes llamarnos al +34 662 24 18 60 o escribirnos a info@khurmistore.es'
    },
    fallback: 'Lo siento, no entendí tu pregunta. ¿Quieres hablar con nosotros por WhatsApp?',
    whatsappUrl: 'https://wa.me/34662241860'
};

const chatKeywords = [
    { topic: 'envio', keywords: ['envío', 'envio', 'shipping', 'entrega', 'pedido'] },
    { topic: 'pagos', keywords: ['pago', 'pagos', 'payment', 'paypal', 'tarjeta', 'transferencia', 'bizum'] },
    { topic: 'devoluciones', keywords: ['devolución', 'devoluciones', 'devolucion', 'reembolso', 'devolver'] },
    { topic: 'categorias', keywords: ['categoría', 'categorias', 'categoria', 'menu', 'menú', 'categorías', 'categorias'] },
    { topic: 'contacto', keywords: ['contacto', 'teléfono', 'telefono', 'email', 'correo', 'llamar', 'whatsapp'] }
];

function toggleChatWidget() {
    const panel = document.getElementById('chatWidgetPanel');
    if (!panel) return;
    const open = !panel.classList.contains('active');
    panel.classList.toggle('active', open);
    panel.setAttribute('aria-hidden', open ? 'false' : 'true');
    if (open) {
        const body = document.getElementById('chatWidgetBody');
        if (body && body.innerHTML.trim() === '') {
            showChatGreeting();
        }
    }
}

function closeChatWidget() {
    const panel = document.getElementById('chatWidgetPanel');
    if (!panel) return;
    panel.classList.remove('active');
    panel.setAttribute('aria-hidden', 'true');
}

function appendChatMessage(text, type = 'bot') {
    const body = document.getElementById('chatWidgetBody');
    if (!body) return;
    const wrapper = document.createElement('div');
    wrapper.className = `message ${type}`;
    wrapper.innerHTML = `<span>${text}</span>`;
    body.appendChild(wrapper);
    body.scrollTop = body.scrollHeight;
}

function renderQuickReplies() {
    const body = document.getElementById('chatWidgetBody');
    if (!body) return;
    const row = document.createElement('div');
    row.className = 'button-row';
    const topics = [
        { key: 'envio', label: 'Envío' },
        { key: 'pagos', label: 'Pagos' },
        { key: 'devoluciones', label: 'Devoluciones' },
        { key: 'categorias', label: 'Categorías' },
        { key: 'contacto', label: 'Contacto' }
    ];
    topics.forEach(topic => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'quick-reply-btn';
        button.textContent = topic.label;
        button.addEventListener('click', () => handleChatReply(topic.key));
        row.appendChild(button);
    });
    body.appendChild(row);
    body.scrollTop = body.scrollHeight;
}

function showChatGreeting() {
    appendChatMessage(chatConfig.welcomeText, 'bot');
    renderQuickReplies();
}

function handleChatReply(topic) {
    const label = topic === 'envio' ? 'Envío' : topic === 'pagos' ? 'Pagos' : topic === 'devoluciones' ? 'Devoluciones' : topic === 'categorias' ? 'Categorías' : 'Contacto';
    appendChatMessage(label, 'user');
    appendBotResponse(chatConfig.answers[topic]);
}

function handleChatSubmit(event) {
    event.preventDefault();
    const input = document.getElementById('chatMessageInput');
    if (!input) return;
    const text = input.value.trim();
    if (!text) return;
    appendChatMessage(text, 'user');
    input.value = '';
    const response = getChatResponse(text);
    appendBotResponse(response);
}

function appendBotResponse(message) {
    appendChatMessage(message, 'bot');
}

function getChatResponse(message) {
    const text = message.toLowerCase();
    for (const rule of chatKeywords) {
        if (rule.keywords.some(keyword => text.includes(keyword))) {
            return chatConfig.answers[rule.topic];
        }
    }
    return `${chatConfig.fallback} <div class="button-row"><a class="quick-reply-btn" href="${chatConfig.whatsappUrl}" target="_blank" rel="noopener noreferrer">Abrir WhatsApp</a></div>`;
}

function initChatWidget() {
    const toggle = document.getElementById('chatWidgetToggle');
    const closeBtn = document.getElementById('chatWidgetClose');
    const form = document.getElementById('chatForm');
    if (toggle) toggle.addEventListener('click', toggleChatWidget);
    if (closeBtn) closeBtn.addEventListener('click', closeChatWidget);
    if (form) form.addEventListener('submit', handleChatSubmit);
}

// Inicializar
initChatWidget();
loadCart();
renderProducts();
updateCart();

// Auto-open checkout when redirected from a page without a checkout modal
if (new URLSearchParams(window.location.search).get('checkout') === '1') {
    setTimeout(openCheckout, 200);
}

// Estilo de animación
const style = document.createElement('style');
style.textContent = `@keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }`;
document.head.appendChild(style);

// ===== BARRA DE ANUNCIOS =====
// Only shift the header down if this page actually has an announcement bar —
// pages where it was removed (see the "beware of imitators" banner cleanup)
// must not get the extra top spacing with nothing occupying it.
if (document.getElementById('announcementBar')) {
    document.body.classList.add('announcement-active');
}

function closeAnnouncement() {
    document.getElementById('announcementBar')?.classList.add('hidden');
    document.body.classList.remove('announcement-active');
}

// ===== TEMPORIZADOR OFERTA FLASH =====
function updateCountdown() {
    let saleEnd = localStorage.getItem('saleEnd');
    if (!saleEnd) {
        saleEnd = new Date().getTime() + (24 * 60 * 60 * 1000);
        localStorage.setItem('saleEnd', saleEnd);
    }

    const now = new Date().getTime();
    const distance = saleEnd - now;

    if (distance < 0) {
        localStorage.setItem('saleEnd', new Date().getTime() + (24 * 60 * 60 * 1000));
        return;
    }

    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

    const hEl = document.getElementById('hours');
    const mEl = document.getElementById('minutes');
    const sEl = document.getElementById('seconds');
    if (hEl) hEl.textContent = String(hours).padStart(2, '0');
    if (mEl) mEl.textContent = String(minutes).padStart(2, '0');
    if (sEl) sEl.textContent = String(seconds).padStart(2, '0');
}

setInterval(updateCountdown, 1000);
updateCountdown();

function closeFlashSale() {
    document.getElementById('flashSale')?.classList.add('hidden');
}

// ===== AVISO DE COOKIES =====
function loadAnalytics() {
    const s = document.createElement('script');
    s.src = 'https://analytics.ahrefs.com/analytics.js';
    s.setAttribute('data-key', 'vgjsV0Kb5bzhjglesAk7VQ');
    s.async = true;
    document.head.appendChild(s);
}

function loadPixel() {
    if (window._fbPixelLoaded) return;
    window._fbPixelLoaded = true;
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){
        n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;
        s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s);
    }(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init','27064969309833667');
    fbq('track','PageView');
}

window.addEventListener('load', () => {
    const consent = localStorage.getItem('kw_cookie_consent');
    if (consent === 'accepted') {
        loadAnalytics();
        loadPixel();
    } else if (consent !== 'rejected') {
        setTimeout(() => {
            document.getElementById('cookieConsent')?.classList.remove('hidden');
        }, 1000);
    }
});

function acceptCookies() {
    localStorage.setItem('kw_cookie_consent', 'accepted');
    document.getElementById('cookieConsent')?.classList.add('hidden');
    loadAnalytics();
    loadPixel();
}

function declineCookies() {
    localStorage.setItem('kw_cookie_consent', 'rejected');
    document.getElementById('cookieConsent')?.classList.add('hidden');
}

// ===== PÁGINA DE DETALLES DEL PRODUCTO =====
function getProductDetails(p) {
    // Devuelve detalles enriquecidos para cualquier producto
    const defaults = {
        description: `${p.name} - Producto premium de KhurmiStore con la mejor relación calidad-precio. Diseñado con materiales de alta calidad para ofrecerte una experiencia única. Garantía de 1 año incluida.`,
        features: [
            "Calidad Premium garantizada",
            "Envío rápido desde España",
            "Garantía de fábrica 1 año",
            "Devolución 14 días"
        ],
        gallery: [p.image, p.image.replace('w=500', 'w=800'), p.image, p.image],
        stock: Math.floor(Math.random() * 40) + 10,
        brand: "KhurmiStore"
    };
    return {
        ...defaults,
        ...p,
        gallery: p.gallery || defaults.gallery,
        features: p.features || defaults.features,
        description: p.description || defaults.description,
        stock: p.stock !== undefined ? p.stock : defaults.stock,
        brand: p.brand || defaults.brand
    };
}

function renderProductDetails() {
    const container = document.getElementById('productDetailsContainer');
    if (!container) return;

    const urlParams = new URLSearchParams(window.location.search);
    const productId = parseInt(urlParams.get('id'));
    const product = products.find(p => p.id === productId);

    if (!product) {
        container.innerHTML = `
            <div class="not-found">
                <i class="fas fa-exclamation-triangle"></i>
                <h2>Producto no encontrado</h2>
                <p>El producto que buscas no existe.</p>
                <a href="index.html" class="btn-primary">Volver al Inicio</a>
            </div>`;
        return;
    }

    const p = getProductDetails(product);
    const discount = p.oldPrice ? Math.round(((p.oldPrice - p.price) / p.oldPrice) * 100) : 0;
    const inStock  = p.stock > 0 && p.isActive !== false; // unchanged — still drives the add-to-cart/button-disable logic below
    // Never show the raw stock count to the visitor — only a 3-tier status.
    const lowStock = inStock && p.stock <= 5;
    const stockBadgeClass = !inStock ? 'low-stock' : (lowStock ? 'low-stock' : 'in-stock');
    const stockBadgeIcon  = !inStock ? 'times-circle' : (lowStock ? 'exclamation-circle' : 'check-circle');
    const stockBadgeText  = !inStock ? 'Agotado' : (lowStock ? 'Últimas unidades' : 'En stock');

    // Top 2 selling points as a compact above-the-fold teaser — the full
    // list still renders in the "Características" section below. Read from
    // the RAW product object, not p (getProductDetails()'s enriched copy),
    // since that always falls back to 4 generic bullets when a product has
    // no real features — which would defeat the "omit if empty" rule below.
    const rawFeatures = Array.isArray(product.features) && product.features.length > 0 ? product.features : [];
    const topFeatures = rawFeatures.slice(0, 2);

    // Video thumbnail (from BigBuy's video_id, if any) — inserted as the 2nd
    // thumbnail so it stays prominent without displacing the default main
    // image (gallery[0], still shown/active first). No-op when videoId is
    // null/empty — gallery behaves exactly as before in that case.
    const galleryThumbs = p.gallery.map((img, i) => `
        <div class="thumb ${i === 0 ? 'active' : ''}" onclick="changeMainImage('${img}', this)">
            <img src="${img}" alt="${p.name} vista ${i+1}">
        </div>
    `);
    if (p.videoId) {
        galleryThumbs.splice(1, 0, `
            <div class="thumb thumb-video" id="thumbVideo" onclick="playProductVideo('${p.videoId}', this)">
                <img src="https://img.youtube.com/vi/${p.videoId}/hqdefault.jpg" alt="${p.name} vídeo">
                <span class="thumb-play-icon"><i class="fas fa-play"></i></span>
            </div>
        `);
    }

    container.innerHTML = `
        <div class="breadcrumb">
            <a href="/"><i class="fas fa-home"></i> Inicio</a>
            <i class="fas fa-chevron-right"></i>
            <a href="/categoria.php">Productos</a>
            <i class="fas fa-chevron-right"></i>
            <a href="/categoria.php?cat=${encodeURIComponent(p.category)}">${p.category.charAt(0).toUpperCase() + p.category.slice(1)}</a>
            <i class="fas fa-chevron-right"></i>
            <span class="current">${p.name}</span>
        </div>

        <div class="product-detail-grid">
            <div class="product-gallery">
                <div class="main-image" id="mainImageWrap">
                    ${discount ? `<span class="discount-badge">-${discount}%</span>` : ''}
                    <img id="mainProductImg" src="${p.gallery[0]}" alt="${p.name}">
                    ${p.videoId ? `
                    <button type="button" class="watch-video-btn" onclick="playProductVideo('${p.videoId}', document.getElementById('thumbVideo'))">
                        <i class="fas fa-play"></i> Ver vídeo
                    </button>
                    ` : ''}
                </div>
                <div class="main-image main-video-wrap" id="mainProductVideoWrap" style="display:none;">
                    <iframe id="mainProductVideoFrame" src="" title="${p.name} vídeo" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                </div>
                ${(p.gallery.length > 1 || p.videoId) ? `
                <div class="thumbnail-list">
                    ${galleryThumbs.join('')}
                </div>
                ` : ''}
            </div>

            <div class="product-info-detail">
                <span class="detail-brand">${p.brand}</span>
                <h1>${p.name}</h1>

                <div class="price-section">
                    <span class="current-price">${formatPrice(p.price)}</span>
                    ${p.oldPrice ? `<span class="old-price">${formatPrice(p.oldPrice)}</span>` : ''}
                    ${discount ? `<span class="discount-tag">Ahorras ${formatPrice(p.oldPrice - p.price)}</span>` : ''}
                </div>

                <span class="stock-badge ${stockBadgeClass}" style="margin:0 0 14px;">
                    <i class="fas fa-${stockBadgeIcon}"></i>
                    ${stockBadgeText}
                </span>

                ${topFeatures.length ? `
                <ul class="product-hook-list">
                    ${topFeatures.map(f => `<li><i class="fas fa-check-circle"></i> ${f}</li>`).join('')}
                </ul>
                ` : ''}

                <div class="quantity-selector">
                    <h4>Cantidad:</h4>
                    <div class="qty-box">
                        <button onclick="changeDetailQty(-1)">-</button>
                        <input type="number" id="detailQty" value="1" min="1" max="${p.stock}">
                        <button onclick="changeDetailQty(1)">+</button>
                    </div>
                </div>

                <div class="action-buttons">
                    <button class="btn-primary big-btn" onclick="addToCartFromDetails(${p.id})" ${inStock ? '' : 'disabled'}>
                        <i class="fas fa-cart-plus"></i> ${inStock ? 'Añadir al Carrito' : 'Agotado'}
                    </button>
                    <button class="btn-secondary big-btn" onclick="buyNow(${p.id})" ${inStock ? '' : 'disabled'}>
                        <i class="fas fa-bolt"></i> ${inStock ? 'Comprar Ahora' : 'No disponible'}
                    </button>
                    <button class="wishlist-btn" onclick="toggleWishlist(${p.id}, this)"><i class="far fa-heart"></i></button>
                </div>

                <div class="trust-row">
                    <span><i class="fas fa-lock"></i> Pago 100% seguro</span>
                    <span><i class="fas fa-truck-fast"></i> Envío gratis desde España</span>
                    <span><i class="fas fa-rotate-left"></i> Devoluciones en 14 días</span>
                    <span><i class="fas fa-shield-halved"></i> Garantía 12 meses</span>
                    <span><i class="fas fa-headset"></i> Atención al cliente en España</span>
                </div>
            </div>
        </div>

        ${rawFeatures.length ? `
        <div class="product-section">
            <h2>Características</h2>
            <ul class="feature-list">
                ${rawFeatures.map(f => `<li><i class="fas fa-check-circle"></i> ${f}</li>`).join('')}
            </ul>
        </div>
        ` : ''}

        <div class="product-section">
            <h2>Descripción</h2>
            <div class="product-description">${p.description}</div>
        </div>

        <div class="product-section">
            <h2>Envío y devoluciones</h2>
            <p><i class="fas fa-truck"></i> <strong>Envío estándar:</strong> 2-5 días laborables — GRATIS, sin mínimo</p>
            <p><i class="fas fa-rotate-left"></i> <strong>Devolución:</strong> 14 días (derecho de desistimiento)</p>
            <p><i class="fas fa-globe"></i> <strong>Cobertura:</strong> España peninsular y Baleares</p>
        </div>

        <div class="faq-accordion">
            <div class="faq-item" data-faq-index="0">
                <button class="faq-question" type="button" aria-expanded="false" onclick="toggleFAQ(0)">
                    <strong>¿Cuánto tarda el envío?</strong>
                    <span class="toggle-icon">+</span>
                </button>
                <div class="faq-answer" id="faqAnswer0">
                    El envío estándar llega en 2-5 días laborables en España. Envío gratis siempre, sin mínimo de compra.
                </div>
            </div>
            <div class="faq-item" data-faq-index="1">
                <button class="faq-question" type="button" aria-expanded="false" onclick="toggleFAQ(1)">
                    <strong>¿Qué garantía ofrece este producto?</strong>
                    <span class="toggle-icon">+</span>
                </button>
                <div class="faq-answer" id="faqAnswer1">
                    Incluye garantía oficial de 12 meses y soporte posventa de KhurmiStore para cualquier consulta.
                </div>
            </div>
            <div class="faq-item" data-faq-index="2">
                <button class="faq-question" type="button" aria-expanded="false" onclick="toggleFAQ(2)">
                    <strong>¿Puedo cambiarlo o devolverlo?</strong>
                    <span class="toggle-icon">+</span>
                </button>
                <div class="faq-answer" id="faqAnswer2">
                    Sí, tienes 14 días para cambios o devoluciones gratuitas siempre que el producto llegue en buen estado.
                </div>
            </div>
        </div>

        <div class="related-section">
            <div class="section-header">
                <span class="subtitle">TAMBIÉN TE PUEDE GUSTAR</span>
                <h2>Productos <span>Relacionados</span></h2>
            </div>
            <div class="related-grid">
                ${products.filter(rp => rp.category === p.category && rp.id !== p.id).slice(0, 4).map(rp => `
                    <div class="product-card" onclick="goToProduct(${rp.id})">
                        <div class="product-img">
                            <img src="${rp.image}" alt="${rp.name}">
                        </div>
                        <div class="product-info">
                            <h3>${rp.name}</h3>
                            <p class="product-cat">${getCategoryName(rp.category)}</p>
                            <span class="badge-nuevo">Nuevo</span>
                            <div class="product-price">
                                <span class="price">${formatPrice(rp.price)}</span>
                                <button class="add-cart" onclick="event.stopPropagation(); addToCart(${rp.id})"><i class="fas fa-cart-plus"></i></button>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;

    // Actualizar título de la página
    document.title = `${p.name} - KhurmiStore`;
    injectProductFAQSchema(p);
}

function injectProductFAQSchema(p) {
    const schemaId = 'faqSchemaScript';
    const existing = document.getElementById(schemaId);
    if (existing) existing.remove();

    const faqData = {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "¿Cuánto tarda el envío?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "El envío estándar para este producto llega en 2-5 días laborables en España. Envío gratis siempre, sin mínimo de compra."
                }
            },
            {
                "@type": "Question",
                "name": "¿Qué garantía ofrece este producto?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Incluye garantía oficial de 12 meses y soporte posventa de KhurmiStore para cualquier consulta."
                }
            },
            {
                "@type": "Question",
                "name": "¿Puedo cambiarlo o devolverlo?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Sí, tienes 14 días para cambios o devoluciones gratuitas siempre que el producto llegue en buen estado."
                }
            }
        ]
    };

    const schema = document.createElement('script');
    schema.id = schemaId;
    schema.type = 'application/ld+json';
    schema.text = JSON.stringify(faqData, null, 2);
    document.head.appendChild(schema);
}

function changeMainImage(src, thumb) {
    const main = document.getElementById('mainProductImg');
    if (main) main.src = src;
    hideProductVideo();
    document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
}

function playProductVideo(videoId, thumb) {
    const imgWrap   = document.getElementById('mainImageWrap');
    const videoWrap = document.getElementById('mainProductVideoWrap');
    const frame     = document.getElementById('mainProductVideoFrame');
    if (!imgWrap || !videoWrap || !frame) return;
    frame.src = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
    imgWrap.style.display = 'none';
    videoWrap.style.display = 'block';
    document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
    if (thumb) thumb.classList.add('active');
}

function hideProductVideo() {
    const imgWrap   = document.getElementById('mainImageWrap');
    const videoWrap = document.getElementById('mainProductVideoWrap');
    const frame     = document.getElementById('mainProductVideoFrame');
    if (videoWrap) videoWrap.style.display = 'none';
    if (frame) frame.src = ''; // stops playback when switching away
    if (imgWrap) imgWrap.style.display = '';
}

function changeDetailQty(change) {
    const input = document.getElementById('detailQty');
    if (!input) return;
    let val = parseInt(input.value) + change;
    const max = parseInt(input.max);
    if (val < 1) val = 1;
    if (val > max) val = max;
    input.value = val;
}

function toggleFAQ(index) {
    const item = document.querySelector(`.faq-item[data-faq-index="${index}"]`);
    if (!item) return;
    const question = item.querySelector('.faq-question');
    const answer = item.querySelector('.faq-answer');
    const isActive = question.classList.contains('active');

    document.querySelectorAll('.faq-question').forEach(q => {
        q.classList.remove('active');
        q.setAttribute('aria-expanded', 'false');
    });
    document.querySelectorAll('.faq-answer').forEach(a => a.classList.remove('active'));

    if (!isActive) {
        question.classList.add('active');
        question.setAttribute('aria-expanded', 'true');
        answer.classList.add('active');
    }
}

function addToCartFromDetails(id) {
    const qty = parseInt(document.getElementById('detailQty').value) || 1;
    addToCart(id, qty);
}

function buyNow(id) {
    const qty = parseInt(document.getElementById('detailQty').value) || 1;
    addToCart(id, qty);
    setTimeout(() => openCart(), 400);
}

function toggleWishlist(id, btn) {
    const icon = btn.querySelector('i');
    if (icon.classList.contains('far')) {
        icon.classList.remove('far');
        icon.classList.add('fas');
        btn.classList.add('active');
        showNotification('❤️ ¡Añadido a favoritos!');
    } else {
        icon.classList.remove('fas');
        icon.classList.add('far');
        btn.classList.remove('active');
        showNotification('Eliminado de favoritos');
    }
}

// Renderizar detalles si estamos en la página de detalles
renderProductDetails();

// ===== MOBILE MENU DRAWER LOGIC =====
document.addEventListener('DOMContentLoaded', () => {
    const menuToggle = document.getElementById('mobileMenuToggle');
    const menuClose = document.getElementById('mobileNavClose');
    const menuDrawer = document.getElementById('mobileNavDrawer');
    const menuOverlay = document.getElementById('mobileNavOverlay');

    // The "Categorías" rows themselves (one independent accordion row per
    // top-level category, inserted by initCategoryMenus() — see
    // CATEGORY_TREE above) are populated into #mobileCategoryNavSlot; each
    // row's own toggleMobileCatPanel() handles its own expand/collapse, so
    // no extra wiring is needed here.
    const menuLinks = document.querySelectorAll('.mobile-nav-links a');

    function openMenu() {
        menuDrawer.classList.add('active');
        menuOverlay.classList.add('active');
        menuToggle.classList.add('active');
        menuToggle.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
        menuDrawer.classList.remove('active');
        menuOverlay.classList.remove('active');
        menuToggle.classList.remove('active');
        menuToggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    if (menuToggle) menuToggle.addEventListener('click', openMenu);
    if (menuClose) menuClose.addEventListener('click', closeMenu);
    if (menuOverlay) menuOverlay.addEventListener('click', closeMenu);

    menuLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            closeMenu();
            if (href && href.startsWith('#') && href.length > 1) {
                e.preventDefault();
                setTimeout(() => {
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }, 300);
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && menuDrawer && menuDrawer.classList.contains('active')) {
            closeMenu();
        }
    });
});
// ===== FUNCIONALIDAD DE BÚSQUEDA =====

// Variable global para almacenar el término de búsqueda actual
let currentSearchTerm = '';

/**
 * Abre la modal de búsqueda
 */
function openSearch() {
    const overlay = document.getElementById('searchOverlay');
    if (overlay) {
        overlay.classList.add('active');
        const input = document.getElementById('searchInput');
        if (input) {
            input.focus();
            // No limpiar la búsqueda anterior para continuidad
        }
        // Prevenir scroll del body cuando la modal está abierta
        document.body.style.overflow = 'hidden';
        // Scroll a la sección de productos
        setTimeout(() => {
            const productsSection = document.getElementById('products');
            if (productsSection) {
                productsSection.scrollIntoView({behavior: 'smooth'});
            }
        }, 300);
    }
}

/**
 * Cierra la modal de búsqueda
 */
function closeSearch(event) {
    // Si el evento viene del overlay, solo cerrar si se hace clic en el overlay mismo
    if (event && event.target.id !== 'searchOverlay') {
        return;
    }
    
    const overlay = document.getElementById('searchOverlay');
    if (overlay) {
        overlay.classList.remove('active');
        // Restaurar scroll del body
        document.body.style.overflow = 'auto';
    }
}

/**
 * Realiza la búsqueda de productos en tiempo real
 * Filtra los productos en el grid y muestra/oculta según coincidencia
 */
function performSearch(query) {
    currentSearchTerm = query.trim().toLowerCase();
    
    // Obtener el contenedor de productos
    const productsGrid = document.getElementById('productsGrid');
    if (!productsGrid) return;

    // Obtener todas las tarjetas de productos
    const productCards = productsGrid.querySelectorAll('.product-card');
    let visibleCount = 0;

    // Iterar sobre cada tarjeta de producto
    productCards.forEach(card => {
        // Obtener el nombre del producto desde la tarjeta
        const productName = card.querySelector('h3')?.textContent.toLowerCase() || '';
        
        // Verificar si el nombre coincide con la búsqueda
        const matches = !currentSearchTerm || productName.includes(currentSearchTerm);
        
        // Mostrar u ocultar la tarjeta
        if (matches) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Mostrar u ocultar el mensaje de "No se encontraron productos"
    let noResultsMessage = productsGrid.querySelector('.no-search-results');
    
    if (visibleCount === 0 && currentSearchTerm) {
        // Si no hay resultados y hay búsqueda activa, mostrar mensaje
        if (!noResultsMessage) {
            noResultsMessage = document.createElement('div');
            noResultsMessage.className = 'no-search-results';
            noResultsMessage.innerHTML = `
                <div class="no-results-content">
                    <i class="fas fa-search"></i>
                    <h3>No se encontraron productos</h3>
                    <p>Intenta con otro término de búsqueda</p>
                </div>
            `;
            productsGrid.appendChild(noResultsMessage);
        }
        noResultsMessage.style.display = 'flex';
    } else if (noResultsMessage) {
        // Ocultar el mensaje si hay resultados o si la búsqueda está vacía
        noResultsMessage.style.display = 'none';
    }
}

/**
 * Muestra los resultados de búsqueda en la modal (para navegación desde búsqueda)
 */
function displaySearchResults(results) {
    const resultsContainer = document.getElementById('searchResults');
    if (!resultsContainer) return;

    // Si no hay resultados, mostrar mensaje
    if (results.length === 0) {
        resultsContainer.innerHTML = `
            <div class="search-no-results">
                <i class="fas fa-search"></i>
                <h3>No se encontraron productos</h3>
                <p>Intenta con otro término de búsqueda</p>
            </div>
        `;
        return;
    }

    // Mostrar resultados como lista
    resultsContainer.innerHTML = results.map(product => `
        <div class="search-result-item" onclick="goToProduct(${product.id})">
            <div class="search-result-img">
                <img src="${product.image}" alt="${product.name}">
            </div>
            <div class="search-result-info">
                <div class="search-result-name">${product.name}</div>
                <div class="search-result-category">${getCategoryName(product.category)}</div>
                <div class="search-result-price">${formatPrice(product.price)}</div>
            </div>
        </div>
    `).join('');
}

// ===== INICIALIZAR LISTENERS DE BÚSQUEDA =====

document.addEventListener('DOMContentLoaded', () => {
    // Escuchar entrada en el campo de búsqueda
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            performSearch(e.target.value);
        });
    }

    // Cerrar búsqueda y modales al presionar Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeSearch();
            closeBenefitModal();
        }
    });

    // Cerrar búsqueda al hacer clic en el overlay
    const searchOverlay = document.getElementById('searchOverlay');
    if (searchOverlay) {
        searchOverlay.addEventListener('click', (e) => {
            if (e.target.id === 'searchOverlay') {
                closeSearch(e);
            }
        });
    }

    // Cerrar búsqueda al hacer clic en el botón X
    const closeBtn = document.querySelector('.search-close-btn');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeSearch);
    }

    // Abrir modal de beneficio al pulsar los cards
    document.querySelectorAll('.feature-box.benefit-card').forEach(card => {
        card.addEventListener('click', () => openBenefitModal(card.dataset.benefit));
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openBenefitModal(card.dataset.benefit);
            }
        });
    });

    const benefitModal = document.getElementById('benefitModal');
    if (benefitModal) {
        benefitModal.addEventListener('click', (e) => {
            if (e.target.id === 'benefitModal') {
                closeBenefitModal();
            }
        });
    }

    const closeBenefitBtn = document.getElementById('closeBenefitModal');
    if (closeBenefitBtn) {
        closeBenefitBtn.addEventListener('click', closeBenefitModal);
    }
});

// PayPal Smart Buttons
let paypalButtonsRendered = false;

function initPayPalButtons() {
    if (paypalButtonsRendered) return;
    if (!window.paypal || !document.getElementById('paypal-button-container')) return;
    paypalButtonsRendered = true;
    paypal.Buttons({
        fundingSource: paypal.FUNDING.PAYPAL,
        style: { color: 'blue', shape: 'rect', label: 'pay', height: 45 },
        createOrder: function(data, actions) {
            const subtotal = cart.reduce((sum, i) => sum + (i.price * i.qty), 0);
            const total = (subtotal + calcCartShippingJS(cart)).toFixed(2);
            return actions.order.create({
                purchase_units: [{
                    amount: { value: total, currency_code: 'EUR' },
                    description: 'Pedido KhurmiStore'
                }]
            });
        },
        onApprove: async function(data, actions) {
            const details  = await actions.order.capture();

            // Datos del formulario
            const name    = document.getElementById('custName')?.value.trim()    || '';
            const email   = document.getElementById('custEmail')?.value.trim()   || '';
            const phone   = document.getElementById('custPhone')?.value.trim()   || '';
            const address = document.getElementById('custAddress')?.value.trim() || '';
            const city    = document.getElementById('custCity')?.value.trim()    || '';
            const postal  = document.getElementById('custPostal')?.value.trim()  || '';
            const notes   = document.getElementById('custNotes')?.value.trim()   || '';
            const subtotal     = cart.reduce((sum, i) => sum + (i.price * i.qty), 0);
            const shippingCost = calcCartShippingJS(cart);
            const total        = parseFloat((subtotal + shippingCost).toFixed(2));

            // Datos confirmados por PayPal (más fiables que el formulario)
            const payerName  = details.payer?.name
                ? (details.payer.name.given_name + ' ' + details.payer.name.surname).trim()
                : name;
            const payerEmail = details.payer?.email_address || email;
            const shipping   = details.purchase_units?.[0]?.shipping?.address;
            const fullAddress = shipping
                ? [
                    shipping.address_line_1,
                    shipping.address_line_2,
                    shipping.admin_area_2,
                    shipping.postal_code,
                    shipping.country_code,
                  ].filter(Boolean).join(', ')
                : [address, city, postal].filter(Boolean).join(', ');

            // 1. Guardar pedido en servidor — SIEMPRE, antes de WhatsApp y confirmación
            let orderId = details.id; // fallback if save_order.php's response is unavailable
            try {
                const saveRes  = await fetch('save_order.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        paypalId : details.id,
                        name     : payerName,
                        email    : payerEmail,
                        phone,
                        address  : fullAddress,
                        total,
                        shippingAmount: shippingCost,
                        products : cart.map(i => ({ name: i.name, qty: i.qty, price: i.price })),
                        notes,
                    }),
                });
                const saveData = await saveRes.json();
                if (saveData?.orderId) { orderId = saveData.orderId; }
            } catch (e) {
                console.error('save_order.php error:', e);
            }

            // 2. Facebook Pixel — eventID matches capi.php's eventIdForOrder()
            // so Meta dedupes this against the server-side Conversions API event.
            fbq('track', 'Purchase', { value: total, currency: 'EUR' }, { eventID: 'purchase_' + details.id });

            // 3. WhatsApp al dueño (puede estar bloqueado; el pedido ya está guardado)
            const items = cart.map(i => `• ${i.name} x${i.qty} - ${formatPrice(i.price * i.qty)}`).join('\n');
            const msg   = `✅ PEDIDO PAGADO con PayPal - KhurmiStore\n\nID Transacción: ${details.id}\n\nProductos:\n${items}\n\nSUBTOTAL: ${formatPrice(subtotal)}\nENVÍO: ${formatPrice(shippingCost)}\nTOTAL COBRADO: ${formatPrice(total)}\n\nCliente:\nNombre: ${name}\nTeléfono: ${phone}\nDirección: ${address}\nCiudad: ${city}\nCódigo Postal: ${postal}\nNotas: ${notes}`;
            window.open('https://wa.me/34662241860?text=' + encodeURIComponent(msg), '_blank');

            // 4. Pantalla de confirmación
            const orderID = '#KW' + Math.floor(1000 + Math.random() * 9000);
            document.getElementById('orderID').textContent = orderID;
            document.getElementById('step2').style.display = 'none';
            document.getElementById('step3').style.display = 'block';
            document.querySelectorAll('.step')[1].classList.remove('active');
            document.querySelectorAll('.step')[2].classList.add('active');
            cart = [];
            saveCart();
            updateCart();
        },
        onError: function(err) {
            console.error('PayPal error:', err);
            showNotification('Error al procesar el pago. Por favor, inténtalo de nuevo o elige otro método.');
        }
    }).render('#paypal-button-container');
}

// Stripe Checkout (hosted redirect) — alternative to PayPal, same cart/customer
// data. Creates a Checkout Session server-side, then redirects the browser to
// Stripe's hosted payment page; PayPal keeps working exactly as before.
let stripeButtonWired = false;

function initStripeButton() {
    if (stripeButtonWired) return;
    const btn = document.getElementById('stripeCheckoutBtn');
    if (!btn) return;
    stripeButtonWired = true;

    btn.addEventListener('click', async function () {
        if (cart.length === 0) {
            showNotification('¡Tu carrito está vacío!');
            return;
        }

        // Datos del formulario (mismos campos que recoge el flujo de PayPal)
        const name    = document.getElementById('custName')?.value.trim()    || '';
        const email   = document.getElementById('custEmail')?.value.trim()   || '';
        const phone   = document.getElementById('custPhone')?.value.trim()   || '';
        const address = document.getElementById('custAddress')?.value.trim() || '';
        const city    = document.getElementById('custCity')?.value.trim()    || '';
        const notes   = document.getElementById('custNotes')?.value.trim()   || '';

        const originalLabel = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirigiendo a Stripe...';

        try {
            const res = await fetch('stripe_checkout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name, email, phone, address, city, notes,
                    products: cart.map(i => ({ name: i.name, qty: i.qty, price: i.price, weight: i.weight ?? null })),
                }),
            });
            const result = await res.json();

            if (result.url) {
                window.location = result.url;
                return; // leaving the page — no need to restore the button
            }
            showNotification(result.error || 'No se pudo iniciar el pago con tarjeta.');
        } catch (e) {
            console.error('stripe_checkout.php error:', e);
            showNotification('Error al conectar con Stripe. Por favor, inténtalo de nuevo.');
        }

        btn.disabled = false;
        btn.innerHTML = originalLabel;
    });
}

// Stripe redirect landing (?pago=exitoso|cancelado|fallido on the homepage,
// set by stripe_checkout.php's cancel_url and stripe_return.php's redirects).
// Mirrors what PayPal's onApprove already does on success: clear the cart
// and show the same confirmation banner.
(function handleStripeRedirect() {
    const params = new URLSearchParams(window.location.search);
    const pago = params.get('pago');
    if (!pago) return;

    if (pago === 'exitoso') {
        cart = [];
        saveCart();
        updateCart();
        showOrderConfirmation('¡Gracias! Tu pago con tarjeta se ha completado. Te contactaremos pronto para confirmar el envío.');
    } else if (pago === 'cancelado') {
        showNotification('Pago cancelado. Tu carrito sigue guardado.');
    } else if (pago === 'fallido') {
        showNotification('No se pudo verificar el pago. Si el cargo se realizó, contáctanos.');
    }

    // Clean the URL so refreshing the page doesn't re-trigger this.
    params.delete('pago');
    const query  = params.toString();
    const newUrl = window.location.pathname + (query ? '?' + query : '') + window.location.hash;
    window.history.replaceState({}, '', newUrl);
})();

// ===== FREE SHIPPING POPUP — once per session, any page that loads script.js =====
(function () {
    const STORAGE_KEY = 'kw_free_shipping_popup_seen';
    if (sessionStorage.getItem(STORAGE_KEY)) return;
    // Mark as seen immediately (not on close) — this must fire only once per
    // session total, not once per page the user happens to close it on.
    sessionStorage.setItem(STORAGE_KEY, '1');

    function showFreeShippingPopup() {
        const popup = document.createElement('div');
        popup.className = 'free-shipping-popup';
        popup.id = 'freeShippingPopup';
        popup.innerHTML = `
            <button type="button" class="free-shipping-popup-close" aria-label="Cerrar"><i class="fas fa-times"></i></button>
            <div class="free-shipping-popup-icon"><i class="fas fa-truck-fast"></i></div>
            <div class="free-shipping-popup-text">
                <strong>¡Envío GRATIS!</strong>
                <span>a toda España en todos los pedidos</span>
            </div>
        `;
        document.body.appendChild(popup);
        requestAnimationFrame(() => popup.classList.add('active'));

        popup.querySelector('.free-shipping-popup-close').addEventListener('click', () => {
            popup.classList.remove('active');
            setTimeout(() => popup.remove(), 400);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => setTimeout(showFreeShippingPopup, 1200));
    } else {
        setTimeout(showFreeShippingPopup, 1200);
    }
})();

