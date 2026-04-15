<?php
require_once __DIR__ . '/../includes/security.php';
app_start_session();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/yookassa.php';

app_ensure_order_schema($pdo);

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? (int)$_SESSION['user_id'] : 0;
$order_error = '';
$order_success = '';
$payment_return = isset($_GET['payment_return']) ? '1' : '0';
$payment_status = (string)($_GET['payment_status'] ?? '');

function checkout_is_ajax_request(): bool
{
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    return $requestedWith === 'xmlhttprequest' || strpos($accept, 'application/json') !== false;
}

function checkout_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function create_guest_order_user(PDO $pdo, string $customer_name, string $customer_phone, string $customer_email): int
{
    $emailBase = filter_var($customer_email, FILTER_VALIDATE_EMAIL)
        ? strtolower($customer_email)
        : ('guest+' . time() . '.' . random_int(1000, 9999) . '@penpoint.local');
    $email = $emailBase;
    $suffix = 1;

    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if (!$stmt->fetchColumn()) {
            break;
        }
        $email = preg_replace('/@/', '+' . $suffix . '@', $emailBase, 1);
        $suffix++;
    }

    $password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (role_id, name, email, password_hash, phone) VALUES (2, ?, ?, ?, ?)');
    $stmt->execute([
        $customer_name !== '' ? $customer_name : 'Гость',
        $email,
        $password_hash,
        $customer_phone !== '' ? $customer_phone : null,
    ]);

    return (int)$pdo->lastInsertId();
}

function checkout_delivery_methods(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name, price FROM delivery_methods ORDER BY id ASC');
    $methods = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $methods[(int)$row['id']] = $row;
    }
    return $methods;
}

