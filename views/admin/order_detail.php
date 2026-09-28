<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<p><a href="/admin/orders">← Back to Orders</a></p>

<div class="order-detail-layout">
  <section class="panel">
    <div class="order-head">
      <h2><?= e($o['order_number']) ?>
        <span class="status-pill status-<?= e($o['status']) ?>"><?= e(\Order::label((string)$o['status'])) ?></span>
      </h2>
      <small>Placed <?= e(date('d M Y, H:i', strtotime((string)$o['placed_at']))) ?></small>
    </div>

    <table class="data-table">
      <thead><tr><th>Item</th><th>SKU</th><th>Unit</th><th>Qty</th><th>Total</th></tr></thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= e($it['product_name']) ?><?php if ($it['variant_name']): ?> <em>(<?= e($it['variant_name']) ?>)</em><?php endif; ?></td>
            <td><?= e((string)($it['sku'] ?? '—')) ?></td>
            <td><?= money((float)$it['unit_price']) ?></td>
            <td><?= (int)$it['quantity'] ?></td>
            <td><?= money((float)$it['line_total']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <dl class="summary-lines compact">
      <div><dt>Subtotal</dt><dd><?= money((float)$o['subtotal']) ?></dd></div>
      <?php if ((float)$o['discount'] > 0): ?><div class="discount-line"><dt>Discount<?= $o['coupon_code'] ? ' (' . e($o['coupon_code']) . ')' : '' ?></dt><dd>− <?= money((float)$o['discount']) ?></dd></div><?php endif; ?>
      <div><dt>Shipping</dt><dd><?= money((float)$o['shipping_cost']) ?></dd></div>
      <?php if ((float)$o['tax_amount'] > 0): ?><div><dt>Tax</dt><dd><?= money((float)$o['tax_amount']) ?></dd></div><?php endif; ?>
      <div class="grand"><dt>Grand Total</dt><dd><?= money((float)$o['grand_total']) ?></dd></div>
    </dl>
  </section>

  <aside class="panel">
    <h2>Customer &amp; Delivery</h2>
    <ul class="track-details">
      <li><strong>Name:</strong> <?= e($o['customer_name']) ?></li>
      <li><strong>Phone:</strong> <a href="tel:<?= e(preg_replace('/[^0-9+]/','',(string)$o['customer_phone'])) ?>"><?= e($o['customer_phone']) ?></a></li>
      <li><strong>Email:</strong> <?= e((string)($o['customer_email'] ?: '—')) ?><?= $o['user_id'] ? ' <small>(registered)</small>' : ' <small>(guest)</small>' ?></li>
      <li><strong>Address:</strong> <?= e($o['address']) ?><?= $o['landmark'] ? '<br>Near: ' . e($o['landmark']) : '' ?></li>
      <li><strong>Area/City:</strong> <?= e((string)($o['area'] ?: '—')) ?>, <?= e($o['city']) ?>, <?= e($o['province']) ?><?= $o['postal_code'] ? ' — ' . e($o['postal_code']) : '' ?></li>
      <?php if ($o['order_notes']): ?><li><strong>Notes:</strong> <?= e((string)$o['order_notes']) ?></li><?php endif; ?>
    </ul>
    <?php if ($wa = preg_replace('/\D+/', '', (string)setting('whatsapp_number', ''))): ?>
      <p><a target="_blank" rel="noopener" href="https://wa.me/<?= e(preg_replace('/\D+/','', (string)$o['customer_phone'])) ?>">💬 WhatsApp customer</a></p>
    <?php endif; ?>
  </aside>

  <section class="panel">
    <h2>Update Status</h2>
    <form method="post" action="/admin/orders/<?= (int)$o['id'] ?>/status">
      <?= csrf_field() ?>
      <label>Status
        <select name="status">
          <?php foreach ($statuses as $s): ?>
            <option value="<?= e($s) ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= e(\Order::label($s)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Note (internal)<input type="text" name="note" maxlength="500"></label>
      <button class="btn btn-primary btn-sm" type="submit">Save Status</button>
      <small>Customer is notified by email on status change.</small>
    </form>
  </section>

  <section class="panel">
    <h2>Payment</h2>
    <p>Method: <strong><?= e(\Order::label((string)$o['payment_method'])) ?></strong> · Reference: <?= e((string)($o['payment_reference'] ?: '—')) ?></p>
    <form method="post" action="/admin/orders/<?= (int)$o['id'] ?>/payment">
      <?= csrf_field() ?>
      <label>Payment Status
        <select name="payment_status">
          <?php foreach (['pending','paid','failed','refunded'] as $ps): ?>
            <option value="<?= $ps ?>" <?= $o['payment_status'] === $ps ? 'selected' : '' ?>><?= ucfirst($ps) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Reference / Transaction ID<input type="text" name="reference" maxlength="120" value="<?= e((string)($o['payment_reference'] ?? '')) ?>"></label>
      <button class="btn btn-primary btn-sm" type="submit">Save Payment</button>
      <small>Manual payments are marked <em>paid</em> only here — never automatically.</small>
    </form>
  </section>

  <section class="panel">
    <h2>Tracking</h2>
    <form method="post" action="/admin/orders/<?= (int)$o['id'] ?>/tracking">
      <?= csrf_field() ?>
      <div class="form-grid">
        <label>Courier<input type="text" name="courier" maxlength="120" value="<?= e((string)($o['courier'] ?? '')) ?>" placeholder="e.g. Leopards / TCS"></label>
        <label>Tracking Number<input type="text" name="tracking_number" maxlength="120" value="<?= e((string)($o['tracking_number'] ?? '')) ?>"></label>
      </div>
      <button class="btn btn-primary btn-sm" type="submit">Save Tracking</button>
    </form>
  </section>

  <section class="panel">
    <h2>Status History</h2>
    <?php if (!$history): ?><p>No history yet.</p><?php else: ?>
      <ul class="history-list">
        <?php foreach ($history as $h): ?>
          <li><span class="status-pill sm"><?= e(\Order::label((string)$h['status'])) ?></span>
              <?= e(date('d M Y H:i', strtotime((string)$h['changed_at']))) ?>
              <?php if ($h['note']): ?><br><small><?= e((string)$h['note']) ?></small><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
