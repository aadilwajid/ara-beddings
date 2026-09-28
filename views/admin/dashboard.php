<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<div class="stat-grid">
  <div class="stat-card"><span class="stat-num"><?= money($stats['sales_today']) ?></span><span class="stat-lbl">Sales Today</span></div>
  <div class="stat-card"><span class="stat-num"><?= money($stats['revenue_total']) ?></span><span class="stat-lbl">Total Revenue</span></div>
  <div class="stat-card"><span class="stat-num"><?= (int)$stats['orders_today'] ?></span><span class="stat-lbl">Orders Today</span></div>
  <div class="stat-card warn"><span class="stat-num"><?= (int)$stats['orders_pending'] ?></span><span class="stat-lbl">Pending Orders</span></div>
  <div class="stat-card"><span class="stat-num"><?= (int)$stats['orders_total'] ?></span><span class="stat-lbl">All Orders</span></div>
  <div class="stat-card"><span class="stat-num"><?= (int)$stats['customers'] ?></span><span class="stat-lbl">Customers</span></div>
  <div class="stat-card"><span class="stat-num"><?= (int)$stats['products'] ?></span><span class="stat-lbl">Products</span></div>
  <div class="stat-card <?= (int)$stats['low_stock'] > 0 ? 'warn' : '' ?>"><span class="stat-num"><?= (int)$stats['low_stock'] ?></span><span class="stat-lbl">Low Stock</span></div>
</div>

<div class="dash-cols">
  <section class="panel">
    <h2>Recent Orders</h2>
    <?php if (!$stats['recent_orders']): ?><p>No orders yet.</p><?php else: ?>
    <table class="data-table">
      <thead><tr><th>#</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Placed</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($stats['recent_orders'] as $o): ?>
          <tr>
            <td><?= e($o['order_number']) ?></td>
            <td><?= e($o['customer_name']) ?></td>
            <td><?= money((float)$o['grand_total']) ?></td>
            <td><?= e(\Order::label((string)$o['payment_method'])) ?></td>
            <td><span class="status-pill status-<?= e($o['status']) ?>"><?= e(\Order::label((string)$o['status'])) ?></span></td>
            <td><?= e(date('d M H:i', strtotime((string)$o['placed_at']))) ?></td>
            <td><a class="btn btn-sm btn-outline" href="/admin/orders/<?= (int)$o['id'] ?>">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    <p><a href="/admin/orders">View all orders →</a></p>
  </section>

  <section class="panel">
    <h2>Sales — Last 7 Days</h2>
    <?php
      $max = 0.0;
      foreach ($stats['sales_7d'] as $d) $max = max($max, (float)$d['t']);
    ?>
    <?php if (!$stats['sales_7d']): ?><p>No sales in the last 7 days.</p><?php else: ?>
      <ul class="barlist">
        <?php foreach ($stats['sales_7d'] as $d): ?>
          <li>
            <span class="barlbl"><?= e(date('D d', strtotime((string)$d['d']))) ?></span>
            <span class="bartrack"><span class="barfill" style="width: <?= $max > 0 ? round((float)$d['t'] / $max * 100) : 0 ?>%"></span></span>
            <span class="barval"><?= money((float)$d['t']) ?> · <?= (int)$d['c'] ?> order<?= (int)$d['c'] === 1 ? '' : 's' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <h2>Top Products</h2>
    <?php if (!$stats['top_products']): ?><p>No sales data yet.</p><?php else: ?>
      <ol class="top-products">
        <?php foreach ($stats['top_products'] as $tp): ?>
          <li><a href="/admin/products/edit/<?= (int)$tp['id'] ?>"><?= e($tp['name']) ?></a> <small>(<?= (int)$tp['q'] ?> sold)</small></li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>
</div>
