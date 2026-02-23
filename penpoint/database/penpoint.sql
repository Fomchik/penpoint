-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Хост: localhost
-- Время создания: Янв 28 2026 г., 19:19
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

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `price` decimal(10,2) NOT NULL
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
  `rating` decimal(2,1) DEFAULT '0.0',
  `is_active` tinyint(1) DEFAULT '1',
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
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

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
  ADD KEY `price` (`price`),
  ADD FULLTEXT KEY `name_description` (`name`,`description`);

--
-- Индексы таблицы `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Индексы таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `rating` (`rating`);

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
-- AUTO_INCREMENT для таблицы `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT для таблицы `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Ограничения внешнего ключа таблицы `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;

--
-- Ограничения внешнего ключа таблицы `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

-- --------------------------------------------------------
--
-- Структура таблицы `product_colors`
--
CREATE TABLE `product_colors` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hex_code` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_colors_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Ограничения внешнего ключа таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;

--
-- Дамп данных таблицы `categories`
--
INSERT INTO `categories` (`id`, `name`, `slug`) VALUES
(1, 'Письменные принадлежности', 'writing'),
(2, 'Бумажная продукция', 'paper'),
(3, 'Органайзеры', 'organizers'),
(4, 'Творчество', 'creativity'),
(5, 'Аксессуары', 'accessories');

--
-- Дамп данных таблицы `products`
--
INSERT INTO `products` (`id`, `category_id`, `name`, `description`, `price`, `stock_quantity`, `rating`, `is_active`, `created_at`) VALUES
(1, 1, 'Шариковая ручка, синяя, 0.7 мм', 'Шариковая ручка с качественным стержнем для повседневного письма.', 84.00, 4, 4.0, 1, '2026-01-15 10:30:00'),
(2, 2, 'Блокнот для заметок', 'Качественный блокнот для заметок с плотной бумагой. Идеален для студентов и профессионалов.', 420.00, 87, 4.5, 1, '2026-01-16 14:20:00'),
(3, 1, 'Гелевая ручка черная', 'Прецизионная гелевая ручка с ровным письмом. Пишет четко и ярко.', 150.00, 200, 4.5, 1, '2026-01-17 09:15:00'),
(4, 2, 'Линейка пластиковая', 'Прозрачная пластиковая линейка для измерений и черчения. Удобна в использовании.', 49.00, 320, 3.5, 1, '2026-01-18 11:45:00'),
(5, 3, 'Рюкзак школьный', 'Стильный и практичный школьный рюкзак с ортопедической спиной.', 2870.00, 42, 5.0, 1, '2026-01-19 13:00:00'),
(6, 4, 'Набор красок gouache', 'Набор качественных гуашевых красок для творчества. Яркие и насыщенные цвета.', 360.00, 95, 4.0, 1, '2026-01-20 15:30:00'),
(7, 4, 'Набор маркеров', 'Яркие маркеры для рисования и творчества. Не высыхают длительное время.', 650.00, 128, 4.0, 1, '2026-01-21 10:00:00'),
(8, 1, 'Механический карандаш', 'Надежный механический карандаш для черчения и письма. Удобен в использовании.', 200.00, 180, 4.5, 1, '2026-01-22 12:15:00'),
(9, 1, 'Набор цветных карандашей', 'Удобный набор из множества цветов. Идеален для школы и творчества.', 340.00, 76, 4.0, 1, '2026-01-23 14:45:00'),
(10, 2, 'Ластик мягкий', 'Универсальный ластик для карандаша. Стирает не оставляя следов.', 75.00, 250, 4.0, 1, '2026-01-24 09:20:00'),
(11, 2, 'Блокнот клеевой', 'Удобный блокнот с клеевым слоем для быстрых заметок.', 125.00, 200, 4.5, 1, '2026-01-25 10:00:00'),
(12, 4, 'Палитра для красок', 'Удобная палитра для смешивания красок и акварели.', 95.00, 180, 4.0, 1, '2026-01-25 11:15:00'),
(13, 1, 'Набор кистей', 'Профессиональный набор кистей для живописи и творчества.', 450.00, 65, 4.5, 1, '2026-01-25 12:30:00'),
(14, 2, 'Альбом для рисования', 'Высокачественный альбом с плотной бумагой для рисования.', 280.00, 110, 4.0, 1, '2026-01-25 13:45:00'),
(15, 1, 'Ножницы детские', 'Безопасные ножницы для детей с округленными концами.', 120.00, 150, 4.5, 1, '2026-01-25 14:00:00'),
(16, 3, 'Пенал школьный', 'Удобный пенал для хранения ручек и карандашей.', 310.00, 95, 4.0, 1, '2026-01-25 15:15:00'),
(17, 1, 'Клей-карандаш', 'Удобный клей-карандаш для склеивания бумаги и картона.', 45.00, 300, 3.5, 1, '2026-01-25 16:30:00');

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

