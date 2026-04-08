<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
app_start_session();
require_once __DIR__ . '/../includes/cart.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

app_validate_csrf_or_fail();
$payload = cart_require_json_input();

$productId = (int)($payload['product_id'] ?? 0);
$variantId = array_key_exists('variant_id', $payload) && $payload['variant_id'] !== null && $payload['variant_id'] !== '' ? (int)$payload['variant_id'] : null;
$quantity = max(1, (int)($payload['quantity'] ?? 1));

if ($productId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Некорректный товар.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!get_product_by_id($productId)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Товар не найден.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($variantId !== null && !product_fetch_variant_by_id($productId, $variantId, true)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Выбранный вариант недоступен.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

cart_add_item($productId, $variantId, $quantity);
echo json_encode(['success' => true, 'state' => cart_get_state()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
