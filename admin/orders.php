<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/order_statuses.php';

admin_require_auth();

global $pdo;

$orders = [];

try {
    $stmt = $pdo->query(
        'SELECT o.id, o.total_price, o.created_at, u.name AS user_name, os.name AS status_name, o.status_id
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         LEFT JOIN order_statuses os ON os.id = o.status_id
         ORDER BY o.created_at DESC'
    );
    $orders = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    admin_log_error('orders_list', $e);
    admin_set_flash('error', 'Не удалось загрузить заказы.');
}

admin_render_header('Заказы', 'orders');
?>
<section class="admin-table-wrap">
    <div class="admin-section-head">
        <h2>Управление заказами</h2>
    </div>
    <table class="admin-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Пользователь</th>
            <th>Сумма</th>
            <th>Статус</th>
            <th>Дата</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$orders): ?>
            <tr><td colspan="6">Заказов пока нет.</td></tr>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?php echo admin_e((string)$order['id']); ?></td>
                    <td><?php echo admin_e((string)($order['user_name'] ?? 'Гость')); ?></td>
                    <td><?php echo admin_e(number_format((float)$order['total_price'], 2, '.', ' ')); ?></td>
                    <td>
                        <?php echo admin_e((string)($order['status_name'] ?? '—')); ?>
                        <span class="admin-status-tag"><?php echo admin_e(admin_order_status_slug_by_id((int)$order['status_id'])); ?></span>
                    </td>
                    <td><?php echo admin_e(date('d.m.Y H:i', strtotime((string)$order['created_at']))); ?></td>
                    <td><a href="/admin/order_view.php?id=<?php echo admin_e((string)$order['id']); ?>">Открыть</a></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php admin_render_footer(); ?>
