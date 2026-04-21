<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function admin_reviews_visibility_available(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM information_schema.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'reviews' 
             AND COLUMN_NAME = 'is_published' 
             LIMIT 1"
        );
        $stmt->execute();

        if ($stmt->fetch()) {
            return true;
        }

        $pdo->exec("ALTER TABLE reviews ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 1");
        return true;
    } catch (Throwable $e) {
        if (function_exists('admin_log_error')) {
            admin_log_error('admin_reviews_visibility_available', $e);
        }
        return false;
    }
}

function admin_review_toggle_visibility(PDO $pdo, int $reviewId, int $status): bool
{
    if (!admin_reviews_visibility_available($pdo)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("UPDATE reviews SET is_published = ? WHERE id = ? LIMIT 1");
        $stmt->execute([$status > 0 ? 1 : 0, $reviewId]);

        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        if (function_exists('admin_log_error')) {
            admin_log_error('admin_review_toggle_visibility', $e);
        }
        return false;
    }
}

function admin_review_delete(PDO $pdo, int $reviewId): bool
{
    try {
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ? LIMIT 1");
        $stmt->execute([$reviewId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        if (function_exists('admin_log_error')) {
            admin_log_error('admin_review_delete', $e);
        }
        return false;
    }
}
