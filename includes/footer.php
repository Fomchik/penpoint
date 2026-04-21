<?php
$footer_base = defined('BASE_PATH') ? BASE_PATH : '';
?>
<footer class="footer">
    <div class="footer__content">
        <div class="footer__company">
            <a href="<?php echo $footer_base; ?>/index.php" class="footer__logo">
                <span class="footer__logo-text">Канцария</span>
            </a>
            <p class="footer__description">Интернет-магазин канцелярских товаров для учебы, работы и творчества</p>
            <p class="footer__copyright">© 2026 Канцария</p>
        </div>

        <div class="footer__categories">
            <h3 class="footer__heading">Категории</h3>
            <ul class="footer__nav-list">
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/pages/catalog.php?category[]=1">Письменные принадлежности</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/pages/catalog.php?category[]=2">Бумажная продукция</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/pages/catalog.php?category[]=4">Творчество</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/pages/catalog.php?category[]=5">Аксессуары</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/pages/catalog.php?category[]=3">Органайзеры</a></li>
            </ul>
        </div>

        <div class="footer__info">
            <h3 class="footer__heading">Информация</h3>
            <ul class="footer__nav-list">
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/pages/privacy.php">Политика конфиденциальности</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/pages/contacts.php">Контакты</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/pages/returns.php">Возврат</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/pages/delivery-payment.php">Доставка и оплата</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/pages/sitemap.php">Карта сайта</a></li>
            </ul>
        </div>
    </div>
</footer>
<script>
window.APP_CSRF_TOKEN = <?php echo json_encode(app_csrf_token(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.appResolvePath = window.appResolvePath || function (path) {
    return <?php echo json_encode($footer_base, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> + String(path || '');
};
</script>
<script src="<?php echo $footer_base; ?>/scripts/guest-cleanup.js"></script>
<script src="<?php echo $footer_base; ?>/scripts/search.js"></script>
<script src="<?php echo $footer_base; ?>/scripts/cart.js"></script>
<script src="<?php echo $footer_base; ?>/scripts/favorites.js"></script>
