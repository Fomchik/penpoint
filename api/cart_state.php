<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/security.php';
app_start_session();
require_once __DIR__ . '/../includes/cart.php';

header('Content-Type: application/json; charset=utf-8');
echo json_encode(cart_get_state(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
