<?php
/**
 * categoria.php — Lists all ACTIVE products from Supabase.
 * URL: categoria.php            (all products)
 *      categoria.php?cat=Tech   (one category)
 * -------------------------------------------------------------
 * NOTE: Replace the <header>/<footer> and <style> below with your own
 * site's (link style.min.css). Self-contained for now so it works immediately.
 * All visitor-facing text is in Spanish (site language).
 */

require_once __DIR__ . '/supabase.php';
$cfg = require __DIR__ . '/config.php';

$cat   = isset($_GET['cat']) ? trim($_GET['cat']) : '';
$query = 'products?status=eq.active&order=created_at.desc';
if ($cat !== '') {
    $query .= '&category=eq.' . rawurlencode($cat);
}
$products = sb_get($cfg, $query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($cfg['store_name']) ?> — <?= $cat ? htmlspecialchars($cat) : 'Productos' ?></title>
<meta name="description" content="Compra <?= $cat ? htmlspecialchars($cat) : 'productos' ?> en <?= htmlspecialchars($cfg['store_name']) ?>. Envío desde España.">
<style>
  :root{
    --navy:#0A0E27; --navy-soft:#121735; --orange:#FF6B35; --amber:#F88E20;
    --ink:#EAECF5; --muted:#9AA0BC; --line:rgba(255,255,255,.08);
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--navy);color:var(--ink);
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;line-height:1.5}
  a{color:inherit;text-decoration:none}
  .wrap{max-width:1180px;margin:0 auto;padding:0 20px}

  /* ---- replace this header with your site header ---- */
  header.site{padding:22px 0;border-bottom:1px solid var(--line)}
  header.site .brand{font-weight:800;font-size:22px;letter-spacing:.5px;
    background:linear-gradient(90deg,var(--orange),var(--amber));
    -webkit-background-clip:text;background-clip:text;color:transparent}

  .head{padding:38px 0 24px}
  .head h1{margin:0 0 6px;font-size:clamp(26px,4vw,38px);font-weight:800}
  .head p{margin:0;color:var(--muted)}

  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));
    gap:20px;padding:8px 0 60px}
  .card{background:var(--navy-soft);border:1px solid var(--line);border-radius:16px;
    overflow:hidden;display:flex;flex-direction:column;transition:transform .15s,border-color .15s}
  .card:hover{transform:translateY(-4px);border-color:rgba(255,107,53,.5)}
  .thumb{aspect-ratio:1/1;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden}
  .thumb img{width:100%;height:100%;object-fit:contain}
  .body{padding:14px 16px 18px;display:flex;flex-direction:column;gap:8px;flex:1}
  .cat{font-size:11px;text-transform:uppercase;letter-spacing:.6px;color:var(--amber)}
  .name{font-weight:600;font-size:15px;line-height:1.35;
    display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
  .price{margin-top:auto;font-size:20px;font-weight:800}
  .cta{margin-top:6px;text-align:center;padding:10px;border-radius:10px;font-weight:700;font-size:14px;
    background:linear-gradient(90deg,var(--orange),var(--amber));color:#1a1002}

  .empty{padding:60px 0;text-align:center;color:var(--muted)}
</style>
</head>
<body>

<!-- ==== replace with your site header ==== -->
<header class="site"><div class="wrap"><a class="brand" href="/"><?= htmlspecialchars($cfg['store_name']) ?></a></div></header>

<div class="wrap">
  <div class="head">
    <h1><?= $cat ? htmlspecialchars($cat) : 'Todos los productos' ?></h1>
    <p><?= count($products) ?> producto<?= count($products)===1?'':'s' ?> disponible<?= count($products)===1?'':'s' ?></p>
  </div>

  <?php if (empty($products)): ?>
    <div class="empty">Todavía no hay productos en esta categoría. Vuelve pronto.</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($products as $p): ?>
        <a class="card" href="producto.php?id=<?= (int)$p['id'] ?>">
          <div class="thumb">
            <?php if (!empty($p['image_url'])): ?>
              <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
            <?php endif; ?>
          </div>
          <div class="body">
            <?php if (!empty($p['category'])): ?><span class="cat"><?= htmlspecialchars($p['category']) ?></span><?php endif; ?>
            <span class="name"><?= htmlspecialchars($p['name']) ?></span>
            <span class="price"><?= price_es($p['price'], $cfg['currency_symbol']) ?></span>
            <span class="cta">Ver producto</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ==== replace with your site footer ==== -->

</body>
</html>
