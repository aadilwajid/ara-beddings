<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<h1 class="page-title">Checkout</h1>

<form method="post" action="/checkout" id="checkoutForm" class="checkout-layout" autocomplete="on">
  <?= csrf_field() ?>
  <input type="hidden" name="grand_total_expected" value="<?= e((string)$cart['total']) ?>">

  <div class="checkout-form">
    <?php if (!$user): ?><p class="login-hint">Already have an account? <a href="/login?next=/checkout">Login</a> for faster checkout (optional).</p><?php endif; ?>

    <fieldset>
      <legend>Contact Details</legend>
      <div class="form-grid">
        <label>Full Name *<input type="text" name="full_name" required maxlength="120" value="<?= e(old('full_name', $user['name'] ?? '')) ?>"></label>
        <label>Mobile Number *<input type="tel" name="phone" required maxlength="20" inputmode="tel" placeholder="03XXXXXXXXX" pattern="(?:\+?92|0)3\d{9}" title="Pakistani mobile, e.g. 03001234567" value="<?= e(old('phone', $user['phone'] ?? '')) ?>"></label>
        <label>Email (for order updates)<input type="email" name="email" maxlength="190" value="<?= e(old('email', $user['email'] ?? '')) ?>"></label>
      </div>
    </fieldset>

    <fieldset>
      <legend>Delivery Address</legend>
      <?php if ($user && !empty($addresses)): ?>
        <div class="saved-addresses">
          <label><input type="radio" name="use_address" value="new" checked> Enter a new address</label>
          <?php foreach ($addresses as $a): ?>
            <label>
              <input type="radio" name="use_address" value="<?= (int)$a['id'] ?>"
                     data-fill='<?= json_encode(["full_name"=>$a['full_name'],"phone"=>$a['phone'],"province"=>$a['province'],"city"=>$a['city'],"area"=>$a['area'],"address"=>$a['address'],"landmark"=>$a['landmark'],"postal_code"=>$a['postal_code']], JSON_UNESCAPED_UNICODE) ?>'>
              <?= e($a['label']) ?> — <?= e($a['address']) ?>, <?= e($a['city']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="form-grid">
        <label>Province *
          <select name="province" id="provinceSelect" required>
            <option value="">Select province…</option>
            <?php foreach ($provinces as $pv): ?>
              <option value="<?= e($pv) ?>" <?= old('province') === $pv ? 'selected' : '' ?>><?= e($pv) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>City *<input type="text" name="city" id="cityInput" required maxlength="80" value="<?= e(old('city')) ?>"></label>
        <label>Area / Town<input type="text" name="area" maxlength="120" value="<?= e(old('area')) ?>"></label>
        <label>Postal Code<input type="text" name="postal_code" maxlength="12" inputmode="numeric" value="<?= e(old('postal_code')) ?>"></label>
        <label class="full">Complete Address *<textarea name="address" required rows="2" maxlength="600"><?= e(old('address')) ?></textarea></label>
        <label class="full">Landmark<input type="text" name="landmark" maxlength="160" value="<?= e(old('landmark')) ?>" placeholder="Nearby landmark for the rider"></label>
        <label class="full">Order Notes<textarea name="notes" rows="2" maxlength="1000" placeholder="Delivery instructions, colour preference, etc."><?= e(old('notes')) ?></textarea></label>
      </div>
      <p class="ship-estimate" id="shipEstimate" aria-live="polite"></p>
    </fieldset>

    <fieldset>
      <legend>Payment Method *</legend>
      <?php if (!$methods): ?>
        <p class="flash flash-error">No payment methods are currently enabled. Please <a href="/contact">contact us</a>.</p>
      <?php else: foreach ($methods as $key => $label): ?>
        <label class="pay-option">
          <input type="radio" name="payment_method" value="<?= e($key) ?>" required <?= $key === 'cod' ? 'checked' : '' ?> data-pay="<?= e($key) ?>">
          <span>
            <strong><?= e($label) ?></strong>
            <small>
              <?= $key === 'cod' ? 'Pay in cash when your order arrives at your doorstep.'
                : ($key === 'bank_transfer' ? 'Transfer to our bank account; order ships after payment is verified.'
                : ($key === 'easypaisa' ? 'Send to our Easypaisa number; share the transaction ID after ordering.'
                : 'Send to our JazzCash number; share the transaction ID after ordering.'))) ?>
            </small>
          </span>
        </label>
      <?php endforeach; endif; ?>
      <template id="payInstructionsTpl">
        <?php // Server-rendered instructions per enabled manual method (never trusted from browser) ?>
        <?php foreach ($methods as $key => $label): if ($key === 'cod') continue; ?>
          <div data-pay="<?= e($key) ?>"><?= Shipping::paymentInstructions($key, $cart['total']) ?></div>
        <?php endforeach; ?>
      </template>
      <div class="manual-pay-note" id="manualPayNote" hidden></div>
    </fieldset>
  </div>

  <aside class="cart-summary checkout-summary">
    <h2>Your Order</h2>
    <ul class="mini-items">
      <?php foreach ($cart['items'] as $it): ?>
        <li>
          <span><?= e($it['name']) ?><?php if ($it['variant']): ?> <em>(<?= e($it['variant']) ?>)</em><?php endif; ?> × <?= (int)$it['quantity'] ?></span>
          <span><?= money($it['line_total']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <dl class="summary-lines">
      <div><dt>Subtotal</dt><dd><?= money($cart['subtotal']) ?></dd></div>
      <?php if ($cart['discount'] > 0): ?><div class="discount-line"><dt>Discount (<?= e((string)$cart['coupon']) ?>)</dt><dd>− <?= money($cart['discount']) ?></dd></div><?php endif; ?>
      <div><dt>Shipping</dt><dd id="sumShipping"><?= $cart['shipping'] > 0 ? money($cart['shipping']) : 'Free' ?></dd></div>
      <?php if ($cart['tax'] > 0): ?><div><dt>Tax (<?= e(setting('tax_rate','0')) ?>%)</dt><dd><?= money($cart['tax']) ?></dd></div><?php endif; ?>
      <div class="grand"><dt>Total Payable</dt><dd><?= money($cart['total']) ?></dd></div>
    </dl>
    <p class="recalc-note">Final totals are recalculated and verified on the server when you place the order.</p>
    <button class="btn btn-primary btn-block" type="submit" <?= $methods ? '' : 'disabled' ?>>Place Order</button>
    <a class="btn btn-link" href="/cart">← Back to cart</a>
  </aside>
</form>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
