<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

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

function admin_promotion_parse_ids($values): array
{
    if (!is_array($values)) {
        return [];
    }

    $result = [];
    foreach ($values as $value) {
        if (is_numeric($value)) {
            $id = (int)$value;
            if ($id > 0) {
                $result[$id] = $id;
            }
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
    $stmt = $pdo->prepare(
        'SELECT id, title, short_text, image_path, date_start, date_end, discount_percent, apply_scope, status
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
    $stmtDeleteProducts = $pdo->prepare('DELETE FROM promotion_products WHERE promotion_id = ?');
    $stmtDeleteCategories = $pdo->prepare('DELETE FROM promotion_categories WHERE promotion_id = ?');
    $stmtDeleteProducts->execute([$promotionId]);
    $stmtDeleteCategories->execute([$promotionId]);

    if ($scope === 'products') {
        if (!$selectedIds) {
            return;
        }
        $stmtInsert = $pdo->prepare('INSERT INTO promotion_products (promotion_id, product_id) VALUES (?, ?)');
        foreach ($selectedIds as $productId) {
            $stmtInsert->execute([$promotionId, (int)$productId]);
        }
        return;
    }

    if (!$selectedIds) {
        return;
    }
    $stmtInsert = $pdo->prepare('INSERT INTO promotion_categories (promotion_id, category_id) VALUES (?, ?)');
    foreach ($selectedIds as $categoryId) {
        $stmtInsert->execute([$promotionId, (int)$categoryId]);
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
    $dateEnd = null;
    if (!empty($promotion['date_end'])) {
        $dateEnd = new DateTimeImmutable((string)$promotion['date_end']);
    }

    if ($targetStatus === 'draft') {
        $newStart = $today->modify('+1 day');
        $newEnd = $dateEnd;
        if ($newEnd !== null && $newEnd < $newStart) {
            $newEnd = $newStart;
        }
    } elseif ($targetStatus === 'active') {
        $newStart = $dateStart > $today ? $today : $dateStart;
        $newEnd = $dateEnd;
        if ($newEnd !== null && $newEnd < $today) {
            $newEnd = $today;
        }
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
