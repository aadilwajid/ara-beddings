<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<h1 class="page-title">My Wishlist</h1>
<?php if (!$items): ?>
  <div class="empty-state">
    <h3>Your wishlist is empty</h3>
    <p>Tap ♡ on any product to save it here for later.</p>
    <a class="btn btn-primary" href="/shop">Browse Products</a>
  </div>
<?php else: ?>
  <div class="product-grid"><?php foreach ($items as $p) require dirname(__DIR__, 2) . '/components/product-card.php'; ?></div>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
