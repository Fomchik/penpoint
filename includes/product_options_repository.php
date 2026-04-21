<?php

declare(strict_types=1);

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
            'SELECT p.id, p.name, p.code, p.sort_order,
                    pv.id as value_id, pv.name as value_name, pv.price, pv.stock_quantity, pv.image_path as value_image_path, pv.sort_order as value_sort_order
             FROM product_parameters p
             LEFT JOIN product_parameter_values pv ON pv.parameter_id = p.id
             WHERE p.product_id = ?
             ORDER BY p.sort_order ASC, p.id ASC, pv.sort_order ASC, pv.id ASC'
        );
        $stmt->execute([$productId]);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $parameters = [];
        foreach ($rawRows as $row) {
            $paramId = (int)$row['id'];
            if (!isset($parameters[$paramId])) {
                $parameters[$paramId] = [
                    'id' => $paramId,
                    'name' => $row['name'],
                    'code' => $row['code'],
                    'sort_order' => (int)$row['sort_order'],
                    'values' => [],
                ];
            }

            if ($row['value_id'] !== null) {
                $parameters[$paramId]['values'][] = [
                    'id' => (int)$row['value_id'],
                    'name' => $row['value_name'],
                    'price' => $row['price'] !== null ? (float)$row['price'] : null,
                    'stock' => $row['stock_quantity'] !== null ? (int)$row['stock_quantity'] : null,
                    'image_path' => trim((string)($row['value_image_path'] ?? '')),
                ];
            }
        }

        return array_values($parameters);
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
        $schemaMap = [];
        foreach ($parameters as $parameter) {
            if (empty($parameter['values'])) {
                continue;
            }

            $cleanValues = [];
            foreach ((array)$parameter['values'] as $rawValue) {
                $value = is_array($rawValue)
                    ? trim((string)($rawValue['name'] ?? ''))
                    : trim((string)$rawValue);
                if ($value === '' || mb_strtolower($value, 'UTF-8') === 'нет') {
                    continue;
                }
                $signature = mb_strtolower($value, 'UTF-8');
                if (isset($cleanValues[$signature])) {
                    continue;
                }
                $cleanValues[$signature] = $value;
            }
            if ($cleanValues === []) {
                continue;
            }

            $key = mb_strtolower(trim((string)$parameter['code']) !== '' ? (string)$parameter['code'] : (string)$parameter['name'], 'UTF-8');
            if (!isset($schemaMap[$key])) {
                $schemaMap[$key] = [
                    'id' => (int)$parameter['id'],
                    'code' => (string)$parameter['code'],
                    'label' => (string)$parameter['name'],
                    'ui' => 'buttons',
                    'values' => [],
                    'use_for_variants' => false,
                ];
            }

            foreach ($cleanValues as $value) {
                $signature = mb_strtolower($value, 'UTF-8');
                $exists = false;
                foreach ($schemaMap[$key]['values'] as $existingValue) {
                    if (mb_strtolower((string)$existingValue, 'UTF-8') === $signature) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $schemaMap[$key]['values'][] = $value;
                }
            }
            $schemaMap[$key]['use_for_variants'] = $schemaMap[$key]['use_for_variants'] || ((int)$parameter['use_for_variants'] === 1);
        }

        $schema = array_values($schemaMap);
        foreach ($schema as &$item) {
            $item['ui'] = count($item['values']) > 4 ? 'select' : 'buttons';
        }
        unset($item);
        return $schema;
    }

    return [];
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
