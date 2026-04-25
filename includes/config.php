<?php

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (!function_exists('app_bootstrap_env_file')) {
    function app_bootstrap_env_file(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

app_bootstrap_env_file(dirname(__DIR__) . '/.env');
require_once __DIR__ . '/../config/app.php';

if (!function_exists('app_load_env')) {
    function app_load_env(string $path): void
    {
        static $loaded = [];
        if (isset($loaded[$path]) || !is_file($path)) {
            return;
        }
        $loaded[$path] = true;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key === '') {
                continue;
            }

            if ($value !== '' && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

if (!function_exists('app_env')) {
    function app_env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        return $value === false ? $default : (string)$value;
    }
}

app_load_env(APP_ROOT_PATH . '/.env');
require_once __DIR__ . '/../config/mail.php';

define('DB_HOST', app_env('DB_HOST', 'localhost'));
define('DB_PORT', app_env('DB_PORT', '3306'));
define('DB_NAME', app_env('DB_NAME', 'penpoint'));
define('DB_USER', app_env('DB_USER', 'root'));
define('DB_PASSWORD', app_env('DB_PASSWORD', ''));
define('DB_CHARSET', app_env('DB_CHARSET', 'utf8mb4'));

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec("SET NAMES '" . DB_CHARSET . "'");
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    exit('Internal server error.');
}

function format_price($price): string
{
    return number_format((float)$price, 0, ',', ' ') . ' ₽';
}

if (!function_exists('app_url')) {
    function app_url(string $path): string
    {
        if ($path === '' || preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }

        return (defined('BASE_PATH') ? (string)BASE_PATH : '') . $path;
    }
}

function sync_promotion_statuses(): void
{
    global $pdo;

    try {
        $pdo->exec('CALL sp_sync_promotion_statuses()');
    } catch (PDOException $e) {
        error_log('Database error (sync_promotion_statuses): ' . $e->getMessage());
    }
}

function calculate_discounted_price($base_price, $discount_percent): float
{
    $base = (float)$base_price;
    $percent = max(0, min(100, (int)$discount_percent));
    if ($percent <= 0) {
        return $base;
    }

    return round($base * (1 - ($percent / 100)), 2);
}

function app_fetch_product_images(int $productId): array
{
    global $pdo;

    try {
        $stmt = $pdo->prepare('SELECT image_path FROM product_images WHERE product_id = ? ORDER BY id ASC');
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $images = [];
        foreach ($rows as $row) {
            $path = trim((string)$row);
            if ($path !== '') {
                $images[] = $path;
            }
        }

        return $images !== [] ? $images : [APP_DEFAULT_PRODUCT_IMAGE];
    } catch (PDOException $e) {
        error_log('Database error (app_fetch_product_images): ' . $e->getMessage());
        return [APP_DEFAULT_PRODUCT_IMAGE];
    }
}

function get_product_discounts_map(array $productIds): array
{
    global $pdo;

    $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), static function (int $id): bool {
        return $id > 0;
    })));
    if ($ids === []) {
        return [];
    }

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT id AS product_id, discount_percent FROM v_product_pricing WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll() ?: [];

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['product_id']] = max(0, min(100, (int)($row['discount_percent'] ?? 0)));
        }

        return $map;
    } catch (PDOException $e) {
        error_log('Database error (get_product_discounts_map): ' . $e->getMessage());
        return [];
    }
}

function apply_discount_to_product(array $product, $discountPercent = null): array
{
    $priceOld = isset($product['base_price']) ? (float)$product['base_price'] : (float)($product['price'] ?? 0);
    $priceNew = isset($product['final_price']) ? (float)$product['final_price'] : $priceOld;
    $percent = $discountPercent !== null ? (int)$discountPercent : (int)($product['discount_percent'] ?? 0);
    $percent = max(0, min(100, $percent));

    if (!isset($product['final_price'])) {
        $priceNew = calculate_discounted_price($priceOld, $percent);
    }

    $product['price_old'] = $priceOld;
    $product['discount_percent'] = $percent;
    $product['price'] = $priceNew;

    return $product;
}

