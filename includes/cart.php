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
        if ($productId <= 0) {
            continue;
        }

        $key = cart_line_key($productId, $variantId);
        if (!isset($normalized[$key])) {
            $normalized[$key] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => 0,
            ];
        }

        $normalized[$key]['quantity'] += $quantity;
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

function cart_add_item(int $productId, ?int $variantId, int $quantity = 1): array
{
    $quantity = max(1, $quantity);
    $lines = cart_get_lines();
    $key = cart_line_key($productId, $variantId);
    $found = false;

    foreach ($lines as &$line) {
        if (cart_line_key((int)$line['product_id'], isset($line['variant_id']) ? (int)$line['variant_id'] : null) === $key) {
            $line['quantity'] += $quantity;
            $found = true;
            break;
        }
    }
    unset($line);

    if (!$found) {
        $lines[] = [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ];
    }

    return cart_save_lines($lines);
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
            $line['quantity'] = max(1, $quantity);
            $lines[] = $line;
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
    if ($variantId !== null) {
        $variant = product_fetch_variant_by_id($productId, $variantId, true);
        if (!$variant) {
            return [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'available' => false,
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
    }

    $state = build_dynamic_product_state($product, $attributes, $images);
    $unitPrice = (float)$state['price'];
    $basePrice = (float)$state['base_price'];

    return [
        'product_id' => $productId,
        'variant_id' => $variantId,
        'quantity' => $quantity,
        'available' => (bool)$state['in_stock'],
        'title' => (string)$product['name'],
        'variant_label' => $variantLabel !== '' ? $variantLabel : (string)($state['variant_label'] ?? ''),
        'attributes' => $attributes,
        'unit_price' => $unitPrice,
        'base_price' => $basePrice,
        'discount_percent' => (int)($state['discount_percent'] ?? 0),
        'promotion_id' => $state['promotion_id'] ?? null,
        'image' => (string)($state['image'] ?? ($images[0] ?? APP_DEFAULT_PRODUCT_IMAGE)),
        'line_total' => round($unitPrice * $quantity, 2),
        'line_base_total' => round($basePrice * $quantity, 2),
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
