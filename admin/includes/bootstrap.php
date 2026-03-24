<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/security.php';
app_start_session();

require_once __DIR__ . '/../../includes/config.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);

const ADMIN_BASE_PATH = '/admin';
const ADMIN_LOGIN_PATH = '/admin/login.php';
const ADMIN_DASHBOARD_PATH = '/admin/index.php';

function admin_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function admin_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function admin_set_flash(string $type, string $message): void
{
    $_SESSION['admin_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function admin_get_flash(): ?array
{
    if (!isset($_SESSION['admin_flash']) || !is_array($_SESSION['admin_flash'])) {
        return null;
    }

    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);

    return $flash;
}

function admin_safe_int($value, int $default = 0): int
{
    if (is_numeric($value)) {
        return (int)$value;
    }

    return $default;
}

function admin_log_error(string $context, Throwable $exception): void
{
    error_log('[ADMIN][' . $context . '] ' . $exception->getMessage());
}

function admin_get_uploads_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

function admin_handle_image_upload(array $file): ?string
{
    if (!isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Ошибка загрузки файла.');
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
        throw new RuntimeException('Некорректный загруженный файл.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file((string)$file['tmp_name']);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Допустимы только JPG, PNG и WEBP.');
    }

    $ext = $allowed[$mime];
    $name = 'product_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destination = admin_get_uploads_dir() . '/' . $name;

    if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
        throw new RuntimeException('Не удалось сохранить изображение.');
    }

    return '/uploads/' . $name;
}

function admin_path_with_redirect(string $path): string
{
    return ADMIN_LOGIN_PATH . '?redirect=' . rawurlencode($path);
}
