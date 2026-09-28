<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<?php
$gallery = array_map(fn($i) => ['image' => $i['image'], 'alt' => $i['alt_text'] ?: $p['name']], $images);
$specs = json_decode((string)($p['specifications'] ?? '[]'), true) ?: [];
$eff = effective_price($p);
$inWishlist = false;
if (current_user() && function_exists('User::wishlistIds')) {
    $inWishlist = in_array((int)$p['id'], User::wishlistIds((int)current_user()['id']), true);
}
?>

<nav class="breadcrumbs" aria-label="Breadcrumb">
  <ol itemscope itemtype="https://schema.org/BreadcrumbList">
    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
      <a itemprop="item" href="/"><span itemprop="name">Home</span></a><meta itemprop="position" content="1">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
      <a itemprop="item" href="/shop"><span itemprop="name">Shop</span></a><meta itemprop="position" content="2">
    </li>
    <?php if ($category): ?>
    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
      <a itemprop="item" href="/category/<?= e($category['slug']) ?>"><span itemprop="name"><?= e($category['name']) ?></span></a><meta itemprop="position" content="3">
    </li>
    <?php endif; ?>
    <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" aria-current="page">
      <span itemprop="name"><?= e($p['name']) ?></span><meta itemprop="position" content="<?= $category ? 4 : 3 ?>">
    </li>
  </ol>
</nav>

