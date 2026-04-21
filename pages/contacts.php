<?php
require_once __DIR__ . '/../includes/security.php';
app_start_session();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/feedback.php';
require_once __DIR__ . '/../includes/mail.php';

$success_message = '';
$send_error = '';

$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

$subject_options = [
    'order' => 'Вопрос по заказу',
    'product' => 'Вопрос по товару',
    'delivery' => 'Доставка и оплата',
    'other' => 'Другое',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_validate_csrf_or_fail();
    $is_valid = (
        $name !== '' &&
        $phone !== '' &&
        $message !== '' &&
        $email !== '' &&
        filter_var($email, FILTER_VALIDATE_EMAIL)
    );

    if ($is_valid) {
        $saved_to_db = false;
        try {
            app_feedback_insert($pdo, [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'subject' => $subject !== '' ? ($subject_options[$subject] ?? $subject) : '',
                'message' => $message,
                'status' => 'new',
            ]);
            $saved_to_db = true;
        } catch (Throwable $e) {
            error_log('Database error (feedback): ' . $e->getMessage());
        }

        $to = 'cantsaria@yandex.ru';
        $selected_subject = $subject_options[$subject] ?? 'Сообщение с формы контактов';
        $mail_subject = 'Контакты Канцария: ' . $selected_subject;

        $safe_name = str_replace(["\r", "\n"], ' ', $name);
        $safe_email = filter_var($email, FILTER_SANITIZE_EMAIL) ?: 'no-reply@localhost';
        $safe_phone = str_replace(["\r", "\n"], ' ', $phone);

        $plain = "Новое сообщение с сайта Канцария\n\n";
        $plain .= "Имя: {$safe_name}\n";
        $plain .= "Email: {$safe_email}\n";
        $plain .= "Телефон: {$safe_phone}\n";
        $plain .= "Тема: {$selected_subject}\n\n";
        $plain .= "Сообщение:\n{$message}\n";

        $safe_subject = htmlspecialchars((string)$selected_subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html lang="ru"><head><meta charset="UTF-8"><title>' . $safe_subject . '</title></head><body style="margin:0;padding:24px;background:#f5f6f8;font-family:Arial,sans-serif;">'
            . '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:12px;">'
            . '<tr><td style="padding:22px 20px;color:#1f2937;font-size:15px;line-height:1.55;">'
            . '<h2 style="margin:0 0 14px;font-size:18px;line-height:1.3;">Новое сообщение с формы контактов</h2>'
            . '<p style="margin:0 0 6px;"><b>Имя:</b> ' . htmlspecialchars($safe_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
            . '<p style="margin:0 0 6px;"><b>Email:</b> ' . htmlspecialchars($safe_email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
            . '<p style="margin:0 0 6px;"><b>Телефон:</b> ' . htmlspecialchars($safe_phone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>'
            . '<p style="margin:0 0 12px;"><b>Тема:</b> ' . $safe_subject . '</p>'
            . '<div style="padding:12px 14px;border:1px solid #eadfd5;border-radius:10px;background:#fbf7f3;white-space:pre-wrap;">'
            . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</div>'
            . '</td></tr></table></body></html>';

        $mail_sent = app_send_smtp_mail(
            $to,
            $mail_subject,
            $html,
            $plain,
            $safe_email,
            $safe_name
        );

        if ($mail_sent) {
            $success_message = 'Спасибо! Ваше сообщение отправлено.';
            $name = '';
            $email = '';
            $phone = '';
            $subject = '';
            $message = '';
        } else {
            $send_error = 'Не удалось отправить сообщение на почту. Попробуйте позже.';
        }

        if (!$saved_to_db && $mail_sent) {
            error_log('Feedback mail sent, but DB save failed.');
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
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/styles/global.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/styles/header.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/styles/footer.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/styles/contacts.css">
    <title>Контакты — Канцария</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="main contacts-page">
        <section class="section-contacts">
            <h1 class="contacts__title">Контакты</h1>
            <p class="contacts__subtitle">Свяжитесь с нами любым удобным способом</p>

            <div class="contacts__grid">
                <div class="contacts__left">
                    <div class="contacts-card">
                        <div class="contacts-card__top">
                            <span class="contacts-card__icon-wrap" aria-hidden="true">
                                <img src="<?php echo BASE_PATH; ?>/assets/icons/mail.svg" alt="" class="contacts-card__icon" width="20" height="20">
                            </span>
                            <div>
                                <p class="contacts-card__label">Email</p>
                                <p class="contacts-card__value">cantsaria@yandex.ru</p>
                                <p class="contacts-card__hint">Отвечаем в течение часа</p>
                            </div>
                        </div>
                    </div>

                    <div class="contacts-card">
                        <div class="contacts-card__top">
                            <span class="contacts-card__icon-wrap" aria-hidden="true">
                                <img src="<?php echo BASE_PATH; ?>/assets/icons/phone.svg" alt="" class="contacts-card__icon" width="20" height="20">
                            </span>
                            <div>
                                <p class="contacts-card__label">Телефон</p>
                                <p class="contacts-card__value">8 996 357 67 87</p>
                                <p class="contacts-card__hint">Ежедневно с 09:00 до 21:00</p>
                            </div>
                        </div>
                    </div>

                    <div class="contacts-card">
                        <div class="contacts-card__top">
                            <span class="contacts-card__icon-wrap" aria-hidden="true">
                                <img src="<?php echo BASE_PATH; ?>/assets/icons/mark.svg" alt="" class="contacts-card__icon" width="20" height="20">
                            </span>
                            <div>
                                <p class="contacts-card__label">Магазин</p>
                                <p class="contacts-card__value">Волгоград, ул. Новороссийская, 67</p>
                            </div>
                        </div>
                    </div>

                    <div class="contacts-card contacts-card--inline">
                        <div class="contacts-card__top">
                            <span class="contacts-card__icon-wrap" aria-hidden="true">
                                <img src="<?php echo BASE_PATH; ?>/assets/icons/clock.svg" alt="" class="contacts-card__icon" width="20" height="20">
                            </span>
                            <p class="contacts-card__label contacts-card__label--inline-title">Режим работы</p>
                        </div>
                        <div class="contacts-card__schedule">
                            <div>
                                <span class="contacts-card__schedule-day">Пн - Пт</span>
                                <span class="contacts-card__schedule-time">09:00 - 21:00</span>
                            </div>
                            <div>
                                <span class="contacts-card__schedule-day">Сб - Вс</span>
                                <span class="contacts-card__schedule-time">10:00 - 20:00</span>
                            </div>
                        </div>
                    </div>

                    <div class="contacts-card contacts-card--inline">
                        <div class="contacts-card__top">
                            <span class="contacts-card__icon-wrap" aria-hidden="true">
                                <img src="<?php echo BASE_PATH; ?>/assets/icons/message.svg" alt="" class="contacts-card__icon" width="20" height="20">
                            </span>
                            <p class="contacts-card__label contacts-card__label--inline-title">Мы в соцсетях</p>
                        </div>
                        <div class="contacts-card__socials">
                            <a href="#" aria-label="Max">
                                <img src="<?php echo BASE_PATH; ?>/assets/icons/max.svg" alt="" width="24" height="24">
                            </a>
                            <a href="#" aria-label="ВКонтакте">
                                <img src="<?php echo BASE_PATH; ?>/assets/icons/vkontakte.svg" alt="" width="24" height="24">
                            </a>
                        </div>
                    </div>
                </div>

                <div class="contacts__right">
                    <div class="contacts-form-card">
                        <h2 class="contacts-form-card__title">Напиши нам</h2>

                        <?php if ($success_message): ?>
                            <div class="contacts-form__success">
                                <?php echo htmlspecialchars($success_message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($send_error): ?>
                            <div class="contacts-form__errors">
                                <?php echo htmlspecialchars($send_error); ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="contacts-form">
                            <?php echo app_csrf_input(); ?>
                            <div class="contacts-form__row">
                                <label class="contacts-form__field">
                                    <span class="contacts-form__label">Ваше имя</span>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="Введите имя" autocomplete="name" required>
                                </label>
                                <label class="contacts-form__field">
                                    <span class="contacts-form__label">Email</span>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="Введите email" autocomplete="email" required>
                                </label>
                            </div>

                            <div class="contacts-form__row">
                                <label class="contacts-form__field">
                                    <span class="contacts-form__label">Телефон</span>
                                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="Введите телефон" autocomplete="tel" required>
                                </label>
                                <label class="contacts-form__field">
                                    <span class="contacts-form__label">Тема обращения</span>
                                    <select name="subject">
                                        <option value="">Выберите тему</option>
                                        <?php foreach ($subject_options as $option_value => $option_label): ?>
                                            <option value="<?php echo htmlspecialchars($option_value); ?>" <?php echo $subject === $option_value ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($option_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>

                            <label class="contacts-form__field contacts-form__field--full">
                                <span class="contacts-form__label">Сообщение</span>
                                <textarea name="message" rows="5" maxlength="500" placeholder="Опишите ваш вопрос подробнее..." required><?php echo htmlspecialchars($message); ?></textarea>
                            </label>

                            <div class="contacts-form__footer">
                                <div class="contacts-form__notice">
                                    <img src="<?php echo BASE_PATH; ?>/assets/icons/notification.svg" alt="" width="16" height="16" aria-hidden="true">
                                    <span>Нажимая кнопку, вы соглашаетесь с <a href="/pages/privacy.php" class="contacts-form__policy-link">политикой обработки персональных данных</a>.</span>
                                </div>
                                <button type="submit" class="contacts-form__submit">Отправить</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
