<?php
/** Product + variant data access. All queries prepared. */

declare(strict_types=1);

class Product
{
    public static function find(int $id): ?array
    {
        $st = db()->prepare('SELECT * FROM products WHERE id=? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $st = db()->prepare("SELECT * FROM products WHERE slug=? AND status='published' LIMIT 1");
        $st->execute([$slug]);
        return $st->fetch() ?: null;
    }

    /** Paginated catalog with filters: category, search, sort. */
    public static function browse(array $f): array
    {
        $where = ["p.status='published'"];
        $params = [];

        if (!empty($f['category_id'])) {
            $where[] = '(p.category_id = ? OR EXISTS (
                SELECT 1 FROM categories c WHERE c.id=p.category_id AND c.parent_id=?))';
            $params[] = $params[] = (int)$f['category_id'];
        }
        if (!empty($f['search'])) {
            // LIKE-based search works on MySQL & MariaDB alike and is index-friendly for prefixes
            $like = '%' . str_replace(['%','_'], ['\%','\_'], $f['search']) . '%';
            $where[] = '(p.name LIKE ? OR p.short_description LIKE ? OR p.tags LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if (!empty($f['featured'])) { $where[] = 'p.is_featured=1'; }
        if (!empty($f['max_price'])) { $where[] = 'LEAST(p.price, COALESCE(NULLIF(p.sale_price,0), p.price)) <= ?'; $params[]=(float)$f['max_price']; }
        if (!empty($f['min_price'])) { $where[] = 'LEAST(p.price, COALESCE(NULLIF(p.sale_price,0), p.price)) >= ?'; $params[]=(float)$f['min_price']; }

        $sortMap = [
            'new'      => 'p.created_at DESC',
            'price_asc'=> 'eff_price ASC',
            'price_desc'=>'eff_price DESC',
            'best'     => 'p.sold_count DESC, p.rating_avg DESC',
            'name'     => 'p.name ASC',
        ];
        $order = $sortMap[$f['sort'] ?? 'new'] ?? $sortMap['new'];

        $whereSql = implode(' AND ', $where);
        $limit  = max(1, min(60, (int)($f['per_page'] ?? 12)));
        $offset = max(0, ((int)($f['page'] ?? 1) - 1) * $limit);

        $pdo = db();
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $st = $pdo->prepare(
            "SELECT p.*, LEAST(p.price, COALESCE(NULLIF(p.sale_price,0), p.price)) AS eff_price
             FROM products p WHERE $whereSql ORDER BY $order LIMIT $limit OFFSET $offset"
        );
        $st->execute($params);
        $items = $st->fetchAll();

        self::attachThumbnails($items);
        return ['items' => $items, 'total' => $total];
    }

    private static function attachThumbnails(array &$items): void
    {
        if (!$items) return;
        $ids = array_column($items, 'id');
        [$in, $ph] = self::inClause($ids);
        $st = db()->prepare(
            "SELECT product_id, MIN(id) AS mid FROM product_images WHERE product_id IN ($in) GROUP BY product_id"
        );
        $st->execute($ids);
        $imgIds = [];
        foreach ($st as $r) { $imgIds[(int)$r['product_id']] = (int)$r['mid']; }
        $paths = [];
        if ($imgIds) {
            [, $ph2] = self::inClause(array_values($imgIds));
            $s2 = db()->prepare("SELECT id, image, alt_text FROM product_images WHERE id IN ($ph2)");
            $s2->execute(array_values($imgIds));
            foreach ($s2 as $r) $paths[(int)$r['id']] = $r;
        }
        foreach ($items as &$it) {
            $pid = (int)$it['id'];
            $it['thumb'] = $imgIds[$pid] ?? null ? $paths[$imgIds[$pid]]['image'] : null;
            $it['thumb_alt'] = $imgIds[$pid] ?? null ? $paths[$imgIds[$pid]]['alt_text'] : $it['name'];
        }
    }

    private static function inClause(array $vals): array
    {
        return [implode(',', array_fill(0, count($vals), '?')), ''];
    }

    public static function images(int $productId): array
    {
        $st = db()->prepare('SELECT * FROM product_images WHERE product_id=? ORDER BY sort_order,id');
        $st->execute([$productId]);
        return $st->fetchAll();
    }

