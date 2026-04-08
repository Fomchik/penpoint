<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function admin_promotion_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $columns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM promotions') ?: [] as $column) {
            $columns[(string)$column['Field']] = true;
        }

        if (!isset($columns['promotion_type'])) {
            $pdo->exec("ALTER TABLE promotions ADD COLUMN promotion_type ENUM('regular','seasonal') NOT NULL DEFAULT 'regular' AFTER short_text");
        }
        if (!isset($columns['image_main'])) {
            $pdo->exec("ALTER TABLE promotions ADD COLUMN image_main VARCHAR(255) NULL DEFAULT NULL AFTER image_path");
        }
        if (!isset($columns['image_list'])) {
            $pdo->exec("ALTER TABLE promotions ADD COLUMN image_list VARCHAR(255) NULL DEFAULT NULL AFTER image_main");
        }
    } catch (Throwable $e) {
        admin_log_error('promotion_schema', $e);
    }
}

function admin_promotion_status_labels(): array
{
    return [
        'draft' => 'Черновик',
        'active' => 'Активна',
        'finished' => 'Завершена',
    ];
}

function admin_promotion_scope_labels(): array
{
    return [
        'categories' => 'Категории',
        'products' => 'Товары',
    ];
}

function admin_promotion_type_labels(): array
{
    return [
        'regular' => 'Regular',
        'seasonal' => 'Seasonal',
    ];
}

function admin_promotion_parse_ids($values): array
{
    if (!is_array($values)) {
        return [];
    }

    $result = [];
    foreach ($values as $value) {
        if (is_numeric($value) && (int)$value > 0) {
            $result[(int)$value] = (int)$value;
        }
    }

    return array_values($result);
}

function admin_promotion_fetch_categories(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
    return $stmt->fetchAll() ?: [];
}

function admin_promotion_fetch_products(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name FROM products ORDER BY name ASC');
    return $stmt->fetchAll() ?: [];
}

function admin_promotion_fetch_one(PDO $pdo, int $promotionId): ?array
{
    admin_promotion_ensure_schema($pdo);

    $stmt = $pdo->prepare(
        'SELECT id, title, short_text, image_path, image_main, image_list, promotion_type, date_start, date_end, discount_percent, apply_scope, status
         FROM promotions
         WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$promotionId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function admin_promotion_fetch_links(PDO $pdo, int $promotionId, string $scope): array
{
    if ($scope === 'products') {
        $stmt = $pdo->prepare('SELECT product_id FROM promotion_products WHERE promotion_id = ?');
    } else {
        $stmt = $pdo->prepare('SELECT category_id FROM promotion_categories WHERE promotion_id = ?');
    }
    $stmt->execute([$promotionId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function admin_promotion_sync_links(PDO $pdo, int $promotionId, string $scope, array $selectedIds): void
{
    $pdo->prepare('DELETE FROM promotion_products WHERE promotion_id = ?')->execute([$promotionId]);
    $pdo->prepare('DELETE FROM promotion_categories WHERE promotion_id = ?')->execute([$promotionId]);

    if ($scope === 'products') {
        if ($selectedIds === []) {
            return;
        }
        $stmt = $pdo->prepare('INSERT INTO promotion_products (promotion_id, product_id) VALUES (?, ?)');
        foreach ($selectedIds as $productId) {
            $stmt->execute([$promotionId, (int)$productId]);
        }
        return;
    }

    if ($selectedIds === []) {
        return;
    }
    $stmt = $pdo->prepare('INSERT INTO promotion_categories (promotion_id, category_id) VALUES (?, ?)');
    foreach ($selectedIds as $categoryId) {
        $stmt->execute([$promotionId, (int)$categoryId]);
    }
}

function admin_promotion_force_status(PDO $pdo, int $promotionId, string $targetStatus): void
{
    $promotion = admin_promotion_fetch_one($pdo, $promotionId);
    if (!$promotion) {
        throw new RuntimeException('Акция не найдена.');
    }

    $today = new DateTimeImmutable('today');
    $dateStart = new DateTimeImmutable((string)$promotion['date_start']);
    $dateEnd = !empty($promotion['date_end']) ? new DateTimeImmutable((string)$promotion['date_end']) : null;

    if ($targetStatus === 'draft') {
        $newStart = $today->modify('+1 day');
        $newEnd = $dateEnd !== null && $dateEnd < $newStart ? $newStart : $dateEnd;
    } elseif ($targetStatus === 'active') {
        $newStart = $dateStart > $today ? $today : $dateStart;
        $newEnd = $dateEnd !== null && $dateEnd < $today ? $today : $dateEnd;
    } elseif ($targetStatus === 'finished') {
        $newEnd = $today->modify('-1 day');
        $newStart = $dateStart > $newEnd ? $newEnd : $dateStart;
    } else {
        throw new RuntimeException('Недопустимый статус.');
    }

    $stmt = $pdo->prepare('UPDATE promotions SET date_start = ?, date_end = ? WHERE id = ? LIMIT 1');
    $stmt->execute([
        $newStart->format('Y-m-d'),
        $newEnd ? $newEnd->format('Y-m-d') : null,
        $promotionId,
    ]);
}
