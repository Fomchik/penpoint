<?php
require_once __DIR__ . '/../includes/security.php';
app_start_session();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth_security.php';

auth_ensure_security_schema($pdo);

$error = '';
$success = '';
$active_tab = 'login';
$show_reset_form = false;
$reset_token = trim((string)($_GET['reset'] ?? ''));
$verify_token = trim((string)($_GET['verify'] ?? ''));
$redirect_target = trim((string)($_GET['redirect'] ?? ($_POST['redirect_to'] ?? '')));
if ($redirect_target !== '' && strpos($redirect_target, '/') !== 0) {
    $redirect_target = '';
}

function auth_role_id_by_name(PDO $pdo, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    return (int)($stmt->fetchColumn() ?: 0);
}

function auth_redirect_by_role(array $user, string $redirectTarget = ''): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = (string)$user['name'];
    $_SESSION['user_email'] = (string)$user['email'];
    $_SESSION['role_name'] = (string)$user['role_name'];

    if ((string)$user['role_name'] === 'admin') {
        $_SESSION['admin_user_id'] = (int)$user['id'];
        $_SESSION['admin_user_name'] = (string)$user['name'];
        $_SESSION['admin_user_email'] = (string)$user['email'];
        if ($redirectTarget !== '' && strpos($redirectTarget, '/admin/') === 0) {
            header('Location: ' . $redirectTarget);
            exit;
        }
        header('Location: /admin/index.php');
        exit;
    }

    unset($_SESSION['admin_user_id'], $_SESSION['admin_user_name'], $_SESSION['admin_user_email']);
    header('Location: /pages/account.php');
    exit;
}

if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, r.name AS role_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([(int)$_SESSION['user_id']]);
        $session_user = $stmt->fetch();
        if ($session_user) {
            auth_redirect_by_role($session_user, $redirect_target);
        }
    } catch (Throwable $e) {
        error_log('Session login redirect error: ' . $e->getMessage());
    }
}

