<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<?php $sec = fn(string $k): bool => in_array($k, $sections ?? [], true); ?>

<?php if ($sec('hero')): ?>
<section class="hero">
  <div class="hero-inner">
    <h1><?= e(setting('hero_title', 'Quality products, delivered across Pakistan')) ?></h1>
    <p><?= e(setting('hero_subtitle', 'Cash on Delivery · Easy returns · Nationwide shipping')) ?></p>
    <div class="hero-cta">
      <a class="btn btn-primary" href="/shop"><?= e(setting('hero_button_text', 'Shop Now')) ?></a>
      <?php if (setting('whatsapp_number')): $wa = preg_replace('/\D+/', '', (string)setting('whatsapp_number')); ?>
        <a class="btn btn-outline" target="_blank" rel="noopener"
           href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Hi ' . setting('store_name') . ', I would like to order') ?>">Order on WhatsApp</a>
      <?php endif; ?>
    </div>
  </div>
  <img class="hero-img" src="<?= e(setting('hero_image', '/assets/img/hero.svg')) ?>" alt="<?= e(setting('store_name')) ?> featured collection" width="640" height="420">
</section>
<?php endif; ?>

<?php if ($sec('categories') && !empty($categories)): ?>
<section class="home-section">
  <h2 class="section-title">Shop by Category</h2>
  <div class="cat-grid">
    <?php foreach ($categories as $c): ?>
      <a class="cat-tile" href="/category/<?= e($c['slug']) ?>">
        <?php if (!empty($c['image'])): ?>
          <img src="<?= e($c['image']) ?>" alt="<?= e($c['name']) ?> category" loading="lazy">
        <?php else: ?>
          <span class="img-placeholder cat-ph"><?= mb_substr(e($c['name']), 0, 2) ?></span>
        <?php endif; ?>
        <span><?= e($c['name']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($sec('featured') && !empty($featured)): ?>
<section class="home-section">
  <h2 class="section-title">Featured Products</h2>
  <div class="product-grid">
    <?php foreach ($featured as $p) require dirname(__DIR__, 2) . '/components/product-card.php'; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($sec('promo')): ?>
<section class="home-section promo-strip">
  <div class="promo-card"><strong>🚚 <?= e(setting('shipping_info_text', 'Fast delivery all over Pakistan')) ?></strong></div>
  <div class="promo-card"><strong>💵 Cash on Delivery available</strong></div>
  <div class="promo-card"><strong>↩️ <?= (int)setting('return_policy_days', '7') ?>-day easy returns</strong></div>
</section>
<?php endif; ?>

<?php if ($sec('new') && !empty($newItems)): ?>
<section class="home-section">
  <h2 class="section-title">New Arrivals</h2>
  <div class="product-grid">
    <?php foreach ($newItems as $p) require dirname(__DIR__, 2) . '/components/product-card.php'; ?>
  </div>
  <p class="see-all"><a href="/shop?sort=new">See all new products →</a></p>
</section>
<?php endif; ?>

<?php if ($sec('best') && !empty($bestsellers)): ?>
<section class="home-section">
  <h2 class="section-title">Best Sellers</h2>
  <div class="product-grid">
    <?php foreach ($bestsellers as $p) require dirname(__DIR__, 2) . '/components/product-card.php'; ?>
  </div>
  <p class="see-all"><a href="/shop?sort=best">See all products →</a></p>
</section>
<?php endif; ?>

<?php if ($sec('reviews') && !empty($reviews)): ?>
<section class="home-section">
  <h2 class="section-title">What Customers Say</h2>
  <div class="review-grid">
    <?php foreach ($reviews as $r): ?>
      <blockquote class="review-card">
        <div class="stars" aria-label="<?= (int)$r['rating'] ?> out of 5 stars"><?= str_repeat('★', (int)$r['rating']) ?><span class="dim"><?= str_repeat('★', 5 - (int)$r['rating']) ?></span></div>
        <?php if ($r['title']): ?><strong><?= e($r['title']) ?></strong><?php endif; ?>
        <p><?= e(mb_substr((string)$r['body'], 0, 180)) ?></p>
        <footer>— <?= e($r['author_name']) ?><?php if (!empty($r['product_name'])): ?>, <a href="/product/<?= e($r['product_slug'] ?? '') ?>"><?= e($r['product_name']) ?></a><?php endif; ?></footer>
      </blockquote>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($sec('trust')): ?>
<section class="home-section trust-row">
  <span>✅ Genuine products</span><span>📞 <?= e(setting('contact_phone')) ?> support</span>
  <span>🏠 <?= e(setting('store_address')) ?></span>
</section>
<?php endif; ?>

<?php if ($sec('payments')): ?>
<section class="home-section pay-methods">
  <h2 class="section-title">Payment Methods</h2>
  <div class="pay-list">
    <?php if (setting('cod_enabled')==='1'): ?><span class="pay-chip">Cash on Delivery</span><?php endif; ?>
    <?php if (setting('bank_transfer_enabled')==='1' && setting('bank_iban')): ?><span class="pay-chip">Bank Transfer</span><?php endif; ?>
    <?php if (setting('easypaisa_enabled')==='1' && setting('easypaisa_number')): ?><span class="pay-chip">Easypaisa</span><?php endif; ?>
    <?php if (setting('jazzcash_enabled')==='1' && setting('jazzcash_number')): ?><span class="pay-chip">JazzCash</span><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($sec('newsletter')): ?>
<section class="home-section newsletter">
  <h2 class="section-title">Stay in the loop</h2>
  <p>Get updates about new arrivals &amp; offers.</p>
  <form id="newsletterForm" class="nl-form" autocomplete="off">
    <input type="email" name="email" required placeholder="Your email address" aria-label="Email address">
    <button class="btn btn-primary" type="submit">Subscribe</button>
  </form>
  <p class="nl-msg" role="status" aria-live="polite"></p>
</section>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
