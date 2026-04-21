<?php

declare(strict_types=1);

function product_options_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS product_parameters (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                product_id INT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                code VARCHAR(80) NOT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_product_sort (product_id, sort_order),
                KEY idx_product_code (product_id, code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS product_parameter_values (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                parameter_id INT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                price DECIMAL(10,2) NULL DEFAULT NULL,
                stock_quantity INT UNSIGNED NULL DEFAULT NULL,
                image_path VARCHAR(255) DEFAULT '',
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_parameter_sort (parameter_id, sort_order),
                FOREIGN KEY (parameter_id) REFERENCES product_parameters(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS product_variants (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                product_id INT UNSIGNED NOT NULL,
                label VARCHAR(255) NOT NULL,
                price DECIMAL(10,2) NULL DEFAULT NULL,
                stock_quantity INT UNSIGNED NOT NULL DEFAULT 0,
                attributes_json TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_product_active (product_id, is_active),
                KEY idx_product_price (product_id, price)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS product_variant_images (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                product_id INT UNSIGNED NOT NULL,
                variant_id INT UNSIGNED NULL,
                parameter_code VARCHAR(80) NOT NULL,
                parameter_value VARCHAR(120) NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_product_variant_images_product (product_id),
                KEY idx_product_variant_images_variant (variant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        try {
            $pdo->exec('ALTER TABLE product_variants MODIFY price DECIMAL(10,2) NULL DEFAULT NULL');
        } catch (Throwable $e) {
            // Existing schema may already be compatible.
        }

        $columns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM product_parameters') ?: [] as $column) {
            $columns[(string)$column['Field']] = true;
        }
        if (!isset($columns['image_path'])) {
            $pdo->exec("ALTER TABLE product_parameters ADD COLUMN image_path VARCHAR(255) DEFAULT '' AFTER values_text");
        }

        $columns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM order_items') ?: [] as $column) {
            $columns[(string)$column['Field']] = true;
        }
        if (!isset($columns['variant_id'])) {
            $pdo->exec('ALTER TABLE order_items ADD COLUMN variant_id INT UNSIGNED NULL DEFAULT NULL AFTER product_id');
        }
        if (!isset($columns['attributes_json'])) {
            $pdo->exec('ALTER TABLE order_items ADD COLUMN attributes_json TEXT NULL AFTER variant_id');
        }
        if (!isset($columns['title'])) {
            $pdo->exec('ALTER TABLE order_items ADD COLUMN title VARCHAR(255) NULL DEFAULT NULL AFTER attributes_json');
        }
        if (!isset($columns['variant_label'])) {
            $pdo->exec('ALTER TABLE order_items ADD COLUMN variant_label VARCHAR(255) NULL DEFAULT NULL AFTER title');
        }

        $orderColumns = [];
        foreach ($pdo->query('SHOW COLUMNS FROM orders') ?: [] as $column) {
            $orderColumns[(string)$column['Field']] = true;
        }
        if (!isset($orderColumns['delivery_price'])) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER total_price");
        }
        if (!isset($orderColumns['discount_total'])) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN discount_total DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER delivery_price");
        }

        try {
            $pdo->exec('ALTER TABLE order_items DROP INDEX uniq_order_product');
        } catch (Throwable $e) {
            // index may already be absent
        }
        try {
            $pdo->exec('ALTER TABLE order_items ADD UNIQUE KEY uniq_order_product_variant (order_id, product_id, variant_id)');
        } catch (Throwable $e) {
            // index may already exist
        }
    } catch (Throwable $e) {
        error_log('Product options schema ensure error: ' . $e->getMessage());
    }
}
