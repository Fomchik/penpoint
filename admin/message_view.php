<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/feedback_tools.php';

admin_require_auth();
global $pdo;

$messageId = admin_safe_int($_GET['id'] ?? 0);
if ($messageId <= 0) {
    admin_redirect('/admin/messages.php');
}

// Обработка статуса (autosubmit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();
    $newStatus = (string)($_POST['status'] ?? '');
    admin_feedback_update_status($pdo, $messageId, $newStatus);
    admin_redirect('/admin/message_view.php?id=' . $messageId);
}

$message = admin_feedback_fetch_one($pdo, $messageId);
if (!$message) {
    admin_redirect('/admin/messages.php');
}

$schema = admin_feedback_schema($pdo);
$statusLabels = admin_feedback_status_labels();

admin_render_header('Сообщение #' . $messageId, 'messages');
?>

<section class="admin-form-wrap">
    <div class="admin-section-head">
        <h2>Сообщение №<?php echo $messageId; ?></h2>
        <a class="admin-link-btn" href="/admin/messages.php">К списку</a>
    </div>

    <div class="admin-grid-2">
        <div class="admin-panel">
            <h3>Отправитель</h3>
            <p><strong>Имя:</strong> <?php echo admin_e((string)$message['name']); ?></p>

            <p>
                <strong>Email:</strong>
                <a href="#" id="show-reply-form" class="admin-link-btn">
                    <?php echo admin_e((string)$message['email']); ?>
                </a>
            </p>

            <div id="reply-panel" class="admin-reply-box">
                <form action="mailto:<?php echo admin_e((string)$message['email']); ?>" method="GET" class="admin-form">
                    <input type="hidden" name="subject" value="Re: <?php echo admin_e((string)($message['subject'] ?? 'Обращение')); ?>">
                    <textarea name="body" rows="5" placeholder="Текст ответа..."></textarea>
                    <div class="admin-actions">
                        <button type="submit">Отправить письмо</button>
                        <button type="button" id="hide-reply-form" class="admin-text-btn danger">Отмена</button>
                    </div>
                </form>
            </div>

            <?php if (!empty($message['phone'])): ?>
                <p><strong>Телефон:</strong> <?php echo admin_e((string)$message['phone']); ?></p>
            <?php endif; ?>
            <p class="admin-list__item-note">Дата: <?php echo date('d.m.Y H:i', strtotime((string)$message['created_at'])); ?></p>
        </div>

        <div class="admin-panel">
            <h3>Статус</h3>
            <form method="post" class="admin-form">
                <?php echo admin_csrf_input(); ?>
                <select name="status" onchange="this.form.submit()">
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $message['status'] === $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <div class="admin-panel">
        <h3>Тема</h3>
        <p><strong><?php echo admin_e((string)($message['subject'] ?? 'Без темы')); ?></strong></p>
    </div>

    <div class="admin-panel">
        <h3>Текст сообщения</h3>
        <div class="admin-cell-text">
            <?php echo admin_e((string)$message['message']); ?>
        </div>
    </div>
</section>

<script src="/admin/assets/feedback.js"></script>

<?php admin_render_footer(); ?>