-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Фев 27 2026 г., 22:59
-- Версия сервера: 5.7.44
-- Версия PHP: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `penpoint`
--

DELIMITER $$
--
-- Процедуры
--
CREATE DEFINER=`root`@`%` PROCEDURE `sp_sync_promotion_statuses` ()   BEGIN
    UPDATE `promotions`
    SET `status` = CASE
        WHEN `date_start` > CURDATE() THEN 'draft'
        WHEN `date_end` IS NOT NULL AND `date_end` < CURDATE() THEN 'finished'
        ELSE 'active'
    END
    WHERE `status` <> CASE
        WHEN `date_start` > CURDATE() THEN 'draft'
        WHEN `date_end` IS NOT NULL AND `date_end` < CURDATE() THEN 'finished'
        ELSE 'active'
    END;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`) VALUES
(1, 'Письменные принадлежности', 'writing'),
(2, 'Бумажная продукция', 'paper'),
(3, 'Органайзеры', 'organizers'),
(4, 'Творчество', 'creativity'),
(5, 'Аксессуары', 'accessories');

-- --------------------------------------------------------

--
-- Структура таблицы `delivery_methods`
--

CREATE TABLE `delivery_methods` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `delivery_methods`
--

INSERT INTO `delivery_methods` (`id`, `name`, `price`) VALUES
(1, 'Самовывоз', 0.00),
(2, 'Курьерская доставка', 300.00);

-- --------------------------------------------------------

--
-- Структура таблицы `favorites`
--

CREATE TABLE `favorites` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `feedback`
--

CREATE TABLE `feedback` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `status_id` int(10) UNSIGNED NOT NULL,
  `delivery_method_id` int(10) UNSIGNED NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `discount_percent` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `promotion_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `order_statuses`
--

CREATE TABLE `order_statuses` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `order_statuses`
--

INSERT INTO `order_statuses` (`id`, `name`) VALUES
(2, 'В обработке'),
(4, 'Доставлен'),
(1, 'Новый'),
(5, 'Отменён'),
(3, 'Отправлен');

-- --------------------------------------------------------

--
-- Структура таблицы `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(10) UNSIGNED DEFAULT '0',
  `pickup_available` tinyint(1) DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT '0.0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `stock_quantity`, `pickup_available`, `rating`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Шариковая ручка, синяя, 0.7 мм', 'Шариковая ручка с качественным стержнем для повседневного письма.', 84.00, 4, NULL, 0.0, 1, '2026-01-15 10:30:00', '2026-02-27 12:11:02'),
(2, 2, 'Блокнот для заметок', 'Качественный блокнот для заметок с плотной бумагой. Идеален для студентов и профессионалов.', 420.00, 87, NULL, 0.0, 1, '2026-01-16 14:20:00', '2026-02-27 12:11:02'),
(3, 1, 'Гелевая ручка черная', 'Прецизионная гелевая ручка с ровным письмом. Пишет четко и ярко.', 150.00, 200, NULL, 0.0, 1, '2026-01-17 09:15:00', '2026-02-27 12:11:02'),
(4, 2, 'Линейка пластиковая', 'Прозрачная пластиковая линейка для измерений и черчения. Удобна в использовании.', 49.00, 320, NULL, 0.0, 1, '2026-01-18 11:45:00', '2026-02-27 12:11:02'),
(5, 3, 'Рюкзак школьный', 'Стильный и практичный школьный рюкзак с ортопедической спиной.', 2870.00, 42, NULL, 0.0, 1, '2026-01-19 13:00:00', '2026-02-27 12:11:02'),
(6, 4, 'Набор красок gouache', 'Набор качественных гуашевых красок для творчества. Яркие и насыщенные цвета.', 360.00, 95, NULL, 0.0, 1, '2026-01-20 15:30:00', '2026-02-27 12:11:02'),
(7, 4, 'Набор маркеров', 'Яркие маркеры для рисования и творчества. Не высыхают длительное время.', 650.00, 128, NULL, 0.0, 1, '2026-01-21 10:00:00', '2026-02-27 12:11:02'),
(8, 1, 'Механический карандаш', 'Надежный механический карандаш для черчения и письма. Удобен в использовании.', 200.00, 180, NULL, 0.0, 1, '2026-01-22 12:15:00', '2026-02-27 12:11:02'),
(9, 1, 'Набор цветных карандашей', 'Удобный набор из множества цветов. Идеален для школы и творчества.', 340.00, 76, NULL, 0.0, 1, '2026-01-23 14:45:00', '2026-02-27 12:11:02'),
(10, 2, 'Ластик мягкий', 'Универсальный ластик для карандаша. Стирает не оставляя следов.', 75.00, 250, NULL, 0.0, 1, '2026-01-24 09:20:00', '2026-02-27 12:11:02'),
(11, 2, 'Блокнот клеевой', 'Удобный блокнот с клеевым слоем для быстрых заметок.', 125.00, 200, NULL, 0.0, 1, '2026-01-25 10:00:00', '2026-02-27 12:11:02'),
(12, 4, 'Палитра для красок', 'Удобная палитра для смешивания красок и акварели.', 95.00, 180, NULL, 0.0, 1, '2026-01-25 11:15:00', '2026-02-27 12:11:02'),
(13, 1, 'Набор кистей', 'Профессиональный набор кистей для живописи и творчества.', 450.00, 65, NULL, 0.0, 1, '2026-01-25 12:30:00', '2026-02-27 12:11:02'),
(14, 2, 'Альбом для рисования', 'Высокачественный альбом с плотной бумагой для рисования.', 280.00, 110, NULL, 0.0, 1, '2026-01-25 13:45:00', '2026-02-27 12:11:02'),
(15, 1, 'Ножницы детские', 'Безопасные ножницы для детей с округленными концами.', 120.00, 150, NULL, 0.0, 1, '2026-01-25 14:00:00', '2026-02-27 12:11:02'),
(16, 3, 'Пенал школьный', 'Удобный пенал для хранения ручек и карандашей.', 310.00, 95, NULL, 0.0, 1, '2026-01-25 15:15:00', '2026-02-27 12:11:02'),
(17, 1, 'Клей-карандаш', 'Клей-крандаш для бумаги и картона.', 45.00, 300, NULL, 0.0, 1, '2026-01-25 16:30:00', '2026-02-27 12:11:02');

-- --------------------------------------------------------

--
-- Структура таблицы `product_colors`
--

CREATE TABLE `product_colors` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hex_code` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `product_colors`
--

