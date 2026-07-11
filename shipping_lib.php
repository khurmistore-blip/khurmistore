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
 * Spain shipping cost for ONE product by weight (kg):
 *  - rounds UP to the next whole-kg tier (e.g. 2.1kg -> 3kg rate)
 *  - weight < 1kg uses the 1kg rate
 *  - weight > 10kg clamps to the 10kg rate (the table has no tier above
 *    10kg — this is the highest known rate, used as a ceiling)
 *  - null/0/negative weight (unknown) falls back to SHIPPING_DEFAULT_ES
 */
function calcShipping(?float $weightKg): float
{
    if ($weightKg === null || $weightKg <= 0) {
        return SHIPPING_DEFAULT_ES;
    }
    $tier = (int)ceil($weightKg);
    if ($tier < 1) {
        $tier = 1;
    }
    if ($tier > 10) {
        $tier = 10; // table has no tier above 10kg — clamp to highest known rate
    }
    return SHIPPING_RATES_ES[$tier] ?? SHIPPING_DEFAULT_ES;
}

/**
 * Cart-level shipping: MAX of each distinct item's per-unit shipping cost
 * (not multiplied by qty, not summed across items — BigBuy typically
 * consolidates a cart into one shipment, so charging per-item would
 * over-charge). $items is an array of arrays/objects with a 'weight' key.
 */
function calcCartShipping(array $items): float
{
    $max = 0.0;
    foreach ($items as $item) {
        $weight = is_array($item) ? ($item['weight'] ?? null) : null;
        $cost   = calcShipping($weight !== null ? (float)$weight : null);
        if ($cost > $max) {
            $max = $cost;
        }
    }
    return $max > 0 ? $max : SHIPPING_DEFAULT_ES;
}
