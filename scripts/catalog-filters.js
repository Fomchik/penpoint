(function() {
    'use strict';

    const form = document.getElementById('catalog-filters-form');
    const contentEl = document.getElementById('catalog-content');
    const resetButton = document.getElementById('catalog-filters-reset');

    if (!form || !contentEl) return;

    let lastRequestId = 0;
    let activeController = null;
    let currentSort = (new URLSearchParams(window.location.search)).get('sort') || 'new';

    function syncSortUI(value) {
        currentSort = value || 'new';
        const select = document.querySelector('#catalog-sort-form select[name="sort"]');
        if (select) select.value = currentSort;
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
        return params;
    }

    function setLoading(isLoading) {
        contentEl.style.opacity = isLoading ? '0.5' : '';
        contentEl.style.pointerEvents = isLoading ? 'none' : '';
    }

    function updatePickupCounter(total) {
        const counter = form.querySelector('[data-pickup-counter]');
        if (!counter) return;
        counter.textContent = 'Самовывоз сегодня (' + String(Number(total) || 0) + ')';
    }

    function applyFromUrl(targetUrl) {
        lastRequestId += 1;
        const requestId = lastRequestId;
        const scrollTopBeforeRequest = window.pageYOffset || document.documentElement.scrollTop || 0;

        if (activeController) {
            try { activeController.abort(); } catch (e) {}
        }
        activeController = (typeof AbortController !== 'undefined') ? new AbortController() : null;

        setLoading(true);

        fetch(targetUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store',
            signal: activeController ? activeController.signal : undefined
        })
        .then(function(response) {
            return response.text();
        })
        .then(function(html) {
            if (requestId !== lastRequestId) return;

            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.getElementById('catalog-content');
            if (!newContent) return;
            const meta = doc.querySelector('[data-catalog-meta]');
            if (meta) {
                updatePickupCounter(meta.getAttribute('data-pickup-total'));
            }

            contentEl.innerHTML = newContent.innerHTML;
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, '', targetUrl);
            }
            window.scrollTo({ top: scrollTopBeforeRequest });

            if (window.Cart) window.Cart.updateCartBadge();
            if (window.Favorites) window.Favorites.updateBadge();
        })
        .catch(function(err) {
            if (err && err.name === 'AbortError') return;
            window.location.href = targetUrl;
        })
        .finally(function() {
            if (requestId !== lastRequestId) return;
            setLoading(false);
        });
    }

    function applyFilters() {
        const params = buildQueryParams();
        params.delete('page');
        applyFromUrl('/pages/catalog.php?' + params.toString());
    }

    const debounce = function(fn, ms) {
        let t;
        return function() {
            clearTimeout(t);
            t = setTimeout(fn, ms);
        };
    };

    const debouncedApply = debounce(applyFilters, 300);

    form.addEventListener('change', debouncedApply);
    form.addEventListener('input', function(e) {
        if (e.target.matches('.filters__input, .filters__search-input')) {
            debouncedApply();
        }
    });

    document.addEventListener('change', function(e) {
        if (e.target.matches('#catalog-sort-form select[name="sort"]')) {
            syncSortUI(e.target.value);
            applyFilters();
        }
    });

    contentEl.addEventListener('click', function(e) {
        const link = e.target.closest('.catalog__pagination a');
        if (!link) return;

        e.preventDefault();
        const href = link.getAttribute('href') || '';
        if (!href) return;

        const target = new URL(href, window.location.origin);
        const params = new URLSearchParams(target.search);
        const nextSort = params.get('sort');
        if (nextSort) syncSortUI(nextSort);

        applyFromUrl(target.pathname + '?' + params.toString());
    });

    if (resetButton) {
        resetButton.addEventListener('click', function() {
            const controls = form.querySelectorAll('input, select, textarea');
            controls.forEach(function(control) {
                if (control.type === 'checkbox' || control.type === 'radio') {
                    control.checked = false;
                } else {
                    control.value = '';
                }
            });

            syncSortUI('new');
            applyFilters();
        });
    }

    syncSortUI(currentSort);
    const initialMeta = contentEl.querySelector('[data-catalog-meta]');
    if (initialMeta) {
        updatePickupCounter(initialMeta.getAttribute('data-pickup-total'));
    }
})();
