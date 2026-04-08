<?php

declare(strict_types=1);

function app_ensure_order_payment_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM orders') ?: [] as $row) {
        $name = (string)($row['Field'] ?? '');
        if ($name !== '') {
            $columns[$name] = true;
        }
    }

    if (!isset($columns['payment_status'])) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_status ENUM('not_required','pending_payment','paid','failed') NOT NULL DEFAULT 'not_required' AFTER address");
    }
    if (!isset($columns['payment_id'])) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_id VARCHAR(128) NULL DEFAULT NULL AFTER payment_status");
    }
    if (!isset($columns['paid_at'])) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN paid_at TIMESTAMP NULL DEFAULT NULL AFTER payment_id');
    }
}
