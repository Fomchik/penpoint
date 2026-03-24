<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function admin_current_user(): ?array
{
    global $pdo;

    $sessionAdminId = admin_safe_int($_SESSION['admin_user_id'] ?? 0);
    if ($sessionAdminId <= 0) {
        return null;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND r.name = ? LIMIT 1'
        );
        $stmt->execute([$sessionAdminId, 'admin']);
        $user = $stmt->fetch();

        return is_array($user) ? $user : null;
    } catch (Throwable $e) {
        admin_log_error('admin_current_user', $e);
        return null;
    }
}

function admin_is_authenticated(): bool
{
    return admin_current_user() !== null;
}

function admin_require_auth(): array
{
    $user = admin_current_user();
    if (!$user) {
        admin_redirect(admin_path_with_redirect($_SERVER['REQUEST_URI'] ?? ADMIN_DASHBOARD_PATH));
    }

    return $user;
}

function admin_require_guest(): void
{
    if (admin_is_authenticated()) {
        admin_redirect(ADMIN_DASHBOARD_PATH);
    }
}

function admin_authenticate(string $email, string $password): bool
{
    global $pdo;

    try {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, u.password_hash
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? AND r.name = ? LIMIT 1'
        );
        $stmt->execute([$email, 'admin']);
        $user = $stmt->fetch();

        if (!is_array($user)) {
            return false;
        }

        if (!password_verify($password, (string)$user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = (int)$user['id'];
        $_SESSION['admin_user_name'] = (string)$user['name'];
        $_SESSION['admin_user_email'] = (string)$user['email'];

        return true;
    } catch (Throwable $e) {
        admin_log_error('admin_authenticate', $e);
        return false;
    }
}

function admin_logout(): void
{
    unset(
        $_SESSION['admin_user_id'],
        $_SESSION['admin_user_name'],
        $_SESSION['admin_user_email'],
        $_SESSION['admin_csrf_token']
    );
}
