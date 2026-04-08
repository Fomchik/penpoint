(function () {
    'use strict';

    const resolvePath = window.appResolvePath || function (path) {
        return String(path || '');
    };

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function safePath(value, fallback) {
        const s = String(value || '');
        if (!s.startsWith('/')) {
            return resolvePath(fallback || '');
        }
        return resolvePath(s).replace(/"/g, '%22').replace(/'/g, '%27');
    }

    function getFavorites() {
        return window.Favorites ? window.Favorites.getFavorites() : [];
    }

    function updateFavoritesCount() {
        const countBadge = document.getElementById('favorites-count');
        if (!countBadge) return;
        countBadge.textContent = getFavorites().length;
    }

    function loadFavorites() {
        const favorites = getFavorites();
        const grid = document.getElementById('favorites-grid');

        if (!grid) {
            return;
        }

        updateFavoritesCount();

        if (favorites.length === 0) {
            grid.innerHTML = '<div class="empty-state"><div class="empty-state__icon">вќ¤</div><p>РЎРїРёСЃРѕРє РёР·Р±СЂР°РЅРЅРѕРіРѕ РїСѓСЃС‚</p></div>';
            return;
        }

        fetch(resolvePath('/api/products.php?ids=' + favorites.join(',')))
            .then(function (response) { return response.json(); })
            .then(function (products) {
                if (!Array.isArray(products) || products.length === 0) {
                    grid.innerHTML = '<div class="empty-state"><div class="empty-state__icon">вќ¤</div><p>РЎРїРёСЃРѕРє РёР·Р±СЂР°РЅРЅРѕРіРѕ РїСѓСЃС‚</p></div>';
                    return;
                }

                grid.innerHTML = products.map(function (product) {
                    return '' +
                        '<article class="product-card">' +
                        '<button type="button" class="product-card__wishlist" aria-label="Р’ РёР·Р±СЂР°РЅРЅРѕРµ" data-product-id="' + (Number(product.id) || 0) + '">' +
                        '<img src="' + resolvePath('/assets/icons/heart.svg') + '" alt="" class="product-card__wishlist-icon">' +
                        '</button>' +
                        '<a href="' + resolvePath('/pages/page-product.php?id=' + (Number(product.id) || 0)) + '" class="product-card__link">' +
                        '<img src="' + safePath(product.image, '/assets/product_images/default.png') + '" alt="' + escapeHtml(product.name) + '" class="product-card__image" loading="lazy">' +
                        '</a>' +
                        '<h3 class="product-card__name">' +
                        '<a href="' + resolvePath('/pages/page-product.php?id=' + (Number(product.id) || 0)) + '">' + escapeHtml(product.name) + '</a>' +
                        '</h3>' +
                        '<div class="product-card__price">' +
                        (Number(product.discount_percent) > 0 ? '<span class="product-card__price--old">' + escapeHtml(product.old_price_formatted) + '</span>' : '') +
                        '<span class="product-card__price--new">' + escapeHtml(product.price_formatted) + '</span>' +
                        '</div>' +
                        '<button type="button" class="product-card__add-to-cart" data-product-id="' + (Number(product.id) || 0) + '" data-product-name="' + escapeHtml(product.name) + '" data-product-price="' + (Number(product.price_raw) || 0) + '" data-product-old-price="' + (Number(product.old_price_raw) || 0) + '">' +
                        '<img src="' + resolvePath('/assets/icons/cart.svg') + '" alt="" class="product-card__add-to-cart-icon" width="18" height="18">Р’ РєРѕСЂР·РёРЅСѓ' +
                        '</button>' +
                        '</article>';
                }).join('');

                if (window.Favorites) {
                    window.Favorites.updateBadge();
                }
            })
            .catch(function () {
                grid.innerHTML = '<div class="empty-state"><p>РћС€РёР±РєР° РїСЂРё Р·Р°РіСЂСѓР·РєРµ РёР·Р±СЂР°РЅРЅРѕРіРѕ</p></div>';
            });
    }

    function init() {
        const main = document.querySelector('main[data-account-tab]');
        const currentTab = main ? main.getAttribute('data-account-tab') : '';
        updateFavoritesCount();

        if (currentTab === 'favorites') {
            loadFavorites();
        }

        window.addEventListener('penpoint:favorites-updated', function () {
            updateFavoritesCount();
            if (currentTab === 'favorites') {
                loadFavorites();
            }
        });

        window.addEventListener('storage', function (event) {
            if (event.key === 'penpoint_favorites') {
                updateFavoritesCount();
                if (currentTab === 'favorites') {
                    loadFavorites();
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
