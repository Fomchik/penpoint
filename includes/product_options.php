<?php

declare(strict_types=1);

function product_option_catalog(): array
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

function product_option_code_from_name(string $name): string
{
    $catalog = product_option_catalog();
    $lookup = array_flip($catalog);
    $trimmed = trim($name);
    if (isset($lookup[$trimmed])) {
        return (string)$lookup[$trimmed];
    }

    $code = mb_strtolower($trimmed, 'UTF-8');
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];
    $code = strtr($code, $map);
    $code = preg_replace('/[^a-z0-9]+/', '_', $code) ?: '';
    $code = trim($code, '_');

    return $code !== '' ? $code : 'param';
}

function product_options_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS product_parameters (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                product_id INT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                code VARCHAR(80) NOT NULL,
                values_text TEXT NULL,
                use_for_variants TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_product_sort (product_id, sort_order),
                KEY idx_product_code (product_id, code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS product_variants (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                product_id INT UNSIGNED NOT NULL,
                label VARCHAR(255) NOT NULL,
                price DECIMAL(10,2) NULL DEFAULT NULL,
                stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
                attributes_json TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_product_active (product_id, is_active),
                KEY idx_product_price (product_id, price)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        try {
            $pdo->exec('ALTER TABLE product_variants MODIFY price DECIMAL(10,2) NULL DEFAULT NULL');
        } catch (Throwable $e) {
            // Existing schema may already be compatible.
        }

        $columns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM order_items') ?: [] as $column) {
            $columns[(string)$column['Field']] = true;
        }
        if (!isset($columns['variant_id'])) {
            $pdo->exec('ALTER TABLE order_items ADD COLUMN variant_id INT UNSIGNED NULL DEFAULT NULL AFTER product_id');
        }
        if (!isset($columns['attributes_json'])) {
            $pdo->exec('ALTER TABLE order_items ADD COLUMN attributes_json TEXT NULL AFTER variant_id');
        }
        if (!isset($columns['title'])) {
            $pdo->exec('ALTER TABLE order_items ADD COLUMN title VARCHAR(255) NULL DEFAULT NULL AFTER attributes_json');
        }
        if (!isset($columns['variant_label'])) {
            $pdo->exec('ALTER TABLE order_items ADD COLUMN variant_label VARCHAR(255) NULL DEFAULT NULL AFTER title');
        }

        $orderColumns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM orders') ?: [] as $column) {
            $orderColumns[(string)$column['Field']] = true;
        }
        if (!isset($orderColumns['delivery_price'])) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_price");
        }
        if (!isset($orderColumns['discount_total'])) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN discount_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER delivery_price");
        }

        try {
            $pdo->exec('ALTER TABLE order_items DROP INDEX uniq_order_product');
        } catch (Throwable $e) {
            // index may already be absent
        }
        try {
            $pdo->exec('ALTER TABLE order_items ADD UNIQUE KEY uniq_order_product_variant (order_id, product_id, variant_id)');
        } catch (Throwable $e) {
            // index may already exist
        }
    } catch (Throwable $e) {
        error_log('Product options schema ensure error: ' . $e->getMessage());
    }
}

function product_parse_values_text(string $valuesText): array
{
    $parts = preg_split('/[\r\n,]+/u', $valuesText) ?: [];
    $result = [];
    foreach ($parts as $part) {
        $value = trim((string)$part);
        if ($value === '') {
            continue;
        }
        $key = mb_strtolower($value, 'UTF-8');
        $result[$key] = $value;
    }

    return array_values($result);
}

function product_fetch_parameters(int $productId): array
{
    global $pdo;
    product_options_ensure_schema($pdo);

    try {
        $stmt = $pdo->prepare(
            'SELECT id, name, code, values_text, use_for_variants, sort_order
             FROM product_parameters
             WHERE product_id = ?
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll() ?: [];

        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['use_for_variants'] = (int)$row['use_for_variants'];
            $row['sort_order'] = (int)$row['sort_order'];
            $row['values'] = product_parse_values_text((string)($row['values_text'] ?? ''));
        }
        unset($row);

        return $rows;
    } catch (Throwable $e) {
        error_log('Fetch product parameters error: ' . $e->getMessage());
        return [];
    }
}