INSERT INTO `product_colors` (`id`, `product_id`, `name`, `hex_code`) VALUES
(1, 1, 'Синий', '#1d4ed8'),
(2, 1, 'Чёрный', '#111827'),
(3, 1, 'Красный', '#dc2626'),
(4, 1, 'Зелёный', '#16a34a'),
(5, 3, 'Чёрный', '#111827'),
(6, 3, 'Синий', '#1d4ed8');

-- --------------------------------------------------------

--
-- Структура таблицы `product_images`
--

CREATE TABLE `product_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_path`) VALUES
(1, 1, '/assets/product_images/pens/ballpoint pen.webp'),
(2, 2, '/assets/product_images/notebooks/notebook-12-sh.webp'),
(3, 3, '/assets/product_images/pens/gel_pen_black.webp'),
(4, 4, '/assets/product_images/ruler/ruler.webp'),
(5, 5, '/assets/product_images/backpacks/backpack.webp'),
(6, 6, '/assets/product_images/gouache/set-gouache.webp'),
(7, 7, '/assets/product_images/markers/set-markers.webp'),
(8, 8, '/assets/product_images/pencils/mechanical pencil.webp'),
(9, 9, '/assets/product_images/pencils/set_pencil.webp'),
(10, 10, '/assets/product_images/rubber/rubber.webp'),
(11, 11, '/assets/product_images/notepads/notepad_white.webp'),
(12, 12, '/assets/product_images/palette/color-palette.webp'),
(13, 13, '/assets/product_images/brush/set-brush.webp'),
(14, 14, '/assets/product_images/albums/album.webp'),
(15, 15, '/assets/product_images/scissors/scissors-kid.webp'),
(16, 16, '/assets/product_images/pencil-box/pencil-box.webp'),
(17, 17, '/assets/product_images/glue-sctick/glue-stick.webp');

-- --------------------------------------------------------

--
-- Структура таблицы `promotions`
--

