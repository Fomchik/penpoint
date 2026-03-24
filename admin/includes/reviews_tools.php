<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function admin_reviews_visibility_available(): bool
{
    global $pdo;

    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'reviews'
               AND COLUMN_NAME = 'is_published'"
        );
        $stmt->execute();
        $exists = (int)($stmt->fetch()['cnt'] ?? 0) > 0;
        if ($exists) {
            return true;
        }

        $pdo->exec("ALTER TABLE reviews ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 1");
        return true;
    } catch (Throwable $e) {
        admin_log_error('admin_reviews_visibility_available', $e);
        return false;
    }
}

