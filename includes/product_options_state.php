<?php

declare(strict_types=1);

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

function product_normalize_selection_for_product(int $productId, array $selection): array
{
    $normalized = [];
    $params = product_fetch_parameters($productId);
    $codeToName = [];
    $nameToCode = [];

    foreach ($params as $param) {
        $code = mb_strtolower(trim((string)($param['code'] ?? '')), 'UTF-8');
        $name = mb_strtolower(trim((string)($param['name'] ?? '')), 'UTF-8');
        if ($code !== '' && $name !== '') {
            $codeToName[$code] = $name;
            $nameToCode[$name] = $code;
        }
    }

    foreach ($selection as $key => $value) {
        $rawKey = mb_strtolower(trim((string)$key), 'UTF-8');
        $rawValue = trim((string)$value);
        if ($rawKey === '' || $rawValue === '') {
            continue;
        }

        $normalizedValue = mb_strtolower($rawValue, 'UTF-8');
        $normalized[$rawKey] = $normalizedValue;

        if (isset($codeToName[$rawKey])) {
            $normalized[$codeToName[$rawKey]] = $normalizedValue;
        }
        if (isset($nameToCode[$rawKey])) {
            $normalized[$nameToCode[$rawKey]] = $normalizedValue;
        }
    }

    ksort($normalized);
    return $normalized;
}

