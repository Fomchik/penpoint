/**
 * Favorites functionality with localStorage
 */
(function() {
    'use strict';
    if (window.__FAVORITES_SCRIPT_INIT__) return;
    window.__FAVORITES_SCRIPT_INIT__ = true;

    const FAVORITES_STORAGE_KEY = 'penpoint_favorites';
    const favoritesBadge = document.getElementById('favorites-badge');

    /**
     * Get favorites from localStorage
     */
    function getFavorites() {
        const favoritesJson = localStorage.getItem(FAVORITES_STORAGE_KEY);
        return favoritesJson ? JSON.parse(favoritesJson) : [];
    }

    /**
     * Save favorites to localStorage
     */
    function saveFavorites(favorites) {
        localStorage.setItem(FAVORITES_STORAGE_KEY, JSON.stringify(favorites));
        updateFavoritesBadge();
        updateWishlistButtons();
    }

    /**
     * Update favorites badge
     */
    function updateFavoritesBadge() {
        if (favoritesBadge) {
            const favorites = getFavorites();
            const count = favorites.length;
            favoritesBadge.textContent = count || '0';
            favoritesBadge.style.display = count > 0 ? 'flex' : 'none';
        }
    }

    /**
     * Check if product is in favorites
     */
    function isFavorite(productId) {
        const favorites = getFavorites();
        return favorites.includes(productId);
    }

    /**
     * Toggle favorite status
     */
    function toggleFavorite(productId) {
        const favorites = getFavorites();
        const index = favorites.indexOf(productId);
        const wasFavorite = index !== -1;

        if (wasFavorite) {
            favorites.splice(index, 1);
        } else {
            favorites.push(productId);
        }

        saveFavorites(favorites);
        return !wasFavorite;
    }

    /**
     * Update wishlist button states
     */
    function updateWishlistButtons() {
        const buttons = document.querySelectorAll('.product-card__wishlist, .product-page__wishlist');
        buttons.forEach(button => {
            const productId = parseInt(button.getAttribute('data-product-id'));
            if (productId && isFavorite(productId)) {
                button.classList.add('active');
                const icon = button.querySelector('img');
                if (icon) {
                    icon.style.filter = 'brightness(0) saturate(100%) invert(27%) sepia(95%) saturate(1352%) hue-rotate(347deg) brightness(95%) contrast(83%)';
                }
            } else {
                button.classList.remove('active');
                const icon = button.querySelector('img');
                if (icon) {
                    icon.style.filter = '';
                }
            }
        });
    }

    /**
     * Initialize wishlist buttons
     */
    function initWishlistButtons() {
        document.addEventListener('click', function(e) {
            const wishlistBtn = e.target.closest('.product-card__wishlist, .product-page__wishlist');
            if (!wishlistBtn) return;

            e.preventDefault();
            e.stopPropagation();

            // Get product ID from button or nearest product card
            let productId = parseInt(wishlistBtn.getAttribute('data-product-id'));
            if (!productId) {
                const productCard = wishlistBtn.closest('.product-card, .product-page');
                if (productCard) {
                    const addToCartBtn = productCard.querySelector('.product-card__add-to-cart, .product-page__add-to-cart');
                    if (addToCartBtn) {
                        productId = parseInt(addToCartBtn.getAttribute('data-product-id'));
                    }
                }
            }

            if (productId) {
                const wasAdded = toggleFavorite(productId);
                const message = wasAdded ? 'Товар добавлен в избранное' : 'Товар удалён из избранного';
                showNotification(message);
            }
        });
    }

    /**
     * Show notification
     */
    function showNotification(message) {
        const notification = document.createElement('div');
        notification.className = 'favorites-notification';
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #D55204;
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            font-size: 0.9375rem;
            font-weight: 500;
            animation: slideIn 0.3s ease;
            cursor: pointer;
        `;

        document.body.appendChild(notification);

        function hideNotification() {
            if (!notification.parentNode || notification.dataset.hiding === '1') return;
            notification.dataset.hiding = '1';
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }

        notification.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            hideNotification();
        });
        setTimeout(hideNotification, 3000);
    }
    /**
     * Initialize
     */
    function init() {
        updateFavoritesBadge();
        updateWishlistButtons();
        initWishlistButtons();

        // Update buttons when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateWishlistButtons();
        });
    }

    // Export functions
    window.Favorites = {
        getFavorites: getFavorites,
        isFavorite: isFavorite,
        toggleFavorite: toggleFavorite,
        updateBadge: updateFavoritesBadge
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
