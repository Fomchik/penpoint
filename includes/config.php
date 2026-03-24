<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'penpoint');
define('DB_USER', 'root');
define('DB_PASSWORD', '');

// Base path of site (empty for server root)
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '');
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    http_response_code(500);
    exit('Internal server error.');
}

function format_price($price) {
    $price = (float)$price;
    return number_format($price, 0, ',', ' ') . ' ₽';
}

function sync_promotion_statuses() {
    global $pdo;
    try {
        $pdo->exec("CALL sp_sync_promotion_statuses()");
    } catch (PDOException $e) {
        error_log('Database error (sync_promotion_statuses): ' . $e->getMessage());
    }
}

function calculate_discounted_price($base_price, $discount_percent) {
    $base_price = (float)$base_price;
    $discount_percent = max(0, min(100, (int)$discount_percent));
    if ($discount_percent <= 0) {
        return $base_price;
    }
    return round($base_price * (1 - $discount_percent / 100), 2);
}

function get_product_discounts_map(array $product_ids) {
    global $pdo;

    $ids = array_values(array_unique(array_map('intval', $product_ids)));
    $ids = array_values(array_filter($ids, function ($id) {
        return $id > 0;
    }));

    if (empty($ids)) {
        return [];
    }

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id AS product_id, discount_percent FROM v_product_pricing WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll() ?: [];

        $map = [];
        foreach ($rows as $row) {
            $pid = (int)$row['product_id'];
            $map[$pid] = max(0, min(100, (int)$row['discount_percent']));
        }

        return $map;
    } catch (PDOException $e) {
        error_log('Database error (get_product_discounts_map): ' . $e->getMessage());
        return [];
    }
}

function apply_discount_to_product(array $product, $discount_percent = null) {
    $price_old = isset($product['base_price']) ? (float)$product['base_price'] : (float)($product['price'] ?? 0);
    $price_new = isset($product['final_price']) ? (float)$product['final_price'] : $price_old;
    $percent = $discount_percent !== null ? (int)$discount_percent : (int)($product['discount_percent'] ?? 0);
    $percent = max(0, min(100, $percent));

    if (!isset($product['final_price'])) {
        $price_new = calculate_discounted_price($price_old, $percent);
    }

    $product['price_old'] = $price_old;
    $product['discount_percent'] = $percent;
    $product['price'] = $price_new;

    return $product;
}

function enrich_products_with_discounts(array $products) {
    global $pdo;

    if (empty($products)) {
        return [];
    }

    $ids = [];
    foreach ($products as $p) {
        if (isset($p['id'])) {
            $ids[] = (int)$p['id'];
        }
    }

    $discount_map = get_product_discounts_map($ids);

    $pricing_map = [];
    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            SELECT id, base_price, final_price, discount_percent, promotion_id
            FROM v_product_pricing
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $pricing_map[(int)$row['id']] = $row;
        }
    } catch (PDOException $e) {
        error_log('Database error (enrich_products_with_discounts): ' . $e->getMessage());
    }

    foreach ($products as $idx => $p) {
        $pid = (int)($p['id'] ?? 0);
        if (isset($pricing_map[$pid])) {
            $p['base_price'] = (float)$pricing_map[$pid]['base_price'];
            $p['final_price'] = (float)$pricing_map[$pid]['final_price'];
            $p['promotion_id'] = $pricing_map[$pid]['promotion_id'] !== null ? (int)$pricing_map[$pid]['promotion_id'] : null;
        }
        $products[$idx] = apply_discount_to_product($p, $discount_map[$pid] ?? 0);
    }

    return $products;
}

function get_active_discounted_products_count() {
    global $pdo;

    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) AS cnt
            FROM v_product_pricing
            WHERE is_active = 1
              AND discount_percent > 0
        ");
        $row = $stmt->fetch();
        return (int)($row['cnt'] ?? 0);
    } catch (PDOException $e) {
        error_log('Database error (get_active_discounted_products_count): ' . $e->getMessage());
        return 0;
    }
}

function get_product_image($product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? LIMIT 1");
        $stmt->execute([(int)$product_id]);
        $result = $stmt->fetch();
        return $result ? htmlspecialchars($result['image_path'], ENT_QUOTES, 'UTF-8') : '/assets/product_images/default.png';
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return '/assets/product_images/default.png';
    }
}

function get_product_rating($product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT ROUND(AVG(rating), 1) as rating, COUNT(*) as count FROM reviews WHERE product_id = ?");
        $stmt->execute([(int)$product_id]);
        $result = $stmt->fetch();
        return [
            'rating' => (float)($result['rating'] ?? 0),
            'count' => (int)($result['count'] ?? 0),
        ];
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return ['rating' => 0, 'count' => 0];
    }
}

function get_categories() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT id, name, slug FROM categories ORDER BY id ASC", PDO::FETCH_ASSOC);
        return $stmt->fetchAll() ?? [];
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return [];
    }
}

function get_featured_products($limit = 20) {
    global $pdo;
    try {
        $limit = max(1, min((int)$limit, 1000));
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
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.is_active = 1
            ORDER BY p.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll() ?? [];
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return [];
    }
}

function get_products_by_category($category_id, $limit = 20) {
    global $pdo;
    try {
        $category_id = (int)$category_id;
        $limit = max(1, min((int)$limit, 1000));

        $stmt = $pdo->prepare("
            SELECT
                id,
                category_id,
                name,
                description,
                final_price AS price,
                base_price AS price_old,
                discount_percent,
                promotion_id,
                stock_quantity,
                rating,
                is_active,
                created_at
            FROM v_product_pricing
            WHERE is_active = 1 AND category_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$category_id, $limit]);
        return $stmt->fetchAll() ?? [];
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return [];
    }
}

