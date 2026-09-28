<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<h1 class="page-title">Contact Us</h1>
<div class="contact-grid">
  <div class="contact-info">
    <p>We're happy to help with product questions, orders and delivery support.</p>
    <ul class="contact-list">
      <?php if ($ph = setting('contact_phone')): ?><li>📞 Phone: <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $ph)) ?>"><?= e($ph) ?></a></li><?php endif; ?>
      <?php if ($em = setting('contact_email')): ?><li>✉️ Email: <a href="mailto:<?= e($em) ?>"><?= e($em) ?></a></li><?php endif; ?>
      <?php if ($ad = setting('store_address')): ?><li>🏠 Address: <?= e($ad) ?></li><?php endif; ?>
      <?php if ($wa = preg_replace('/\D+/', '', (string)setting('whatsapp_number', ''))): ?>
        <li>💬 WhatsApp: <a target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Hi ' . setting('store_name') . ', I need help with my order/product.') ?>"><?= e(setting('whatsapp_number')) ?></a></li>
      <?php endif; ?>
    </ul>
    <?php if ($s = setting('facebook_url')): ?><a href="<?= e($s) ?>" target="_blank" rel="noopener">Facebook</a><?php endif; ?>
    <?php if ($s = setting('instagram_url')): ?> · <a href="<?= e($s) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
  </div>
  <div class="contact-form-wrap">
    <h2>Send a message by email</h2>
    <form method="get" action="mailto:<?= e(setting('contact_email')) ?>" enctype="text/plain" class="contact-mailto">
      <label>Your Name<input type="text" name="subject" placeholder="Message from (pre-fills subject)"></label>
      <label>Message<textarea name="body" rows="5" placeholder="How can we help?"></textarea></label>
      <button class="btn btn-primary" type="submit">Open Email App</button>
    </form>
    <p class="help-note">This opens your own email app — nothing is stored on the website. For fastest replies use WhatsApp or phone.</p>
  </div>
</div>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
