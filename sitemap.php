<?php
require_once __DIR__ . '/includes/security.php';
app_start_session();
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Карта сайта Канцария со ссылками на основные, информационные и сервисные страницы.">
    <link rel="icon" href="<?php echo htmlspecialchars(app_url('/assets/icons/favicon.ico'), ENT_QUOTES, 'UTF-8'); ?>" sizes="any">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/styles/global.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/styles/header.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/styles/footer.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/styles/legal-pages.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <title>Карта сайта — Канцария</title>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="main">
    <section class="legal-page">
        <h1>Карта сайта</h1>

        <h2>Основные страницы</h2>
        <ul>
            <li><a href="<?php echo htmlspecialchars(app_url('/index.php'), ENT_QUOTES, 'UTF-8'); ?>">Главная</a></li>
            <li><a href="<?php echo htmlspecialchars(app_url('/pages/catalog.php'), ENT_QUOTES, 'UTF-8'); ?>">Каталог</a></li>
            <li><a href="<?php echo htmlspecialchars(app_url('/pages/sales.php'), ENT_QUOTES, 'UTF-8'); ?>">Акции</a></li>
            <li><a href="<?php echo htmlspecialchars(app_url('/pages/cart.php'), ENT_QUOTES, 'UTF-8'); ?>">Корзина</a></li>
            <li><a href="<?php echo htmlspecialchars(app_url('/pages/checkout.php'), ENT_QUOTES, 'UTF-8'); ?>">Оформление заказа</a></li>
            <li><a href="<?php echo htmlspecialchars(app_url('/pages/account.php'), ENT_QUOTES, 'UTF-8'); ?>">Личный кабинет</a></li>
        </ul>

        <h2>Информационные страницы</h2>
        <ul>
            <li><a href="<?php echo htmlspecialchars(app_url('/pages/delivery-payment.php'), ENT_QUOTES, 'UTF-8'); ?>">Доставка и оплата</a></li>
            <li><a href="<?php echo htmlspecialchars(app_url('/returns.php'), ENT_QUOTES, 'UTF-8'); ?>">Возврат</a></li>
            <li><a href="<?php echo htmlspecialchars(app_url('/privacy.php'), ENT_QUOTES, 'UTF-8'); ?>">Политика обработки персональных данных</a></li>
        </ul>

        <h2>Дополнительно</h2>
        <ul>
            <li><a href="<?php echo htmlspecialchars(app_url('/pages/contacts.php'), ENT_QUOTES, 'UTF-8'); ?>">Контакты</a></li>
            <li><a href="<?php echo htmlspecialchars(app_url('/pages/login.php'), ENT_QUOTES, 'UTF-8'); ?>">Вход и регистрация</a></li>
        </ul>
    </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
