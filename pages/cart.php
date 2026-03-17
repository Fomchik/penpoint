<?php
session_start();
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
                            $price_new = (float)$product['price'];
                            $price_old = (float)($product['price_old'] ?? $product['price']);
                        ?>
                            <article class="cart-recommended__item">
                                <a class="cart-recommended__link" href="/pages/page-product.php?id=<?php echo (int)$product['id']; ?>">
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
                                    <div class="cart-recommended__name"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class="cart-recommended__price"><?php echo format_price($price_new); ?></div>
                                </a>
                                <button
                                    type="button"
                                    class="product-card__add-to-cart"
                                    data-product-id="<?php echo (int)$product['id']; ?>"
                                    data-product-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>"
                                    data-product-price="<?php echo $price_new; ?>"
                                    data-product-old-price="<?php echo $price_old; ?>">
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

    <script>
        (function() {
            'use strict';
            const CART_STORAGE_KEY = 'penpoint_cart';

            function getCart() {
                try {
                    const raw = localStorage.getItem(CART_STORAGE_KEY);
                    return raw ? JSON.parse(raw) : [];
                } catch (e) {
                    return [];
                }
            }

            function saveCart(cart) {
                localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
                if (window.Cart && typeof window.Cart.updateCartBadge === 'function') {
                    window.Cart.updateCartBadge();
                }
            }

            function formatPrice(num) {
                return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(num) + ' ₽';
            }

            function pluralizeProduct(num) {
                const n10 = num % 10;
                const n100 = num % 100;
                if (n10 === 1 && n100 !== 11) return 'товар';
                if (n10 >= 2 && n10 <= 4 && (n100 < 12 || n100 > 14)) return 'товара';
                return 'товаров';
            }

            function updateHeaderCount(totalQty) {
                const headCount = document.getElementById('cart-head-count');
                if (headCount) {
                    headCount.textContent = totalQty + ' ' + pluralizeProduct(totalQty);
                }
            }

            function updateSummary(totalQty, totalPrice, totalOldPrice) {
                const itemsEl = document.getElementById('side-total-items');
                const priceEl = document.getElementById('side-total-price');
                const oldEl = document.getElementById('side-total-old');

                if (itemsEl) itemsEl.textContent = totalQty + ' ' + pluralizeProduct(totalQty);
                if (priceEl) priceEl.textContent = formatPrice(totalPrice);
                if (oldEl) oldEl.textContent = totalOldPrice > totalPrice ? formatPrice(totalOldPrice) : '';
            }

            function attachCartHandlers() {
                const container = document.getElementById('cart-items');
                if (!container) return;

                container.addEventListener('click', function(e) {
                    const row = e.target.closest('.cart-item-row');
                    if (!row) return;

                    const id = parseInt(row.getAttribute('data-id'), 10);
                    if (!id) return;

                    const cart = getCart();
                    const idx = cart.findIndex((item) => item.id === id);
                    if (idx === -1) return;

                    if (e.target.classList.contains('cart-item-row__remove')) {
                        cart.splice(idx, 1);
                        saveCart(cart);
                        loadCart();
                        return;
                    }

                    if (e.target.classList.contains('cart-item-row__qty-btn')) {
                        const action = e.target.getAttribute('data-action');
                        let qty = cart[idx].quantity || 1;
                        if (action === 'inc') qty += 1;
                        if (action === 'dec') qty = Math.max(1, qty - 1);
                        cart[idx].quantity = qty;
                        saveCart(cart);
                        loadCart();
                    }
                });
            }

            function renderCart(productsById, cart) {
                const itemsContainer = document.getElementById('cart-items');
                if (!itemsContainer) return;

                let totalQty = 0;
                let totalPrice = 0;
                let totalOldPrice = 0;
                const rows = [];

                cart.forEach((item) => {
                    const product = productsById[item.id];
                    if (!product) return;

                    const qty = Math.max(1, parseInt(item.quantity, 10) || 1);
                    const currentUnit = parseFloat(product.price_raw || item.price) || 0;
                    const oldRaw = parseFloat(product.old_price_raw || product.old_price || 0);
                    const oldUnit = oldRaw > currentUnit ? oldRaw : null;

                    const linePrice = currentUnit * qty;
                    const lineOldPrice = oldUnit ? oldUnit * qty : 0;

                    totalQty += qty;
                    totalPrice += linePrice;
                    totalOldPrice += lineOldPrice || linePrice;

                    rows.push(`
                        <article class="cart-item-row" data-id="${item.id}">
                            <div class="cart-item-row__image"><img src="${product.image}" alt="${product.name}"></div>
                            <div class="cart-item-row__content">
                                <h3 class="cart-item-row__name">${product.name}</h3>
                                <div class="cart-item-row__meta">${product.article || 'Артикул не указан'}</div>
                                <div class="cart-item-row__tools">
                                    <div class="cart-item-row__qty">
                                        <button type="button" class="cart-item-row__qty-btn" data-action="dec">−</button>
                                        <input type="text" value="${qty}" readonly>
                                        <button type="button" class="cart-item-row__qty-btn" data-action="inc">+</button>
                                    </div>
                                    <button type="button" class="cart-item-row__remove">Удалить</button>
                                </div>
                            </div>
                            <div class="cart-item-row__price">
                                <div class="cart-item-row__price-current">${formatPrice(linePrice)}</div>
                                ${lineOldPrice > linePrice ? `<div class="cart-item-row__price-old">${formatPrice(lineOldPrice)}</div>` : ''}
                            </div>
                        </article>
                    `);
                });

                itemsContainer.innerHTML = rows.join('');
                updateHeaderCount(totalQty);
                updateSummary(totalQty, totalPrice, totalOldPrice);
                attachCartHandlers();
            }

            function loadCart() {
                const card = document.getElementById('cart-card');
                const empty = document.getElementById('cart-empty');
                const cart = getCart();

                if (!cart.length) {
                    if (card) card.style.display = 'none';
                    if (empty) empty.style.display = 'block';
                    updateHeaderCount(0);
                    updateSummary(0, 0, 0);
                    return;
                }

                if (card) card.style.display = '';
                if (empty) empty.style.display = 'none';

                const ids = cart.map((item) => item.id).join(',');
                fetch('/api/products.php?ids=' + encodeURIComponent(ids))
                    .then((response) => response.json())
                    .then((products) => {
                        const byId = {};
                        products.forEach((product) => {
                            byId[product.id] = product;
                        });
                        renderCart(byId, cart);
                    })
                    .catch(() => {
                        if (card) card.style.display = 'none';
                        if (empty) {
                            empty.style.display = 'block';
                            empty.textContent = 'Не удалось загрузить товары корзины.';
                        }
                    });
            }

            function setupHeaderActions() {
                const printBtn = document.getElementById('cart-print');
                const shareBtn = document.getElementById('cart-share');

                if (printBtn) {
                    printBtn.addEventListener('click', function() {
                        window.print();
                    });
                }

                if (shareBtn) {
                    shareBtn.addEventListener('click', function() {
                        if (navigator.share) {
                            navigator.share({
                                title: 'Корзина Канцария',
                                url: window.location.href
                            }).catch(function() {});
                            return;
                        }

                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(window.location.href).then(function() {
                                alert('Ссылка скопирована.');
                            }).catch(function() {
                                alert('Скопируйте ссылку вручную из адресной строки.');
                            });
                        } else {
                            alert('Скопируйте ссылку вручную из адресной строки.');
                        }
                    });
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                setupHeaderActions();
                loadCart();
            });
        })();
    </script>
</body>
</html>
