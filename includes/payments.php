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
    if (!isset($columns['payment_idempotence_key'])) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_idempotence_key VARCHAR(64) NULL DEFAULT NULL AFTER payment_id");
    }
    if (!isset($columns['paid_at'])) {
        $pdo->exec('ALTER TABLE orders ADD COLUMN paid_at TIMESTAMP NULL DEFAULT NULL AFTER payment_idempotence_key');
    }
}

function app_update_order_payment_status(PDO $pdo, int $orderId, string $newStatus, ?string $paymentId = null): bool
{
    app_ensure_order_payment_schema($pdo);

    $allowed = ['not_required', 'pending_payment', 'paid', 'failed'];
    if ($orderId <= 0 || !in_array($newStatus, $allowed, true)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE orders
             SET payment_status = ?, payment_id = COALESCE(?, payment_id), paid_at = CASE WHEN ? = "paid" THEN NOW() ELSE paid_at END
             WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$newStatus, $paymentId, $newStatus, $orderId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        error_log('Payment status update error: ' . $e->getMessage());
        return false;
    }
}

function payment_can_be_cancelled(array $order): bool
{
    $status = (string)($order['payment_status'] ?? '');
    return in_array($status, ['pending_payment', 'failed'], true);
}
