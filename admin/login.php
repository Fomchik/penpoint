<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

admin_require_guest();

$error = '';
$redirectTo = (string)($_GET['redirect'] ?? ADMIN_DASHBOARD_PATH);
if (strpos($redirectTo, '/admin/') !== 0) {
    $redirectTo = ADMIN_DASHBOARD_PATH;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $redirectFromPost = (string)($_POST['redirect_to'] ?? ADMIN_DASHBOARD_PATH);

    if ($email === '' || $password === '') {
        $error = 'Заполните email и пароль.';
    } elseif (admin_authenticate($email, $password)) {
        if (strpos($redirectFromPost, '/admin/') !== 0) {
            $redirectFromPost = ADMIN_DASHBOARD_PATH;
        }
        admin_redirect($redirectFromPost);
    } else {
        $error = 'Неверные данные или недостаточно прав.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg">
    <link rel="stylesheet" href="/admin/assets/admin.css">
    <title>Вход в админ-панель</title>
</head>
<body class="admin-login-body">
    <main class="admin-login-wrap">
        <section class="admin-login-card">
            <h1>Вход в админ-панель</h1>
            <p class="admin-login-subtitle">Только для пользователей с ролью `admin`.</p>
            <?php if ($error !== ''): ?>
                <div class="admin-alert admin-alert--error"><?php echo admin_e($error); ?></div>
            <?php endif; ?>
            <form method="post" class="admin-form">
                <?php echo admin_csrf_input(); ?>
                <input type="hidden" name="redirect_to" value="<?php echo admin_e($redirectTo); ?>">
                <label>
                    Email
                    <input type="email" name="email" required>
                </label>
                <label>
                    Пароль
                    <input type="password" name="password" required>
                </label>
                <button type="submit">Войти</button>
            </form>
        </section>
    </main>
</body>
</html>

