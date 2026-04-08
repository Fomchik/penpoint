(function () {
    'use strict';

    function formatPrice(value) {
        return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(Number(value) || 0) + ' ₽';
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function safePath(value, fallback) {
        const path = String(value || '');
        if (!path || path.charAt(0) !== '/') {
            return (window.appResolvePath || function (item) { return String(item || ''); })(fallback || '');
        }
        return (window.appResolvePath || function (item) { return String(item || ''); })(path).replace(/"/g, '%22').replace(/'/g, '%27');
    }

    function pluralizeProduct(num) {
        const n10 = num % 10;
        const n100 = num % 100;
        if (n10 === 1 && n100 !== 11) return 'товар';
        if (n10 >= 2 && n10 <= 4 && (n100 < 12 || n100 > 14)) return 'товара';
        return 'товаров';
    }

    function lineKey(item) {
        return String(Number(item.product_id) || 0) + ':' + (item.variant_id === null || item.variant_id === undefined ? 'base' : String(Number(item.variant_id) || 0));
    }

    function render(state) {
        const itemsContainer = document.getElementById('cart-items');
        const card = document.getElementById('cart-card');
        const empty = document.getElementById('cart-empty');
        const headCount = document.getElementById('cart-head-count');
        const sideItems = document.getElementById('side-total-items');
        const sidePrice = document.getElementById('side-total-price');
        const sideOld = document.getElementById('side-total-old');

        if (!itemsContainer || !state || !Array.isArray(state.items) || state.items.length === 0) {
            if (card) card.style.display = 'none';
            if (empty) empty.style.display = 'block';
            if (itemsContainer) itemsContainer.innerHTML = '';
            if (headCount) headCount.textContent = '0 товаров';
            if (sideItems) sideItems.textContent = '0 товаров';
            if (sidePrice) sidePrice.textContent = '0 ₽';
            if (sideOld) sideOld.textContent = '';
            return;
        }

        if (card) card.style.display = '';
        if (empty) empty.style.display = 'none';

        itemsContainer.innerHTML = state.items.map(function (item) {
            const attributes = item.attributes && typeof item.attributes === 'object'
                ? Object.keys(item.attributes).map(function (key) {
                    return '<span>' + escapeHtml(key) + ': ' + escapeHtml(item.attributes[key]) + '</span>';
                }).join(' · ')
                : '';

            return '' +
                '<article class="cart-item-row' + (!item.available ? ' cart-item-row--unavailable' : '') + '" data-line-key="' + escapeHtml(lineKey(item)) + '">' +
                '<div class="cart-item-row__image"><img src="' + safePath(item.image, '/assets/product_images/default.png') + '" alt="' + escapeHtml(item.title) + '"></div>' +
                '<div class="cart-item-row__content">' +
                '<h3 class="cart-item-row__name">' + escapeHtml(item.title) + '</h3>' +
                (item.variant_label ? '<div class="cart-item-row__meta">' + escapeHtml(item.variant_label) + '</div>' : '') +
                (attributes ? '<div class="cart-item-row__meta">' + attributes + '</div>' : '') +
                '<div class="cart-item-row__tools">' +
                '<div class="cart-item-row__qty">' +
                '<button type="button" class="cart-item-row__qty-btn" data-action="dec"' + (!item.available ? ' disabled' : '') + '>−</button>' +
                '<input type="text" value="' + (Number(item.quantity) || 1) + '" readonly>' +
                '<button type="button" class="cart-item-row__qty-btn" data-action="inc"' + (!item.available ? ' disabled' : '') + '>+</button>' +
                '</div>' +
                '<button type="button" class="cart-item-row__remove">Удалить</button>' +
                '</div>' +
                '</div>' +
                '<div class="cart-item-row__price">' +
                '<div class="cart-item-row__price-current">' + (item.available ? formatPrice(item.line_total) : 'Недоступно') + '</div>' +
                (Number(item.base_price) > Number(item.unit_price) ? '<div class="cart-item-row__price-old">' + formatPrice((Number(item.base_price) || 0) * (Number(item.quantity) || 1)) + '</div>' : '') +
                '</div>' +
                '</article>';
        }).join('');

        if (headCount) headCount.textContent = state.total_quantity + ' ' + pluralizeProduct(state.total_quantity);
        if (sideItems) sideItems.textContent = state.total_quantity + ' ' + pluralizeProduct(state.total_quantity);
        if (sidePrice) sidePrice.textContent = formatPrice(state.subtotal);
        if (sideOld) sideOld.textContent = Number(state.base_subtotal) > Number(state.subtotal) ? formatPrice(state.base_subtotal) : '';
    }

    function loadState() {
        if (!window.Cart || typeof window.Cart.syncState !== 'function') {
            return;
        }

        window.Cart.syncState().then(function (state) {
            render(state);
        });
    }

    function bindActions() {
        const items = document.getElementById('cart-items');
        if (!items) {
            return;
        }

        items.addEventListener('click', function (event) {
            const row = event.target.closest('.cart-item-row');
            if (!row || !window.Cart || typeof window.Cart.updateItem !== 'function') {
                return;
            }

            const key = row.getAttribute('data-line-key') || '';
            const parts = key.split(':');
            const productId = parseInt(parts[0], 10) || 0;
            const variantId = parts[1] && parts[1] !== 'base' ? (parseInt(parts[1], 10) || 0) : null;
            if (productId <= 0) {
                return;
            }

            if (event.target.closest('.cart-item-row__remove')) {
                window.Cart.updateItem({ product_id: productId, variant_id: variantId, quantity: 0 });
                return;
            }

            const qtyButton = event.target.closest('.cart-item-row__qty-btn');
            if (!qtyButton) {
                return;
            }

            const input = row.querySelector('.cart-item-row__qty input');
            const current = Math.max(1, parseInt(input && input.value ? input.value : '1', 10) || 1);
            const next = qtyButton.getAttribute('data-action') === 'inc' ? current + 1 : Math.max(1, current - 1);
            window.Cart.updateItem({ product_id: productId, variant_id: variantId, quantity: next });
        });

        const printBtn = document.getElementById('cart-print');
        const shareBtn = document.getElementById('cart-share');
        if (printBtn) {
            printBtn.addEventListener('click', function () {
                window.print();
            });
        }
        if (shareBtn) {
            shareBtn.addEventListener('click', function () {
                if (navigator.share) {
                    navigator.share({ title: 'Корзина Канцария', url: window.location.href }).catch(function () {});
                    return;
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(window.location.href);
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindActions();
        loadState();
        window.addEventListener('penpoint:cart-updated', function (event) {
            render(event.detail && event.detail.state ? event.detail.state : null);
        });
    });
})();
