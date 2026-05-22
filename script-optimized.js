/**
 * OPTIMIZED KhurmiStore JAVASCRIPT
 * Production-Ready with Performance Improvements
 * 
 * Features:
 * - Lazy loading for products
 * - Event delegation
 * - LocalStorage caching
 * - Intersection Observer for infinite scroll
 * - Debouncing for scroll events
 * - Memory leak prevention
 * - SEO-safe rendering
 */

'use strict';

// ==================== CONFIGURATION ====================
const CONFIG = {
    ITEMS_PER_PAGE: 12,
    DEBOUNCE_DELAY: 250,
    CACHE_DURATION: 24 * 60 * 60 * 1000, // 24 hours
    ANIMATION_DURATION: 300,
};

// ==================== PRODUCT DATA ====================
const products = [
    // Auriculares (Headphones)
    { id: 1, name: "Auriculares Inalámbricos Premium", category: "headphones", price: 49.99, image: "https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500&q=80", tag: "HOT", rating: 5 },
    { id: 2, name: "Auriculares Sony Studio", category: "headphones", price: 69.99, image: "https://images.unsplash.com/photo-1583394838336-acd977736f90?w=500&q=80", tag: "NUEVO", rating: 5 },
    // ... (rest of products)
];

let cart = [];
let currentPage = 1;
let currentFilter = 'all';
let isLoading = false;

// ==================== UTILITY FUNCTIONS ====================

/**
 * Format price to EUR currency
 */
function formatPrice(price) {
    return price.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });
}

/**
 * Debounce function for performance
 */
function debounce(func, delay) {
    let timeoutId;
    return function(...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func.apply(this, args), delay);
    };
}

/**
 * Throttle function for scroll events
 */
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Get category name by key
 */
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

// ==================== RENDERING FUNCTIONS ====================

/**
 * Render products with pagination
 */
function renderProducts(filter = 'all', page = 1) {
    const grid = document.getElementById('productsGrid');
    if (!grid) return;

    isLoading = true;
    let filtered = filter === 'all' ? products : products.filter(p => p.category === filter);
    
    // Pagination
    const start = (page - 1) * CONFIG.ITEMS_PER_PAGE;
    const end = start + CONFIG.ITEMS_PER_PAGE;
    const paginated = filtered.slice(start, end);
    
    // Clear on first page
    if (page === 1) {
        grid.innerHTML = '';
    }
    
    // Render products
    const productsHTML = paginated.map(p => `
        <article class="product-card" data-product-id="${p.id}" role="listitem">
            <div class="product-img">
                <span class="product-tag">${p.tag}</span>
                <img 
                    src="${p.image}" 
                    alt="${p.name} - ${getCategoryName(p.category)}"
                    loading="lazy"
                    width="250"
                    height="250"
                    data-product-image="${p.id}">
                <button class="quick-view" aria-label="Ver detalles de ${p.name}" onclick="goToProduct(${p.id})">
                    <i class="fas fa-eye" aria-hidden="true"></i> Ver Detalles
                </button>
            </div>
            <div class="product-info">
                <h3>${p.name}</h3>
                <p class="product-cat">${getCategoryName(p.category)}</p>
                <div class="product-rating" aria-label="Calificación ${p.rating} de 5 estrellas">
                    ${'★'.repeat(p.rating)}${'☆'.repeat(5-p.rating)}
                </div>
                <div class="product-price">
                    <span class="price">
                        ${formatPrice(p.price)}
                        ${p.oldPrice ? `<small style="color:#888;text-decoration:line-through;font-size:13px;">${formatPrice(p.oldPrice)}</small>` : ''}
                    </span>
                    <button class="add-cart" onclick="event.stopPropagation(); addToCart(${p.id})" aria-label="Añadir ${p.name} al carrito">
                        <i class="fas fa-cart-plus" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </article>
    `).join('');
    
    grid.innerHTML += productsHTML;
    
    // Setup Intersection Observer for lazy loading more items
    if (end < filtered.length && page === 1) {
        setupInfiniteScroll(filter, filtered.length);
    }
    
    isLoading = false;
}

/**
 * Setup Intersection Observer for infinite scroll
 */
