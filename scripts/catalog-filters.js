/**
 * Фильтры каталога: при выборе параметра сразу применяются через AJAX
 */
(function() {
    'use strict';

    const form = document.getElementById('catalog-filters-form');
    const contentEl = document.getElementById('catalog-content');

    if (!form || !contentEl) return;

    function getFormData(formEl) {
        const fd = new FormData(formEl);
        const params = new URLSearchParams();
        for (const [k, v] of fd.entries()) {
            if (v) params.append(k, v);
        }
        return params;
    }

    function buildQueryParams() {
        const params = getFormData(form);
        params.delete('page');

        const sortSelect = document.querySelector('#catalog-sort-form select[name="sort"]');
        if (sortSelect && sortSelect.value) {
            params.set('sort', sortSelect.value);
        } else {
            params.set('sort', 'new');
        }

        return params.toString();
    }

    function applyFilters() {
        const allParams = buildQueryParams();
        const url = '/pages/catalog.php' + (allParams ? '?' + allParams : '');

        contentEl.style.opacity = '0.5';
        contentEl.style.pointerEvents = 'none';

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContent = doc.getElementById('catalog-content');
                if (newContent) {
                    contentEl.innerHTML = newContent.innerHTML;
                    if (window.history && typeof window.history.replaceState === 'function') {
                        window.history.replaceState({}, '', url);
                    }
                    if (window.Cart) window.Cart.updateCartBadge();
                    if (window.Favorites) window.Favorites.updateBadge();
                }
            })
            .catch(function() {
                form.submit();
            })
            .finally(function() {
                contentEl.style.opacity = '';
                contentEl.style.pointerEvents = '';
            });
    }

    function debounce(fn, ms) {
        let t;
        return function() {
            clearTimeout(t);
            t = setTimeout(fn, ms);
        };
    }

    const debouncedApply = debounce(applyFilters, 300);

    form.addEventListener('change', function() {
        debouncedApply();
    });

    form.addEventListener('input', function(e) {
        if (e.target.matches('.filters__input, .filters__search-input')) {
            debouncedApply();
        }
    });

    document.addEventListener('change', function(event) {
        if (event.target && event.target.matches('#catalog-sort-form select[name="sort"]')) {
            applyFilters();
        }
    });
})();
