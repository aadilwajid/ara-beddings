<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<?php
$specs = $p ? (json_decode((string)($p['specifications'] ?? '[]'), true) ?: []) : [];
$attrIds = [];
if ($p) {
    $st = db()->prepare('SELECT attribute_id FROM product_attributes WHERE product_id=?');
    $st->execute([(int)$p['id']]);
    $attrIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}
?>
<form method="post" action="/admin/products/save" enctype="multipart/form-data" class="product-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>">

  <div class="form-cols">
    <div class="form-main">
      <section class="panel">
        <h2>Basic Information</h2>
        <div class="form-grid">
          <label class="full">Product Name *<input type="text" name="name" required maxlength="200" value="<?= e($p['name'] ?? '') ?>"></label>
          <label>Slug<input type="text" name="slug" maxlength="220" value="<?= e($p['slug'] ?? '') ?>" placeholder="auto from name"></label>
          <label>SKU<input type="text" name="sku" maxlength="80" value="<?= e($p['sku'] ?? '') ?>"></label>
          <label>Category
            <select name="category_id">
              <option value="0">— None —</option>
              <?php foreach ($cats as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= ((int)($p['category_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Tags<input type="text" name="tags" maxlength="500" value="<?= e($p['tags'] ?? '') ?>" placeholder="comma separated"></label>
          <label class="full">Short Description<textarea name="short_description" rows="2" maxlength="500"><?= e($p['short_description'] ?? '') ?></textarea></label>
          <label class="full">Full Description<textarea name="description" rows="10" maxlength="65000"><?= e($p['description'] ?? '') ?></textarea></label>
        </div>
      </section>

      <section class="panel">
        <h2>Pricing &amp; Inventory</h2>
        <div class="form-grid">
          <label>Price (<?= e(setting('currency_symbol','Rs.')) ?>) *<input type="number" name="price" step="0.01" min="0" required value="<?= e((string)($p['price'] ?? '')) ?>"></label>
          <label>Sale Price<input type="number" name="sale_price" step="0.01" min="0" value="<?= e((string)($p['sale_price'] ?? '')) ?>" placeholder="empty = no sale"></label>
          <label><span class="lbl-row">Simple product stock
            <label class="check inline"><input type="checkbox" name="is_variable" id="isVariable" value="1" <?= (int)($p['is_variable'] ?? 0) === 1 ? 'checked' : '' ?>> Variable (has options)</label>
          </span><input type="number" name="stock_quantity" min="0" value="<?= (int)($p['stock_quantity'] ?? 0) ?>"></label>
          <label>Low Stock Alert At<input type="number" name="low_stock_threshold" min="0" value="<?= (int)($p['low_stock_threshold'] ?? setting('low_stock_default','5')) ?>"></label>
          <label>Stock Status Override
            <select name="stock_status">
              <?php foreach (['in_stock'=>'In stock','out_of_stock'=>'Out of stock','on_backorder'=>'On backorder'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= ($p['stock_status'] ?? 'in_stock') === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Weight (kg)<input type="number" name="weight_kg" step="0.001" min="0" value="<?= e((string)($p['weight_kg'] ?? '')) ?>"></label>
          <label>Length (cm)<input type="number" name="length_cm" step="0.1" min="0" value="<?= e((string)($p['length_cm'] ?? '')) ?>"></label>
          <label>Width (cm)<input type="number" name="width_cm" step="0.1" min="0" value="<?= e((string)($p['width_cm'] ?? '')) ?>"></label>
          <label>Height (cm)<input type="number" name="height_cm" step="0.1" min="0" value="<?= e((string)($p['height_cm'] ?? '')) ?>"></label>
        </div>
      </section>

      <section class="panel" id="variantsPanel" <?= (int)($p['is_variable'] ?? 0) === 1 ? '' : 'hidden' ?>>
        <h2>Variants</h2>
        <p class="help-note">Tick the attributes this product varies by, then build variant rows below. Prices/stock are per variant.</p>
        <div class="attr-ticks">
          <?php foreach ($attrs as $a): ?>
            <label class="check inline">
              <input type="checkbox" class="attr-tick" data-attr-id="<?= (int)$a['id'] ?>" data-attr-name="<?= e($a['name']) ?>" value="<?= (int)$a['id'] ?>"
                     <?= in_array((int)$a['id'], $attrIds, true) ? 'checked' : '' ?>> <?= e($a['name']) ?>
              <small>(<?= (int)$a['vc'] ?> values)</small>
            </label>
          <?php endforeach; ?>
        </div>
        <div id="variantBuilder"></div>
        <table class="data-table" id="variantTable">
          <thead><tr><th>Options</th><th>Name</th><th>SKU *</th><th>Price *</th><th>Sale</th><th>Stock</th><th>Low&nbsp;at</th><th>Weight</th><th>Image URL</th><th>Active</th><th></th></tr></thead>
          <tbody id="variantRows"></tbody>
        </table>
        <button type="button" class="btn btn-sm btn-outline" id="addVariantRow">+ Add Variant Row</button>
        <textarea name="variants_json" id="variantsJson" hidden></textarea>
        <div id="variantDeletes"></div>
        <script id="existingVariants" type="application/json"><?= json_encode(array_map(fn($v)=>[
            'id'=>(int)$v['id'],'sku'=>$v['sku'],'name'=>$v['name'],
            'options'=>json_decode((string)$v['option_json'],true) ?: [],
            'price'=>(float)$v['price'],'sale_price'=>$v['sale_price']!==null?(float)$v['sale_price']:'',
            'stock_quantity'=>(int)$v['stock_quantity'],'low_stock_threshold'=>(int)$v['low_stock_threshold'],
            'weight_kg'=>$v['weight_kg']!==null?(float)$v['weight_kg']:'','image'=>$v['image'] ?? '',
            'is_active'=>(int)$v['is_active']], $variants), JSON_UNESCAPED_UNICODE) ?></script>
      </section>

      <section class="panel">
        <h2>Specifications</h2>
        <div id="specRows">
          <?php foreach ($specs as $s): ?>
            <div class="spec-row">
              <input type="text" name="spec_labels[]" placeholder="Label" value="<?= e($s['label']) ?>">
              <input type="text" name="spec_values[]" placeholder="Value" value="<?= e($s['value'] ?? '') ?>">
              <button type="button" class="btn btn-sm btn-link danger spec-del">✕</button>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline" id="addSpecRow">+ Add Spec</button>
      </section>
    </div>

    <div class="form-side">
      <section class="panel">
        <h2>Publish</h2>
        <label>Status
          <select name="status">
            <option value="published" <?= ($p['status'] ?? 'published')==='published'?'selected':'' ?>>Published</option>
            <option value="draft" <?= ($p['status'] ?? '')==='draft'?'selected':'' ?>>Draft</option>
          </select>
        </label>
        <label class="check"><input type="checkbox" name="is_featured" value="1" <?= (int)($p['is_featured'] ?? 0) === 1 ? 'checked' : '' ?>> Featured on homepage</label>
        <button class="btn btn-primary btn-block" type="submit"><?= $p ? 'Update Product' : 'Create Product' ?></button>
        <?php if ($p): ?><a class="btn btn-link" target="_blank" rel="noopener" href="/product/<?= e($p['slug']) ?>">Preview on store →</a><?php endif; ?>
      </section>

      <section class="panel">
        <h2>Images</h2>
        <?php if ($images): ?>
          <div class="admin-gallery">
            <?php foreach ($images as $i): ?>
              <figure>
                <img src="<?= e($i['image']) ?>" alt="<?= e($i['alt_text']) ?>" loading="lazy">
                <figcaption><a href="<?= e($i['image']) ?>" target="_blank" rel="noopener">view</a></figcaption>
              </figure>
            <?php endforeach; ?>
          </div>
        <?php else: ?><p>No images yet.</p><?php endif; ?>
        <label>Upload (up to 8)<input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple></label>
        <p class="help-note">Or paste an image URL:<br>
          <input type="url" name="image_url_add" placeholder="https://…" formnovalidate></p>
      </section>

      <section class="panel">
        <h2>SEO</h2>
        <label>Meta Title<input type="text" name="meta_title" maxlength="220" value="<?= e($p['meta_title'] ?? '') ?>"></label>
        <label>Meta Description<textarea name="meta_description" rows="3" maxlength="300"><?= e($p['meta_description'] ?? '') ?></textarea></label>
      </section>
    </div>
  </div>
</form>

<script>
window.ADMIN_ATTR_VALUES = <?= json_encode(array_combine(array_column($attrs,'name'), array_map(function($a){ $s=db()->prepare('SELECT value FROM attribute_values WHERE attribute_id=? ORDER BY sort_order,value'); $s->execute([(int)$a['id']]); return $s->fetchAll(PDO::FETCH_COLUMN); }, $attrs)), JSON_UNESCAPED_UNICODE) ?>;
</script>
