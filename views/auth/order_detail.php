<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<h1 class="page-title">Order <?= e($order['order_number']) ?></h1>
<p><a href="/account">← Back to My Account</a></p>

<div class="order-detail-layout">
  <section class="account-card">
    <div class="order-head">
      <h2>Items</h2>
      <span class="status-pill status-<?= e($order['status']) ?>"><?= e(\Order::label((string)$order['status'])) ?></span>
    </div>
    <ul class="mini-items">
      <?php foreach ($order['items'] as $it): ?>
        <li>
          <span>
            <?php if ($it['product_id']): ?><a href="/product/<?= e((string)($it['product_slug'] ?? '')) ?>"><?= e($it['product_name']) ?></a><?php else: ?><?= e($it['product_name']) ?><?php endif; ?>
            <?php if ($it['variant_name']): ?> <em>(<?= e($it['variant_name']) ?>)</em><?php endif; ?>
            <?php if ($it['sku']): ?><small> · SKU <?= e($it['sku']) ?></small><?php endif; ?>
            × <?= (int)$it['quantity'] ?> @ <?= money((float)$it['unit_price']) ?>
          </span>
          <span><?= money((float)$it['line_total']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <dl class="summary-lines">
      <div><dt>Subtotal</dt><dd><?= money((float)$order['subtotal']) ?></dd></div>
      <?php if ((float)$order['discount'] > 0): ?><div class="discount-line"><dt>Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></dt><dd>− <?= money((float)$order['discount']) ?></dd></div><?php endif; ?>
      <div><dt>Shipping</dt><dd><?= (float)$order['shipping_cost'] > 0 ? money((float)$order['shipping_cost']) : 'Free' ?></dd></div>
      <?php if ((float)$order['tax_amount'] > 0): ?><div><dt>Tax</dt><dd><?= money((float)$order['tax_amount']) ?></dd></div><?php endif; ?>
      <div class="grand"><dt>Total</dt><dd><?= money((float)$order['grand_total']) ?></dd></div>
    </dl>
  </section>

  <aside class="account-card">
    <h2>Summary</h2>
    <ul class="track-details">
      <li><strong>Placed:</strong> <?= e(date('d M Y, h:i A', strtotime((string)$order['placed_at']))) ?></li>
      <li><strong>Payment method:</strong> <?= e(\Order::label((string)$order['payment_method'])) ?></li>
      <li><strong>Payment status:</strong> <span class="pay-status pay-<?= e($order['payment_status']) ?>"><?= e(ucfirst((string)$order['payment_status'])) ?></span></li>
      <?php if ($order['tracking_number']): ?>
        <li><strong>Tracking #:</strong> <?= e((string)$order['tracking_number']) ?><?php if ($order['courier']): ?> (<?= e((string)$order['courier']) ?>)<?php endif; ?></li>
      <?php endif; ?>
      <li><strong>Ship to:</strong> <?= e($order['customer_name']) ?>, <?= e($order['address']) ?>, <?= e($order['city']) ?>, <?= e($order['province']) ?></li>
      <?php if ($order['order_notes']): ?><li><strong>Notes:</strong> <?= e((string)$order['order_notes']) ?></li><?php endif; ?>
    </ul>

    <?php if (!empty($order['history'])): ?>
      <h2>Timeline</h2>
      <ul class="history-list">
        <?php foreach ($order['history'] as $h): ?>
          <li><span class="status-pill sm"><?= e(\Order::label((string)$h['status'])) ?></span>
              <?= e(date('d M Y H:i', strtotime((string)$h['changed_at']))) ?>
              <?php if ($h['note']): ?><br><small><?= e((string)$h['note']) ?></small><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($order['status'] === 'pending' && in_array($order['payment_method'], ['bank_transfer','easypaisa','jazzcash'], true)): ?>
      <div class="pay-instructions">
        <h2>Complete Your Payment</h2>
        <?= Shipping::paymentInstructions((string)$order['payment_method'], (float)$order['grand_total']) ?>
      </div>
    <?php endif; ?>

    <?php if ($wa = preg_replace('/\D+/', '', (string)setting('whatsapp_number', ''))): ?>
      <p class="wa-help">Question about this order?
        <a target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Hi ' . setting('store_name') . ', regarding my order ' . $order['order_number']) ?>">WhatsApp us</a>
      </p>
    <?php endif; ?>
  </aside>
</div>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
