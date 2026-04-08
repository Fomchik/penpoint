import { GEOCACHE_KEY, GEOCACHE_TTL, PICKUP_POINTS, TARGET_CITY, YANDEX_MAPS_SRC } from './config.js';
import { escapeHtml } from './utils.js';

let ymapsReady = false;
let mapsScriptPromise = null;

function getGeocache() {
  try {
    const raw = localStorage.getItem(GEOCACHE_KEY);
    if (!raw) return {};
    const data = JSON.parse(raw);
    const now = Date.now();
    const filtered = {};
    Object.keys(data || {}).forEach((key) => {
      const entry = data[key];
      if (entry && now - entry.timestamp < GEOCACHE_TTL) {
        filtered[key] = entry;
      }
    });
    return filtered;
  } catch (e) {
    return {};
  }
}

function setGeocache(address, coords, preciseAddress) {
  try {
    const cache = getGeocache();
    cache[address] = { coords, address: preciseAddress || address, timestamp: Date.now() };
    localStorage.setItem(GEOCACHE_KEY, JSON.stringify(cache));
  } catch (e) {}
}

function loadYandexMapsScript() {
  if (window.ymaps) return Promise.resolve(window.ymaps);
  if (mapsScriptPromise) return mapsScriptPromise;

  mapsScriptPromise = new Promise((resolve, reject) => {
    const existing = document.querySelector('script[data-penpoint-yandex-maps="1"]');
    if (existing) {
      existing.addEventListener('load', () => resolve(window.ymaps), { once: true });
      existing.addEventListener('error', reject, { once: true });
      return;
    }

    const script = document.createElement('script');
    script.src = YANDEX_MAPS_SRC;
    script.async = true;
    script.defer = true;
    script.dataset.penpointYandexMaps = '1';
    script.onload = () => resolve(window.ymaps);
    script.onerror = reject;
    document.head.appendChild(script);
  });

  return mapsScriptPromise;
}

async function geocodeAddress(address) {
  if (!ymapsReady || !address || !address.trim()) return null;
  const query = address.toLowerCase().includes(TARGET_CITY.toLowerCase()) ? address : address + ', ' + TARGET_CITY;
  const cached = getGeocache()[query];
  if (cached) {
    return { coords: cached.coords, address: cached.address || query };
  }

  try {
    const result = await ymaps.geocode(query, { results: 1, kind: 'house', strictBounds: false });
    const geoObject = result.geoObjects.get(0);
    if (!geoObject) return null;
    const coords = geoObject.geometry.getCoordinates();
    const preciseAddress = geoObject.getAddressLine();
    setGeocache(query, coords, preciseAddress);
    return { coords, address: preciseAddress };
  } catch (e) {
    return null;
  }
}

async function detectUserCity() {
  if (!ymapsReady) return TARGET_CITY;
  try {
    const location = await ymaps.geolocation.get({ provider: 'auto', mapStateAutoApply: false });
    return location.address?.components?.get('locality') || TARGET_CITY;
  } catch (e) {
    return TARGET_CITY;
  }
}

