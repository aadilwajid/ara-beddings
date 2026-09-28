<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin — <?= e($title ?? setting('store_name')) ?></title>
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<script>window.CSRF = <?= json_encode(csrf_token()) ?>; window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
<link rel="stylesheet" href="/assets/css/style.css?v=1">
<link rel="stylesheet" href="/assets/css/admin.css?v=1">
</head>
<body class="admin-body">
<div class="admin-shell">
  <aside class="admin-side" id="adminSide">
    <div class="admin-brand"><a href="/admin/dashboard">⚙️ <?= e(setting('store_name')) ?><small>Admin</small></a></div>
    <nav class="admin-nav">
      <?php $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH); $on = fn(string $r): bool => $p === $r || ($r !== '/admin/dashboard' && str_starts_with((string)$p, $r)); ?>
      <a href="/admin/dashboard" class="<?= $on('/admin/dashboard') ? 'active' : '' ?>">📊 Dashboard</a>
      <a href="/admin/orders" class="<?= $on('/admin/orders') ? 'active' : '' ?>">📦 Orders</a>
      <a href="/admin/products" class="<?= $on('/admin/products') ? 'active' : '' ?>">🏷️ Products</a>
      <a href="/admin/inventory" class="<?= $on('/admin/inventory') ? 'active' : '' ?>">📋 Inventory</a>
      <a href="/admin/categories" class="<?= $on('/admin/categories') ? 'active' : '' ?>">🗂️ Categories</a>
      <a href="/admin/attributes" class="<?= $on('/admin/attributes') ? 'active' : '' ?>">🎛️ Attributes</a>
      <a href="/admin/coupons" class="<?= $on('/admin/coupons') ? 'active' : '' ?>">🎟️ Coupons</a>
      <a href="/admin/reviews" class="<?= $on('/admin/reviews') ? 'active' : '' ?>">⭐ Reviews</a>
      <?php if (in_array(current_user()['role'] ?? '', ['super_admin','admin'], true)): ?>
        <a href="/admin/customers" class="<?= $on('/admin/customers') ? 'active' : '' ?>">👥 Customers</a>
        <a href="/admin/settings" class="<?= $on('/admin/settings') ? 'active' : '' ?>">⚙️ Settings</a>
      <?php endif; ?>
      <a href="/" target="_blank" rel="noopener">🌐 View Store</a>
      <a href="/logout">🚪 Logout</a>
    </nav>
  </aside>
  <div class="admin-main-col">
    <header class="admin-topbar">
      <button class="admin-menu-toggle" id="adminMenuToggle" aria-label="Toggle admin menu" aria-expanded="false">☰</button>
      <h1><?= e($title ?? 'Admin') ?></h1>
      <span class="admin-user"><?= e(current_user()['name'] ?? '') ?> (<?= e(str_replace('_', ' ', (string)current_user()['role'])) ?>)</span>
    </header>
    <?php if ($f = get_flash()): ?>
      <div class="flash flash-<?= e($f['t']) ?>"><?= e($f['m']) ?></div>
    <?php endif; ?>
    <main class="admin-content">
