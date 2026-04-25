<?php
require_once __DIR__ . '/../includes/security.php';
app_start_session();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/reviews.php';
require_once __DIR__ . '/../includes/auth_security.php';

app_ensure_order_schema($pdo);
reviews_ensure_schema($pdo);
auth_ensure_security_schema($pdo);

if (!isset($_SESSION['user_id'])) {
    header('Location: /pages/login.php');
    exit;
}

if ((string)($_SESSION['role_name'] ?? '') === 'admin' || isset($_SESSION['admin_user_id'])) {
    header('Location: /admin/index.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$current_tab = (string)($_GET['tab'] ?? 'profile');
$error = '';
$feedback_error = '';
$feedback_success = '';
$review_error = '';
$review_success = '';
$review_form_order_id = max(0, (int)($_GET['review_order'] ?? 0));
$review_form_product_id = max(0, (int)($_GET['review_product'] ?? 0));
$confirm_email_token = trim((string)($_GET['confirm_email'] ?? ''));

if (!function_exists('account_set_flash')) {
    function account_set_flash(string $type, string $message): void
    {
        $_SESSION['account_flash'] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('account_pull_flash')) {
    function account_pull_flash(): ?array
    {
        if (!isset($_SESSION['account_flash']) || !is_array($_SESSION['account_flash'])) {
            return null;
        }

        $flash = $_SESSION['account_flash'];
        unset($_SESSION['account_flash']);
        return $flash;
    }
}

$profile_flash = account_pull_flash();

try {
    $stmt = $pdo->prepare(
        'SELECT u.id, u.name, u.email, u.phone, u.created_at, r.name AS role_name
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE u.id = ? LIMIT 1'
    );
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        header('Location: /pages/logout.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Account user fetch error: ' . $e->getMessage());
    $user = ['name' => '', 'email' => '', 'phone' => '', 'created_at' => date('Y-m-d H:i:s')];
}

if ($confirm_email_token !== '') {
    try {
        $tokenData = auth_consume_email_change_token($pdo, $confirm_email_token);
        if (!$tokenData) {
            account_set_flash('error', 'Ссылка подтверждения email недействительна или истекла.');
            header('Location: /pages/account.php?tab=profile');
            exit;
        }

        if ((int)$tokenData['user_id'] !== $user_id) {
            account_set_flash('error', 'Эта ссылка подтверждения относится к другому аккаунту.');
            header('Location: /pages/account.php?tab=profile');
            exit;
        }

        $newEmail = trim((string)$tokenData['new_email']);
        if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            account_set_flash('error', 'Новый email некорректен.');
            header('Location: /pages/account.php?tab=profile');
            exit;
        }

        $stmtExists = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmtExists->execute([$newEmail, $user_id]);
        if ($stmtExists->fetch()) {
            account_set_flash('error', 'Этот email уже используется другим пользователем.');
            header('Location: /pages/account.php?tab=profile');
            exit;
        }

        $stmtUpdate = $pdo->prepare('UPDATE users SET email = ?, is_verified = 1, email_verified_at = NOW() WHERE id = ? LIMIT 1');
        $stmtUpdate->execute([$newEmail, $user_id]);
        $_SESSION['user_email'] = $newEmail;
        account_set_flash('success', 'Email успешно подтверждён и обновлён.');
        header('Location: /pages/account.php?tab=profile');
        exit;
    } catch (Throwable $e) {
        error_log('Account confirm email error: ' . $e->getMessage());
        account_set_flash('error', 'Не удалось подтвердить новый email.');
        header('Location: /pages/account.php?tab=profile');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    app_validate_csrf_or_fail();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));

    $phone_digits = preg_replace('/\D+/u', '', $phone);
    $is_phone_valid = $phone === '' || ($phone_digits !== null && strlen($phone_digits) >= 10 && strlen($phone_digits) <= 15);

    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Проверьте имя и email.';
    } elseif (!$is_phone_valid) {
        $error = 'Проверьте формат телефона.';
    } else {
        try {
            $currentEmail = (string)($user['email'] ?? '');
            $newEmail = mb_strtolower($email, 'UTF-8');
            $isEmailChanged = mb_strtolower($currentEmail, 'UTF-8') !== $newEmail;

            if ($isEmailChanged) {
                $stmtEmailExists = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
                $stmtEmailExists->execute([$email, $user_id]);
                if ($stmtEmailExists->fetch()) {
                    $error = 'Этот email уже используется.';
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ? LIMIT 1');
                    $stmt->execute([$name, $phone !== '' ? $phone : null, $user_id]);

                    $token = auth_create_email_change_token($pdo, $user_id, $email, 86400);
                    $link = auth_base_url() . '/pages/account.php?tab=profile&confirm_email=' . urlencode($token);
                    $mailSent = auth_send_link_email($email, 'Подтверждение нового email', 'Подтвердите новый email', $link, 24);

                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_phone'] = $phone;

                    if ($mailSent) {
                        account_set_flash('success', 'Профиль обновлён. На новый email отправлена ссылка подтверждения.');
                    } else {
                        account_set_flash('error', 'Профиль обновлён, но письмо подтверждения не отправилось. Попробуйте снова.');
                    }
                    header('Location: /pages/account.php?tab=profile');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ? LIMIT 1');
                $stmt->execute([$name, $email, $phone !== '' ? $phone : null, $user_id]);
                $_SESSION['user_email'] = $email;
                account_set_flash('success', 'Данные обновлены.');
                $_SESSION['user_name'] = $name;
                $_SESSION['user_phone'] = $phone;
                header('Location: /pages/account.php?tab=profile');
                exit;
            }

        } catch (PDOException $e) {
            error_log('Account update error: ' . $e->getMessage());
            $error = 'Не удалось обновить профиль.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_feedback'])) {
    app_validate_csrf_or_fail();
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    if ($subject === '' || $message === '') {
        $feedback_error = 'Заполните тему и сообщение.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO feedback (name, email, subject, message) VALUES (?, ?, ?, ?)');
            $stmt->execute([(string)$user['name'], (string)$user['email'], $subject, $message]);
            $feedback_success = 'Сообщение отправлено.';
        } catch (PDOException $e) {
            error_log('Feedback insert error: ' . $e->getMessage());
            $feedback_error = 'Не удалось отправить сообщение.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    app_validate_csrf_or_fail();
    $current_tab = 'orders';
    $review_form_order_id = max(0, (int)($_POST['order_id'] ?? 0));
    $review_form_product_id = max(0, (int)($_POST['product_id'] ?? 0));
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 0)));
    $comment = trim((string)($_POST['comment'] ?? ''));

    if ($review_form_order_id <= 0 || $review_form_product_id <= 0) {
        $review_error = 'Некорректные данные для отзыва.';
    } elseif (mb_strlen($comment, 'UTF-8') < 5) {
        $review_error = 'Комментарий должен содержать не менее 5 символов.';
    } elseif (!reviews_can_user_submit($pdo, $review_form_product_id, $user_id)) {
        $review_error = 'Оставить отзыв можно только после получения заказа и только один раз.';
    } else {
        try {
            reviews_create($pdo, $review_form_product_id, $user_id, $rating, $comment);
            account_set_flash('success', 'Отзыв сохранён.');
            header('Location: /pages/account.php?tab=orders');
            exit;
        } catch (Throwable $e) {
            error_log('Account review create error: ' . $e->getMessage());
            $review_error = 'Не удалось сохранить отзыв.';
        }
    }
}

