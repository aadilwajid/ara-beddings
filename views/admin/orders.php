<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<div class="toolbar">
  <form method="get" class="admin-search">
    <input type="search" name="q" placeholder="Search orders…" value="<?= e($_GET['q'] ?? '') ?>">
    <select name="status">
      <option value="">All statuses</option>
      <?php foreach ($statuses as $s): ?>
        <option value="<?= e($s) ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(\Order::label($s)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-primary" type="submit">Filter</button>
  </form>
  <p class="result-count"><?= (int)$res['total'] ?> order<?= (int)$res['total'] === 1 ? '' : 's' ?></p>
</div>

<div class="table-wrap">
<table class="data-table">
  <thead><tr>
    <th>Order #</th><th>Date</th><th>Customer</th><th>Phone</th><th>City</th>
    <th>Total</th><th>Payment</th><th>Status</th><th></th>
  </tr></thead>
  <tbody>
    <?php foreach ($res['items'] as $o): ?>
      <tr>
        <td><a href="/admin/orders/<?= (int)$o['id'] ?>"><strong><?= e($o['order_number']) ?></strong></a></td>
        <td><?= e(date('d M Y H:i', strtotime((string)$o['placed_at']))) ?></td>
        <td><?= e($o['customer_name']) ?><?php if (!$o['user_id']): ?> <small>(guest)</small><?php endif; ?></td>
        <td><?= e($o['customer_phone']) ?></td>
        <td><?= e($o['city']) ?></td>
        <td><?= money((float)$o['grand_total']) ?></td>
        <td><?= e(\Order::label((string)$o['payment_method'])) ?> · <span class="pay-status pay-<?= e($o['payment_status']) ?>"><?= e((string)$o['payment_status']) ?></span></td>
        <td><span class="status-pill status-<?= e($o['status']) ?>"><?= e(\Order::label((string)$o['status'])) ?></span></td>
        <td><a class="btn btn-sm btn-outline" href="/admin/orders/<?= (int)$o['id'] ?>">Manage</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$res['items']): ?><tr><td colspan="9" class="empty-cell">No orders found.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<?= paginate((int)$res['total'], 20, max(1,(int)($_GET['page'] ?? 1)), '/admin/orders?' . http_build_query(array_merge($_GET, ['page'=>null]))) ?>
