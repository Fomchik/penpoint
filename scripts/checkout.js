(function () {
  "use strict";

  const CART_STORAGE_KEY = "penpoint_cart";
  const DEFAULT_WORKING_HOURS = "ПН-ПТ: 09:00-21:00, СБ-ВС: 10:00-22:00";
  const GEOCACHE_KEY = "penpoint_geocache";
  const GEOCACHE_TTL = 7 * 24 * 60 * 60 * 1000;
  const TARGET_CITY = "Волгоград";

  const PICKUP_POINTS = [
    {
      id: "store",
      name: "Канцария",
      address: "улица Новороссийская, 67",
      isMainStore: true,
      status: "temporarily_closed",
      workingHours: DEFAULT_WORKING_HOURS,
      note: "Магазин откроется в ближайшее время",
    },
    {
      id: "cdek_lenina",
      name: "CDEK",
      address: "проспект Ленина, 50",
      workingHours: "ПН-ПТ: 09:00-20:00, СБ: 10:00-18:00, ВС: выходной",
    },
    {
      id: "post_mira",
      name: "Почта России",
      address: "улица Мира, 10",
      workingHours: "ПН-ПТ: 08:00-20:00, СБ: 09:00-17:00, ВС: выходной",
    },
    {
      id: "cdek_mira",
      name: "CDEK",
      address: "улица Мира, 25",
      workingHours: "ПН-ПТ: 09:00-20:00, СБ: 10:00-18:00, ВС: выходной",
    },
  ];

  let ymapsReady = false;
  let map = null;
  let deliveryPlacemark = null;
  const pickupPlacemarks = {};
  let selectedPickupPoint = null;
  let userCity = TARGET_CITY;
  let suggestElement = null;
  let suggestDebouncer = null;

  function getGeocache() {
    try {
      const raw = localStorage.getItem(GEOCACHE_KEY);
      if (!raw) return {};
      const data = JSON.parse(raw);
      const now = Date.now();
      const filtered = {};
      for (const [key, value] of Object.entries(data)) {
        if (now - value.timestamp < GEOCACHE_TTL) {
          filtered[key] = value;
        }
      }
      return filtered;
    } catch (e) {
      return {};
    }
  }

  function setGeocache(address, coords) {
    try {
      const cache = getGeocache();
      cache[address] = { coords, timestamp: Date.now() };
      localStorage.setItem(GEOCACHE_KEY, JSON.stringify(cache));
    } catch (e) {
      // Игнорируем ошибки
    }
  }

  function getCart() {
    try {
      const raw = localStorage.getItem(CART_STORAGE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch (e) {
      return [];
    }
  }

  function formatPrice(num) {
    return (
      new Intl.NumberFormat("ru-RU", { maximumFractionDigits: 0 }).format(num) +
      " ₽"
    );
  }

  function pluralizeProduct(num) {
    const n10 = num % 10,
      n100 = num % 100;
    if (n10 === 1 && n100 !== 11) return "товар";
    if (n10 >= 2 && n10 <= 4 && (n100 < 12 || n100 > 14)) return "товара";
    return "товаров";
  }

  function bindTabs(groupId, name) {
    const root = document.getElementById(groupId);
    if (!root) return;
    const radios = root.querySelectorAll('input[name="' + name + '"]');
    radios.forEach((radio) => {
      radio.addEventListener("change", function () {
        radios.forEach((item) => {
          const tab = item.closest(".checkout-tab");
          if (tab) tab.classList.toggle("checkout-tab--active", item.checked);
        });
      });
    });
  }

  function setupPaymentPanel() {
    const panel = document.getElementById("online-payment-panel");
    const methodRadios = document.querySelectorAll(
      'input[name="payment_method"]',
    );
    const providerRadios = document.querySelectorAll(
      'input[name="payment_provider"]',
    );
    if (!panel || !methodRadios.length) return;

    function syncPanelVisibility() {
      const selected = document.querySelector(
        'input[name="payment_method"]:checked',
      );
      panel.classList.toggle(
        "checkout-payment-panel--open",
        selected?.value === "online",
      );
    }
    methodRadios.forEach((r) =>
      r.addEventListener("change", syncPanelVisibility),
    );
    providerRadios.forEach((radio) => {
      radio.addEventListener("change", function () {
        providerRadios.forEach((item) => {
          const option = item.closest(".checkout-payment-option");
          if (option)
            option.classList.toggle(
              "checkout-payment-option--active",
              item.checked,
            );
        });
      });
    });
    syncPanelVisibility();
  }

  function loadSummary() {
    const cart = getCart();
    let totalQty = 0,
      totalPrice = 0,
      totalOld = 0;
    cart.forEach((item) => {
      const qty = Math.max(1, parseInt(item.quantity, 10) || 1);
      const price = parseFloat(item.unit_price || item.price) || 0;
      const old = parseFloat(item.base_price || item.old_price || 0);
      totalQty += qty;
      totalPrice += price * qty;
      totalOld += (old > price ? old : price) * qty;
    });
    const deliverySelected = document.querySelector(
      'input[name="delivery_type"]:checked',
    );
    const deliveryPrice = deliverySelected && deliverySelected.value === "delivery" ? 300 : 0;
    const itemsEl = document.getElementById("checkout-total-items");
    const priceEl = document.getElementById("checkout-total-price");
    const oldEl = document.getElementById("checkout-total-old");
    const deliveryEl = document.getElementById("checkout-delivery-price");
    if (itemsEl)
      itemsEl.textContent = totalQty + " " + pluralizeProduct(totalQty);
    if (deliveryEl) deliveryEl.textContent = formatPrice(deliveryPrice);
    if (priceEl) priceEl.textContent = formatPrice(totalPrice + deliveryPrice);
    if (oldEl)
      oldEl.textContent = totalOld > totalPrice ? formatPrice(totalOld) : "";
  }

  function setupSubmit() {
    const form = document.getElementById("order-form");
    const payload = document.getElementById("cart-payload");
    if (!form || !payload) return;
    form.addEventListener("submit", function (e) {
      const cart = getCart();
      if (!cart.length) {
        e.preventDefault();
        alert("Корзина пуста.");
        return;
      }
      payload.value = JSON.stringify(cart);
    });
  }

  async function geocodeAddress(address) {
    if (!ymapsReady || !address?.trim()) return null;

    const query = address.toLowerCase().includes("волгоград")
      ? address
      : address + ", " + TARGET_CITY;

    const cache = getGeocache();
    const cached = cache[query];
    if (cached) {
      return { coords: cached.coords, address: cached.address };
    }

    try {
      const result = await ymaps.geocode(query, {
        results: 1,
        kind: "house",
        boundedBy: [
          [48.5, 44.3],
          [48.9, 44.7],
        ],
        strictBounds: false,
      });
      const geoObject = result.geoObjects.get(0);
      if (geoObject) {
        const coords = geoObject.geometry.getCoordinates();
        const preciseAddress = geoObject.getAddressLine();
        setGeocache(query, coords);
        return { coords, address: preciseAddress };
      }
    } catch (e) {
      console.warn('Geocoding error for "' + address + '":', e);
    }
    return null;
  }

  async function detectUserCity() {
    if (!ymapsReady) return null;
    try {
      const location = await ymaps.geolocation.get({
        provider: "auto",
        mapStateAutoApply: false,
      });
      const city = location.address?.components?.get("locality");
      return city || TARGET_CITY;
    } catch (e) {
      console.warn("Geolocation error:", e);
      return TARGET_CITY;
    }
  }

  async function initYandexMap() {
    if (!window.ymaps || !document.getElementById("pickup-map")) {
      setTimeout(initYandexMap, 100);
      return;
    }

    ymapsReady = true;

    const detectedCity = await detectUserCity();
    userCity = detectedCity || TARGET_CITY;

    ymaps.ready(async function () {
      const cityCoords = await geocodeAddress(userCity);
      const center = cityCoords?.coords || [48.708, 44.513];

      map = new ymaps.Map("pickup-map", {
        center: center,
        zoom: 11,
        controls: ["zoomControl", "geolocationControl", "typeSelector"],
      });

      await loadPickupPoints();
    });
  }

  async function loadPickupPoints() {
    if (!map) return;

    for (const point of PICKUP_POINTS) {
      let coords = point.isMainStore ? [48.707103, 44.516939] : null;

      if (!coords && !point.isMainStore) {
        const result = await geocodeAddress(point.address);
        if (result) {
          coords = result.coords;
          point.resolvedAddress = result.address;
        }
      }

      if (!coords) {
        console.warn("Could not geocode point:", point.name, point.address);
        continue;
      }

      const balloonContent = createBalloonContent(point);

      const placemark = new ymaps.Placemark(
        coords,
        {
          hintContent:
            point.name +
            (point.status === "temporarily_closed"
              ? " (временно не работает)"
              : ""),
          balloonContentHeader: point.name,
          balloonContentBody: balloonContent,
          balloonContentFooter: point.isMainStore
            ? '<span style="color:#e74c3c;font-weight:600">⚠ Временно не работает</span>'
            : `<button type="button" class="pickup-map-select-btn" data-pickup-id="${point.id}">Выбрать</button>`,
        },
        {
          preset:
            point.status === "temporarily_closed"
              ? "islands#grayCircleDotIcon"
              : "islands#redCircleDotIcon",
          draggable: false,
        },
      );

      placemark.events.add("balloonopen", function () {
        map.panTo(coords, { flying: true });

        if (!point.isMainStore) {
          const btn = document.querySelector(
            '.pickup-map-select-btn[data-pickup-id="' + point.id + '"]',
          );
          if (btn) {
            btn.onclick = function () {
              selectPickupPoint(point);
              closeModal();
            };
          }
        }
      });

      placemark.events.add("click", function () {
        map.panTo(coords, { flying: true });
        placemark.balloon.open();
      });

      map.geoObjects.add(placemark);
      pickupPlacemarks[point.id] = { placemark, point, coords };
    }

    if (selectedPickupPoint && pickupPlacemarks[selectedPickupPoint.id]) {
      const coords = pickupPlacemarks[selectedPickupPoint.id].coords;
      map.setCenter(coords, 14);
    }
  }

  function createBalloonContent(point) {
    const address = point.resolvedAddress || point.address;
    const statusBadge =
      point.status === "temporarily_closed"
        ? '<span style="background:#fee2e2;color:#dc2626;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:500">Не работает</span>'
        : '<span style="background:#dcfce7;color:#16a34a;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:500">Работает</span>';

    return `
            <div style="font-family:Montserrat,sans-serif;font-size:14px;line-height:1.4">
                <p style="margin:0 0 8px 0"><strong>📍 ${address}</strong></p>
                <p style="margin:0 0 8px 0">${statusBadge}</p>
                <p style="margin:0 0 8px 0"><strong>🕒 График:</strong><br>${point.workingHours}</p>
                ${point.note ? `<p style="margin:0;color:#6b7280;font-size:13px">💡 ${point.note}</p>` : ""}
            </div>
        `;
  }

  function selectPickupPoint(point) {
    if (point.status === "temporarily_closed") {
      alert(
        "Этот пункт временно не работает. Пожалуйста, выберите другой пункт самовывоза или доставку курьером.",
      );
      return;
    }

    selectedPickupPoint = point;
    const displayAddress = point.resolvedAddress || point.address;

    document.getElementById("pickup-point-input").value = displayAddress;
    document.getElementById("pickup-selected-text").textContent =
      displayAddress;
    updatePickupMeta(point);

    Object.values(pickupPlacemarks).forEach((item) => {
      item.placemark.options.set(
        "preset",
        item.point.status === "temporarily_closed"
          ? "islands#grayCircleDotIcon"
          : "islands#redCircleDotIcon",
      );
    });
    if (pickupPlacemarks[point.id]) {
      pickupPlacemarks[point.id].placemark.options.set(
        "preset",
        "islands#greenCircleDotIcon",
      );
      map.panTo(pickupPlacemarks[point.id].coords, { flying: true });
    }
  }

  function updatePickupMeta(point) {
    const metaEl = document.getElementById("pickup-selected-meta");
    if (metaEl) {
      metaEl.innerHTML = `<img src="/assets/icons/clock.svg" alt="" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"> ${point.workingHours}`;
    }
  }

  async function setDeliveryAddress(address) {
    if (!map || !address?.trim()) return null;

    if (deliveryPlacemark) {
      map.geoObjects.remove(deliveryPlacemark);
      deliveryPlacemark = null;
    }

    const result = await geocodeAddress(address);
    if (result) {
      deliveryPlacemark = new ymaps.Placemark(
        result.coords,
        {
          hintContent: "Адрес доставки",
          balloonContentBody: `<p style="font-family:Montserrat,sans-serif"><strong>🚚 Доставка:</strong><br>${result.address}</p>`,
        },
        {
          preset: "islands#blueCircleDotIcon",
          draggable: false,
        },
      );
      map.geoObjects.add(deliveryPlacemark);
      map.setCenter(result.coords, 14);
      return result.address;
    }
    return null;
  }

  function setupDeliveryModal() {
    const modal = document.getElementById("delivery-modal");
    const closeBtn = document.getElementById("delivery-modal-close");
    const titleEl = document.getElementById("delivery-modal-title");
    const pickupContent = document.getElementById("modal-pickup-content");
    const deliveryContent = document.getElementById("modal-delivery-content");
    const pickupList = document.getElementById("pickup-list");
    const pickupSearch = document.getElementById("pickup-search");
    const pickupApplyBtn = document.getElementById("pickup-apply-btn");
    const deliveryAddressField = document.getElementById(
      "delivery-address-field",
    );
    const deliveryFlatField = document.getElementById("delivery-flat-field");
    const deliveryFloorField = document.getElementById("delivery-floor-field");
    const deliveryApplyBtn = document.getElementById("delivery-apply-btn");
    const pickupSelectedText = document.getElementById("pickup-selected-text");
    const pickupSelectedMeta = document.getElementById("pickup-selected-meta");
    const deliverySelectedText = document.getElementById(
      "delivery-selected-text",
    );
    const pickupPointInput = document.getElementById("pickup-point-input");
    const deliveryAddressInput = document.getElementById(
      "delivery-address-input",
    );
    const pickupChangeBtn = document.getElementById("pickup-change-btn");
    const deliveryChangeBtn = document.getElementById("delivery-change-btn");
    const pickupBlock = document.getElementById("pickup-block");
    const deliveryBlock = document.getElementById("delivery-block");
    const deliveryRadios = document.querySelectorAll(
      'input[name="delivery_type"]',
    );

    function renderPickupList(filter = "") {
      const search = filter.trim().toLowerCase();
      const filtered = PICKUP_POINTS.filter((p) =>
        (p.name + " " + p.address).toLowerCase().includes(search),
      );

      if (filtered.length === 0) {
        pickupList.innerHTML =
          '<p style="padding:12px;color:#6b7280">Пункты не найдены</p>';
        return;
      }

      pickupList.innerHTML = filtered
        .map((point) => {
          const checked = selectedPickupPoint?.id === point.id;
          const isDisabled = point.status === "temporarily_closed";
          const statusText = isDisabled ? " — временно не работает" : "";

          return `<label class="pickup-list__item${isDisabled ? " pickup-list__item--disabled" : ""}">
                    <input type="radio" name="pickup_modal_point" value="${point.id}" 
                        ${checked ? "checked" : ""} ${isDisabled ? "disabled" : ""}>
                    <span>
                        <strong>${point.name}${statusText}</strong><br>
                        <small style="color:#6b7280">${point.address}</small>
                    </span>
                </label>`;
        })
        .join("");

      const radioButtons = pickupList.querySelectorAll(
        'input[name="pickup_modal_point"]',
      );
      radioButtons.forEach((radio) => {
        radio.addEventListener("change", function () {
          if (this.checked && !this.disabled) {
            const point = PICKUP_POINTS.find((p) => p.id === this.value);
            if (point && pickupPlacemarks[point.id]) {
              const coords = pickupPlacemarks[point.id].coords;
              map.panTo(coords, { flying: true, delay: 0 });
              pickupPlacemarks[point.id].placemark.balloon.open();
            }
          }
        });
      });
    }

    function openModal(mode) {
      if (mode === "pickup") {
        titleEl.textContent = "Выбор пункта самовывоза";
        pickupContent.style.display = "";
        deliveryContent.style.display = "none";
        renderPickupList(pickupSearch?.value || "");

        const point =
          selectedPickupPoint ||
          PICKUP_POINTS.find((p) => p.status !== "temporarily_closed") ||
          PICKUP_POINTS[0];
        if (map && pickupPlacemarks[point.id]) {
          const coords = pickupPlacemarks[point.id].coords;
          map.setCenter(coords, 14);
          if (!point.isMainStore) {
            pickupPlacemarks[point.id].placemark.balloon.open();
          }
        }
      } else {
        titleEl.textContent = "Новый адрес доставки";
        pickupContent.style.display = "none";
        deliveryContent.style.display = "";
        const addr =
          deliveryAddressField?.value ||
          deliverySelectedText?.textContent ||
          userCity;
        setDeliveryAddress(addr);
      }
      modal.classList.add("delivery-modal--open");
      modal.setAttribute("aria-hidden", "false");
      if (map) setTimeout(() => map.container.fitToViewport(), 0);
    }

    function closeModal() {
      modal.classList.remove("delivery-modal--open");
      modal.setAttribute("aria-hidden", "true");
      if (suggestElement) {
        suggestElement.destroy();
        suggestElement = null;
      }
    }

    if (pickupSearch) {
      pickupSearch.addEventListener("input", (e) =>
        renderPickupList(e.target.value),
      );
    }

    if (pickupApplyBtn) {
      pickupApplyBtn.addEventListener("click", () => {
        const selected = document.querySelector(
          'input[name="pickup_modal_point"]:checked',
        );
        if (!selected) {
          alert("Выберите пункт самовывоза");
          return;
        }
        const point = PICKUP_POINTS.find((p) => p.id === selected.value);
        if (point) {
          if (point.status === "temporarily_closed") {
            alert("Этот пункт временно не работает. Выберите другой.");
            return;
          }
          selectPickupPoint(point);
          closeModal();
        }
      });
    }

    if (deliveryApplyBtn) {
      deliveryApplyBtn.addEventListener("click", async () => {
        const address = (deliveryAddressField.value || "").trim();
        if (!address) {
          alert("Укажите адрес доставки.");
          return;
        }
        const flat = (deliveryFlatField.value || "").trim();
        const floor = (deliveryFloorField.value || "").trim();
        const parts = [address];
        if (flat) parts.push("кв. " + flat);
        if (floor) parts.push("этаж " + floor);
        const fullAddress = parts.join(", ");

        const resolved = await setDeliveryAddress(fullAddress);
        if (resolved) {
          deliveryAddressInput.value = resolved;
          deliverySelectedText.textContent = resolved;
          closeModal();
        } else {
          alert(
            "Не удалось определить адрес. Проверьте правильность ввода или уточните адрес.",
          );
        }
      });
    }

    if (pickupChangeBtn)
      pickupChangeBtn.addEventListener("click", () => openModal("pickup"));
    if (deliveryChangeBtn)
      deliveryChangeBtn.addEventListener("click", () => openModal("delivery"));
    if (closeBtn) closeBtn.addEventListener("click", closeModal);
    modal.addEventListener("click", (e) => {
      if (e.target === modal) closeModal();
    });

    deliveryRadios.forEach((radio) => {
      radio.addEventListener("change", function () {
        const isDelivery = this.value === "delivery" && this.checked;
        if (pickupBlock) pickupBlock.style.display = isDelivery ? "none" : "";
        if (deliveryBlock)
          deliveryBlock.style.display = isDelivery ? "" : "none";
        openModal(isDelivery ? "delivery" : "pickup");
      });
    });

    if (!pickupPointInput.value) {
      const availablePoint = PICKUP_POINTS.find(
        (p) => p.status !== "temporarily_closed",
      );
      if (availablePoint) {
        selectPickupPoint(availablePoint);
      }
    }
  }

    // Функции автодополнения адреса
  
  function initDeliverySuggest() {
    const deliveryInput = document.getElementById("delivery-address-field");
    const suggestContainer = document.getElementById("delivery-suggest");
    
    if (!deliveryInput || !suggestContainer) return;

    let suggestTimer = null;

    deliveryInput.addEventListener("input", function(e) {
      const value = e.target.value.trim();
      
      if (suggestTimer) clearTimeout(suggestTimer);

      if (value.length < 2) {
        suggestContainer.style.display = "none";
        return;
      }

      suggestTimer = setTimeout(() => {
        fetchAddressSuggestions(value, suggestContainer, deliveryInput);
      }, 300);
    });

    deliveryInput.addEventListener("focus", function() {
      const value = this.value.trim();
      if (value.length >= 2) {
        fetchAddressSuggestions(value, suggestContainer, deliveryInput);
      }
    });

    deliveryInput.addEventListener("blur", function() {
      setTimeout(() => { suggestContainer.style.display = "none"; }, 200);
    });

    document.addEventListener("click", function(e) {
      if (!deliveryInput.contains(e.target) && !suggestContainer.contains(e.target)) {
        suggestContainer.style.display = "none";
      }
    });
  }

  async function fetchAddressSuggestions(query, container, input) {
    if (!ymapsReady || !window.ymaps) return;

    try {
      const result = await ymaps.geocode(query + ", Волгоград", {
        results: 5,
        kind: "house",
        boundedBy: [[48.5, 44.3], [48.9, 44.7]]
      });

      const suggestions = [];
      for (let i = 0; i < result.geoObjects.getLength(); i++) {
        const geoObject = result.geoObjects.get(i);
        suggestions.push({
          address: geoObject.getAddressLine(),
          fullName: geoObject.properties.get("name"),
          description: geoObject.properties.get("description")
        });
      }

      renderAddressSuggestions(suggestions, container, input);
    } catch (e) {
      console.warn("Suggest error:", e);
    }
  }

  function renderAddressSuggestions(suggestions, container, input) {
    if (suggestions.length === 0) {
      container.style.display = "none";
      return;
    }

    container.innerHTML = suggestions.map(item => `
      <div class="delivery-suggest-item" data-address="${escapeHtml(item.address)}">
        <strong>${escapeHtml(item.fullName || item.address)}</strong>
        ${item.description ? `<small>${escapeHtml(item.description)}</small>` : ""}
      </div>
    `).join("");

    const items = container.querySelectorAll(".delivery-suggest-item");
    items.forEach(item => {
      item.addEventListener("click", function() {
        const address = this.getAttribute("data-address");
        input.value = address;
        container.style.display = "none";
      });
    });

    container.style.display = "block";
  }

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  document.addEventListener("DOMContentLoaded", function () {
    bindTabs("delivery-tabs", "delivery_type");
    bindTabs("payment-tabs", "payment_method");
    setupPaymentPanel();
    loadSummary();
    setupSubmit();
    setupDeliveryModal();
    initYandexMap();
    initDeliverySuggest();
    document.querySelectorAll('input[name="delivery_type"]').forEach(function (radio) {
      radio.addEventListener("change", loadSummary);
    });
    window.addEventListener("penpoint:cart-updated", loadSummary);
  });
})();

