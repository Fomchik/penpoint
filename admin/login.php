<?php

declare(strict_types=1);

$redirectTo = (string)($_GET['redirect'] ?? '/admin/index.php');
if (strpos($redirectTo, '/admin/') !== 0) {
    $redirectTo = '/admin/index.php';
}

header('Location: /pages/login.php?redirect=' . rawurlencode($redirectTo), true, 302);
exit;
