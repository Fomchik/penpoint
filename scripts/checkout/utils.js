export function formatPrice(num) {
  return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(Number(num) || 0) + ' ₽';
}

export function pluralizeProduct(num) {
  const n10 = num % 10;
  const n100 = num % 100;
  if (n10 === 1 && n100 !== 11) return 'товар';
  if (n10 >= 2 && n10 <= 4 && (n100 < 12 || n100 > 14)) return 'товара';
  return 'товаров';
}

export function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = String(text || '');
  return div.innerHTML;
}

export function debounce(fn, timeout) {
  let timer = null;
  return function debounced(...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), timeout);
  };
}
