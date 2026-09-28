<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php require dirname(__DIR__, 2) . '/views/partials/header.php'; ?>

<div class="shop-layout">
  <aside class="shop-sidebar">
    <h2><?= e($category['name'] ?? (($_GET['q'] ?? '') !== '' ? 'Search results' : 'Shop')) ?></h2>
    <form method="get" class="filter-form">
      <?php if (!empty($category)): ?><input type="hidden" name="category" value="<?= e($category['slug']) ?>"><?php endif; ?>
      <?php if (!empty($_GET['q'])): ?><input type="hidden" name="q" value="<?= e($_GET['q']) ?>"><?php endif; ?>

      <label>Category
        <select name="category" onchange="this.form.submit()">
          <option value="">All categories</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= e($c['slug']) ?>" <?= (($category['id'] ?? null) === $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>Sort by
        <select name="sort" onchange="this.form.submit()">
          <?php $cur = $_GET['sort'] ?? 'new'; ?>
          <option value="new"        <?= $cur==='new'?'selected':'' ?>>Newest</option>
          <option value="best"       <?= $cur==='best'?'selected':'' ?>>Best sellers</option>
          <option value="price_asc"  <?= $cur==='price_asc'?'selected':'' ?>>Price: low to high</option>
          <option value="price_desc" <?= $cur==='price_desc'?'selected':'' ?>>Price: high to low</option>
          <option value="name"       <?= $cur==='name'?'selected':'' ?>>Name A–Z</option>
        </select>
      </label>

      <fieldset>
        <legend>Price (<?= e(setting('currency_symbol','Rs.')) ?>)</legend>
        <div class="price-row">
          <input type="number" name="min" min="0" placeholder="Min" value="<?= e($_GET['min'] ?? '') ?>">
          <input type="number" name="max" min="0" placeholder="Max" value="<?= e($_GET['max'] ?? '') ?>">
        </div>
        <button class="btn btn-sm btn-outline" type="submit">Apply</button>
      </fieldset>
    </form>
    <p class="result-count"><?= (int)$total ?> product<?= $total === 1 ? '' : 's' ?> found<?= !empty($_GET['q']) ? ' for “' . e($_GET['q']) . '”' : '' ?></p>
  </aside>

  <div class="shop-main">
    <?php if (!$items): ?>
      <div class="empty-state">
        <h3>No products found</h3>
        <p>Try a different search or browse the full <a href="/shop">shop</a>.</p>
      </div>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($items as $p) require dirname(__DIR__, 2) . '/components/product-card.php'; ?>
      </div>
      <?= paginate((int)$total, (int)$per, (int)$page,
            '/shop' . '?' . http_build_query(array_merge($_GET, ['page' => null]))) ?>
    <?php endif; ?>
  </div>
</div>

<?php require dirname(__DIR__, 2) . '/views/partials/footer.php'; ?>
