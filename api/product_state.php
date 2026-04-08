<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/product_options.php';

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_request'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$productId = isset($payload['product_id']) ? (int)$payload['product_id'] : 0;
$selections = isset($payload['selections']) && is_array($payload['selections']) ? $payload['selections'] : [];
if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_product_id'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$product = get_product_by_id($productId);
if (!$product) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$images = app_fetch_product_images($productId);

$state = build_dynamic_product_state($product, $selections, $images);
$state['has_variants'] = product_has_real_variants($productId);
echo json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
