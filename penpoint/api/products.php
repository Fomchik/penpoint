<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

$product_ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];

if (empty($product_ids)) {
    echo json_encode([]);
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.price
        FROM products p
        WHERE p.id IN ($placeholders) AND p.is_active = 1
    ");
    $stmt->execute($product_ids);
    $products = $stmt->fetchAll() ?: [];
    
    // Add images and format prices
    foreach ($products as &$product) {
        $product['image'] = get_product_image($product['id']);
        $product['price'] = format_price((float)$product['price']);
    }
    unset($product); // Break reference
    
    echo json_encode($products);
} catch (PDOException $e) {
    error_log('API error: ' . $e->getMessage());
    echo json_encode([]);
}
