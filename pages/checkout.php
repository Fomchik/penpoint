<?php session_start();
require_once __DIR__ . '/../includes/config.php';
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? (int)$_SESSION['user_id'] : 0;
$order_error = '';
$order_success = '';

function create_guest_order_user(PDO $pdo, string $customer_name, string $customer_phone, string $customer_email): int
{
    $email_base = filter_var($customer_email, FILTER_VALIDATE_EMAIL) ? strtolower($customer_email) : ('guest+' . time() . '.' . random_int(1000, 9999) . '@cantsaria.local');
    $email = $email_base;
    $suffix = 1;
    while (true) {
        $stmt_check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt_check->execute([$email]);
        if (!$stmt_check->fetchColumn()) {
            break;
        }
        $email = preg_replace('/@/', '+' . $suffix . '@', $email_base, 1);
        $suffix++;
    }
    $guest_name = $customer_name !== '' ? $customer_name : 'Гость';
    $password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt_insert = $pdo->prepare('INSERT INTO users (role_id, name, email, password_hash, phone) VALUES (2, ?, ?, ?, ?)');
    $stmt_insert->execute([$guest_name, $email, $password_hash, $customer_phone !== '' ? $customer_phone : null]);
    return (int)$pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_phone = trim($_POST['customer_phone'] ?? '');
    $customer_email = trim($_POST['customer_email'] ?? '');
    $delivery_type = $_POST['delivery_type'] ?? 'pickup';
    $payment_method = $_POST['payment_method'] ?? 'online';
    $pickup_point = trim($_POST['pickup_point'] ?? '');
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $cart_payload = $_POST['cart_payload'] ?? '';
    $cart_items = json_decode($cart_payload, true);

    if (!is_array($cart_items) || empty($cart_items)) {
        $order_error = 'Корзина пуста. Добавьте товары перед оформлением заказа.';
    } elseif ($customer_name === '' || $customer_phone === '') {
        $order_error = 'Укажите имя и телефон для связи.';
    } elseif ($delivery_type === 'pickup' && $pickup_point === '') {
        $order_error = 'Выберите пункт самовывоза.';
    } elseif ($delivery_type === 'delivery' && $delivery_address === '') {
        $order_error = 'Укажите адрес доставки.';
    } else {
        try {
            $pdo->beginTransaction();
            $ids = [];
            foreach ($cart_items as $item) {
                $id = (int)($item['id'] ?? 0);
                $qty = (int)($item['quantity'] ?? 0);
                if ($id > 0 && $qty > 0) {
                    $ids[$id] = $qty;
                }
            }
            if (empty($ids)) {
                throw new RuntimeException('Корзина пуста.');
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare(" SELECT id, base_price, final_price, discount_percent, promotion_id FROM v_product_pricing WHERE id IN ($placeholders) AND is_active = 1 ");
            $stmt->execute(array_keys($ids));
            $products = $stmt->fetchAll() ?: [];
            if (empty($products)) {
                throw new RuntimeException('Товары из корзины недоступны для заказа.');
            }
            $products_by_id = [];
            foreach ($products as $p) {
                $pid = (int)$p['id'];
                $products_by_id[$pid] = [
                    'base_price' => (float)$p['base_price'],
                    'price' => (float)$p['final_price'],
                    'discount_percent' => (int)$p['discount_percent'],
                    'promotion_id' => $p['promotion_id'] !== null ? (int)$p['promotion_id'] : null,
                ];
            }
            $total_price = 0.0;
            foreach ($ids as $pid => $qty) {
                if (!isset($products_by_id[$pid])) {
                    continue;
                }
                $total_price += $products_by_id[$pid]['price'] * $qty;
            }
            if ($total_price <= 0) {
                throw new RuntimeException('Невозможно оформить заказ с нулевой суммой.');
            }
            $delivery_method_id = ($delivery_type === 'delivery') ? 2 : 1;
            $status_id = 1;
            $address_text = $delivery_type === 'delivery' ? $delivery_address : $pickup_point;
            $order_user_id = $is_logged_in ? $user_id : create_guest_order_user($pdo, $customer_name, $customer_phone, $customer_email);
            $stmt = $pdo->prepare('INSERT INTO orders (user_id, status_id, delivery_method_id, total_price, address) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$order_user_id, $status_id, $delivery_method_id, $total_price, $address_text !== '' ? $address_text : null]);
            $order_id = (int)$pdo->lastInsertId();
            $stmt_items = $pdo->prepare(' INSERT INTO order_items (order_id, product_id, quantity, price, base_price, discount_percent, promotion_id) VALUES (?, ?, ?, ?, ?, ?, ?) ');
            foreach ($ids as $pid => $qty) {
                if (!isset($products_by_id[$pid])) {
                    continue;
                }
                $item = $products_by_id[$pid];
                $stmt_items->execute([$order_id, $pid, $qty, $item['price'], $item['base_price'], $item['discount_percent'], $item['promotion_id']]);
            }
            $pdo->commit();
            $order_success = $is_logged_in ? 'Заказ оформлен! Информация доступна в личном кабинете.' : 'Заказ оформлен! Для гостей история заказов и отзывы недоступны.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Order error: ' . $e->getMessage());
            $order_error = 'Не удалось оформить заказ. Попробуйте позже.';
        }
    }
} ?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/styles/global.css">
    <link rel="stylesheet" href="/styles/header.css">
    <link rel="stylesheet" href="/styles/footer.css">
    <link rel="stylesheet" href="/styles/checkout.css">
    <script src="https://api-maps.yandex.ru/2.1/?apikey=7227d584-ec4b-465b-9bc1-f9efdfa096b5&lang=ru_RU" defer></script>
    <title>Оформление заказа — Канцария</title>
</head>

<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main class="main checkout-page">
        <section class="checkout-grid">
            <section class="checkout-form-card" aria-label="Форма оформления заказа">
                <h1 class="checkout-title">Оформление заказа</h1>
                <?php if ($order_error): ?>
                    <div class="checkout-alert checkout-alert--error"><?php echo htmlspecialchars($order_error); ?></div>
                <?php endif; ?>
                <?php if ($order_success): ?>
                    <div class="checkout-alert checkout-alert--success"><?php echo htmlspecialchars($order_success); ?></div>
                <?php endif; ?>
                <form method="post" id="order-form" novalidate>
                    <section class="checkout-step">
                        <h2 class="checkout-step__title"><span class="checkout-step__idx">1</span>Данные покупателя</h2>
                        <div class="checkout-fields-grid">
                            <label class="checkout-field">
                                <span>Телефон</span>
                                <input type="tel" name="customer_phone" placeholder="+7 917 123-45-67" required>
                            </label>
                            <label class="checkout-field">
                                <span>E-mail (необязательно)</span>
                                <input type="email" name="customer_email" value="<?php echo htmlspecialchars($_SESSION['user_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                        </div>
                        <label class="checkout-field checkout-field--full">
                            <span>Имя</span>
                            <input type="text" name="customer_name" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                        </label>
                    </section>

                    <section class="checkout-step">
                        <h2 class="checkout-step__title"><span class="checkout-step__idx">2</span>Выберите способ получения</h2>
                        <div class="checkout-tabs" id="delivery-tabs">
                            <label class="checkout-tab checkout-tab--active">
                                <input type="radio" name="delivery_type" value="pickup" checked> Самовывоз
                            </label>
                            <label class="checkout-tab">
                                <input type="radio" name="delivery_type" value="delivery"> Курьером
                            </label>
                        </div>
                        <div id="pickup-block" class="pickup-card">
                            <span class="pickup-card__label">Пункт самовывоза</span>
                            <p id="pickup-selected-text" class="pickup-card__address">Пункт не выбран</p>
                            <p id="pickup-selected-meta" class="pickup-card__meta"></p>
                            <button type="button" id="pickup-change-btn" class="checkout-link-btn">Изменить</button>
                        </div>
                        <div id="delivery-block" class="delivery-card" style="display:none;">
                            <p class="delivery-card__label">Адрес доставки</p>
                            <p id="delivery-selected-text" class="delivery-card__address">Адрес не выбран</p>
                            <button type="button" id="delivery-change-btn" class="checkout-link-btn">Изменить адрес</button>
                        </div>
                    </section>

                    <section class="checkout-step">
                        <h2 class="checkout-step__title"><span class="checkout-step__idx">3</span>Выберите способ оплаты</h2>
                        <div class="checkout-tabs" id="payment-tabs">
                            <label class="checkout-tab checkout-tab--active">
                                <input type="radio" name="payment_method" value="online" checked> Оплата онлайн
                            </label>
                            <label class="checkout-tab">
                                <input type="radio" name="payment_method" value="on_delivery"> При получении
                            </label>
                        </div>
                        <div id="online-payment-panel" class="checkout-payment-panel checkout-payment-panel--open">
                            <div class="checkout-payment-panel__list">
                                <label class="checkout-payment-option checkout-payment-option--active">
                                    <input type="radio" name="payment_provider" value="yoomoney" checked>
                                    <img src="/assets/pay/logo-Ю.webp" alt="ЮKassa" class="checkout-payment-option__logo">
                                </label>
                            </div>
                        </div>
                    </section>

                    <input type="hidden" name="pickup_point" id="pickup-point-input" value="">
                    <input type="hidden" name="delivery_address" id="delivery-address-input" value="">
                    <input type="hidden" name="cart_payload" id="cart-payload" value="">

                    <p class="checkout-agree">
                        Подтверждая заказ, Вы даёте согласие на обработку персональных данных в соответствии с
                        <a href="/privacy.php" class="checkout-agree__link">политикой конфиденциальности</a>
                    </p>
                    <button type="submit" class="checkout-submit">Подтвердить заказ</button>
                </form>
            </section>

            <aside class="checkout-side" aria-label="Сводка заказа">
                <section class="checkout-side__card">
                    <h3 class="checkout-side__title">Ваш заказ</h3>
                    <div class="checkout-side__row">
                        <span>Товары</span>
                        <span id="checkout-total-items">0</span>
                    </div>
                    <div class="checkout-side__row checkout-side__row--total">
                        <span>Итого</span>
                        <span id="checkout-total-price">0 ₽</span>
                    </div>
                    <div id="checkout-total-old" class="checkout-side__old"></div>
                </section>
            </aside>
        </section>
    </main>

    <!-- Модальное окно выбора доставки -->
    <div id="delivery-modal" class="delivery-modal" aria-hidden="true">
        <div class="delivery-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="delivery-modal-title">
            <button type="button" class="delivery-modal__close" id="delivery-modal-close" aria-label="Закрыть"></button>
            <div class="delivery-modal__left">
                <h3 id="delivery-modal-title" class="delivery-modal__title">Выбор пункта</h3>
                <div id="modal-pickup-content">
                    <input id="pickup-search" class="delivery-modal__input" type="text" placeholder="Поиск пункта самовывоза">
                    <div id="pickup-list" class="pickup-list"></div>
                    <button type="button" id="pickup-apply-btn" class="delivery-modal__submit">Продолжить</button>
                </div>
                <div id="modal-delivery-content" style="display:none;">
                    <div style="position:relative;">
                        <input id="delivery-address-field" class="delivery-modal__input" type="text" placeholder="Улица, дом" autocomplete="off">
                        <div id="delivery-suggest" class="delivery-suggest" style="display:none;"></div>
                    </div>

                    <div class="delivery-modal__row">
                        <input id="delivery-flat-field" class="delivery-modal__input" type="text" placeholder="№ квартиры">
                        <input id="delivery-floor-field" class="delivery-modal__input" type="text" placeholder="Этаж">
                    </div>

                    <button type="button" id="delivery-apply-btn" class="delivery-modal__submit">Продолжить</button>
                </div>
            </div>
            <div class="delivery-modal__map">
                <div id="pickup-map" aria-label="Карта пунктов выдачи и доставки"></div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

<script src="https://api-maps.yandex.ru/2.1/?apikey=7227d584-ec4b-465b-9bc1-f9efdfa096b5&lang=ru_RU" defer></script>
<script src="/scripts/checkout.js" defer></script>
</body>

</html>