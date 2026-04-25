<?php

declare(strict_types=1);

if (defined('PRODUCT_OPTIONS_LOADED')) {
    return;
}
define('PRODUCT_OPTIONS_LOADED', true);

require_once __DIR__ . '/product_options_catalog.php';
require_once __DIR__ . '/product_options_schema.php';
require_once __DIR__ . '/product_options_repository.php';
require_once __DIR__ . '/product_options_state.php';
require_once __DIR__ . '/product_options_admin.php';
