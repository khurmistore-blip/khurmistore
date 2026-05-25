// Catálogo de Productos - KhurmiStore España
const products = [
    // Auriculares (Headphones)
    { id: 1, name: "Auriculares Inalámbricos Premium", category: "headphones", price: 49.99, image: "https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500&q=80", tag: "HOT", rating: 5 },
    { id: 2, name: "Auriculares Sony Studio", category: "headphones", price: 69.99, image: "https://images.unsplash.com/photo-1583394838336-acd977736f90?w=500&q=80", tag: "NUEVO", rating: 5 },
    { id: 3, name: "Auriculares Bass Boost", category: "headphones", price: 29.99, image: "https://images.unsplash.com/photo-1484704849700-f032a568e944?w=500&q=80", tag: "-20%", rating: 4 },
    { id: 4, name: "Noise Cancel Pro", category: "headphones", price: 89.99, image: "https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=500&q=80", tag: "PREMIUM", rating: 5 },
    { id: 5, name: "Auriculares Gaming RGB", category: "headphones", price: 44.99, image: "https://images.unsplash.com/photo-1618366712010-f4ae9c647dcb?w=500&q=80", tag: "GAMING", rating: 5 },
    { id: 31, name: "Auriculares Deportivos Pro", category: "headphones", price: 34.99, image: "https://images.unsplash.com/photo-1577174881658-0f30ed549adc?w=500&q=80", tag: "DEPORTE", rating: 4 },

    // Relojes Inteligentes (Smart Watches)
    { id: 6, name: "Smartwatch Pro Series", category: "smartwatch", price: 79.99, image: "https://images.unsplash.com/photo-1546868871-7041f2a55e12?w=500&q=80", tag: "NUEVO", rating: 5 },
    { id: 7, name: "Reloj Fitness Tracker", category: "smartwatch", price: 44.99, image: "https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?w=500&q=80", tag: "TENDENCIA", rating: 5 },
    { id: 8, name: "Reloj Estilo Clásico", category: "smartwatch", price: 119.99, image: "https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=500&q=80", tag: "PREMIUM", rating: 5 },
    { id: 9, name: "Reloj Deportivo Smart", category: "smartwatch", price: 34.99, image: "https://images.unsplash.com/photo-1579586337278-3befd40fd17a?w=500&q=80", tag: "DEPORTE", rating: 4 },
    { id: 10, name: "Reloj Digital Clásico", category: "smartwatch", price: 24.99, image: "https://images.unsplash.com/photo-1434056886845-dac89ffe9b56?w=500&q=80", tag: "OFERTA", rating: 4 },
    { id: 32, name: "Smartwatch GPS Avanzado", category: "smartwatch", price: 99.99, image: "https://images.unsplash.com/photo-1617043786394-f977fa12eddf?w=500&q=80", tag: "NUEVO", rating: 5 },

    // Auriculares Inalámbricos (Earpods)
    { id: 11, name: "Earpods Pro Inalámbricos", category: "earpods", price: 29.99, image: "https://images.unsplash.com/photo-1606220588913-b3aacb4d2f46?w=500&q=80", tag: "-20%", rating: 4 },
    { id: 12, name: "Estilo AirPods Max", category: "earpods", price: 54.99, image: "https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=500&q=80", tag: "HOT", rating: 5 },
    { id: 13, name: "Buds Pro Wireless", category: "earpods", price: 27.99, image: "https://images.unsplash.com/photo-1572569511254-d8f925fe2cbb?w=500&q=80", tag: "NUEVO", rating: 5 },
    { id: 14, name: "Earpods Gaming", category: "earpods", price: 39.99, image: "https://images.unsplash.com/photo-1600294037681-c80b4cb5b434?w=500&q=80", tag: "GAMING", rating: 4 },
    { id: 15, name: "Mini Earbuds Compactos", category: "earpods", price: 17.99, image: "https://images.unsplash.com/photo-1606220588913-b3aacb4d2f46?w=500&q=80", tag: "OFERTA", rating: 4 },
    { id: 33, name: "Earbuds con ANC", category: "earpods", price: 59.99, image: "https://images.unsplash.com/photo-1585386959984-a4155224a1ad?w=500&q=80", tag: "PREMIUM", rating: 5 },

    // Fundas de Móvil (Mobile Covers)
    { id: 16, name: "Funda Premium iPhone", category: "covers", price: 12.99, image: "https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?w=500&q=80", tag: "OFERTA", rating: 5 },
    { id: 17, name: "Funda Transparente Samsung", category: "covers", price: 7.99, image: "https://images.unsplash.com/photo-1556656793-08538906a9f8?w=500&q=80", tag: "HOT", rating: 4 },
    { id: 18, name: "Funda de Cuero Tipo Libro", category: "covers", price: 17.99, image: "https://images.unsplash.com/photo-1592434134753-a70baf7979d5?w=500&q=80", tag: "PREMIUM", rating: 5 },
    { id: 19, name: "Funda Magnética MagSafe", category: "covers", price: 11.99, image: "https://images.unsplash.com/photo-1586953208448-b95a79798f07?w=500&q=80", tag: "NUEVO", rating: 4 },
    { id: 34, name: "Funda Antigolpes Reforzada", category: "covers", price: 14.99, image: "https://images.unsplash.com/photo-1565849904461-04a58ad377e0?w=500&q=80", tag: "HOT", rating: 5 },

    // Cascos Gaming (Head Gear)
    { id: 20, name: "Casco Gaming RGB Pro", category: "headgear", price: 59.99, image: "https://images.unsplash.com/photo-1599669454699-248893623440?w=500&q=80", tag: "GAMING", rating: 5 },
    { id: 21, name: "Visor VR Realidad Virtual", category: "headgear", price: 139.99, image: "https://images.unsplash.com/photo-1593508512255-86ab42a8e620?w=500&q=80", tag: "PREMIUM", rating: 5 },
    { id: 22, name: "Casco Gaming Profesional", category: "headgear", price: 84.99, image: "https://images.unsplash.com/photo-1591370874773-6702e8f12fd8?w=500&q=80", tag: "PRO", rating: 5 },
    { id: 35, name: "Casco E-Sports Surround 7.1", category: "headgear", price: 74.99, image: "https://images.unsplash.com/photo-1612287230202-1ff1d85d1bdf?w=500&q=80", tag: "NUEVO", rating: 5 },

    // Manos Libres (Hands Free)
    { id: 23, name: "Manos Libres Bluetooth", category: "handsfree", price: 13.99, image: "https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=500&q=80", tag: "HOT", rating: 4 },
    { id: 24, name: "Neckband Inalámbrico", category: "handsfree", price: 22.99, image: "https://images.unsplash.com/photo-1574920162043-b872873f19c8?w=500&q=80", tag: "NUEVO", rating: 5 },
    { id: 25, name: "Manos Libres Ejecutivo", category: "handsfree", price: 36.99, image: "https://images.unsplash.com/photo-1606400082777-ef05f3c5cde2?w=500&q=80", tag: "PREMIUM", rating: 5 },
    { id: 26, name: "Manos Libres Deportivo", category: "handsfree", price: 18.99, image: "https://images.unsplash.com/photo-1629367494173-c78a56567877?w=500&q=80", tag: "DEPORTE", rating: 4 },

    // Ratones (Mouse)
    { id: 27, name: "Ratón Gaming Inalámbrico", category: "mouse", price: 27.99, image: "https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=500&q=80", tag: "NUEVO", rating: 5 },
    { id: 28, name: "Ratón Gaming RGB Pro", category: "mouse", price: 44.99, image: "https://images.unsplash.com/photo-1563297007-0686b7003af7?w=500&q=80", tag: "GAMING", rating: 5 },
    { id: 29, name: "Ratón Ergonómico Oficina", category: "mouse", price: 18.99, image: "https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?w=500&q=80", tag: "OFICINA", rating: 4 },
    { id: 30, name: "Ratón Compacto Viaje", category: "mouse", price: 9.99, image: "https://images.unsplash.com/photo-1527814050087-3793815479db?w=500&q=80", tag: "OFERTA", rating: 4 },
    { id: 36, name: "Ratón Vertical Antiestres", category: "mouse", price: 32.99, image: "https://images.unsplash.com/photo-1563770660941-20978e870e26?w=500&q=80", tag: "NUEVO", rating: 5 },

    // ===== 10 NUEVOS PRODUCTOS AÑADIDOS =====
    {
        id: 37, name: "Auriculares DJ Profesional XBass", category: "headphones", price: 119.99, oldPrice: 159.99,
        image: "https://images.unsplash.com/photo-1558756520-22cfe5d382ca?w=500&q=80",
        gallery: [
            "https://images.unsplash.com/photo-1558756520-22cfe5d382ca?w=800&q=80",
            "https://images.unsplash.com/photo-1545127398-14699f92334b?w=800&q=80",
            "https://images.unsplash.com/photo-1583394838336-acd977736f90?w=800&q=80",
            "https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=800&q=80"
        ],
        tag: "NUEVO", rating: 5, stock: 24, brand: "KhurmiStore Pro",
        colors: ["#000000", "#ff6b35", "#004e89"],
        description: "Auriculares DJ profesionales con sonido de estudio, bajos potentes y aislamiento total del ruido. Perfectos para DJs, productores musicales y amantes del audio de alta fidelidad.",
        features: [
            "Drivers de 50mm con sonido Hi-Fi",
            "Bluetooth 5.3 con baja latencia",
            "Hasta 40 horas de batería",
            "Cancelación activa de ruido (ANC)",
            "Carga rápida USB-C (10 min = 5h)",
            "Diadema acolchada de espuma viscoelástica"
        ]
    },
    {
        id: 38, name: "Smartwatch Ultra GPS Sport", category: "smartwatch", price: 149.99, oldPrice: 199.99,
        image: "https://images.unsplash.com/photo-1551816230-ef5deaed4a26?w=500&q=80",
        gallery: [
            "https://images.unsplash.com/photo-1551816230-ef5deaed4a26?w=800&q=80",
            "https://images.unsplash.com/photo-1546868871-7041f2a55e12?w=800&q=80",
            "https://images.unsplash.com/photo-1617043786394-f977fa12eddf?w=800&q=80",
            "https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?w=800&q=80"
        ],
        tag: "HOT", rating: 5, stock: 18, brand: "KhurmiStore Ultra",
        colors: ["#1a1a1a", "#c0c0c0", "#ff6b35"],
        description: "Smartwatch deportivo con GPS integrado, monitor de salud avanzado y pantalla AMOLED de 1.91\". Diseñado para atletas que quieren llevar su rendimiento al siguiente nivel.",
        features: [
            "Pantalla AMOLED 1.91\" siempre encendida",
            "GPS dual integrado",
            "Monitor de oxígeno SpO2 y ECG",
            "Resistencia al agua 10ATM",
            "Más de 120 modos deportivos",
            "Batería de 14 días de duración"
        ]
    },
    {
        id: 39, name: "AirBuds Pro Plus ANC", category: "earpods", price: 49.99, oldPrice: 79.99,
        image: "https://images.unsplash.com/photo-1606741965326-cb990ae01bb2?w=500&q=80",
        gallery: [
            "https://images.unsplash.com/photo-1606741965326-cb990ae01bb2?w=800&q=80",
            "https://images.unsplash.com/photo-1572569511254-d8f925fe2cbb?w=800&q=80",
            "https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=800&q=80",
            "https://images.unsplash.com/photo-1606220588913-b3aacb4d2f46?w=800&q=80"
        ],
        tag: "TENDENCIA", rating: 5, stock: 56, brand: "KhurmiStore Audio",
        colors: ["#ffffff", "#000000", "#ff6b35"],
        description: "AirBuds Pro Plus con cancelación activa de ruido, sonido espacial 3D y estuche de carga inalámbrica. La libertad inalámbrica que siempre quisiste.",
        features: [
            "Cancelación activa de ruido (ANC)",
            "Audio espacial 3D Dolby",
            "Bluetooth 5.3 multi-conexión",
            "Hasta 32h con estuche",
            "Carga inalámbrica Qi",
            "Resistencia al agua IPX5"
        ]
    },
    {
        id: 40, name: "Funda iPad Pro Magnética 360°", category: "covers", price: 24.99, oldPrice: 39.99,
        image: "https://images.unsplash.com/photo-1585790050230-5dd28404ccb9?w=500&q=80",
        gallery: [
            "https://images.unsplash.com/photo-1585790050230-5dd28404ccb9?w=800&q=80",
            "https://images.unsplash.com/photo-1592434134753-a70baf7979d5?w=800&q=80",
            "https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?w=800&q=80",
            "https://images.unsplash.com/photo-1586953208448-b95a79798f07?w=800&q=80"
        ],
        tag: "NUEVO", rating: 4, stock: 42, brand: "KhurmiStore Cover",
        colors: ["#000000", "#964B00", "#0066cc", "#cc0000"],
        description: "Funda magnética para iPad Pro con cierre inteligente, soporte multi-ángulo y compartimento para Apple Pencil. Hecha de piel sintética premium.",
        features: [
            "Compatible iPad Pro 11\"/12.9\"",
            "Cierre magnético inteligente",
            "Activación/apagado automático",
            "Soporte para Apple Pencil",
            "Soporte multi-ángulo",
            "Material de cuero PU premium"
        ]
    },
    {
        id: 41, name: "Casco Gaming Pro 7.1 Surround RGB", category: "headgear", price: 99.99, oldPrice: 139.99,
        image: "https://images.unsplash.com/photo-1599669454699-248893623440?w=500&q=80",
        gallery: [
            "https://images.unsplash.com/photo-1599669454699-248893623440?w=800&q=80",
            "https://images.unsplash.com/photo-1599669454699-248893623440?w=800&q=80",
            "https://images.unsplash.com/photo-1591370874773-6702e8f12fd8?w=800&q=80",
            "https://images.unsplash.com/photo-1612287230202-1ff1d85d1bdf?w=800&q=80"
        ],
        tag: "GAMING", rating: 5, stock: 15, brand: "KhurmiStore Gaming",
        colors: ["#000000", "#ff0000", "#00ff00"],
        description: "Casco gaming con sonido envolvente 7.1, iluminación RGB personalizable y micrófono con cancelación de ruido. Domina cada partida.",
        features: [
            "Sonido envolvente 7.1 virtual",
            "Iluminación RGB 16.8M colores",
            "Micrófono con cancelación de ruido",
            "Drivers de neodimio 50mm",
            "Compatible PC, PS5, Xbox, Switch",
            "Almohadillas memory foam"
        ]
    },
    {
        id: 42, name: "Pinganillo Bluetooth Empresarial", category: "handsfree", price: 28.99, oldPrice: 44.99,
        image: "https://images.unsplash.com/photo-1606400082777-ef05f3c5cde2?w=500&q=80",
        gallery: [
            "https://images.unsplash.com/photo-1606400082777-ef05f3c5cde2?w=800&q=80",
            "https://images.unsplash.com/photo-1606400082777-ef05f3c5cde2?w=800&q=80",
            "https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=800&q=80",
            "https://images.unsplash.com/photo-1574920162043-b872873f19c8?w=800&q=80"
        ],
        tag: "NUEVO", rating: 5, stock: 33, brand: "KhurmiStore Office",
        colors: ["#000000", "#c0c0c0"],
        description: "Pinganillo Bluetooth empresarial con doble micrófono, cancelación de ruido y hasta 12h de conversación. Perfecto para profesionales en movimiento.",
        features: [
            "Doble micrófono con cancelación",
            "Hasta 12h de conversación",
            "Bluetooth 5.2 multi-punto",
            "Compatible con Siri/Google",
            "Solo 4g de peso ultraligero",
            "Estuche de carga 24h extra"
        ]
    },
    {
        id: 43, name: "Ratón Gaming Pro Inalámbrico 26K DPI", category: "mouse", price: 54.99, oldPrice: 79.99,
        image: "https://images.unsplash.com/photo-1629429407759-01cd3d7cfb38?w=500&q=80",
        gallery: [
            "https://images.unsplash.com/photo-1629429407759-01cd3d7cfb38?w=800&q=80",
            "https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=800&q=80",
            "https://images.unsplash.com/photo-1563297007-0686b7003af7?w=800&q=80",
            "https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?w=800&q=80"
        ],
        tag: "GAMING", rating: 5, stock: 22, brand: "KhurmiStore Gaming",
        colors: ["#000000", "#ffffff"],
        description: "Ratón gaming inalámbrico de alta precisión con sensor óptico 26000 DPI, 8 botones programables e iluminación RGB. Para gamers profesionales y e-sports.",
        features: [
            "Sensor óptico PixArt 26000 DPI",
            "Inalámbrico 2.4GHz + Bluetooth",
            "8 botones programables",
            "Iluminación RGB Chroma",
            "Batería 70h de juego",
            "Solo 80g - ultraligero"
        ]
    },
    {
        id: 44, name: "Auriculares Studio Monitor Hi-Res", category: "headphones", price: 159.99, oldPrice: 219.99,
        image: "https://images.unsplash.com/photo-1487215078519-e21cc028cb29?w=500&q=80",
        gallery: [
            "https://images.unsplash.com/photo-1487215078519-e21cc028cb29?w=800&q=80",
            "https://images.unsplash.com/photo-1546435770-a3e426bf472b?w=800&q=80",
            "https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&q=80",
            "https://images.unsplash.com/photo-1583394838336-acd977736f90?w=800&q=80"
        ],
        tag: "PREMIUM", rating: 5, stock: 12, brand: "KhurmiStore Studio",
        colors: ["#1a1a1a", "#964B00"],
        description: "Auriculares de monitor de estudio con certificación Hi-Res Audio, drivers planares y respuesta de frecuencia ampliada. Pureza de sonido absoluta.",
        features: [
            "Certificación Hi-Res Audio",
            "Drivers magnético-planares 40mm",
            "Respuesta 5Hz - 40kHz",
            "Cable desmontable 3m",
            "Almohadillas terciopelo premium",
            "Estuche rígido incluido"
        ]
    },
    {
        id: 45, name: "Reloj Infantil GPS 4G Tracker", category: "smartwatch", price: 39.99, oldPrice: 69.99,
        image: "https://images.unsplash.com/photo-1542496658-e33a6d0d50f6?w=500&q=80",
        gallery: [
            "https://images.unsplash.com/photo-1542496658-e33a6d0d50f6?w=800&q=80",
            "https://images.unsplash.com/photo-1579586337278-3befd40fd17a?w=800&q=80",
            "https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?w=800&q=80",
            "https://images.unsplash.com/photo-1434056886845-dac89ffe9b56?w=800&q=80"
        ],
        tag: "OFERTA", rating: 4, stock: 38, brand: "KhurmiStore Kids",
        colors: ["#ff69b4", "#0066cc", "#00cc66"],
        description: "Reloj inteligente para niños con GPS 4G en tiempo real, llamadas, SOS y cámara. La tranquilidad que los padres necesitan.",
        features: [
            "GPS 4G localización en tiempo real",
            "Llamadas bidireccionales",
            "Botón SOS de emergencia",
            "Cámara HD frontal",
            "App para padres (iOS/Android)",
            "Resistente al agua IP67"
        ]
    },
    {
        id: 46, name: "Earbuds Traductor IA 40 Idiomas", category: "earpods", price: 79.99, oldPrice: 129.99,
        image: "https://images.unsplash.com/photo-1610438235354-a6ae5528385c?w=500&q=80",
        gallery: [
            "https://images.unsplash.com/photo-1610438235354-a6ae5528385c?w=800&q=80",
            "https://images.unsplash.com/photo-1585386959984-a4155224a1ad?w=800&q=80",
            "https://images.unsplash.com/photo-1610438235354-a6ae5528385c?w=800&q=80",
            "https://images.unsplash.com/photo-1572569511254-d8f925fe2cbb?w=800&q=80"
        ],
        tag: "NUEVO", rating: 5, stock: 19, brand: "KhurmiStore AI",
        colors: ["#ffffff", "#000000"],
        description: "Earbuds revolucionarios con traducción simultánea por IA en 40 idiomas. Habla con cualquiera en el mundo en tiempo real.",
        features: [
            "Traducción IA en 40+ idiomas",
            "Traducción en tiempo real",
            "Bluetooth 5.3 dual",
            "App con conversaciones offline",
            "Hasta 25h con estuche",
            "Audio HD para llamadas claras"
        ]
    }
];

