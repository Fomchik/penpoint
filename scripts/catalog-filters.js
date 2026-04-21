(function() {
    'use strict';

    const form = document.getElementById('catalog-filters-form');
    const contentEl = document.getElementById('catalog-content');

    if (!form || !contentEl) return;

    let lastRequestId = 0;
    let activeController = null;
    
    const urlParams = new URLSearchParams(window.location.search);
    let currentSort = urlParams.get('sort') || 'new';

    function syncSortUI(value) {
        currentSort = value || 'new';
        const select = document.querySelector('#catalog-sort-form select[name="sort"]');
        const hidden = document.getElementById('catalog-sort-hidden');
        
        if (select) select.value = currentSort;
        if (hidden) hidden.value = currentSort;
    }

    function buildQueryParams() {
        const params = new URLSearchParams();
        const fd = new FormData(form);

        for (const [k, v] of fd.entries()) {
            if (v !== '' && k !== 'sort') {
                params.append(k, v);
            }
        }

        params.set('sort', currentSort);
        params.delete('page');

        return params.toString();
    }

    function applyFilters() {
        lastRequestId += 1;
        const requestId = lastRequestId;

        if (activeController) {
            try { activeController.abort(); } catch (e) {}
        }
        activeController = (typeof AbortController !== 'undefined') ? new AbortController() : null;

        const queryString = buildQueryParams();
        const url = '/pages/catalog.php?' + queryString;

        contentEl.style.opacity = '0.5';
        contentEl.style.pointerEvents = 'none';

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store',
            signal: activeController ? activeController.signal : undefined
        })
        .then(r => r.text())
        .then(html => {
            if (requestId !== lastRequestId) return;

            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.getElementById('catalog-content');

            if (newContent) {
                contentEl.innerHTML = newContent.innerHTML;
                
                syncSortUI(currentSort);
                
                if (window.history && window.history.replaceState) {
                    window.history.replaceState({}, '', url);
                }

                if (window.Cart) window.Cart.updateCartBadge();
                if (window.Favorites) window.Favorites.updateBadge();
            }
        })
        .catch(err => {
            if (err.name === 'AbortError') return;
            form.submit();
        })
        .finally(() => {
            if (requestId !== lastRequestId) return;
            contentEl.style.opacity = '';
            contentEl.style.pointerEvents = '';
        });
    }

    const debounce = (fn, ms) => {
        let t;
        return () => { clearTimeout(t); t = setTimeout(fn, ms); };
    };

    const debouncedApply = debounce(applyFilters, 300);

    form.addEventListener('change', debouncedApply);
    form.addEventListener('input', (e) => {
        if (e.target.matches('.filters__input, .filters__search-input')) {
            debouncedApply();
        }
    });

    document.addEventListener('change', (e) => {
        if (e.target.matches('#catalog-sort-form select[name="sort"]')) {
            syncSortUI(e.target.value);
            applyFilters();
        }
    });

    syncSortUI(currentSort);
})();