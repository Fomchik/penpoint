-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Апр 24 2026 г., 20:39
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
    WHERE (
        `date_start` > CURDATE() AND `status` <> 'draft'
    ) OR (
        `date_end` IS NOT NULL AND `date_end` < CURDATE() AND `status` <> 'finished'
    ) OR (
        `date_start` <= CURDATE() AND (`date_end` IS NULL OR `date_end` >= CURDATE()) AND `status` <> 'active'
    );
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `attributes`
--

CREATE TABLE `attributes` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `attributes`
--

INSERT INTO `attributes` (`id`, `name`, `created_at`) VALUES
(1, 'Цвет', '2026-04-08 15:10:08'),
(2, 'Размер', '2026-04-08 15:10:08'),
(3, 'Формат', '2026-04-08 15:10:08'),
(4, 'Объем', '2026-04-08 15:10:08'),
(5, 'Толщина', '2026-04-08 15:10:08'),
(6, 'Количество в наборе', '2026-04-08 15:10:08'),
(7, 'Количество листов', '2026-04-08 15:10:08'),
(8, 'Плотность бумаги', '2026-04-08 15:10:08'),
(9, 'Тип бумаги', '2026-04-08 15:10:08'),
(10, 'Тип крепления', '2026-04-08 15:10:08'),
(11, 'Тип обложки', '2026-04-08 15:10:08'),
(12, 'Жесткость', '2026-04-08 15:10:08'),
(73, 'Страна', '2026-04-18 12:01:13');

-- --------------------------------------------------------

--
-- Структура таблицы `auth_audit_logs`
--

CREATE TABLE `auth_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `details` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `auth_audit_logs`
--

INSERT INTO `auth_audit_logs` (`id`, `user_id`, `email`, `ip_address`, `event_type`, `success`, `details`, `created_at`) VALUES
(1, 12, 'rakvdele45@gmail.com', '127.0.0.1', 'register', 1, NULL, '2026-04-08 13:12:39'),
(2, 11, 'cantsaria@yandex.ru', '127.0.0.1', 'login', 1, NULL, '2026-04-08 13:14:01'),
(3, 11, 'cantsaria@yandex.ru', '127.0.0.1', 'login', 1, NULL, '2026-04-08 14:13:24'),
(4, 11, 'cantsaria@yandex.ru', '127.0.0.1', 'login', 1, NULL, '2026-04-15 16:11:07'),
(5, 11, 'cantsaria@yandex.ru', '127.0.0.1', 'login', 1, NULL, '2026-04-18 11:28:23'),
(6, 11, 'cantsaria@yandex.ru', '127.0.0.1', 'login', 1, NULL, '2026-04-20 17:38:15'),
(7, 11, 'cantsaria@yandex.ru', '127.0.0.1', 'login', 1, NULL, '2026-04-21 05:15:41'),
(8, 11, 'cantsaria@yandex.ru', '127.0.0.1', 'login', 1, NULL, '2026-04-21 18:12:36'),
(9, 11, 'cantsaria@yandex.ru', '127.0.0.1', 'login', 1, NULL, '2026-04-22 06:44:09'),
(10, 11, 'cantsaria@yandex.ru', '127.0.0.1', 'login', 1, NULL, '2026-04-22 07:07:39'),
(11, 11, 'cantsaria@yandex.ru', '127.0.0.1', 'login', 1, NULL, '2026-04-23 04:23:59'),
(12, 11, 'cantsaria@yandex.ru', '127.0.0.1', 'login', 1, NULL, '2026-04-23 04:34:31'),
(13, 11, 'cantsaria@yandex.ru', '127.0.0.1', 'login', 1, NULL, '2026-04-23 15:29:48'),
(14, 11, 'cantsaria@yandex.ru', '127.0.0.1', 'login', 1, NULL, '2026-04-24 07:19:57');

-- --------------------------------------------------------

--
-- Структура таблицы `auth_rate_limits`
--

CREATE TABLE `auth_rate_limits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `action_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `auth_rate_limits`
--

INSERT INTO `auth_rate_limits` (`id`, `action_key`, `ip_address`, `user_id`, `created_at`) VALUES
(15, 'login', '127.0.0.1', NULL, '2026-04-24 07:19:57');

-- --------------------------------------------------------

--
-- Структура таблицы `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `variant_id` int(10) UNSIGNED DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Структура таблицы `email_change_tokens`
--

CREATE TABLE `email_change_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `new_email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `email_verification_tokens`
--

CREATE TABLE `email_verification_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('new','in_progress','done') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `status_updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `feedback`
--

