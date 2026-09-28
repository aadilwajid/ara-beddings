<?php if (!defined('APP_URL')) { http_response_code(403); exit; } ?>
<div class="toolbar">
  <a class="btn btn-primary" href="/admin/products/add">+ Add Product</a>
  <p class="result-count"><?= (int)$total ?> product<?= (int)$total === 1 ? '' : 's' ?></p>
</div>

<form method="post" action="/admin/products/bulk" id="bulkForm">
  <?= csrf_field() ?>
  <div class="bulk-bar">
    <select name="bulk_action" aria-label="Bulk action">
      <option value="">Choose bulk action…</option>
      <option value="feature">Feature selected</option>
      <option value="unfeature">Unfeature selected</option>
      <option value="publish">Publish selected</option>
      <option value="draft">Move to draft</option>
      <option value="delete">Delete selected</option>
    </select>
    <button class="btn btn-sm btn-outline" type="submit" formnovalidate onclick="return confirmBulk(this.form)">Apply</button>
  </div>

  <div class="table-wrap">
  <table class="data-table">
    <thead><tr>
      <th><input type="checkbox" id="checkAll" aria-label="Select all"></th>
      <th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th>
    </tr></thead>
    <tbody>
      <?php foreach ($items as $p): ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= (int)$p['id'] ?>"></td>
          <td>
            <a href="/admin/products/edit/<?= (int)$p['id'] ?>"><strong><?= e($p['name']) ?></strong></a>
            <?php if ($p['is_featured']): ?> ⭐<?php endif; ?>
            <?php if ($p['is_variable']): ?> <small>(variable)</small><?php endif; ?>
          </td>
          <td><?= e((string)($p['sku'] ?? '—')) ?></td>
          <td><?= e((string)($p['cat_name'] ?? '—')) ?></td>
          <td><?= money(effective_price($p)) ?><?php if ($p['sale_price']): ?><br><s><?= money((float)$p['price']) ?></s><?php endif; ?></td>
          <td>
            <?php if ($p['is_variable']): ?>—<?php else: ?>
              <span class="<?= (int)$p['stock_quantity'] <= (int)$p['low_stock_threshold'] ? 'low-text' : '' ?>"><?= (int)$p['stock_quantity'] ?></span>
            <?php endif; ?>
          </td>
          <td>
            <span class="status-pill <?= $p['status'] === 'published' ? 'status-confirmed' : 'status-pending' ?>"><?= e($p['status']) ?></span>
            <?php if ($p['stock_status'] === 'out_of_stock'): ?><span class="badge oos">OOS</span><?php endif; ?>
          </td>
          <td class="row-actions">
            <a class="btn btn-sm btn-outline" href="/admin/products/edit/<?= (int)$p['id'] ?>">Edit</a>
            <a class="btn btn-sm btn-link" target="_blank" rel="noopener" href="/product/<?= e($p['slug']) ?>">View</a>
            <button class="btn btn-sm btn-link danger" form="del<?= (int)$p['id'] ?>">Delete</button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?><tr><td colspan="8" class="empty-cell">No products yet — add your first one.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</form>

<?php foreach ($items as $p): ?>
  <form method="post" action="/admin/products/delete/<?= (int)$p['id'] ?>" id="del<?= (int)$p['id'] ?>" hidden><?= csrf_field() ?></form>
<?php endforeach; ?>

<?= paginate((int)$total, 20, (int)$page, '/admin/products?' . http_build_query(array_merge($_GET, ['page'=>null]))) ?>

<script>
function confirmBulk(f){ if(!f.querySelector('input[name="bulk_action"]').value){alert('Choose an action first.');return false;} return confirm('Apply this action to the selected products?'); }
document.getElementById('checkAll')?.addEventListener('change', function(){ document.querySelectorAll('input[name="ids[]"]').forEach(c=>c.checked=this.checked); });
</script>