<div class="product-page" data-product-id="<?= (int)$p['id'] ?>" id="productPage">
  <div class="product-gallery">
    <?php if ($gallery): ?>
      <img id="mainImage" src="<?= e($gallery[0]['image']) ?>" alt="<?= e($gallery[0]['alt']) ?>" width="600" height="600" fetchpriority="high">
      <div class="thumbs">
        <?php foreach ($gallery as $i => $g): ?>
          <button class="thumb <?= $i === 0 ? 'active' : '' ?>" data-full="<?= e($g['image']) ?>" aria-label="View image <?= $i + 1 ?>">
            <img src="<?= e($g['image']) ?>" alt="<?= e($g['alt']) ?> thumbnail" loading="lazy" width="72" height="72">
          </button>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <span class="img-placeholder big" role="img" aria-label="<?= e($p['name']) ?> image"><?= mb_substr(e($p['name']), 0, 2) ?></span>
    <?php endif; ?>
  </div>

  <div class="product-info">
    <h1><?= e($p['name']) ?></h1>
    <?php if ((int)$p['rating_count'] > 0): ?>
      <div class="pc-rating">
        <?= str_repeat('★', (int)round((float)$p['rating_avg'])) ?><span class="dim"><?= str_repeat('★', 5 - (int)round((float)$p['rating_avg'])) ?></span>
        <a href="#reviews">(<?= number_format((float)$p['rating_avg'], 1) ?> · <?= (int)$p['rating_count'] ?> review<?= (int)$p['rating_count'] === 1 ? '' : 's' ?>)</a>
      </div>
    <?php endif; ?>

    <div class="price-block">
      <?php if ($p['sale_price'] && (float)$p['sale_price'] < (float)$p['price']): ?>
        <s id="regularPrice"><?= money((float)$p['price']) ?></s>
      <?php else: ?>
        <s id="regularPrice" hidden><?= money((float)$p['price']) ?></s>
      <?php endif; ?>
      <strong class="price-now" id="priceNow"><?= money($eff) ?></strong>
      <span class="tax-note"><?php if (setting('tax_enabled')==='1'): ?>Incl. GST info at checkout<?php endif; ?></span>
    </div>

    <?php if ($p['short_description']): ?><p class="short-desc"><?= e($p['short_description']) ?></p><?php endif; ?>

    <?php if ($p['is_variable'] && !empty($variants)): ?>
      <!-- Variant selectors -->
      <div class="variant-selectors" id="variantSelectors">
        <?php foreach ($options as $attrName => $values): ?>
          <div class="opt-group" data-attr="<?= e($attrName) ?>">
            <label><?= e($attrName) ?>: <span class="opt-chosen" data-chosen="<?= e($attrName) ?>">—</span></label>
            <div class="opt-values">
              <?php foreach ($values as $val): ?>
                <button type="button" class="opt-btn" data-value="<?= e($val) ?>"><?= e($val) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <script id="variantData" type="application/json"><?= json_encode($variants, JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>

    <p class="stock-line" id="stockLine" aria-live="polite">
      <?php if ($p['stock_status'] === 'out_of_stock'): ?>
        <span class="oos-text">Out of stock</span>
      <?php elseif ($p['is_variable']): ?>
        Select options to check availability.
      <?php elseif ((int)$p['stock_quantity'] <= (int)$p['low_stock_threshold']): ?>
        <span class="low-text">Only <?= (int)$p['stock_quantity'] ?> left in stock</span>
      <?php else: ?>
        <span class="in-text">✓ In stock</span>
      <?php endif; ?>
    </p>
    <p class="sku-line" id="skuLine">SKU: <span><?= e($p['sku'] ?: '—') ?></span></p>

    <form class="buy-form" id="buyForm" method="post" action="/cart/add-html">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
      <input type="hidden" name="variant_id" id="variantInput" value="">
      <div class="qty-row">
        <label for="qty" class="sr-only">Quantity</label>
        <div class="qty-picker">
          <button type="button" class="qty-minus" aria-label="Decrease quantity">−</button>
          <input id="qty" name="quantity" type="number" min="1" max="<?= (int)($p['is_variable'] ? 99 : $p['stock_quantity']) ?>" value="1" inputmode="numeric">
          <button type="button" class="qty-plus" aria-label="Increase quantity">+</button>
        </div>
        <button type="submit" class="btn btn-primary add-to-cart-btn" id="addToCartBtn"
                <?= (!$p['is_variable'] && $p['stock_status'] === 'out_of_stock') ? 'disabled' : '' ?>>Add to Cart</button>
        <button type="submit" class="btn btn-secondary buy-now-btn" name="buy_now" value="1" id="buyNowBtn"
                <?= (!$p['is_variable'] && $p['stock_status'] === 'out_of_stock') ? 'disabled' : '' ?>>Buy Now</button>
      </div>
    </form>

    <?php if (current_user()): ?>
      <form method="post" action="/wishlist/toggle" class="wishlist-form">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
        <button class="btn btn-link"><?= $inWishlist ? '♥ Remove from Wishlist' : '♡ Add to Wishlist' ?></button>
      </form>
    <?php else: ?>
      <p class="wishlist-hint"><a href="/login">Login</a> to save this item to your wishlist.</p>
    <?php endif; ?>

    <?php if ($wa = preg_replace('/\D+/', '', (string)setting('whatsapp_number', ''))): ?>
      <p class="wa-order">
        <a target="_blank" rel="noopener" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode('Hi ' . setting('store_name') . ', I am interested in: ' . url('/product/' . $p['slug'])) ?>">
          💬 Ask about this product on WhatsApp
        </a>
      </p>
    <?php endif; ?>

    <ul class="mini-trust">
      <li>🚚 Shipping across Pakistan</li>
      <li>💵 Cash on Delivery available</li>
      <li>↩️ <?= (int)setting('return_policy_days', '7') ?>-day returns</li>
    </ul>
  </div>
</div>

<section class="tabs" id="productTabs">
  <div class="tab-heads" role="tablist">
    <button class="tab-head active" role="tab" aria-selected="true" data-tab="desc">Description</button>
    <?php if ($specs): ?><button class="tab-head" role="tab" aria-selected="false" data-tab="specs">Specifications</button><?php endif; ?>
    <button class="tab-head" role="tab" aria-selected="false" data-tab="ship">Shipping &amp; Returns</button>
    <button class="tab-head" role="tab" aria-selected="false" data-tab="revs">Reviews (<?= count($reviews) ?>)</button>
  </div>
  <div class="tab-panel active" data-panel="desc">
    <?= nl2br(e((string)($p['description'] ?: $p['short_description'] ?: 'No description provided.'))) ?>
  </div>
  <?php if ($specs): ?>
  <div class="tab-panel" data-panel="specs">
    <table class="spec-table"><tbody>
      <?php foreach ($specs as $s): if (empty($s['label'])) continue; ?>
        <tr><th><?= e($s['label']) ?></th><td><?= e($s['value'] ?? '') ?></td></tr>
      <?php endforeach; ?>
      <?php if ($p['weight_kg']): ?><tr><th>Weight</th><td><?= e($p['weight_kg']) ?> kg</td></tr><?php endif; ?>
      <?php if ($p['length_cm'] || $p['width_cm'] || $p['height_cm']): ?>
        <tr><th>Dimensions</th><td><?= e($p['length_cm']) ?> × <?= e($p['width_cm']) ?> × <?= e($p['height_cm']) ?> cm</td></tr>
      <?php endif; ?>
    </tbody></table>
  </div>
  <?php endif; ?>
  <div class="tab-panel" data-panel="ship">
    <p><strong>Shipping:</strong> We deliver all over Pakistan
      <?php $mode = setting('shipping_mode','flat'); ?>
      — <?= setting('free_shipping_threshold') > 0 ? 'free shipping on orders over ' . money((float)setting('free_shipping_threshold')) . ';' : '' ?>
      standard shipping applies at checkout (calculated by city/province settings).</p>
    <p><strong>Returns:</strong>
      <?= e(str_replace('{days}', setting('return_policy_days','7'), (string)(setting('return_policy') ?: (setting('return_policy_days','7') . '-day return window on unused items in original packaging.')))) ?>
      <a href="/returns">Full policy</a></p>
  </div>
  <div class="tab-panel" data-panel="revs" id="reviews">
    <?php if (!$reviews): ?>
      <p>No reviews yet — be the first to review this product.</p>
    <?php else: foreach ($reviews as $r): ?>
      <article class="review-card">
        <div class="stars"><?= str_repeat('★', (int)$r['rating']) ?><span class="dim"><?= str_repeat('★', 5 - (int)$r['rating']) ?></span></div>
        <?php if ($r['title']): ?><strong><?= e($r['title']) ?></strong><?php endif; ?>
        <p><?= nl2br(e((string)$r['body'])) ?></p>
        <footer>— <?= e($r['author_name']) ?> · <?= e(substr((string)$r['created_at'], 0, 10)) ?></footer>
      </article>
    <?php endforeach; endif; ?>

    <form method="post" action="/product/<?= e($p['slug']) ?>/review" class="review-form" autocomplete="off">
      <?= csrf_field() ?>
      <h3>Write a review</h3>
      <div class="form-grid">
        <label>Your name *<input type="text" name="author" required maxlength="120" value="<?= e(current_user()['name'] ?? '') ?>"></label>
        <label>Rating *
          <select name="rating" required>
            <option value="5">★★★★★ Excellent</option><option value="4">★★★★ Good</option>
            <option value="3">★★★ Average</option><option value="2">★★ Fair</option><option value="1">★ Poor</option>
          </select>
        </label>
        <label class="full">Title<input type="text" name="title" maxlength="160"></label>
        <label class="full">Review<textarea name="body" rows="4" maxlength="4000"></textarea></label>
      </div>
      <button class="btn btn-primary" type="submit">Submit Review</button>
      <small>Reviews appear after moderation.</small>
    </form>
  </div>
</section>

<?php if ($related): ?>
<section class="home-section">
  <h2 class="section-title">Related Products</h2>
  <div class="product-grid"><?php foreach ($related as $p) require dirname(__DIR__, 2) . '/components/product-card.php'; ?></div>
</section>
<?php endif; ?>

<?php
$recentItems = !empty($recentlyIds) ? Product::byIds(array_diff($recentlyIds, [(int)$p['id']])) : [];
$recentItems = array_slice($recentItems, 0, 4);
?>
<?php if ($recentItems): ?>
<section class="home-section">
  <h2 class="section-title">Recently Viewed</h2>
  <div class="product-grid"><?php foreach ($recentItems as $p) require dirname(__DIR__, 2) . '/components/product-card.php'; ?></div>
</section>
<?php endif; ?>

<script type="application/ld+json"><?= json_encode([
  '@context' => 'https://schema.org',
  '@type' => 'Product',
  'name' => $p['name'],
  'description' => $p['short_description'] ?: mb_substr((string)$p['description'], 0, 300),
  'sku' => $p['sku'],
  'image' => array_column($gallery, 'image'),
  'brand' => ['@type' => 'Brand', 'name' => setting('store_name')],
  'offers' => [
    '@type' => 'Offer',
    'priceCurrency' => setting('currency_code', 'PKR'),
    'price' => $eff,
    'availability' => $p['stock_status'] === 'in_stock' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
    'url' => url('/product/' . $p['slug']),
  ] + ((int)$p['rating_count'] > 0 ? ['aggregateRating' => [
      '@type' => 'AggregateRating',
      'ratingValue' => (float)$p['rating_avg'],
      'reviewCount' => (int)$p['rating_count'],
    ]] : []),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
