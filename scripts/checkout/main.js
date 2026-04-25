import { getCart, summarizeCart } from './cart.js';
import { initDelivery } from './delivery.js';
import { initPaymentPanel, initCheckoutSubmit, handlePaymentReturn } from './payment.js';
import { formatPrice, pluralizeProduct } from './utils.js';

function bindTabs(groupId, name) {
  const root = document.getElementById(groupId);
  if (!root) return;

  const radios = root.querySelectorAll('input[name="' + name + '"]');
  radios.forEach((radio) => {
    radio.addEventListener('change', () => {
      radios.forEach((item) => {
        const tab = item.closest('.checkout-tab');
        if (tab) {
          tab.classList.toggle('checkout-tab--active', item.checked);
        }
      });
    });
  });
}

function selectedDeliveryPrice() {
  const selected = document.querySelector('input[name="delivery_type"]:checked');
  if (!selected) return 0;
  return parseFloat(selected.getAttribute('data-delivery-price') || '0') || 0;
}

function renderSummary(deliveryPrice) {
  const summary = summarizeCart(getCart());
  const itemsEl = document.getElementById('checkout-total-items');
  const priceEl = document.getElementById('checkout-total-price');
  const oldEl = document.getElementById('checkout-total-old');
  const deliveryEl = document.getElementById('checkout-delivery-price');

  const normalizedDelivery = Number.isFinite(deliveryPrice) ? deliveryPrice : selectedDeliveryPrice();
  const totalPrice = summary.subtotal + normalizedDelivery;

  if (itemsEl) {
    itemsEl.textContent = summary.quantity + ' ' + pluralizeProduct(summary.quantity);
  }
  if (deliveryEl) {
    deliveryEl.textContent = formatPrice(normalizedDelivery);
  }
  if (priceEl) {
    priceEl.textContent = formatPrice(totalPrice);
  }
  if (oldEl) {
    oldEl.textContent = summary.baseSubtotal > summary.subtotal ? formatPrice(summary.baseSubtotal) : '';
  }
}

function initCheckoutPage() {
  handlePaymentReturn();

  bindTabs('delivery-tabs', 'delivery_type');
  bindTabs('payment-tabs', 'payment_method');

  initPaymentPanel();
  initCheckoutSubmit();

  initDelivery({
    onTotalsChange(price) {
      renderSummary(price);
    },
  });

  document.querySelectorAll('input[name="delivery_type"]').forEach((radio) => {
    radio.addEventListener('change', () => renderSummary(selectedDeliveryPrice()));
  });

  window.addEventListener('penpoint:cart-updated', () => {
    renderSummary(selectedDeliveryPrice());
  });

  renderSummary(selectedDeliveryPrice());
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initCheckoutPage);
} else {
  initCheckoutPage();
}
