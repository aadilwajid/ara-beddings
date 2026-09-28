<?php
/**
 * Admin page controllers. Every action checks role server-side.
 * Roles: super_admin > admin > staff (staff = view + order ops, no settings/users).
 */

declare(strict_types=1);

function admin_guard(array $roles = ['super_admin','admin','staff']): array
{
    return require_role($roles);
}

function admin_view(string $tpl, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $me = current_user();
    require dirname(__DIR__) . '/views/admin/layout_top.php';
    require dirname(__DIR__) . '/views/admin/' . $tpl . '.php';
    require dirname(__DIR__) . '/views/admin/layout_bottom.php';
}

/* ---------- Dashboard ---------- */
function admin_dashboard(): void
{
    admin_guard();
    admin_view('dashboard', ['stats' => Order::stats(), 'title' => 'Dashboard']);
}

/* ---------- Products list ---------- */
function admin_products(): void
{
    admin_guard();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = max(0, ($page - 1) * 20);
    $st = db()->prepare(
        'SELECT p.*, c.name cat_name FROM products p
         LEFT JOIN categories c ON c.id=p.category_id
         ORDER BY p.id DESC LIMIT 20 OFFSET ' . $offset
    );
    $st->execute();
    $total = (int)db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
    admin_view('products', [
        'items' => $st->fetchAll(), 'total' => $total, 'page' => $page,
        'title' => 'Products',
    ]);
}

/* ---------- Product create/edit form ---------- */
function admin_product_form(?int $id): void
{
    admin_guard();
    $p = $id ? Product::find($id) : null;
    if ($id && !$p) { render_error_page(404, 'Product not found.'); return; }
    admin_view('product_form', [
        'p' => $p,
        'cats' => Category::all(),
        'attrs' => db()->query('SELECT a.*, (SELECT COUNT(*) FROM attribute_values av WHERE av.attribute_id=a.id) vc FROM attributes a ORDER BY a.sort_order')->fetchAll(),
        'variants' => $p && $p['is_variable'] ? Product::variants((int)$p['id']) : [],
        'images' => $p ? Product::images((int)$p['id']) : [],
        'title' => $p ? 'Edit: ' . $p['name'] : 'Add Product',
    ]);
}

