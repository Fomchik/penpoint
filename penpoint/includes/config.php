<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'penpoint');
define('DB_USER', 'root');
define('DB_PASSWORD', '');

// Базовый путь сайта (пустая строка если в корне сервера, иначе например '/project')
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
            PDO::ATTR_EMULATE_PREPARES => false // Используем натива prepares для безопасности
        ]
    );
} catch (PDOException $e) {
    die('Database connection error: ' . $e->getMessage());
}

// Helper functions
/**
 * Форматирует цену в строку с русским форматом
 * @param float $price Цена
 * @return string Отформатированная цена
 */
function format_price($price) {
    $price = (float)$price;
    return number_format($price, 0, ',', ' ') . ' ₽';
}

/**
 * Получает изображение товара
 * @param int $product_id ID товара
 * @return string Путь к изображению
 */
function get_product_image($product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT image_path 
            FROM product_images 
            WHERE product_id = ? 
            LIMIT 1
        ");
        $stmt->execute([(int)$product_id]);
        $result = $stmt->fetch();
        return $result ? htmlspecialchars($result['image_path'], ENT_QUOTES, 'UTF-8') : '/assets/product_images/default.png';
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return '/assets/product_images/default.png';
    }
}

/**
 * Получает рейтинг товара
 * @param int $product_id ID товара
 * @return array Массив с рейтингом и количеством отзывов
 */
function get_product_rating($product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ROUND(AVG(rating), 1) as rating,
                COUNT(*) as count 
            FROM reviews 
            WHERE product_id = ?
        ");
        $stmt->execute([(int)$product_id]);
        $result = $stmt->fetch();
        return [
            'rating' => (float)($result['rating'] ?? 0),
            'count' => (int)($result['count'] ?? 0)
        ];
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return ['rating' => 0, 'count' => 0];
    }
}

/**
 * Получает все категории
 * @return array Массив категорий
 */
function get_categories() {
    global $pdo;
    try {
        $stmt = $pdo->query("
            SELECT id, name, slug 
            FROM categories 
            ORDER BY id ASC
        ", PDO::FETCH_ASSOC);
        return $stmt->fetchAll() ?? [];
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Получает избранные товары
 * @param int $limit Количество товаров для получения
 * @return array Массив товаров
 */
function get_featured_products($limit = 20) {
    global $pdo;
    try {
        $limit = max(1, min((int)$limit, 1000)); // Защита от SQL injection
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.category_id,
                p.name,
                p.description,
                p.price,
                p.stock_quantity,
                p.rating,
                p.is_active,
                p.created_at,
                c.name as category_name 
            FROM products p 
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

/**
 * Получает товары по категории
 * @param int $category_id ID категории
 * @param int $limit Количество товаров
 * @return array Массив товаров
 */
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
                price,
                stock_quantity,
                rating,
                is_active,
                created_at
            FROM products 
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

/**
 * Поиск товаров по названию
 * @param string $query Поисковый запрос
 * @param int $limit Количество результатов
 * @return array Массив товаров
 */
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
                price,
                stock_quantity,
                rating,
                is_active,
                created_at
            FROM products 
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

/**
 * Получает товары со скидкой (для главной страницы).
 * Для отображения к первым товарам применяется скидка.
 * @param int $limit Количество товаров
 * @return array Массив товаров с полями discount_percent, price_old, price (новая цена)
 */
function get_discounted_products($limit = 5) {
    $products = get_featured_products($limit);
    $discounts = [20, 15, 15, 10, 10];
    foreach ($products as $idx => $p) {
        $percent = $discounts[$idx % count($discounts)] ?? 10;
        $products[$idx]['discount_percent'] = $percent;
        $products[$idx]['price_old'] = (float)$p['price'];
        $products[$idx]['price'] = round($products[$idx]['price_old'] * (1 - $percent / 100), 2);
    }
    return $products;
}

/**
 * Получает товар по ID
 * @param int $product_id ID товара
 * @return array|null Данные товара или null
 */
function get_product_by_id($product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.category_id,
                p.name,
                p.description,
                p.price,
                p.stock_quantity,
                p.rating,
                p.is_active,
                p.created_at,
                c.name as category_name
            FROM products p 
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

/**
 * Получает цвета товара
 * @param int $product_id ID товара
 * @return array Массив цветов
 */
function get_product_colors($product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, hex_code
            FROM product_colors
            WHERE product_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([(int)$product_id]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Получает связанные товары (для блока "С этим покупают")
 * @param int $product_id ID товара
 * @param int $limit Количество товаров
 * @return array Массив товаров
 */
function get_related_products($product_id, $limit = 3) {
    global $pdo;
    try {
        // Получаем товары из той же категории, исключая текущий товар
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.category_id,
                p.name,
                p.description,
                p.price,
                p.stock_quantity,
                p.rating,
                p.is_active,
                p.created_at
            FROM products p
            WHERE p.category_id = (SELECT category_id FROM products WHERE id = ?)
            AND p.id != ?
            AND p.is_active = 1
            ORDER BY RAND()
            LIMIT ?
        ");
        $stmt->execute([(int)$product_id, (int)$product_id, (int)$limit]);
        $products = $stmt->fetchAll() ?: [];
        
        // Если недостаточно товаров, добавляем случайные
        if (count($products) < $limit) {
            $stmt2 = $pdo->prepare("
                SELECT 
                    p.id,
                    p.category_id,
                    p.name,
                    p.description,
                    p.price,
                    p.stock_quantity,
                    p.rating,
                    p.is_active,
                    p.created_at
                FROM products p
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

/**
 * Проверяет, нужна ли панель выбора цвета для товара
 * @param int $product_id ID товара
 * @return bool
 */
function product_needs_color_panel($product_id) {
    global $pdo;
    try {
        // Проверяем название товара на наличие ключевых слов
        $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
        $stmt->execute([(int)$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) return false;
        
        $name = mb_strtolower($product['name']);
        
        // Если это цветные карандаши или тетради - не нужна панель
        if (strpos($name, 'цветные карандаши') !== false || 
            strpos($name, 'карандаши') !== false && strpos($name, 'цветн') !== false) {
            return false;
        }
        
        if (strpos($name, 'тетрадь') !== false || 
            strpos($name, 'блокнот') !== false ||
            strpos($name, 'альбом') !== false) {
            return false;
        }
        
        // Для ручек нужна панель, если есть цвета в БД
        $colors = get_product_colors($product_id);
        return !empty($colors);
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        return false;
    }
}
?>
