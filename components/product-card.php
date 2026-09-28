<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<article class="product-card">
  <a class="pc-img" href="/product/<?= e($p['slug']) ?>">
    <?php if (!empty($p['thumb'])): ?>
      <img src="<?= e($p['thumb']) ?>" alt="<?= e($p['thumb_alt'] ?: $p['name']) ?>" loading="lazy" width="300" height="300">
    <?php else: ?>
      <span class="img-placeholder" role="img" aria-label="<?= e($p['name']) ?> image"><?= mb_substr(e($p['name']), 0, 2) ?></span>
    <?php endif; ?>
    <?php if ($p['sale_price'] && (float)$p['sale_price'] < (float)$p['price']): ?>
      <span class="badge sale">Sale</span>
    <?php endif; ?>
    <?php if ($p['stock_status'] === 'out_of_stock'): ?><span class="badge oos">Out of stock</span><?php endif; ?>
  </a>
  <div class="pc-body">
    <h3 class="pc-name"><a href="/product/<?= e($p['slug']) ?>"><?= e($p['name']) ?></a></h3>
    <?php if ((int)$p['rating_count'] > 0): ?>
      <div class="pc-rating" aria-label="Rating <?= number_format((float)$p['rating_avg'],1) ?> out of 5">
        <?= str_repeat('★', (int)round((float)$p['rating_avg'])) . str_repeat('☆', 5 - (int)round((float)$p['rating_avg'])) ?>
        <small>(<?= (int)$p['rating_count'] ?>)</small>
      </div>
    <?php endif; ?>
    <div class="pc-price">
      <?php $eff = effective_price($p); ?>
      <?php if ($p['sale_price'] && (float)$p['sale_price'] < (float)$p['price']): ?>
        <s><?= money((float)$p['price']) ?></s>
      <?php endif; ?>
      <strong><?= money($eff) ?></strong>
    </div>
    <button class="btn btn-sm add-quick" data-product="<?= (int)$p['id'] ?>"
            data-variable="<?= (int)$p['is_variable'] ?>" data-href="/product/<?= e($p['slug']) ?>"
            <?= $p['stock_status'] === 'out_of_stock' ? 'disabled' : '' ?>>
      <?= $p['is_variable'] ? 'Choose Options' : ($p['stock_status'] === 'out_of_stock' ? 'Unavailable' : 'Add to Cart') ?>
    </button>
  </div>
</article>
