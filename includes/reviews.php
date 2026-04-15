<?php

declare(strict_types=1);

function reviews_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $columns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM reviews') ?: [] as $column) {
            $columns[(string)$column['Field']] = true;
        }
        if (!isset($columns['is_published'])) {
            $pdo->exec('ALTER TABLE reviews ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 1');
        }
        try {
            $pdo->exec('ALTER TABLE reviews ADD UNIQUE KEY uniq_product_user (product_id, user_id)');
        } catch (Throwable $e) {
        }
    } catch (Throwable $e) {
        error_log('Review schema ensure error: ' . $e->getMessage());
    }
}

function reviews_can_user_submit(PDO $pdo, int $productId, int $userId): bool
{
    reviews_ensure_schema($pdo);

    try {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = ?
               AND oi.product_id = ?
               AND o.status_id = 4
             LIMIT 1'
        );
        $stmt->execute([$userId, $productId]);
        if (!$stmt->fetchColumn()) {
            return false;
        }

        $stmt = $pdo->prepare('SELECT 1 FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$productId, $userId]);
        return !$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Review eligibility error: ' . $e->getMessage());
        return false;
    }
}

function reviews_fetch_product(PDO $pdo, int $productId): array
{
    reviews_ensure_schema($pdo);

    try {
        $stmt = $pdo->prepare(
            'SELECT r.id, r.rating, r.comment, r.created_at, u.name AS user_name
             FROM reviews r
             INNER JOIN users u ON u.id = r.user_id
             WHERE r.product_id = ?
               AND r.is_published = 1
             ORDER BY r.created_at DESC, r.id DESC'
        );
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll() ?: [];

        return array_map(static function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'user_name' => (string)$row['user_name'],
                'rating' => (int)$row['rating'],
                'comment' => (string)($row['comment'] ?? ''),
                'created_at' => (string)$row['created_at'],
                'created_at_formatted' => date('d.m.Y', strtotime((string)$row['created_at'])),
            ];
        }, $rows);
    } catch (Throwable $e) {
        error_log('Review fetch error: ' . $e->getMessage());
        return [];
    }
}

function reviews_create(PDO $pdo, int $productId, int $userId, int $rating, string $comment): array
{
    reviews_ensure_schema($pdo);

    $stmt = $pdo->prepare(
        'INSERT INTO reviews (product_id, user_id, rating, comment, is_published)
         VALUES (?, ?, ?, ?, 1)'
    );
    $stmt->execute([$productId, $userId, $rating, $comment]);

    $reviewId = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare(
        'SELECT r.id, r.rating, r.comment, r.created_at, u.name AS user_name
         FROM reviews r
         INNER JOIN users u ON u.id = r.user_id
         WHERE r.id = ?
         LIMIT 1'
    );
    $stmt->execute([$reviewId]);
    $row = $stmt->fetch();

    return [
        'id' => (int)$row['id'],
        'user_name' => (string)$row['user_name'],
        'rating' => (int)$row['rating'],
        'comment' => (string)($row['comment'] ?? ''),
        'created_at' => (string)$row['created_at'],
        'created_at_formatted' => date('d.m.Y', strtotime((string)$row['created_at'])),
    ];
}
