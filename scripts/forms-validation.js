(function () {
    'use strict';

    function applyNoValidate(root) {
        const scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('form').forEach(function (form) {
            if (!form.hasAttribute('novalidate')) {
                form.setAttribute('novalidate', 'novalidate');
            }
        });
    }

    function init() {
        applyNoValidate(document);

        if ('MutationObserver' in window) {
            const observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (!node || node.nodeType !== 1) return;
                        if (node.tagName === 'FORM') {
                            node.setAttribute('novalidate', 'novalidate');
                            return;
                        }
                        applyNoValidate(node);
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
