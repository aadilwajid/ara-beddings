<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<div class="toolbar">
  <h2>All Orders</h2>
  <a class="btn btn-sm btn-outline" href="/admin/orders/export?status=<?= e($_GET['status'] ?? '') ?>&q=<?= e($_GET['q'] ?? '') ?>">⬇ Export CSV</a>
</div>

<form method="post" action="/admin/orders/bulk" id="bulkOrderForm">
  <?= csrf_field() ?>
  <div class="bulk-bar">
    <select name="bulk_status" aria-label="Bulk status">
      <option value="">Set status of selected to…</option>
      <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>"><?= e(\Order::label($s)) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-outline" type="submit" onclick="return confirm('Apply this status change to all selected orders?')">Apply</button>
  </div>

  <div class="table-wrap">
  <table class="data-table">
    <thead><tr>
      <th><input type="checkbox" id="checkAllOrders" aria-label="Select all"></th>
      <th>Order #</th><th>Date</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
      <?php foreach ($res['items'] as $o): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= (int)$o['id'] ?>"></td>
          <td><strong><?= e($o['order_number']) ?></strong></td>
          <td><?= e(date('d M Y', strtotime((string)$o['placed_at']))) ?></td>
          <td><?= e($o['customer_name']) ?><br><small><?= e($o['customer_phone']) ?> · <?= e($o['city']) ?></small></td>
          <td><?= money((float)$o['grand_total']) ?></td>
          <td><?= e((string)$o['payment_method']) ?> · <span class="pay-status pay-<?= e($o['payment_status']) ?>"><?= e((string)$o['payment_status']) ?></span></td>
          <td><span class="status-pill status-<?= e($o['status']) ?>"><?= e(\Order::label((string)$o['status'])) ?></span></td>
          <td><a class="btn btn-sm btn-outline" href="/admin/orders/<?= (int)$o['id'] ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$res['items']): ?><tr><td colspan="8" class="empty-cell">No orders found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</form>

<?= paginate((int)$res['total'], 20, max(1,(int)($_GET['page'] ?? 1)), '/admin/orders?' . http_build_query(array_merge($_GET, ['page'=>null]))) ?>

<script>
document.getElementById('checkAllOrders')?.addEventListener('change', function(){ document.querySelectorAll('input[name="ids[]"]').forEach(c=>c.checked=this.checked); });
</script>
