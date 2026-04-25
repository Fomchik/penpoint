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

        if (!searchToggle || !searchContainer || !searchForm) return;

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

                const action = searchForm.getAttribute('action') || window.location.pathname;
                const target = new URL(action, window.location.origin);
                const params = new URLSearchParams();
                const formData = new FormData(searchForm);
                formData.forEach(function(value, key) {
                    const normalized = String(value || '').trim();
                    if (normalized !== '' && key !== 'q') {
                        params.append(key, normalized);
                    }
                });
                const nextUrl = target.pathname + (params.toString() ? '?' + params.toString() : '');
                window.location.assign(nextUrl);
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