function product_variant_attributes(array $variant): array
{
    $raw = $variant['attributes_json'] ?? '';
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function product_build_variant_label(array $attributes): string
{
    $parts = [];
    foreach ($attributes as $name => $value) {
        $name = trim((string)$name);
        $value = trim((string)$value);
        if ($name === '' || $value === '') {
            continue;
        }
        $parts[] = $name . ': ' . $value;
    }

    return implode(', ', $parts);
}

function product_fetch_variants(int $productId, bool $activeOnly = false): array
{
    global $pdo;
    product_options_ensure_schema($pdo);

    try {
        $sql = 'SELECT id, product_id, label, price, stock_quantity, attributes_json, is_active
                FROM product_variants
                WHERE product_id = ?';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll() ?: [];

        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['product_id'] = (int)$row['product_id'];
            $row['price'] = $row['price'] !== null ? (float)$row['price'] : null;
            $row['stock_quantity'] = (int)$row['stock_quantity'];
            $row['is_active'] = (int)$row['is_active'];
            $row['attributes'] = product_variant_attributes($row);
            if ((string)$row['label'] === '') {
                $row['label'] = product_build_variant_label($row['attributes']);
            }
        }
        unset($row);

        return $rows;
    } catch (Throwable $e) {
        error_log('Fetch product variants error: ' . $e->getMessage());
        return [];
    }
}

function product_has_real_variants(int $productId): bool
{
    return product_fetch_variants($productId, true) !== [];
}

function product_fetch_variant_by_id(int $productId, int $variantId, bool $activeOnly = true): ?array
{
    foreach (product_fetch_variants($productId, $activeOnly) as $variant) {
        if ((int)$variant['id'] === $variantId) {
            return $variant;
        }
    }

    return null;
}

function get_product_options_for_product(int $productId): array
{
    $parameters = product_fetch_parameters($productId);
    if ($parameters !== []) {
        $schema = [];
        foreach ($parameters as $parameter) {
            if (empty($parameter['values'])) {
                continue;
            }
            $schema[] = [
                'id' => (int)$parameter['id'],
                'code' => (string)$parameter['code'],
                'label' => (string)$parameter['name'],
                'ui' => count($parameter['values']) > 4 ? 'select' : 'buttons',
                'values' => array_values($parameter['values']),
                'use_for_variants' => (int)$parameter['use_for_variants'] === 1,
            ];
        }
        return $schema;
    }

    return [];
}

function product_selection_signature(array $selection): array
{
    $normalized = [];
    foreach ($selection as $key => $value) {
        $k = trim((string)$key);
        $v = trim((string)$value);
        if ($k === '' || $v === '') {
            continue;
        }
        $normalized[mb_strtolower($k, 'UTF-8')] = mb_strtolower($v, 'UTF-8');
    }
    ksort($normalized);
    return $normalized;
}

function product_find_variant_by_selection(int $productId, array $selection): ?array
{
    $signature = product_selection_signature($selection);
    foreach (product_fetch_variants($productId, true) as $variant) {
        if (product_selection_signature($variant['attributes']) === $signature) {
            return $variant;
        }
    }

    return null;
}

function product_get_discount_context(int $productId): array
{
    global $pdo;

    try {
        $stmt = $pdo->prepare(
            'SELECT discount_percent, promotion_id
             FROM v_product_pricing
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$productId]);
        $row = $stmt->fetch();

        return [
            'discount_percent' => (int)($row['discount_percent'] ?? 0),
            'promotion_id' => isset($row['promotion_id']) ? (int)$row['promotion_id'] : null,
        ];
    } catch (Throwable $e) {
        error_log('Discount context error: ' . $e->getMessage());
        return ['discount_percent' => 0, 'promotion_id' => null];
    }
}

function product_find_image_for_attributes(array $images, array $attributes): string
{
    if ($images === []) {
        return APP_DEFAULT_PRODUCT_IMAGE;
    }

    $color = trim((string)($attributes['Цвет'] ?? $attributes['color'] ?? ''));
    if ($color === '') {
        return (string)$images[0];
    }

    $needle = mb_strtolower($color, 'UTF-8');
    foreach ($images as $path) {
        $file = mb_strtolower((string)basename((string)$path), 'UTF-8');
        if (strpos($file, $needle) !== false) {
            return (string)$path;
        }
    }

    return (string)$images[0];
}

