<?php
$footer_base = defined('BASE_PATH') ? BASE_PATH : '';
?>
<footer class="footer">
    <div class="footer__content">
        <div class="footer__company">
            <a href="<?php echo $footer_base; ?>/index.php" class="footer__logo">
                <img src="<?php echo $footer_base; ?>/assets/icons/logo.svg" alt="PENPOINT" class="footer__logo-img">
            </a>
            <p class="footer__description">Интернет-магазин канцелярских товаров для учёбы, работы и творчества</p>
            <p class="footer__copyright">© 2026 PENPOINT</p>
        </div>

        <div class="footer__categories">
            <h3 class="footer__heading">Категории</h3>
            <ul class="footer__nav-list">
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/catalog.php?category=writing">Письменные принадлежности</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/catalog.php?category=paper">Бумажная продукция</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/catalog.php?category=creativity">Творчество</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/catalog.php?category=accessories">Аксессуары</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/catalog.php?category=organizers">Органайзеры</a></li>
            </ul>
        </div>

        <div class="footer__info">
            <h3 class="footer__heading">Информация</h3>
            <ul class="footer__nav-list">
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/privacy.php">Политика конфиденциальности</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/contacts.php">Контакты</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/returns.php">Возврат</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/delivery.php">Доставка и оплата</a></li>
                <li class="footer__nav-item"><a href="<?php echo $footer_base; ?>/sitemap.php">Карта сайта</a></li>
            </ul>
        </div>
    </div>
</footer>
<script src="<?php echo $footer_base; ?>/scripts/guest-cleanup.js"></script>