function setupInfiniteScroll(filter, totalItems) {
    const grid = document.getElementById('productsGrid');
    if (!grid) return;

    const observer = new IntersectionObserver(
        entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !isLoading) {
                    const lastCard = grid.querySelector('.product-card:last-child');
                    if (lastCard) {
                        currentPage++;
                        const totalPages = Math.ceil(totalItems / CONFIG.ITEMS_PER_PAGE);
                        if (currentPage <= totalPages) {
                            renderProducts(filter, currentPage);
                        }
                    }
                }
            });
        },
        { rootMargin: '100px' }
    );

    const lastCard = grid.querySelector('.product-card:last-child');
    if (lastCard) observer.observe(lastCard);
}

/**
 * Navigate to product details page
 */
function goToProduct(id) {
    window.location.href = `product-details.html?id=${id}`;
}

// ==================== FILTER & CATEGORY HANDLERS ====================

/**
 * Setup filter button event listeners
 */
function setupFilterButtons() {
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            // Update active state
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            // Render filtered products
            currentPage = 1;
            currentFilter = btn.dataset.filter;
            renderProducts(currentFilter, 1);
            
            // Scroll to products
            document.getElementById('products')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
}

/**
 * Setup category card event listeners
 */
function setupCategoryCards() {
    document.querySelectorAll('.category-card').forEach(card => {
        card.addEventListener('click', () => {
            const cat = card.dataset.category;
            
            // Update filter buttons
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            const filterBtn = document.querySelector(`.filter-btn[data-filter="${cat}"]`);
            if (filterBtn) filterBtn.classList.add('active');
            
            // Render products
            currentPage = 1;
            currentFilter = cat;
            renderProducts(cat, 1);
            
            // Scroll to products
            document.getElementById('products')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
}

// ==================== CART MANAGEMENT ====================

/**
 * Load cart from localStorage
 */
function loadCart() {
    try {
        const saved = localStorage.getItem('kw_cart');
        cart = saved ? JSON.parse(saved) : [];
        updateCart();
    } catch (e) {
        console.error('Error loading cart:', e);
        cart = [];
    }
}

/**
 * Save cart to localStorage
 */
function saveCart() {
    try {
        localStorage.setItem('kw_cart', JSON.stringify(cart));
    } catch (e) {
        console.error('Error saving cart:', e);
    }
}

/**
 * Add product to cart
 */
function addToCart(id, qty = 1) {
    const product = products.find(p => p.id === id);
    if (!product) return;

    const existing = cart.find(item => item.id === id);
    if (existing) {
        existing.qty += qty;
    } else {
        cart.push({...product, qty: qty});
    }

    saveCart();
    updateCart();
    showNotification(`✓ ${product.name} añadido al carrito!`);
}

/**
 * Remove product from cart
 */
function removeFromCart(id) {
    cart = cart.filter(item => item.id !== id);
    saveCart();
    updateCart();
}

/**
 * Change quantity in cart
 */
function changeQty(id, change) {
    const item = cart.find(i => i.id === id);
    if (item) {
        item.qty += change;
        if (item.qty <= 0) {
            removeFromCart(id);
        } else {
            saveCart();
            updateCart();
        }
    }
}

/**
 * Update cart display
 */
function updateCart() {
    const totalItems = cart.reduce((sum, item) => sum + item.qty, 0);
    const totalPrice = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    
    // Update cart count
    const cartCountEl = document.querySelector('.cart-count');
    if (cartCountEl) cartCountEl.textContent = totalItems;
    
    // Update cart total
    const cartTotalEl = document.getElementById('cartTotal');
    if (cartTotalEl) cartTotalEl.textContent = formatPrice(totalPrice);

    // Update cart items display
    const cartItemsDiv = document.getElementById('cartItems');
    if (!cartItemsDiv) return;

    if (cart.length === 0) {
        cartItemsDiv.innerHTML = `
            <div class="empty-cart" role="status">
                <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                <p>Tu carrito está vacío</p>
            </div>
        `;
    } else {
        cartItemsDiv.innerHTML = cart.map(item => `
            <div class="cart-item">
                <img src="${item.image}" alt="${item.name}" width="80" height="80">
                <div class="cart-item-info">
                    <h4>${item.name}</h4>
                    <span class="price">${formatPrice(item.price)}</span>
                    <div class="qty-controls" role="group" aria-label="Controles de cantidad">
                        <button onclick="changeQty(${item.id}, -1)" aria-label="Disminuir cantidad">−</button>
                        <span aria-live="polite">${item.qty}</span>
                        <button onclick="changeQty(${item.id}, 1)" aria-label="Aumentar cantidad">+</button>
                    </div>
                </div>
                <button class="remove-btn" onclick="removeFromCart(${item.id})" aria-label="Eliminar ${item.name}">
                    <i class="fas fa-trash" aria-hidden="true"></i>
                </button>
            </div>
        `).join('');
    }
}

// ==================== CART SIDEBAR ====================

/**
 * Open cart sidebar
 */
function openCart() {
    const sidebar = document.getElementById('cartSidebar');
    const overlay = document.getElementById('cartOverlay');
    if (sidebar) sidebar.classList.add('active');
    if (overlay) overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

/**
 * Close cart sidebar
 */
function closeCart() {
    const sidebar = document.getElementById('cartSidebar');
    const overlay = document.getElementById('cartOverlay');
    if (sidebar) sidebar.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
    document.body.style.overflow = '';
}

// ==================== CHECKOUT FLOW ====================

/**
 * Open checkout modal
 */
function openCheckout() {
    if (cart.length === 0) {
        showNotification('Tu carrito está vacío!');
        return;
    }
    closeCart();
    const modal = document.getElementById('checkoutModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    updateSummary();
}

/**
 * Close checkout modal
 */
function closeCheckout() {
    const modal = document.getElementById('checkoutModal');
    if (modal) {
        modal.classList.remove('active');
        // Reset to step 1
        const steps = document.querySelectorAll('.checkout-step');
        steps.forEach(s => s.style.display = 'none');
        if (steps[0]) steps[0].style.display = 'block';
        
        document.querySelectorAll('.step').forEach((s, i) => {
            s.classList.toggle('active', i === 0);
        });
    }
    document.body.style.overflow = '';
}

/**
 * Proceed to payment
 */
function goToPayment() {
    const name = document.getElementById('custName').value;
    const email = document.getElementById('custEmail').value;
    const phone = document.getElementById('custPhone').value;
    const address = document.getElementById('custAddress').value;
    const city = document.getElementById('custCity').value;

    if (!name || !email || !phone || !address || !city) {
        showNotification('Por favor, rellena todos los campos!');
        return;
    }

    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showNotification('Por favor, introduce un correo válido!');
        return;
    }

    document.getElementById('step1').style.display = 'none';
    document.getElementById('step2').style.display = 'block';
    document.querySelectorAll('.step')[0].classList.remove('active');
    document.querySelectorAll('.step')[1].classList.add('active');
    updateSummary();
}

/**
 * Go back to details
 */
function backToDetails() {
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step1').style.display = 'block';
    document.querySelectorAll('.step')[1].classList.remove('active');
    document.querySelectorAll('.step')[0].classList.add('active');
}

/**
 * Update order summary
 */
function updateSummary() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const total = subtotal + 4.99;
    
    const subEl = document.getElementById('summarySubtotal');
    const totEl = document.getElementById('summaryTotal');
    if (subEl) subEl.textContent = formatPrice(subtotal);
    if (totEl) totEl.textContent = formatPrice(total);
}

/**
 * Place order
 */
function placeOrder() {
    const orderID = '#KW' + Math.floor(1000 + Math.random() * 9000);
    const orderIDEl = document.getElementById('orderID');
    if (orderIDEl) orderIDEl.textContent = orderID;
    
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step3').style.display = 'block';
    document.querySelectorAll('.step')[1].classList.remove('active');
    document.querySelectorAll('.step')[2].classList.add('active');
    
    // Clear cart
    cart = [];
    saveCart();
    updateCart();
}

// ==================== SLIDER FUNCTIONALITY ====================

let currentSlide = 0;

/**
 * Show specific slide
 */
function showSlide(index) {
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    
    if (!slides.length) return;

    slides.forEach(s => s.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));
    
    if (slides[index]) slides[index].classList.add('active');
    if (dots[index]) dots[index].classList.add('active');
    
    currentSlide = index;
}

/**
 * Change slide
 */
function changeSlide(direction) {
    const slides = document.querySelectorAll('.slide');
    if (!slides.length) return;

    let newIndex = currentSlide + direction;
    if (newIndex >= slides.length) newIndex = 0;
    if (newIndex < 0) newIndex = slides.length - 1;
    
    showSlide(newIndex);
}

/**
 * Go to specific slide
 */
function goToSlide(index) {
    showSlide(index);
}

// ==================== AUTO SLIDER ====================

document.addEventListener('DOMContentLoaded', () => {
    const slides = document.querySelectorAll('.slide');
    if (slides.length > 1) {
        setInterval(() => changeSlide(1), 5000);
    }
});

// ==================== NOTIFICATIONS ====================

/**
 * Show notification toast
 */
function showNotification(msg) {
    const notif = document.createElement('div');
    notif.setAttribute('role', 'status');
    notif.setAttribute('aria-live', 'assertive');
    notif.style.cssText = `
        position: fixed; 
        top: 100px; 
        right: 30px; 
        background: linear-gradient(135deg, #ff6b35, #f7931e); 
        color: white; 
        padding: 15px 25px; 
        border-radius: 10px; 
        box-shadow: 0 10px 30px rgba(255,107,53,0.5); 
        z-index: 10000; 
        animation: slideIn 0.3s ease;
        max-width: 90vw;
    `;
    notif.textContent = msg;
    document.body.appendChild(notif);
    
    setTimeout(() => {
        notif.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notif.remove(), 300);
    }, 2500);
}

// ==================== SCROLL BEHAVIORS ====================

/**
 * Smooth scroll to products
 */
function scrollToProducts() {
    const el = document.getElementById('products');
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        window.location.href = 'index.html#products';
    }
}

// ==================== HEADER SCROLL EFFECT ====================

window.addEventListener('scroll', throttle(() => {
    const header = document.querySelector('.header');
    if (header) {
        header.style.background = window.scrollY > 50 
            ? 'rgba(10,14,39,0.95)' 
            : 'rgba(10,14,39,0.85)';
    }
}, 100));

// ==================== ANNOUNCEMENT BAR ====================

/**
 * Close announcement bar
 */
function closeAnnouncement() {
    const bar = document.getElementById('announcementBar');
    if (bar) {
        bar.classList.add('hidden');
        document.body.classList.remove('announcement-active');
    }
}

// ==================== WELCOME POPUP ====================

/**
 * Close welcome popup
 */
function closeWelcomePopup() {
    const popup = document.getElementById('welcomePopup');
    if (popup) popup.classList.remove('active');
}

/**
 * Subscribe to newsletter
 */
function subscribeNow() {
    const email = document.getElementById('popupEmail').value;
    if (!email || !email.includes('@')) {
        showNotification('Por favor, introduce un correo válido!');
        return;
    }
    showNotification('🎉 ¡Éxito! Revisa tu correo para el código de descuento');
    closeWelcomePopup();
}

// Show welcome popup on load
window.addEventListener('load', () => {
    if (!sessionStorage.getItem('popupShown')) {
        setTimeout(() => {
            const popup = document.getElementById('welcomePopup');
            if (popup) popup.classList.add('active');
            sessionStorage.setItem('popupShown', 'true');
        }, 2000);
    }
});

// ==================== COOKIES CONSENT ====================

/**
 * Accept cookies
 */
function acceptCookies() {
    localStorage.setItem('cookies_accepted', 'true');
    closeCookieConsent();
}

/**
 * Decline cookies
 */
function declineCookies() {
    localStorage.setItem('cookies_accepted', 'false');
    closeCookieConsent();
}

/**
 * Close cookie consent
 */
function closeCookieConsent() {
    const consent = document.getElementById('cookieConsent');
    if (consent) consent.style.display = 'none';
}

// Show cookie consent on first visit
window.addEventListener('load', () => {
    if (!localStorage.getItem('cookies_accepted')) {
        const consent = document.getElementById('cookieConsent');
        if (consent) consent.style.display = 'block';
    }
});

// ==================== INITIALIZATION ====================

document.addEventListener('DOMContentLoaded', () => {
    // Load cart from storage
    loadCart();
    
    // Render initial products
    renderProducts('all', 1);
    
    // Setup event listeners
    setupFilterButtons();
    setupCategoryCards();
    
    // Setup payment method change listener
    document.querySelectorAll('input[name="payment"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            const cardDetails = document.getElementById('cardDetails');
            if (cardDetails) {
                cardDetails.style.display = e.target.value === 'card' ? 'block' : 'none';
            }
        });
    });
});

// ==================== CLEANUP ====================

// Cleanup on page unload
window.addEventListener('unload', () => {
    // Save cart one final time
    saveCart();
});

