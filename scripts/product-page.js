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

    function renderProductImage(root, images) {
        const image = root.querySelector('[data-product-image]');
        if (!image) {
            return;
        }

        const uniqueImages = normalizeImages(images);
        if (uniqueImages.length > 0) {
            root.setAttribute('data-gallery-images', JSON.stringify(uniqueImages));
            root.setAttribute('data-gallery-index', '0');
            image.src = uniqueImages[0];
        }
    }

    function getGalleryImages(root) {
        const raw = root.getAttribute('data-gallery-images');
        if (!raw) {
            return [];
        }

        try {
            return normalizeImages(JSON.parse(raw));
        } catch (error) {
            return [];
        }
    }

    function showGalleryImage(root, index) {
        const image = root.querySelector('[data-product-image]');
        if (!image) {
            return;
        }

        const images = getGalleryImages(root);
        if (images.length === 0) {
            return;
        }

        const nextIndex = ((index % images.length) + images.length) % images.length;
        root.setAttribute('data-gallery-index', String(nextIndex));
        image.src = images[nextIndex];
    }

    function initImageSwipe(root) {
        const slider = root.querySelector('[data-product-image-slider]');
        if (!slider || slider.getAttribute('data-swipe-ready') === '1') {
            return;
        }
        slider.setAttribute('data-swipe-ready', '1');

        let startX = 0;
        let startY = 0;
        let tracking = false;

        slider.addEventListener('touchstart', function (event) {
            if (!event.touches || event.touches.length !== 1) {
                tracking = false;
                return;
            }

            const point = event.touches[0];
            startX = point.clientX;
            startY = point.clientY;
            tracking = true;
        }, { passive: true });

        slider.addEventListener('touchend', function (event) {
            if (!tracking || !event.changedTouches || event.changedTouches.length !== 1) {
                return;
            }

            tracking = false;
            const point = event.changedTouches[0];
            const diffX = point.clientX - startX;
            const diffY = point.clientY - startY;
            if (Math.abs(diffX) < 35 || Math.abs(diffX) <= Math.abs(diffY)) {
                return;
            }

            const images = getGalleryImages(root);
            if (images.length <= 1) {
                return;
            }

            const currentIndex = parseInt(root.getAttribute('data-gallery-index') || '0', 10) || 0;
            const nextIndex = diffX < 0 ? currentIndex + 1 : currentIndex - 1;
            showGalleryImage(root, nextIndex);
        }, { passive: true });
    }

    function collectSelections(root) {
        const result = {};
        root.querySelectorAll('.product-page__variant-option.is-active').forEach(function (button) {
            result[button.getAttribute('data-option-code')] = button.getAttribute('data-option-value') || '';
        });
        return result;
    }

    function mapAttributeLabel(key) {
        const map = {
            color: 'Цвет',
            size: 'Размер',
            format: 'Формат',
            volume: 'Объем',
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

    function renderVariantAttributes(root, attributes) {
        const holder = root.querySelector('[data-product-spec-attributes]');
        const emptyHolder = root.querySelector('[data-product-spec-empty]');
        if (!holder) {
            return;
        }

        const entries = Object.entries(attributes || {}).filter(function (item) {
            return String(item[0] || '').trim() !== '' && String(item[1] || '').trim() !== '';
        });

        if (entries.length === 0) {
            holder.innerHTML = '';
            if (emptyHolder && emptyHolder.getAttribute('data-static-specs') !== '1') {
                emptyHolder.hidden = false;
            }
            return;
        }

        holder.innerHTML = entries.map(function (item) {
            return '' +
                '<tr>' +
                    '<td class="product-page__spec-name">' + escapeHtml(mapAttributeLabel(item[0])) + '</td>' +
                    '<td class="product-page__spec-value">' + escapeHtml(item[1]) + '</td>' +
                '</tr>';
        }).join('');

        if (emptyHolder) {
            emptyHolder.hidden = true;
        }
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

        const hasUnavailableVariant = Boolean(state.has_variants) && (state.variant_id === null || state.variant_id === undefined);
        const inStock = Boolean(state.in_stock) && !hasUnavailableVariant;
        if (stock) {
            stock.textContent = String(hasUnavailableVariant ? 0 : (Number(state.stock) || 0)) + ' шт';
        }

        renderVariantAttributes(root, state.attributes || {});

        const images = Array.isArray(state.images) && state.images.length ? state.images : (state.image ? [state.image] : []);
        renderProductImage(root, images);

        if (addButton) {
            addButton.setAttribute('data-product-price', String(Number(state.price) || 0));
            addButton.setAttribute('data-product-old-price', String(Number(state.base_price) || 0));
            addButton.setAttribute('data-variant-id', state.variant_id === null || state.variant_id === undefined ? '' : String(Number(state.variant_id) || 0));
            addButton.disabled = !inStock;
            addButton.title = hasUnavailableVariant ? 'Выбранный вариант больше недоступен' : '';
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
                renderProductImage(root, initialImages);
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

    document.addEventListener('DOMContentLoaded', function () {
        initTabs();
        initColors();
        const root = document.querySelector('[data-product-page]');
        if (root) {
            initQty(root);
            initDynamicOptions(root);
            initShare(root);
            initImageSwipe(root);
        }
    });
})();
