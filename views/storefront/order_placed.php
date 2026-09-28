<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<div class="placed-wrap">
  <div class="placed-card">
    <div class="placed-icon">✅</div>
    <h1>Thank you! Your order has been placed.</h1>
    <?php if ($order): ?>
      <p class="order-num">Order Number: <strong><?= e($order['order_number']) ?></strong></p>
      <ul class="placed-details">
        <li><strong>Total:</strong> <?= money((float)$order['grand_total']) ?></li>
        <li><strong>Payment:</strong> <?= e(\Order::label((string)$order['payment_method'])) ?></li>
        <li><strong>Status:</strong> <?= e(\Order::label((string)$order['status'])) ?> — we will call <?= e($order['customer_phone']) ?> to confirm.</li>
        <li><strong>Deliver to:</strong> <?= e($order['address']) ?>, <?= e($order['city']) ?>, <?= e($order['province']) ?></li>
      </ul>

      <?php if ($order['payment_method'] !== 'cod'): ?>
        <div class="pay-instructions">
          <h2>Payment Instructions</h2>
          <?= Shipping::paymentInstructions((string)$order['payment_method'], (float)$order['grand_total']) ?>
          <p class="manual-warning"><strong>Important:</strong> Your order is <em>pending</em> until our team verifies the payment. It will not be marked paid automatically.</p>
        </div>
      <?php else: ?>
        <div class="pay-instructions">
          <h2>Cash on Delivery</h2>
          <p>Please keep <strong><?= money((float)$order['grand_total']) ?></strong> ready when the rider arrives.</p>
        </div>
      <?php endif; ?>

      <div class="placed-actions">
        <a class="btn btn-primary" href="/account">View My Orders</a>
        <a class="btn btn-outline" href="/track?n=<?= e($order['order_number']) ?>">Track this Order</a>
        <a class="btn btn-link" href="/shop">Continue Shopping</a>
      </div>
    <?php else: ?>
      <p>Your order <strong><?= e($num) ?></strong> was received and is pending confirmation. Our team will contact you shortly.</p>
      <div class="placed-actions">
        <a class="btn btn-primary" href="/track">Track your order</a>
        <a class="btn btn-outline" href="/shop">Continue Shopping</a>
      </div>
      <?php if ($num === ''): ?><p><small>Keep your order number and mobile number handy to track the delivery.</small></p><?php endif; ?>
    <?php endif; ?>

    <?php if ($wa = preg_replace('/\D+/', '', (string)setting('whatsapp_number', ''))): ?>
      <p class="wa-help">Need help?
        <a target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Hi ' . setting('store_name') . ', regarding my order ' . ($order['order_number'] ?? $num)) ?>">Chat on WhatsApp</a>
      </p>
    <?php endif; ?>
  </div>
</div>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
