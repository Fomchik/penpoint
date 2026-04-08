<?php

declare(strict_types=1);

require_once __DIR__ . '/mail.php';

function auth_get_client_ip(): string
{
    return trim((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
}

function auth_validate_password_strength(string $password): bool
{
    if (mb_strlen($password) < 8) {
        return false;
    }
    if (!preg_match('/\p{Lu}/u', $password)) {
        return false;
    }
    if (!preg_match('/\d/u', $password)) {
        return false;
    }
    if (!preg_match('/[^\p{L}\p{N}]/u', $password)) {
        return false;
    }

    return true;
}

function auth_ensure_security_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS auth_rate_limits (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                action_key VARCHAR(64) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_action_ip_time (action_key, ip_address, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS auth_audit_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED DEFAULT NULL,
                email VARCHAR(190) DEFAULT NULL,
                ip_address VARCHAR(45) NOT NULL,
                event_type VARCHAR(64) NOT NULL,
                success TINYINT(1) NOT NULL DEFAULT 0,
                details TEXT,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_event_time (event_type, created_at),
                KEY idx_user_time (user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_token_hash (token_hash),
                KEY idx_user_expire (user_id, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS email_verification_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_token_hash (token_hash),
                KEY idx_user_expire (user_id, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_verified'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0");
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified_at'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN email_verified_at DATETIME DEFAULT NULL");
        }
    } catch (Throwable $e) {
        error_log('Auth schema ensure error: ' . $e->getMessage());
    }
}

function auth_rate_limit_allow(PDO $pdo, string $actionKey, string $ipAddress, int $limit = 5, int $windowSeconds = 60): bool
{
    auth_ensure_security_schema($pdo);

    try {
        $stmtCleanup = $pdo->prepare('DELETE FROM auth_rate_limits WHERE created_at < (NOW() - INTERVAL ? SECOND)');
        $stmtCleanup->execute([$windowSeconds]);

        $stmtCount = $pdo->prepare(
            'SELECT COUNT(*) FROM auth_rate_limits
             WHERE action_key = ? AND ip_address = ? AND created_at >= (NOW() - INTERVAL ? SECOND)'
        );
        $stmtCount->execute([$actionKey, $ipAddress, $windowSeconds]);
        $count = (int)$stmtCount->fetchColumn();

        if ($count >= $limit) {
            return false;
        }

        $stmtInsert = $pdo->prepare('INSERT INTO auth_rate_limits (action_key, ip_address) VALUES (?, ?)');
        $stmtInsert->execute([$actionKey, $ipAddress]);

        return true;
    } catch (Throwable $e) {
        error_log('Rate limit error: ' . $e->getMessage());
        return true;
    }
}

function auth_audit_log(PDO $pdo, string $eventType, bool $success, ?int $userId = null, ?string $email = null, ?string $details = null): void
{
    auth_ensure_security_schema($pdo);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO auth_audit_logs (user_id, email, ip_address, event_type, success, details)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $email,
            auth_get_client_ip(),
            $eventType,
            $success ? 1 : 0,
            $details,
        ]);
    } catch (Throwable $e) {
        error_log('Auth audit log error: ' . $e->getMessage());
    }
}

function auth_base_url(): string
{
    $fromEnv = trim((string)(getenv('APP_URL') ?: ''));
    if ($fromEnv !== '') {
        return rtrim($fromEnv, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host;
}

function auth_create_token(PDO $pdo, string $tableName, int $userId, int $ttlSeconds = 3600): string
{
    auth_ensure_security_schema($pdo);

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare("INSERT INTO {$tableName} (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))");
    $stmt->execute([$userId, $tokenHash, $ttlSeconds]);

    return $token;
}

function auth_consume_token(PDO $pdo, string $tableName, string $token): ?int
{
    auth_ensure_security_schema($pdo);

    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        "SELECT id, user_id FROM {$tableName}
         WHERE token_hash = ? AND used_at IS NULL AND expires_at >= NOW()
         LIMIT 1"
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();

    if (!is_array($row)) {
        return null;
    }

    $update = $pdo->prepare("UPDATE {$tableName} SET used_at = NOW() WHERE id = ? AND used_at IS NULL LIMIT 1");
    $update->execute([(int)$row['id']]);

    if ($update->rowCount() !== 1) {
        return null;
    }

    return (int)$row['user_id'];
}

function auth_send_link_email(string $to, string $subject, string $title, string $link): bool
{
    $safeTo = filter_var($to, FILTER_SANITIZE_EMAIL);
    if (!$safeTo) {
        error_log('Auth mail send error: invalid recipient email.');
        return false;
    }

    $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $htmlBody = '<!doctype html><html lang="ru"><head><meta charset="UTF-8"><title>' . $safeTitle . '</title></head><body style="margin:0;padding:24px;background:#f5f6f8;font-family:Arial,sans-serif;">'
        . '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;">'
        . '<tr><td style="padding:28px 24px;color:#1f2937;font-size:16px;line-height:1.5;">'
        . '<h2 style="margin:0 0 12px;font-size:22px;line-height:1.3;">' . $safeTitle . '</h2>'
        . '<p style="margin:0 0 20px;">Для продолжения нажмите кнопку ниже:</p>'
        . '<p style="margin:0 0 20px;"><a href="' . $safeLink . '" style="display:inline-block;padding:12px 20px;background:#d55204;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;">Перейти по ссылке</a></p>'
        . '<p style="margin:0;color:#6b7280;font-size:14px;">Если кнопка не работает, откройте ссылку вручную:<br><a href="' . $safeLink . '" style="color:#d55204;word-break:break-all;">' . $safeLink . '</a></p>'
        . '</td></tr></table></body></html>';

    $plainBody = $title . "\n\n" . $link . "\n\nСсылка действует ограниченное время и может быть использована только один раз.";

    return app_send_smtp_mail($safeTo, $subject, $htmlBody, $plainBody);
}
