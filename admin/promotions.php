<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/promotions_tools.php';

admin_require_auth();

global $pdo;

$statusLabels = admin_promotion_status_labels();
$scopeLabels = admin_promotion_scope_labels();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();
    $action = (string)($_POST['action'] ?? '');
    $promotionId = admin_safe_int($_POST['promotion_id'] ?? 0);

    if ($promotionId <= 0) {
        admin_set_flash('error', 'Некорректный ID акции.');
        admin_redirect('/admin/promotions.php');
    }

    try {
        if ($action === 'delete') {
            $pdo->beginTransaction();
            $stmtDelProducts = $pdo->prepare('DELETE FROM promotion_products WHERE promotion_id = ?');
            $stmtDelCategories = $pdo->prepare('DELETE FROM promotion_categories WHERE promotion_id = ?');
            $stmtDelPromotion = $pdo->prepare('DELETE FROM promotions WHERE id = ? LIMIT 1');
            $stmtDelProducts->execute([$promotionId]);
            $stmtDelCategories->execute([$promotionId]);
            $stmtDelPromotion->execute([$promotionId]);
            if ($stmtDelPromotion->rowCount() !== 1) {
                throw new RuntimeException('Акция не найдена.');
            }
            $pdo->commit();
            admin_set_flash('success', 'Акция удалена.');
        } elseif ($action === 'change_status') {
            $targetStatus = (string)($_POST['target_status'] ?? '');
            admin_promotion_force_status($pdo, $promotionId, $targetStatus);
            admin_set_flash('success', 'Статус акции обновлен.');
        } else {
            admin_set_flash('error', 'Неизвестное действие.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        admin_log_error('promotions_action', $e);
        admin_set_flash('error', 'Не удалось выполнить действие с акцией.');
    }

    admin_redirect('/admin/promotions.php');
}

$promotions = [];
try {
    $stmt = $pdo->query(
        'SELECT v.id, v.title, v.short_text, v.image_path, v.apply_scope, v.date_start, v.date_end,
                v.discount_percent, v.effective_status
         FROM v_promotion_status v
         ORDER BY v.date_start DESC, v.id DESC'
    );
    $promotions = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    admin_log_error('promotions_list', $e);
    admin_set_flash('error', 'Не удалось загрузить список акций.');
}

admin_render_header('Акции', 'promotions');
?>
<section class="admin-table-wrap">
    <div class="admin-section-head">
        <h2>Управление акциями</h2>
        <a class="admin-link-btn" href="/admin/promotion_create.php">Добавить акцию</a>
    </div>
    <table class="admin-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Изображение</th>
            <th>Название</th>
            <th>Описание</th>
            <th>Скидка</th>
            <th>Область</th>
            <th>Период</th>
            <th>Статус</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$promotions): ?>
            <tr><td colspan="9">Акции не найдены.</td></tr>
        <?php else: ?>
            <?php foreach ($promotions as $promotion): ?>
                <?php $effectiveStatus = (string)($promotion['effective_status'] ?? 'draft'); ?>
                <tr>
                    <td><?php echo admin_e((string)$promotion['id']); ?></td>
                    <td>
                        <?php if (!empty($promotion['image_path'])): ?>
                            <img class="admin-thumb" src="<?php echo admin_e((string)$promotion['image_path']); ?>" alt="">
                        <?php else: ?>
                            <span>—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo admin_e((string)$promotion['title']); ?></td>
                    <td class="admin-cell-text"><?php echo admin_e((string)$promotion['short_text']); ?></td>
                    <td><?php echo admin_e((string)$promotion['discount_percent']); ?>%</td>
                    <td><?php echo admin_e($scopeLabels[(string)$promotion['apply_scope']] ?? '—'); ?></td>
                    <td>
                        <?php echo admin_e(date('d.m.Y', strtotime((string)$promotion['date_start']))); ?>
                        -
                        <?php echo !empty($promotion['date_end']) ? admin_e(date('d.m.Y', strtotime((string)$promotion['date_end']))) : 'Без даты окончания'; ?>
                    </td>
                    <td>
                        <span class="admin-status-tag <?php echo $effectiveStatus === 'active' ? 'ok' : ($effectiveStatus === 'finished' ? 'danger' : ''); ?>">
                            <?php echo admin_e($statusLabels[$effectiveStatus] ?? $effectiveStatus); ?>
                        </span>
                    </td>
                    <td class="admin-actions">
                        <a href="/admin/promotion_edit.php?id=<?php echo admin_e((string)$promotion['id']); ?>">Редактировать</a>
                        <?php foreach ($statusLabels as $statusKey => $statusLabel): ?>
                            <?php if ($statusKey !== $effectiveStatus): ?>
                                <form method="post">
                                    <?php echo admin_csrf_input(); ?>
                                    <input type="hidden" name="action" value="change_status">
                                    <input type="hidden" name="promotion_id" value="<?php echo admin_e((string)$promotion['id']); ?>">
                                    <input type="hidden" name="target_status" value="<?php echo admin_e($statusKey); ?>">
                                    <button type="submit" class="admin-text-btn">В <?php echo admin_e($statusLabel); ?></button>
                                </form>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <form method="post" onsubmit="return confirm('Удалить акцию?');">
                            <?php echo admin_csrf_input(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="promotion_id" value="<?php echo admin_e((string)$promotion['id']); ?>">
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