/* ---------- Save product (POST) ---------- */
function admin_product_save(): void
{
    $me = admin_guard();
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 200);
    if ($name === '') { flash('Product name is required.', 'error'); redirect('/admin/products' . ($id ? "/edit/$id" : '/add')); }

    $price = (float)($_POST['price'] ?? 0);
    $sale = $_POST['sale_price'] !== '' ? (float)$_POST['sale_price'] : null;
    if ($price < 0 || ($sale !== null && ($sale < 0 || $sale >= $price))) {
        flash('Check pricing: sale price must be lower than regular price.', 'error');
        redirect('/admin/products' . ($id ? "/edit/$id" : '/add'));
    }

    $d = [
        'name' => $name,
        'slug' => Product::uniqueSlug(slugify((string)($_POST['slug'] ?: $name)), $id),
        'sku' => trim((string)($_POST['sku'] ?? '')),
        'short_description' => mb_substr(trim((string)($_POST['short_description'] ?? '')), 0, 500),
        'description' => trim((string)($_POST['description'] ?? '')),
        'specifications' => json_encode(specs_from_post($_POST['spec_labels'] ?? [], $_POST['spec_values'] ?? []), JSON_UNESCAPED_UNICODE),
        'price' => $price, 'sale_price' => $sale,
        'weight_kg' => trim((string)($_POST['weight_kg'] ?? '')),
        'length_cm' => trim((string)($_POST['length_cm'] ?? '')),
        'width_cm' => trim((string)($_POST['width_cm'] ?? '')),
        'height_cm' => trim((string)($_POST['height_cm'] ?? '')),
        'is_variable' => (int)!empty($_POST['is_variable']),
        'stock_quantity' => max(0, (int)($_POST['stock_quantity'] ?? 0)),
        'low_stock_threshold' => max(0, (int)($_POST['low_stock_threshold'] ?? setting('low_stock_default','5'))),
        'stock_status' => in_array($_POST['stock_status'] ?? '', ['in_stock','out_of_stock','on_backorder'], true) ? $_POST['stock_status'] : 'in_stock',
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'tags' => mb_substr(trim((string)($_POST['tags'] ?? '')), 0, 500),
        'is_featured' => (int)!empty($_POST['is_featured']),
        'status' => ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
        'meta_title' => mb_substr(trim((string)($_POST['meta_title'] ?? '')), 0, 220),
        'meta_description' => mb_substr(trim((string)($_POST['meta_description'] ?? '')), 0, 300),
    ];

    if ($id) {
        Product::update($id, $d);
        flash('Product updated.');
    } else {
        $id = Product::create($d);
        flash('Product created. Add variants/images now if needed.');
    }

    // Variant rows (sent as JSON string built by the admin UI)
    if (!empty($_POST['variants_json'])) {
        $rows = json_decode((string)$_POST['variants_json'], true);
        if (is_array($rows)) {
            foreach ($rows as $v) {
                if (empty($v['sku']) || !isset($v['price'])) continue;
                Product::saveVariant($id, [
                    'id' => $v['id'] ?? null,
                    'sku' => mb_substr(trim((string)$v['sku']), 0, 80),
                    'name' => $v['name'] ?? null,
                    'options' => is_array($v['options'] ?? null) ? $v['options'] : [],
                    'price' => (float)$v['price'],
                    'sale_price' => ($v['sale_price'] ?? '') !== '' ? (float)$v['sale_price'] : '',
                    'stock_quantity' => max(0, (int)($v['stock_quantity'] ?? 0)),
                    'low_stock_threshold' => max(0, (int)($v['low_stock_threshold'] ?? 5)),
                    'weight_kg' => ($v['weight_kg'] ?? '') !== '' ? (float)$v['weight_kg'] : '',
                    'image' => $v['image'] ?? '',
                    'is_active' => isset($v['is_active']) ? (int)$v['is_active'] : 1,
                ]);
            }
        }
    }
    if (!empty($_POST['variant_delete'])) {
        foreach ((array)$_POST['variant_delete'] as $vid) {
            Product::deleteVariant((int)$vid, $id);
        }
    }

    // Gallery images upload (skipped on Vercel — see README for object storage option)
    if (!empty($_FILES['images']['name'][0])) {
        handle_image_uploads($id, $_FILES['images']);
    }

    Product::recalcStockStatus($id);
    redirect('/admin/products/edit/' . $id);
}

function specs_from_post($labels, $values): array
{
    $out = [];
    foreach ((array)$labels as $i => $lab) {
        $val = (array)$values[$i] ?? '';
        if (trim((string)$lab) !== '' && trim((string)$val) !== '') {
            $out[] = ['label' => mb_substr(trim((string)$lab), 0, 80), 'value' => mb_substr(trim((string)$val), 0, 300)];
        }
    }
    return $out;
}

function handle_image_uploads(int $productId, array $files): void
{
    if (getenv('VERCEL') === '1') {
        flash('File uploads are disabled on serverless hosting — use image URLs instead (see README).', 'error');
        return;
    }
    $dir = dirname(__DIR__) . '/public/uploads';
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    $n = min(8, count($files['name']));
    for ($i = 0; $i < $n; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK || $files['size'][$i] > 4 * 1024 * 1024) continue;
        if (!in_array($files['type'][$i], $allowed, true)) continue;
        $info = getimagesize($files['tmp_name'][$i]);
        if (!$info) continue;
        $ext = image_type_to_extension($info[2], false);
        $name = 'p' . $productId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (move_uploaded_file($files['tmp_name'][$i], "$dir/$name")) {
            Product::addImage($productId, '/uploads/' . $name, $files['name'][$i]);
        }
    }
}