$orders = [];
$order_items = [];
try {
    $stmt = $pdo->prepare(
        'SELECT o.id, o.status_id, o.total_price, o.delivery_price, o.discount_total, o.address, o.created_at,
                o.payment_method, o.payment_status, os.name AS status_name, dm.name AS delivery_name
         FROM orders o
         LEFT JOIN order_statuses os ON os.id = o.status_id
         LEFT JOIN delivery_methods dm ON dm.id = o.delivery_method_id
         WHERE o.user_id = ?
         ORDER BY o.created_at DESC'
    );
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll() ?: [];

    if ($orders !== []) {
        $order_ids = array_map(static function (array $order): int {
            return (int)$order['id'];
        }, $orders);
        $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT oi.order_id, oi.product_id, oi.variant_id, oi.quantity, oi.price, oi.base_price,
                    oi.discount_percent, oi.title, oi.variant_label, oi.attributes_json, p.name AS product_name
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id IN ($placeholders)
             ORDER BY oi.order_id DESC, oi.id ASC"
        );
        $stmt->execute($order_ids);
        foreach ($stmt->fetchAll() ?: [] as $item) {
            $order_id = (int)$item['order_id'];
            if (!isset($order_items[$order_id])) {
                $order_items[$order_id] = [];
            }
            $order_items[$order_id][] = $item;
        }
    }
} catch (PDOException $e) {
    error_log('Orders fetch error: ' . $e->getMessage());
}