--
-- Дамп данных таблицы `users` (нужно для FK в reviews)
--
INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `password_hash`, `phone`, `created_at`) VALUES
(1, 2, 'User 1', 'user1@penpoint.local', '$2y$10$seedseedseedseedseedseedseedseedseedseedse', NULL, '2026-01-01 10:00:00'),
(2, 2, 'User 2', 'user2@penpoint.local', '$2y$10$seedseedseedseedseedseedseedseedseedseedse', NULL, '2026-01-01 10:00:00'),
(3, 2, 'User 3', 'user3@penpoint.local', '$2y$10$seedseedseedseedseedseedseedseedseedseedse', NULL, '2026-01-01 10:00:00'),
(4, 2, 'User 4', 'user4@penpoint.local', '$2y$10$seedseedseedseedseedseedseedseedseedseedse', NULL, '2026-01-01 10:00:00'),
(5, 2, 'User 5', 'user5@penpoint.local', '$2y$10$seedseedseedseedseedseedseedseedseedseedse', NULL, '2026-01-01 10:00:00'),
(6, 2, 'User 6', 'user6@penpoint.local', '$2y$10$seedseedseedseedseedseedseedseedseedseedse', NULL, '2026-01-01 10:00:00'),
(7, 2, 'User 7', 'user7@penpoint.local', '$2y$10$seedseedseedseedseedseedseedseedseedseedse', NULL, '2026-01-01 10:00:00'),
(8, 2, 'User 8', 'user8@penpoint.local', '$2y$10$seedseedseedseedseedseedseedseedseedseedse', NULL, '2026-01-01 10:00:00'),
(9, 2, 'User 9', 'user9@penpoint.local', '$2y$10$seedseedseedseedseedseedseedseedseedseedse', NULL, '2026-01-01 10:00:00'),
(10, 2, 'User 10', 'user10@penpoint.local', '$2y$10$seedseedseedseedseedseedseedseedseedseedse', NULL, '2026-01-01 10:00:00');

--
-- Дамп данных таблицы `reviews`
--
INSERT INTO `reviews` (`id`, `product_id`, `user_id`, `rating`, `comment`, `created_at`) VALUES
(1, 1, 1, 4, 'Хорошая ручка, удобно держать в руке.', '2026-01-25 10:30:00'),
(2, 1, 2, 4, 'Пишет мягко, чернила не мажут.', '2026-01-25 11:15:00'),
(3, 1, 3, 4, 'Качество хорошее, беру не первый раз.', '2026-01-25 12:00:00'),
(4, 1, 4, 4, 'Удобная, не скользит в руке.', '2026-01-25 13:30:00'),
(5, 1, 5, 4, 'Отличная ручка на каждый день.', '2026-01-25 14:45:00'),
(6, 1, 6, 4, 'Цена/качество — супер.', '2026-01-25 15:20:00'),
(7, 1, 7, 4, 'Рекомендую, хватает надолго.', '2026-01-25 16:00:00'),

(8, 2, 1, 5, 'Плотная бумага, удобно делать заметки.', '2026-01-25 12:00:00'),
(9, 2, 3, 4, 'Отличный блокнот, выглядит стильно.', '2026-01-25 13:30:00'),
(10, 2, 6, 5, 'Бумага не просвечивает — это главное.', '2026-01-25 16:10:00'),
(11, 2, 8, 4, 'Удобный формат, беру для учёбы.', '2026-01-25 17:00:00'),

(12, 3, 2, 5, 'Пишет чётко и ярко.', '2026-01-25 14:45:00'),
(13, 3, 5, 4, 'Хорошая ручка, без пропусков.', '2026-01-25 15:10:00'),
(14, 3, 9, 5, 'Отличная для конспектов.', '2026-01-25 18:00:00'),

(15, 4, 1, 3, 'Обычная линейка, но удобная.', '2026-01-25 15:20:00'),
(16, 4, 4, 4, 'Прозрачная, деления видно хорошо.', '2026-01-25 15:40:00'),

(17, 6, 3, 4, 'Краски яркие и насыщенные.', '2026-01-25 16:00:00'),
(18, 6, 7, 4, 'Для школы отлично подходит.', '2026-01-25 16:30:00'),

(19, 7, 2, 4, 'Маркеры долго не высыхают.', '2026-01-25 17:15:00'),
(20, 7, 10, 4, 'Цвета классные, детям нравится.', '2026-01-25 17:40:00');

--
-- Дамп данных таблицы `product_colors`
--
INSERT INTO `product_colors` (`product_id`, `name`, `hex_code`) VALUES
(1, 'Синий', '#1d4ed8'),
(1, 'Чёрный', '#111827'),
(1, 'Красный', '#dc2626'),
(1, 'Зелёный', '#16a34a'),
(3, 'Чёрный', '#111827'),
(3, 'Синий', '#1d4ed8');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