let cart = [];

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
                <div class="product-rating">${'★'.repeat(p.rating)}${'☆'.repeat(5-p.rating)}</div>
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

function getCategoryPageConfig(slug) {
    const config = {
        'bebe': {
            name: 'Bebé',
            description: 'Productos seleccionados para recién nacidos y familias.',
            categories: []
        },
        'belleza': {
            name: 'Belleza',
            description: 'Cuidado personal y tecnología de estilo de vida.',
            categories: ['earpods', 'headphones']
        },
        'coche-y-moto': {
            name: 'Coche y moto',
            description: 'Accesorios ideales para viajes y manos libres.',
            categories: ['handsfree']
        },
        'ropa': {
            name: 'Ropa',
            description: 'Moda y accesorios para tu estilo diario.',
            categories: []
        },
        'informatica': {
            name: 'Informática',
            description: 'Fundas y ratones para tu equipo de trabajo.',
            categories: ['covers', 'mouse']
        },
        'bricolaje-y-herramientas': {
            name: 'Bricolaje y herramientas',
            description: 'Herramientas y accesorios prácticos para el hogar.',
            categories: []
        },
        'electronica': {
            name: 'Electrónica',
            description: 'Los productos tecnológicos más populares de KhurmiStore.',
            categories: ['headphones', 'smartwatch', 'earpods', 'covers', 'headgear', 'handsfree', 'mouse']
        },
        'alimentacion-y-bebidas': {
            name: 'Alimentación y bebidas',
            description: 'Productos para una vida más conveniente y saludable.',
            categories: []
        },
        'jardin': {
            name: 'Jardín',
            description: 'Soluciones para tu espacio exterior y descanso.',
            categories: []
        },
        'salud-y-cuidado-personal': {
            name: 'Salud y cuidado personal',
            description: 'Tecnología enfocada en bienestar y fitness.',
            categories: ['smartwatch', 'earpods']
        },
        'hogar-y-cocina': {
            name: 'Hogar y cocina',
            description: 'Accesorios prácticos para el hogar conectado.',
            categories: ['covers']
        },
        'industria-empresas-y-ciencia': {
            name: 'Industria, empresas y ciencia',
            description: 'Equipamiento y herramientas para profesionales.',
            categories: ['handsfree', 'smartwatch']
        },
        'joyeria': {
            name: 'Joyería',
            description: 'Piezas de estilo con tecnología elegante.',
            categories: ['smartwatch']
        },
        'iluminacion': {
            name: 'Iluminación',
            description: 'Iluminación inteligente para tus espacios.',
            categories: []
        },
        'equipaje': {
            name: 'Equipaje',
            description: 'Accesorios para viajes y movilidad.',
            categories: []
        },
        'oficina-y-papeleria': {
            name: 'Oficina y papelería',
            description: 'Productos para trabajar con estilo y comodidad.',
            categories: ['mouse', 'covers']
        },
        'productos-para-mascotas': {
            name: 'Productos para mascotas',
            description: 'Artículos pensados para el cuidado de tu mascota.',
            categories: []
        },
        'sexo-y-sensualidad': {
            name: 'Sexo y sensualidad',
            description: 'Selección discreta y moderna.',
            categories: []
        },
        'calzado-y-accesorios': {
            name: 'Calzado y accesorios',
            description: 'Accesorios que complementan tu estilo.',
            categories: []
        },
        'deportes-y-aire-libre': {
            name: 'Deportes y aire libre',
            description: 'Gadgets y tecnología para mantenerse activo.',
            categories: ['smartwatch', 'headgear']
        },
        'juguetes-y-juegos': {
            name: 'Juguetes y juegos',
            description: 'Entretenimiento y gadgets para la familia.',
            categories: ['headgear', 'earpods']
        },
        'relojes': {
            name: 'Relojes',
            description: 'Relojes inteligentes y wearables de última generación.',
            categories: ['smartwatch']
        }
    };
    return config[slug];
}

