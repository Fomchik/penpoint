/**
 * Корзина покупок с использованием localStorage
 */

(function() {
    'use strict';

    const CART_STORAGE_KEY = 'penpoint_cart';
    const cartBadge = document.getElementById('cart-badge');

    /**
     * Получить корзину из localStorage
     */
    function getCart() {
        const cartJson = localStorage.getItem(CART_STORAGE_KEY);
        return cartJson ? JSON.parse(cartJson) : [];
    }

    /**
     * Сохранить корзину в localStorage
     */
    function saveCart(cart) {
        localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
        updateCartBadge();
    }

    /**
     * Обновить счетчик товаров в корзине
     */
    function updateCartBadge() {
        if (cartBadge) {
            const cart = getCart();
            const totalQuantity = cart.reduce((sum, item) => sum + item.quantity, 0);
            cartBadge.textContent = totalQuantity || '0';
            cartBadge.style.display = totalQuantity > 0 ? 'flex' : 'none';
        }
    }

    /**
     * Добавить товар в корзину
     * @param {number} quantity - количество (по умолчанию 1)
     */
    function addToCart(productId, productName, productPrice, quantity) {
        quantity = Math.max(1, parseInt(quantity, 10) || 1);
        const cart = getCart();
        const existingItemIndex = cart.findIndex(item => item.id === productId);

        if (existingItemIndex !== -1) {
            cart[existingItemIndex].quantity += quantity;
        } else {
            cart.push({
                id: productId,
                name: productName,
                price: productPrice,
                quantity: quantity
            });
        }

        saveCart(cart);
        showNotification(quantity > 1 ? 'Товары добавлены в корзину' : 'Товар добавлен в корзину');
    }

    /**
     * Показать уведомление
     */
    function showNotification(message) {
        // Создаем элемент уведомления
        const notification = document.createElement('div');
        notification.className = 'cart-notification';
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #f2690d;
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            font-size: 0.9375rem;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        `;

        // Добавляем стили для анимации
        if (!document.getElementById('cart-notification-styles')) {
            const style = document.createElement('style');
            style.id = 'cart-notification-styles';
            style.textContent = `
                @keyframes slideIn {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                @keyframes slideOut {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        document.body.appendChild(notification);

        // Удаляем уведомление через 3 секунды
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    /**
     * Инициализация обработчиков событий
     */
    function init() {
        // Обновляем счетчик при загрузке страницы
        updateCartBadge();

        // Обработчики для кнопок "В корзину"
        document.addEventListener('click', function(e) {
            const addToCartBtn = e.target.closest('.product-card__add-to-cart, .product-page__add-to-cart');
            
            if (addToCartBtn) {
                e.preventDefault();
                
                const productId = parseInt(addToCartBtn.getAttribute('data-product-id'));
                const productName = addToCartBtn.getAttribute('data-product-name');
                const productPrice = parseFloat(addToCartBtn.getAttribute('data-product-price'));
                let quantity = 1;
                const qtyInput = document.querySelector('.product-page__qty-input');
                if (qtyInput) {
                    quantity = Math.max(1, parseInt(qtyInput.value, 10) || 1);
                }

                if (productId && productName && !isNaN(productPrice)) {
                    addToCart(productId, productName, productPrice, quantity);
                }
            }
        });
    }

    // Инициализация при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Экспортируем функции для использования в других скриптах
    window.Cart = {
        getCart: getCart,
        addToCart: addToCart,
        updateCartBadge: updateCartBadge
    };
})();
