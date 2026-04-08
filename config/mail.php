<?php

declare(strict_types=1);

if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', app_env('SMTP_HOST', ''));
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', app_env('SMTP_PORT', '587'));
}
if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', app_env('SMTP_USERNAME', ''));
}
if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', app_env('SMTP_PASSWORD', ''));
}
if (!defined('SMTP_ENCRYPTION')) {
    define('SMTP_ENCRYPTION', app_env('SMTP_ENCRYPTION', 'tls'));
}
if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', app_env('SMTP_FROM_EMAIL', SMTP_USERNAME));
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', app_env('SMTP_FROM_NAME', SITE_NAME));
}
if (!defined('MAIL_LOG_PREFIX')) {
    define('MAIL_LOG_PREFIX', '[MAIL]');
}