function formatCategorySlug(slug) {
    return slug
        ? slug.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
        : 'Categoría no encontrada';
}

function renderCategoryPage() {
    const categoryTitleHeading = document.getElementById('categoryTitleHeading');
    const categoryTitle = document.getElementById('categoryTitle');
    const categoryDescription = document.getElementById('categoryDescription');
    const productsGrid = document.getElementById('productsGrid');
    const params = new URLSearchParams(window.location.search);
    const slug = params.get('cat');

    if (!categoryTitleHeading || !categoryTitle || !categoryDescription || !productsGrid) return;

    const config = getCategoryPageConfig(slug);
    const title = config ? config.name : formatCategorySlug(slug);
    const description = config ? config.description : 'Lo sentimos, no se reconoce esta categoría. Explora otras opciones desde el menú.';
    const categories = config ? config.categories : [];
    const categoryEmptyMessage = document.getElementById('categoryEmptyMessage');
    const categoryEmptyText = document.getElementById('categoryEmptyText');

    categoryTitleHeading.textContent = title;
    categoryTitle.textContent = title;
    categoryDescription.textContent = description;
    document.title = `${title} | KhurmiStore`;

    const filteredProducts = categories.length ? products.filter(p => categories.includes(p.category)) : [];
    if (filteredProducts.length === 0) {
        productsGrid.innerHTML = '';
        productsGrid.style.display = 'none';
        if (categoryEmptyMessage) categoryEmptyMessage.classList.add('active');
        if (categoryEmptyText) categoryEmptyText.textContent = `Estamos preparando los mejores productos de ${title} para ti. ¡Vuelve pronto!`;
        return;
    }

    if (categoryEmptyMessage) categoryEmptyMessage.classList.remove('active');
    productsGrid.style.display = '';
    productsGrid.innerHTML = filteredProducts.map(p => `
        <div class="product-card" onclick="goToProduct(${p.id})">
            <div class="product-img">
                <span class="product-tag">${p.tag}</span>
                <img src="${p.image}" alt="${p.name}">
                <div class="quick-view"><i class="fas fa-eye"></i> Ver Detalles</div>
            </div>
            <div class="product-info">
                <h3>${p.name}</h3>
                <p class="product-cat">${getCategoryName(p.category)}</p>
                <div class="product-rating">${'★'.repeat(p.rating)}${'☆'.repeat(5-p.rating)}</div>
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

// Navegar a la página de detalles del producto
function goToProduct(id) {
    window.location.href = `product-details.html?id=${id}`;
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
    const existing = cart.find(item => item.id === id);
    if (existing) {
        existing.qty += qty;
    } else {
        cart.push({...product, qty: qty});
    }
    saveCart();
    updateCart();
    showNotification(`¡${product.name} añadido al carrito!`);
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
    const totalPrice = subtotal; // Modify here if shipping or fees are added later

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
    const menuOpen = !menu.classList.contains('active');
    menu.classList.toggle('active');
    overlay.classList.toggle('active', menuOpen);
    button.setAttribute('aria-expanded', menuOpen ? 'true' : 'false');
}

// Toggle the categories dropdown on mobile
function toggleCategoryDropdown(event) {
    const button = event.currentTarget;
    const dropdown = button.closest('.nav-item.dropdown');
    if (!dropdown) return;
    const isOpen = dropdown.classList.toggle('dropdown-open');
    button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

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
    closeCart();
    document.getElementById('checkoutForm')?.reset();
    updateSummary();
    document.getElementById('checkoutModal')?.classList.add('active');
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
    const message = `🛒 NUEVO PEDIDO - Khurmi Store\n\nPedido:\n${itemsText}\n\nTOTAL: ${formatPrice(total)}\n\nDatos del cliente:\nNombre: ${name}\nTeléfono: ${phone}\nDirección: ${address}\nCiudad: ${city}\nCódigo postal: ${postal}\nNotas: ${notes || 'Ninguna'}`;
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
        text: 'Disfruta de envío gratuito en todos los pedidos superiores a 50€. Entrega en 24-72 horas en toda España. Para pedidos menores, se aplica una tarifa de envío estándar.'
    },
    pago: {
        title: 'Pago 100% Seguro',
        text: 'Tus pagos están protegidos con cifrado SSL. Aceptamos tarjeta, PayPal y transferencia. Tu información nunca se comparte con terceros.'
    },
    devolucion: {
        title: 'Devolución Fácil',
        text: 'Dispones de 14 días para devolver cualquier producto sin complicaciones. Reembolso completo una vez recibido el artículo en buen estado.'
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
}

function backToDetails() {
    document.getElementById('step2').style.display = 'none';
    document.getElementById('step1').style.display = 'block';
    document.querySelectorAll('.step')[1].classList.remove('active');
    document.querySelectorAll('.step')[0].classList.add('active');
}

function updateSummary() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const total = subtotal + 4.99;
    const subEl = document.getElementById('summarySubtotal');
    const totEl = document.getElementById('summaryTotal');
    if (subEl) subEl.textContent = formatPrice(subtotal);
    if (totEl) totEl.textContent = formatPrice(total);
}

// Cambio de método de pago
document.querySelectorAll('input[name="payment"]').forEach(radio => {
    radio.addEventListener('change', (e) => {
        const cd = document.getElementById('cardDetails');
        if (cd) cd.style.display = e.target.value === 'card' ? 'block' : 'none';
    });
});

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
        window.location.href = 'index.html#products';
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
    welcomeText: '¡Hola! 👋 Soy el asistente de Khurmi Store. ¿En qué puedo ayudarte?',
    answers: {
        envio: 'Envío gratis en pedidos superiores a 50€. Entrega en 24-72h en toda España.',
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

// Estilo de animación
const style = document.createElement('style');
style.textContent = `@keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }`;
document.head.appendChild(style);

// ===== BARRA DE ANUNCIOS =====
document.body.classList.add('announcement-active');

function closeAnnouncement() {
    document.getElementById('announcementBar')?.classList.add('hidden');
    document.body.classList.remove('announcement-active');
}

// ===== POPUP DE BIENVENIDA =====
window.addEventListener('load', () => {
    if (!sessionStorage.getItem('popupShown')) {
        setTimeout(() => {
            document.getElementById('welcomePopup')?.classList.add('active');
            sessionStorage.setItem('popupShown', 'true');
        }, 2000);
    }
});

function closeWelcomePopup() {
    document.getElementById('welcomePopup')?.classList.remove('active');
}

function subscribeNow() {
    const email = document.getElementById('popupEmail').value;
    if (!email || !email.includes('@')) {
        showNotification('¡Por favor, introduce un correo válido!');
        return;
    }
    showNotification('🎉 ¡Éxito! Revisa tu correo para el código de 20% de descuento');
    closeWelcomePopup();
}

const wp = document.getElementById('welcomePopup');
if (wp) {
    wp.addEventListener('click', (e) => {
        if (e.target.id === 'welcomePopup') {
            closeWelcomePopup();
        }
    });
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
window.addEventListener('load', () => {
    if (!localStorage.getItem('cookieAccepted')) {
        setTimeout(() => {
            document.getElementById('cookieConsent')?.classList.remove('hidden');
        }, 3000);
    } else {
        document.getElementById('cookieConsent')?.classList.add('hidden');
    }
});

function acceptCookies() {
    localStorage.setItem('cookieAccepted', 'true');
    document.getElementById('cookieConsent')?.classList.add('hidden');
    showNotification('✅ ¡Cookies aceptadas!');
}

function declineCookies() {
    document.getElementById('cookieConsent')?.classList.add('hidden');
    showNotification('Cookies rechazadas');
}

// ===== PÁGINA DE DETALLES DEL PRODUCTO =====
function getProductDetails(p) {
    // Devuelve detalles enriquecidos para cualquier producto
    const defaults = {
        description: `${p.name} - Producto premium de KhurmiStore con la mejor relación calidad-precio. Diseñado con materiales de alta calidad para ofrecerte una experiencia única. Garantía de 1 año incluida.`,
        features: [
            "Calidad Premium garantizada",
            "Diseño moderno y ergonómico",
            "Compatible con múltiples dispositivos",
            "Garantía de fábrica 1 año",
            "Envío rápido desde España",
            "Soporte técnico 24/7"
        ],
        gallery: [p.image, p.image.replace('w=500', 'w=800'), p.image, p.image],
        stock: Math.floor(Math.random() * 40) + 10,
        brand: "KhurmiStore",
        colors: ["#000000", "#ff6b35", "#004e89"]
    };
    return {
        ...defaults,
        ...p,
        gallery: p.gallery || defaults.gallery,
        features: p.features || defaults.features,
        description: p.description || defaults.description,
        stock: p.stock !== undefined ? p.stock : defaults.stock,
        brand: p.brand || defaults.brand,
        colors: p.colors || defaults.colors
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

    container.innerHTML = `
        <div class="breadcrumb">
            <a href="index.html"><i class="fas fa-home"></i> Inicio</a>
            <i class="fas fa-chevron-right"></i>
            <a href="index.html#products">Productos</a>
            <i class="fas fa-chevron-right"></i>
            <span>${getCategoryName(p.category)}</span>
            <i class="fas fa-chevron-right"></i>
            <span class="current">${p.name}</span>
        </div>

        <div class="product-detail-grid">
            <div class="product-gallery">
                <div class="main-image">
                    ${discount ? `<span class="discount-badge">-${discount}%</span>` : ''}
                    <span class="product-tag detail-tag">${p.tag}</span>
                    <img id="mainProductImg" src="${p.gallery[0]}" alt="${p.name}">
                </div>
                <div class="thumbnail-list">
                    ${p.gallery.map((img, i) => `
                        <div class="thumb ${i === 0 ? 'active' : ''}" onclick="changeMainImage('${img}', this)">
                            <img src="${img}" alt="${p.name} vista ${i+1}">
                        </div>
                    `).join('')}
                </div>
            </div>

            <div class="product-info-detail">
                <span class="detail-brand">${p.brand}</span>
                <h1>${p.name}</h1>
                <div class="detail-rating">
                    <span class="stars">${'★'.repeat(p.rating)}${'☆'.repeat(5-p.rating)}</span>
                    <span class="rating-count">(${Math.floor(Math.random() * 200) + 50} reseñas)</span>
                    <span class="stock-badge ${p.stock > 5 ? 'in-stock' : 'low-stock'}">
                        <i class="fas fa-${p.stock > 5 ? 'check-circle' : 'exclamation-circle'}"></i>
                        ${p.stock > 5 ? `${p.stock} en stock` : `¡Solo ${p.stock} disponibles!`}
                    </span>
                </div>

                <div class="price-section">
                    <span class="current-price">${formatPrice(p.price)}</span>
                    ${p.oldPrice ? `<span class="old-price">${formatPrice(p.oldPrice)}</span>` : ''}
                    ${discount ? `<span class="discount-tag">Ahorras ${formatPrice(p.oldPrice - p.price)}</span>` : ''}
                </div>

                <p class="product-description">${p.description}</p>

                <div class="color-selector">
                    <h4>Color:</h4>
                    <div class="colors">
                        ${p.colors.map((c, i) => `
                            <span class="color-dot ${i === 0 ? 'active' : ''}" style="background:${c}" onclick="selectColor(this)" title="Color"></span>
                        `).join('')}
                    </div>
                </div>

                <div class="quantity-selector">
                    <h4>Cantidad:</h4>
                    <div class="qty-box">
                        <button onclick="changeDetailQty(-1)">-</button>
                        <input type="number" id="detailQty" value="1" min="1" max="${p.stock}">
                        <button onclick="changeDetailQty(1)">+</button>
                    </div>
                </div>

                <div class="action-buttons">
                    <button class="btn-primary big-btn" onclick="addToCartFromDetails(${p.id})">
                        <i class="fas fa-cart-plus"></i> Añadir al Carrito
                    </button>
                    <button class="btn-secondary big-btn" onclick="buyNow(${p.id})">
                        <i class="fas fa-bolt"></i> Comprar Ahora
                    </button>
                    <button class="wishlist-btn" onclick="toggleWishlist(${p.id}, this)"><i class="far fa-heart"></i></button>
                </div>

                <div class="benefits-grid">
                    <div class="benefit"><i class="fas fa-truck-fast"></i><span>Envío Gratis<br><small>+50€</small></span></div>
                    <div class="benefit"><i class="fas fa-rotate-left"></i><span>Devolución<br><small>14 días</small></span></div>
                    <div class="benefit"><i class="fas fa-shield-halved"></i><span>Garantía<br><small>1 año</small></span></div>
                    <div class="benefit"><i class="fas fa-lock"></i><span>Pago Seguro<br><small>SSL</small></span></div>
                </div>
            </div>
        </div>

        <div class="product-tabs">
            <div class="tab-buttons">
                <button class="tab-btn active" data-tab="features" onclick="switchTab('features', this)">Características</button>
                <button class="tab-btn" data-tab="shipping" onclick="switchTab('shipping', this)">Envío & Devolución</button>
                <button class="tab-btn" data-tab="reviews" onclick="switchTab('reviews', this)">Reseñas</button>
            </div>
            <div class="tab-content">
                <div id="tab-features" class="tab-pane active">
                    <h3>Características Principales</h3>
                    <ul class="feature-list">
                        ${p.features.map(f => `<li><i class="fas fa-check-circle"></i> ${f}</li>`).join('')}
                    </ul>
                </div>
                <div id="tab-shipping" class="tab-pane">
                    <h3>Información de Envío</h3>
                    <p><i class="fas fa-truck"></i> <strong>Envío Estándar:</strong> 2-4 días laborables (Gratis en pedidos +50€)</p>
                    <p><i class="fas fa-shipping-fast"></i> <strong>Envío Express:</strong> 24 horas (+9,99€)</p>
                    <p><i class="fas fa-rotate-left"></i> <strong>Devolución:</strong> 14 días para cambios o devoluciones gratuitas</p>
                    <p><i class="fas fa-globe"></i> <strong>Cobertura:</strong> Toda España peninsular y Baleares</p>
                </div>
                <div id="tab-reviews" class="tab-pane">
                    <h3>Reseñas de Clientes</h3>
                    <div class="review">
                        <div class="review-header"><strong>María G.</strong><span class="stars">★★★★★</span></div>
                        <p>"Excelente producto, llegó rápido y la calidad es increíble. ¡Súper recomendado!"</p>
                    </div>
                    <div class="review">
                        <div class="review-header"><strong>Carlos M.</strong><span class="stars">★★★★★</span></div>
                        <p>"Mejor de lo que esperaba. Relación calidad-precio inmejorable."</p>
                    </div>
                    <div class="review">
                        <div class="review-header"><strong>Ana L.</strong><span class="stars">★★★★☆</span></div>
                        <p>"Muy contenta con la compra, el servicio al cliente es muy bueno."</p>
                    </div>
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
                            <span class="product-tag">${rp.tag}</span>
                            <img src="${rp.image}" alt="${rp.name}">
                        </div>
                        <div class="product-info">
                            <h3>${rp.name}</h3>
                            <p class="product-cat">${getCategoryName(rp.category)}</p>
                            <div class="product-rating">${'★'.repeat(rp.rating)}${'☆'.repeat(5-rp.rating)}</div>
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
}

function changeMainImage(src, thumb) {
    const main = document.getElementById('mainProductImg');
    if (main) main.src = src;
    document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
}

function selectColor(el) {
    document.querySelectorAll('.color-dot').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
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

function switchTab(tabId, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + tabId)?.classList.add('active');
}

// Renderizar detalles si estamos en la página de detalles
renderProductDetails();

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

    renderCategoryPage();
});

