<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// 1. Защита от GET-запросов
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php', true, 303);
    exit;
}

try {
    // 2. Проверка токена безопасности
    admin_validate_csrf_or_fail();

    // 3. Завершение сессии
    admin_logout();

    // 4. Редирект на страницу логина
    header('Location: /pages/login.php?logout=success', true, 303);
    exit;

} catch (Throwable $e) {
    // В случае ошибки (например, неверный CSRF) пишется в лог и адресуем на главную
    admin_log_error('logout_error', $e);
    header('Location: /index.php', true, 303);
    exit;
}