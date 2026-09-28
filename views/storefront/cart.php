<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<h1 class="page-title">Shopping Cart</h1>

<?php if (empty($cart['items'])): ?>
  <div class="empty-state">
    <h3>Your cart is empty</h3>
    <p>Browse our collection and add something you like.</p>
    <a class="btn btn-primary" href="/shop">Start Shopping</a>
  </div>
<?php else: ?>
<div class="cart-layout">
  <div class="cart-items">
    <?php foreach ($cart['items'] as $it): ?>
      <div class="cart-row" data-item="<?= (int)$it['item_id'] ?>">
        <a class="cart-thumb" href="/product/<?= e($it['slug']) ?>">
          <?php if ($it['image']): ?><img src="<?= e($it['image']) ?>" alt="<?= e($it['name']) ?>" loading="lazy" width="72" height="72">
          <?php else: ?><span class="img-placeholder sm"><?= mb_substr(e($it['name']), 0, 2) ?></span><?php endif; ?>
        </a>
        <div class="cart-meta">
          <a class="cart-name" href="/product/<?= e($it['slug']) ?>"><?= e($it['name']) ?></a>
          <?php if ($it['variant']): ?><div class="cart-variant"><?= e($it['variant']) ?></div><?php endif; ?>
          <?php if ($it['sku']): ?><div class="cart-sku">SKU: <?= e($it['sku']) ?></div><?php endif; ?>
          <div class="cart-unit"><?= money($it['price']) ?> each</div>
        </div>
        <div class="cart-qty">
          <button class="btn btn-sm btn-outline qty-dec" aria-label="Decrease quantity">−</button>
          <input type="number" class="qty-input" min="1" max="<?= (int)$it['max_qty'] ?>" value="<?= (int)$it['quantity'] ?>" aria-label="Quantity for <?= e($it['name']) ?>">
          <button class="btn btn-sm btn-outline qty-inc" aria-label="Increase quantity">+</button>
          <?php if ($it['quantity'] >= $it['max_qty']): ?><small class="low-text">Max stock: <?= (int)$it['max_qty'] ?></small><?php endif; ?>
        </div>
        <div class="cart-line-total"><?= money($it['line_total']) ?></div>
        <button class="cart-remove btn btn-link" aria-label="Remove <?= e($it['name']) ?> from cart">✕</button>
      </div>
    <?php endforeach; ?>
    <div class="cart-actions-row">
      <button id="clearCartBtn" class="btn btn-link danger">Clear cart</button>
      <a class="btn btn-outline" href="/shop">Continue shopping</a>
    </div>
  </div>

  <aside class="cart-summary" id="cartSummary">
    <h2>Order Summary</h2>
    <dl class="summary-lines">
      <div><dt>Subtotal</dt><dd data-summary="subtotal"><?= money($cart['subtotal']) ?></dd></div>
      <?php if ($cart['discount'] > 0): ?>
        <div class="discount-line"><dt>Discount (<?= e((string)$cart['coupon']) ?>)</dt><dd data-summary="discount">− <?= money($cart['discount']) ?></dd></div>
      <?php endif; ?>
      <?php if ($cart['coupon_error']): ?><p class="coupon-stale">Coupon “<?= e((string)$cart['coupon']) ?>” no longer valid: <?= e($cart['coupon_error']) ?></p><?php endif; ?>
      <div><dt>Shipping</dt><dd data-summary="shipping"><?= $cart['shipping'] > 0 ? money($cart['shipping']) : 'Free' ?></dd></div>
      <?php if ($cart['tax'] > 0): ?><div><dt>Tax (<?= e(setting('tax_rate','0')) ?>%)</dt><dd data-summary="tax"><?= money($cart['tax']) ?></dd></div><?php endif; ?>
      <div class="grand"><dt>Total</dt><dd data-summary="total"><?= money($cart['total']) ?></dd></div>
    </dl>
    <?php $thr = (float)setting('free_shipping_threshold', '0'); if ($thr > 0 && $cart['shipping'] > 0): ?>
      <p class="free-ship-note">Add <?= money($thr - ($cart['subtotal'] - $cart['discount'])) ?> more for free shipping.</p>
    <?php endif; ?>

    <form class="coupon-form" id="couponForm" autocomplete="off">
      <input type="text" name="code" placeholder="Coupon code" aria-label="Coupon code" value="<?= e((string)($cart['coupon'] ?? '')) ?>" maxlength="60">
      <button class="btn btn-sm btn-outline" type="submit">Apply</button>
    </form>
    <p class="coupon-msg" role="status" aria-live="polite"></p>

    <a class="btn btn-primary btn-block" href="/checkout">Proceed to Checkout</a>
    <p class="secure-note">🔒 Prices &amp; stock verified server-side. Never share OTPs/CNIC with anyone.</p>
  </aside>
</div>
<noscript><p class="flash flash-error">JavaScript is required to update the cart. You can still view totals below.</p></noscript>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
