(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function createRowHtml(index, item) {
        const row = item || { name: '', value: '' };
        return '' +
            '<div class="admin-characteristics__row" data-characteristics-row>' +
                '<input type="text" name="product_characteristics[' + index + '][name]" value="' + escapeHtml(row.name) + '" placeholder="Название" list="characteristics-catalog">' +
                '<input type="text" name="product_characteristics[' + index + '][value]" value="' + escapeHtml(row.value) + '" placeholder="Значение">' +
                '<button type="button" class="admin-text-btn" data-characteristics-remove>Удалить</button>' +
            '</div>';
    }

    function reindexRows(list) {
        list.querySelectorAll('[data-characteristics-row]').forEach(function (row, index) {
            const nameInput = row.querySelector('input[name*="[name]"]');
            const valueInput = row.querySelector('input[name*="[value]"]');
            if (nameInput) {
                nameInput.name = 'product_characteristics[' + index + '][name]';
            }
            if (valueInput) {
                valueInput.name = 'product_characteristics[' + index + '][value]';
            }
        });
    }

    function init(root) {
        const list = root.querySelector('[data-characteristics-list]');
        const addBtn = root.querySelector('[data-characteristics-add]');

        if (!list || !addBtn) return;

        const raw = root.getAttribute('data-initial-characteristics');
        if (raw) {
            try {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed) && parsed.length > 0) {
                    list.innerHTML = parsed.map(function (item, index) {
                        return createRowHtml(index, item);
                    }).join('');
                }
            } catch (e) {}
        }

        if (list.children.length === 0) {
            list.insertAdjacentHTML('beforeend', createRowHtml(0, { name: '', value: '' }));
        }
        reindexRows(list);

        addBtn.addEventListener('click', function () {
            list.insertAdjacentHTML('beforeend', createRowHtml(list.children.length, { name: '', value: '' }));
            reindexRows(list);
            const lastRow = list.lastElementChild;
            if (lastRow) {
                const firstInput = lastRow.querySelector('input');
                if (firstInput) firstInput.focus();
            }
        });

        list.addEventListener('click', function (event) {
            const btn = event.target.closest('[data-characteristics-remove]');
            if (!btn) return;

            const row = btn.closest('[data-characteristics-row]');
            if (row) {
                row.remove();
                if (list.children.length === 0) {
                    list.insertAdjacentHTML('beforeend', createRowHtml(0, { name: '', value: '' }));
                }
                reindexRows(list);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-characteristics]').forEach(init);
    });
})();