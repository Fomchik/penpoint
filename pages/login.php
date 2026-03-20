<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$error = '';
$success = '';

// Если уже авторизован, перенаправляе в личный кабинет
if (isset($_SESSION['user_id'])) {
    header('Location: /pages/account.php');
    exit;
}

// Обработка форы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Заполните все поля';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, password_hash FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
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
                <button type="button" class="login-page__tab login-page__tab--active" data-tab="login">Вход</button>
                <button type="button" class="login-page__tab" data-tab="register">Регистрация</button>
            </div>

            <?php if ($error): ?>
                <div class="login-page__error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="login-page__success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- Форма входа -->
            <form method="post" class="login-page__form login-page__form--active" id="login-form">
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
            <form method="post" class="login-page__form" id="register-form">
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
