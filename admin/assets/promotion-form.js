(function () {
  "use strict";

  const scope = document.getElementById("promotion-scope");
  const fields = {
    categories: document.getElementById("categories-field"),
    products: document.getElementById("products-field"),
  };

  function refreshScopeFields() {
    if (!scope) return;

    const currentValue = scope.value;

    // Перебираем все связанные поля
    Object.keys(fields).forEach((key) => {
      const element = fields[key];
      if (!element) return;

      element.classList.toggle("is-hidden", key !== currentValue);
    });
  }

  if (scope) {
    scope.addEventListener("change", refreshScopeFields);
  }

  // Инициализация при загрузке
  refreshScopeFields();
})();
