<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

$product_ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
$product_ids = array_values(array_filter(array_map('intval', $product_ids), function ($id) {
    return $id > 0;
}));

if (empty($product_ids)) {
    echo json_encode([]);
    exit;
}

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

    echo json_encode($products);
} catch (PDOException $e) {
    error_log('API error: ' . $e->getMessage());
    echo json_encode([]);
}