/* ---------- Delete / bulk actions ---------- */
function admin_product_delete(int $id): void
{
    $me = admin_guard(['super_admin','admin']);
    verify_csrf();
    Product::delete($id);
    flash('Product deleted.');
    redirect('/admin/products');
}

function admin_bulk(): void
{
    $me = admin_guard(['super_admin','admin']);
    verify_csrf();
    $action = (string)($_POST['bulk_action'] ?? '');
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    $ids = array_filter($ids);
    if (!$ids || !$action) { flash('Nothing selected.', 'error'); redirect('/admin/products'); }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    switch ($action) {
        case 'feature':   db()->prepare("UPDATE products SET is_featured=1 WHERE id IN ($ph)")->execute($ids); break;
        case 'unfeature': db()->prepare("UPDATE products SET is_featured=0 WHERE id IN ($ph)")->execute($ids); break;
        case 'publish':   db()->prepare("UPDATE products SET status='published' WHERE id IN ($ph)")->execute($ids); break;
        case 'draft':     db()->prepare("UPDATE products SET status='draft' WHERE id IN ($ph)")->execute($ids); break;
        case 'delete':
            if ($me['role'] !== 'super_admin' && $me['role'] !== 'admin') break;
            db()->prepare("DELETE FROM products WHERE id IN ($ph)")->execute($ids);
            break;
    }
    flash('Bulk action applied to ' . count($ids) . ' product(s).');
    redirect('/admin/products');
}

/* ---------- Inventory ---------- */
function admin_inventory(): void
{
    admin_guard();
    $low = db()->query(
        "(SELECT p.id, p.name, p.sku, p.stock_quantity, p.low_stock_threshold, NULL variant_id, 'simple' kind
         FROM products p WHERE p.is_variable=0 AND p.status='published' AND p.stock_quantity <= p.low_stock_threshold)
         UNION
         (SELECT p.id, CONCAT(p.name,' — ',v.name), v.sku, v.stock_quantity, v.low_stock_threshold, v.id, 'variant'
          FROM product_variants v JOIN products p ON p.id=v.product_id
          WHERE v.is_active=1 AND p.status='published' AND v.stock_quantity <= v.low_stock_threshold)
         ORDER BY 4 ASC LIMIT 200"
    )->fetchAll();
    $tx = db()->query(
        'SELECT t.*, p.name product_name, v.name variant_name
         FROM inventory_transactions t
         JOIN products p ON p.id=t.product_id
         LEFT JOIN product_variants v ON v.id=t.variant_id
         ORDER BY t.id DESC LIMIT 100'
    )->fetchAll();
    admin_view('inventory', ['low' => $low, 'tx' => $tx, 'title' => 'Inventory']);
}

function admin_stock_adjust(): void
{
    $me = admin_guard();
    verify_csrf();
    $productId = (int)($_POST['product_id'] ?? 0);
    $variantId = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
    $change = (int)($_POST['change'] ?? 0);
    $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 120);
    if (!$productId || $change === 0) { flash('Enter product and a non-zero change.', 'error'); redirect('/admin/inventory'); }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($variantId) {
            $st = $pdo->prepare('SELECT stock_quantity FROM product_variants WHERE id=? AND product_id=? FOR UPDATE');
            $st->execute([$variantId, $productId]);
            $cur = $st->fetchColumn();
            if ($cur === false) throw new RuntimeException('Variant not found');
            if ((int)$cur + $change < 0) throw new RuntimeException('Stock cannot go negative');
            $pdo->prepare('UPDATE product_variants SET stock_quantity = stock_quantity + ? WHERE id=?')->execute([$change, $variantId]);
        } else {
            $st = $pdo->prepare('SELECT stock_quantity FROM products WHERE id=? FOR UPDATE');
            $st->execute([$productId]);
            $cur = $st->fetchColumn();
            if ($cur === false) throw new RuntimeException('Product not found');
            if ((int)$cur + $change < 0) throw new RuntimeException('Stock cannot go negative');
            $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id=?')->execute([$change, $productId]);
        }
        $pdo->prepare('INSERT INTO inventory_transactions (product_id,variant_id,change_qty,reason,reference,actor) VALUES (?,?,?,?,?,?)')
            ->execute([$productId, $variantId, $change, 'adjustment', $note ?: 'manual', $me['email']]);
        $pdo->commit();
        Product::recalcStockStatus($productId);
        flash('Stock adjusted.');
    } catch (Throwable $ex) {
        $pdo->rollBack();
        flash($ex->getMessage(), 'error');
    }
    redirect('/admin/inventory');
}

