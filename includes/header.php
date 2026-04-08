<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '');
}

require_once __DIR__ . '/security.php';

$base = BASE_PATH;
app_start_session();

$is_logged_in = isset($_SESSION['user_id']) || isset($_SESSION['admin_user_id']);
$is_admin = (string)($_SESSION['role_name'] ?? '') === 'admin' || isset($_SESSION['admin_user_id']);
$account_link = $base . '/pages/login.php';
if ($is_logged_in) {
    $account_link = $is_admin ? $base . '/admin/index.php' : $base . '/pages/account.php';
}
$current_uri = $_SERVER['REQUEST_URI'];
$is_home = ($current_uri === $base . '/' || $current_uri === $base . '/index.php' || strpos($current_uri, 'pages/index.php') !== false);
$is_catalog = (strpos($current_uri, 'catalog.php') !== false);
$is_sales = (strpos($current_uri, 'sales.php') !== false);
$is_contacts = (strpos($current_uri, 'contacts.php') !== false);
$is_admin_page = (strpos($current_uri, '/admin/') !== false);
?>
<header class="header">
    <a href="<?php echo $base; ?>/index.php" class="header__logo">
        <span class="header__logo-text">Канцария</span>
    </a>

    <button type="button" class="header__burger" id="header-burger" aria-label="Открыть меню" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <nav class="header__nav" id="header-nav">
        <ul class="header__nav-list">
            <li class="header__nav-item"><a href="<?php echo $base; ?>/index.php" class="header__link <?php echo $is_home ? 'active' : ''; ?>">Главная</a></li>
            <li class="header__nav-item"><a href="<?php echo $base; ?>/pages/catalog.php" class="header__link <?php echo $is_catalog ? 'active' : ''; ?>">Каталог</a></li>
            <li class="header__nav-item"><a href="<?php echo $base; ?>/pages/sales.php" class="header__link <?php echo $is_sales ? 'active' : ''; ?>">Акции</a></li>
            <li class="header__nav-item"><a href="<?php echo $base; ?>/pages/contacts.php" class="header__link <?php echo $is_contacts ? 'active' : ''; ?>">Контакты</a></li>
            <?php if ($is_admin): ?>
                <li class="header__nav-item"><a href="<?php echo $base; ?>/admin/index.php" class="header__link <?php echo $is_admin_page ? 'active' : ''; ?>">Админ-панель</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="header__actions">
        <div class="header__search" id="header-search">
            <a href="#" class="header__action header__search-icon" id="search-toggle" aria-label="Поиск">
                <img src="<?php echo $base; ?>/assets/icons/search.svg" alt="" class="header__action-icon">
            </a>
            <form action="<?php echo $base; ?>/pages/catalog.php" method="get" class="header__search-form" id="header-search-form">
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

        <a href="<?php echo $account_link; ?>" class="header__action" aria-label="Личный кабинет">
            <img src="<?php echo $base; ?>/assets/icons/user.svg" alt="" class="header__action-icon">
        </a>
    </div>

    <script>window.IS_GUEST_USER = <?php echo $is_logged_in ? 'false' : 'true'; ?>;</script>
    <script>
    (function () {
        const burger = document.getElementById('header-burger');
        const nav = document.getElementById('header-nav');
        if (!burger || !nav) {
            return;
        }

        burger.addEventListener('click', function () {
            const expanded = burger.getAttribute('aria-expanded') === 'true';
            burger.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            nav.classList.toggle('header__nav--open', !expanded);
            document.body.classList.toggle('header-menu-open', !expanded);
        });
    })();
    </script>
</header>
