import { PICKUP_POINTS } from './config.js';
import { initDeliverySuggest } from './suggest.js';

export function initDelivery({ onTotalsChange }) {
  const modal = document.getElementById('delivery-modal');
  const closeBtn = document.getElementById('delivery-modal-close');
  const titleEl = document.getElementById('delivery-modal-title');
  const pickupContent = document.getElementById('modal-pickup-content');
  const deliveryContent = document.getElementById('modal-delivery-content');
  const pickupList = document.getElementById('pickup-list');
  const pickupSearch = document.getElementById('pickup-search');
  const pickupApplyBtn = document.getElementById('pickup-apply-btn');
  const deliveryApplyBtn = document.getElementById('delivery-apply-btn');
  const deliveryAddressField = document.getElementById('delivery-address-field');
  const deliveryFlatField = document.getElementById('delivery-flat-field');
  const deliveryFloorField = document.getElementById('delivery-floor-field');
  const deliverySelectedText = document.getElementById('delivery-selected-text');
  const deliveryAddressInput = document.getElementById('delivery-address-input');
  const pickupPointInput = document.getElementById('pickup-point-input');
  const pickupSelectedText = document.getElementById('pickup-selected-text');
  const pickupSelectedMeta = document.getElementById('pickup-selected-meta');
  const pickupChangeBtn = document.getElementById('pickup-change-btn');
  const deliveryChangeBtn = document.getElementById('delivery-change-btn');
  const pickupBlock = document.getElementById('pickup-block');
  const deliveryBlock = document.getElementById('delivery-block');
  const deliveryRadios = document.querySelectorAll('input[name="delivery_type"]');
  const suggestContainer = document.getElementById('delivery-suggest');

  if (!modal || !pickupContent || !deliveryContent || !pickupList) return;

  let selectedPickupPoint = null;
  let mapControllerPromise = null;
  let activeMode = 'pickup';

  async function getMapController() {
    if (!mapControllerPromise) {
      mapControllerPromise = import('./map.js').then(async ({ createMapController }) => {
        const controller = await createMapController('pickup-map');
        await controller.ensurePickupPoints((point) => {
          selectPickupPoint(point);
          closeModal();
        });
        return controller;
      });
    }
    return mapControllerPromise;
  }

  function closeModal() {
    modal.classList.remove('delivery-modal--open');
    modal.setAttribute('aria-hidden', 'true');
  }

  function selectPickupPoint(point) {
    if (!point || point.status === 'temporarily_closed') {
      alert('Этот пункт временно не работает. Пожалуйста, выберите другой пункт самовывоза или доставку курьером.');
      return;
    }

    selectedPickupPoint = point;
    if (pickupPointInput) pickupPointInput.value = point.resolvedAddress || point.address;
    if (pickupSelectedText) pickupSelectedText.textContent = point.resolvedAddress || point.address;
    if (pickupSelectedMeta) pickupSelectedMeta.textContent = point.workingHours || '';

    getMapController().then((controller) => {
      controller.setSelectedPickupPoint(point);
    }).catch(function () {});
  }

  function renderPickupList(filter) {
    const search = String(filter || '').trim().toLowerCase();
    const filtered = PICKUP_POINTS.filter((point) => (point.name + ' ' + point.address).toLowerCase().includes(search));

    pickupList.innerHTML = filtered.length
      ? filtered.map((point) => {
          const checked = selectedPickupPoint && selectedPickupPoint.id === point.id;
          const disabled = point.status === 'temporarily_closed';
          return '<label class="pickup-list__item' + (disabled ? ' pickup-list__item--disabled' : '') + '">' +
            '<input type="radio" name="pickup_modal_point" value="' + point.id + '"' + (checked ? ' checked' : '') + (disabled ? ' disabled' : '') + '>' +
            '<span><strong>' + point.name + (disabled ? ' — временно не работает' : '') + '</strong><br><small style="color:#6b7280;">' + point.address + '</small></span>' +
            '</label>';
        }).join('')
      : '<p style="padding:12px;color:#6b7280;">Пункты не найдены</p>';

    pickupList.querySelectorAll('input[name="pickup_modal_point"]').forEach((radio) => {
      radio.addEventListener('change', function () {
        if (!this.checked) return;
        const point = PICKUP_POINTS.find((item) => item.id === this.value);
        if (!point) return;
        getMapController().then((controller) => controller.highlightPickupPoint(point.id)).catch(function () {});
      });
    });
  }

  async function openModal(mode) {
    activeMode = mode;
    if (mode === 'pickup') {
      titleEl.textContent = 'Выбор пункта самовывоза';
      pickupContent.style.display = '';
      deliveryContent.style.display = 'none';
      renderPickupList(pickupSearch ? pickupSearch.value : '');
    } else {
      titleEl.textContent = 'Новый адрес доставки';
      pickupContent.style.display = 'none';
      deliveryContent.style.display = '';
    }

    modal.classList.add('delivery-modal--open');
    modal.setAttribute('aria-hidden', 'false');
    const mapNode = document.getElementById('pickup-map');
    if (mapNode) {
      mapNode.classList.add('pickup-map--loading');
    }

    try {
      const controller = await getMapController();
      controller.fit();
      if (mode === 'pickup' && selectedPickupPoint) {
        controller.highlightPickupPoint(selectedPickupPoint.id);
      }
      if (mode === 'delivery' && deliveryAddressInput && deliveryAddressInput.value.trim()) {
        await controller.setDeliveryAddress(deliveryAddressInput.value.trim());
      }
    } catch (e) {
    } finally {
      if (mapNode) {
        mapNode.classList.remove('pickup-map--loading');
      }
    }
  }

  function syncBlocks() {
    const selected = document.querySelector('input[name="delivery_type"]:checked');
    const isDelivery = selected && selected.value === 'delivery';
    if (pickupBlock) pickupBlock.style.display = isDelivery ? 'none' : '';
    if (deliveryBlock) deliveryBlock.style.display = isDelivery ? '' : 'none';
    onTotalsChange(parseFloat((selected && selected.getAttribute('data-delivery-price')) || '0') || 0);
  }

  if (pickupSearch) {
    pickupSearch.addEventListener('input', function () {
      renderPickupList(this.value);
    });
  }

  if (pickupApplyBtn) {
    pickupApplyBtn.addEventListener('click', function () {
      const selected = document.querySelector('input[name="pickup_modal_point"]:checked');
      if (!selected) {
        alert('Выберите пункт самовывоза.');
        return;
      }
      const point = PICKUP_POINTS.find((item) => item.id === selected.value);
      if (!point) return;
      selectPickupPoint(point);
      closeModal();
    });
  }

  if (deliveryApplyBtn) {
    deliveryApplyBtn.addEventListener('click', async function () {
      const address = deliveryAddressField ? deliveryAddressField.value.trim() : '';
      if (!address) {
        alert('Укажите адрес доставки.');
        return;
      }

      const parts = [address];
      if (deliveryFlatField && deliveryFlatField.value.trim()) parts.push('кв. ' + deliveryFlatField.value.trim());
      if (deliveryFloorField && deliveryFloorField.value.trim()) parts.push('этаж ' + deliveryFloorField.value.trim());

      try {
        const controller = await getMapController();
        const resolved = await controller.setDeliveryAddress(parts.join(', '));
        if (!resolved) {
          alert('Не удалось определить адрес. Проверьте корректность ввода.');
          return;
        }
        if (deliveryAddressInput) deliveryAddressInput.value = resolved;
        if (deliverySelectedText) deliverySelectedText.textContent = resolved;
        closeModal();
      } catch (e) {
        alert('Не удалось загрузить карту доставки.');
      }
    });
  }

  if (pickupChangeBtn) pickupChangeBtn.addEventListener('click', () => openModal('pickup'));
  if (deliveryChangeBtn) deliveryChangeBtn.addEventListener('click', () => openModal('delivery'));
  if (closeBtn) closeBtn.addEventListener('click', closeModal);

  modal.addEventListener('click', function (event) {
    if (event.target === modal) closeModal();
  });

  deliveryRadios.forEach((radio) => {
    radio.addEventListener('change', function () {
      syncBlocks();
      openModal(this.value === 'delivery' ? 'delivery' : 'pickup');
    });
  });

  initDeliverySuggest({
    input: deliveryAddressField,
    container: suggestContainer,
    getMapController,
  });

  const defaultPickup = PICKUP_POINTS.find((point) => point.status !== 'temporarily_closed');
  if (defaultPickup) {
    selectPickupPoint(defaultPickup);
  }
  syncBlocks();

  return {
    getMode() {
      return activeMode;
    },
    openModal,
    syncBlocks,
  };
}
