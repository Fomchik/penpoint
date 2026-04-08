<?php

declare(strict_types=1);

if (!function_exists('app_detect_base_path')) {
    function app_detect_base_path(): string
    {
        $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? (string)$_SERVER['DOCUMENT_ROOT'] : '';
        $appRoot = dirname(__DIR__);

        if ($documentRoot !== '') {
            $realDocumentRoot = realpath($documentRoot);
            $realAppRoot = realpath($appRoot);

            if ($realDocumentRoot !== false && $realAppRoot !== false) {
                $normalizedDocumentRoot = str_replace('\\', '/', $realDocumentRoot);
                $normalizedAppRoot = str_replace('\\', '/', $realAppRoot);

                if (strpos($normalizedAppRoot, $normalizedDocumentRoot) === 0) {
                    $relative = substr($normalizedAppRoot, strlen($normalizedDocumentRoot));
                    $relative = '/' . trim((string)$relative, '/');
                    return $relative === '/' ? '' : $relative;
                }
            }
        }

        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']) : '';
        if ($scriptName !== '') {
            $scriptDir = str_replace('\\', '/', dirname($scriptName));
            $scriptDir = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');

            if (preg_match('#/(pages|admin)$#', $scriptDir)) {
                $base = preg_replace('#/(pages|admin)$#', '', $scriptDir);
                return $base === '/' ? '' : (string)$base;
            }

            if (substr($scriptName, -10) === '/index.php') {
                $base = substr($scriptName, 0, -10);
                return $base === '/' ? '' : rtrim((string)$base, '/');
            }
        }

        return '';
    }
}

if (!defined('BASE_PATH')) {
    define('BASE_PATH', app_detect_base_path());
}

if (!defined('APP_ROOT_PATH')) {
    define('APP_ROOT_PATH', dirname(__DIR__));
}

if (!defined('APP_UPLOADS_DIR')) {
    define('APP_UPLOADS_DIR', APP_ROOT_PATH . '/uploads');
}

if (!defined('APP_UPLOADS_URL')) {
    define('APP_UPLOADS_URL', BASE_PATH . '/uploads');
}

if (!defined('APP_DEFAULT_PRODUCT_IMAGE')) {
    define('APP_DEFAULT_PRODUCT_IMAGE', BASE_PATH . '/assets/product_images/default.png');
}

if (!defined('CONTACT_EMAIL')) {
    define('CONTACT_EMAIL', (string)(getenv('CONTACT_EMAIL') ?: 'cantsaria@yandex.ru'));
}

if (!defined('SITE_NAME')) {
    define('SITE_NAME', (string)(getenv('SITE_NAME') ?: 'Канцария'));
}