/* ---------- Categories ---------- */
function admin_categories(): void
{
    admin_guard();
    admin_view('categories', [
        'cats' => db()->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) pc FROM categories c ORDER BY sort_order,name')->fetchAll(),
        'editing' => null, 'title' => 'Categories',
    ]);
}

function admin_category_form(?int $id): void
{
    admin_guard();
    $cat = $id ? Category::byId($id) : null;
    admin_view('categories', [
        'cats' => db()->query('SELECT * FROM categories ORDER BY sort_order,name')->fetchAll(),
        'editing' => $cat, 'title' => $cat ? 'Edit Category' : 'Add Category',
    ]);
}

function admin_category_save(): void
{
    admin_guard();
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 120);
    if ($name === '') { flash('Name required.', 'error'); redirect('/admin/categories'); }
    $d = [
        'parent_id' => (int)($_POST['parent_id'] ?? 0),
        'name' => $name,
        'slug' => Category::uniqueSlug(slugify((string)($_POST['slug'] ?: $name)), $id),
        'description' => trim((string)($_POST['description'] ?? '')),
        'image' => trim((string)($_POST['image'] ?? '')),
        'sort_order' => (int)($_POST['sort_order'] ?? 0),
        'is_active' => (int)!empty($_POST['is_active']),
        'meta_title' => trim((string)($_POST['meta_title'] ?? '')),
        'meta_description' => trim((string)($_POST['meta_description'] ?? '')),
    ];
    if ($id) Category::update($id, $d); else Category::create($d);
    flash('Category saved.');
    redirect('/admin/categories');
}

function admin_category_delete(int $id): void
{
    admin_guard(['super_admin','admin']);
    verify_csrf();
    Category::delete($id);
    flash('Category deleted (products keep existing but lose the category link).');
    redirect('/admin/categories');
}

/* ---------- Attributes ---------- */
function admin_attributes(): void
{
    admin_guard();
    $attrs = db()->query('SELECT * FROM attributes ORDER BY sort_order,id')->fetchAll();
    $vals = db()->query('SELECT * FROM attribute_values ORDER BY attribute_id, sort_order, value')->fetchAll();
    $byAttr = [];
    foreach ($vals as $v) $byAttr[(int)$v['attribute_id']][] = $v;
    admin_view('attributes', ['attrs' => $attrs, 'byAttr' => $byAttr, 'title' => 'Attributes']);
}

function admin_attribute_save(): void
{
    admin_guard();
    verify_csrf();
    $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 80);
    if ($name === '') { flash('Attribute name required.', 'error'); redirect('/admin/attributes'); }
    $slug = slugify($name);
    db()->prepare('INSERT INTO attributes (name,slug,type) VALUES (?,?,\'select\') ON DUPLICATE KEY UPDATE name=VALUES(name)')
        ->execute([$name, $slug]);
    flash('Attribute saved. Add values below.');
    redirect('/admin/attributes');
}

function admin_attribute_value_save(): void
{
    admin_guard();
    verify_csrf();
    $attrId = (int)($_POST['attribute_id'] ?? 0);
    $value = mb_substr(trim((string)($_POST['value'] ?? '')), 0, 120);
    if (!$attrId || $value === '') { flash('Pick an attribute and enter a value.', 'error'); redirect('/admin/attributes'); }
    db()->prepare('INSERT IGNORE INTO attribute_values (attribute_id,value) VALUES (?,?)')->execute([$attrId, $value]);
    flash('Value added.');
    redirect('/admin/attributes');
}

