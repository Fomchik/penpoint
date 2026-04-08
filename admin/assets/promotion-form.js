(function () {
    'use strict';

    const scope = document.getElementById('promotion-scope');
    const categoriesField = document.getElementById('categories-field');
    const productsField = document.getElementById('products-field');

    function refreshScopeFields() {
        if (!scope || !categoriesField || !productsField) {
            return;
        }

        const value = scope.value;
        categoriesField.style.display = value === 'categories' ? 'grid' : 'none';
        productsField.style.display = value === 'products' ? 'grid' : 'none';
    }

    if (scope) {
        scope.addEventListener('change', refreshScopeFields);
    }

    refreshScopeFields();
})();
