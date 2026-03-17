<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/styles/global.css">
    <link rel="stylesheet" href="/styles/header.css">
    <link rel="stylesheet" href="/styles/main.css">
    <link rel="stylesheet" href="/styles/product-card.css">
    <link rel="stylesheet" href="/styles/footer.css">
    <title>Канцария - Интернет-магазин канцелярских товаров</title>
</head>

<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="main home-page">
        <?php require_once __DIR__ . '/../includes/config.php'; ?>

        <?php
        $categories = get_categories();
        $discounted_products = get_discounted_products(5);
        $recommended_products = enrich_products_with_discounts(array_slice(get_featured_products(10), 5, 5));
        if (empty($recommended_products)) {
            $recommended_products = enrich_products_with_discounts(get_featured_products(5));
        }
        ?>

        <section class="promo-banner" aria-label="Промо баннер">
            <img src="/assets/banners/hero-banner.png" alt="До 20% на школьные принадлежности" class="promo-banner__image">
        </section>

        <section class="categories" aria-label="Категории товаров">
            <h2 class="section-title">Категории товаров</h2>
            <ul class="categories__list">
                <?php foreach ($categories as $cat): ?>
                    <?php
                    $icon = 'write-icon.svg';
                    switch ((int)$cat['id']) {
                        case 2:
                            $icon = 'notebok-icon.svg';
                            break;
                        case 3:
                            $icon = 'organization-icon.svg';
                            break;
                        case 4:
                            $icon = 'painting-icon.svg';
                            break;
                        case 5:
                            $icon = 'accessories-icon.svg';
                            break;
                    }
                    ?>
                    <li class="categories__item">
                        <a href="/pages/catalog.php?category[]=<?php echo (int)$cat['id']; ?>" class="categories__link">
                            <span class="categories__icon">
                                <img src="/assets/icons/<?php echo htmlspecialchars($icon); ?>" alt="">
                            </span>
                            <p class="categories__label"><?php echo htmlspecialchars($cat['name']); ?></p>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section class="product-list" aria-label="Товары со скидкой">
            <h2 class="section-title">Товары со скидкой</h2>
            <div class="product-list__grid">
                <?php foreach ($discounted_products as $product): ?>
                    <?php
                    $image = get_product_image($product['id']);
                    $rating = get_product_rating($product['id']);
                    $rating_value = (float)$rating['rating'];
                    ?>
                    <article class="product-card">
                        <span class="product-card__badge product-card__badge--discount"><?php echo (int)$product['discount_percent']; ?>%</span>
                        <button type="button" class="product-card__wishlist" aria-label="В избранное" data-product-id="<?php echo (int)$product['id']; ?>">
                            <img src="/assets/icons/heart.svg" alt="" class="product-card__wishlist-icon">
                        </button>
                        <a href="/pages/page-product.php?id=<?php echo (int)$product['id']; ?>" class="product-card__link">
                            <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-card__image" loading="lazy">
                        </a>
                        <h4 class="product-card__name">
                            <a href="/pages/page-product.php?id=<?php echo (int)$product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a>
                        </h4>
                        <div class="product-card__rating">
                            <span class="product-card__stars" aria-label="Рейтинг: <?php echo $rating_value; ?> из 5">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php
                                    $fill = max(0, min(1, $rating_value - ($i - 1)));
                                    $fill_percent = (int)round($fill * 100);
                                    ?>
                                    <span class="star" aria-hidden="true">
                                        <img src="/assets/icons/star.svg" alt="" class="star__bg" width="16" height="16">
                                        <span class="star__fg" style="width: <?php echo $fill_percent; ?>%;">
                                            <img src="/assets/icons/star.svg" alt="" class="star__img" width="16" height="16">
                                        </span>
                                    </span>
                                <?php endfor; ?>
                            </span>
                            <span class="product-card__reviews">(<?php echo (int)$rating['count']; ?>)</span>
                        </div>
                        <div class="product-card__price">
                            <span class="product-card__price--old"><?php echo format_price($product['price_old']); ?></span>
                            <span class="product-card__price--new"><?php echo format_price($product['price']); ?></span>
                        </div>
                        <button type="button"
                            class="product-card__add-to-cart"
                            data-product-id="<?php echo (int)$product['id']; ?>"
                            data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>"
                            data-product-price="<?php echo (float)$product['price']; ?>"
                            data-product-old-price="<?php echo (float)$product['price_old']; ?>">
                            <img src="/assets/icons/cart.svg" alt="" class="product-card__add-to-cart-icon" width="18" height="18">
                            В корзину
                        </button>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="product-list" aria-label="Рекомендуем">
            <h2 class="section-title">Рекомендуем</h2>
            <div class="product-list__grid">
                <?php foreach ($recommended_products as $product): ?>
                    <?php
                    $image = get_product_image($product['id']);
                    $rating = get_product_rating($product['id']);
                    $rating_value = (float)$rating['rating'];
                    $discount = (int)($product['discount_percent'] ?? 0);
                    $price_old = (float)($product['price_old'] ?? $product['price']);
                    ?>
                    <article class="product-card">
                        <?php if ($discount): ?>
                            <span class="product-card__badge product-card__badge--discount"><?php echo $discount; ?>%</span>
                        <?php endif; ?>
                        <button type="button" class="product-card__wishlist" aria-label="В избранное" data-product-id="<?php echo (int)$product['id']; ?>">
                            <img src="/assets/icons/heart.svg" alt="" class="product-card__wishlist-icon">
                        </button>
                        <a href="/pages/page-product.php?id=<?php echo (int)$product['id']; ?>" class="product-card__link">
                            <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-card__image" loading="lazy">
                        </a>
                        <h4 class="product-card__name">
                            <a href="/pages/page-product.php?id=<?php echo (int)$product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a>
                        </h4>
                        <div class="product-card__rating">
                            <span class="product-card__stars" aria-label="Рейтинг: <?php echo $rating_value; ?> из 5">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php
                                    $fill = max(0, min(1, $rating_value - ($i - 1)));
                                    $fill_percent = (int)round($fill * 100);
                                    ?>
                                    <span class="star" aria-hidden="true">
                                        <img src="/assets/icons/star.svg" alt="" class="star__bg" width="16" height="16">
                                        <span class="star__fg" style="width: <?php echo $fill_percent; ?>%;">
                                            <img src="/assets/icons/star.svg" alt="" class="star__img" width="16" height="16">
                                        </span>
                                    </span>
                                <?php endfor; ?>
                            </span>
                            <span class="product-card__reviews">(<?php echo (int)$rating['count']; ?>)</span>
                        </div>
                        <div class="product-card__price">
                            <?php if ($discount): ?>
                                <span class="product-card__price--old"><?php echo format_price($price_old); ?></span>
                            <?php endif; ?>
                            <span class="product-card__price--new"><?php echo format_price($product['price']); ?></span>
                        </div>
                        <button type="button"
                            class="product-card__add-to-cart"
                            data-product-id="<?php echo (int)$product['id']; ?>"
                            data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>"
                            data-product-price="<?php echo (float)$product['price']; ?>"
                            data-product-old-price="<?php echo (float)$price_old; ?>">
                            <img src="/assets/icons/cart.svg" alt="" class="product-card__add-to-cart-icon" width="18" height="18">
                            В корзину
                        </button>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="advantages" aria-label="Преимущества">
            <h2 class="section-title">Преимущества</h2>
            <ul class="advantages__list">
                <li class="advantages__item">
                    <span class="advantages__icon">
                        <img src="/assets/icons/any-amount-icon.svg" alt="">
                    </span>
                    <span class="advantages__label">Заказ от любой суммы</span>
                </li>
                <li class="advantages__item">
                    <span class="advantages__icon">
                        <img src="/assets/icons/payment-by-any-method-icon.svg" alt="">
                    </span>
                    <span class="advantages__label">Наличная и безналичная оплата</span>
                </li>
                <li class="advantages__item">
                    <span class="advantages__icon">
                        <img src="/assets/icons/delivery-icon.svg" alt="">
                    </span>
                    <span class="advantages__label">Доставка по городу</span>
                </li>
                <li class="advantages__item">
                    <span class="advantages__icon">
                        <img src="/assets/icons/discounts-icon.svg" alt="">
                    </span>
                    <span class="advantages__label">Регулярные акции и скидки</span>
                </li>
            </ul>
        </section>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

</body>

</html>
