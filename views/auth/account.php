<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<h1 class="page-title">My Account</h1>
<div class="account-layout">
  <nav class="account-nav">
    <a href="/account" class="active">Profile</a>
    <a href="/account/addresses">Addresses</a>
    <a href="/wishlist">Wishlist</a>
    <a href="/logout">Logout</a>
  </nav>

  <div class="account-main">
    <section class="account-card">
      <h2>Profile Details</h2>
      <form method="post" action="/account" class="profile-form">
        <?= csrf_field() ?>
        <div class="form-grid">
          <label>Name *<input type="text" name="name" required maxlength="120" value="<?= e($u['name']) ?>"></label>
          <label>Mobile<input type="tel" name="phone" maxlength="20" pattern="(?:\+?92|0)3\d{9}" title="Pakistani mobile, e.g. 03001234567" value="<?= e((string)$u['phone']) ?>"></label>
          <label>Email<input type="email" value="<?= e($u['email']) ?>" disabled><small>Contact support to change your email.</small></label>
        </div>
        <button class="btn btn-primary" type="submit">Save Changes</button>
      </form>
    </section>

    <section class="account-card">
      <h2>Recent Orders</h2>
      <?php if (!$orders): ?>
        <p>No orders yet. <a href="/shop">Start shopping →</a></p>
      <?php else: ?>
        <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Order</th><th>Date</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
              <tr>
                <td><?= e($o['order_number']) ?></td>
                <td><?= e(date('d M Y', strtotime((string)$o['placed_at']))) ?></td>
                <td><?= money((float)$o['grand_total']) ?></td>
                <td><span class="pay-status pay-<?= e($o['payment_status']) ?>"><?= e(ucfirst((string)$o['payment_status'])) ?></span></td>
                <td><span class="status-pill status-<?= e($o['status']) ?>"><?= e(\Order::label((string)$o['status'])) ?></span></td>
                <td><a class="btn btn-sm btn-outline" href="/order/<?= e($o['order_number']) ?>">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
