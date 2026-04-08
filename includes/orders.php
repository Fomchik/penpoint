<?php

declare(strict_types=1);

require_once __DIR__ . '/product_options.php';
require_once __DIR__ . '/payments.php';

function app_ensure_order_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    product_options_ensure_schema($pdo);
    app_ensure_order_payment_schema($pdo);

    try {
        $columns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM orders') ?: [] as $column) {
            $columns[(string)$column['Field']] = true;
        }

        if (!isset($columns['customer_name'])) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN customer_name VARCHAR(190) NULL DEFAULT NULL AFTER user_id");
        }
        if (!isset($columns['customer_phone'])) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN customer_phone VARCHAR(50) NULL DEFAULT NULL AFTER customer_name");
        }
        if (!isset($columns['customer_email'])) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN customer_email VARCHAR(190) NULL DEFAULT NULL AFTER customer_phone");
        }
        if (!isset($columns['payment_method'])) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(32) NOT NULL DEFAULT 'online' AFTER delivery_method_id");
        }
    } catch (Throwable $e) {
        error_log('Order schema ensure error: ' . $e->getMessage());
    }

    app_ensure_order_statuses($pdo);
}

function app_ensure_order_statuses(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $statuses = [
        1 => 'Новый',
        2 => 'В обработке',
        3 => 'Отправлен',
        4 => 'Доставлен',
        5 => 'Отменён',
        6 => 'В пути',
    ];

    try {
        $stmt = $pdo->prepare('INSERT INTO order_statuses (id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)');
        foreach ($statuses as $id => $name) {
            $stmt->execute([$id, $name]);
        }
    } catch (Throwable $e) {
        error_log('Order statuses ensure error: ' . $e->getMessage());
    }
}