if ($verify_token !== '') {
    $user_id = auth_consume_token($pdo, 'email_verification_tokens', $verify_token);
    if ($user_id > 0) {
        $stmt = $pdo->prepare('UPDATE users SET is_verified = 1, email_verified_at = NOW() WHERE id = ? LIMIT 1');
        $stmt->execute([$user_id]);
        $success = 'Email успешно подтверждён. Теперь можно войти.';
    } else {
        $error = 'Ссылка подтверждения недействительна или уже использована.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $show_reset_form = true;
    app_validate_csrf_or_fail();

    $email = trim((string)($_POST['reset_email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Укажите корректный email.';
    } elseif (!auth_rate_limit_allow($pdo, 'password_reset_request', auth_get_client_ip(), 5, 300)) {
        $error = 'Слишком много запросов. Попробуйте позже.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = auth_create_token($pdo, 'password_reset_tokens', (int)$user['id'], 3600);
                $link = auth_base_url() . '/pages/login.php?reset=' . urlencode($token);
                auth_send_link_email((string)$user['email'], 'Сброс пароля Канцария', 'Сброс пароля', $link);
                auth_audit_log($pdo, 'password_reset_request', true, (int)$user['id'], (string)$user['email']);
            } else {
                auth_audit_log($pdo, 'password_reset_request', true, null, $email);
            }

            $success = 'Если email найден, ссылка на сброс уже отправлена.';
        } catch (Throwable $e) {
            auth_audit_log($pdo, 'password_reset_request', false, null, $email, $e->getMessage());
            error_log('Password reset request error: ' . $e->getMessage());
            $error = 'Не удалось отправить письмо. Попробуйте позже.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $show_reset_form = true;
    app_validate_csrf_or_fail();

    $token = trim((string)($_POST['reset_token'] ?? ''));
    $new_password = (string)($_POST['new_password'] ?? '');
    $new_password_confirm = (string)($_POST['new_password_confirm'] ?? '');

    if ($token === '') {
        $error = 'Ссылка на сброс недействительна.';
    } elseif ($new_password === '' || $new_password_confirm === '') {
        $error = 'Заполните оба поля пароля.';
    } elseif ($new_password !== $new_password_confirm) {
        $error = 'Пароли не совпадают.';
    } elseif (!auth_validate_password_strength($new_password)) {
        $error = 'Пароль должен быть не короче 8 символов, содержать заглавную букву, цифру и спецсимвол.';
    } else {
        $user_id = auth_consume_token($pdo, 'password_reset_tokens', $token);
        if ($user_id <= 0) {
            $error = 'Ссылка на сброс недействительна или уже использована.';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1');
                $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), $user_id]);
                auth_audit_log($pdo, 'password_reset_complete', true, $user_id);
                $success = 'Пароль обновлён. Теперь можно войти.';
                $show_reset_form = false;
                $reset_token = '';
            } catch (Throwable $e) {
                auth_audit_log($pdo, 'password_reset_complete', false, $user_id, null, $e->getMessage());
                error_log('Password reset complete error: ' . $e->getMessage());
                $error = 'Не удалось обновить пароль.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $active_tab = 'login';
    app_validate_csrf_or_fail();

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Заполните email и пароль.';
    } elseif (!auth_rate_limit_allow($pdo, 'login', auth_get_client_ip(), 10, 300)) {
        $error = 'Слишком много попыток входа. Попробуйте позже.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT u.id, u.name, u.email, u.password_hash, u.is_verified, r.name AS role_name
                 FROM users u
                 INNER JOIN roles r ON r.id = u.role_id
                 WHERE u.email = ? LIMIT 1'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, (string)$user['password_hash'])) {
                auth_audit_log($pdo, 'login', false, null, $email, 'Invalid credentials');
                $error = 'Неверный email или пароль.';
            } elseif ((string)$user['role_name'] !== 'admin' && isset($user['is_verified']) && (int)$user['is_verified'] !== 1) {
                auth_audit_log($pdo, 'login', false, (int)$user['id'], $email, 'Email not verified');
                $error = 'Подтвердите email перед входом.';
            } else {
                auth_audit_log($pdo, 'login', true, (int)$user['id'], $email);
                auth_redirect_by_role($user, $redirect_target);
            }
        } catch (Throwable $e) {
            auth_audit_log($pdo, 'login', false, null, $email, $e->getMessage());
            error_log('Login error: ' . $e->getMessage());
            $error = 'Ошибка при входе. Попробуйте позже.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $active_tab = 'register';
    app_validate_csrf_or_fail();

    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $password_confirm = (string)($_POST['password_confirm'] ?? '');

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Заполните все поля.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Укажите корректный email.';
    } elseif ($password !== $password_confirm) {
        $error = 'Пароли не совпадают.';
    } elseif (!auth_validate_password_strength($password)) {
        $error = 'Пароль должен быть не короче 8 символов, содержать заглавную букву, цифру и спецсимвол.';
    } elseif (!auth_rate_limit_allow($pdo, 'register', auth_get_client_ip(), 5, 300)) {
        $error = 'Слишком много попыток регистрации. Попробуйте позже.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Пользователь с таким email уже существует.';
            } else {
                $role_id = auth_role_id_by_name($pdo, 'user');
                if ($role_id <= 0) {
                    throw new RuntimeException('Role user not found.');
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO users (role_id, name, email, password_hash, is_verified)
                     VALUES (?, ?, ?, ?, 0)'
                );
                $stmt->execute([
                    $role_id,
                    $name,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                ]);

                $user_id = (int)$pdo->lastInsertId();
                $token = auth_create_token($pdo, 'email_verification_tokens', $user_id, 86400);
                $link = auth_base_url() . '/pages/login.php?verify=' . urlencode($token);
                auth_send_link_email($email, 'Подтверждение регистрации Канцария', 'Подтвердите email', $link);
                auth_audit_log($pdo, 'register', true, $user_id, $email);
                $success = 'Регистрация завершена. Проверьте почту и подтвердите email.';
                $active_tab = 'login';
            }
        } catch (Throwable $e) {
            auth_audit_log($pdo, 'register', false, null, $email, $e->getMessage());
            error_log('Registration error: ' . $e->getMessage());
            $error = 'Не удалось зарегистрироваться. Попробуйте позже.';
        }
    }
}

