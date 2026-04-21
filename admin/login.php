<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

if (admin_is_authenticated()) {
    header('Location: /admin/index.php', true, 302);
    exit;
}

$redirectTo = (string)($_GET['redirect'] ?? '/admin/index.php');

if (!str_starts_with($redirectTo, '/admin/')) {
    $redirectTo = '/admin/index.php';
}

header('Location: /pages/login.php?redirect=' . rawurlencode($redirectTo), true, 302);
exit;