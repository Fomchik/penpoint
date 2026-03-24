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
    $sessionToken = (string)($_SESSION['admin_csrf_token'] ?? '');
    $requestToken = (string)($_POST['csrf_token'] ?? '');

    if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        http_response_code(403);
        exit('CSRF validation failed.');
    }
}

