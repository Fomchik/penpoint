(function () {
    'use strict';

    window.__CHECKOUT_PAYMENT_FLOW_ENABLED__ = true;

    const CART_STORAGE_KEY = 'penpoint_cart';

    function getCartSnapshot() {
        if (window.Cart && typeof window.Cart.getCart === 'function') {
            const cart = window.Cart.getCart();
            return Array.isArray(cart) ? cart : [];
        }
        try {
            const raw = localStorage.getItem(CART_STORAGE_KEY);
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function preserveCartSnapshot(cart) {
        try {
            localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(Array.isArray(cart) ? cart : []));
        } catch (e) {}
    }

    function clearCartState() {
        if (window.Cart && typeof window.Cart.clear === 'function') {
            return window.Cart.clear().catch(function () {
                try {
                    localStorage.removeItem(CART_STORAGE_KEY);
                } catch (e) {}
            });
        }
        try {
            localStorage.removeItem(CART_STORAGE_KEY);
        } catch (e) {}
        return Promise.resolve();
    }

    function initCheckoutPaymentFlow() {
        const form = document.getElementById('order-form');
        const payload = document.getElementById('cart-payload');
        if (!form || !payload) {
            return;
        }
        const csrfInput = form.querySelector('input[name="csrf_token"]');

        function syncCsrfTokenFromResponse(response) {
            if (!response || !response.headers) {
                return;
            }
            const token = response.headers.get('X-CSRF-Token');
            if (typeof token === 'string' && token.trim() !== '') {
                if (csrfInput) {
                    csrfInput.value = token.trim();
                }
                window.APP_CSRF_TOKEN = token.trim();
            }
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        let isSubmitting = false;
        const defaultSubmitText = submitBtn ? (submitBtn.getAttribute('data-default-text') || submitBtn.textContent || 'Подтвердить заказ') : 'Подтвердить заказ';

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (isSubmitting) {
                return;
            }

            const cart = getCartSnapshot();
            if (!Array.isArray(cart) || cart.length === 0) {
                alert('Корзина пуста.');
                return;
            }

            payload.value = JSON.stringify(cart);
            const formData = new FormData(form);
            formData.set('cart_payload', payload.value);

            isSubmitting = true;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('is-loading');
                submitBtn.textContent = 'Оформляем заказ...';
            }

            fetch(form.getAttribute('action') || window.location.pathname, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            }).then(function (response) {
                syncCsrfTokenFromResponse(response);
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            }).then(function (result) {
                if (!result.ok || !result.data || !result.data.success) {
                    throw new Error(result.data && result.data.message ? result.data.message : 'Не удалось оформить заказ.');
                }

                if (result.data.payment_method === 'online' && result.data.confirmation_url) {
                    window.location.href = result.data.confirmation_url;
                    return;
                }

                clearCartState().finally(function () {
                    if (result.data.redirect_url) {
                        window.location.href = result.data.redirect_url;
                    } else {
                        window.location.reload();
                    }
                });
            }).catch(function (error) {
                alert(error.message || 'Не удалось оформить заказ.');
            }).finally(function () {
                isSubmitting = false;
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('is-loading');
                    submitBtn.textContent = defaultSubmitText;
                }
            });
        });
    }

    function handlePaymentReturn() {
        const state = document.getElementById('payment-return-state');
        if (!state || state.dataset.isReturn !== '1') {
            return;
        }

        const status = String(state.dataset.status || '');
        if (status === 'paid') {
            clearCartState();
            return;
        }

        if (status === 'pending') {
            const snapshot = getCartSnapshot();
            preserveCartSnapshot(snapshot);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        handlePaymentReturn();
        initCheckoutPaymentFlow();
    });
})();
