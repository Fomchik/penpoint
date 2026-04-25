<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/config.php';

function products_parse_ids_payload(): array
{
    $ids = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawBody = (string)file_get_contents('php://input');
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded) && isset($decoded['ids']) && is_array($decoded['ids'])) {
            $ids = $decoded['ids'];
        } elseif (isset($_POST['ids']) && is_array($_POST['ids'])) {
            $ids = $_POST['ids'];
        }
    }

    if ($ids === [] && isset($_GET['ids'])) {
        $ids = explode(',', (string)$_GET['ids']);
    }

    return array_values(array_filter(array_map('intval', $ids), function ($id) {
        return $id > 0;
    }));
}

$product_ids = products_parse_ids_payload();

if (!empty($product_ids)) {
    $product_ids = array_values(array_unique($product_ids));
}

if (count($product_ids) > 1000) {
    $product_ids = array_slice($product_ids, 0, 1000);
}

if (empty($product_ids)) {
    echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$product_ids = array_values(array_filter($product_ids, function ($id) {
    return $id > 0;
}));

try {
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.name,
            p.final_price AS price,
            p.base_price AS price_old,
            p.discount_percent
        FROM v_product_pricing p
        WHERE p.id IN ($placeholders) AND p.is_active = 1
    ");
    $stmt->execute($product_ids);
    $products = $stmt->fetchAll() ?: [];

    foreach ($products as &$product) {
        $product['image'] = get_product_image($product['id']);
        $product['price_raw'] = (float)$product['price'];
        $product['old_price_raw'] = (float)($product['price_old'] ?? $product['price']);
        $product['discount_percent'] = (int)($product['discount_percent'] ?? 0);
        $product['price_formatted'] = format_price($product['price_raw']);
        $product['old_price_formatted'] = format_price($product['old_price_raw']);
    }
    unset($product);

    if ($products === []) {
        http_response_code(404);
        echo json_encode(['message' => 'Товары не найдены'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    error_log('API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Внутренняя ошибка сервера'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
