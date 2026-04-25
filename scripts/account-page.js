(function () {
    'use strict';

    const resolvePath = window.appResolvePath || function (path) {
        return String(path || '');
    };
    const FAVORITES_CACHE_KEY = 'penpoint_favorites_products_cache';

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
        const count = getFavorites().length;
        countBadge.textContent = count;
        const clearButton = document.getElementById('favorites-clear');
        if (clearButton) {
            clearButton.hidden = count === 0;
        }
    }

    function getCache(idsKey) {
        try {
            const raw = sessionStorage.getItem(FAVORITES_CACHE_KEY);
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (!parsed || parsed.idsKey !== idsKey || !Array.isArray(parsed.products)) {
                return null;
            }
            return parsed.products;
        } catch (e) {
            return null;
        }
    }

    function setCache(idsKey, products) {
        try {
            sessionStorage.setItem(FAVORITES_CACHE_KEY, JSON.stringify({
                idsKey: idsKey,
                products: Array.isArray(products) ? products : [],
                updatedAt: Date.now()
            }));
        } catch (e) {}
    }

    function clearCache() {
        try {
            sessionStorage.removeItem(FAVORITES_CACHE_KEY);
        } catch (e) {}
    }

    function renderEmpty(grid) {
        grid.innerHTML = '<div class="empty-state"><div class="empty-state__icon">&#10084;</div><p>РЎРїРёСЃРѕРє РёР·Р±СЂР°РЅРЅРѕРіРѕ РїСѓСЃС‚.</p></div>';
    }

    function renderError(grid, message) {
        grid.innerHTML = '<div class="empty-state"><p>' + escapeHtml(message) + '</p></div>';
    }

    function renderProducts(grid, products) {
        if (!Array.isArray(products) || products.length === 0) {
            renderEmpty(grid);
            return;
        }

        grid.innerHTML = products.map(function (product) {
            return '' +
                '<article class="product-card">' +
                '<button type="button" class="product-card__wishlist" aria-label="Р’ РёР·Р±СЂР°РЅРЅРѕРµ" data-product-id="' + (Number(product.id) || 0) + '">' +
                '<img src="' + resolvePath('/assets/icons/heart.svg') + '" alt="" class="product-card__wishlist-icon">' +
                '</button>' +
                '<a href="' + resolvePath('/pages/page-product.php?id=' + (Number(product.id) || 0)) + '" class="product-card__link">' +
                '<img src="' + safePath(product.image, '/assets/icons/favicon.svg') + '" alt="' + escapeHtml(product.name) + '" class="product-card__image" loading="lazy">' +
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
    }

    function requestProductsByIds(ids) {
        return fetch(resolvePath('/api/products.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ ids: ids })
        }).then(function (response) {
            return response.json().catch(function () { return null; }).then(function (payload) {
                return { ok: response.ok, status: response.status, payload: payload };
            });
        });
    }

    function loadFavorites() {
        const favorites = getFavorites();
        const grid = document.getElementById('favorites-grid');
        if (!grid) {
            return;
        }

        updateFavoritesCount();

        if (favorites.length === 0) {
            clearCache();
            renderEmpty(grid);
            return;
        }

        const idsKey = favorites.slice().sort(function (a, b) { return a - b; }).join(',');
        const cached = getCache(idsKey);
        if (cached) {
            renderProducts(grid, cached);
        }

        requestProductsByIds(favorites)
            .then(function (result) {
                if (!result.ok || !Array.isArray(result.payload)) {
                    if (result.status === 404) {
                        throw new Error('РўРѕРІР°СЂС‹ РёР· РёР·Р±СЂР°РЅРЅРѕРіРѕ РЅРµ РЅР°Р№РґРµРЅС‹. РџРѕРїСЂРѕР±СѓР№С‚Рµ РѕР±РЅРѕРІРёС‚СЊ СЃС‚СЂР°РЅРёС†Сѓ.');
                    }
                    if (result.status >= 500) {
                        throw new Error('РЎРµСЂРІРёСЃ РІСЂРµРјРµРЅРЅРѕ РЅРµРґРѕСЃС‚СѓРїРµРЅ. РџРѕРїСЂРѕР±СѓР№С‚Рµ РїРѕР·Р¶Рµ.');
                    }
                    throw new Error('РќРµ СѓРґР°Р»РѕСЃСЊ Р·Р°РіСЂСѓР·РёС‚СЊ РёР·Р±СЂР°РЅРЅРѕРµ. РћР±РЅРѕРІРёС‚Рµ СЃС‚СЂР°РЅРёС†Сѓ.');
                }
                setCache(idsKey, result.payload);
                renderProducts(grid, result.payload);
            })
            .catch(function (error) {
                if (!cached) {
                    renderError(grid, error && error.message ? error.message : 'РћС€РёР±РєР° Р·Р°РіСЂСѓР·РєРё РёР·Р±СЂР°РЅРЅРѕРіРѕ.');
                }
            });
    }

    function init() {
        const main = document.querySelector('main[data-account-tab]');
        const currentTab = main ? main.getAttribute('data-account-tab') : '';
        updateFavoritesCount();

        const clearButton = document.getElementById('favorites-clear');
        if (clearButton) {
            clearButton.addEventListener('click', function () {
                const favorites = getFavorites();
                if (!favorites.length) {
                    return;
                }
                if (!window.confirm('РћС‡РёСЃС‚РёС‚СЊ РёР·Р±СЂР°РЅРЅРѕРµ РїРѕР»РЅРѕСЃС‚СЊСЋ?')) {
                    return;
                }

                if (window.Favorites && typeof window.Favorites.clearFavorites === 'function') {
                    window.Favorites.clearFavorites();
                }
                clearCache();
                if (currentTab === 'favorites') {
                    loadFavorites();
                } else {
                    updateFavoritesCount();
                }
            });
        }

        if (currentTab === 'favorites') {
            loadFavorites();
        }

        window.addEventListener('penpoint:favorites-updated', function () {
            updateFavoritesCount();
            clearCache();
            if (currentTab === 'favorites') {
                loadFavorites();
            }
        });

        window.addEventListener('storage', function (event) {
            if (event.key === 'penpoint_favorites') {
                updateFavoritesCount();
                clearCache();
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

