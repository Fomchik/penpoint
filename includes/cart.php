<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/product_options.php';

function cart_session_key(): string
{
    return 'penpoint_cart_items';
}

function cart_line_key(int $productId, ?int $variantId): string
{
    return $productId . ':' . ($variantId === null ? 'base' : (string)$variantId);
}

function cart_normalize_attributes(array $attributes): array
{
    $normalized = [];
    foreach ($attributes as $key => $value) {
        $k = trim((string)$key);
        $v = trim((string)$value);
        if ($k === '' || $v === '') {
            continue;
        }
        $normalized[$k] = $v;
    }
    ksort($normalized);
    return $normalized;
}

function cart_get_product_cached(int $productId): ?array
{
    static $cache = [];
    if (array_key_exists($productId, $cache)) {
        return $cache[$productId];
    }

    $product = get_product_by_id($productId);
    $cache[$productId] = is_array($product) ? $product : null;
    return $cache[$productId];
}

function cart_validate_attributes_for_product(int $productId, array $attributes): bool
{
    if ($attributes === []) {
        return true;
    }

    if (!function_exists('get_product_options_for_product')) {
        return true;
    }

    $options = get_product_options_for_product($productId);
    if (!is_array($options) || $options === []) {
        return false;
    }

    $aliases = [];
    foreach ($options as $option) {
        $values = array_values(array_filter(array_map(
            static fn($item): string => trim((string)$item),
            (array)($option['values'] ?? [])
        ), static fn(string $item): bool => $item !== ''));
        if ($values === []) {
            continue;
        }

        $code = mb_strtolower(trim((string)($option['code'] ?? '')), 'UTF-8');
        $label = mb_strtolower(trim((string)($option['label'] ?? '')), 'UTF-8');
        if ($code !== '') {
            $aliases[$code] = $values;
        }
        if ($label !== '') {
            $aliases[$label] = $values;
        }
    }

    if ($aliases === []) {
        return false;
    }

    foreach ($attributes as $key => $value) {
        $attributeKey = mb_strtolower(trim((string)$key), 'UTF-8');
        $attributeValue = trim((string)$value);
        if ($attributeKey === '' || $attributeValue === '') {
            return false;
        }

        if (!isset($aliases[$attributeKey])) {
            return false;
        }

        $isAllowedValue = false;
        foreach ($aliases[$attributeKey] as $allowedValue) {
            if (mb_strtolower($allowedValue, 'UTF-8') === mb_strtolower($attributeValue, 'UTF-8')) {
                $isAllowedValue = true;
                break;
            }
        }
        if (!$isAllowedValue) {
            return false;
        }
    }

    return true;
}

function cart_normalize_lines(array $lines): array
{
    $normalized = [];

    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }

        $productId = (int)($line['product_id'] ?? 0);
        $variantId = isset($line['variant_id']) && $line['variant_id'] !== '' ? (int)$line['variant_id'] : null;
        $quantity = max(1, (int)($line['quantity'] ?? 1));
        $attributes = isset($line['attributes']) && is_array($line['attributes'])
            ? cart_normalize_attributes($line['attributes'])
            : [];
        if ($productId <= 0) {
            continue;
        }

        $key = cart_line_key($productId, $variantId);
        if (!isset($normalized[$key])) {
            $normalized[$key] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => 0,
                'attributes' => [],
            ];
        }

        $normalized[$key]['quantity'] += $quantity;
        if ($variantId === null && $attributes !== []) {
            $normalized[$key]['attributes'] = $attributes;
        }
    }

    return array_values($normalized);
}

function cart_get_lines(): array
{
    $raw = $_SESSION[cart_session_key()] ?? [];
    return is_array($raw) ? cart_normalize_lines($raw) : [];
}

function cart_save_lines(array $lines): array
{
    $normalized = cart_normalize_lines($lines);
    $_SESSION[cart_session_key()] = $normalized;
    return $normalized;
}

function cart_clear(): void
{
    unset($_SESSION[cart_session_key()]);
}

function cart_add_item(int $productId, ?int $variantId, int $quantity = 1, array $attributes = []): array
{
    $quantity = max(1, $quantity);
    $normalizedAttributes = cart_normalize_attributes($attributes);
    if ($variantId === null && !cart_validate_attributes_for_product($productId, $normalizedAttributes)) {
        throw new RuntimeException('Invalid product attributes.');
    }
    $stockLimit = cart_stock_limit($productId, $variantId, $normalizedAttributes);
    if ($stockLimit <= 0) {
        throw new RuntimeException('Товар отсутствует на складе.');
    }
    $lines = cart_get_lines();
    $key = cart_line_key($productId, $variantId);
    $found = false;

    foreach ($lines as &$line) {
        if (cart_line_key((int)$line['product_id'], isset($line['variant_id']) ? (int)$line['variant_id'] : null) === $key) {
            $line['quantity'] = min($stockLimit, $line['quantity'] + $quantity);
            if ($variantId === null && $normalizedAttributes !== []) {
                $line['attributes'] = $normalizedAttributes;
            }
            $found = true;
            break;
        }
    }
    unset($line);

    if (!$found) {
        $lines[] = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => min($stockLimit, $quantity),
            'attributes' => $variantId === null ? $normalizedAttributes : [],
        ];
    }

    return cart_save_lines($lines);
}

function cart_stock_limit(int $productId, ?int $variantId, array $attributes = []): int
{
    if ($variantId !== null) {
        $variant = product_fetch_variant_by_id($productId, $variantId, true);
        if (!$variant) {
            return 0;
        }
        return max(0, (int)($variant['stock_quantity'] ?? 0));
    }

    $product = cart_get_product_cached($productId);
    if (!$product) {
        return 0;
    }

    if ($attributes !== [] && function_exists('product_find_stock_for_selection')) {
        $selectionStock = product_find_stock_for_selection($productId, $attributes);
        if ($selectionStock !== null) {
            return max(0, (int)$selectionStock);
        }
    }

    return max(0, (int)($product['stock_quantity'] ?? 0));
}

