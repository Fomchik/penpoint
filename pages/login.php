<?php
require_once __DIR__ . '/../includes/security.php';
app_start_session();
require_once __DIR__ . '/../includes/config.php';

$error = '';
$success = '';
$active_tab = 'login';

// Если уже авторизован, перенаправляем в соответствующий раздел по роли
if (isset($_SESSION['user_id'])) {
    $role_name = (string)($_SESSION['role_name'] ?? '');

    if ($role_name === '') {
        try {
            $stmt = $pdo->prepare("
                SELECT r.name AS role_name
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.id = ? LIMIT 1
            ");
            $stmt->execute([(int)$_SESSION['user_id']]);
            $session_user_role = $stmt->fetchColumn();
            if (is_string($session_user_role) && $session_user_role !== '') {
                $role_name = $session_user_role;
                $_SESSION['role_name'] = $role_name;
            }
        } catch (PDOException $e) {
            error_log('Session role fetch error: ' . $e->getMessage());
        }
    }

    if ($role_name === 'admin') {
        $_SESSION['admin_user_id'] = (int)$_SESSION['user_id'];
        $_SESSION['admin_user_name'] = (string)($_SESSION['user_name'] ?? '');
        $_SESSION['admin_user_email'] = (string)($_SESSION['user_email'] ?? '');
        header('Location: /admin/index.php');
        exit;
    }

    header('Location: /pages/account.php');
    exit;
}

// Восстановление пароля пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $active_tab = 'reset';
    app_validate_csrf_or_fail();
    $email = trim((string)($_POST['reset_email'] ?? ''));
    $newPassword = (string)($_POST['new_password'] ?? '');
    $newPasswordConfirm = (string)($_POST['new_password_confirm'] ?? '');

    if ($email === '' || $newPassword === '' || $newPasswordConfirm === '') {
        $error = 'Заполните все поля для восстановления пароля';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный email';
    } elseif ($newPassword !== $newPasswordConfirm) {
        $error = 'Пароли не совпадают';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Новый пароль должен быть не менее 6 символов';
    } else {
        try {
            $stmt = $pdo->prepare(
                'SELECT u.id
                 FROM users u
                 INNER JOIN roles r ON r.id = u.role_id
                 WHERE u.email = ? AND r.name = ? LIMIT 1'
            );
            $stmt->execute([$email, 'user']);
            $userId = (int)($stmt->fetchColumn() ?: 0);

            if ($userId > 0) {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmtUpdate = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1');
                $stmtUpdate->execute([$passwordHash, $userId]);
            }

            $success = 'Если пользователь с таким email найден, пароль обновлен.';
        } catch (PDOException $e) {
            $error = 'Ошибка при восстановлении пароля. Попробуйте позже.';
            error_log('Password reset error: ' . $e->getMessage());
        }
    }
}

// Обработка форы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $active_tab = 'login';
    app_validate_csrf_or_fail();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Заполните все поля';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.name, u.email, u.password_hash, r.name AS role_name
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.email = ? LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role_name'] = (string)($user['role_name'] ?? 'user');

                if ($_SESSION['role_name'] === 'admin') {
                    $_SESSION['admin_user_id'] = (int)$user['id'];
                    $_SESSION['admin_user_name'] = (string)$user['name'];
                    $_SESSION['admin_user_email'] = (string)$user['email'];
                    header('Location: /admin/index.php');
                    exit;
                }

                unset(
                    $_SESSION['admin_user_id'],
                    $_SESSION['admin_user_name'],
                    $_SESSION['admin_user_email']
                );
                header('Location: /pages/account.php');
                exit;
            } else {
                $error = 'Неверный email или пароль';
            }
        } catch (PDOException $e) {
            $error = 'Ошибка при входе. Попробуйте позже.';
            error_log('Login error: ' . $e->getMessage());
        }
    }
}

// Обработка регистрации
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $active_tab = 'register';
    app_validate_csrf_or_fail();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Заполните все поля';
    } elseif ($password !== $password_confirm) {
        $error = 'Пароли не совпадают';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен быть не енее 6 сиволов';
    } else {
        try {
            // Проверяе, существует ли пользователь
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Пользователь с таки email уже существует';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (role_id, name, email, password_hash) VALUES (2, ?, ?, ?)");
                $stmt->execute([$name, $email, $password_hash]);
                $success = 'Регистрация успешна! Войдите в систему.';
            }
        } catch (PDOException $e) {
            $error = 'Ошибка при регистрации. Попробуйте позже.';
            error_log('Registration error: ' . $e->getMessage());
        }
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
                <button type="button" class="login-page__tab <?php echo $active_tab === 'reset' ? 'login-page__tab--active' : ''; ?>" data-tab="reset">Восстановить</button>
            </div>

            <?php if ($error): ?>
                <div class="login-page__error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="login-page__success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Форма входа -->
            <form method="post" class="login-page__form <?php echo $active_tab === 'login' ? 'login-page__form--active' : ''; ?>" id="login-form">
                <?php echo app_csrf_input(); ?>
                <div class="login-page__field">
                    <label class="login-page__label">Email</label>
                    <input type="email" name="email" class="login-page__input" required>
                </div>
                <div class="login-page__field">
                    <label class="login-page__label">Пароль</label>
                    <input type="password" name="password" class="login-page__input" required>
                </div>
                <button type="submit" name="login" class="login-page__submit">Войти</button>
            </form>

            <!-- Форма регистрации -->
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

            <!-- Форма восстановления пароля -->
            <form method="post" class="login-page__form <?php echo $active_tab === 'reset' ? 'login-page__form--active' : ''; ?>" id="reset-form">
                <?php echo app_csrf_input(); ?>
                <div class="login-page__field">
                    <label class="login-page__label">Email</label>
                    <input type="email" name="reset_email" class="login-page__input" required>
                </div>
                <div class="login-page__field">
                    <label class="login-page__label">Новый пароль</label>
                    <input type="password" name="new_password" class="login-page__input" required>
                </div>
                <div class="login-page__field">
                    <label class="login-page__label">Подтвердите новый пароль</label>
                    <input type="password" name="new_password_confirm" class="login-page__input" required>
                </div>
                <button type="submit" name="reset_password" class="login-page__submit">Обновить пароль</button>
            </form>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        document.querySelectorAll('.login-page__tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabName = this.getAttribute('data-tab');
                
                // Update tabs
                document.querySelectorAll('.login-page__tab').forEach(t => t.classList.remove('login-page__tab--active'));
                this.classList.add('login-page__tab--active');
                
                // Update forms
                document.querySelectorAll('.login-page__form').forEach(f => {
                    f.classList.remove('login-page__form--active');
                });
                document.getElementById(tabName + '-form').classList.add('login-page__form--active');
            });
        });
    </script>
</body>
</html>