function checkout_base_url(): string
{
    $appUrl = trim(app_env('APP_URL', ''));
    if ($appUrl !== '') {
        return rtrim($appUrl, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_validate_csrf_or_fail();

    $customer_name = trim((string)($_POST['customer_name'] ?? ''));
    $customer_phone = trim((string)($_POST['customer_phone'] ?? ''));
    $customer_email = trim((string)($_POST['customer_email'] ?? ''));
    $delivery_type = (string)($_POST['delivery_type'] ?? 'pickup');
    $payment_method = (string)($_POST['payment_method'] ?? 'online');
    $pickup_point = trim((string)($_POST['pickup_point'] ?? ''));
    $delivery_address = trim((string)($_POST['delivery_address'] ?? ''));
    $is_ajax = checkout_is_ajax_request();

    $cart_state = cart_get_state();
    $available_items = array_values(array_filter((array)$cart_state['items'], static function (array $item): bool {
        return !empty($item['available']) && (int)($item['quantity'] ?? 0) > 0;
    }));

    try {
        $delivery_methods = checkout_delivery_methods($pdo);
        $pickup_method = $delivery_methods[1] ?? reset($delivery_methods);
        $delivery_method = $delivery_methods[2] ?? ($delivery_methods[1] ?? null);
    } catch (Throwable $e) {
        $pickup_method = null;
        $delivery_method = null;
    }

    if ($available_items === []) {
        $order_error = 'Корзина пуста. Добавьте товары перед оформлением заказа.';
    } elseif ($customer_name === '' || $customer_phone === '') {
        $order_error = 'Укажите имя и телефон для связи.';
    } elseif ($customer_email !== '' && !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        $order_error = 'Укажите корректный email.';
    } elseif (!in_array($delivery_type, ['pickup', 'delivery'], true)) {
        $order_error = 'Некорректный способ доставки.';
    } elseif (!in_array($payment_method, ['online', 'on_delivery'], true)) {
        $order_error = 'Некорректный способ оплаты.';
    } elseif ($delivery_type === 'pickup' && $pickup_point === '') {
        $order_error = 'Выберите пункт самовывоза.';
    } elseif ($delivery_type === 'delivery' && $delivery_address === '') {
        $order_error = 'Укажите адрес доставки.';
    } elseif ($delivery_type === 'pickup' && !$pickup_method) {
        $order_error = 'Метод самовывоза недоступен.';
    } elseif ($delivery_type === 'delivery' && !$delivery_method) {
        $order_error = 'Метод доставки недоступен.';
    } else {
        $selected_method = $delivery_type === 'delivery' ? $delivery_method : $pickup_method;
        $delivery_price = round((float)($selected_method['price'] ?? 0), 2);
        $subtotal = round((float)($cart_state['subtotal'] ?? 0), 2);
        $discount_total = round((float)($cart_state['discount_total'] ?? 0), 2);
        $total_price = round($subtotal + $delivery_price, 2);
        $address_text = $delivery_type === 'delivery' ? $delivery_address : $pickup_point;

        try {
            $pdo->beginTransaction();

            $order_user_id = $is_logged_in ? $user_id : create_guest_order_user($pdo, $customer_name, $customer_phone, $customer_email);
            $status_id = 1;
            $payment_status_value = $payment_method === 'online' ? 'pending_payment' : 'not_required';

            $stmt = $pdo->prepare(
                'INSERT INTO orders (
                    user_id, customer_name, customer_phone, customer_email, status_id, delivery_method_id,
                    payment_method, total_price, delivery_price, discount_total, address, payment_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $order_user_id,
                $customer_name,
                $customer_phone,
                $customer_email !== '' ? $customer_email : null,
                $status_id,
                (int)$selected_method['id'],
                $payment_method,
                $total_price,
                $delivery_price,
                $discount_total,
                $address_text,
                $payment_status_value,
            ]);

            $order_id = (int)$pdo->lastInsertId();
            $stmt_items = $pdo->prepare(
                'INSERT INTO order_items (
                    order_id, product_id, variant_id, quantity, price, base_price, discount_percent,
                    promotion_id, attributes_json, title, variant_label
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($available_items as $item) {
                $stmt_items->execute([
                    $order_id,
                    (int)$item['product_id'],
                    $item['variant_id'] !== null ? (int)$item['variant_id'] : null,
                    (int)$item['quantity'],
                    (float)$item['unit_price'],
                    (float)$item['base_price'],
                    (int)$item['discount_percent'],
                    $item['promotion_id'] !== null ? (int)$item['promotion_id'] : null,
                    json_encode((array)($item['attributes'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    (string)$item['title'],
                    (string)($item['variant_label'] ?? ''),
                ]);
            }

            $pdo->commit();

            $confirmation_url = '';
            if ($payment_method === 'online') {
                try {
                    $payment = yookassa_api_request('POST', '/payments', [
                        'amount' => [
                            'value' => number_format($total_price, 2, '.', ''),
                            'currency' => 'RUB',
                        ],
                        'capture' => true,
                        'confirmation' => [
                            'type' => 'redirect',
                            'return_url' => checkout_base_url() . '/pages/payment_return.php?order_id=' . $order_id,
                        ],
                        'description' => 'Заказ #' . $order_id,
                        'metadata' => [
                            'order_id' => $order_id,
                        ],
                    ], bin2hex(random_bytes(16)));

                    $confirmation_url = (string)($payment['confirmation']['confirmation_url'] ?? '');
                    $payment_id = (string)($payment['id'] ?? '');

                    if ($payment_id === '' || $confirmation_url === '') {
                        throw new RuntimeException('Платёжный шлюз вернул неполные данные.');
                    }

                    $stmtUpdate = $pdo->prepare('UPDATE orders SET payment_id = ?, payment_status = ? WHERE id = ? LIMIT 1');
                    $stmtUpdate->execute([$payment_id, 'pending_payment', $order_id]);
                } catch (Throwable $e) {
                    $stmtFail = $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ? LIMIT 1");
                    $stmtFail->execute([$order_id]);
                    throw $e;
                }
            } else {
                cart_clear();
            }

            if ($is_ajax) {
                checkout_json_response([
                    'success' => true,
                    'order_id' => $order_id,
                    'payment_method' => $payment_method,
                    'confirmation_url' => $confirmation_url,
                    'redirect_url' => $payment_method === 'on_delivery' ? '/pages/account.php?tab=orders' : '',
                ]);
            }

            $order_success = $payment_method === 'on_delivery'
                ? 'Заказ оформлен. Он появился в личном кабинете.'
                : 'Заказ создан. Перенаправляем на оплату.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Checkout error: ' . $e->getMessage());
            $order_error = 'Не удалось оформить заказ. Попробуйте позже.';
        }
    }

    if ($is_ajax) {
        checkout_json_response([
            'success' => false,
            'message' => $order_error !== '' ? $order_error : 'Не удалось оформить заказ.',
        ], 422);
    }
}
?>
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

                <div id="payment-return-state" data-is-return="<?php echo htmlspecialchars($payment_return, ENT_QUOTES, 'UTF-8'); ?>" data-status="<?php echo htmlspecialchars($payment_status, ENT_QUOTES, 'UTF-8'); ?>"></div>

                <form method="post" id="order-form" novalidate>
                    <?php echo app_csrf_input(); ?>
                    <section class="checkout-step">
                        <h2 class="checkout-step__title"><span class="checkout-step__idx">1</span>Данные покупателя</h2>
                        <div class="checkout-fields-grid">
                            <label class="checkout-field">
                                <span>Телефон</span>
                                <input type="tel" name="customer_phone" placeholder="+7 917 123-45-67" value="<?php echo htmlspecialchars((string)($_SESSION['user_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                            </label>
                            <label class="checkout-field">
                                <span>E-mail</span>
                                <input type="email" name="customer_email" value="<?php echo htmlspecialchars((string)($_SESSION['user_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            </label>
                        </div>
                        <label class="checkout-field checkout-field--full">
                            <span>Имя</span>
                            <input type="text" name="customer_name" value="<?php echo htmlspecialchars((string)($_SESSION['user_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
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
                                    <input type="radio" name="payment_provider" value="yookassa" checked>
                                    <span class="checkout-payment-option__logo"><img src="/assets/pay/logo-Ю.webp" alt="YooKassa" class="checkout-payment-option__logo-image"></span>
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
                    <div class="checkout-side__row">
                        <span>Доставка</span>
                        <span id="checkout-delivery-price">0 ₽</span>
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
    <script src="/scripts/checkout.js" defer></script>
    <script src="/scripts/checkout-payment.js" defer></script>
</body>
</html>
