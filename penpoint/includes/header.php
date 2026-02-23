<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '');
}
$base = BASE_PATH;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = isset($_SESSION['user_id']);
$current_uri = $_SERVER['REQUEST_URI'];
$is_home = ($current_uri === $base . '/' || $current_uri === $base . '/index.php' || strpos($current_uri, 'pages/index.php') !== false);
$is_catalog = (strpos($current_uri, 'catalog.php') !== false);
?>
<header class="header">
    <a href="<?php echo $base; ?>/index.php" class="header__logo">
        <img src="<?php echo $base; ?>/assets/icons/logo.svg" alt="PENPOINT" class="header__logo-img">
    </a>

    <nav class="header__nav">
        <ul class="header__nav-list">
            <li class="header__nav-item"><a href="<?php echo $base; ?>/index.php" class="header__link <?php echo $is_home ? 'active' : ''; ?>">Главная</a></li>
            <li class="header__nav-item"><a href="<?php echo $base; ?>/catalog.php" class="header__link <?php echo $is_catalog ? 'active' : ''; ?>">Каталог</a></li>
            <li class="header__nav-item"><a href="<?php echo $base; ?>/sales.php" class="header__link">Акции</a></li>
            <li class="header__nav-item"><a href="<?php echo $base; ?>/contacts.php" class="header__link">Контакты</a></li>
        </ul>
    </nav>

    <div class="header__actions">
        <div class="header__search" id="header-search">
            <a href="#" class="header__action header__search-icon" id="search-toggle" aria-label="Поиск">
                <img src="<?php echo $base; ?>/assets/icons/search.svg" alt="" class="header__action-icon">
            </a>
            <form action="<?php echo $base; ?>/catalog.php" method="get" class="header__search-form" id="header-search-form">
                <input type="text" name="q" class="header__search-input" placeholder="Поиск товаров...">
            </form>
        </div>
        <a href="<?php echo $base; ?>/pages/account.php?tab=favorites" class="header__action" aria-label="Избранное" id="favorites-link">
            <img src="<?php echo $base; ?>/assets/icons/heart.svg" alt="" class="header__action-icon">
            <span class="header__cart-badge" id="favorites-badge" style="display: none;">0</span>
        </a>
        <a href="<?php echo $base; ?>/pages/cart.php" class="header__action header__action--cart" aria-label="Корзина">
            <img src="<?php echo $base; ?>/assets/icons/cart.svg" alt="" class="header__action-icon">
            <span class="header__cart-badge" id="cart-badge">0</span>
        </a>
        <a href="<?php echo $is_logged_in ? $base . '/pages/account.php' : $base . '/pages/login.php'; ?>" class="header__action" aria-label="Личный кабинет">
            <img src="<?php echo $base; ?>/assets/icons/user.svg" alt="" class="header__action-icon">
        </a>
    </div>
    <script>window.PENPOINT_IS_GUEST = <?php echo $is_logged_in ? 'false' : 'true'; ?>;</script>
</header>
