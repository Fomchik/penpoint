/**
 * При уходе с сайта для незарегистрированных пользователей
 * очищаются корзина и избранное из localStorage
 */
(function() {
    'use strict';
    if (typeof window.PENPOINT_IS_GUEST === 'undefined' || !window.PENPOINT_IS_GUEST) return;

    function clearGuestStorage() {
        try {
            localStorage.removeItem('penpoint_cart');
            localStorage.removeItem('penpoint_favorites');
        } catch (e) {}
    }

    window.addEventListener('beforeunload', clearGuestStorage);
    window.addEventListener('pagehide', clearGuestStorage);
})();
