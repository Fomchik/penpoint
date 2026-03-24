<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_redirect('/index.php');
}

admin_validate_csrf_or_fail();
admin_logout();
admin_redirect('/index.php');
