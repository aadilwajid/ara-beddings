</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <h3><?= e(setting('store_name')) ?></h3>
      <p><?= e(setting('store_description')) ?></p>
      <p><?= e(setting('store_address')) ?><br>
         <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string)setting('contact_phone'))) ?>"><?= e(setting('contact_phone')) ?></a><br>
         <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></p>
    </div>
    <div>
      <h4>Shop</h4>
      <a href="/shop">All Products</a>
      <?php foreach (array_slice(Category::all(), 0, 6) as $c): ?>
        <a href="/category/<?= e($c['slug']) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <div>
      <h4>Help</h4>
      <a href="/track">Track Order</a>
      <a href="/returns">Return Policy</a>
      <a href="/terms">Terms &amp; Conditions</a>
      <a href="/privacy">Privacy Policy</a>
      <a href="/contact">Contact Us</a>
    </div>
    <div>
      <h4>Follow / Pay</h4>
      <div class="social-links">
        <?php if ($s = setting('facebook_url')): ?><a href="<?= e($s) ?>" rel="noopener" target="_blank">Facebook</a><?php endif; ?>
        <?php if ($s = setting('instagram_url')): ?><a href="<?= e($s) ?>" rel="noopener" target="_blank">Instagram</a><?php endif; ?>
        <?php if ($s = setting('tiktok_url')): ?><a href="<?= e($s) ?>" rel="noopener" target="_blank">TikTok</a><?php endif; ?>
        <?php if ($s = setting('youtube_url')): ?><a href="<?= e($s) ?>" rel="noopener" target="_blank">YouTube</a><?php endif; ?>
      </div>
      <p class="pay-badges">
        <span>Cash on Delivery</span>
        <?php if (setting('easypaisa_enabled')==='1' && setting('easypaisa_number')): ?><span>Easypaisa</span><?php endif; ?>
        <?php if (setting('jazzcash_enabled')==='1' && setting('jazzcash_number')): ?><span>JazzCash</span><?php endif; ?>
        <?php if (setting('bank_transfer_enabled')==='1' && setting('bank_iban')): ?><span>Bank Transfer</span><?php endif; ?>
      </p>
    </div>
  </div>
  <div class="footer-bottom container">
    &copy; <?= date('Y') ?> <?= e(setting('store_name')) ?> · Prices in <?= e(setting('currency_code','PKR')) ?> · Pakistan
  </div>
</footer>
<script src="/assets/js/app.js?v=1" defer></script>
</body>
</html>
