<?php
/**
 * BigBuy.php — BigBuy API helper class for KhurmiStore.
 * -------------------------------------------------------
 * Drop into your Hostinger PHP backend. Get the API key from
 * BigBuy Control Panel > API.
 *
 * Testing: keep sandbox = true (no real orders, test only).
 * Live:    set sandbox = false once everything works.
 *
 * NOTE: BigBuy updated some endpoints in April 2024. If an endpoint
 * returns 404, check the live docs: https://api.bigbuy.eu/rest/doc/
 */

class BigBuy
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, bool $sandbox = false)
    {
        $this->apiKey  = $apiKey;
        $this->baseUrl = $sandbox
            ? 'https://api.sandbox.bigbuy.eu'
            : 'https://api.bigbuy.eu';
    }

    /**
     * Core request method — every endpoint goes through this.
     */
    private function request(string $method, string $endpoint, ?array $body = null): array
    {
        $url = $this->baseUrl . $endpoint;
        $ch  = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'status' => 0, 'error' => $error];
        }

        $decoded = json_decode($response, true);

        return [
            'success' => $status >= 200 && $status < 300,
            'status'  => $status,
            'data'    => $decoded ?? $response,
        ];
    }

    /* ---------------------------------------------------------------
     *  CONNECTION TEST
     * --------------------------------------------------------------- */

    /** Run this first — if success is true, the key works. */
    public function testConnection(): array
    {
        // categories is a small, safe call to check the connection
        return $this->request('GET', '/rest/catalog/categories.json?isoCode=es');
    }

    /* ---------------------------------------------------------------
     *  CATALOG
     * --------------------------------------------------------------- */

    /** Full catalog (IDs, SKU, prices, category). Large response. */
    public function getAllProducts(string $iso = 'es'): array
    {
        return $this->request('GET', "/rest/catalog/products.json?isoCode=$iso");
    }

    /** Single product detail: name, description, attributes. */
    public function getProductInfo(int $productId, string $iso = 'es'): array
    {
        return $this->request('GET', "/rest/catalog/productinformation/$productId.json?isoCode=$iso");
    }

    /** Product info by SKU. */
    public function getProductInfoBySku(string $sku, string $iso = 'es'): array
    {
        return $this->request('GET', "/rest/catalog/productinformationbysku/$sku.json?isoCode=$iso");
    }

    /**
     * Catalog object for a product — contains wholesalePrice, sku, retailPrice.
     * (getProductInfo gives name/description; getProduct gives price.)
     */
    public function getProduct(int $productId, string $iso = 'es'): array
    {
        return $this->request('GET', "/rest/catalog/product/$productId.json?isoCode=$iso");
    }

    /** Real-time stock for one product. */
    public function getProductStock(int $productId): array
    {
        return $this->request('GET', "/rest/catalog/productstock/$productId.json");
    }

    /** All images for a product. */
    public function getProductImages(int $productId): array
    {
        return $this->request('GET', "/rest/catalog/productimages/$productId.json");
    }

    /** Category tree (Spanish). */
    public function getCategories(string $iso = 'es'): array
    {
        return $this->request('GET', "/rest/catalog/categories.json?isoCode=$iso");
    }

    /* ---------------------------------------------------------------
     *  SHIPPING
     * --------------------------------------------------------------- */

    /**
     * Get shipping cost before creating an order.
     * See the $order example in createOrder() below.
     */
    public function getShippingCost(array $order): array
    {
        return $this->request('POST', '/rest/shipping/orders.json', ['order' => $order]);
    }

    /* ---------------------------------------------------------------
     *  ORDER (dropshipping fulfillment)
     * --------------------------------------------------------------- */

    /** Validate an order before creating it — catches errors without placing it. */
    public function checkOrder(array $order): array
    {
        return $this->request('POST', '/rest/order/check.json', ['order' => $order]);
    }

    /**
     * Create a real dropshipping order (BigBuy ships directly to the customer).
     *
     * $order = [
     *   'internalReference' => 'KHURMI-1023',   // your own order number
     *   'language'          => 'es',
     *   'paymentMethod'     => 'moneybox',       // pay from wallet (recommended)
     *   'carriers'          => [ ['name' => 'correos'] ], // or leave for cheapest
     *   'shippingAddress'   => [
     *       'firstName'   => 'Juan',
     *       'lastName'    => 'Garcia',
     *       'country'     => 'ES',
     *       'postcode'    => '28001',
     *       'town'        => 'Madrid',
     *       'address'     => 'Calle Falsa 123',
     *       'phone'       => '600000000',
     *       'email'       => 'cliente@email.com',
     *       'comment'     => '',
     *   ],
     *   'products' => [
     *       ['reference' => 'S1234567', 'quantity' => 1], // reference = BigBuy SKU
     *   ],
     * ];
     */
    public function createOrder(array $order): array
    {
        return $this->request('POST', '/rest/order/create.json', ['order' => $order]);
    }

    /* ---------------------------------------------------------------
     *  TRACKING
     * --------------------------------------------------------------- */

    /** Tracking for one order (by BigBuy order ID). */
    public function getOrderTracking(int $orderId): array
    {
        return $this->request('GET', "/rest/tracking/order/$orderId.json");
    }
}


/* ===================================================================
 *  USAGE EXAMPLE — call from a test.php file like this:
 * ===================================================================
 *
 *  require_once 'bigbuy.php';
 *
 *  $bb = new BigBuy('PUT_API_KEY_HERE', true); // true = sandbox
 *
 *  // 1) Connection test
 *  $test = $bb->testConnection();
 *  var_dump($test['success']);   // should be true
 *
 *  // 2) Product info (use a BigBuy product ID)
 *  $info = $bb->getProductInfo(123456);
 *  print_r($info['data']);
 *
 *  // 3) Stock check
 *  $stock = $bb->getProductStock(123456);
 *  print_r($stock['data']);
 *
 * =================================================================== */
