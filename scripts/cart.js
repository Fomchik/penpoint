(function () {
    'use strict';

    if (window.__CART_SCRIPT_INIT__) {
        return;
    }
    window.__CART_SCRIPT_INIT__ = true;

    const CART_STORAGE_KEY = 'penpoint_cart';
    const resolvePath = window.appResolvePath || function (path) {
        return String(path || '');
    };

    function getCart() {
        try {
            const raw = localStorage.getItem(CART_STORAGE_KEY);
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function persistLocalCart(items) {
        localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(Array.isArray(items) ? items : []));
    }

    function itemKey(item) {
        return String(parseInt(item.product_id, 10) || 0) + ':' + (item.variant_id === null || item.variant_id === undefined ? 'base' : String(parseInt(item.variant_id, 10) || 0));
    }

    function mapStateToLocal(state) {
        if (!state || !Array.isArray(state.items)) {
            return [];
        }

        return state.items.map(function (item) {
            return {
                product_id: Number(item.product_id) || 0,
                variant_id: item.variant_id === null || item.variant_id === undefined ? null : Number(item.variant_id) || 0,
                quantity: Math.max(1, Number(item.quantity) || 1),
                unit_price: Number(item.unit_price) || 0,
                base_price: Number(item.base_price) || 0,
                title: String(item.title || ''),
                variant_label: String(item.variant_label || ''),
                image: String(item.image || ''),
                attributes: item.attributes && typeof item.attributes === 'object' ? item.attributes : {},
                promotion_id: item.promotion_id === null || item.promotion_id === undefined ? null : Number(item.promotion_id) || 0,
                available: Boolean(item.available)
            };
        });
    }

    function updateCartBadge(count) {
        const badge = document.getElementById('cart-badge');
        if (!badge) {
            return;
        }

        let badgeCount = typeof count === 'number' ? count : 0;
        if (typeof count !== 'number') {
            badgeCount = getCart().reduce(function (sum, item) {
                return sum + Math.max(1, Number(item.quantity) || 1);
            }, 0);
        }

        badge.textContent = badgeCount > 0 ? String(badgeCount) : '0';
        badge.style.display = badgeCount > 0 ? 'flex' : 'none';
    }

    function dispatchCartUpdated(state) {
        window.dispatchEvent(new CustomEvent('penpoint:cart-updated', {
            detail: {
                state: state || null,
                cart: getCart()
            }
        }));
    }

    function syncState(state) {
        persistLocalCart(mapStateToLocal(state));
        updateCartBadge(state && typeof state.badge_count === 'number' ? state.badge_count : undefined);
        dispatchCartUpdated(state);
    }

    function request(url, options) {
        const requestOptions = Object.assign({
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        }, options || {});

        requestOptions.headers = Object.assign({
            'Accept': 'application/json'
        }, requestOptions.headers || {});

        return fetch(resolvePath(url), requestOptions).then(function (response) {
            const nextCsrfToken = response.headers ? response.headers.get('X-CSRF-Token') : '';
            if (typeof nextCsrfToken === 'string' && nextCsrfToken.trim() !== '') {
                window.APP_CSRF_TOKEN = nextCsrfToken.trim();
            }
            return response.json().catch(function () {
                return null;
            }).then(function (payload) {
                if (!response.ok || !payload) {
                    throw new Error(payload && payload.message ? payload.message : 'Ошибка запроса корзины.');
                }
                return payload;
            });
        });
    }

    function showNotification(message) {
        const notification = document.createElement('div');
        notification.className = 'cart-notification';
        notification.textContent = message;
        notification.style.cssText = 'position:fixed;top:20px;right:20px;background-color:#d55204;color:#fff;padding:12px 24px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);z-index:10000;font-size:.9375rem;font-weight:500;animation:slideIn .3s ease;cursor:pointer;';

        if (!document.getElementById('cart-notification-styles')) {
            const style = document.createElement('style');
            style.id = 'cart-notification-styles';
            style.textContent = '@keyframes slideIn{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}@keyframes slideOut{from{transform:translateX(0);opacity:1}to{transform:translateX(100%);opacity:0}}';
            document.head.appendChild(style);
        }

        document.body.appendChild(notification);

        function hide() {
            if (!notification.parentNode || notification.dataset.hiding === '1') {
                return;
            }
            notification.dataset.hiding = '1';
            notification.style.animation = 'slideOut .3s ease';
            setTimeout(function () {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }

        let remaining = 2500;
        let hideAt = Date.now() + remaining;
        let hideTimer = setTimeout(hide, remaining);

        function clearHideTimer() {
            if (hideTimer) {
                clearTimeout(hideTimer);
                hideTimer = null;
            }
        }

        notification.addEventListener('click', hide);
        notification.addEventListener('mouseenter', function () {
            remaining = Math.max(0, hideAt - Date.now());
            clearHideTimer();
        });
        notification.addEventListener('mouseleave', function () {
            clearHideTimer();
            hideAt = Date.now() + remaining;
            hideTimer = setTimeout(hide, remaining);
        });
    }

    function setButtonBusy(button, busy) {
        if (!button) {
            return;
        }
        button.disabled = busy;
        button.classList.toggle('is-loading', busy);
    }

    function resolveButtonPayload(button) {
        const productId = parseInt(button.getAttribute('data-product-id'), 10) || 0;
        const variantAttr = button.getAttribute('data-variant-id');
        const variantId = variantAttr !== null && variantAttr !== '' ? (parseInt(variantAttr, 10) || 0) : null;
        let quantity = 1;
        let attributes = {};

        const productPage = button.closest('.product-page');
        if (productPage) {
            const qtyInput = productPage.querySelector('.product-page__qty-input');
            quantity = Math.max(1, parseInt(qtyInput && qtyInput.value ? qtyInput.value : '1', 10) || 1);
            productPage.querySelectorAll('.product-page__variant-option.is-active').forEach(function (option) {
                const code = String(option.getAttribute('data-option-code') || '').trim();
                const value = String(option.getAttribute('data-option-value') || '').trim();
                if (code !== '' && value !== '') {
                    attributes[code] = value;
                }
            });
        }

        return {
            product_id: productId,
            variant_id: variantId,
            quantity: quantity,
            attributes: attributes
        };
    }

    function addItem(payload) {
        return request('/api/cart_add.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.APP_CSRF_TOKEN || ''
            },
            body: JSON.stringify(payload)
        }).then(function (data) {
            syncState(data.state || null);
            return data.state || null;
        });
    }

    function preflightProductState(button, payload) {
        const productPage = button ? button.closest('.product-page') : null;
        if (!productPage) {
            return Promise.resolve(payload);
        }

        const stateUrl = String(productPage.getAttribute('data-product-state-url') || '').trim();
        const productId = parseInt(productPage.getAttribute('data-product-id'), 10) || 0;
        if (!stateUrl || productId <= 0) {
            return Promise.resolve(payload);
        }

        const selections = {};
        productPage.querySelectorAll('.product-page__variant-option.is-active').forEach(function (option) {
            const code = String(option.getAttribute('data-option-code') || '').trim();
            const value = String(option.getAttribute('data-option-value') || '').trim();
            if (code !== '' && value !== '') {
                selections[code] = value;
            }
        });

        return fetch(resolvePath(stateUrl), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                product_id: productId,
                selections: selections
            })
        }).then(function (response) {
            return response.json().catch(function () {
                return null;
            }).then(function (state) {
                if (!response.ok || !state || typeof state !== 'object') {
                    throw new Error('Не удалось проверить выбранный вариант товара.');
                }

                const hasUnavailableVariant = Boolean(state.has_variants) && (state.variant_id === null || state.variant_id === undefined);
                if (hasUnavailableVariant || !Boolean(state.in_stock)) {
                    throw new Error('Выбранный вариант больше недоступен. Выберите другой.');
                }

                return Object.assign({}, payload, {
                    variant_id: state.variant_id === null || state.variant_id === undefined ? null : (parseInt(state.variant_id, 10) || null),
                    attributes: selections
                });
            });
        });
    }

    function updateItem(payload) {
        return request('/api/cart_update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.APP_CSRF_TOKEN || ''
            },
            body: JSON.stringify(payload)
        }).then(function (data) {
            syncState(data.state || null);
            return data.state || null;
        });
    }

    function clearCart() {
        return request('/api/cart_clear.php', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': window.APP_CSRF_TOKEN || ''
            }
        }).then(function (data) {
            syncState(data.state || null);
            return data.state || null;
        });
    }

    function loadState() {
        return request('/api/cart_state.php').then(function (data) {
            syncState(data);
            return data;
        }).catch(function () {
            updateCartBadge();
            return null;
        });
    }

    function bindAddToCart() {
        document.addEventListener('click', function (event) {
            const button = event.target.closest('.product-card__add-to-cart, .product-page__add-to-cart');
            if (!button) {
                return;
            }

            event.preventDefault();
            const payload = resolveButtonPayload(button);
            if (payload.product_id <= 0) {
                return;
            }

            setButtonBusy(button, true);
            preflightProductState(button, payload).then(function (actualPayload) {
                return addItem(actualPayload).then(function () {
                    return actualPayload;
                });
            }).then(function (actualPayload) {
                button.classList.add('is-added');
                showNotification(actualPayload.quantity > 1 ? 'Товары добавлены в корзину.' : 'Товар добавлен в корзину.');
            }).catch(function (error) {
                alert(error.message || 'Не удалось добавить товар в корзину.');
            }).finally(function () {
                setButtonBusy(button, false);
            });
        });
    }

    function init() {
        updateCartBadge();
        bindAddToCart();
        loadState();

        window.addEventListener('storage', function (event) {
            if (event.key === CART_STORAGE_KEY) {
                loadState().catch(function () {
                    updateCartBadge();
                    dispatchCartUpdated(null);
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.Cart = {
        getCart: getCart,
        syncState: loadState,
        addItem: addItem,
        updateItem: updateItem,
        clear: clearCart,
        updateCartBadge: updateCartBadge
    };
})();