function cart_update_item(int $productId, ?int $variantId, int $quantity): array
{
    $key = cart_line_key($productId, $variantId);
    $lines = [];

    foreach (cart_get_lines() as $line) {
        $lineKey = cart_line_key((int)$line['product_id'], isset($line['variant_id']) ? (int)$line['variant_id'] : null);
        if ($lineKey !== $key) {
            $lines[] = $line;
            continue;
        }

        if ($quantity > 0) {
            $lineAttributes = isset($line['attributes']) && is_array($line['attributes'])
                ? cart_normalize_attributes($line['attributes'])
                : [];
            $stockLimit = cart_stock_limit($productId, $variantId, $lineAttributes);
            if ($stockLimit > 0) {
                $line['quantity'] = min(max(1, $quantity), $stockLimit);
                $lines[] = $line;
            }
        }
    }

    return cart_save_lines($lines);
}

function cart_remove_item(int $productId, ?int $variantId): array
{
    return cart_update_item($productId, $variantId, 0);
}

function cart_build_item_state(array $line): array
{
    $productId = (int)$line['product_id'];
    $variantId = isset($line['variant_id']) && $line['variant_id'] !== null ? (int)$line['variant_id'] : null;
    $quantity = max(1, (int)$line['quantity']);

    $product = get_product_by_id($productId);
    if (!$product) {
        return [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'available' => false,
            'stock_limit' => 0,
            'title' => 'Недоступный товар',
            'variant_label' => '',
            'attributes' => [],
            'unit_price' => 0.0,
            'base_price' => 0.0,
            'discount_percent' => 0,
            'promotion_id' => null,
            'image' => APP_DEFAULT_PRODUCT_IMAGE,
            'line_total' => 0.0,
            'line_base_total' => 0.0,
        ];
    }

    $images = app_fetch_product_images($productId);
    $variant = null;
    $variantLabel = '';
    $attributes = [];
    $lineAttributes = isset($line['attributes']) && is_array($line['attributes'])
        ? cart_normalize_attributes($line['attributes'])
        : [];
    if ($variantId !== null) {
        $variant = product_fetch_variant_by_id($productId, $variantId, true);
        if (!$variant) {
            return [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'available' => false,
                'stock_limit' => 0,
                'title' => (string)$product['name'],
                'variant_label' => 'Вариант недоступен',
                'attributes' => [],
                'unit_price' => 0.0,
                'base_price' => 0.0,
                'discount_percent' => 0,
                'promotion_id' => isset($product['promotion_id']) ? (int)$product['promotion_id'] : null,
                'image' => (string)($images[0] ?? APP_DEFAULT_PRODUCT_IMAGE),
                'line_total' => 0.0,
                'line_base_total' => 0.0,
            ];
        }
        $variantLabel = (string)($variant['label'] ?? '');
        $attributes = (array)($variant['attributes'] ?? []);
    } else {
        $attributes = $lineAttributes;
    }

    $state = build_dynamic_product_state($product, $attributes, $images);
    $unitPrice = (float)$state['price'];
    $basePrice = (float)$state['base_price'];
    $stockLimit = cart_stock_limit($productId, $variantId, $attributes);
    $availableQty = min($quantity, $stockLimit);
    $effectiveQty = $availableQty > 0 ? $availableQty : $quantity;
    $displayAttributes = function_exists('product_humanize_attributes')
        ? product_humanize_attributes($productId, $attributes)
        : $attributes;

    return [
        'product_id' => $productId,
        'variant_id' => $variantId,
        'quantity' => $effectiveQty,
        'available' => (bool)$state['in_stock'] && $stockLimit > 0,
        'stock_limit' => $stockLimit,
        'title' => (string)$product['name'],
        'variant_label' => $variantLabel !== '' ? $variantLabel : (string)($state['variant_label'] ?? ''),
        'attributes' => $displayAttributes,
        'unit_price' => $unitPrice,
        'base_price' => $basePrice,
        'discount_percent' => (int)($state['discount_percent'] ?? 0),
        'promotion_id' => $state['promotion_id'] ?? null,
        'image' => (string)($state['image'] ?? ($images[0] ?? APP_DEFAULT_PRODUCT_IMAGE)),
        'line_total' => round($unitPrice * $effectiveQty, 2),
        'line_base_total' => round($basePrice * $effectiveQty, 2),
    ];
}

function cart_get_state(): array
{
    $items = [];
    $badgeCount = 0;
    $totalQuantity = 0;
    $subtotal = 0.0;
    $baseSubtotal = 0.0;

    foreach (cart_get_lines() as $line) {
        $state = cart_build_item_state($line);
        $items[] = $state;
        $badgeCount += (int)$state['quantity'];

        if ($state['available']) {
            $totalQuantity += (int)$state['quantity'];
            $subtotal += (float)$state['line_total'];
            $baseSubtotal += (float)$state['line_base_total'];
        }
    }

    return [
        'items' => $items,
        'badge_count' => $badgeCount,
        'total_quantity' => $totalQuantity,
        'subtotal' => round($subtotal, 2),
        'base_subtotal' => round($baseSubtotal, 2),
        'discount_total' => round(max(0, $baseSubtotal - $subtotal), 2),
        'subtotal_formatted' => format_price($subtotal),
        'base_subtotal_formatted' => format_price($baseSubtotal),
    ];
}

function cart_require_json_input(): array
{
    $payload = json_decode((string)file_get_contents('php://input'), true);
    return is_array($payload) ? $payload : [];
}