function search_products($query, $limit = 20) {
    global $pdo;
    try {
        $query = '%' . trim($query) . '%';
        $limit = max(1, min((int)$limit, 1000));

        $stmt = $pdo->prepare("
            SELECT
                id,
                category_id,
                name,
                description,
                final_price AS price,
                base_price AS price_old,
                discount_percent,
                promotion_id,
                stock_quantity,
                rating,
                is_active,
                created_at
            FROM v_product_pricing
            WHERE is_active = 1 AND (name LIKE ? OR description LIKE ?)
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$query, $query, $limit]);
        return $stmt->fetchAll() ?? [];
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return [];
    }
}

function get_discounted_products($limit = 5) {
    global $pdo;

    $limit = max(1, min((int)$limit, 100));
    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                category_id,
                name,
                description,
                final_price AS price,
                base_price AS price_old,
                discount_percent,
                promotion_id,
                stock_quantity,
                rating,
                is_active,
                created_at
            FROM v_product_pricing
            WHERE is_active = 1
              AND discount_percent > 0
            ORDER BY discount_percent DESC, created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('Database error (get_discounted_products view): ' . $e->getMessage());

        // Fallback if views were not created during DB import.
        try {
            $stmt = $pdo->prepare("
                SELECT
                    p.id,
                    p.category_id,
                    p.name,
                    p.description,
                    ROUND(p.price * (1 - COALESCE(d.discount_percent, 0) / 100), 2) AS price,
                    p.price AS price_old,
                    COALESCE(d.discount_percent, 0) AS discount_percent,
                    d.promotion_id,
                    p.stock_quantity,
                    p.rating,
                    p.is_active,
                    p.created_at
                FROM products p
                INNER JOIN (
                    SELECT
                        x.product_id,
                        MIN(x.promotion_id) AS promotion_id,
                        x.discount_percent
                    FROM (
                        SELECT
                            s.product_id,
                            promo.id AS promotion_id,
                            promo.discount_percent
                        FROM (
                            SELECT pp.product_id, pp.promotion_id
                            FROM promotion_products pp
                            UNION ALL
                            SELECT p2.id AS product_id, pc.promotion_id
                            FROM products p2
                            INNER JOIN promotion_categories pc ON pc.category_id = p2.category_id
                        ) s
                        INNER JOIN promotions promo ON promo.id = s.promotion_id
                        WHERE CURDATE() BETWEEN promo.date_start AND promo.date_end
                    ) x
                    INNER JOIN (
                        SELECT
                            y.product_id,
                            MAX(y.discount_percent) AS discount_percent
                        FROM (
                            SELECT
                                s2.product_id,
                                promo2.discount_percent
                            FROM (
                                SELECT pp2.product_id, pp2.promotion_id
                                FROM promotion_products pp2
                                UNION ALL
                                SELECT p3.id AS product_id, pc2.promotion_id
                                FROM products p3
                                INNER JOIN promotion_categories pc2 ON pc2.category_id = p3.category_id
                            ) s2
                            INNER JOIN promotions promo2 ON promo2.id = s2.promotion_id
                            WHERE CURDATE() BETWEEN promo2.date_start AND promo2.date_end
                        ) y
                        GROUP BY y.product_id
                    ) best ON best.product_id = x.product_id AND best.discount_percent = x.discount_percent
                    GROUP BY x.product_id, x.discount_percent
                ) d ON d.product_id = p.id
                WHERE p.is_active = 1
                  AND d.discount_percent > 0
                ORDER BY d.discount_percent DESC, p.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll() ?: [];
        } catch (PDOException $e2) {
            error_log('Database error (get_discounted_products fallback): ' . $e2->getMessage());
            return [];
        }
    }
}

function get_product_by_id($product_id) {
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
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ? AND p.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([(int)$product_id]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return null;
    }
}

function get_product_colors($product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id, name, hex_code FROM product_colors WHERE product_id = ? ORDER BY id ASC");
        $stmt->execute([(int)$product_id]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return [];
    }
}

function get_related_products($product_id, $limit = 3) {
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
                p.created_at
            FROM v_product_pricing p
            WHERE p.category_id = (SELECT category_id FROM products WHERE id = ?)
            AND p.id != ?
            AND p.is_active = 1
            ORDER BY RAND()
            LIMIT ?
        ");
        $stmt->execute([(int)$product_id, (int)$product_id, (int)$limit]);
        $products = $stmt->fetchAll() ?: [];

        if (count($products) < $limit) {
            $stmt2 = $pdo->prepare("
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
                    p.created_at
                FROM v_product_pricing p
                WHERE p.id != ? AND p.is_active = 1
                ORDER BY RAND()
                LIMIT ?
            ");
            $stmt2->execute([(int)$product_id, (int)$limit]);
            $additional = $stmt2->fetchAll() ?: [];
            $products = array_merge($products, $additional);
            $products = array_slice($products, 0, $limit);
        }

        return $products;
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return [];
    }
}

function product_needs_color_panel($product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
        $stmt->execute([(int)$product_id]);
        $product = $stmt->fetch();

        if (!$product) {
            return false;
        }

        $name = mb_strtolower($product['name']);

        if (strpos($name, 'цветные карандаши') !== false ||
            (strpos($name, 'карандаши') !== false && strpos($name, 'цветн') !== false)) {
            return false;
        }

        if (strpos($name, 'тетрадь') !== false ||
            strpos($name, 'блокнот') !== false ||
            strpos($name, 'альбом') !== false) {
            return false;
        }

        $colors = get_product_colors($product_id);
        return !empty($colors);
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return false;
    }
}
?>
