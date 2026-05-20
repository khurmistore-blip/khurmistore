const CJ_DROPSHIPPING_CONFIG = {
    // Use a relative endpoint so the browser will call the same origin
    // (avoids cross-origin preflight when the page and proxy run together)
    proxyEndpoint: '/cj-order',
    logisticName: 'PostNL',
    fromCountryCode: 'CN',
    platform: 'shopify',
    shopLogisticsType: 1,
    storageId: '201e67f6ba4644c0a36d63bf4989dd70'
};

function buildCJOrderPayload(orderId, cartItems) {
    const name = document.getElementById('custName')?.value.trim() || '';
    const email = document.getElementById('custEmail')?.value.trim() || '';
    const phone = document.getElementById('custPhone')?.value.trim() || '';
    const address = document.getElementById('custAddress')?.value.trim() || '';
    const city = document.getElementById('custCity')?.value.trim() || '';
    const postal = document.getElementById('custPostal')?.value.trim() || '';

    return {
        orderNumber: orderId,
        shippingZip: postal,
        shippingCountry: 'Spain',
        shippingCountryCode: 'ES',
        shippingProvince: '',
        shippingCity: city,
        shippingCounty: '',
        shippingPhone: phone,
        shippingCustomerName: name,
        shippingAddress: address,
        shippingAddress2: '',
        taxId: '',
        remark: 'Pedido confirmado desde el sitio web',
        email: email,
        consigneeID: '',
        payType: '',
        shopAmount: '',
        logisticName: CJ_DROPSHIPPING_CONFIG.logisticName,
        fromCountryCode: CJ_DROPSHIPPING_CONFIG.fromCountryCode,
        houseNumber: '',
        iossType: '',
        platform: CJ_DROPSHIPPING_CONFIG.platform,
        iossNumber: '',
        shopLogisticsType: CJ_DROPSHIPPING_CONFIG.shopLogisticsType,
        storageId: CJ_DROPSHIPPING_CONFIG.storageId,
        products: cartItems.map(item => ({
            vid: item.vid || `AUTO-${item.id}`,
            quantity: item.qty || 1,
            storeLineItemId: item.storeLineItemId || `lineItem-${item.id}-${Date.now()}`
        }))
    };
}

async function sendCJOrderNotification(orderId, cartItems) {
    if (!cartItems || !cartItems.length) {
        console.warn('No cart items available to send to CJ Dropshipping.');
        return;
    }

    const payload = buildCJOrderPayload(orderId, cartItems);

    try {
        const response = await fetch(CJ_DROPSHIPPING_CONFIG.proxyEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        console.log('CJ Order Notification Response:', result);

        if (!response.ok) {
            showNotification('Error al notificar pedido a CJ Dropshipping.');
        } else {
            showNotification('Pedido enviado a CJ Dropshipping.');
        }

        return result;
    } catch (error) {
        console.error('CJ Dropshipping API request failed:', error);
        showNotification('No se pudo conectar con CJ Dropshipping.');
        throw error;
    }
}
