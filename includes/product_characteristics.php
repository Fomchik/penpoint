<?php

declare(strict_types=1);

function product_characteristics_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS product_characteristics (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                product_id INT UNSIGNED NOT NULL,
                name VARCHAR(150) NOT NULL,
                value VARCHAR(255) NOT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_product_sort (product_id, sort_order, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        error_log('Product characteristics schema ensure error: ' . $e->getMessage());
    }
}

function product_characteristics_fetch(PDO $pdo, int $productId): array
{
    product_characteristics_ensure_schema($pdo);

    if ($productId <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, name, value, sort_order
             FROM product_characteristics
             WHERE product_id = ?
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['sort_order'] = (int)$row['sort_order'];
        }
        unset($row);
        return $rows;
    } catch (Throwable $e) {
        error_log('Fetch product characteristics error: ' . $e->getMessage());
        return [];
    }
}

function product_characteristics_save(PDO $pdo, int $productId, array $rows): void
{
    product_characteristics_ensure_schema($pdo);

    if ($productId <= 0) {
        return;
    }

    $clean = [];
    $order = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string)($row['name'] ?? ''));
        $value = trim((string)($row['value'] ?? ''));
        if ($name === '' || $value === '') {
            continue;
        }
        $clean[] = [
            'name' => mb_substr($name, 0, 150, 'UTF-8'),
            'value' => mb_substr($value, 0, 255, 'UTF-8'),
            'sort_order' => $order,
        ];
        $order += 1;
    }

    $pdo->prepare('DELETE FROM product_characteristics WHERE product_id = ?')->execute([$productId]);
    if ($clean === []) {
        return;
    }

    $insert = $pdo->prepare(
        'INSERT INTO product_characteristics (product_id, name, value, sort_order) VALUES (?, ?, ?, ?)'
    );
    foreach ($clean as $item) {
        $insert->execute([$productId, $item['name'], $item['value'], $item['sort_order']]);
    }
}