function admin_attribute_value_delete(int $id): void
{
    admin_guard();
    verify_csrf();
    db()->prepare('DELETE FROM attribute_values WHERE id=?')->execute([$id]);
    redirect('/admin/attributes');
}

/* ---------- Orders ---------- */
function admin_orders(): void
{
    admin_guard();
    $res = Order::adminList([
        'status' => in_array($_GET['status'] ?? '', Order::STATUSES, true) ? $_GET['status'] : '',
        'search' => trim((string)($_GET['q'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
    ]);
    admin_view('orders', ['res' => $res, 'statuses' => Order::STATUSES, 'title' => 'Orders']);
}

function admin_order_detail(int $id): void
{
    admin_guard();
    $o = Order::find($id);
    if (!$o) { render_error_page(404, 'Order not found.'); return; }
    $o['history'] = Order::history($id);
    admin_view('order_detail', ['order' => $o, 'statuses' => Order::STATUSES, 'title' => 'Order ' . $o['order_number']]);
}

function admin_order_update(int $id): void
{
    $me = admin_guard();
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $ok = true;
    if ($action === 'status') {
        $ok = Order::setStatus($id, (string)($_POST['status'] ?? ''), $_POST['note'] ?: null, $me['email']);
    } elseif ($action === 'payment') {
        $ok = Order::setPayment($id, (string)($_POST['payment_status'] ?? ''), $_POST['payment_reference'] ?: null, $me['email']);
    } elseif ($action === 'tracking') {
        Order::setTracking($id, trim((string)($_POST['tracking_number'] ?? '')) ?: null, trim((string)($_POST['courier'] ?? '')) ?: null);
    }
    flash($ok ? 'Order updated.' : 'Update failed.', $ok ? 'success' : 'error');
    redirect('/admin/orders/' . $id);
}

function admin_invoice(int $id): void
{
    admin_guard();
    $o = Order::find($id);
    if (!$o) { render_error_page(404, 'Order not found.'); return; }
    $me = current_user();
    require dirname(__DIR__) . '/views/admin/invoice.php';
    exit;
}

/* ---------- Customers ---------- */
function admin_customers(): void
{
    admin_guard(['super_admin','admin']);
    $res = User::listCustomers(max(1,(int)($_GET['page'] ?? 1)));
    admin_view('customers', ['res' => $res, 'page' => max(1,(int)($_GET['page'] ?? 1)), 'title' => 'Customers']);
}

function admin_customer_detail(int $id): void
{
    admin_guard(['super_admin','admin']);
    $st = db()->prepare('SELECT * FROM users WHERE id=? AND role=\'customer\'');
    $st->execute([$id]);
    $c = $st->fetch();
    if (!$c) { render_error_page(404, 'Customer not found.'); return; }
    admin_view('customer_detail', [
        'c' => $c,
        'orders' => Order::forUser($id),
        'addresses' => User::addresses($id),
        'title' => 'Customer: ' . $c['name'],
    ]);
}

/* ---------- Coupons ---------- */
function admin_coupons(): void
{
    admin_guard(['super_admin','admin']);
    admin_view('coupons', ['coupons' => Coupon::all(), 'editing' => null, 'title' => 'Coupons']);
}

function admin_coupon_form(?int $id): void
{
    admin_guard(['super_admin','admin']);
    admin_view('coupons', [
        'coupons' => Coupon::all(),
        'editing' => $id ? Coupon::find($id) : null,
        'cats' => Category::all(),
        'title' => $id ? 'Edit Coupon' : 'Add Coupon',
    ]);
}

function admin_coupon_save(): void
{
    admin_guard(['super_admin','admin']);
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    if (!preg_match('/^[A-Z0-9_-]{3,60}$/', $code)) { flash('Code must be 3–60 chars (A-Z, 0-9, _ or -).', 'error'); redirect('/admin/coupons'); }
    $val = (float)($_POST['discount_value'] ?? 0);
    if ($val <= 0) { flash('Discount value must be positive.', 'error'); redirect('/admin/coupons'); }
    $type = ($_POST['discount_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
    if ($type === 'percent' && $val > 100) { flash('Percent discount cannot exceed 100.', 'error'); redirect('/admin/coupons'); }
    Coupon::save([
        'code' => $code, 'description' => $_POST['description'] ?? '',
        'discount_type' => $type, 'discount_value' => $val,
        'min_order_amount' => $_POST['min_order_amount'] ?? 0,
        'max_discount' => trim((string)($_POST['max_discount'] ?? '')),
        'usage_limit' => trim((string)($_POST['usage_limit'] ?? '')),
        'per_user_limit' => trim((string)($_POST['per_user_limit'] ?? '')),
        'starts_at' => trim((string)($_POST['starts_at'] ?? '')),
        'expires_at' => trim((string)($_POST['expires_at'] ?? '')),
        'category_id' => (int)($_POST['category_id'] ?? 0),
        'product_id' => (int)($_POST['product_id'] ?? 0),
        'is_active' => (int)!empty($_POST['is_active']),
    ], $id ?: null);
    flash('Coupon saved.');
    redirect('/admin/coupons');
}

function admin_coupon_delete(int $id): void
{
    admin_guard(['super_admin','admin']);
    verify_csrf();
    Coupon::delete($id);
    flash('Coupon deleted.');
    redirect('/admin/coupons');
}

/* ---------- Reviews moderation ---------- */
function admin_reviews(): void
{
    admin_guard();
    admin_view('reviews', ['pending' => Review::pendingList(), 'title' => 'Reviews']);
}

function admin_review_action(int $id, string $what): void
{
    admin_guard();
    verify_csrf();
    if ($what === 'approve') Review::approve($id);
    elseif ($what === 'delete') Review::delete($id);
    flash('Done.');
    redirect('/admin/reviews');
}

/* ---------- Settings ---------- */
function admin_settings(): void
{
    admin_guard(['super_admin','admin']);
    admin_view('settings', ['title' => 'Settings']);
}

function admin_settings_save(): void
{
    $me = admin_guard(['super_admin','admin']);
    verify_csrf();
    $keys = [
        'store_name','store_description','logo_text','logo_image','favicon','contact_phone','whatsapp_number',
        'contact_email','store_address','facebook_url','instagram_url','tiktok_url','youtube_url','twitter_url',
        'shipping_mode','shipping_flat_cost','free_shipping_threshold','shipping_city_rates','shipping_province_rates',
        'tax_enabled','tax_rate','return_policy_days','return_policy','terms_conditions','privacy_policy',
        'bank_account_title','bank_account_name','bank_account_number','bank_iban','bank_name',
        'easypaisa_number','easypaisa_title','jazzcash_number','jazzcash_title',
        'hero_title','hero_subtitle','hero_image','hero_button_text','show_home_sections','products_per_page','low_stock_default',
    ];
    $toggles = ['cod_enabled','bank_transfer_enabled','easypaisa_enabled','jazzcash_enabled'];
    $pdo = db();
    foreach ($keys as $k) {
        if (array_key_exists($k, $_POST)) set_setting($pdo, $k, mb_substr(trim((string)$_POST[$k]), 0, 6000));
    }
    foreach ($toggles as $k) set_setting($pdo, $k, empty($_POST[$k]) ? '0' : '1');

    // Basic sanity for numeric fields
    if (isset($_POST['shipping_flat_cost']) && !is_numeric($_POST['shipping_flat_cost'])) set_setting($pdo, 'shipping_flat_cost', '0');
    if (isset($_POST['free_shipping_threshold']) && !is_numeric($_POST['free_shipping_threshold'])) set_setting($pdo, 'free_shipping_threshold', '0');
    if (isset($_POST['tax_rate']) && !is_numeric($_POST['tax_rate'])) set_setting($pdo, 'tax_rate', '0');

    flash('Settings saved.');
    redirect('/admin/settings');
}
