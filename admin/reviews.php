<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/reviews_tools.php';

admin_require_auth();

global $pdo;

$visibilityAvailable = admin_reviews_visibility_available($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();
    $action = (string)($_POST['action'] ?? '');
    $reviewId = admin_safe_int($_POST['review_id'] ?? 0);

    if ($reviewId <= 0) {
        admin_set_flash('error', 'Некорректный ID отзыва.');
        admin_redirect('/admin/reviews.php');
    }

    try {
        if ($action === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM reviews WHERE id = ? LIMIT 1');
            $stmt->execute([$reviewId]);
            admin_set_flash('success', 'Отзыв удалён.');
        } elseif ($action === 'toggle_visibility') {
            if (!$visibilityAvailable) {
                throw new RuntimeException('Скрытие/публикация недоступны.');
            }

            $newState = admin_safe_int($_POST['is_published'] ?? 1) === 1 ? 1 : 0;
            $stmt = $pdo->prepare('UPDATE reviews SET is_published = ? WHERE id = ? LIMIT 1');
            $stmt->execute([$newState, $reviewId]);
            admin_set_flash('success', $newState === 1 ? 'Отзыв опубликован.' : 'Отзыв скрыт.');
        }
    } catch (Throwable $e) {
        admin_log_error('reviews_action', $e);
        admin_set_flash('error', 'Не удалось выполнить действие с отзывом.');
    }

    admin_redirect('/admin/reviews.php');
}

$reviews = [];

try {
    $selectPublished = $visibilityAvailable ? ', r.is_published' : ', 1 AS is_published';
    $stmt = $pdo->query(
        'SELECT r.id, r.rating, r.comment, r.created_at, r.product_id, r.user_id' . $selectPublished . ',
                p.name AS product_name, u.name AS user_name
         FROM reviews r
         LEFT JOIN products p ON p.id = r.product_id
         LEFT JOIN users u ON u.id = r.user_id
         ORDER BY r.created_at DESC'
    );
    $reviews = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    admin_log_error('reviews_list', $e);
    admin_set_flash('error', 'Не удалось загрузить отзывы.');
}

admin_render_header('Отзывы', 'reviews');
?>
<section class="admin-table-wrap">
    <div class="admin-section-head">
        <h2>Управление отзывами</h2>
    </div>
    <?php if (!$visibilityAvailable): ?>
        <div class="admin-alert admin-alert--warning">
            Режим публикации/скрытия недоступен. Проверка и изменение статуса не выполнены.
        </div>
    <?php endif; ?>
    <table class="admin-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Пользователь</th>
            <th>Товар</th>
            <th>Оценка</th>
            <th>Комментарий</th>
            <th>Статус</th>
            <th>Дата</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$reviews): ?>
            <tr><td colspan="8">Отзывов пока нет.</td></tr>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <?php $published = ((int)$review['is_published'] === 1); ?>
                <tr>
                    <td><?php echo admin_e((string)$review['id']); ?></td>
                    <td><?php echo admin_e((string)($review['user_name'] ?? 'Пользователь #' . $review['user_id'])); ?></td>
                    <td><?php echo admin_e((string)($review['product_name'] ?? 'Товар #' . $review['product_id'])); ?></td>
                    <td><?php echo admin_e((string)$review['rating']); ?></td>
                    <td class="admin-cell-text"><?php echo admin_e((string)($review['comment'] ?? '')); ?></td>
                    <td>
                        <span class="admin-status-tag <?php echo $published ? 'ok' : 'danger'; ?>">
                            <?php echo $published ? 'Опубликован' : 'Скрыт'; ?>
                        </span>
                    </td>
                    <td><?php echo admin_e(date('d.m.Y H:i', strtotime((string)$review['created_at']))); ?></td>
                    <td class="admin-actions">
                        <?php if ($visibilityAvailable): ?>
                            <form method="post">
                                <?php echo admin_csrf_input(); ?>
                                <input type="hidden" name="action" value="toggle_visibility">
                                <input type="hidden" name="review_id" value="<?php echo admin_e((string)$review['id']); ?>">
                                <input type="hidden" name="is_published" value="<?php echo $published ? '0' : '1'; ?>">
                                <button type="submit" class="admin-text-btn"><?php echo $published ? 'Скрыть' : 'Опубликовать'; ?></button>
                            </form>
                        <?php endif; ?>
                        <form method="post" onsubmit="return confirm('Удалить отзыв?');">
                            <?php echo admin_csrf_input(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="review_id" value="<?php echo admin_e((string)$review['id']); ?>">
                            <button type="submit" class="admin-text-btn danger">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php admin_render_footer(); ?>
