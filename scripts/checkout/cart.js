import { CART_STORAGE_KEY } from './config.js';

export function getCart() {
  if (window.Cart && typeof window.Cart.getCart === 'function') {
    return window.Cart.getCart();
  }

  try {
    const raw = localStorage.getItem(CART_STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed : [];
  } catch (e) {
    return [];
  }
}

export function clearCart() {
  localStorage.removeItem(CART_STORAGE_KEY);
  window.dispatchEvent(new CustomEvent('penpoint:cart-updated', { detail: { cart: [] } }));
}

export function summarizeCart(cart) {
  return cart.reduce(
    (acc, item) => {
      const quantity = Math.max(1, parseInt(item.quantity, 10) || 1);
      const unitPrice = parseFloat(item.unit_price) || 0;
      const basePrice = parseFloat(item.base_price || item.unit_price) || unitPrice;
      acc.quantity += quantity;
      acc.subtotal += unitPrice * quantity;
      acc.discount += Math.max(0, basePrice - unitPrice) * quantity;
      acc.baseSubtotal += basePrice * quantity;
      return acc;
    },
    { quantity: 0, subtotal: 0, baseSubtotal: 0, discount: 0 }
  );
}
