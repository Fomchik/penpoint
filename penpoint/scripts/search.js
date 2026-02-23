/**
 * Поиск: иконка при клике плавно сдвигается влево, появляется поле ввода
 */
(function() {
    'use strict';

    function init() {
        const searchToggle = document.getElementById('search-toggle');
        const searchContainer = document.getElementById('header-search');
        const searchForm = document.getElementById('header-search-form');
        const searchInput = document.querySelector('.header__search-input');

        if (!searchToggle || !searchContainer) return;

        searchToggle.addEventListener('click', function(e) {
            e.preventDefault();
            searchContainer.classList.toggle('header__search--expanded');
            if (searchContainer.classList.contains('header__search--expanded')) {
                setTimeout(function() {
                    if (searchInput) searchInput.focus();
                }, 150);
            }
        });

        searchForm.addEventListener('submit', function(e) {
            if (searchInput && !searchInput.value.trim()) {
                e.preventDefault();
                searchContainer.classList.remove('header__search--expanded');
            }
        });

        document.addEventListener('click', function(e) {
            if (searchContainer.classList.contains('header__search--expanded') &&
                !searchContainer.contains(e.target)) {
                searchContainer.classList.remove('header__search--expanded');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && searchContainer.classList.contains('header__search--expanded')) {
                searchContainer.classList.remove('header__search--expanded');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
