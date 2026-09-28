<?php
declare(strict_types=1);

class Review
{
    public static function forProduct(int $productId, int $limit = 20): array
    {
        $st = db()->prepare(
            'SELECT * FROM reviews WHERE product_id=? AND is_approved=1 ORDER BY id DESC LIMIT ?'
        );
        $st->execute([$productId, $limit]);
        return $st->fetchAll();
    }

    public static function pendingList(): array
    {
        return db()->query(
            'SELECT r.*, p.name AS product_name, p.slug FROM reviews r
             JOIN products p ON p.id=r.product_id WHERE r.is_approved=0 ORDER BY r.id DESC LIMIT 200'
        )->fetchAll();
    }

    /** Customer submits a review (pending approval). */
    public static function create(int $productId, ?int $userId, string $author, int $rating, ?string $title, ?string $body): array
    {
        $p = Product::find($productId);
        if (!$p || $p['status'] !== 'published') return [false, 'Product not found.'];
        $rating = max(1, min(5, $rating));
        $author = mb_substr(trim($author), 0, 120);
        if ($author === '') return [false, 'Please enter your name.'];
        if ($body !== null) $body = mb_substr(trim($body), 0, 4000);
        db()->prepare(
            'INSERT INTO reviews (product_id,user_id,author_name,rating,title,body,is_approved) VALUES (?,?,?,?,?,?,0)'
        )->execute([$productId, $userId, $author, $rating, $title ? mb_substr(trim($title),0,160) : null, $body ?: null]);
        return [true, 'Thank you! Your review will appear after approval.'];
    }

    public static function approve(int $id): void
    {
        $st = db()->prepare('SELECT product_id FROM reviews WHERE id=?');
        $st->execute([$id]);
        $pid = (int)($st->fetchColumn() ?: 0);
        if (!$pid) return;
        db()->prepare('UPDATE reviews SET is_approved=1 WHERE id=?')->execute([$id]);
        Product::refreshRating($pid);
    }

    public static function delete(int $id): void
    {
        $st = db()->prepare('SELECT product_id FROM reviews WHERE id=?');
        $st->execute([$id]);
        $pid = (int)($st->fetchColumn() ?: 0);
        db()->prepare('DELETE FROM reviews WHERE id=?')->execute([$id]);
        if ($pid) Product::refreshRating($pid);
    }

    /** Latest approved reviews across the store (homepage testimonials). */
    public static function latestFeatured(int $limit = 6): array
    {
        $st = db()->prepare(
            'SELECT r.*, p.name AS product_name, p.slug FROM reviews r
             JOIN products p ON p.id=r.product_id
             WHERE r.is_approved=1 AND r.body IS NOT NULL AND r.rating>=4
             ORDER BY r.id DESC LIMIT ?'
        );
        $st->execute([$limit]);
        return $st->fetchAll();
    }
}
