export const CART_STORAGE_KEY = 'penpoint_cart';
export const DEFAULT_WORKING_HOURS = 'ПН-ПТ: 09:00-21:00, СБ-ВС: 10:00-22:00';
export const GEOCACHE_KEY = 'penpoint_geocache';
export const GEOCACHE_TTL = 7 * 24 * 60 * 60 * 1000;
export const TARGET_CITY = 'Волгоград';
export const YANDEX_MAPS_SRC = (typeof window !== 'undefined' && window.APP_YANDEX_MAPS_SRC)
  ? String(window.APP_YANDEX_MAPS_SRC)
  : '';
export const PAYMENT_LOGO_SRC = window.appResolvePath ? window.appResolvePath('/assets/pay/logo-%D0%AE.webp') : '/assets/pay/logo-%D0%AE.webp';

const DEFAULT_PICKUP_POINTS = [
  {
    id: 'store',
    name: 'Канцария',
    address: 'улица Новороссийская, 67',
    isMainStore: true,
    status: 'temporarily_closed',
    workingHours: DEFAULT_WORKING_HOURS,
    note: 'Магазин откроется в ближайшее время',
  },
  {
    id: 'cdek_lenina',
    name: 'CDEK',
    address: 'проспект Ленина, 50',
    workingHours: 'ПН-ПТ: 09:00-20:00, СБ: 10:00-18:00, ВС: выходной',
  },
  {
    id: 'post_mira',
    name: 'Почта России',
    address: 'улица Мира, 10',
    workingHours: 'ПН-ПТ: 08:00-20:00, СБ: 09:00-17:00, ВС: выходной',
  },
  {
    id: 'cdek_mira',
    name: 'CDEK',
    address: 'улица Мира, 25',
    workingHours: 'ПН-ПТ: 09:00-20:00, СБ: 10:00-18:00, ВС: выходной',
  },
];

function normalizePickupPoints(points) {
  if (!Array.isArray(points) || points.length === 0) {
    return DEFAULT_PICKUP_POINTS;
  }

  const normalized = points
    .map((point) => {
      if (!point || typeof point !== 'object') return null;
      const id = String(point.id || '').trim();
      const name = String(point.name || '').trim();
      const address = String(point.address || '').trim();
      if (!id || !name || !address) return null;
      return {
        id: id,
        name: name,
        address: address,
        isMainStore: Boolean(point.isMainStore),
        status: String(point.status || '').trim(),
        workingHours: String(point.workingHours || DEFAULT_WORKING_HOURS),
        note: String(point.note || '').trim(),
      };
    })
    .filter((point) => point !== null);

  return normalized.length > 0 ? normalized : DEFAULT_PICKUP_POINTS;
}

export const PICKUP_POINTS = normalizePickupPoints(
  typeof window !== 'undefined' ? window.APP_PICKUP_POINTS : [],
);
