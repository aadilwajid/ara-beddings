<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<!DOCTYPE html>
<html lang="<?= e(setting('language', 'en')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? setting('store_name')) ?></title>
<meta name="description" content="<?= e($description ?? setting('store_description')) ?>">
<link rel="canonical" href="<?= e(rtrim(APP_URL, '/') . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/')) ?>">
<link rel="icon" href="<?= e(setting('favicon', '/assets/img/favicon.svg')) ?>">
<meta property="og:site_name" content="<?= e(setting('store_name')) ?>">
<meta property="og:title" content="<?= e($title ?? setting('store_name')) ?>">
<meta property="og:description" content="<?= e($description ?? setting('store_description')) ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= e(rtrim(APP_URL, '/') . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/')) ?>">
<meta property="og:image" content="<?= e(($og_image ?? '') ?: rtrim(APP_URL, '/') . setting('hero_image', '/assets/img/hero.svg')) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<script>window.CSRF = <?= json_encode(csrf_token()) ?>; window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
<link rel="stylesheet" href="/assets/css/style.css?v=1">
</head>
<body>
<header class="site-header" id="siteHeader">
  <div class="container header-row">
    <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false">&#9776;</button>
    <a class="logo" href="/">
      <?php if ($lg = setting('logo_image')): ?>
        <img src="<?= e($lg) ?>" alt="<?= e(setting('store_name')) ?>" width="140" height="44">
      <?php else: ?>
        <span class="logo-text"><?= e(setting('logo_text') ?: setting('store_name')) ?></span>
      <?php endif; ?>
    </a>
    <form class="search-form" action="/search" method="get" role="search">
      <input type="search" name="q" placeholder="Search products…" value="<?= e($_GET['q'] ?? '') ?>" aria-label="Search products">
      <button type="submit" aria-label="Search">&#128269;</button>
    </form>
    <nav class="main-nav" id="mainNav" aria-label="Main navigation">
      <a href="/">Home</a>
      <a href="/shop">Shop</a>
      <details class="nav-cats">
        <summary>Categories</summary>
        <div class="cat-drop">
          <?php foreach (Category::all() as $c): ?>
            <a href="/category/<?= e($c['slug']) ?>"><?= e($c['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </details>
      <a href="/track">Track Order</a>
      <a href="/contact">Contact</a>
      <?php if ($u = current_user()): ?>
        <a href="/account" class="nav-account">Account</a>
        <a href="/wishlist">Wishlist</a>
        <a href="/logout">Logout</a>
      <?php else: ?>
        <a href="/login" class="nav-account">Account</a>
      <?php endif; ?>
      <a href="/cart" class="cart-link" aria-label="Shopping cart">Cart <span class="cart-count" data-cart-count><?= (int)(Cart::get()['count'] ?? 0) ?></span></a>
    </nav>
    <?php if ($wa = preg_replace('/\D+/', '', (string)setting('whatsapp_number', ''))): ?>
      <a class="wa-float" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Hi ' . setting('store_name') . ', I need help') ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
        <svg viewBox="0 0 32 32" width="26" height="26" fill="#fff"><path d="M16 3C9.4 3 4 8.4 4 15c0 2.1.6 4.1 1.6 5.9L4 29l8.3-1.6c1.7.9 3.6 1.4 5.7 1.4 6.6 0 12-5.4 12-12S22.6 3 16 3zm5.6 16.4c-.2.7-1.4 1.3-2 1.4-.5.1-1.2.1-1.9-.1-.4-.1-1-.3-1.8-.6-3.1-1.3-5.1-4.5-5.3-4.7-.1-.2-1.2-1.6-1.2-3.1s.8-2.2 1-2.5c.2-.3.5-.4.7-.4h.5c.2 0 .4 0 .6.4l.9 2.1c0 .1.1.3 0 .5l-.4.5-.5.5c-.1.1-.3.3-.1.6.1.3.7 1.2 1.6 1.9 1.1 1 2 1.3 2.3 1.4.3.1.5.1.7-.1l.9-1c.2-.2.4-.2.6-.1l2 1c.3.1.5.2.5.3.1.2.1.8-.1 1.6z"/></svg>
      </a>
    <?php endif; ?>
  </div>
</header>
<?php if ($f = get_flash()): ?>
  <div class="container"><div class="flash flash-<?= e($f['t']) ?>"><?= e($f['m']) ?></div></div>
<?php endif; ?>
<main class="container">