    public static function variants(int $productId): array
    {
        $st = db()->prepare('SELECT * FROM product_variants WHERE product_id=? AND is_active=1 ORDER BY id');
        $st->execute([$productId]);
        $rows = $st->fetchAll();
        foreach ($rows as &$v) {
            $v['options'] = json_decode($v['option_json'], true) ?: [];
        }
        return $rows;
    }

    /** Attribute options present across active variants (for selectors). */
    public static function variantOptions(int $productId): array
    {
        $opts = [];
        foreach (self::variants($productId) as $v) {
            foreach ($v['options'] as $k => $val) {
                $opts[$k][$val] = true;
            }
        }
        foreach ($opts as $k => $set) { $opts[$k] = array_keys($set); }
        return $opts;
    }

    public static function related(int $productId, ?int $categoryId, int $limit = 4): array
    {
        if (!$categoryId) return [];
        $st = db()->prepare(
            "SELECT p.* FROM products p
             WHERE p.category_id=? AND p.id<>? AND p.status='published'
             ORDER BY p.is_featured DESC, p.sold_count DESC LIMIT ?"
        );
        $st->execute([$categoryId, $productId, $limit]);
        $rows = $st->fetchAll();
        self::attachThumbnails($rows);
        return $rows;
    }

    public static function byIds(array $ids): array
    {
        if (!$ids) return [];
        [, ] = self::inClause($ids);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = db()->prepare("SELECT p.* FROM products p WHERE p.id IN ($ph) AND p.status='published'");
        $st->execute(array_values($ids));
        $rows = $st->fetchAll();
        self::attachThumbnails($rows);
        return $rows;
    }

    /* ---------------- Admin writes ---------------- */