function enrich_products_with_discounts(array $products): array
{
    global $pdo;

    if ($products === []) {
        return [];
    }

    $ids = [];
    foreach ($products as $product) {
        if (isset($product['id'])) {
            $ids[] = (int)$product['id'];
        }
    }

    $discountMap = get_product_discounts_map($ids);
    $pricingMap = [];

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            SELECT id, base_price, final_price, discount_percent, promotion_id
            FROM v_product_pricing
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $pricingMap[(int)$row['id']] = $row;
        }
    } catch (PDOException $e) {
        error_log('Database error (enrich_products_with_discounts): ' . $e->getMessage());
    }

    foreach ($products as $index => $product) {
        $productId = (int)($product['id'] ?? 0);
        if (isset($pricingMap[$productId])) {
            $product['base_price'] = (float)$pricingMap[$productId]['base_price'];
            $product['final_price'] = (float)$pricingMap[$productId]['final_price'];
            $product['promotion_id'] = $pricingMap[$productId]['promotion_id'] !== null ? (int)$pricingMap[$productId]['promotion_id'] : null;
        }
        $products[$index] = apply_discount_to_product($product, $discountMap[$productId] ?? 0);
    }

    return $products;
}

function get_active_discounted_products_count(): int
{
    global $pdo;

    try {
        $stmt = $pdo->query('SELECT COUNT(*) AS cnt FROM v_product_pricing WHERE is_active = 1 AND discount_percent > 0');
        $row = $stmt->fetch();
        return (int)($row['cnt'] ?? 0);
    } catch (PDOException $e) {
        error_log('Database error (get_active_discounted_products_count): ' . $e->getMessage());
        return 0;
    }
}

function get_product_image($productId): string
{
    $images = app_fetch_product_images((int)$productId);
    $candidate = (string)($images[0] ?? APP_DEFAULT_PRODUCT_IMAGE);
    $rootDir = dirname(__DIR__);
    $candidatePath = $candidate !== '' ? $rootDir . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $candidate), DIRECTORY_SEPARATOR) : '';
    if ($candidatePath === '' || !is_file($candidatePath)) {
        $fallback = '/assets/icons/favicon.svg';
        $fallbackPath = $rootDir . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $fallback), DIRECTORY_SEPARATOR);
        $candidate = is_file($fallbackPath) ? $fallback : '/assets/icons/favicon.svg';
    }

    return htmlspecialchars($candidate, ENT_QUOTES, 'UTF-8');
}

function get_product_rating($productId): array
{
    global $pdo;
    static $cache = [];
    $cacheKey = (int)$productId;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    try {
        $hasPublishedColumn = false;
        try {
            foreach ($pdo->query('SHOW COLUMNS FROM reviews') ?: [] as $column) {
                if ((string)$column['Field'] === 'is_published') {
                    $hasPublishedColumn = true;
                    break;
                }
            }
        } catch (Throwable $e) {
        }

        $sql = 'SELECT ROUND(AVG(rating), 1) AS rating, COUNT(*) AS count FROM reviews WHERE product_id = ?';
        if ($hasPublishedColumn) {
            $sql .= ' AND is_published = 1';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([(int)$productId]);
        $result = $stmt->fetch();

        $rating = [
            'rating' => (float)($result['rating'] ?? 0),
            'count' => (int)($result['count'] ?? 0),
        ];
        $cache[$cacheKey] = $rating;
        return $rating;
    } catch (PDOException $e) {
        error_log('Database error (get_product_rating): ' . $e->getMessage());
        $cache[$cacheKey] = ['rating' => 0.0, 'count' => 0];
        return $cache[$cacheKey];
    }
}

function get_categories(): array
{
    global $pdo;
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    try {
        // slug мог отсутствовать в старой схеме — обеспечиваем совместимость
        try {
            $stmtHasSlug = $pdo->query("SHOW COLUMNS FROM categories LIKE 'slug'");
            $hasSlug = (bool)($stmtHasSlug && $stmtHasSlug->fetch());
        } catch (Throwable $e) {
            $hasSlug = false;
        }

        if (!$hasSlug) {
            try {
                $pdo->exec("ALTER TABLE categories ADD COLUMN slug VARCHAR(190) NOT NULL DEFAULT '' AFTER name");
            } catch (Throwable $e) {
                // ignore if we cannot alter
            }
            try {
                $stmtHasSlug = $pdo->query("SHOW COLUMNS FROM categories LIKE 'slug'");
                $hasSlug = (bool)($stmtHasSlug && $stmtHasSlug->fetch());
            } catch (Throwable $e) {
                $hasSlug = false;
            }
        }

        $stmt = $pdo->query($hasSlug ? 'SELECT id, name, slug FROM categories ORDER BY id ASC' : 'SELECT id, name, "" AS slug FROM categories ORDER BY id ASC');
        $cache = $stmt->fetchAll() ?: [];
        return $cache;
    } catch (PDOException $e) {
        error_log('Database error (get_categories): ' . $e->getMessage());
        $cache = [];
        return [];
    }
}

function product_category_links_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS product_categories (
                product_id INT NOT NULL,
                category_id INT NOT NULL,
                PRIMARY KEY (product_id, category_id),
                KEY idx_category_product (category_id, product_id),
                CONSTRAINT fk_product_categories_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                CONSTRAINT fk_product_categories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        error_log('Product categories schema error: ' . $e->getMessage());
    }
}

