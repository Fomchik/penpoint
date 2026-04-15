<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
app_start_session();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/reviews.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

app_validate_csrf_or_fail();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
$productId = (int)($payload['product_id'] ?? 0);
$rating = (int)($payload['rating'] ?? 0);
$comment = trim((string)($payload['comment'] ?? ''));
$userId = (int)$_SESSION['user_id'];

if ($productId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Некорректный товар.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($rating < 1 || $rating > 5) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Укажите оценку от 1 до 5.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($comment === '' || mb_strlen($comment, 'UTF-8') < 5) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Комментарий должен содержать не менее 5 символов.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!reviews_can_user_submit($pdo, $productId, $userId)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Оставить отзыв можно только после получения заказа с этим товаром и только один раз.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $review = reviews_create($pdo, $productId, $userId, $rating, $comment);
    echo json_encode(['success' => true, 'review' => $review], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Review submit error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Не удалось сохранить отзыв.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
