(function () {
  "use strict";

  const CART_STORAGE_KEY = "penpoint_cart";

  function initCheckoutPaymentFlow() {
    const form = document.getElementById("order-form");
    const payload = document.getElementById("cart-payload");
    if (!form || !payload) return;

    const submitBtn = form.querySelector('button[type="submit"]');
    let isSubmitting = false;

    form.addEventListener("submit", async function (event) {
      event.preventDefault();

      if (isSubmitting) return;

      let cart = [];
      try {
        const raw = localStorage.getItem(CART_STORAGE_KEY);
        cart = raw ? JSON.parse(raw) : [];
      } catch (e) {
        cart = [];
      }

      if (!Array.isArray(cart) || cart.length === 0) {
        alert("Корзина пуста.");
        return;
      }

      payload.value = JSON.stringify(cart);
      const formData = new FormData(form);
      formData.set("cart_payload", payload.value);

      isSubmitting = true;
      if (submitBtn) submitBtn.disabled = true;

      try {
        const response = await fetch(form.getAttribute("action") || window.location.pathname, {
          method: "POST",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json",
          },
          body: formData,
        });

        let data;
        try {
          data = await response.json();
        } catch (e) {
          throw new Error("Некорректный ответ сервера.");
        }

        if (!response.ok || !data || !data.success) {
          throw new Error((data && data.message) || "Не удалось оформить заказ.");
        }

        if (data.payment_method === "online" && data.confirmation_url) {
          window.location.href = data.confirmation_url;
          return;
        }

        if (data.payment_method === "on_delivery") {
          localStorage.removeItem(CART_STORAGE_KEY);
        }

        if (data.redirect_url) {
          window.location.href = data.redirect_url;
          return;
        }

        window.location.reload();
      } catch (e) {
        alert((e && e.message) || "Не удалось оформить заказ.");
      } finally {
        isSubmitting = false;
        if (submitBtn) submitBtn.disabled = false;
      }
    });
  }

  function handlePaymentReturn() {
    const state = document.getElementById("payment-return-state");
    if (!state || state.dataset.isReturn !== "1") return;

    if (state.dataset.status === "paid") {
      localStorage.removeItem(CART_STORAGE_KEY);
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    handlePaymentReturn();
    initCheckoutPaymentFlow();
  });
})();
