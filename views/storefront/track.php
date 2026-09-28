<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<h1 class="page-title">Track Your Order</h1>

<form method="post" action="/track" class="track-form">
  <?= csrf_field() ?>
  <div class="form-grid">
    <label>Order Number *<input type="text" name="order_number" required maxlength="30" placeholder="e.g. ORD-2026-000123" value="<?= e($_POST['order_number'] ?? $_GET['n'] ?? '') ?>"></label>
    <label>Mobile Number or Email *<input type="text" name="identifier" required maxlength="190" placeholder="03XXXXXXXXX or you@example.com" value="<?= e($_POST['identifier'] ?? '') ?>"></label>
  </div>
  <button class="btn btn-primary" type="submit">Find My Order</button>
  <p class="help-note">We only show limited details — your full address and contact info stay private.</p>
</form>

<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<?php if ($order): ?>
  <?php
  $flow = ['pending','confirmed','processing','packed','shipped','out_for_delivery','delivered'];
  $cancelled = in_array($order['status'], ['cancelled','returned','refunded'], true);
  $curIdx = array_search($order['status'], $flow, true);
  ?>
  <section class="track-result">
    <div class="track-head">
      <h2>Order <?= e($order['order_number']) ?></h2>
      <span class="status-pill status-<?= e($order['status']) ?>"><?= e(\Order::label((string)$order['status'])) ?></span>
    </div>

    <?php if (!$cancelled): ?>
    <ol class="timeline" aria-label="Order timeline">
      <?php foreach ($flow as $i => $s): ?>
        <li class="<?= $i <= (int)$curIdx ? 'done' : '' ?>"><span><?= e(\Order::label($s)) ?></span></li>
      <?php endforeach; ?>
    </ol>
    <?php else: ?>
      <p class="cancel-note">This order was <?= e(mb_strtolower(\Order::label((string)$order['status']))) ?>. Contact support if you think this is a mistake.</p>
    <?php endif; ?>

    <div class="track-grid">
      <div>
        <h3>Items</h3>
        <ul class="mini-items">
          <?php foreach ($order['items'] as $it): ?>
            <li><span><?= e($it['product_name']) ?><?php if ($it['variant_name']): ?> <em>(<?= e($it['variant_name']) ?>)</em><?php endif; ?> × <?= (int)$it['quantity'] ?></span>
                <span><?= money((float)$it['line_total']) ?></span></li>
          <?php endforeach; ?>
        </ul>
        <dl class="summary-lines compact">
          <div><dt>Subtotal</dt><dd><?= money((float)$order['subtotal']) ?></dd></div>
          <?php if ((float)$order['discount'] > 0): ?><div class="discount-line"><dt>Discount</dt><dd>− <?= money((float)$order['discount']) ?></dd></div><?php endif; ?>
          <div><dt>Shipping</dt><dd><?= (float)$order['shipping_cost'] > 0 ? money((float)$order['shipping_cost']) : 'Free' ?></dd></div>
          <?php if ((float)$order['tax_amount'] > 0): ?><div><dt>Tax</dt><dd><?= money((float)$order['tax_amount']) ?></dd></div><?php endif; ?>
          <div class="grand"><dt>Total</dt><dd><?= money((float)$order['grand_total']) ?></dd></div>
        </dl>
      </div>
      <div>
        <h3>Details</h3>
        <ul class="track-details">
          <li><strong>Placed:</strong> <?= e(date('d M Y, h:i A', strtotime((string)$order['placed_at']))) ?></li>
          <li><strong>Payment method:</strong> <?= e(\Order::label((string)$order['payment_method'])) ?></li>
          <li><strong>Payment status:</strong> <span class="pay-status pay-<?= e($order['payment_status']) ?>"><?= e(ucfirst((string)$order['payment_status'])) ?></span></li>
          <?php if ($order['tracking_number']): ?>
            <li><strong>Tracking #:</strong> <?= e((string)$order['tracking_number']) ?><?php if ($order['courier']): ?> (<?= e((string)$order['courier']) ?>)<?php endif; ?></li>
          <?php endif; ?>
          <li><strong>Delivering to:</strong> <?= e((string)$order['city']) ?>, <?= e((string)$order['province']) ?></li>
        </ul>
        <?php if (!empty($order['history'])): ?>
          <h3>Status Updates</h3>
          <ul class="history-list">
            <?php foreach (array_slice($order['history'], -6) as $h): ?>
              <li><span class="status-pill sm"><?= e(\Order::label((string)$h['status'])) ?></span> <?= e(date('d M H:i', strtotime((string)$h['changed_at']))) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
