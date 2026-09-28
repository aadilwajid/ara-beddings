<?php
declare(strict_types=1);

class Category
{
    public static function all(): array
    {
        return db()->query(
            'SELECT * FROM categories WHERE is_active=1 ORDER BY sort_order, name'
        )->fetchAll();
    }

    public static function bySlug(string $slug): ?array
    {
        $st = db()->prepare('SELECT * FROM categories WHERE slug=? AND is_active=1 LIMIT 1');
        $st->execute([$slug]);
        return $st->fetch() ?: null;
    }

    public static function byId(int $id): ?array
    {
        $st = db()->prepare('SELECT * FROM categories WHERE id=? LIMIT 1');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function create(array $d): int
    {
        $st = db()->prepare(
            'INSERT INTO categories (parent_id,name,slug,description,image,sort_order,is_active) VALUES (?,?,?,?,?,?,?)'
        );
        $st->execute([
            $d['parent_id'] ?: null, $d['name'], $d['slug'], $d['description'] ?: null,
            $d['image'] ?: null, (int)($d['sort_order'] ?? 0), (int)($d['is_active'] ?? 1),
        ]);
        return (int)db()->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        $st = db()->prepare(
            'UPDATE categories SET parent_id=?,name=?,slug=?,description=?,image=?,sort_order=?,is_active=?,
             meta_title=?,meta_description=? WHERE id=?'
        );
        $st->execute([
            $d['parent_id'] ?: null, $d['name'], $d['slug'], $d['description'] ?: null,
            $d['image'] ?: null, (int)($d['sort_order'] ?? 0), (int)($d['is_active'] ?? 1),
            $d['meta_title'] ?? null, $d['meta_description'] ?? null, $id,
        ]);
    }

    public static function delete(int $id): void
    {
        db()->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
    }

    public static function uniqueSlug(string $base, int $exceptId = 0): string
    {
        $slug = $base; $i = 2;
        while (true) {
            $st = db()->prepare('SELECT COUNT(*) FROM categories WHERE slug=? AND id<>?');
            $st->execute([$slug, $exceptId]);
            if (!$st->fetchColumn()) break;
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /** Count published products per category id. */
    public static function productCounts(): array
    {
        $rows = db()->query(
            "SELECT category_id, COUNT(*) c FROM products WHERE status='published' GROUP BY category_id"
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) $out[(int)$r['category_id']] = (int)$r['c'];
        return $out;
    }
}