function build_dynamic_product_state(array $product, array $selections, array $images): array
{
    $productId = (int)($product['id'] ?? 0);
    $discount = product_get_discount_context($productId);
    $variant = product_find_variant_by_selection($productId, $selections);

    if ($variant !== null) {
        $basePrice = $variant['price'] !== null ? (float)$variant['price'] : (float)($product['price_old'] ?? $product['price'] ?? 0);
        $finalPrice = calculate_discounted_price($basePrice, (int)$discount['discount_percent']);
        $stock = (int)$variant['stock_quantity'];
        $image = product_find_image_for_attributes($images, $variant['attributes']);

        return [
            'variant_id' => (int)$variant['id'],
            'variant_label' => (string)$variant['label'],
            'attributes' => $variant['attributes'],
            'price' => $finalPrice,
            'base_price' => $basePrice,
            'price_formatted' => format_price($finalPrice),
            'base_price_formatted' => format_price($basePrice),
            'stock' => $stock,
            'in_stock' => $stock > 0,
            'discount_percent' => (int)$discount['discount_percent'],
            'promotion_id' => $discount['promotion_id'],
            'image' => $image,
        ];
    }

    $price = (float)($product['price'] ?? 0);
    $basePrice = (float)($product['price_old'] ?? $price);

    return [
        'variant_id' => null,
        'variant_label' => '',
        'attributes' => [],
        'price' => $price,
        'base_price' => $basePrice,
        'price_formatted' => format_price($price),
        'base_price_formatted' => format_price($basePrice),
        'stock' => (int)($product['stock_quantity'] ?? 0),
        'in_stock' => (int)($product['stock_quantity'] ?? 0) > 0,
        'discount_percent' => (int)($product['discount_percent'] ?? 0),
        'promotion_id' => isset($product['promotion_id']) ? (int)$product['promotion_id'] : null,
        'image' => $images[0] ?? APP_DEFAULT_PRODUCT_IMAGE,
    ];
}

function product_collect_cart_snapshot(array $product, ?array $variant = null): array
{
    $images = app_fetch_product_images((int)$product['id']);
    $state = build_dynamic_product_state($product, $variant['attributes'] ?? [], $images);

    return [
        'product_id' => (int)$product['id'],
        'variant_id' => $variant !== null ? (int)$variant['id'] : null,
        'unit_price' => (float)$state['price'],
        'base_price' => (float)$state['base_price'],
        'discount_percent' => (int)$state['discount_percent'],
        'promotion_id' => $state['promotion_id'],
        'title' => (string)$product['name'],
        'variant_label' => (string)($state['variant_label'] ?? ''),
        'image' => (string)$state['image'],
        'attributes' => $variant !== null ? (array)$variant['attributes'] : [],
    ];
}

function product_normalize_admin_parameter_rows(array $rows): array
{
    $result = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row)) {
            continue;
        }

        $name = trim((string)($row['name'] ?? ''));
        $customName = trim((string)($row['custom_name'] ?? ''));
        if ($name === '__custom__') {
            $name = $customName;
        }
        if ($name === '') {
            continue;
        }

        $valuesText = trim((string)($row['values_text'] ?? ''));
        $values = product_parse_values_text($valuesText);
        if ($values === []) {
            continue;
        }

        $result[] = [
            'name' => $name,
            'code' => product_option_code_from_name($name),
            'values_text' => implode("\n", $values),
            'use_for_variants' => !empty($row['use_for_variants']) ? 1 : 0,
            'sort_order' => count($result),
        ];
    }

    return $result;
}

