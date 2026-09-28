<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<h1 class="page-title">My Account</h1>
<div class="account-layout">
  <nav class="account-nav">
    <a href="/account">Profile</a>
    <a href="/account/addresses" class="active">Addresses</a>
    <a href="/wishlist">Wishlist</a>
    <a href="/logout">Logout</a>
  </nav>

  <div class="account-main">
    <section class="account-card">
      <h2>Saved Addresses</h2>
      <?php if (!$addresses): ?><p>No saved addresses yet.</p><?php else: ?>
        <div class="addr-grid">
          <?php foreach ($addresses as $a): ?>
            <div class="addr-card">
              <strong><?= e($a['label']) ?> <?= (int)$a['is_default'] === 1 ? '★ Default' : '' ?></strong>
              <p><?= e($a['full_name']) ?> · <?= e($a['phone']) ?><br>
                 <?= e($a['address']) ?><?= $a['landmark'] ? ', near ' . e($a['landmark']) : '' ?><br>
                 <?= e($a['area']) ? e($a['area']) . ', ' : '' ?><?= e($a['city']) ?>, <?= e($a['province']) ?><?= $a['postal_code'] ? ' — ' . e($a['postal_code']) : '' ?></p>
              <form method="post" action="/account/addresses/delete">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button class="btn btn-sm btn-link danger" type="submit">Delete</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="account-card">
      <h2>Add New Address</h2>
      <form method="post" action="/account/addresses" class="address-form">
        <?= csrf_field() ?>
        <div class="form-grid">
          <label>Label<input type="text" name="label" maxlength="60" placeholder="Home / Office / Shop" value="<?= e(old('label', 'Home')) ?>"></label>
          <label>Full Name *<input type="text" name="full_name" required maxlength="120" value="<?= e($u['name']) ?>"></label>
          <label>Mobile *<input type="tel" name="phone" required maxlength="20" pattern="(?:\+?92|0)3\d{9}" value="<?= e((string)$u['phone']) ?>"></label>
          <label>Province *
            <select name="province" required>
              <option value="">Select…</option>
              <?php foreach ($provinces as $pv): ?><option value="<?= e($pv) ?>"><?= e($pv) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label>City *<input type="text" name="city" required maxlength="80"></label>
          <label>Area<input type="text" name="area" maxlength="120"></label>
          <label class="full">Complete Address *<textarea name="address" required rows="2" maxlength="600"></textarea></label>
          <label>Landmark<input type="text" name="landmark" maxlength="160"></label>
          <label>Postal Code<input type="text" name="postal_code" maxlength="12"></label>
          <label class="check"><input type="checkbox" name="is_default" value="1"> Set as default address</label>
        </div>
        <button class="btn btn-primary" type="submit">Save Address</button>
      </form>
    </section>
  </div>
</div>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
