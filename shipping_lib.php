<?php
declare(strict_types=1);

/**
 * shipping_lib.php — Spain (ES) shipping cost lookup, weight-based.
 * BigBuy's live per-product shipping API is unreliable (many products
 * fail/come back unavailable), so this uses a STATIC rate table taken from
 * BigBuy's official Spain shipping CSV instead of a live API call per order.
 */

const SHIPPING_RATES_ES = [
    1 => 4.94, 2 => 5.16, 3 => 5.26, 4 => 5.26, 5 => 6.36,
    6 => 6.54, 7 => 6.41, 8 => 6.49, 9 => 6.52, 10 => 7.34,
];
const SHIPPING_DEFAULT_ES = 4.99;

/**
 * Spain shipping cost for ONE product. STORE-WIDE FREE SHIPPING: always 0,
 * regardless of weight — the store absorbs the real BigBuy shipping cost.
 * Weight-tier table/default above kept in place (unused) rather than
 * deleted, in case free shipping is ever reverted.
 */
function calcShipping(?float $weightKg): float
{
    return 0.0;
}

/**
 * Cart-level shipping: always 0 (store-wide free shipping). Kept as a
 * function (rather than inlined at call sites) since callers (checkout
 * summary, PayPal, Stripe, feed.php) already depend on this exact name.
 */
function calcCartShipping(array $items): float
{
    return 0.0;
}
