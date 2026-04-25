<?php

declare(strict_types=1);

function app_feedback_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS feedback (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(150) NOT NULL,
                phone VARCHAR(30) NULL DEFAULT NULL,
                subject VARCHAR(255) NULL DEFAULT NULL,
                message TEXT NOT NULL,
                status ENUM('new','in_progress','done') NOT NULL DEFAULT 'new',
                status_updated_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_feedback_created (created_at),
                KEY idx_feedback_status (status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $columns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM feedback') ?: [] as $column) {
            $columns[(string)$column['Field']] = true;
        }

        if (!isset($columns['phone'])) {
            $pdo->exec("ALTER TABLE feedback ADD COLUMN phone VARCHAR(30) NULL DEFAULT NULL AFTER email");
        }
        if (!isset($columns['status'])) {
            $pdo->exec("ALTER TABLE feedback ADD COLUMN status ENUM('new','in_progress','done') NOT NULL DEFAULT 'new' AFTER message");
        }
        if (!isset($columns['status_updated_at'])) {
            $pdo->exec("ALTER TABLE feedback ADD COLUMN status_updated_at TIMESTAMP NULL DEFAULT NULL AFTER status");
        }
        if (!isset($columns['created_at'])) {
            $pdo->exec("ALTER TABLE feedback ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP AFTER status_updated_at");
        }
    } catch (Throwable $e) {
        error_log('Feedback schema ensure error: ' . $e->getMessage());
    }
}

function app_feedback_insert(PDO $pdo, array $payload): int
{
    app_feedback_ensure_schema($pdo);

    $name = trim((string)($payload['name'] ?? ''));
    $email = trim((string)($payload['email'] ?? ''));
    $phone = trim((string)($payload['phone'] ?? ''));
    $subject = trim((string)($payload['subject'] ?? ''));
    $message = trim((string)($payload['message'] ?? ''));
    $status = trim((string)($payload['status'] ?? 'new'));

    if ($name === '' || $email === '' || $message === '') {
        throw new RuntimeException('Feedback payload is incomplete.');
    }
    if (mb_strlen($name, 'UTF-8') > 100) {
        throw new RuntimeException('Feedback name is too long.');
    }
    if (mb_strlen($email, 'UTF-8') > 150) {
        throw new RuntimeException('Feedback email is too long.');
    }
    if ($phone !== '' && mb_strlen($phone, 'UTF-8') > 30) {
        throw new RuntimeException('Feedback phone is too long.');
    }
    if ($subject !== '' && mb_strlen($subject, 'UTF-8') > 255) {
        throw new RuntimeException('Feedback subject is too long.');
    }
    if (mb_strlen($message, 'UTF-8') > 5000) {
        throw new RuntimeException('Feedback message is too long.');
    }

    if (!in_array($status, ['new', 'in_progress', 'done'], true)) {
        $status = 'new';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO feedback (name, email, phone, subject, message, status, status_updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        mb_substr($name, 0, 100, 'UTF-8'),
        mb_substr($email, 0, 150, 'UTF-8'),
        $phone !== '' ? mb_substr($phone, 0, 30, 'UTF-8') : null,
        $subject !== '' ? mb_substr($subject, 0, 255, 'UTF-8') : null,
        $message,
        $status,
        date('Y-m-d H:i:s'),
    ]);

    return (int)$pdo->lastInsertId();
}

function app_feedback_update_status(PDO $pdo, int $feedbackId, string $status): bool
{
    app_feedback_ensure_schema($pdo);

    $status = trim($status);
    if (!in_array($status, ['new', 'in_progress', 'done'], true)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE feedback
             SET status = ?, status_updated_at = NOW()
             WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$status, $feedbackId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        error_log('Feedback status update error: ' . $e->getMessage());
        return false;
    }
}
