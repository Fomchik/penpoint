export const CART_STORAGE_KEY = 'penpoint_cart';
export const DEFAULT_WORKING_HOURS = 'ПН-ПТ: 09:00-21:00, СБ-ВС: 10:00-22:00';
export const GEOCACHE_KEY = 'penpoint_geocache';
export const GEOCACHE_TTL = 7 * 24 * 60 * 60 * 1000;
export const TARGET_CITY = 'Волгоград';
export const YANDEX_MAPS_SRC = 'https://api-maps.yandex.ru/2.1/?apikey=7227d584-ec4b-465b-9bc1-f9efdfa096b5&lang=ru_RU';
export const PAYMENT_LOGO_SRC = window.appResolvePath ? window.appResolvePath('/assets/pay/logo-%D0%AE.webp') : '/assets/pay/logo-%D0%AE.webp';

export const PICKUP_POINTS = [
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
