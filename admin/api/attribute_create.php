<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/product_attribute_catalog.php';

admin_require_auth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

admin_validate_csrf_or_fail();

$name = trim((string)($_POST['name'] ?? ''));

if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Название атрибута не может быть пустым'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $attribute = admin_attribute_catalog_add($pdo, $name);
    echo json_encode(['success' => true, 'attribute' => $attribute], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') {
        $stmt = $pdo->prepare('SELECT id, name FROM attributes WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->execute([$name]);
        $existing = $stmt->fetch();
        if (is_array($existing)) {
            echo json_encode([
                'success' => true,
                'attribute' => [
                    'id' => (int)$existing['id'],
                    'name' => (string)$existing['name'],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