INSERT INTO `feedback` (`id`, `name`, `email`, `phone`, `subject`, `message`, `status`, `status_updated_at`, `created_at`) VALUES
(1, 'ilya pasichenko', 'pasichilya@yandex.ru', NULL, 'Вопрос по заказу', 'тест', 'done', '2026-04-18 11:59:24', '2026-04-15 16:37:50'),
(2, 'ilya pasichenko', 'pasichilya@yandex.ru', '+79178437700', 'Вопрос по заказу', 'тест', 'done', '2026-04-22 06:45:36', '2026-04-22 06:43:49'),
(3, 'ilya pasichenko', 'pasichilya@yandex.ru', '+79178437700', 'Вопрос по заказу', 'тестирование', 'done', '2026-04-22 07:08:51', '2026-04-22 06:51:29'),
(4, 'ilya pasichenko', 'pasichilya@yandex.ru', '+79178437700', 'Вопрос по заказу', 'тестирование', 'done', '2026-04-22 07:09:02', '2026-04-22 07:07:22');

-- --------------------------------------------------------

--
-- Структура таблицы `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `customer_name` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_id` int(10) UNSIGNED NOT NULL,
  `delivery_method_id` int(10) UNSIGNED NOT NULL,
  `payment_method` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'online',
  `total_price` decimal(10,2) NOT NULL,
  `delivery_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `address` text COLLATE utf8mb4_unicode_ci,
  `payment_status` enum('not_required','pending_payment','paid','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_required',
  `payment_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_idempotence_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `customer_name`, `customer_phone`, `customer_email`, `status_id`, `delivery_method_id`, `payment_method`, `total_price`, `delivery_price`, `discount_total`, `address`, `payment_status`, `payment_id`, `payment_idempotence_key`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 11, 'Admin', '+79178437700', 'cantsaria@yandex.ru', 5, 1, 'online', 120.00, 0.00, 0.00, 'проспект Ленина, 50', 'failed', NULL, NULL, NULL, '2026-04-15 16:53:11', '2026-04-19 18:05:27'),
(2, 11, 'Admin', '+79178437700', 'cantsaria@yandex.ru', 5, 1, 'online', 992.00, 0.00, 248.00, 'просп. Маршала Жукова, 5', 'failed', NULL, NULL, NULL, '2026-04-22 06:47:47', '2026-04-22 06:52:22'),
(3, 11, 'Admin', '+79178437700', 'cantsaria@yandex.ru', 5, 1, 'online', 120.00, 0.00, 0.00, 'улица Рионская ул., 3', 'failed', NULL, NULL, NULL, '2026-04-22 07:10:31', '2026-04-22 07:10:50');

-- --------------------------------------------------------

--
-- Структура таблицы `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `variant_id` int(10) UNSIGNED DEFAULT NULL,
  `attributes_json` text COLLATE utf8mb4_unicode_ci,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `variant_label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
(6, 'В пути'),
(4, 'Доставлен'),
(1, 'Новый'),
(5, 'Отменён'),
(3, 'Отправлен');

-- --------------------------------------------------------

--
-- Структура таблицы `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `has_color_variants` tinyint(1) NOT NULL DEFAULT '0',
  `parameter_stock_mode` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'strict_min',
  `rating` decimal(2,1) DEFAULT '0.0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `stock_quantity`, `pickup_available`, `has_color_variants`, `parameter_stock_mode`, `rating`, `is_active`, `created_at`, `updated_at`) VALUES
(20, 0, 'Клей-карандаш', 'Клей-карандаш на основе PVP для склеивания бумаги, картона и фотографий.', 45.00, 39, 1, 0, 'strict_min', 0.0, 1, '2026-04-24 15:18:33', '2026-04-24 15:18:33'),
(21, 0, 'Альбом для рисования Brauberg 32 листа', 'Альбом для рисования. Жесткая подложка позволяет рисовать на весу. Листы имеют перфорацию для аккуратного отрыва.', 165.00, 23, 1, 0, 'strict_min', 0.0, 1, '2026-04-24 17:28:16', '2026-04-24 17:28:16'),
(22, 2, 'Альбом для рисования Brauberg 32 листа', 'Альбом для рисования. Жесткая подложка позволяет рисовать на весу. Листы имеют перфорацию для аккуратного отрыва.', 165.00, 23, 1, 0, 'strict_min', 0.0, 1, '2026-04-24 17:31:06', '2026-04-24 17:31:06'),
(23, 2, 'Альбом для рисования Brauberg 32 листа', 'Альбом для рисования. Жесткая подложка позволяет рисовать на весу. Листы имеют перфорацию для аккуратного отрыва.', 165.00, 23, 1, 0, 'strict_min', 0.0, 1, '2026-04-24 17:33:42', '2026-04-24 17:33:42');

-- --------------------------------------------------------

--
-- Структура таблицы `product_categories`
--

CREATE TABLE `product_categories` (
  `product_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `product_categories`
