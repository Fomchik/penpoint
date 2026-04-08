(function () {
    'use strict';

    function formatPrice(value) {
        return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(Number(value) || 0) + ' ₽';
    }

    function collectSelections(root) {
        const result = {};

        root.querySelectorAll('select[data-option-code]').forEach(function (select) {
            if (select.value) {
                result[select.getAttribute('data-option-code')] = select.value;
            }
        });

        root.querySelectorAll('.product-page__chip.is-active').forEach(function (button) {
            result[button.getAttribute('data-option-code')] = button.getAttribute('data-option-value') || '';
        });

        return result;
    }

    function applyState(root, state) {
        if (!state) {
            return;
        }

        const price = root.querySelector('[data-final-price]');
        const basePrice = root.querySelector('[data-base-price]');
        const stock = root.querySelector('[data-product-stock]');
        const specPrice = root.querySelector('[data-product-spec-price]');
        const specStock = root.querySelector('[data-product-spec-stock]');
        const image = root.querySelector('[data-product-image]');
        const addButton = root.querySelector('.product-page__add-to-cart');

        if (price) price.textContent = state.price_formatted || formatPrice(state.price);
        if (basePrice) {
            const showBase = Number(state.base_price) > Number(state.price);
            basePrice.textContent = state.base_price_formatted || formatPrice(state.base_price);
            basePrice.classList.toggle('visually-hidden', !showBase);
        }
        if (stock) stock.textContent = String(Number(state.stock) || 0) + ' шт';
        if (specPrice) specPrice.textContent = state.price_formatted || formatPrice(state.price);
        if (specStock) specStock.textContent = String(Number(state.stock) || 0) + ' шт.';
        if (image && state.image) image.src = state.image;
        if (addButton) {
            addButton.setAttribute('data-product-price', String(Number(state.price) || 0));
            addButton.setAttribute('data-product-old-price', String(Number(state.base_price) || 0));
            addButton.setAttribute('data-variant-id', state.variant_id === null || state.variant_id === undefined ? '' : String(Number(state.variant_id) || 0));
            addButton.disabled = !state.in_stock;
        }
    }

    function initTabs() {
        document.querySelectorAll('.product-page__tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                const tabName = this.getAttribute('data-tab');
                document.querySelectorAll('.product-page__tab').forEach(function (item) {
                    item.classList.remove('product-page__tab--active');
                });
                document.querySelectorAll('.product-page__tab-content').forEach(function (content) {
                    content.classList.remove('product-page__tab-content--active');
                });
                this.classList.add('product-page__tab--active');
                const content = document.querySelector('.product-page__tab-content[data-content="' + tabName + '"]');
                if (content) {
                    content.classList.add('product-page__tab-content--active');
                }
            });
        });
    }

    function initQty(root) {
        const input = root.querySelector('.product-page__qty-input');
        if (!input) {
            return;
        }

        root.addEventListener('click', function (event) {
            const button = event.target.closest('.product-page__qty-btn');
            if (!button) {
                return;
            }

            const delta = parseInt(button.getAttribute('data-qty'), 10) || 0;
            const current = Math.max(1, parseInt(input.value, 10) || 1);
            input.value = String(Math.max(1, current + delta));
        });
    }

    function initColors() {
        document.querySelectorAll('.product-page__color').forEach(function (button) {
            button.addEventListener('click', function () {
                document.querySelectorAll('.product-page__color').forEach(function (item) {
                    item.classList.remove('product-page__color--active');
                });
                this.classList.add('product-page__color--active');
                const label = document.querySelector('.product-page__option-label b');
                if (label && this.getAttribute('data-color-name')) {
                    label.textContent = this.getAttribute('data-color-name');
                }
            });
        });
    }

    function initDynamicOptions(root) {
        const stateUrl = root.getAttribute('data-product-state-url');
        const productId = parseInt(root.getAttribute('data-product-id'), 10) || 0;
        if (!stateUrl || productId <= 0) {
            return;
        }

        function refresh() {
            fetch((window.appResolvePath || function (path) { return String(path || ''); })(stateUrl), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    product_id: productId,
                    selections: collectSelections(root)
                })
            }).then(function (response) {
                return response.json();
            }).then(function (state) {
                applyState(root, state);
            }).catch(function () {});
        }

        root.querySelectorAll('.product-page__chip').forEach(function (button) {
            button.addEventListener('click', function () {
                const code = this.getAttribute('data-option-code');
                root.querySelectorAll('.product-page__chip[data-option-code="' + code + '"]').forEach(function (item) {
                    item.classList.remove('is-active');
                });
                this.classList.add('is-active');
                refresh();
            });
        });

        root.querySelectorAll('select[data-option-code]').forEach(function (select) {
            select.addEventListener('change', refresh);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTabs();
        initColors();
        const root = document.querySelector('[data-product-page]');
        if (root) {
            initQty(root);
            initDynamicOptions(root);
        }
    });
})();