CREATE TABLE `promotions` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_start` date NOT NULL,
  `date_end` date DEFAULT NULL,
  `discount_percent` tinyint(3) UNSIGNED NOT NULL,
  `apply_scope` enum('products','categories') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'categories',
  `status` enum('draft','active','finished') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `promotions`
--

INSERT INTO `promotions` (`id`, `title`, `short_text`, `image_path`, `date_start`, `date_end`, `discount_percent`, `apply_scope`, `status`) VALUES
(1, '30% на ручки, карандаши и блокноты', 'Скидка на письменные принадлежности и блокноты', '/assets/banners/promo-writing.png', '2026-02-01', '2026-02-25', 30, 'categories', 'finished'),
(2, '20% на краски, кисти и альбомы', 'Товары для творчества по спеццене', '/assets/banners/promo-art.png', '2025-12-01', '2026-01-31', 20, 'categories', 'finished'),
(3, '15% на ручки', 'Скидки на ручки', '/assets/banners/promo-pens.png', '2026-01-05', '2026-02-20', 15, 'categories', 'finished'),
(4, '20% на категорию «органайзеры»', 'Скидка на органайзеры и аксессуары для порядка', '/assets/banners/promo-organizers.png', '2025-11-01', '2025-12-15', 20, 'categories', 'finished'),
(5, '20% на школьные принадлежности', 'Скидка на школьные принадлежности', '/assets/banners/promo-school.webp', '2026-02-01', '2026-03-01', 20, 'products', 'active');

--
-- Триггеры `promotions`
--
DELIMITER $$
CREATE TRIGGER `tr_promotions_scope_au` AFTER UPDATE ON `promotions` FOR EACH ROW BEGIN
    IF NEW.`apply_scope` = 'products'
       AND EXISTS (SELECT 1 FROM `promotion_categories` pc WHERE pc.`promotion_id` = NEW.`id` LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'products-scope promotion cannot have category links';
    END IF;

    IF NEW.`apply_scope` = 'categories'
       AND EXISTS (SELECT 1 FROM `promotion_products` pp WHERE pp.`promotion_id` = NEW.`id` LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'categories-scope promotion cannot have product links';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_promotions_set_status_bi` BEFORE INSERT ON `promotions` FOR EACH ROW BEGIN
    IF NEW.`date_end` IS NOT NULL AND NEW.`date_end` < NEW.`date_start` THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'date_end must be greater than or equal to date_start';
    END IF;

    IF NEW.`discount_percent` = 0 OR NEW.`discount_percent` > 90 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'discount_percent must be between 1 and 90';
    END IF;

    IF NEW.`apply_scope` NOT IN ('products', 'categories') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'apply_scope must be products or categories';
    END IF;

    SET NEW.`status` = CASE
        WHEN NEW.`date_start` > CURDATE() THEN 'draft'
        WHEN NEW.`date_end` IS NOT NULL AND NEW.`date_end` < CURDATE() THEN 'finished'
        ELSE 'active'
    END;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_promotions_set_status_bu` BEFORE UPDATE ON `promotions` FOR EACH ROW BEGIN
    IF NEW.`date_end` IS NOT NULL AND NEW.`date_end` < NEW.`date_start` THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'date_end must be greater than or equal to date_start';
    END IF;

    IF NEW.`discount_percent` = 0 OR NEW.`discount_percent` > 90 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'discount_percent must be between 1 and 90';
    END IF;

    IF NEW.`apply_scope` NOT IN ('products', 'categories') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'apply_scope must be products or categories';
    END IF;

    SET NEW.`status` = CASE
        WHEN NEW.`date_start` > CURDATE() THEN 'draft'
        WHEN NEW.`date_end` IS NOT NULL AND NEW.`date_end` < CURDATE() THEN 'finished'
        ELSE 'active'
    END;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `promotion_categories`
--

CREATE TABLE `promotion_categories` (
  `promotion_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `promotion_categories`
--

INSERT INTO `promotion_categories` (`promotion_id`, `category_id`) VALUES
(1, 1),
(1, 2),
(2, 4),
(4, 3),
(6, 4);

