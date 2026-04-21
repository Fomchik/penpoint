<?php

declare(strict_types=1);

function admin_attribute_catalog_defaults(): array
{
    return [
        'Цвет',
        'Размер',
        'Формат',
        'Объем',
        'Толщина',
        'Количество в наборе',
        'Количество листов',
        'Плотность бумаги',
        'Тип бумаги',
        'Тип крепления',
        'Тип обложки',
        'Жесткость',
    ];
}

function admin_attribute_catalog_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS attributes (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_attributes_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $insert = $pdo->prepare('INSERT IGNORE INTO attributes (name) VALUES (?)');
        foreach (admin_attribute_catalog_defaults() as $name) {
            $insert->execute([$name]);
        }
    } catch (Throwable $e) {
        admin_log_error('attribute_catalog_schema', $e);
    }
}

function admin_attribute_catalog_all(PDO $pdo): array
{
    admin_attribute_catalog_ensure_schema($pdo);
    try {
        $stmt = $pdo->query('SELECT id, name FROM attributes ORDER BY name ASC');
        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    } catch (Throwable $e) {
        admin_log_error('attribute_catalog_all', $e);
        return [];
    }
}

function admin_attribute_catalog_add(PDO $pdo, string $name): array
{
    admin_attribute_catalog_ensure_schema($pdo);

    $name = trim($name);
    if ($name === '') {
        throw new RuntimeException('Укажите название параметра.');
    }

    // Проверка на существование (без учета регистра)
    $stmt = $pdo->prepare('SELECT id, name FROM attributes WHERE LOWER(name) = LOWER(?) LIMIT 1');
    $stmt->execute([$name]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        return [
            'id' => (int)$existing['id'],
            'name' => (string)$existing['name'],
        ];
    }

    $stmt = $pdo->prepare('INSERT INTO attributes (name) VALUES (?)');
    $stmt->execute([$name]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'name' => $name,
    ];
}

function admin_attribute_catalog_delete(PDO $pdo, int $id): bool
{
    admin_attribute_catalog_ensure_schema($pdo);

    try {
        
        $stmt = $pdo->prepare('DELETE FROM attributes WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        admin_log_error('attribute_catalog_delete', $e);
        return false;
    }
}

function admin_attribute_catalog_exists(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM attributes WHERE LOWER(name) = LOWER(?) LIMIT 1');
    $stmt->execute([trim($name)]);
    return (bool)$stmt->fetch();
}