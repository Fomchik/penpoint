<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Проверка авторзац
if (!isset($_SESSION['user_id'])) {
    header('Location: /pages/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$current_tab = $_GET['tab'] ?? 'profile';

// Получае анные пользователя
try {
    $stmt = $pdo->prepare("SELECT id, name, email, phone, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    $user = null;
}

// Обработка обновленя профля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->execute([$name, $email, $phone, $user_id]);
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        header('Location: /pages/account.php?tab=profile&success=1');
        exit;
    } catch (PDOException $e) {
        $error = 'Ошбка пр обновлен анных';
        error_log('Update profile error: ' . $e->getMessage());
    }
}

// Обработка обратной связ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_feedback'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($subject) || empty($message)) {
        $feedback_error = 'Заполнте все поля';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO feedback (name, email, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['name'], $user['email'], $subject, $message]);
            $feedback_success = 'Сообщене отправлено! Мы свяжеся с ва в блжайшее врея.';
        } catch (PDOException $e) {
            $feedback_error = 'Ошбка пр отправке сообщеня';
            error_log('Feedback error: ' . $e->getMessage());
        }
    }
}

//  Заказы пользователя
try {
    $stmt = $pdo->prepare("
        SELECT o.id, o.total_price, o.created_at, os.name as status_name
        FROM orders o
        LEFT JOIN order_statuses os ON o.status_id = os.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll() ?: [];
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    $orders = [];
}

// Забранные товары (из localStorage через JS)
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

    <main class="main">
        <div class="account-page">
            <nav class="account-nav">
                <h2 class="account-nav__title">Настройк профля</h2>
                <ul class="account-nav__list">
                    <li class="account-nav__item">
                        <a href="/pages/account.php?tab=profile" class="account-nav__link <?php echo $current_tab === 'profile' ? 'account-nav__link--active' : ''; ?>">
                            <span>Профль</span>
                        </a>
                    </li>
                    <li class="account-nav__item">
                        <a href="/pages/account.php?tab=orders" class="account-nav__link <?php echo $current_tab === 'orders' ? 'account-nav__link--active' : ''; ?>">
                            <span>Заказы</span>
                        </a>
                    </li>
                    <li class="account-nav__item">
                        <a href="/pages/account.php?tab=favorites" class="account-nav__link <?php echo $current_tab === 'favorites' ? 'account-nav__link--active' : ''; ?>">
                            <span>Избранное</span>
                            <span class="account-nav__badge" id="favorites-count">0</span>
                        </a>
                    </li>
                    <li class="account-nav__item">
                        <a href="/pages/account.php?tab=feedback" class="account-nav__link <?php echo $current_tab === 'feedback' ? 'account-nav__link--active' : ''; ?>">
                            <span>Обратная связь</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="account-content">
                <!-- Профиль -->
                <div class="account-section <?php echo $current_tab === 'profile' ? 'account-section--active' : ''; ?>" id="profile-section">
                    <h2 class="account-content__title">Редактирование профиля</h2>
                    <?php if (isset($_GET['success'])): ?>
                        <div class="message message--success">Данные успешно обновлены!</div>
                    <?php endif; ?>
                    <?php if (isset($error)): ?>
                        <div class="message message--error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <form method="post" class="profile-form">
                        <div class="profile-form__field">
                            <label class="profile-form__label">Имя</label>
                            <input type="text" name="name" class="profile-form__input" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                        </div>
                        <div class="profile-form__field">
                            <label class="profile-form__label">Email</label>
                            <input type="email" name="email" class="profile-form__input" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                        <div class="profile-form__field">
                            <label class="profile-form__label">Телефон</label>
                            <input type="tel" name="phone" class="profile-form__input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        <div class="profile-form__field">
                            <label class="profile-form__label">Дата регистрации</label>
                            <input type="text" class="profile-form__input" value="<?php echo $user ? date('d.m.Y', strtotime($user['created_at'])) : ''; ?>" disabled>
                        </div>
                        <button type="submit" name="update_profile" class="profile-form__submit">Сохранить изменения</button>
                        <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #e5e5e5;">
                            <a href="/pages/logout.php" style="color: #c33; text-decoration: none; font-weight: 600;">Выйти из аккаунта</a>
                        </div>
                    </form>
                </div>

                <!-- Заказы -->
                <div class="account-section <?php echo $current_tab === 'orders' ? 'account-section--active' : ''; ?>" id="orders-section">
                    <h2 class="account-content__title">История заказов</h2>
                    <?php if (empty($orders)): ?>
                        <div class="empty-state">
                            <div class="empty-state__icon">📦</div>
                            <p>У вас пока нет заказов</p>
                        </div>
                    <?php else: ?>
                        <ul class="orders-list">
                            <?php foreach ($orders as $order): ?>
                                <li class="orders-item">
                                    <div class="orders-item__header">
                                        <span class="orders-item__id">Заказ №<?php echo $order['id']; ?></span>
                                        <span class="orders-item__status orders-item__status--<?php echo strtolower(str_replace(' ', '-', $order['status_name'])); ?>">
                                            <?php echo htmlspecialchars($order['status_name']); ?>
                                        </span>
                                    </div>
                                    <div class="orders-item__date">
                                        <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?>
                                    </div>
                                    <div class="orders-item__total">
                                        <?php echo format_price($order['total_price']); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <!-- избранное -->
                <div class="account-section <?php echo $current_tab === 'favorites' ? 'account-section--active' : ''; ?>" id="favorites-section">
                    <h2 class="account-content__title">Избранное</h2>
                    <div class="favorites-grid" id="favorites-grid">
                        <div class="empty-state">
                            <p>Список избранного пуст</p>
                        </div>
                    </div>
                </div>

                <!-- Обратная связь -->
                <div class="account-section <?php echo $current_tab === 'feedback' ? 'account-section--active' : ''; ?>" id="feedback-section">
                    <h2 class="account-content__title">Обратная связь</h2>
                    <?php if (isset($feedback_error)): ?>
                        <div class="message message--error"><?php echo htmlspecialchars($feedback_error); ?></div>
                    <?php endif; ?>
                    <?php if (isset($feedback_success)): ?>
                        <div class="message message--success"><?php echo htmlspecialchars($feedback_success); ?></div>
                    <?php endif; ?>
                    <form method="post" class="feedback-form">
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

    <script>
        // Load favorites products
        function loadFavorites() {
            const favorites = window.Favorites ? window.Favorites.getFavorites() : [];
            const grid = document.getElementById('favorites-grid');
            const countBadge = document.getElementById('favorites-count');
            
            if (countBadge) {
                countBadge.textContent = favorites.length;
            }
            
            if (favorites.length === 0) {
                grid.innerHTML = '<div class="empty-state"><div class="empty-state__icon">❤</div><p>Спсок збранного пуст</p></div>';
                return;
            }
            
            // Fetch products data
            fetch('/api/products.php?ids=' + favorites.join(','))
                .then(response => response.json())
                .then(products => {
                    if (products.length === 0) {
                        grid.innerHTML = '<div class="empty-state"><div class="empty-state__icon">❤</div><p>Список избранного пуст</p></div>';
                        return;
                    }
                    
                    grid.innerHTML = products.map(product => `
                        <article class="product-card">
                            <button type="button" class="product-card__wishlist" aria-label="В избранное" data-product-id="${product.id}">
                                <img src="/assets/icons/heart.svg" alt="" class="product-card__wishlist-icon">
                            </button>
                            <a href="/pages/page-product.php?id=${product.id}" class="product-card__link">
                                <img src="${product.image}" alt="${product.name}" class="product-card__image" loading="lazy">
                            </a>
                            <h3 class="product-card__name">
                                <a href="/pages/page-product.php?id=${product.id}">${product.name}</a>
                            </h3>
                            <div class="product-card__price">
                                ${product.discount_percent > 0 ? `<span class="product-card__price--old">${product.old_price_formatted}</span>` : ''}
                                <span class="product-card__price--new">${product.price_formatted}</span>
                            </div>
                            <button type="button" class="product-card__add-to-cart" data-product-id="${product.id}" data-product-name="${product.name}" data-product-price="${product.price_raw}" data-product-old-price="${product.old_price_raw}">
                                <img src="/assets/icons/cart.svg" alt="" class="product-card__add-to-cart-icon" width="18" height="18">
                                В корзну
                            </button>
                        </article>
                    `).join('');
                    
                    // Update wishlist buttons
                    if (window.Favorites) {
                        window.Favorites.updateBadge();
                    }
                })
                .catch(error => {
                    console.error('Error loading favorites:', error);
                    grid.innerHTML = '<div class="empty-state"><p>Ошбка пр загрузке збранного</p></div>';
                });
        }
        
        // Load favorites when on favorites tab
        <?php if ($current_tab === 'favorites'): ?>
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadFavorites);
        } else {
            loadFavorites();
        }
        <?php endif; ?>
    </script>
</body>
</html>