export async function createMapController(containerId) {
  let map = null;
  let deliveryPlacemark = null;
  let selectedPickupPoint = null;
  const pickupPlacemarks = {};

  await loadYandexMapsScript();
  await new Promise((resolve) => {
    ymaps.ready(resolve);
  });
  ymapsReady = true;

  const userCity = await detectUserCity();
  const cityCoords = await geocodeAddress(userCity);
  const center = cityCoords?.coords || [48.708389, 44.515102];

  map = new ymaps.Map(containerId, {
    center,
    zoom: 11,
    controls: ['zoomControl', 'geolocationControl', 'typeSelector'],
  });

  function createBalloonContent(point) {
    const statusLabel = point.status === 'temporarily_closed' ? 'Не работает' : 'Работает';
    const statusClass = point.status === 'temporarily_closed' ? '#dc2626' : '#16a34a';
    return (
      '<div style="font-family:Montserrat,sans-serif;font-size:14px;line-height:1.4;">' +
      '<p style="margin:0 0 8px 0"><strong>' + escapeHtml(point.resolvedAddress || point.address) + '</strong></p>' +
      '<p style="margin:0 0 8px 0;color:' + statusClass + ';font-weight:600;">' + statusLabel + '</p>' +
      '<p style="margin:0;"><strong>График:</strong><br>' + escapeHtml(point.workingHours || '') + '</p>' +
      (point.note ? '<p style="margin:8px 0 0;color:#6b7280;">' + escapeHtml(point.note) + '</p>' : '') +
      '</div>'
    );
  }

  async function ensurePickupPoints(onSelect) {
    for (const point of PICKUP_POINTS) {
      if (pickupPlacemarks[point.id]) continue;

      let coords = point.isMainStore ? [48.707103, 44.516939] : null;
      if (!coords) {
        const result = await geocodeAddress(point.address);
        if (result) {
          coords = result.coords;
          point.resolvedAddress = result.address;
        }
      }
      if (!coords) continue;

      const placemark = new ymaps.Placemark(coords, {
        hintContent: point.name,
        balloonContentBody: createBalloonContent(point),
      }, {
        preset: point.status === 'temporarily_closed' ? 'islands#grayCircleDotIcon' : 'islands#redCircleDotIcon',
      });

      placemark.events.add('click', function () {
        map.panTo(coords, { flying: true });
      });

      placemark.events.add('balloonopen', function () {
        const button = document.querySelector('.pickup-map-select-btn[data-pickup-id="' + point.id + '"]');
        if (button) {
          button.onclick = function () {
            if (point.status === 'temporarily_closed') return;
            selectedPickupPoint = point;
            onSelect(point);
          };
        }
      });

      placemark.properties.set('balloonContentFooter', point.isMainStore
        ? '<span style="color:#6b7280;font-weight:600;">Временно не работает</span>'
        : '<button type="button" class="pickup-map-select-btn" data-pickup-id="' + point.id + '">Выбрать</button>');

      map.geoObjects.add(placemark);
      pickupPlacemarks[point.id] = { point, placemark, coords };
    }
  }

  function highlightPickupPoint(pointId) {
    Object.keys(pickupPlacemarks).forEach((key) => {
      const item = pickupPlacemarks[key];
      item.placemark.options.set('preset', item.point.status === 'temporarily_closed' ? 'islands#grayCircleDotIcon' : 'islands#redCircleDotIcon');
    });

    if (pickupPlacemarks[pointId]) {
      pickupPlacemarks[pointId].placemark.options.set('preset', 'islands#greenCircleDotIcon');
      map.panTo(pickupPlacemarks[pointId].coords, { flying: true });
    }
  }

  async function setDeliveryAddress(address) {
    if (!address || !address.trim()) return null;
    if (deliveryPlacemark) {
      map.geoObjects.remove(deliveryPlacemark);
      deliveryPlacemark = null;
    }
    const result = await geocodeAddress(address);
    if (!result) return null;

    deliveryPlacemark = new ymaps.Placemark(result.coords, {
      hintContent: 'Адрес доставки',
      balloonContentBody: '<strong>Доставка:</strong><br>' + escapeHtml(result.address),
    }, {
      preset: 'islands#blueCircleDotIcon',
    });

    map.geoObjects.add(deliveryPlacemark);
    map.setCenter(result.coords, 14);
    return result.address;
  }

  function fit() {
    setTimeout(() => map.container.fitToViewport(), 0);
  }

  async function fetchSuggestions(query) {
    if (!query || query.trim().length < 2) return [];
    const result = await ymaps.geocode(query + ', ' + TARGET_CITY, { results: 5, kind: 'house', strictBounds: false });
    const suggestions = [];
    for (let i = 0; i < result.geoObjects.getLength(); i += 1) {
      const geoObject = result.geoObjects.get(i);
      suggestions.push({
        address: geoObject.getAddressLine(),
        fullName: geoObject.properties.get('name') || geoObject.getAddressLine(),
        description: geoObject.properties.get('description') || '',
      });
    }
    return suggestions;
  }

  return {
    ensurePickupPoints,
    fetchSuggestions,
    fit,
    highlightPickupPoint,
    setDeliveryAddress,
    getMap() {
      return map;
    },
    getSelectedPickupPoint() {
      return selectedPickupPoint;
    },
    setSelectedPickupPoint(point) {
      selectedPickupPoint = point;
      if (point) {
        highlightPickupPoint(point.id);
      }
    },
  };
}
