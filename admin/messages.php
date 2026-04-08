<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/feedback_tools.php';

admin_require_auth();

global $pdo;

$schema = admin_feedback_schema($pdo);
$statusLabels = admin_feedback_status_labels();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();

    $messageId = admin_safe_int($_POST['message_id'] ?? 0);
    $newStatus = (string)($_POST['status'] ?? '');

    if ($messageId <= 0) {
        admin_set_flash('error', 'Некорректный ID сообщения.');
        admin_redirect('/admin/messages.php');
    }

    if (!admin_feedback_update_status($pdo, $messageId, $newStatus)) {
        admin_set_flash('error', 'Не удалось обновить статус.');
    } else {
        admin_set_flash('success', 'Статус сообщения обновлен.');
    }

    admin_redirect('/admin/messages.php');
}

$messages = [];
try {
    $messages = admin_feedback_fetch_list($pdo);
} catch (Throwable $e) {
    admin_log_error('feedback_list', $e);
    admin_set_flash('error', 'Не удалось загрузить сообщения.');
}

admin_render_header('Сообщения', 'messages');
?>
<section class="admin-table-wrap">
    <div class="admin-section-head">
        <h2>Сообщения с формы обратной связи</h2>
    </div>

    <?php if (!$schema['has_status']): ?>
        <div class="admin-alert admin-alert--warning">В таблице feedback нет столбца status. Управление статусами недоступно до обновления схемы БД.</div>
    <?php endif; ?>

    <table class="admin-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Имя</th>
            <th>Контакты</th>
            <th>Тема</th>
            <th>Дата</th>
            <th>Статус</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$messages): ?>
            <tr><td colspan="7">Сообщения не найдены.</td></tr>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <?php $status = (string)($message['status'] ?? 'new'); ?>
                <tr>
                    <td><?php echo admin_e((string)$message['id']); ?></td>
                    <td><?php echo admin_e((string)$message['name']); ?></td>
                    <td>
                        <div><?php echo admin_e((string)$message['email']); ?></div>
                        <?php if (!empty($message['phone'])): ?>
                            <div><?php echo admin_e((string)$message['phone']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="admin-cell-text"><?php echo admin_e((string)($message['subject'] ?? '—')); ?></td>
                    <td><?php echo !empty($message['created_at']) ? admin_e(date('d.m.Y H:i', strtotime((string)$message['created_at']))) : '—'; ?></td>
                    <td>
                        <?php if ($schema['has_status']): ?>
                            <span class="admin-status-tag <?php echo $status === 'done' ? 'ok' : ($status === 'in_progress' ? '' : ''); ?>">
                                <?php echo admin_e($statusLabels[$status] ?? $status); ?>
                            </span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="admin-actions">
                        <a href="<?php echo admin_e(app_url('/admin/message_view.php?id=' . (string)$message['id'])); ?>">Открыть</a>
                        <?php if ($schema['has_status']): ?>
                            <form method="post">
                                <?php echo admin_csrf_input(); ?>
                                <input type="hidden" name="message_id" value="<?php echo admin_e((string)$message['id']); ?>">
                                <select name="status">
                                    <?php foreach ($statusLabels as $statusKey => $label): ?>
                                        <option value="<?php echo admin_e($statusKey); ?>" <?php echo $status === $statusKey ? 'selected' : ''; ?>><?php echo admin_e($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="admin-text-btn">Сохранить</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php admin_render_footer(); ?>