--

INSERT INTO `product_categories` (`product_id`, `category_id`) VALUES
(21, 2),
(22, 2),
(23, 2),
(22, 4),
(23, 4),
(20, 5);

-- --------------------------------------------------------

--
-- Структура таблицы `product_characteristics`
--

CREATE TABLE `product_characteristics` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(20, 20, '/assets/product_images/accessories/20/main.webp');

-- --------------------------------------------------------

--
-- Структура таблицы `product_parameters`
--

CREATE TABLE `product_parameters` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `values_text` text COLLATE utf8mb4_unicode_ci,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `use_for_variants` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `product_parameter_values`
--

CREATE TABLE `product_parameter_values` (
  `id` int(10) UNSIGNED NOT NULL,
  `parameter_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int(10) UNSIGNED DEFAULT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `product_variants`
--

CREATE TABLE `product_variants` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `attributes_json` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `product_variant_images`
--

CREATE TABLE `product_variant_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `variant_id` int(10) UNSIGNED DEFAULT NULL,
  `parameter_code` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parameter_value` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `product_variant_values`
--

CREATE TABLE `product_variant_values` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `variant_id` int(10) UNSIGNED DEFAULT NULL,
  `attribute_id` int(10) UNSIGNED NOT NULL,
  `value` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `promotions`
--

CREATE TABLE `promotions` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_main` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_list` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `promotion_type` enum('regular','seasonal') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'regular',
  `date_start` date NOT NULL,
  `date_end` date DEFAULT NULL,
  `discount_percent` tinyint(3) UNSIGNED NOT NULL,
  `apply_scope` enum('products','categories') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'categories',
  `status` enum('draft','active','finished') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_published` tinyint(1) NOT NULL DEFAULT '1'
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
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `email_verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `password_hash`, `phone`, `is_verified`, `email_verified_at`, `created_at`, `updated_at`) VALUES
(11, 1, 'Admin', 'cantsaria@yandex.ru', '$2y$12$MOlqTa2Wh4Uq5Bp1IHPaTu9H7Ctr4DStozbvHfXIUrNDMdL2/qrfG', NULL, 0, NULL, '2026-01-01 10:05:00', '2026-04-19 18:05:28'),
(12, 2, 'Foma773', 'rakvdele45@gmail.com', '$2y$12$KLH9R2.NTlJEeiwWeMy3POaTM/nQNOiPUcXdJTpQTft9k.VNuQvZu', NULL, 0, NULL, '2026-04-08 13:12:38', '2026-04-19 18:05:28');

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
-- Индексы таблицы `attributes`
--
ALTER TABLE `attributes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_attributes_name` (`name`);

--
-- Индексы таблицы `auth_audit_logs`
--
ALTER TABLE `auth_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_time` (`event_type`,`created_at`),
  ADD KEY `idx_user_time` (`user_id`,`created_at`);

--
-- Индексы таблицы `auth_rate_limits`
--
ALTER TABLE `auth_rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action_ip_time` (`action_key`,`ip_address`,`created_at`),
  ADD KEY `idx_action_user_time` (`action_key`,`user_id`,`created_at`);

--
-- Индексы таблицы `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `variant_id` (`variant_id`);

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
-- Индексы таблицы `email_change_tokens`
--
ALTER TABLE `email_change_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_email_change_token_hash` (`token_hash`),
  ADD KEY `idx_email_change_user_expire` (`user_id`,`expires_at`);

--
-- Индексы таблицы `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_token_hash` (`token_hash`),
  ADD KEY `idx_user_expire` (`user_id`,`expires_at`);

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
  ADD UNIQUE KEY `uniq_order_product_variant` (`order_id`,`product_id`,`variant_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `promotion_id` (`promotion_id`),
  ADD KEY `order_items_ibfk_4` (`variant_id`);

--
-- Индексы таблицы `order_statuses`
--
ALTER TABLE `order_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Индексы таблицы `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_token_hash` (`token_hash`),
  ADD KEY `idx_user_expire` (`user_id`,`expires_at`);

--
-- Индексы таблицы `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `is_active_price` (`is_active`,`price`),
  ADD KEY `is_active_created_at` (`is_active`,`created_at`),
  ADD KEY `price` (`price`);
ALTER TABLE `products` ADD FULLTEXT KEY `name_description` (`name`,`description`);

--
-- Индексы таблицы `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`product_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Индексы таблицы `product_characteristics`
--
ALTER TABLE `product_characteristics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_sort` (`product_id`,`sort_order`,`id`);

--
-- Индексы таблицы `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_product_image_path` (`product_id`,`image_path`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `product_parameters`
--
ALTER TABLE `product_parameters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_sort` (`product_id`,`sort_order`),
  ADD KEY `idx_product_code` (`product_id`,`code`);

--
-- Индексы таблицы `product_parameter_values`
--
ALTER TABLE `product_parameter_values`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parameter_sort` (`parameter_id`,`sort_order`);

--
-- Индексы таблицы `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_active` (`product_id`,`is_active`),
  ADD KEY `idx_product_price` (`product_id`,`price`);

--
-- Индексы таблицы `product_variant_images`
--
ALTER TABLE `product_variant_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_variant_images_product` (`product_id`),
  ADD KEY `idx_product_variant_images_variant` (`variant_id`);

--
-- Индексы таблицы `product_variant_values`
--
ALTER TABLE `product_variant_values`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `variant_id` (`variant_id`),
  ADD KEY `attribute_id` (`attribute_id`);

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
  ADD UNIQUE KEY `uniq_product_user` (`product_id`,`user_id`),
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
-- AUTO_INCREMENT для таблицы `attributes`
--
ALTER TABLE `attributes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1886;

--
-- AUTO_INCREMENT для таблицы `auth_audit_logs`
--
ALTER TABLE `auth_audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT для таблицы `auth_rate_limits`
--
ALTER TABLE `auth_rate_limits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT для таблицы `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT для таблицы `email_change_tokens`
--
ALTER TABLE `email_change_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `email_verification_tokens`
--
ALTER TABLE `email_verification_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `order_statuses`
--
ALTER TABLE `order_statuses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT для таблицы `product_characteristics`
--
ALTER TABLE `product_characteristics`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT для таблицы `product_parameters`
--
ALTER TABLE `product_parameters`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `product_parameter_values`
--
ALTER TABLE `product_parameter_values`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT для таблицы `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `product_variant_images`
--
ALTER TABLE `product_variant_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `product_variant_values`
--
ALTER TABLE `product_variant_values`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_product_pricing`  AS SELECT `p`.`id` AS `id`, `p`.`category_id` AS `category_id`, `p`.`name` AS `name`, `p`.`description` AS `description`, `p`.`price` AS `base_price`, `p`.`stock_quantity` AS `stock_quantity`, `p`.`pickup_available` AS `pickup_available`, `p`.`rating` AS `rating`, `p`.`is_active` AS `is_active`, `p`.`created_at` AS `created_at`, `p`.`updated_at` AS `updated_at`, coalesce(max(`bd`.`discount_percent`),0) AS `discount_percent`, min(`bd`.`promotion_id`) AS `promotion_id`, (case when isnull(max(`bd`.`promotion_id`)) then 0 else 1 end) AS `has_active_promotion`, round((`p`.`price` * (1 - (coalesce(max(`bd`.`discount_percent`),0) / 100))),2) AS `final_price` FROM (`products` `p` left join `v_product_best_discount` `bd` on((`bd`.`product_id` = `p`.`id`))) GROUP BY `p`.`id` ;

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
-- Ограничения внешнего ключа таблицы `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_3` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL;

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
  ADD CONSTRAINT `order_items_ibfk_3` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `order_items_ibfk_4` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `product_categories`
--
ALTER TABLE `product_categories`
  ADD CONSTRAINT `product_categories_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_parameter_values`
--
ALTER TABLE `product_parameter_values`
  ADD CONSTRAINT `product_parameter_values_ibfk_1` FOREIGN KEY (`parameter_id`) REFERENCES `product_parameters` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_variant_values`
--
ALTER TABLE `product_variant_values`
  ADD CONSTRAINT `product_variant_values_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_variant_values_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_variant_values_ibfk_3` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `promotion_categories`
--
ALTER TABLE `promotion_categories`
  ADD CONSTRAINT `promotion_categories_ibfk_1` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `promotion_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `promotion_products`
--
ALTER TABLE `promotion_products`
  ADD CONSTRAINT `promotion_products_ibfk_1` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `promotion_products_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
