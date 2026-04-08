(function () {
    'use strict';

    const CART_STORAGE_KEY = 'penpoint_cart';

    function clearLocalCart() {
        localStorage.removeItem(CART_STORAGE_KEY);
        if (window.Cart && typeof window.Cart.updateCartBadge === 'function') {
            window.Cart.updateCartBadge(0);
        }
    }

    function clearServerCart() {
        if (window.Cart && typeof window.Cart.clear === 'function') {
            return window.Cart.clear().catch(function () {
                clearLocalCart();
            });
        }
        clearLocalCart();
        return Promise.resolve();
    }

    function initCheckoutPaymentFlow() {
        const form = document.getElementById('order-form');
        const payload = document.getElementById('cart-payload');
        if (!form || !payload) {
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        let isSubmitting = false;

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (isSubmitting) {
                return;
            }

            const cart = window.Cart && typeof window.Cart.getCart === 'function' ? window.Cart.getCart() : [];
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

                clearServerCart().finally(function () {
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
                }
            });
        });
    }

    function handlePaymentReturn() {
        const state = document.getElementById('payment-return-state');
        if (!state || state.dataset.isReturn !== '1') {
            return;
        }

        if (state.dataset.status === 'paid') {
            clearServerCart();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        handlePaymentReturn();
        initCheckoutPaymentFlow();
    });
})();