$my_reviews = [];
$reviewed_product_ids = [];
try {
    $stmt = $pdo->prepare(
        'SELECT r.id, r.product_id, r.rating, r.comment, r.created_at, p.name AS product_name
         FROM reviews r
         LEFT JOIN products p ON p.id = r.product_id
         WHERE r.user_id = ?
         ORDER BY r.created_at DESC, r.id DESC'
    );
    $stmt->execute([$user_id]);
    $my_reviews = $stmt->fetchAll() ?: [];
    foreach ($my_reviews as $row) {
        $reviewed_product_ids[(int)$row['product_id']] = true;
    }
} catch (PDOException $e) {
    error_log('Account reviews fetch error: ' . $e->getMessage());
}

$review_allowed = [];
foreach ($orders as $order) {
    $order_id = (int)$order['id'];
    if ((int)($order['status_id'] ?? 0) !== 4 || empty($order_items[$order_id])) {
        continue;
    }

    foreach ($order_items[$order_id] as $item) {
        $product_id = (int)($item['product_id'] ?? 0);
        if ($product_id <= 0) {
            continue;
        }
        $review_allowed[$order_id . ':' . $product_id] = !isset($reviewed_product_ids[$product_id]);
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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/styles/global.css">
    <link rel="stylesheet" href="/styles/header.css">
    <link rel="stylesheet" href="/styles/footer.css">
    <link rel="stylesheet" href="/styles/product-card.css">
    <link rel="stylesheet" href="/styles/account.css">
    <title>Личный кабинет — Канцария</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="main" data-account-tab="<?php echo htmlspecialchars($current_tab, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="account-page">
            <nav class="account-nav">
                <h2 class="account-nav__title">Личный кабинет</h2>
                <ul class="account-nav__list">
                    <li class="account-nav__item"><a href="/pages/account.php?tab=profile" class="account-nav__link <?php echo $current_tab === 'profile' ? 'account-nav__link--active' : ''; ?>">Профиль</a></li>
                    <li class="account-nav__item"><a href="/pages/account.php?tab=orders" class="account-nav__link <?php echo $current_tab === 'orders' ? 'account-nav__link--active' : ''; ?>">Заказы</a></li>
                    <li class="account-nav__item"><a href="/pages/account.php?tab=my-reviews" class="account-nav__link <?php echo $current_tab === 'my-reviews' ? 'account-nav__link--active' : ''; ?>">Мои отзывы</a></li>
                    <li class="account-nav__item"><a href="/pages/account.php?tab=favorites" class="account-nav__link <?php echo $current_tab === 'favorites' ? 'account-nav__link--active' : ''; ?>">Избранное <span class="account-nav__badge" id="favorites-count">0</span></a></li>
                    <li class="account-nav__item"><a href="/pages/account.php?tab=feedback" class="account-nav__link <?php echo $current_tab === 'feedback' ? 'account-nav__link--active' : ''; ?>">Обратная связь</a></li>
                </ul>
            </nav>

            <div class="account-content">
                <div class="account-section <?php echo $current_tab === 'profile' ? 'account-section--active' : ''; ?>">
                    <h2 class="account-content__title">Профиль</h2>
                    <?php if (is_array($profile_flash) && ($profile_flash['type'] ?? '') === 'success'): ?>
                        <div class="message message--success"><?php echo htmlspecialchars((string)($profile_flash['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    <?php if (is_array($profile_flash) && ($profile_flash['type'] ?? '') === 'error'): ?>
                        <div class="message message--error"><?php echo htmlspecialchars((string)($profile_flash['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    <?php if ($error !== ''): ?>
                        <div class="message message--error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <form method="post" class="profile-form">
                        <?php echo app_csrf_input(); ?>
                        <div class="profile-form__field">
                            <label class="profile-form__label">Имя</label>
                            <input type="text" name="name" class="profile-form__input" value="<?php echo htmlspecialchars((string)$user['name']); ?>" required>
                        </div>
                        <div class="profile-form__field">
                            <label class="profile-form__label">Email</label>
                            <input type="email" name="email" class="profile-form__input" value="<?php echo htmlspecialchars((string)$user['email']); ?>" required>
                        </div>
                        <div class="profile-form__field">
                            <label class="profile-form__label">Телефон</label>
                            <input type="tel" name="phone" class="profile-form__input" value="<?php echo htmlspecialchars((string)($user['phone'] ?? '')); ?>" pattern="^\+?[0-9\-\(\)\s]{10,20}$" title="Введите телефон в формате +7 (999) 123-45-67">
                        </div>
                        <div class="profile-form__field">
                            <label class="profile-form__label">Дата регистрации</label>
                            <input type="text" class="profile-form__input" value="<?php echo htmlspecialchars(date('d.m.Y', strtotime((string)$user['created_at']))); ?>" disabled>
                        </div>
                        <button type="submit" name="update_profile" class="profile-form__submit">Сохранить изменения</button>
                    </form>
                    <form method="post" id="logout-form" action="/pages/logout.php" style="margin-top:24px;">
                        <?php echo app_csrf_input(); ?>
                        <button type="submit" style="color:#c33;text-decoration:none;font-weight:600;background:none;border:none;padding:0;cursor:pointer;">Выйти из аккаунта</button>
                    </form>
                </div>

                <div class="account-section <?php echo $current_tab === 'orders' ? 'account-section--active' : ''; ?>">
                    <h2 class="account-content__title">История заказов</h2>
                    <?php if (is_array($profile_flash) && ($profile_flash['type'] ?? '') === 'success'): ?>
                        <div class="message message--success"><?php echo htmlspecialchars((string)($profile_flash['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    <?php if ($orders === []): ?>
                        <div class="empty-state"><p>У вас пока нет заказов.</p></div>
                    <?php else: ?>
                        <div class="orders-list">
                            <?php foreach ($orders as $order): ?>
                                <?php $order_id = (int)$order['id']; ?>
                                <article class="orders-item">
                                    <div class="orders-item__header">
                                        <span class="orders-item__id">Заказ №<?php echo $order_id; ?></span>
                                        <span class="orders-item__status orders-item__status--<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', (string)$order['status_name']))); ?>">
                                            <?php echo htmlspecialchars((string)$order['status_name']); ?>
                                        </span>
                                    </div>
                                    <div class="orders-item__date"><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime((string)$order['created_at']))); ?></div>
                                    <div class="orders-item__total">Итого: <?php echo format_price((float)$order['total_price']); ?></div>
                                    <div class="orders-item__date">Доставка: <?php echo htmlspecialchars((string)($order['delivery_name'] ?? 'Не указана')); ?>, <?php echo format_price((float)($order['delivery_price'] ?? 0)); ?></div>
                                    <div class="orders-item__date">Оплата: <?php echo htmlspecialchars((string)$order['payment_method']); ?> / <?php echo htmlspecialchars((string)$order['payment_status']); ?></div>
                                    <?php if (!empty($order['address'])): ?>
                                        <div class="orders-item__date">Адрес: <?php echo htmlspecialchars((string)$order['address']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($order_items[$order_id])): ?>
                                        <div style="margin-top:16px;">
                                            <?php foreach ($order_items[$order_id] as $item): ?>
                                                <?php
                                                $attributes = json_decode((string)($item['attributes_json'] ?? ''), true);
                                                $product_id = (int)($item['product_id'] ?? 0);
                                                $can_review = !empty($review_allowed[$order_id . ':' . $product_id]);
                                                $is_review_form_open = ($review_form_order_id === $order_id && $review_form_product_id === $product_id);
                                                ?>
                                                <div style="padding:10px 0;border-top:1px solid #eee;">
                                                    <div><?php echo htmlspecialchars((string)($item['title'] ?: $item['product_name'] ?: 'Товар')); ?></div>
                                                    <?php if (!empty($item['variant_label'])): ?>
                                                        <div style="color:#666;font-size:14px;"><?php echo htmlspecialchars((string)$item['variant_label']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (is_array($attributes) && $attributes !== []): ?>
                                                        <div style="color:#666;font-size:14px;">
                                                            <?php
                                                            $parts = [];
                                                            foreach ($attributes as $key => $value) {
                                                                $parts[] = htmlspecialchars((string)$key) . ': ' . htmlspecialchars((string)$value);
                                                            }
                                                            echo implode(' · ', $parts);
                                                            ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div style="color:#666;font-size:14px;">Количество: <?php echo (int)$item['quantity']; ?> · Цена: <?php echo format_price((float)$item['price']); ?></div>
                                                    <?php if ($can_review): ?>
                                                        <div style="margin-top:10px;">
                                                            <a class="profile-form__submit" style="display:inline-block;text-decoration:none;" href="/pages/account.php?tab=orders&review_order=<?php echo $order_id; ?>&review_product=<?php echo $product_id; ?>">Отзыв</a>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($is_review_form_open): ?>
                                                        <div style="margin-top:12px;padding:12px;border:1px solid #e5e5e5;border-radius:8px;background:#fafafa;">
                                                            <?php if ($review_error !== ''): ?>
                                                                <div class="message message--error"><?php echo htmlspecialchars($review_error, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <?php endif; ?>
                                                            <?php if ($review_success !== ''): ?>
                                                                <div class="message message--success"><?php echo htmlspecialchars($review_success, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <?php endif; ?>
                                                            <form method="post">
                                                                <?php echo app_csrf_input(); ?>
                                                                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                                                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                                                <div class="feedback-form__field">
                                                                    <label class="feedback-form__label">Оценка</label>
                                                                    <select name="rating" class="feedback-form__input" required>
                                                                        <option value="5">5</option>
                                                                        <option value="4">4</option>
                                                                        <option value="3">3</option>
                                                                        <option value="2">2</option>
                                                                        <option value="1">1</option>
                                                                    </select>
                                                                </div>
                                                                <div class="feedback-form__field">
                                                                    <label class="feedback-form__label">Отзыв</label>
                                                                    <textarea name="comment" class="feedback-form__textarea" required minlength="5"></textarea>
                                                                </div>
                                                                <button type="submit" name="submit_review" class="profile-form__submit">Сохранить отзыв</button>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="account-section <?php echo $current_tab === 'my-reviews' ? 'account-section--active' : ''; ?>">
                    <h2 class="account-content__title">Мои отзывы</h2>
                    <?php if ($my_reviews === []): ?>
                        <div class="empty-state"><p>Вы пока не оставляли отзывы.</p></div>
                    <?php else: ?>
                        <div class="orders-list">
                            <?php foreach ($my_reviews as $review): ?>
                                <article class="orders-item">
                                    <div class="orders-item__header">
                                        <a href="/pages/page-product.php?id=<?php echo (int)$review['product_id']; ?>" style="text-decoration:none;color:inherit;font-weight:700;">
                                            <?php echo htmlspecialchars((string)($review['product_name'] ?: ('Товар #' . (int)$review['product_id']))); ?>
                                        </a>
                                        <span class="orders-item__status">Оценка: <?php echo (int)$review['rating']; ?>/5</span>
                                    </div>
                                    <div class="orders-item__date"><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime((string)$review['created_at']))); ?></div>
                                    <div style="margin-top:8px;color:#333;"><?php echo nl2br(htmlspecialchars((string)$review['comment'], ENT_QUOTES, 'UTF-8')); ?></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="account-section <?php echo $current_tab === 'favorites' ? 'account-section--active' : ''; ?>">
                    <h2 class="account-content__title">Избранное</h2>
                    <button type="button" class="account-content__action" id="favorites-clear">Очистить избранное</button>
                    <div class="favorites-grid" id="favorites-grid">
                        <div class="empty-state"><p>Список избранного пуст.</p></div>
                    </div>
                </div>

                <div class="account-section <?php echo $current_tab === 'feedback' ? 'account-section--active' : ''; ?>">
                    <h2 class="account-content__title">Обратная связь</h2>
                    <?php if ($feedback_error !== ''): ?>
                        <div class="message message--error"><?php echo htmlspecialchars($feedback_error); ?></div>
                    <?php endif; ?>
                    <?php if ($feedback_success !== ''): ?>
                        <div class="message message--success"><?php echo htmlspecialchars($feedback_success); ?></div>
                    <?php endif; ?>
                    <form method="post" class="feedback-form">
                        <?php echo app_csrf_input(); ?>
                        <div class="feedback-form__field">
                            <label class="feedback-form__label">Тема</label>
                            <input type="text" name="subject" class="feedback-form__input" required>
                        </div>
                        <div class="feedback-form__field">
                            <label class="feedback-form__label">Сообщение</label>
                            <textarea name="message" class="feedback-form__textarea" required></textarea>
                        </div>
                        <button type="submit" name="send_feedback" class="profile-form__submit">Отправить</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="/scripts/account-page.js"></script>
</body>
</html>
