// Минимальная логика количества на странице товара
(function () {
  'use strict';

  function initQty() {
    const input = document.querySelector('.product-page__qty-input');
    if (!input) return;

    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.product-page__qty-btn');
      if (!btn) return;

      const delta = parseInt(btn.getAttribute('data-qty'), 10) || 0;
      const current = Math.max(1, parseInt(input.value, 10) || 1);
      input.value = String(Math.max(1, current + delta));
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initQty);
  } else {
    initQty();
  }
})();

