<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf_token']) || !is_string($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

function admin_csrf_input(): string
{
    $token = admin_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . admin_e($token) . '">';
}

function admin_validate_csrf_or_fail(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
        return;
    }

    $sessionToken = (string)($_SESSION['admin_csrf_token'] ?? '');
    
    $requestToken = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        admin_log_error('CSRF_VALIDATION_FAILED', new RuntimeException("Method: $method"));
        
        // Если это AJAX-запрос, отдаем JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Ошибка безопасности (CSRF).']);
            exit;
        }

        // Для обычных форм показываем ошибку и останавливаем выполнение
        http_response_code(403);
        exit('Ошибка безопасности: CSRF токен невалиден или отсутствует. Вернитесь назад и обновите страницу.');
    }
}