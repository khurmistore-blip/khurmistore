<?php
/**
 * inspect_id.php — Show RAW responses for one product ID so we can map fields.
 * Uses only light calls (no full catalog), spaced out to avoid rate limits.
 * -------------------------------------------------------------------------
 * Open:  khurmistore.es/inspect_id.php?key=khurmi2026&id=1300776
 * DELETE after use.
 */

if (($_GET['key'] ?? '') !== 'khurmi2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(90);

require_once __DIR__ . '/bigbuy.php';
$cfg = require __DIR__ . '/config.php';
$id  = (int)($_GET['id'] ?? 1300776);
$bb  = new BigBuy($cfg['bigbuy_api_key'], $cfg['bigbuy_sandbox']);

echo "Inspecting product ID: $id (sandbox=" . ($cfg['bigbuy_sandbox']?'T':'F') . ")\n";
echo str_repeat("=", 50) . "\n\n";

echo "### getProductInfo($id) — name/description come from here ###\n";
$r = $bb->getProductInfo($id, 'es');
echo "HTTP " . ($r['status'] ?? '?') . "\n";
print_r($r['data'] ?? null);
echo "\n\n";
sleep(2); // avoid rate limit

echo "### getProductStock($id) ###\n";
$r = $bb->getProductStock($id);
echo "HTTP " . ($r['status'] ?? '?') . "\n";
print_r($r['data'] ?? null);
echo "\n";

echo "\nDONE. Send me this whole output — I'll lock the field mapping.\n";
echo "(Skipping getProduct here on purpose — it's the rate-limited one.)\n";
