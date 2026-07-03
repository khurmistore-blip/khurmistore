<?php
/**
 * producto.php — Single product detail page (live from Supabase).
 * URL: producto.php?id=5
 * -------------------------------------------------------------
 * - SEO title/description come from your AI columns (seo_title, seo_description).
 * - long_description / bullet_points / faq_content also show if filled.
 * - "Pedir por WhatsApp" button with a pre-filled message (manual order flow).
 *   PayPal/checkout hook goes here later (see comment).
 *
 * NOTE: Replace header/footer + <style> with your own site's.
 * All visitor-facing text is in Spanish (site language).
 */

require_once __DIR__ . '/supabase.php';
$cfg = require __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rows = $id ? sb_get($cfg, "products?id=eq.$id&status=eq.active&limit=1") : [];
$p = $rows[0] ?? null;

if (!$p) {
    http_response_code(404);
    $title = 'Producto no encontrado';
} else {
    $title = $p['seo_title'] ?: $p['name'];
}

// bullet points: one point per line
$bullets = [];
if ($p && !empty($p['bullet_points'])) {
    $bullets = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $p['bullet_points'])));
}

// WhatsApp order link
$waLink = '';
if ($p) {
    $msg = "Hola! Quiero pedir: {$p['name']} (" . price_es((float)$p['price'], $cfg['currency_symbol']) . "). ID: {$p['id']}";
    $waLink = 'https://wa.me/' . $cfg['whatsapp_number'] . '?text=' . rawurlencode($msg);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?> — <?= htmlspecialchars($cfg['store_name']) ?></title>
<?php if ($p): ?>
<meta name="description" content="<?= htmlspecialchars($p['seo_description'] ?: mb_substr(strip_tags($p['description'] ?? ''),0,155)) ?>">
<?php if (!empty($p['seo_keywords'])): ?><meta name="keywords" content="<?= htmlspecialchars($p['seo_keywords']) ?>"><?php endif; ?>
<?php endif; ?>
<style>
  :root{
    --navy:#0A0E27; --navy-soft:#121735; --orange:#FF6B35; --amber:#F88E20;
    --ink:#EAECF5; --muted:#9AA0BC; --line:rgba(255,255,255,.08); --green:#25D366;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--navy);color:var(--ink);
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;line-height:1.6}
  a{color:inherit}
  .wrap{max-width:1080px;margin:0 auto;padding:0 20px}
  header.site{padding:22px 0;border-bottom:1px solid var(--line)}
  header.site .brand{font-weight:800;font-size:22px;
    background:linear-gradient(90deg,var(--orange),var(--amber));
    -webkit-background-clip:text;background-clip:text;color:transparent;text-decoration:none}

  .crumb{padding:18px 0;color:var(--muted);font-size:13px}
  .crumb a{text-decoration:none;color:var(--muted)}

  .product{display:grid;grid-template-columns:1fr 1fr;gap:40px;padding:8px 0 40px}
  @media(max-width:820px){.product{grid-template-columns:1fr;gap:24px}}
  .gallery{background:#fff;border-radius:18px;overflow:hidden;aspect-ratio:1/1;
    display:flex;align-items:center;justify-content:center}
  .gallery img{width:100%;height:100%;object-fit:contain}

  .cat{font-size:12px;text-transform:uppercase;letter-spacing:.7px;color:var(--amber)}
  h1{margin:8px 0 14px;font-size:clamp(24px,3.4vw,32px);font-weight:800;line-height:1.2}
  .price{font-size:34px;font-weight:800;margin:6px 0 4px}
  .stockline{font-size:13px;color:var(--muted);margin-bottom:22px}
  .in{color:#39d98a}

  .buy{display:flex;flex-direction:column;gap:10px;max-width:360px}
  .btn{display:flex;align-items:center;justify-content:center;gap:8px;
    padding:15px;border-radius:12px;font-weight:800;font-size:16px;text-decoration:none;border:0;cursor:pointer}
  .btn-wa{background:var(--green);color:#04310f}
  .btn-buy{background:linear-gradient(90deg,var(--orange),var(--amber));color:#1a1002}

  .bullets{margin:26px 0 0;padding:0;list-style:none;display:grid;gap:8px}
  .bullets li{padding-left:26px;position:relative;color:#cfd3e6}
  .bullets li::before{content:"✓";position:absolute;left:0;color:var(--orange);font-weight:800}

  .section{border-top:1px solid var(--line);padding:30px 0}
  .section h2{font-size:18px;margin:0 0 12px}
  .desc{color:#cfd3e6}
  .desc img{max-width:100%;height:auto;border-radius:10px}
  .notfound{padding:80px 0;text-align:center}
</style>
</head>
<body>

<!-- ==== replace with your site header ==== -->
<header class="site"><div class="wrap"><a class="brand" href="/"><?= htmlspecialchars($cfg['store_name']) ?></a></div></header>

<div class="wrap">
<?php if (!$p): ?>
  <div class="notfound">
    <h1>Producto no encontrado</h1>
    <p><a href="categoria.php" style="color:var(--amber)">← Ver todos los productos</a></p>
  </div>
<?php else: ?>

  <div class="crumb">
    <a href="categoria.php">Productos</a>
    <?php if (!empty($p['category'])): ?> / <a href="categoria.php?cat=<?= rawurlencode($p['category']) ?>"><?= htmlspecialchars($p['category']) ?></a><?php endif; ?>
  </div>

  <div class="product">
    <div class="gallery">
      <?php if (!empty($p['image_url'])): ?>
        <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
      <?php endif; ?>
    </div>

    <div class="info">
      <?php if (!empty($p['category'])): ?><span class="cat"><?= htmlspecialchars($p['category']) ?></span><?php endif; ?>
      <h1><?= htmlspecialchars($p['name']) ?></h1>
      <div class="price"><?= price_es((float)$p['price'], $cfg['currency_symbol']) ?></div>
      <div class="stockline">
        <?php if ((int)($p['stock'] ?? 0) > 0): ?>
          <span class="in">● En stock</span> · Envío desde España
        <?php else: ?>
          Consultar disponibilidad
        <?php endif; ?>
      </div>

      <div class="buy">
        <!-- Manual order (works now) -->
        <a class="btn btn-wa" href="<?= htmlspecialchars($waLink) ?>" target="_blank" rel="noopener">
          Pedir por WhatsApp
        </a>

        <!-- PayPal / checkout: goes here later.
             Call save_order.php from here (push order to Supabase + BigBuy). -->
        <!-- <button class="btn btn-buy" onclick="/* checkout */">Comprar ahora</button> -->
      </div>

      <?php if ($bullets): ?>
        <ul class="bullets">
          <?php foreach ($bullets as $b): ?><li><?= htmlspecialchars($b) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <?php $longDesc = $p['long_description'] ?: $p['description']; ?>
  <?php if (!empty($longDesc)): ?>
    <div class="section">
      <h2>Descripción</h2>
      <div class="desc"><?= $longDesc /* BigBuy/AI HTML */ ?></div>
    </div>
  <?php endif; ?>

  <?php if (!empty($p['faq_content'])): ?>
    <div class="section">
      <h2>Preguntas frecuentes</h2>
      <div class="desc"><?= $p['faq_content'] ?></div>
    </div>
  <?php endif; ?>

<?php endif; ?>
</div>

<!-- ==== replace with your site footer ==== -->

</body>
</html>
