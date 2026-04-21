(function () {
    'use strict';

    function formatPrice(value) {
        return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(Number(value) || 0) + ' ₽';
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function normalizeImages(images) {
        if (!Array.isArray(images)) {
            return [];
        }

        return Array.from(new Set(images.map(function (item) {
            return String(item || '').trim();
        }).filter(function (item) {
            return item !== '';
        })));
    }
    function renderProductImageSlider(root, images) {
        const image = root.querySelector('[data-product-image]');
        const dots = root.querySelector('[data-product-image-dots]');
        if (!image) {
            return;
        }

        const uniqueImages = normalizeImages(images);
        if (uniqueImages.length > 0) {
            image.src = uniqueImages[0];
        }

        if (dots) {
            dots.innerHTML = '';
            dots.classList.add('product-page__image-dots--hidden');
        }
    }
    function collectSelections(root) {
        const result = {};
        root.querySelectorAll('.product-page__variant-option.is-active').forEach(function (button) {
            result[button.getAttribute('data-option-code')] = button.getAttribute('data-option-value') || '';
        });
        return result;
    }

    function renderVariantAttributes(root, attributes) {
        const holder = root.querySelector('[data-product-spec-attributes]');
        if (!holder) {
            return;
        }

        const entries = Object.entries(attributes || {}).filter(function (item) {
            return String(item[0] || '').trim() !== '' && String(item[1] || '').trim() !== '';
        });

        if (entries.length === 0) {
            holder.innerHTML = '';
            return;
        }

        holder.innerHTML = entries.map(function (item) {
            return '' +
                '<tr>' +
                    '<td class="product-page__spec-name">' + escapeHtml(item[0]) + '</td>' +
                    '<td class="product-page__spec-value">' + escapeHtml(item[1]) + '</td>' +
                '</tr>';
        }).join('');
    }

    function applyState(root, state) {
        if (!state) {
            return;
        }

        const price = root.querySelector('[data-final-price]');
        const basePrice = root.querySelector('[data-base-price]');
        const stock = root.querySelector('[data-product-stock]');
        const addButton = root.querySelector('.product-page__add-to-cart');

        if (price) {
            const showBase = Number(state.base_price) > Number(state.price);
            price.textContent = state.price_formatted || formatPrice(state.price);
            price.classList.toggle('product-page__price-new', showBase);
            price.classList.toggle('product-page__price-current', !showBase);
        }
        if (basePrice) {
            const showBase = Number(state.base_price) > Number(state.price);
            basePrice.textContent = state.base_price_formatted || formatPrice(state.base_price);
            basePrice.classList.toggle('visually-hidden', !showBase);
        }
        if (stock) stock.textContent = String(Number(state.stock) || 0) + ' шт';
        renderVariantAttributes(root, state.attributes || {});

        const images = Array.isArray(state.images) && state.images.length ? state.images : (state.image ? [state.image] : []);
        renderProductImageSlider(root, images);
        
        // При смене варианта сбрасываем слайдер на первое изображение
        if (addButton) {
            addButton.setAttribute('data-product-price', String(Number(state.price) || 0));
            addButton.setAttribute('data-product-old-price', String(Number(state.base_price) || 0));
            addButton.setAttribute('data-variant-id', state.variant_id === null || state.variant_id === undefined ? '' : String(Number(state.variant_id) || 0));
            addButton.disabled = !state.in_stock;
        }
    }

    function parseInitialProductImages(root) {
        const payload = root.getAttribute('data-product-images');
        if (!payload) {
            return [];
        }

        try {
            const images = JSON.parse(payload);
            return normalizeImages(images);
        } catch (error) {
            return [];
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
                if (!state || typeof state !== 'object' || !Object.prototype.hasOwnProperty.call(state, 'price')) {
                    return;
                }
                applyState(root, state);
            }).catch(function () {});
        }

        root.querySelectorAll('.product-page__variant-option').forEach(function (item) {
            function activateOption() {
                const code = item.getAttribute('data-option-code');
                root.querySelectorAll('.product-page__variant-option[data-option-code="' + code + '"]').forEach(function (option) {
                    option.classList.remove('is-active');
                });
                item.classList.add('is-active');
                refresh();
            }

            item.addEventListener('click', activateOption);
            item.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    activateOption();
                }
            });
        });

        if (root.querySelectorAll('.product-page__variant-option').length === 0) {
            const initialImages = parseInitialProductImages(root);
            if (initialImages.length > 0) {
                renderProductImageSlider(root, initialImages);
            }
        }

        refresh();
    }

    function copyText(value) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(value);
        }

        return new Promise(function (resolve, reject) {
            const input = document.createElement('input');
            input.value = value;
            document.body.appendChild(input);
            input.select();
            try {
                document.execCommand('copy');
                document.body.removeChild(input);
                resolve();
            } catch (error) {
                document.body.removeChild(input);
                reject(error);
            }
        });
    }

    function initShare(root) {
        const button = root.querySelector('[data-share-button]');
        const notice = root.querySelector('[data-share-notice]');
        if (!button) {
            return;
        }

        button.addEventListener('click', function () {
            copyText(window.location.href).then(function () {
                if (!notice) {
                    return;
                }
                notice.classList.add('is-visible');
                window.setTimeout(function () {
                    notice.classList.remove('is-visible');
                }, 1800);
            }).catch(function () {});
        });
    }

    function renderReview(review) {
        const stars = [];
        for (let i = 1; i <= 5; i += 1) {
            stars.push(
                '<span class="product-review__star' + (i <= Number(review.rating) ? ' is-active' : '') + '">' +
                '<img src="/assets/icons/star.svg" alt="" width="16" height="16">' +
                '</span>'
            );
        }

        return '' +
            '<article class="product-review">' +
                '<div class="product-review__head">' +
                    '<strong>' + escapeHtml(review.user_name) + '</strong>' +
                    '<span>' + escapeHtml(review.created_at_formatted) + '</span>' +
                '</div>' +
                '<div class="product-review__rating">' + stars.join('') + '</div>' +
                '<div class="product-review__text">' + escapeHtml(review.comment).replace(/\n/g, '<br>') + '</div>' +
            '</article>';
    }

    function initReviews(root) {
        const form = root.querySelector('[data-review-form]');
        if (!form) {
            return;
        }

        const message = form.querySelector('[data-review-message]');
        const stars = form.querySelectorAll('[data-rating-value]');
        const ratingInput = form.querySelector('[data-review-rating-input]');
        const list = root.querySelector('[data-review-list]');
        const submitUrl = root.getAttribute('data-review-submit-url');

        function setRating(value) {
            ratingInput.value = String(value);
            stars.forEach(function (button) {
                button.classList.toggle('is-active', Number(button.getAttribute('data-rating-value')) <= value);
            });
        }

        stars.forEach(function (button) {
            button.addEventListener('click', function () {
                setRating(Number(button.getAttribute('data-rating-value')) || 0);
            });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const productId = parseInt(form.querySelector('[name="product_id"]').value, 10) || 0;
            const commentField = form.querySelector('[name="comment"]');
            const payload = {
                product_id: productId,
                rating: parseInt(ratingInput.value, 10) || 0,
                comment: commentField ? commentField.value : ''
            };

            fetch((window.appResolvePath || function (path) { return String(path || ''); })(submitUrl), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': window.APP_CSRF_TOKEN || ''
                },
                body: JSON.stringify(payload)
            }).then(function (response) {
                return response.json().then(function (json) {
                    return { ok: response.ok, json: json };
                });
            }).then(function (result) {
                if (!result.ok || !result.json.success) {
                    throw new Error(result.json.message || 'Не удалось сохранить отзыв.');
                }

                if (message) {
                    message.textContent = 'Отзыв сохранён.';
                }
                if (list) {
                    const empty = list.querySelector('.product-reviews__empty');
                    if (empty) {
                        empty.remove();
                    }
                    list.insertAdjacentHTML('afterbegin', renderReview(result.json.review));
                }
                form.classList.add('product-reviews__form--disabled');
                form.querySelectorAll('textarea, button[type="submit"]').forEach(function (field) {
                    field.disabled = true;
                });
            }).catch(function (error) {
                if (message) {
                    message.textContent = error.message;
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTabs();
        initColors();
        const root = document.querySelector('[data-product-page]');
        if (root) {
            initQty(root);
            initDynamicOptions(root);
            initShare(root);
            initReviews(root);
        }
    });
})();
