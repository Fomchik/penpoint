<?php

declare(strict_types=1);

function product_parse_uploaded_variant_images(array $files): array
{
    $result = [];
    $fields = ['name', 'type', 'tmp_name', 'error', 'size'];

    foreach ($fields as $field) {
        if (!isset($files[$field]) || !is_array($files[$field])) {
            return [];
        }
    }

    foreach ($files['name'] as $pIdx => $paramData) {
        if (!is_array($paramData) || !isset($paramData['values'])) {
            continue;
        }

        foreach ($paramData['values'] as $vIdx => $valueData) {
            if (!is_array($valueData) || !isset($valueData['image'])) {
                continue;
            }

            $slot = (string)$pIdx . '_' . (string)$vIdx;
            $result[$slot] = [
                'image' => [
                    'name' => $files['name'][$pIdx]['values'][$vIdx]['image'],
                    'type' => $files['type'][$pIdx]['values'][$vIdx]['image'],
                    'tmp_name' => $files['tmp_name'][$pIdx]['values'][$vIdx]['image'],
                    'error' => $files['error'][$pIdx]['values'][$vIdx]['image'],
                    'size' => $files['size'][$pIdx]['values'][$vIdx]['image'],
                ],
            ];
        }
    }

    return $result;
}

function product_normalize_admin_parameter_rows(array $rows): array
{
    $result = [];

    foreach ($rows as $pIdx => $param) {
        if (!is_array($param)) {
            continue;
        }

        $name = trim((string)($param['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $values = [];
        if (isset($param['values']) && is_array($param['values'])) {
            foreach ($param['values'] as $vIdx => $value) {
                if (!is_array($value)) {
                    continue;
                }

                $valueName = trim((string)($value['name'] ?? ''));
                if ($valueName === '') {
                    continue;
                }

                $values[] = [
                    'id' => isset($value['id']) && is_numeric($value['id']) ? (int)$value['id'] : null,
                    'name' => $valueName,
                    'price' => isset($value['price']) && is_numeric($value['price']) ? (float)$value['price'] : null,
                    'stock' => isset($value['stock']) && is_numeric($value['stock']) ? (int)$value['stock'] : null,
                    'image_path' => trim((string)($value['image_path'] ?? '')),
                ];
            }
        }

        if ($values === []) {
            continue;
        }

        $result[] = [
            'name' => $name,
            'code' => product_option_code_from_name($name),
            'values' => $values,
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
                    if ($name !== '' && $itemValue !== '' && mb_strtolower($itemValue, 'UTF-8') !== 'нет') {
                        $attributes[$name] = $itemValue;
                    }
                }
            }
        } elseif (is_array($row['attributes'] ?? null)) {
            foreach ($row['attributes'] as $key => $value) {
                $name = trim((string)$key);
                $itemValue = trim((string)$value);
                if ($name !== '' && $itemValue !== '' && mb_strtolower($itemValue, 'UTF-8') !== 'нет') {
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

function product_uploaded_parameter_image_file(array $files, int|string $index): ?array
{
    if ($files === []) {
        return null;
    }

    if (isset($files[$index]['image']['error'])) {
        $file = $files[$index]['image'];
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        return [
            'name' => (string)($file['name'] ?? ''),
            'type' => (string)($file['type'] ?? ''),
            'tmp_name' => (string)($file['tmp_name'] ?? ''),
            'error' => (int)$file['error'],
            'size' => (int)($file['size'] ?? 0),
        ];
    }

    $err = $files['error'][$index]['image'] ?? null;
    if ($err === null || (int)$err !== UPLOAD_ERR_OK) {
        return null;
    }

    return [
        'name' => (string)($files['name'][$index]['image'] ?? ''),
        'type' => (string)($files['type'][$index]['image'] ?? ''),
        'tmp_name' => (string)($files['tmp_name'][$index]['image'] ?? ''),
        'error' => (int)$err,
        'size' => (int)($files['size'][$index]['image'] ?? 0),
    ];
}

function product_delete_public_file(string $publicPath): void
{
    $publicPath = trim($publicPath);
    if ($publicPath === '' || $publicPath[0] !== '/') {
        return;
    }

    $documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
    if ($documentRoot === '') {
        return;
    }

    $absolutePath = $documentRoot . str_replace('/', DIRECTORY_SEPARATOR, $publicPath);
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function product_save_parameters(PDO $pdo, int $productId, array $rows, array $files = []): void
{
    product_options_ensure_schema($pdo);
    $normalized = product_normalize_admin_parameter_rows($rows);
    $uploadedFiles = product_parse_uploaded_variant_images($files);

    // Удаляем старые параметры и значения
    $stmtDeleteValues = $pdo->prepare('DELETE FROM product_parameter_values WHERE parameter_id IN (SELECT id FROM product_parameters WHERE product_id = ?)');
    $stmtDeleteValues->execute([$productId]);
    $stmtDeleteParams = $pdo->prepare('DELETE FROM product_parameters WHERE product_id = ?');
    $stmtDeleteParams->execute([$productId]);

    $stmtInsertParam = $pdo->prepare(
        'INSERT INTO product_parameters (product_id, name, code, sort_order) VALUES (?, ?, ?, ?)'
    );
    $stmtInsertValue = $pdo->prepare(
        'INSERT INTO product_parameter_values (parameter_id, name, price, stock_quantity, image_path, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
    );

    foreach ($normalized as $paramIndex => $param) {
        $stmtInsertParam->execute([
            $productId,
            $param['name'],
            $param['code'],
            $param['sort_order'],
        ]);
        $paramId = (int)$pdo->lastInsertId();

        foreach ($param['values'] as $valueIndex => $value) {
            $imagePath = $value['image_path'] ?? '';

            // Обработка загрузки файла
            $slot = (string)$paramIndex . '_' . (string)$valueIndex;
            $uploaded = $uploadedFiles[$slot]['image'] ?? null;
            if (is_array($uploaded) && isset($uploaded['error']) && (int)$uploaded['error'] === UPLOAD_ERR_OK) {
                try {
                    $uploadedPath = admin_handle_image_upload($uploaded, [
                        'target' => 'product_images',
                        'prefix' => 'parameter_value',
                    ]);
                    if ($uploadedPath !== null) {
                        if ($imagePath !== '' && $imagePath !== $uploadedPath) {
                            product_delete_public_file($imagePath);
                        }
                        $imagePath = $uploadedPath;
                    }
                } catch (Throwable $e) {
                    error_log('Upload parameter value image error: ' . $e->getMessage());
                }
            }

            $stmtInsertValue->execute([
                $paramId,
                $value['name'],
                $value['price'],
                $value['stock'],
                $imagePath,
                $valueIndex,
            ]);
        }
    }
}

function product_save_variant_images(PDO $pdo, int $productId, array $rows): void
{
    product_options_ensure_schema($pdo);

    try {
        $stmtDelete = $pdo->prepare('DELETE FROM product_variant_images WHERE product_id = ?');
        $stmtDelete->execute([$productId]);

        $stmtInsert = $pdo->prepare(
            'INSERT INTO product_variant_images (product_id, variant_id, parameter_code, parameter_value, image_path, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $parameterCode = trim((string)($row['parameter_code'] ?? $row['code'] ?? ''));
            $parameterValue = trim((string)($row['parameter_value'] ?? $row['values_text'] ?? ''));
            if ($parameterCode === '' || $parameterValue === '') {
                continue;
            }

            if (preg_match('/[\r\n]/', $parameterValue)) {
                continue;
            }

            $imagePath = trim((string)($row['image_path'] ?? $row['image_path_existing'] ?? ''));
            if ($imagePath === '') {
                continue;
            }

            $variantId = isset($row['variant_id']) && $row['variant_id'] !== '' ? (int)$row['variant_id'] : null;
            $sortOrder = isset($row['sort_order']) ? (int)$row['sort_order'] : $index;
            $stmtInsert->execute([
                $productId,
                $variantId,
                $parameterCode,
                $parameterValue,
                $imagePath,
                $sortOrder,
            ]);
        }
    } catch (Throwable $e) {
        error_log('Save variant images error: ' . $e->getMessage());
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

function product_admin_form_payload(int $productId): array
{
    return [
        'catalog' => product_fetch_attribute_catalog_rows(),
        'parameters' => $productId > 0 ? product_fetch_parameters($productId) : [],
        'variants' => $productId > 0 ? product_fetch_variants($productId, false) : [],
    ];
}
