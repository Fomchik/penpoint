<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/security.php';
app_start_session();

require_once __DIR__ . '/../../includes/config.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);

const ADMIN_BASE_PATH = '/admin';
const ADMIN_LOGIN_PATH = '/pages/login.php';
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
    return is_numeric($value) ? (int)$value : $default;
}

function admin_log_error(string $context, Throwable $exception): void
{
    error_log('[ADMIN][' . $context . '] ' . $exception->getMessage());
}

function admin_get_uploads_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
    }

    return $dir;
}

function admin_public_path_to_absolute(string $publicPath): string
{
    $normalized = '/' . ltrim(str_replace('\\', '/', trim($publicPath)), '/');
    return dirname(__DIR__, 2) . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
}

function admin_remove_dir_recursive(string $dir): void
{
    $dir = rtrim($dir, '/\\');
    if ($dir === '' || !is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if (!is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            admin_remove_dir_recursive($path);
        } elseif (is_file($path)) {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

function admin_slugify(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    if (function_exists('transliterator_transliterate')) {
        $value = (string)transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
    }
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?: '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'misc';
}

function admin_image_base_name(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'image';
    }

    $name = preg_replace('/\.[a-z0-9]+$/i', '', $name) ?? $name;
    $name = preg_replace('/[^a-zA-Z0-9\-_]+/u', '_', $name) ?? $name;
    $name = trim($name, '_-');
    if ($name === '') {
        $name = 'image';
    }

    return $name;
}

function admin_public_upload_dir(string $group, string $subPath = ''): array
{
    $group = trim($group, '/');
    $subPath = trim($subPath, '/');
    $relative = '/assets/' . $group . ($subPath !== '' ? '/' . $subPath : '');
    $absolute = dirname(__DIR__, 2) . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (!is_dir($absolute) && !mkdir($absolute, 0755, true) && !is_dir($absolute)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $absolute));
    }

    return [$absolute, $relative];
}

function admin_remove_named_image_variants(string $dir, string $baseName, array $extensions = ['jpg', 'png', 'webp']): void
{
    if (!is_dir($dir)) {
        return;
    }

    foreach ($extensions as $ext) {
        $candidate = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $baseName . '.' . strtolower(trim((string)$ext));
        if (is_file($candidate)) {
            @unlink($candidate);
        }
    }
}

function admin_ensure_category_image_dirs(PDO $pdo): void
{
    try {
        $stmt = $pdo->query("SELECT slug FROM categories WHERE slug IS NOT NULL AND slug <> ''");
        $slugs = $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
        foreach ($slugs as $slugRaw) {
            $slug = trim((string)$slugRaw);
            if ($slug === '') {
                continue;
            }
            admin_public_upload_dir('product_images', $slug);
        }
    } catch (Throwable $e) {
        admin_log_error('ensure_category_image_dirs', $e);
    }
}

function admin_handle_image_upload(array $file, array $options = []): ?string
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

    if (@getimagesize((string)$file['tmp_name']) === false) {
        throw new RuntimeException('Файл не является валидным изображением.');
    }

    $extension = (string)$allowed[$mime];
    $target = (string)($options['target'] ?? 'images');
    $subPath = trim((string)($options['sub_path'] ?? ''), '/');
    $prefix = admin_slugify((string)($options['prefix'] ?? 'image'));
    $fileNameOption = trim((string)($options['file_name'] ?? ''));
    $baseName = $fileNameOption !== ''
        ? admin_image_base_name($fileNameOption)
        : admin_image_base_name($prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)));
    $fileName = $baseName . '.' . $extension;

    if ($target === 'uploads') {
        $uploadDir = admin_get_uploads_dir();
        admin_remove_named_image_variants($uploadDir, $baseName);
        $destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
        if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
            throw new RuntimeException('Не удалось сохранить изображение.');
        }
        return '/uploads/' . $fileName;
    }

    $basePath = trim((string)($options['base_path'] ?? ''), '/');
    if ($basePath === '') {
        $basePath = ($target === 'banners') ? 'banners' : 'product_images';
    }

    [$baseDir, $basePublicPath] = admin_public_upload_dir($basePath, $subPath);
    admin_remove_named_image_variants($baseDir, $baseName);
    $destination = $baseDir . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
        throw new RuntimeException('Не удалось сохранить изображение.');
    }

    return rtrim($basePublicPath, '/') . '/' . $fileName;
}

function admin_path_with_redirect(string $path): string
{
    return ADMIN_LOGIN_PATH . '?redirect=' . rawurlencode($path);
}
