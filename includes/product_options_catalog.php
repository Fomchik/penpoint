<?php

declare(strict_types=1);

function product_option_catalog_defaults(): array
{
    return [
        'color' => 'Цвет',
        'size' => 'Размер',
        'format' => 'Формат',
        'volume' => 'Объем',
        'thickness' => 'Толщина',
        'set_quantity' => 'Количество в наборе',
        'sheet_quantity' => 'Количество листов',
        'paper_density' => 'Плотность бумаги',
        'paper_type' => 'Тип бумаги',
        'binding_type' => 'Тип крепления',
        'cover_type' => 'Тип обложки',
        'hardness' => 'Жесткость',
    ];
}

function product_option_slugify_name(string $name): string
{
    $value = trim($name);
    if ($value === '') {
        return 'param';
    }

    $ascii = '';
    if (function_exists('transliterator_transliterate')) {
        $ascii = (string)transliterator_transliterate('Any-Latin; Latin-ASCII;', $value);
    } else {
        $iconv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $ascii = is_string($iconv) ? $iconv : '';
    }

    $source = $ascii !== '' ? mb_strtolower($ascii, 'UTF-8') : mb_strtolower($value, 'UTF-8');
    $slug = preg_replace('/[^a-z0-9]+/u', '_', $source) ?: '';
    $slug = trim($slug, '_');

    return $slug !== '' ? $slug : 'param';
}

function product_option_catalog_ensure_schema(PDO $pdo): void
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
        foreach (array_values(product_option_catalog_defaults()) as $name) {
            $insert->execute([$name]);
        }
    } catch (Throwable $e) {
        error_log('Product option catalog schema error: ' . $e->getMessage());
    }
}

function product_option_catalog_from_db(PDO $pdo): array
{
    product_option_catalog_ensure_schema($pdo);

    try {
        $stmt = $pdo->query('SELECT name FROM attributes ORDER BY name ASC');
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
        $map = [];
        $used = [];
        foreach ($rows as $rawName) {
            $name = trim((string)$rawName);
            if ($name === '') {
                continue;
            }

            $baseCode = product_option_slugify_name($name);
            $code = $baseCode;
            $suffix = 2;
            while (isset($used[$code])) {
                $code = $baseCode . '_' . $suffix;
                $suffix++;
            }
            $used[$code] = true;
            $map[$code] = $name;
        }

        return $map;
    } catch (Throwable $e) {
        error_log('Product option catalog fetch error: ' . $e->getMessage());
        return [];
    }
}

function product_option_catalog(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $fallback = product_option_catalog_defaults();
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        $fromDb = product_option_catalog_from_db($pdo);
        if ($fromDb !== []) {
            $cache = $fromDb;
            return $cache;
        }
    }

    $cache = $fallback;
    return $cache;
}

function product_option_code_from_name(string $name): string
{
    $catalog = product_option_catalog();
    $lookup = array_flip($catalog);
    $trimmed = trim($name);
    if (isset($lookup[$trimmed])) {
        return (string)$lookup[$trimmed];
    }

    return product_option_slugify_name($trimmed);
}