    public static function create(array $d): int
    {
        $st = db()->prepare(
            'INSERT INTO products (name,slug,sku,short_description,description,specifications,price,sale_price,
              weight_kg,length_cm,width_cm,height_cm,is_variable,stock_quantity,low_stock_threshold,stock_status,
              category_id,tags,is_featured,status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $d['name'], $d['slug'], $d['sku'] ?: null, $d['short_description'] ?: null,
            $d['description'] ?: null, $d['specifications'] ?: null,
            $d['price'], ($d['sale_price'] !== '' && $d['sale_price'] !== null) ? $d['sale_price'] : null,
            $d['weight_kg'] !== '' ? $d['weight_kg'] : null,
            $d['length_cm'] !== '' ? $d['length_cm'] : null,
            $d['width_cm']  !== '' ? $d['width_cm']  : null,
            $d['height_cm'] !== '' ? $d['height_cm'] : null,
            (int)$d['is_variable'], (int)$d['stock_quantity'], (int)$d['low_stock_threshold'],
            $d['stock_status'], $d['category_id'] ?: null, $d['tags'] ?: null,
            (int)$d['is_featured'], $d['status'],
        ]);
        return (int)db()->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        $st = db()->prepare(
            'UPDATE products SET name=?,slug=?,sku=?,short_description=?,description=?,specifications=?,price=?,sale_price=?,
              weight_kg=?,length_cm=?,width_cm=?,height_cm=?,is_variable=?,stock_quantity=?,low_stock_threshold=?,stock_status=?,
              category_id=?,tags=?,is_featured=?,status=?,meta_title=?,meta_description=? WHERE id=?'
        );
        $st->execute([
            $d['name'], $d['slug'], $d['sku'] ?: null, $d['short_description'] ?: null,
            $d['description'] ?: null, $d['specifications'] ?: null,
            $d['price'], ($d['sale_price'] !== '' && $d['sale_price'] !== null) ? $d['sale_price'] : null,
            $d['weight_kg'] !== '' ? $d['weight_kg'] : null,
            $d['length_cm'] !== '' ? $d['length_cm'] : null,
            $d['width_cm']  !== '' ? $d['width_cm']  : null,
            $d['height_cm'] !== '' ? $d['height_cm'] : null,
            (int)$d['is_variable'], (int)$d['stock_quantity'], (int)$d['low_stock_threshold'],
            $d['stock_status'], $d['category_id'] ?: null, $d['tags'] ?: null,
            (int)$d['is_featured'], $d['status'],
            $d['meta_title'] ?: null, $d['meta_description'] ?: null, $id,
        ]);
    }

    public static function delete(int $id): void
    {
        db()->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
    }

    public static function slugExists(string $slug, int $exceptId = 0): bool
    {
        $st = db()->prepare('SELECT COUNT(*) FROM products WHERE slug=? AND id<>?');
        $st->execute([$slug, $exceptId]);
        return (bool)$st->fetchColumn();
    }

    public static function uniqueSlug(string $base, int $exceptId = 0): string
    {
        $slug = $base; $i = 2;
        while (self::slugExists($slug, $exceptId)) { $slug = $base . '-' . $i++; }
        return $slug;
    }

    public static function saveVariant(int $productId, array $v): void
    {
        $opts = json_encode($v['options'], JSON_UNESCAPED_UNICODE);
        if (!empty($v['id'])) {
            $st = db()->prepare(
                'UPDATE product_variants SET sku=?,name=?,option_json=?,price=?,sale_price=?,stock_quantity=?,
                 low_stock_threshold=?,weight_kg=?,image=?,is_active=? WHERE id=? AND product_id=?'
            );
            $st->execute([$v['sku'], $v['name'] ?: null, $opts, $v['price'],
                $v['sale_price'] !== '' ? $v['sale_price'] : null, (int)$v['stock_quantity'],
                (int)$v['low_stock_threshold'], $v['weight_kg'] !== '' ? $v['weight_kg'] : null,
                $v['image'] ?: null, (int)$v['is_active'], (int)$v['id'], $productId]);
        } else {
            $st = db()->prepare(
                'INSERT INTO product_variants (product_id,sku,name,option_json,price,sale_price,stock_quantity,
                 low_stock_threshold,weight_kg,image,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $st->execute([$productId, $v['sku'], $v['name'] ?: null, $opts, $v['price'],
                $v['sale_price'] !== '' ? $v['sale_price'] : null, (int)$v['stock_quantity'],
                (int)$v['low_stock_threshold'], $v['weight_kg'] !== '' ? $v['weight_kg'] : null,
                $v['image'] ?: null, (int)$v['is_active']]);
        }
    }

    public static function deleteVariant(int $vid, int $productId): void
    {
        db()->prepare('DELETE FROM product_variants WHERE id=? AND product_id=?')->execute([$vid, $productId]);
    }

    public static function addImage(int $productId, string $path, ?string $alt): void
    {
        db()->prepare('INSERT INTO product_images (product_id,image,alt_text) VALUES (?,?,?)')
          ->execute([$productId, $path, $alt]);
    }

    public static function deleteImage(int $imgId, int $productId): void
    {
        db()->prepare('DELETE FROM product_images WHERE id=? AND product_id=?')->execute([$imgId, $productId]);
    }

    public static function recalcStockStatus(int $productId): void
    {
        $pdo = db();
        $st = $pdo->prepare('SELECT is_variable, stock_quantity, low_stock_threshold FROM products WHERE id=?');
        $st->execute([$productId]);
        $p = $st->fetch();
        if (!$p) return;
        if ($p['is_variable']) {
            $v = $pdo->prepare('SELECT COALESCE(SUM(stock_quantity),0) s FROM product_variants WHERE product_id=? AND is_active=1');
            $v->execute([$productId]);
            $total = (int)$v->fetchColumn();
            $status = $total > 0 ? 'in_stock' : 'out_of_stock';
            $pdo->prepare('UPDATE products SET stock_quantity=?, stock_status=? WHERE id=?')
                ->execute([$total, $status, $productId]);
        } else {
            $status = ((int)$p['stock_quantity'] > 0) ? 'in_stock' : 'out_of_stock';
            $pdo->prepare('UPDATE products SET stock_status=? WHERE id=?')->execute([$status, $productId]);
        }
    }

    /** Recompute rating aggregates after review approval. */
    public static function refreshRating(int $productId): void
    {
        $st = db()->prepare(
            'SELECT AVG(rating) a, COUNT(*) c FROM reviews WHERE product_id=? AND is_approved=1'
        );
        $st->execute([$productId]);
        $r = $st->fetch();
        db()->prepare('UPDATE products SET rating_avg=COALESCE(?,0), rating_count=? WHERE id=?')
            ->execute([$r['a'], (int)$r['c'], $productId]);
    }
}
