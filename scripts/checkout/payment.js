import { CART_STORAGE_KEY, PAYMENT_LOGO_SRC } from './config.js';
import { clearCart, getCart } from './cart.js';

export function initPaymentPanel() {
  const panel = document.getElementById('online-payment-panel');
  const methodRadios = document.querySelectorAll('input[name="payment_method"]');
  const providerRadios = document.querySelectorAll('input[name="payment_provider"]');
  const logo = document.querySelector('.checkout-payment-option__logo-image');

  if (logo) {
    logo.src = PAYMENT_LOGO_SRC;
    logo.alt = 'ЮKassa';
  }

  if (!panel || !methodRadios.length) return;

  function syncPanelVisibility() {
    const selected = document.querySelector('input[name="payment_method"]:checked');
    panel.classList.toggle('checkout-payment-panel--open', !!selected && selected.value === 'online');
  }

  methodRadios.forEach((radio) => radio.addEventListener('change', syncPanelVisibility));

  providerRadios.forEach((radio) => {
    radio.addEventListener('change', function () {
      providerRadios.forEach((item) => {
        const option = item.closest('.checkout-payment-option');
        if (option) {
          option.classList.toggle('checkout-payment-option--active', item.checked);
        }
      });
    });
  });

  syncPanelVisibility();
}

export function initCheckoutSubmit() {
  const form = document.getElementById('order-form');
  const payload = document.getElementById('cart-payload');
  if (!form || !payload) return;
  const csrfInput = form.querySelector('input[name="csrf_token"]');

  function syncCsrfTokenFromResponse(response) {
    if (!response || !response.headers) return;
    const token = response.headers.get('X-CSRF-Token');
    if (typeof token === 'string' && token.trim() !== '') {
      if (csrfInput) {
        csrfInput.value = token.trim();
      }
      window.APP_CSRF_TOKEN = token.trim();
    }
  }

  const submitBtn = form.querySelector('button[type="submit"]');
  let isSubmitting = false;

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (isSubmitting) return;

    const cart = getCart();
    if (!Array.isArray(cart) || cart.length === 0) {
      alert('Корзина пуста.');
      return;
    }

    payload.value = JSON.stringify(cart);
    const formData = new FormData(form);
    formData.set('cart_payload', payload.value);

    isSubmitting = true;
    if (submitBtn) submitBtn.disabled = true;

    try {
      const response = await fetch(form.getAttribute('action') || window.location.pathname, {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
        body: formData,
      });
      syncCsrfTokenFromResponse(response);

      let data = null;
      try {
        data = await response.json();
      } catch (e) {
        throw new Error('Некорректный ответ сервера.');
      }

      if (!response.ok || !data || !data.success) {
        throw new Error((data && data.message) || 'Не удалось оформить заказ.');
      }

      if (data.payment_method === 'online' && data.confirmation_url) {
        window.location.href = data.confirmation_url;
        return;
      }

      if (data.payment_method === 'on_delivery') {
        clearCart();
      }

      if (data.redirect_url) {
        window.location.href = data.redirect_url;
        return;
      }

      window.location.reload();
    } catch (error) {
      alert((error && error.message) || 'Не удалось оформить заказ.');
    } finally {
      isSubmitting = false;
      if (submitBtn) submitBtn.disabled = false;
    }
  });
}

export function handlePaymentReturn() {
  const state = document.getElementById('payment-return-state');
  if (!state || state.dataset.isReturn !== '1') return;

  if (state.dataset.status === 'paid') {
    clearCart();
  } else if (state.dataset.status === 'pending') {
    const cart = getCart();
    localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
  }
}
