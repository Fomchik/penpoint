(function () {
    'use strict';

    function formatPrice(value) {
        return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(Number(value) || 0) + ' ₽';
    }

    function pluralizeProduct(num) {
        const n = Math.abs(Number(num) || 0);
        const n10 = n % 10;
        const n100 = n % 100;
        if (n10 === 1 && n100 !== 11) return 'товар';
        if (n10 >= 2 && n10 <= 4 && (n100 < 12 || n100 > 14)) return 'товара';
        return 'товаров';
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

    
    function lineKey(item) {
        return String(Number(item.product_id) || 0) + ':' + (item.variant_id === null || item.variant_id === undefined ? 'base' : String(Number(item.variant_id) || 0));
    }

    
    function mapAttributeLabel(key) {
        const map = {
            color: 'Цвет',
            size: 'Размер',
            format: 'Формат',
            volume: 'Объём',
            thickness: 'Толщина',
            set_quantity: 'Количество в наборе',
            sheet_quantity: 'Количество листов',
            paper_density: 'Плотность бумаги',
            paper_type: 'Тип бумаги',
            binding_type: 'Тип крепления',
            cover_type: 'Тип обложки',
            hardness: 'Жесткость'
        };
        const normalized = String(key || '').trim().toLowerCase();
        return map[normalized] || String(key || '');
    }

    function parseLineKey(row) {
        const key = row.getAttribute('data-line-key') || '';
        const parts = key.split(':');
        const productId = parseInt(parts[0], 10) || 0;
        const variantId = parts[1] && parts[1] !== 'base' ? (parseInt(parts[1], 10) || 0) : null;
        return { productId: productId, variantId: variantId };
    }

    function normalizeQuantity(value) {
        return Math.max(1, parseInt(String(value || '').replace(/[^\d]/g, ''), 10) || 1);
    }

    function updateRowQuantity(row, quantity) {
        if (!row || !window.Cart || typeof window.Cart.updateItem !== 'function') {
            return;
        }
        const parsed = parseLineKey(row);
        if (parsed.productId <= 0) {
            return;
        }
        const normalized = normalizeQuantity(quantity);
        if (row.getAttribute('data-last-qty') === String(normalized)) {
            return;
        }
        row.setAttribute('data-last-qty', String(normalized));
        window.Cart.updateItem({
            product_id: parsed.productId,
            variant_id: parsed.variantId,
            quantity: normalized
        });
    }

    function setCheckoutState(hasItems) {
        const checkoutLink = document.getElementById('cart-checkout-link');
        if (!checkoutLink) {
            return;
        }

        if (hasItems) {
            checkoutLink.href = '/pages/checkout.php';
            checkoutLink.classList.remove('is-disabled');
            checkoutLink.setAttribute('aria-disabled', 'false');
            return;
        }

        checkoutLink.href = '/pages/catalog.php';
        checkoutLink.classList.add('is-disabled');
        checkoutLink.setAttribute('aria-disabled', 'true');
    }

    function updateRecommended(state) {
        const list = document.getElementById('cart-recommended-list');
        if (!list) {
            return;
        }

        const excluded = new Set(
            (state && Array.isArray(state.items) ? state.items : []).map(function (item) {
                return String(Number(item.product_id) || 0);
            })
        );

        list.querySelectorAll('.cart-recommended__item').forEach(function (item) {
            const productId = item.getAttribute('data-recommended-product-id') || '';
            item.style.display = excluded.has(productId) ? 'none' : '';
        });
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
            setCheckoutState(false);
            updateRecommended({ items: [] });
            return;
        }

        if (card) card.style.display = '';
        if (empty) empty.style.display = 'none';

        itemsContainer.innerHTML = state.items.map(function (item) {
            const attributes = item.attributes && typeof item.attributes === 'object'
                ? Object.keys(item.attributes).map(function (key) {
                    return '<span>' + escapeHtml(mapAttributeLabel(key)) + ': ' + escapeHtml(item.attributes[key]) + '</span>';
                }).join(' · ')
                : '';
            const meta = attributes || (item.variant_label ? escapeHtml(item.variant_label) : '');
            const productId = Number(item.product_id) || 0;
            const productUrl = productId > 0
                ? (window.appResolvePath || function (path) { return String(path || ''); })('/pages/page-product.php?id=' + encodeURIComponent(String(productId)))
                : '';

            return '' +
                '<article class="cart-item-row' + (!item.available ? ' cart-item-row--unavailable' : '') + '" data-line-key="' + escapeHtml(lineKey(item)) + '" data-last-qty="' + (Number(item.quantity) || 1) + '">' +
                '<div class="cart-item-row__image"><img src="' + safePath(item.image, '/assets/icons/favicon.svg') + '" alt="' + escapeHtml(item.title) + '"></div>' +
                '<div class="cart-item-row__content">' +
                '<h3 class="cart-item-row__name">' + (productUrl ? '<a class="cart-item-row__name-link" href="' + escapeHtml(productUrl) + '">' + escapeHtml(item.title) + '</a>' : escapeHtml(item.title)) + '</h3>' +
                (meta ? '<div class="cart-item-row__meta">' + meta + '</div>' : '') +
                '<div class="cart-item-row__tools">' +
                '<div class="cart-item-row__qty">' +
                '<button type="button" class="cart-item-row__qty-btn" data-action="dec"' + (!item.available ? ' disabled' : '') + '>−</button>' +
                '<input type="number" min="1" step="1" inputmode="numeric" value="' + (Number(item.quantity) || 1) + '"' + (!item.available ? ' disabled' : '') + '>' +
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

        const totalQuantity = Number.isFinite(Number(state.total_quantity))
            ? Number(state.total_quantity)
            : state.items.reduce(function (sum, item) {
                return sum + (item && item.available ? (Math.max(1, Number(item.quantity) || 1)) : 0);
            }, 0);
        const subtotal = Number.isFinite(Number(state.subtotal))
            ? Number(state.subtotal)
            : state.items.reduce(function (sum, item) {
                return sum + (item && item.available ? (Number(item.line_total) || 0) : 0);
            }, 0);
        const baseSubtotal = Number.isFinite(Number(state.base_subtotal))
            ? Number(state.base_subtotal)
            : state.items.reduce(function (sum, item) {
                return sum + (item && item.available ? (Number(item.line_base_total) || Number(item.line_total) || 0) : 0);
            }, 0);

        if (headCount) headCount.textContent = totalQuantity + ' ' + pluralizeProduct(totalQuantity);
        if (sideItems) sideItems.textContent = totalQuantity + ' ' + pluralizeProduct(totalQuantity);
        if (sidePrice) sidePrice.textContent = formatPrice(subtotal);
        if (sideOld) sideOld.textContent = baseSubtotal > subtotal ? formatPrice(baseSubtotal) : '';
        setCheckoutState(true);
        updateRecommended(state);
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

            const parsed = parseLineKey(row);
            if (parsed.productId <= 0) {
                return;
            }

            if (event.target.closest('.cart-item-row__remove')) {
                window.Cart.updateItem({ product_id: parsed.productId, variant_id: parsed.variantId, quantity: 0 });
                return;
            }

            const qtyButton = event.target.closest('.cart-item-row__qty-btn');
            if (!qtyButton) {
                return;
            }

            const input = row.querySelector('.cart-item-row__qty input');
            const current = Math.max(1, parseInt(input && input.value ? input.value : '1', 10) || 1);
            const next = qtyButton.getAttribute('data-action') === 'inc' ? current + 1 : Math.max(1, current - 1);
            window.Cart.updateItem({ product_id: parsed.productId, variant_id: parsed.variantId, quantity: next });
        });

        items.addEventListener('change', function (event) {
            const input = event.target.closest('.cart-item-row__qty input');
            if (!input) {
                return;
            }
            const row = input.closest('.cart-item-row');
            const next = normalizeQuantity(input.value);
            input.value = String(next);
            updateRowQuantity(row, next);
        });

        items.addEventListener('keydown', function (event) {
            const input = event.target.closest('.cart-item-row__qty input');
            if (!input) {
                return;
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                const row = input.closest('.cart-item-row');
                const next = normalizeQuantity(input.value);
                input.value = String(next);
                updateRowQuantity(row, next);
                input.blur();
            }
        });

        items.addEventListener('blur', function (event) {
            const input = event.target.closest('.cart-item-row__qty input');
            if (!input) {
                return;
            }
            const row = input.closest('.cart-item-row');
            const next = normalizeQuantity(input.value);
            input.value = String(next);
            updateRowQuantity(row, next);
        }, true);

        const printBtn = document.getElementById('cart-print');
        const shareBtn = document.getElementById('cart-share');
        const clearBtn = document.getElementById('cart-clear');
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
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (!window.Cart || typeof window.Cart.clear !== 'function') {
                    return;
                }
                if (!window.confirm('Очистить корзину полностью?')) {
                    return;
                }
                window.Cart.clear();
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

