<?php
require_once __DIR__ . '/../includes/security.php';
app_start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/account.php');
    exit;
}

app_validate_csrf_or_fail();

$_SESSION = [];
session_destroy();
header('Location: /index.php');
exit;