function product_normalize_admin_variant_rows(array $rows): array
{
    $result = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $attributes = [];
        if (is_string($row['attributes_json'] ?? null) && $row['attributes_json'] !== '') {
            $decoded = json_decode(urldecode((string)$row['attributes_json']), true);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    $name = trim((string)$key);
                    $itemValue = trim((string)$value);
                    if ($name !== '' && $itemValue !== '') {
                        $attributes[$name] = $itemValue;
                    }
                }
            }
        } elseif (is_array($row['attributes'] ?? null)) {
            foreach ($row['attributes'] as $key => $value) {
                $name = trim((string)$key);
                $itemValue = trim((string)$value);
                if ($name !== '' && $itemValue !== '') {
                    $attributes[$name] = $itemValue;
                }
            }
        }
        $priceRaw = trim((string)($row['price'] ?? ''));
        $price = $priceRaw === '' ? null : (float)str_replace(',', '.', $priceRaw);
        $stock = max(0, (int)($row['stock_quantity'] ?? 0));
        $label = trim((string)($row['label'] ?? ''));
        if ($attributes === [] && $label === '') {
            continue;
        }

        $result[] = [
            'id' => isset($row['id']) && $row['id'] !== '' ? (int)$row['id'] : null,
            'label' => $label !== '' ? $label : product_build_variant_label($attributes),
            'price' => $price !== null ? max(0, $price) : null,
            'stock_quantity' => $stock,
            'attributes_json' => json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'attributes' => $attributes,
            'is_active' => 1,
        ];
    }

    return $result;
}

function product_save_parameters(PDO $pdo, int $productId, array $rows): void
{
    product_options_ensure_schema($pdo);
    $normalized = product_normalize_admin_parameter_rows($rows);

    $stmtDelete = $pdo->prepare('DELETE FROM product_parameters WHERE product_id = ?');
    $stmtDelete->execute([$productId]);

    if ($normalized === []) {
        return;
    }

    $stmtInsert = $pdo->prepare(
        'INSERT INTO product_parameters (product_id, name, code, values_text, use_for_variants, sort_order)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    foreach ($normalized as $row) {
        $stmtInsert->execute([
            $productId,
            $row['name'],
            $row['code'],
            $row['values_text'],
            $row['use_for_variants'],
            $row['sort_order'],
        ]);
    }
}

function product_save_variants(PDO $pdo, int $productId, array $rows): void
{
    product_options_ensure_schema($pdo);
    $normalized = product_normalize_admin_variant_rows($rows);

    $existingIds = array_map('intval', array_column(product_fetch_variants($productId, false), 'id'));
    $incomingIds = array_values(array_filter(array_map(static function ($row) {
        return $row['id'] ?? null;
    }, $normalized)));

    $idsToDelete = array_diff($existingIds, $incomingIds);
    if ($idsToDelete !== []) {
        $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
        $stmtDelete = $pdo->prepare("DELETE FROM product_variants WHERE product_id = ? AND id IN ($placeholders)");
        $stmtDelete->execute(array_merge([$productId], array_values($idsToDelete)));
    }

    $stmtInsert = $pdo->prepare(
        'INSERT INTO product_variants (product_id, label, price, stock_quantity, attributes_json, is_active)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmtUpdate = $pdo->prepare(
        'UPDATE product_variants
         SET label = ?, price = ?, stock_quantity = ?, attributes_json = ?, is_active = ?
         WHERE id = ? AND product_id = ? LIMIT 1'
    );

    foreach ($normalized as $row) {
        if (!empty($row['id'])) {
            $stmtUpdate->execute([
                $row['label'],
                $row['price'],
                $row['stock_quantity'],
                $row['attributes_json'],
                $row['is_active'],
                $row['id'],
                $productId,
            ]);
            continue;
        }

        $stmtInsert->execute([
            $productId,
            $row['label'],
            $row['price'],
            $row['stock_quantity'],
            $row['attributes_json'],
            $row['is_active'],
        ]);
    }
}

function product_fetch_attribute_catalog_rows(): array
{
    $rows = [];
    foreach (product_option_catalog() as $code => $name) {
        $rows[] = [
            'id' => 0,
            'code' => $code,
            'name' => $name,
        ];
    }

    return $rows;
}

function product_admin_form_payload(int $productId): array
{
    return [
        'catalog' => product_fetch_attribute_catalog_rows(),
        'parameters' => $productId > 0 ? product_fetch_parameters($productId) : [],
        'variants' => $productId > 0 ? product_fetch_variants($productId, false) : [],
    ];
}
