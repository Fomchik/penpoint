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
        $stmt = $pdo->query('SHOW COLUMNS FROM promotions');
        foreach ($stmt ?: [] as $column) {
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
        
        try {
            $pdo->exec("ALTER TABLE promotions MODIFY image_path VARCHAR(255) NULL DEFAULT NULL");
        } catch (Throwable $e) {

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
        'regular' => 'Обычная',
        'seasonal' => 'Сезонная',
    ];
}

function admin_promotion_parse_ids($values): array
{
    if (!is_array($values)) {
        return [];
    }

    return array_values(array_filter(array_map('intval', $values), fn($id) => $id > 0));
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

    return $row ?: null;
}

function admin_promotion_fetch_links(PDO $pdo, int $promotionId, string $scope): array
{
    $table = ($scope === 'products') ? 'promotion_products' : 'promotion_categories';
    $column = ($scope === 'products') ? 'product_id' : 'category_id';
    
    $stmt = $pdo->prepare("SELECT $column FROM $table WHERE promotion_id = ?");
    $stmt->execute([$promotionId]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function admin_promotion_sync_links(PDO $pdo, int $promotionId, string $scope, array $selectedIds): void
{

    $pdo->prepare('DELETE FROM promotion_products WHERE promotion_id = ?')->execute([$promotionId]);
    $pdo->prepare('DELETE FROM promotion_categories WHERE promotion_id = ?')->execute([$promotionId]);

    if (empty($selectedIds)) {
        return;
    }

    $table = ($scope === 'products') ? 'promotion_products' : 'promotion_categories';
    $column = ($scope === 'products') ? 'product_id' : 'category_id';

    $stmt = $pdo->prepare("INSERT INTO $table (promotion_id, $column) VALUES (?, ?)");
    foreach ($selectedIds as $id) {
        $stmt->execute([$promotionId, (int)$id]);
    }
}

function admin_promotion_force_status(PDO $pdo, int $promotionId, string $targetStatus): void
{
    $promotion = admin_promotion_fetch_one($pdo, $promotionId);
    if (!$promotion) {
        throw new RuntimeException('Акция не найдена.');
    }

    $today = new DateTimeImmutable('today');
    $dateStart = new DateTimeImmutable((string)($promotion['date_start'] ?? 'today'));
    $dateEnd = !empty($promotion['date_end']) ? new DateTimeImmutable((string)$promotion['date_end']) : null;

    switch ($targetStatus) {
        case 'draft':
            $newStart = $today->modify('+1 day');
            $newEnd = ($dateEnd !== null && $dateEnd < $newStart) ? $newStart->modify('+7 days') : $dateEnd;
            break;

        case 'active':
            $newStart = ($dateStart > $today) ? $today : $dateStart;
            $newEnd = ($dateEnd !== null && $dateEnd < $today) ? $today->modify('+7 days') : $dateEnd;
            break;

        case 'finished':
            $newEnd = $today->modify('-1 day');
            $newStart = ($dateStart > $newEnd) ? $newEnd->modify('-7 days') : $dateStart;
            break;

        default:
            throw new RuntimeException('Недопустимый статус.');
    }

    $stmt = $pdo->prepare('UPDATE promotions SET date_start = ?, date_end = ?, status = ? WHERE id = ? LIMIT 1');
    $stmt->execute([
        $newStart->format('Y-m-d'),
        $newEnd ? $newEnd->format('Y-m-d') : null,
        $targetStatus,
        $promotionId,
    ]);
}