<?php
require_once __DIR__ . '/../includes/security.php';
app_start_session();
require_once __DIR__ . '/../includes/config.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/styles/global.css">
    <link rel="stylesheet" href="/styles/header.css">
    <link rel="stylesheet" href="/styles/footer.css">
    <link rel="stylesheet" href="/styles/product-card.css">
    <link rel="stylesheet" href="/styles/cart.css">
    <title>Корзина — Канцария</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="main cart-page">
        <section class="cart-grid">
            <div>
                <div class="cart-header">
                    <div class="cart-header__title-wrap">
                        <h1 class="cart-title">Корзина</h1>
                        <span id="cart-head-count" class="cart-count">0 товаров</span>
                    </div>
                    <div class="cart-header__actions">
                        <button type="button" class="cart-header__action" id="cart-print">Распечатать</button>
                        <button type="button" class="cart-header__action" id="cart-share">Поделиться</button>
                    </div>
                </div>

                <div id="cart-empty" class="cart-empty" style="display:none;">
                    Ваша корзина пуста. Добавьте товары из каталога.
                </div>

                <section id="cart-card" class="cart-card" aria-label="Товары в корзине">
                    <div id="cart-items" class="cart-items"></div>
                </section>

                <section class="cart-recommended" aria-label="Рекомендуемые товары">
                    <h2 class="cart-recommended__title">Рекомендуемые товары</h2>
                    <div class="cart-recommended__list">
                        <?php
                        $recommended = get_featured_products(4);
                        foreach ($recommended as $product):
                            $image = get_product_image($product['id']);
                            $priceNew = (float)$product['price'];
                            $priceOld = (float)($product['price_old'] ?? $product['price']);
                        ?>
                            <article class="cart-recommended__item">
                                <a class="cart-recommended__link" href="/pages/page-product.php?id=<?php echo (int)$product['id']; ?>">
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
                                    <div class="cart-recommended__name"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class="cart-recommended__price"><?php echo format_price($priceNew); ?></div>
                                </a>
                                <button
                                    type="button"
                                    class="product-card__add-to-cart"
                                    data-product-id="<?php echo (int)$product['id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>"
                                    data-product-price="<?php echo $priceNew; ?>"
                                    data-product-old-price="<?php echo $priceOld; ?>">
                                    <img src="/assets/icons/cart.svg" alt="" class="product-card__add-to-cart-icon" width="18" height="18">
                                    В корзину
                                </button>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <aside class="cart-summary" aria-label="Условия заказа">
                <h2 class="cart-summary__title">Условия заказа</h2>
                <div class="cart-summary__rows">
                    <div class="cart-summary__row">
                        <span>Товары</span>
                        <span id="side-total-items">0</span>
                    </div>
                    <div class="cart-summary__row cart-summary__row--total">
                        <span>Итого</span>
                        <span id="side-total-price">0 ₽</span>
                    </div>
                </div>
                <div id="side-total-old" class="cart-summary__old-price"></div>
                <a href="/pages/checkout.php" class="cart-summary__button">Перейти к оформлению</a>
            </aside>
        </section>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="/scripts/cart-page.js"></script>
</body>
</html>
