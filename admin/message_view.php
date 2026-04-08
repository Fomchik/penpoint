<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/feedback_tools.php';

admin_require_auth();

global $pdo;

$messageId = admin_safe_int($_GET['id'] ?? 0);
if ($messageId <= 0) {
    admin_set_flash('error', 'Некорректный ID сообщения.');
    admin_redirect('/admin/messages.php');
}

$schema = admin_feedback_schema($pdo);
$statusLabels = admin_feedback_status_labels();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();

    $newStatus = (string)($_POST['status'] ?? '');
    if (!admin_feedback_update_status($pdo, $messageId, $newStatus)) {
        admin_set_flash('error', 'Не удалось обновить статус.');
    } else {
        admin_set_flash('success', 'Статус сообщения обновлен.');
    }

    admin_redirect('/admin/message_view.php?id=' . $messageId);
}

$message = null;
try {
    $message = admin_feedback_fetch_one($pdo, $messageId);
} catch (Throwable $e) {
    admin_log_error('feedback_view', $e);
}

if (!$message) {
    admin_set_flash('error', 'Сообщение не найдено.');
    admin_redirect('/admin/messages.php');
}

$currentStatus = (string)($message['status'] ?? 'new');

admin_render_header('Сообщение #' . $messageId, 'messages');
?>
<section class="admin-form-wrap">
    <div class="admin-section-head">
        <h2>Сообщение #<?php echo admin_e((string)$messageId); ?></h2>
        <a class="admin-link-btn" href="<?php echo htmlspecialchars(app_url('/admin/messages.php'), ENT_QUOTES, 'UTF-8'); ?>">К списку</a>
    </div>

    <div class="admin-grid-2">
        <div class="admin-panel">
            <h3>Контакты</h3>
            <p><strong>Имя:</strong> <?php echo admin_e((string)$message['name']); ?></p>
            <p><strong>Email:</strong> <?php echo admin_e((string)$message['email']); ?></p>
            <?php if (!empty($message['phone'])): ?>
                <p><strong>Телефон:</strong> <?php echo admin_e((string)$message['phone']); ?></p>
            <?php endif; ?>
            <p><strong>Дата:</strong> <?php echo !empty($message['created_at']) ? admin_e(date('d.m.Y H:i', strtotime((string)$message['created_at']))) : '—'; ?></p>
        </div>

        <div class="admin-panel">
            <h3>Обработка</h3>
            <?php if ($schema['has_status']): ?>
                <form method="post" class="admin-form-inline">
                    <?php echo admin_csrf_input(); ?>
                    <label>
                        Статус
                        <select name="status">
                            <?php foreach ($statusLabels as $statusKey => $label): ?>
                                <option value="<?php echo admin_e($statusKey); ?>" <?php echo $currentStatus === $statusKey ? 'selected' : ''; ?>><?php echo admin_e($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit">Сохранить</button>
                </form>
            <?php else: ?>
                <p>Статусы недоступны: в таблице feedback отсутствует столбец status.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-panel">
        <h3>Тема</h3>
        <p><?php echo admin_e((string)($message['subject'] ?? '—')); ?></p>
    </div>

    <div class="admin-panel">
        <h3>Текст сообщения</h3>
        <p class="admin-cell-text"><?php echo nl2br(admin_e((string)$message['message'])); ?></p>
    </div>
</section>
<?php admin_render_footer(); ?>