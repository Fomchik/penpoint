(function () {
    'use strict';

    function bindCustomValidation(root) {
        const scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('form[novalidate]').forEach(function (form) {
            if (form.dataset.validationBound === '1') {
                return;
            }
            form.dataset.validationBound = '1';
            form.addEventListener('submit', function (event) {
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    event.preventDefault();
                    if (typeof form.reportValidity === 'function') {
                        form.reportValidity();
                    }
                }
            });
        });
    }

    function init() {
        bindCustomValidation(document);

        if ('MutationObserver' in window) {
            const observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (!node || node.nodeType !== 1) return;
                        bindCustomValidation(node);
                    });
                });
            });
            observer.observe(document.documentElement, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
