<?php
require_once __DIR__ . '/../includes/security.php';
app_start_session();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/product_options.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = $product_id ? get_product_by_id($product_id) : null;

if (!$product) {
    http_response_code(404);
}

$image = $product ? get_product_image($product['id']) : null;
$rating = $product ? get_product_rating($product['id']) : ['rating' => 0, 'count' => 0];
$discount_percent = $product ? (int)$product['discount_percent'] : 0;
$price_old = $product ? (float)$product['price_old'] : 0;
$price_new = $product ? (float)$product['price'] : 0;
$colors = $product ? get_product_colors($product['id']) : [];
$needs_color_panel = $product ? product_needs_color_panel($product['id']) : false;
$product_options = $product ? get_product_options_for_product((int)$product['id']) : [];
$product_images = $product ? app_fetch_product_images((int)$product['id']) : [];
$initial_state = $product ? build_dynamic_product_state($product, [], $product_images) : [];

$related_products = $product ? enrich_products_with_discounts(get_related_products($product['id'], 3)) : [];
$can_leave_review = isset($_SESSION['user_id']);

try {
    $stmt = $pdo->query('SELECT id, name, price FROM delivery_methods ORDER BY id ASC');
    $delivery_methods = $stmt->fetchAll() ?: [];
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    $delivery_methods = [];
}
?>
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
    <link rel="stylesheet" href="/styles/footer.css">
    <link rel="stylesheet" href="/styles/product-card.css">
    <link rel="stylesheet" href="/styles/product-page.css">
    <title><?php echo $product ? htmlspecialchars($product['name']) : 'Товар не найден'; ?> — Канцария</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="main">
        <?php if (!$product): ?>
            <section class="product-page">
                <h1 class="product-page__title">Товар не найден</h1>
            </section>
        <?php else: ?>
            <section class="product-page"
                data-product-page
                data-product-id="<?php echo (int)$product['id']; ?>"
                data-product-state-url="/api/product_state.php">
                <div class="product-page__top">
                    <div class="product-page__gallery">
                        <?php if ($discount_percent): ?>
                            <span class="product-page__badge"><?php echo $discount_percent; ?>%</span>
                        <?php endif; ?>
                        <button type="button" class="product-page__wishlist" aria-label="В избранное" data-product-id="<?php echo (int)$product['id']; ?>">
                            <img src="/assets/icons/heart.svg" alt="" class="product-page__wishlist-icon">
                        </button>
                        <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-page__image" data-product-image>
                    </div>

                    <div class="product-page__info">
                        <h1 class="product-page__title"><?php echo htmlspecialchars($product['name']); ?></h1>

                        <div class="product-page__prices">
                            <span class="product-page__price-old<?php echo $discount_percent ? '' : ' visually-hidden'; ?>" data-base-price><?php echo format_price($price_old); ?></span>
                            <span class="product-page__price-new" data-final-price><?php echo format_price($price_new); ?></span>
                        </div>

                        <div class="product-page__rating">
                            <?php $rating_value = (float)$rating['rating']; ?>
                            <span class="product-page__stars" aria-label="Рейтинг: <?php echo $rating_value; ?> из 5">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php $fill_percent = (int)round(max(0, min(1, $rating_value - ($i - 1))) * 100); ?>
                                    <span class="star" aria-hidden="true">
                                        <img src="/assets/icons/star.svg" alt="" class="star__bg" width="16" height="16">
                                        <span class="star__fg" style="width: <?php echo $fill_percent; ?>%;">
                                            <img src="/assets/icons/star.svg" alt="" class="star__img" width="16" height="16">
                                        </span>
                                    </span>
                                <?php endfor; ?>
                            </span>
                            <span class="product-page__reviews">(<?php echo (int)$rating['count']; ?>)</span>
                        </div>

                        <?php if (!$can_leave_review): ?>
                            <p>Отзывы могут оставлять только зарегистрированные пользователи.</p>
                        <?php endif; ?>

                        <?php if ($needs_color_panel && !empty($colors)): ?>
                            <div class="product-page__options">
                                <div class="product-page__option">
                                    <div class="product-page__option-label">Цвет чернил: <b><?php echo htmlspecialchars($colors[0]['name']); ?></b></div>
                                    <div class="product-page__colors" role="list">
                                        <?php foreach ($colors as $idx => $color): ?>
                                            <button
                                                type="button"
                                                class="product-page__color <?php echo $idx === 0 ? 'product-page__color--active' : ''; ?>"
                                                aria-label="<?php echo htmlspecialchars($color['name']); ?>"
                                                style="background:<?php echo htmlspecialchars($color['hex_code']); ?>"
                                                data-color-name="<?php echo htmlspecialchars($color['name']); ?>">
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($product_options)): ?>
                            <div class="product-page__options" data-product-options>
                                <?php foreach ($product_options as $option): ?>
                                    <div class="product-page__option">
                                        <div class="product-page__option-label"><?php echo htmlspecialchars((string)$option['label']); ?></div>
                                        <?php if (($option['ui'] ?? 'buttons') === 'select'): ?>
                                            <select class="product-page__select" data-option-code="<?php echo htmlspecialchars((string)$option['code'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <option value="">Выберите</option>
                                                <?php foreach ((array)$option['values'] as $value): ?>
                                                    <option value="<?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$value); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <div class="product-page__chips">
                                                <?php foreach ((array)$option['values'] as $value): ?>
                                                    <button
                                                        type="button"
                                                        class="product-page__chip"
                                                        data-option-code="<?php echo htmlspecialchars((string)$option['code'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-option-value="<?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars((string)$value); ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="product-page__stock">
                            <span class="product-page__stock-indicator"></span>
                            <span>В наличии: <b data-product-stock><?php echo (int)$initial_state['stock']; ?> шт</b></span>
                        </div>

                        <div class="product-page__buy">
                            <div class="product-page__qty">
                                <button type="button" class="product-page__qty-btn" data-qty="-1">−</button>
                                <input class="product-page__qty-input" type="text" value="1" inputmode="numeric" aria-label="Количество">
                                <button type="button" class="product-page__qty-btn" data-qty="1">+</button>
                            </div>

                            <button type="button"
                                class="product-card__add-to-cart product-page__add-to-cart"
                                data-product-id="<?php echo (int)$product['id']; ?>"
                                data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>"
                                data-product-price="<?php echo (float)$price_new; ?>"
                                data-product-old-price="<?php echo (float)$price_old; ?>"
                                data-variant-id="">
                                <img src="/assets/icons/cart.svg" alt="" class="product-card__add-to-cart-icon" width="18" height="18">
                                В корзину
                            </button>

                            <button type="button" class="product-page__share" aria-label="Поделиться">
                                <img src="/assets/icons/share-icon.svg" alt="" class="product-page__share-icon">
                            </button>
                        </div>

                        <div class="product-page__tabs">
                            <div class="product-page__tab-header">
                                <button type="button" class="product-page__tab product-page__tab--active" data-tab="description">Описание</button>
                                <button type="button" class="product-page__tab" data-tab="specs">Характеристики</button>
                                <button type="button" class="product-page__tab" data-tab="delivery">Доставка</button>
                            </div>
                            <div class="product-page__tab-body">
                                <div class="product-page__tab-content product-page__tab-content--active" data-content="description">
                                    <p><?php echo nl2br(htmlspecialchars($product['description'] ?: 'Описание отсутствует.')); ?></p>
                                </div>
                                <div class="product-page__tab-content" data-content="specs">
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <tr style="border-bottom: 1px solid #e5e5e5;">
                                            <td style="padding: 12px 0; font-weight: 600; color: #666;">Категория</td>
                                            <td style="padding: 12px 0;"><?php echo htmlspecialchars($product['category_name'] ?? 'Не указана'); ?></td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e5e5e5;">
                                            <td style="padding: 12px 0; font-weight: 600; color: #666;">Наличие</td>
                                            <td style="padding: 12px 0;" data-product-spec-stock><?php echo (int)$initial_state['stock']; ?> шт.</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #e5e5e5;">
                                            <td style="padding: 12px 0; font-weight: 600; color: #666;">Цена</td>
                                            <td style="padding: 12px 0;" data-product-spec-price><?php echo format_price($price_new); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="product-page__tab-content" data-content="delivery">
                                    <h3 style="margin-bottom: 16px; font-size: 1.125rem;">Способы доставки:</h3>
                                    <ul style="list-style: none; padding: 0; margin: 0;">
                                        <?php foreach ($delivery_methods as $method): ?>
                                            <li style="padding: 12px 0; border-bottom: 1px solid #e5e5e5;">
                                                <strong><?php echo htmlspecialchars($method['name']); ?></strong>
                                                <?php if ((float)$method['price'] > 0): ?>
                                                    — <?php echo format_price($method['price']); ?>
                                                <?php else: ?>
                                                    — бесплатно
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <?php if (!empty($related_products)): ?>
                <section class="product-page__also-buy">
                    <h2 class="product-page__also-buy-title">С этим покупают</h2>
                    <div class="product-page__also-buy-grid">
                        <?php foreach ($related_products as $p): ?>
                            <?php
                            $p_image = get_product_image($p['id']);
                            $p_discount = (int)($p['discount_percent'] ?? 0);
                            $p_price_old = (float)($p['price_old'] ?? $p['price']);
                            $p_price_new = (float)$p['price'];
                            ?>
                            <article class="product-card">
                                <?php if ($p_discount): ?>
                                    <span class="product-card__badge product-card__badge--discount"><?php echo $p_discount; ?>%</span>
                                <?php endif; ?>
                                <button type="button" class="product-card__wishlist" aria-label="В избранное" data-product-id="<?php echo (int)$p['id']; ?>">
                                    <img src="/assets/icons/heart.svg" alt="" class="product-card__wishlist-icon">
                                </button>
                                <a href="/pages/page-product.php?id=<?php echo (int)$p['id']; ?>" class="product-card__link">
                                    <img src="<?php echo htmlspecialchars($p_image); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="product-card__image" loading="lazy">
                                </a>
                                <h4 class="product-card__name">
                                    <a href="/pages/page-product.php?id=<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></a>
                                </h4>
                                <div class="product-card__price">
                                    <?php if ($p_discount): ?>
                                        <span class="product-card__price--old"><?php echo format_price($p_price_old); ?></span>
                                    <?php endif; ?>
                                    <span class="product-card__price--new"><?php echo format_price($p_price_new); ?></span>
                                </div>
                                <button type="button"
                                    class="product-card__add-to-cart"
                                    data-product-id="<?php echo (int)$p['id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>"
                                    data-product-price="<?php echo (float)$p_price_new; ?>"
                                    data-product-old-price="<?php echo (float)$p_price_old; ?>">
                                    <img src="/assets/icons/cart.svg" alt="" class="product-card__add-to-cart-icon" width="18" height="18">
                                    В корзину
                                </button>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="/scripts/product-page.js"></script>
</body>
</html>