function product_normalize_category_ids(array $categoryIds): array
{
    $normalized = [];
    foreach ($categoryIds as $rawId) {
        $id = (int)$rawId;
        if ($id > 0) {
            $normalized[$id] = $id;
        }
    }

    return array_values($normalized);
}

function product_sync_categories(PDO $pdo, int $productId, array $categoryIds): void
{
    product_category_links_ensure_schema($pdo);
    $categoryIds = product_normalize_category_ids($categoryIds);

    $stmtDelete = $pdo->prepare('DELETE FROM product_categories WHERE product_id = ?');
    $stmtDelete->execute([$productId]);

    if ($categoryIds === []) {
        return;
    }

    $stmtInsert = $pdo->prepare('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)');
    foreach ($categoryIds as $categoryId) {
        $stmtInsert->execute([$productId, $categoryId]);
    }
}

function product_fetch_category_ids(PDO $pdo, int $productId): array
{
    product_category_links_ensure_schema($pdo);

    try {
        $stmt = $pdo->prepare('SELECT category_id FROM product_categories WHERE product_id = ? ORDER BY category_id ASC');
        $stmt->execute([$productId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return product_normalize_category_ids($rows);
    } catch (Throwable $e) {
        error_log('Product fetch categories error: ' . $e->getMessage());
        return [];
    }
}

function product_fetch_category_names(PDO $pdo, int $productId): array
{
    product_category_links_ensure_schema($pdo);

    if ($productId <= 0) {
        return [];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT c.id, c.name
            FROM (
                SELECT p.category_id AS category_id, 0 AS sort_order
                FROM products p
                WHERE p.id = ?
                UNION ALL
                SELECT pc.category_id AS category_id, 1 AS sort_order
                FROM product_categories pc
                WHERE pc.product_id = ?
            ) t
            INNER JOIN categories c ON c.id = t.category_id
            WHERE t.category_id IS NOT NULL AND t.category_id > 0
            ORDER BY t.sort_order ASC, c.name ASC
        ");
        $stmt->execute([$productId, $productId]);
        $rows = $stmt->fetchAll() ?: [];

        $names = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return $names;
    } catch (Throwable $e) {
        error_log('Fetch product category names error: ' . $e->getMessage());
        return [];
    }
}

function get_featured_products($limit = 20): array
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.category_id,
                p.name,
                p.description,
                p.final_price AS price,
                p.base_price AS price_old,
                p.discount_percent,
                p.promotion_id,
                p.stock_quantity,
                p.rating,
                p.is_active,
                p.created_at,
                c.name AS category_name
            FROM v_product_pricing p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.is_active = 1
            ORDER BY p.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([max(1, min((int)$limit, 1000))]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('Database error (get_featured_products): ' . $e->getMessage());
        return [];
    }
}

function get_products_by_category($categoryId, $limit = 20): array
{
    global $pdo;
    product_category_links_ensure_schema($pdo);

    try {
        $stmt = $pdo->prepare("
            SELECT id, category_id, name, description, final_price AS price, base_price AS price_old,
                   discount_percent, promotion_id, stock_quantity, rating, is_active, created_at
            FROM v_product_pricing
            WHERE is_active = 1
              AND (
                    category_id = ?
                    OR EXISTS (
                        SELECT 1
                        FROM product_categories pc
                        WHERE pc.product_id = v_product_pricing.id AND pc.category_id = ?
                    )
              )
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([(int)$categoryId, (int)$categoryId, max(1, min((int)$limit, 1000))]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('Database error (get_products_by_category): ' . $e->getMessage());
        return [];
    }
}

function search_products($query, $limit = 20): array
{
    global $pdo;

    try {
        $like = '%' . trim((string)$query) . '%';
        $stmt = $pdo->prepare("
            SELECT id, category_id, name, description, final_price AS price, base_price AS price_old,
                   discount_percent, promotion_id, stock_quantity, rating, is_active, created_at
            FROM v_product_pricing
            WHERE is_active = 1 AND (name LIKE ? OR description LIKE ?)
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$like, $like, max(1, min((int)$limit, 1000))]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('Database error (search_products): ' . $e->getMessage());
        return [];
    }
}

function get_discounted_products($limit = 5): array
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT id, category_id, name, description, final_price AS price, base_price AS price_old,
                   discount_percent, promotion_id, stock_quantity, rating, is_active, created_at
            FROM v_product_pricing
            WHERE is_active = 1 AND discount_percent > 0
            ORDER BY discount_percent DESC, created_at DESC
            LIMIT ?
        ");
        $stmt->execute([max(1, min((int)$limit, 100))]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('Database error (get_discounted_products): ' . $e->getMessage());
        return [];
    }
}

function get_product_by_id($productId): ?array
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.category_id,
                p.name,
                p.description,
                p.final_price AS price,
                p.base_price AS price_old,
                p.discount_percent,
                p.promotion_id,
                p.stock_quantity,
                p.rating,
                p.is_active,
                p.created_at,
                c.name AS category_name
            FROM v_product_pricing p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.id = ? AND p.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([(int)$productId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    } catch (PDOException $e) {
        error_log('Database error (get_product_by_id): ' . $e->getMessage());
        return null;
    }
}

function get_product_colors($productId): array
{
    global $pdo;

    try {
        $stmt = $pdo->prepare('SELECT id, name, hex_code FROM product_colors WHERE product_id = ? ORDER BY id ASC');
        $stmt->execute([(int)$productId]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('Database error (get_product_colors): ' . $e->getMessage());
        return [];
    }
}

function get_related_products($productId, $limit = 3): array
{
    global $pdo;
    $limit = max(1, min((int)$limit, 50));

    try {
        $stmtCategory = $pdo->prepare('SELECT category_id FROM products WHERE id = ? LIMIT 1');
        $stmtCategory->execute([(int)$productId]);
        $categoryId = (int)($stmtCategory->fetchColumn() ?: 0);

        $seen = [(int)$productId => true];
        $products = [];
        $pivot = max(1, (int)$productId);

        if ($categoryId > 0) {
            $stmtPrimary = $pdo->prepare("
                SELECT id, category_id, name, description, final_price AS price, base_price AS price_old,
                       discount_percent, promotion_id, stock_quantity, rating, is_active, created_at
                FROM v_product_pricing
                WHERE is_active = 1 AND category_id = ? AND id <> ? AND id >= ?
                ORDER BY id ASC
                LIMIT ?
            ");
            $stmtPrimary->execute([$categoryId, (int)$productId, $pivot, $limit]);
            foreach ($stmtPrimary->fetchAll() ?: [] as $row) {
                $id = (int)($row['id'] ?? 0);
                if ($id > 0 && !isset($seen[$id])) {
                    $seen[$id] = true;
                    $products[] = $row;
                }
            }

            if (count($products) < $limit) {
                $stmtSecondary = $pdo->prepare("
                    SELECT id, category_id, name, description, final_price AS price, base_price AS price_old,
                           discount_percent, promotion_id, stock_quantity, rating, is_active, created_at
                    FROM v_product_pricing
                    WHERE is_active = 1 AND category_id = ? AND id <> ? AND id < ?
                    ORDER BY id DESC
                    LIMIT ?
                ");
                $stmtSecondary->execute([$categoryId, (int)$productId, $pivot, $limit - count($products)]);
                foreach ($stmtSecondary->fetchAll() ?: [] as $row) {
                    $id = (int)($row['id'] ?? 0);
                    if ($id > 0 && !isset($seen[$id])) {
                        $seen[$id] = true;
                        $products[] = $row;
                    }
                }
            }
        }

        if (count($products) < $limit) {
            $stmtFallback = $pdo->prepare("
                SELECT id, category_id, name, description, final_price AS price, base_price AS price_old,
                       discount_percent, promotion_id, stock_quantity, rating, is_active, created_at
                FROM v_product_pricing
                WHERE is_active = 1 AND id <> ?
                ORDER BY id DESC
                LIMIT ?
            ");
            $stmtFallback->execute([(int)$productId, $limit * 3]);
            foreach ($stmtFallback->fetchAll() ?: [] as $row) {
                $id = (int)($row['id'] ?? 0);
                if ($id > 0 && !isset($seen[$id])) {
                    $seen[$id] = true;
                    $products[] = $row;
                    if (count($products) >= $limit) {
                        break;
                    }
                }
            }
        }

        return array_slice($products, 0, $limit);
    } catch (PDOException $e) {
        error_log('Database error (get_related_products): ' . $e->getMessage());
        return [];
    }
}

function product_needs_color_panel($productId): bool
{
    global $pdo;
    static $checkedSchema = false;

    try {
        if (!$checkedSchema) {
            $checkedSchema = true;
            $hasColumn = false;
            foreach ($pdo->query("SHOW COLUMNS FROM products LIKE 'has_color_variants'") ?: [] as $row) {
                $hasColumn = true;
                break;
            }
            if (!$hasColumn) {
                $pdo->exec("ALTER TABLE products ADD COLUMN has_color_variants TINYINT(1) NOT NULL DEFAULT 0 AFTER pickup_available");
            }
        }

        $stmt = $pdo->prepare('SELECT has_color_variants FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$productId]);
        $product = $stmt->fetch();
        if (!$product) {
            return false;
        }

        return ((int)($product['has_color_variants'] ?? 0) === 1) && get_product_colors((int)$productId) !== [];
    } catch (PDOException $e) {
        error_log('Database error (product_needs_color_panel): ' . $e->getMessage());
        return false;
    }
}

function app_promotion_catalog_query_params(int $promotionId): array
{
    global $pdo;

    if ($promotionId <= 0) {
        return [];
    }

    $params = ['sale' => '1'];

    try {
        $stmt = $pdo->prepare('SELECT apply_scope FROM promotions WHERE id = ? LIMIT 1');
        $stmt->execute([$promotionId]);
        $scope = (string)($stmt->fetchColumn() ?: '');

        if ($scope === 'categories') {
            $stmt = $pdo->prepare('SELECT category_id FROM promotion_categories WHERE promotion_id = ? ORDER BY category_id ASC');
            $stmt->execute([$promotionId]);
            $ids = array_values(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []), static function (int $id): bool {
                return $id > 0;
            }));
            if ($ids !== []) {
                $params['category'] = $ids;
            }
        } elseif ($scope === 'products') {
            $stmt = $pdo->prepare('SELECT product_id FROM promotion_products WHERE promotion_id = ? ORDER BY product_id ASC');
            $stmt->execute([$promotionId]);
            $ids = array_values(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []), static function (int $id): bool {
                return $id > 0;
            }));
            if ($ids !== []) {
                $params['product_ids'] = $ids;
            }
        }
    } catch (Throwable $e) {
        error_log('Promotion catalog params error: ' . $e->getMessage());
    }

    return $params;
}

function app_promotion_catalog_url(int $promotionId): string
{
    $params = app_promotion_catalog_query_params($promotionId);
    if ($params === []) {
        return '/pages/catalog.php';
    }

    return '/pages/catalog.php?' . http_build_query($params);
}