if ($reset_token !== '') {
    $show_reset_form = true;
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
    <link rel="stylesheet" href="/styles/login.css">
    <title>Вход — Канцария</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="main">
        <div class="login-page">
            <h1 class="login-page__title">Вход в личный кабинет</h1>

            <div class="login-page__tabs">
                <button type="button" class="login-page__tab <?php echo $active_tab === 'login' ? 'login-page__tab--active' : ''; ?>" data-tab="login">Вход</button>
                <button type="button" class="login-page__tab <?php echo $active_tab === 'register' ? 'login-page__tab--active' : ''; ?>" data-tab="register">Регистрация</button>
            </div>

            <?php if ($error): ?>
                <div class="login-page__error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="login-page__success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" class="login-page__form <?php echo $active_tab === 'login' ? 'login-page__form--active' : ''; ?>" id="login-form">
                <?php echo app_csrf_input(); ?>
                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirect_target, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="login-page__field">
                    <label class="login-page__label">Email</label>
                    <input type="email" name="email" class="login-page__input" required>
                </div>
                <div class="login-page__field">
                    <label class="login-page__label">Пароль</label>
                    <input type="password" name="password" class="login-page__input" required>
                </div>
                <button type="button" class="login-page__reset-link" data-reset-toggle>Восстановить пароль</button>
                <button type="submit" name="login" class="login-page__submit">Войти</button>
            </form>

            <form method="post" class="login-page__form <?php echo $active_tab === 'register' ? 'login-page__form--active' : ''; ?>" id="register-form">
                <?php echo app_csrf_input(); ?>
                <div class="login-page__field">
                    <label class="login-page__label">Имя пользователя</label>
                    <input type="text" name="name" class="login-page__input" required>
                </div>
                <div class="login-page__field">
                    <label class="login-page__label">Email</label>
                    <input type="email" name="email" class="login-page__input" required>
                </div>
                <div class="login-page__field">
                    <label class="login-page__label">Пароль</label>
                    <input type="password" name="password" class="login-page__input" required>
                </div>
                <div class="login-page__field">
                    <label class="login-page__label">Подтвердите пароль</label>
                    <input type="password" name="password_confirm" class="login-page__input" required>
                </div>
                <button type="submit" name="register" class="login-page__submit">Зарегистрироваться</button>
            </form>

            <section class="login-page__reset-panel <?php echo $show_reset_form ? 'login-page__reset-panel--active' : ''; ?>" id="reset-panel">
                <div class="login-page__reset-head">
                    <h2 class="login-page__reset-title"><?php echo $reset_token !== '' ? 'Новый пароль' : 'Восстановление пароля'; ?></h2>
                    <button type="button" class="login-page__reset-close" data-reset-close aria-label="Закрыть"></button>
                </div>

                <?php if ($reset_token !== ''): ?>
                    <form method="post" class="login-page__form login-page__form--active">
                        <?php echo app_csrf_input(); ?>
                        <input type="hidden" name="reset_token" value="<?php echo htmlspecialchars($reset_token, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="login-page__field">
                            <label class="login-page__label">Новый пароль</label>
                            <input type="password" name="new_password" class="login-page__input" required>
                        </div>
                        <div class="login-page__field">
                            <label class="login-page__label">Повторите пароль</label>
                            <input type="password" name="new_password_confirm" class="login-page__input" required>
                        </div>
                        <button type="submit" name="reset_password" class="login-page__submit">Сохранить пароль</button>
                    </form>
                <?php else: ?>
                    <form method="post" class="login-page__form login-page__form--active">
                        <?php echo app_csrf_input(); ?>
                        <div class="login-page__field">
                            <label class="login-page__label">Email</label>
                            <input type="email" name="reset_email" class="login-page__input" required>
                        </div>
                        <button type="submit" name="request_reset" class="login-page__submit">Отправить ссылку</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script>
    (function () {
        const tabs = document.querySelectorAll('.login-page__tab');
        const forms = {
            login: document.getElementById('login-form'),
            register: document.getElementById('register-form')
        };
        const resetPanel = document.getElementById('reset-panel');
        const resetToggle = document.querySelector('[data-reset-toggle]');
        const resetClose = document.querySelector('[data-reset-close]');

        function switchTab(name) {
            tabs.forEach(function (tab) {
                tab.classList.toggle('login-page__tab--active', tab.getAttribute('data-tab') === name);
            });
            Object.keys(forms).forEach(function (key) {
                if (forms[key]) {
                    forms[key].classList.toggle('login-page__form--active', key === name);
                }
            });
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                switchTab(tab.getAttribute('data-tab') || 'login');
            });
        });

        if (resetToggle && resetPanel) {
            resetToggle.addEventListener('click', function () {
                resetPanel.classList.add('login-page__reset-panel--active');
            });
        }

        if (resetClose && resetPanel) {
            resetClose.addEventListener('click', function () {
                resetPanel.classList.remove('login-page__reset-panel--active');
            });
        }
    })();
    </script>
</body>
</html>
