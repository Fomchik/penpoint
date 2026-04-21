<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/feedback_tools.php';

admin_require_auth();
global $pdo;

$schema = admin_feedback_schema($pdo);
$statusLabels = admin_feedback_status_labels();
$page = max(1, admin_safe_int($_GET['page'] ?? 1, 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;
$totalMessages = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();
    $messageId = admin_safe_int($_POST['message_id'] ?? 0);
    $newStatus = (string)($_POST['status'] ?? '');

    if ($messageId > 0 && admin_feedback_update_status($pdo, $messageId, $newStatus)) {
        admin_set_flash('success', "Статус сообщения #{$messageId} обновлен.");
    } else {
        admin_set_flash('error', 'Не удалось обновить статус.');
    }
    admin_redirect('/admin/messages.php');
}

$messages = [];
try {
    $totalMessages = (int)($pdo->query('SELECT COUNT(*) FROM feedback')->fetchColumn() ?: 0);
    $fields = admin_feedback_get_fields($schema);
    $sql = 'SELECT ' . implode(', ', $fields) . ' FROM feedback ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    admin_log_error('feedback_list', $e);
}

admin_render_header('Сообщения', 'messages');
?>
<section class="admin-table-wrap">
    <div class="admin-section-head">
        <h2>Обратная связь</h2>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Отправитель</th>
                <th>Тема</th>
                <th>Дата</th>
                <th>Статус</th>
                <th class="text-right">Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$messages): ?>
                <tr>
                    <td colspan="6" class="text-center">Сообщений нет.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($messages as $message):
                    $status = (string)($message['status'] ?? 'new');
                ?>
                    <tr class="<?php echo $status === 'new' ? 'admin-row-new' : ''; ?>">
                        <td><?php echo admin_e((string)$message['id']); ?></td>
                        <td>
                            <div class="admin-user-info">
                                <strong><?php echo admin_e((string)$message['name']); ?></strong>
                                <div class="admin-list__item-note"><?php echo admin_e((string)$message['email']); ?></div>
                            </div>
                        </td>
                        <td>
                            <div class="admin-subject-line"><?php echo admin_e((string)($message['subject'] ?? 'Без темы')); ?></div>
                        </td>
                        <td><small><?php echo date('d.m.Y H:i', strtotime((string)$message['created_at'])); ?></small></td>
                        <td>
                            <?php if ($schema['has_status']): ?>
                                <form method="post" class="admin-status-form">
                                    <?php echo admin_csrf_input(); ?>
                                    <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                                    <select name="status" onchange="this.form.submit()" class="admin-form-select-sm">
                                        <?php foreach ($statusLabels as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" <?php echo $status === $key ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <a class="admin-link-btn" href="/admin/message_view.php?id=<?php echo $message['id']; ?>">Открыть</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</section>
<?php $totalPages = max(1, (int)ceil($totalMessages / $perPage)); ?>
<?php if ($totalPages > 1): ?>
    <nav class="admin-pagination" aria-label="Пагинация сообщений">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a class="admin-link-btn<?php echo $p === $page ? ' is-active' : ''; ?>" href="/admin/messages.php?page=<?php echo $p; ?>">
                <?php echo $p; ?>
            </a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
<?php admin_render_footer(); ?>