--
-- Триггеры `promotion_categories`
--
DELIMITER $$
CREATE TRIGGER `tr_promotion_categories_scope_bi` BEFORE INSERT ON `promotion_categories` FOR EACH ROW BEGIN
    IF EXISTS (
        SELECT 1
        FROM `promotions` p
        WHERE p.`id` = NEW.`promotion_id`
          AND p.`apply_scope` <> 'categories'
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'promotion apply_scope must be categories for promotion_categories';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_promotion_categories_scope_bu` BEFORE UPDATE ON `promotion_categories` FOR EACH ROW BEGIN
    IF EXISTS (
        SELECT 1
        FROM `promotions` p
        WHERE p.`id` = NEW.`promotion_id`
          AND p.`apply_scope` <> 'categories'
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'promotion apply_scope must be categories for promotion_categories';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `promotion_products`
--

CREATE TABLE `promotion_products` (
  `promotion_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `promotion_products`
--

INSERT INTO `promotion_products` (`promotion_id`, `product_id`) VALUES
(5, 1),
(5, 2),
(5, 3),
(5, 4),
(5, 5),
(5, 16);

--
-- Триггеры `promotion_products`
--
DELIMITER $$
CREATE TRIGGER `tr_promotion_products_scope_bi` BEFORE INSERT ON `promotion_products` FOR EACH ROW BEGIN
    IF EXISTS (SELECT 1 FROM `promotion_categories` pc WHERE pc.`promotion_id` = NEW.`promotion_id` LIMIT 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'promotion cannot target both categories and products';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM `promotions` p
        WHERE p.`id` = NEW.`promotion_id`
          AND p.`apply_scope` <> 'products'
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'promotion apply_scope must be products for promotion_products';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_promotion_products_scope_bu` BEFORE UPDATE ON `promotion_products` FOR EACH ROW BEGIN
    IF EXISTS (
        SELECT 1
        FROM `promotion_categories` pc
        WHERE pc.`promotion_id` = NEW.`promotion_id`
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'promotion cannot target both categories and products';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM `promotions` p
        WHERE p.`id` = NEW.`promotion_id`
          AND p.`apply_scope` <> 'products'
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'promotion apply_scope must be products for promotion_products';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `reviews`
--

CREATE TABLE `reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `roles`
--

INSERT INTO `roles` (`id`, `name`) VALUES
(1, 'admin'),
(2, 'user');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `password_hash`, `phone`, `created_at`) VALUES
(11, 1, 'Admin', 'cantsaria@yandex.ru', '$2y$12$MOlqTa2Wh4Uq5Bp1IHPaTu9H7Ctr4DStozbvHfXIUrNDMdL2/qrfG', NULL, '2026-01-01 10:05:00');

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `v_active_promotions`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `v_active_promotions` (
`id` int(10) unsigned
,`title` varchar(150)
,`short_text` varchar(255)
,`image_path` varchar(255)
,`apply_scope` enum('products','categories')
,`date_start` date
,`date_end` date
,`date_start_ru` varchar(10)
,`date_end_ru` varchar(10)
,`discount_percent` tinyint(3) unsigned
);

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `v_invalid_active_promotions`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `v_invalid_active_promotions` (
`promotion_id` int(10) unsigned
);

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `v_product_active_discounts`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `v_product_active_discounts` (
`product_id` int(10) unsigned
,`discount_percent` tinyint(3) unsigned
);

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `v_product_applicable_promotions`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `v_product_applicable_promotions` (
`product_id` int(11) unsigned
,`promotion_id` int(10) unsigned
,`discount_percent` tinyint(3) unsigned
);

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `v_product_best_discount`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `v_product_best_discount` (
`product_id` int(11) unsigned
,`promotion_id` int(10) unsigned
,`discount_percent` tinyint(3) unsigned
);

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `v_product_final_prices`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `v_product_final_prices` (
`id` int(10) unsigned
,`name` varchar(200)
,`price` decimal(10,2)
,`discount_percent` decimal(3,0)
,`final_price` decimal(18,6)
);

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `v_product_pricing`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `v_product_pricing` (
`id` int(10) unsigned
,`category_id` int(10) unsigned
,`name` varchar(200)
,`description` text
,`base_price` decimal(10,2)
,`stock_quantity` int(10) unsigned
,`pickup_available` tinyint(1)
,`rating` decimal(2,1)
,`is_active` tinyint(1)
,`created_at` timestamp
,`updated_at` timestamp
,`discount_percent` decimal(3,0)
,`promotion_id` int(10) unsigned
,`has_active_promotion` int(1)
,`final_price` decimal(15,2)
);

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `v_promotion_scope_products`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `v_promotion_scope_products` (
`promotion_id` int(11) unsigned
,`product_id` int(11) unsigned
);

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `v_promotion_status`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `v_promotion_status` (
`id` int(10) unsigned
,`title` varchar(150)
,`short_text` varchar(255)
,`image_path` varchar(255)
,`apply_scope` enum('products','categories')
,`date_start` date
,`date_end` date
,`date_start_ru` varchar(10)
,`date_end_ru` varchar(10)
,`discount_percent` tinyint(3) unsigned
,`status_cache` enum('draft','active','finished')
,`effective_status` varchar(8)
);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Индексы таблицы `delivery_methods`
--
ALTER TABLE `delivery_methods`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `favorites`
--
ALTER TABLE `favorites`
  ADD PRIMARY KEY (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`);

--
-- Индексы таблицы `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status_id` (`status_id`),
  ADD KEY `delivery_method_id` (`delivery_method_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Индексы таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_order_product` (`order_id`,`product_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `promotion_id` (`promotion_id`);

--
-- Индексы таблицы `order_statuses`
--
ALTER TABLE `order_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Индексы таблицы `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `is_active_created_at` (`is_active`,`created_at`),
  ADD KEY `price` (`price`);
ALTER TABLE `products` ADD FULLTEXT KEY `name_description` (`name`,`description`);

--
-- Индексы таблицы `product_colors`
--
ALTER TABLE `product_colors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_product_color_name` (`product_id`,`name`),
  ADD UNIQUE KEY `uniq_product_color_hex` (`product_id`,`hex_code`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_product_image_path` (`product_id`,`image_path`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_promotions_status_dates` (`status`,`date_start`,`date_end`),
  ADD KEY `idx_promotions_scope_dates` (`apply_scope`,`date_start`,`date_end`),
  ADD KEY `idx_promotions_dates` (`date_start`,`date_end`);

--
-- Индексы таблицы `promotion_categories`
--
ALTER TABLE `promotion_categories`
  ADD PRIMARY KEY (`promotion_id`,`category_id`),
  ADD KEY `idx_promotion_categories_category` (`category_id`,`promotion_id`);

--
-- Индексы таблицы `promotion_products`
--
ALTER TABLE `promotion_products`
  ADD PRIMARY KEY (`promotion_id`,`product_id`),
  ADD KEY `idx_promotion_products_product` (`product_id`,`promotion_id`);

--
-- Индексы таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `delivery_methods`
--
ALTER TABLE `delivery_methods`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `order_statuses`
--
ALTER TABLE `order_statuses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT для таблицы `product_colors`
--
ALTER TABLE `product_colors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT для таблицы `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

-- --------------------------------------------------------

--
-- Структура для представления `v_active_promotions`
--
DROP TABLE IF EXISTS `v_active_promotions`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_active_promotions`  AS SELECT `v`.`id` AS `id`, `v`.`title` AS `title`, `v`.`short_text` AS `short_text`, `v`.`image_path` AS `image_path`, `v`.`apply_scope` AS `apply_scope`, `v`.`date_start` AS `date_start`, `v`.`date_end` AS `date_end`, `v`.`date_start_ru` AS `date_start_ru`, `v`.`date_end_ru` AS `date_end_ru`, `v`.`discount_percent` AS `discount_percent` FROM `v_promotion_status` AS `v` WHERE ((`v`.`date_start` <= curdate()) AND (isnull(`v`.`date_end`) OR (`v`.`date_end` >= curdate()))) ;

-- --------------------------------------------------------

--
-- Структура для представления `v_invalid_active_promotions`
--
DROP TABLE IF EXISTS `v_invalid_active_promotions`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_invalid_active_promotions`  AS SELECT `p`.`id` AS `promotion_id` FROM `promotions` AS `p` WHERE ((curdate() between `p`.`date_start` and `p`.`date_end`) AND (((`p`.`apply_scope` = 'products') AND (not(exists(select 1 from `promotion_products` `pp` where (`pp`.`promotion_id` = `p`.`id`))))) OR ((`p`.`apply_scope` = 'categories') AND (not(exists(select 1 from `promotion_categories` `pc` where (`pc`.`promotion_id` = `p`.`id`))))))) ;

-- --------------------------------------------------------

--
-- Структура для представления `v_product_active_discounts`
--
DROP TABLE IF EXISTS `v_product_active_discounts`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_product_active_discounts`  AS SELECT `pp`.`product_id` AS `product_id`, max(`p`.`discount_percent`) AS `discount_percent` FROM (`promotion_products` `pp` join `promotions` `p` on(((`p`.`id` = `pp`.`promotion_id`) and (curdate() between `p`.`date_start` and `p`.`date_end`)))) GROUP BY `pp`.`product_id` ;

-- --------------------------------------------------------

--
-- Структура для представления `v_product_applicable_promotions`
--
DROP TABLE IF EXISTS `v_product_applicable_promotions`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_product_applicable_promotions`  AS SELECT `s`.`product_id` AS `product_id`, `ap`.`id` AS `promotion_id`, `ap`.`discount_percent` AS `discount_percent` FROM (`v_promotion_scope_products` `s` join `v_active_promotions` `ap` on((`ap`.`id` = `s`.`promotion_id`))) ;

-- --------------------------------------------------------

--
-- Структура для представления `v_product_best_discount`
--
DROP TABLE IF EXISTS `v_product_best_discount`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_product_best_discount`  AS SELECT `ap`.`product_id` AS `product_id`, min(`ap`.`promotion_id`) AS `promotion_id`, `ap`.`discount_percent` AS `discount_percent` FROM (`v_product_applicable_promotions` `ap` join (select `v_product_applicable_promotions`.`product_id` AS `product_id`,max(`v_product_applicable_promotions`.`discount_percent`) AS `discount_percent` from `v_product_applicable_promotions` group by `v_product_applicable_promotions`.`product_id`) `best` on(((`best`.`product_id` = `ap`.`product_id`) and (`best`.`discount_percent` = `ap`.`discount_percent`)))) GROUP BY `ap`.`product_id`, `ap`.`discount_percent` ;

-- --------------------------------------------------------

--
-- Структура для представления `v_product_final_prices`
--
DROP TABLE IF EXISTS `v_product_final_prices`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_product_final_prices`  AS SELECT `pr`.`id` AS `id`, `pr`.`name` AS `name`, `pr`.`price` AS `price`, coalesce(`d`.`discount_percent`,0) AS `discount_percent`, (`pr`.`price` * (1 - (coalesce(`d`.`discount_percent`,0) / 100))) AS `final_price` FROM (`products` `pr` left join `v_product_active_discounts` `d` on((`d`.`product_id` = `pr`.`id`))) ;

-- --------------------------------------------------------

--
-- Структура для представления `v_product_pricing`
--
DROP TABLE IF EXISTS `v_product_pricing`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_product_pricing`  AS SELECT `p`.`id` AS `id`, `p`.`category_id` AS `category_id`, `p`.`name` AS `name`, `p`.`description` AS `description`, `p`.`price` AS `base_price`, `p`.`stock_quantity` AS `stock_quantity`, `p`.`pickup_available` AS `pickup_available`, `p`.`rating` AS `rating`, `p`.`is_active` AS `is_active`, `p`.`created_at` AS `created_at`, `p`.`updated_at` AS `updated_at`, coalesce(`bd`.`discount_percent`,0) AS `discount_percent`, `bd`.`promotion_id` AS `promotion_id`, (case when isnull(`bd`.`promotion_id`) then 0 else 1 end) AS `has_active_promotion`, round((`p`.`price` * (1 - (coalesce(`bd`.`discount_percent`,0) / 100))),2) AS `final_price` FROM (`products` `p` left join `v_product_best_discount` `bd` on((`bd`.`product_id` = `p`.`id`))) ;

-- --------------------------------------------------------

--
-- Структура для представления `v_promotion_scope_products`
--
DROP TABLE IF EXISTS `v_promotion_scope_products`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_promotion_scope_products`  AS SELECT `pp`.`promotion_id` AS `promotion_id`, `pp`.`product_id` AS `product_id` FROM `promotion_products` AS `pp`union all select `pc`.`promotion_id` AS `promotion_id`,`p`.`id` AS `product_id` from (`promotion_categories` `pc` join `products` `p` on((`p`.`category_id` = `pc`.`category_id`)))  ;

-- --------------------------------------------------------

--
-- Структура для представления `v_promotion_status`
--
DROP TABLE IF EXISTS `v_promotion_status`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_promotion_status`  AS SELECT `p`.`id` AS `id`, `p`.`title` AS `title`, `p`.`short_text` AS `short_text`, `p`.`image_path` AS `image_path`, `p`.`apply_scope` AS `apply_scope`, `p`.`date_start` AS `date_start`, `p`.`date_end` AS `date_end`, date_format(`p`.`date_start`,'%d.%m.%Y') AS `date_start_ru`, (case when isnull(`p`.`date_end`) then NULL else date_format(`p`.`date_end`,'%d.%m.%Y') end) AS `date_end_ru`, `p`.`discount_percent` AS `discount_percent`, `p`.`status` AS `status_cache`, (case when (`p`.`date_start` > curdate()) then 'draft' when ((`p`.`date_end` is not null) and (`p`.`date_end` < curdate())) then 'finished' else 'active' end) AS `effective_status` FROM `promotions` AS `p` ;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `favorites`
--
ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`status_id`) REFERENCES `order_statuses` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`delivery_method_id`) REFERENCES `delivery_methods` (`id`) ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_colors`
--
ALTER TABLE `product_colors`
  ADD CONSTRAINT `product_colors_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