function product_find_variant_by_selection(int $productId, array $selection): ?array
{
    $signature = product_normalize_selection_for_product($productId, $selection);
    foreach (product_fetch_variants($productId, true) as $variant) {
        $variantSignature = product_normalize_selection_for_product($productId, (array)($variant['attributes'] ?? []));
        if ($variantSignature === $signature) {
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

function app_fetch_product_variant_images(int $productId): array
{
    global $pdo;

    try {
        $stmt = $pdo->prepare(
            'SELECT variant_id, parameter_code, parameter_value, image_path
             FROM product_variant_images
             WHERE product_id = ?
             ORDER BY id ASC'
        );
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll() ?: [];

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'variant_id' => isset($row['variant_id']) && $row['variant_id'] !== null && $row['variant_id'] !== '' ? (int)$row['variant_id'] : null,
                'parameter_code' => trim((string)($row['parameter_code'] ?? '')),
                'parameter_value' => trim((string)($row['parameter_value'] ?? '')),
                'image_path' => trim((string)($row['image_path'] ?? '')),
            ];
        }

        return $result;
    } catch (Throwable $e) {
        return [];
    }
}

function product_normalize_attribute_keys(array $attributes): array
{
    $normalized = [];
    $catalog = array_change_key_case(product_option_catalog(), CASE_LOWER);
    $displayToCode = [];
    foreach ($catalog as $code => $name) {
        $displayToCode[mb_strtolower((string)$name, 'UTF-8')] = $code;
    }

    foreach ($attributes as $name => $value) {
        $key = mb_strtolower(trim((string)$name), 'UTF-8');
        $value = trim((string)$value);
        if ($key === '' || $value === '') {
            continue;
        }

        $normalized[$key] = $value;
        if (isset($displayToCode[$key])) {
            $normalized[$displayToCode[$key]] = $value;
        }
    }

    return $normalized;
}

function product_humanize_attributes(int $productId, array $attributes): array
{
    if ($attributes === []) {
        return [];
    }

    $codeToLabel = [];
    foreach (product_option_catalog() as $code => $label) {
        $codeToLabel[mb_strtolower((string)$code, 'UTF-8')] = (string)$label;
    }

    foreach (product_fetch_parameters($productId) as $param) {
        $code = mb_strtolower(trim((string)($param['code'] ?? '')), 'UTF-8');
        $name = trim((string)($param['name'] ?? ''));
        if ($code !== '' && $name !== '') {
            $codeToLabel[$code] = $name;
        }
    }

    $result = [];
    foreach ($attributes as $key => $value) {
        $rawKey = trim((string)$key);
        $rawValue = trim((string)$value);
        if ($rawKey === '' || $rawValue === '') {
            continue;
        }

        $normalizedKey = mb_strtolower($rawKey, 'UTF-8');
        $displayKey = $codeToLabel[$normalizedKey] ?? $rawKey;
        $result[$displayKey] = $rawValue;
    }

    return $result;
}

function product_find_stock_for_selection(int $productId, array $attributes): ?int
{
    if ($attributes === []) {
        return null;
    }

    $normalizedAttributes = product_normalize_attribute_keys($attributes);
    if ($normalizedAttributes === []) {
        return null;
    }

    $matchedStocks = [];
    foreach (product_fetch_parameters($productId) as $param) {
        $code = mb_strtolower(trim((string)($param['code'] ?? '')), 'UTF-8');
        $name = mb_strtolower(trim((string)($param['name'] ?? '')), 'UTF-8');

        foreach ((array)($param['values'] ?? []) as $val) {
            if (!is_array($val)) {
                continue;
            }

            $valueName = trim((string)($val['name'] ?? ''));
            if ($valueName === '') {
                continue;
            }

            $valueNorm = mb_strtolower($valueName, 'UTF-8');
            $match = false;
            if ($code !== '' && isset($normalizedAttributes[$code]) && mb_strtolower((string)$normalizedAttributes[$code], 'UTF-8') === $valueNorm) {
                $match = true;
            }
            if (!$match && $name !== '' && isset($normalizedAttributes[$name]) && mb_strtolower((string)$normalizedAttributes[$name], 'UTF-8') === $valueNorm) {
                $match = true;
            }

            if ($match && isset($val['stock']) && $val['stock'] !== null && $val['stock'] !== '') {
                $matchedStocks[] = max(0, (int)$val['stock']);
                break;
            }
        }
    }

    if ($matchedStocks === []) {
        return null;
    }

    $stockMode = 'strict_min';
    global $pdo;
    static $schemaChecked = false;
    static $modeCache = [];
    if (isset($modeCache[$productId])) {
        $stockMode = $modeCache[$productId];
    } elseif (isset($pdo) && $pdo instanceof PDO) {
        try {
            if (!$schemaChecked) {
                $schemaChecked = true;
                $hasColumn = false;
                foreach ($pdo->query("SHOW COLUMNS FROM products LIKE 'parameter_stock_mode'") ?: [] as $column) {
                    $hasColumn = true;
                    break;
                }
                if (!$hasColumn) {
                    $pdo->exec("ALTER TABLE products ADD COLUMN parameter_stock_mode VARCHAR(24) NOT NULL DEFAULT 'strict_min' AFTER has_color_variants");
                }
            }

            $stmt = $pdo->prepare('SELECT parameter_stock_mode FROM products WHERE id = ? LIMIT 1');
            $stmt->execute([$productId]);
            $mode = trim((string)($stmt->fetchColumn() ?: 'strict_min'));
            if (!in_array($mode, ['strict_min', 'independent_sum'], true)) {
                $mode = 'strict_min';
            }
            $stockMode = $mode;
            $modeCache[$productId] = $stockMode;
        } catch (Throwable $e) {
            $stockMode = 'strict_min';
        }
    }

    if ($stockMode === 'independent_sum') {
        return max(0, (int)array_sum($matchedStocks));
    }

    return max(0, (int)min($matchedStocks));
}

function product_collect_images_from_parameter_rows(int $productId, array $attributes): array
{
    if ($attributes === []) {
        return [];
    }

    $normalizedAttributes = product_normalize_attribute_keys($attributes);
    if ($normalizedAttributes === []) {
        return [];
    }

    $images = [];
    foreach (product_fetch_parameters($productId) as $param) {
        $code = mb_strtolower(trim((string)($param['code'] ?? '')), 'UTF-8');
        $name = mb_strtolower(trim((string)($param['name'] ?? '')), 'UTF-8');

        foreach ((array)($param['values'] ?? []) as $val) {
            if (!is_array($val)) {
                continue;
            }

            $valueName = trim((string)($val['name'] ?? ''));
            $path = trim((string)($val['image_path'] ?? ''));
            if ($valueName === '' || $path === '') {
                continue;
            }

            $valNorm = mb_strtolower($valueName, 'UTF-8');
            if ($valNorm === '') {
                continue;
            }

            $match = false;
            if ($code !== '' && isset($normalizedAttributes[$code]) && mb_strtolower((string)$normalizedAttributes[$code], 'UTF-8') === $valNorm) {
                $match = true;
            }
            if (!$match && $name !== '' && isset($normalizedAttributes[$name]) && mb_strtolower((string)$normalizedAttributes[$name], 'UTF-8') === $valNorm) {
                $match = true;
            }

            if ($match) {
                $images[] = $path;
                break;
            }
        }
    }

    return array_values(array_filter(array_unique($images), static function ($item) {
        return $item !== '';
    }));
}

function product_find_images_for_selection(int $productId, array $attributes, ?int $variantId, array $commonImages): array
{
    $variantImages = app_fetch_product_variant_images($productId);
    $images = [];

    if ($variantId !== null) {
        foreach ($variantImages as $row) {
            if ($row['variant_id'] === $variantId && $row['image_path'] !== '') {
                $images[] = $row['image_path'];
            }
        }
    }

    if ($images === []) {
        $normalizedAttributes = product_normalize_attribute_keys($attributes);
        if ($normalizedAttributes !== []) {
            foreach ($variantImages as $row) {
                if ($row['variant_id'] !== null || $row['parameter_code'] === '' || $row['parameter_value'] === '') {
                    continue;
                }

                $code = mb_strtolower($row['parameter_code'], 'UTF-8');
                $value = mb_strtolower($row['parameter_value'], 'UTF-8');
                if (isset($normalizedAttributes[$code]) && mb_strtolower($normalizedAttributes[$code], 'UTF-8') === $value) {
                    $images[] = $row['image_path'];
                }
            }
        }
    }

    if ($images === []) {
        $images = product_collect_images_from_parameter_rows($productId, $attributes);
    }

    if ($images === []) {
        $images = $commonImages;
    } else {
        $images = array_merge($images, $commonImages);
    }

    return array_values(array_filter(array_unique($images), static function ($item) {
        return $item !== '';
    }));
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
    $displayAttributes = product_humanize_attributes($productId, $selections);

    if ($variant !== null) {
        $basePrice = $variant['price'] !== null ? (float)$variant['price'] : (float)($product['price_old'] ?? $product['price'] ?? 0);
        $finalPrice = calculate_discounted_price($basePrice, (int)$discount['discount_percent']);
        $stock = (int)$variant['stock_quantity'];
        $variantImages = product_find_images_for_selection($productId, $variant['attributes'], (int)$variant['id'], $images);
        $currentImage = $variantImages[0] ?? APP_DEFAULT_PRODUCT_IMAGE;

        return [
            'variant_id' => (int)$variant['id'],
            'variant_label' => (string)$variant['label'],
            'attributes' => product_humanize_attributes($productId, (array)$variant['attributes']),
            'price' => $finalPrice,
            'base_price' => $basePrice,
            'price_formatted' => format_price($finalPrice),
            'base_price_formatted' => format_price($basePrice),
            'stock' => $stock,
            'in_stock' => $stock > 0,
            'discount_percent' => (int)$discount['discount_percent'],
            'promotion_id' => $discount['promotion_id'],
            'image' => $currentImage,
            'images' => $variantImages,
        ];
    }

    $price = (float)($product['price'] ?? 0);
    $basePrice = (float)($product['price_old'] ?? $price);
    $fallbackStock = (int)($product['stock_quantity'] ?? 0);
    $parameterStock = product_find_stock_for_selection($productId, $selections);
    $stock = $parameterStock !== null ? $parameterStock : $fallbackStock;
    $allImages = array_values(array_filter(array_unique($images), static function ($item) {
        return $item !== '';
    }));

    $selectedImages = product_collect_images_from_parameter_rows($productId, $selections);
    $effectiveImages = $selectedImages !== [] ? array_values(array_unique(array_merge($selectedImages, $allImages))) : $allImages;

    return [
        'variant_id' => null,
        'variant_label' => '',
        'attributes' => $displayAttributes,
        'price' => $price,
        'base_price' => $basePrice,
        'price_formatted' => format_price($price),
        'base_price_formatted' => format_price($basePrice),
        'stock' => $stock,
        'in_stock' => $stock > 0,
        'discount_percent' => (int)($product['discount_percent'] ?? 0),
        'promotion_id' => isset($product['promotion_id']) ? (int)$product['promotion_id'] : null,
        'image' => $effectiveImages[0] ?? APP_DEFAULT_PRODUCT_IMAGE,
        'images' => $effectiveImages,
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
