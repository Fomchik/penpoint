<?php

declare(strict_types=1);

if (!headers_sent()) {
    ini_set('display_errors', '0');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
}

function app_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (headers_sent()) {
        return;
    }

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
    }

    session_start();
}

function app_csrf_token(): string
{
    if (empty($_SESSION['app_csrf_token']) || !is_string($_SESSION['app_csrf_token'])) {
        $_SESSION['app_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['app_csrf_token'];
}

function app_rotate_csrf_token(): string
{
    $_SESSION['app_csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['app_csrf_token'];
}

function app_emit_csrf_token_header(?string $token = null): void
{
    if (headers_sent()) {
        return;
    }

    $value = $token !== null && $token !== '' ? $token : app_csrf_token();
    header('X-CSRF-Token: ' . $value);
}

function app_csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function app_request_csrf_token(): string
{
    $token = (string)($_POST['csrf_token'] ?? '');
    if ($token !== '') {
        return $token;
    }

    $headers = [
        'HTTP_X_CSRF_TOKEN',
        'HTTP_X_CSRF',
    ];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            return (string)$_SERVER[$header];
        }
    }

    return '';
}

function app_validate_csrf_or_fail(): void
{
    $sessionToken = (string)($_SESSION['app_csrf_token'] ?? '');
    $requestToken = app_request_csrf_token();

    if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
        http_response_code(403);
        exit('CSRF validation failed.');
    }

    $newToken = app_rotate_csrf_token();
    app_emit_csrf_token_header($newToken);
}
