<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/order_statuses.php';

admin_require_auth();
global $pdo;

$orders = [];
$page = max(1, admin_safe_int($_GET['page'] ?? 1, 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;
$totalOrders = 0;
try {
    $totalOrders = (int)($pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() ?: 0);
    $stmt = $pdo->prepare(
        'SELECT o.id, o.total_price, o.created_at, o.status_id, 
                o.customer_name, u.name AS user_name, 
                os.name AS status_name
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         LEFT JOIN order_statuses os ON os.id = o.status_id
         ORDER BY o.created_at DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    admin_log_error('orders_list', $e);
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
                <th>Покупатель</th>
                <th>Сумма</th>
                <th>Статус</th>
                <th>Дата</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$orders): ?>
                <tr>
                    <td colspan="6">Заказов пока нет.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <?php
                    $displayName = (string)($order['customer_name'] ?: $order['user_name'] ?: 'Гость');
                    $statusSlug = admin_order_status_slug_by_id((int)$order['status_id']);

                    // Привязка твоих классов к слагу статуса
                    $statusClass = '';
                    if ($statusSlug === 'completed' || $statusSlug === 'delivered') $statusClass = 'ok';
                    if ($statusSlug === 'canceled' || $statusSlug === 'rejected') $statusClass = 'danger';
                    ?>
                    <tr>
                        <td><strong>#<?php echo admin_e((string)$order['id']); ?></strong></td>
                        <td><?php echo admin_e($displayName); ?></td>
                        <td><?php echo admin_e(number_format((float)$order['total_price'], 2, '.', ' ')); ?></td>
                        <td>
                            <span class="admin-status-tag <?php echo $statusClass; ?>">
                                <?php echo admin_e((string)($order['status_name'] ?? '—')); ?>
                            </span>
                        </td>
                        <td class="admin-list__item-note"><?php echo admin_e(date('d.m.Y H:i', strtotime((string)$order['created_at']))); ?></td>
                        <td><a class="admin-link-btn" href="/admin/order_view.php?id=<?php echo admin_e((string)$order['id']); ?>">Просмотр</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</section>
<?php $totalPages = max(1, (int)ceil($totalOrders / $perPage)); ?>
<?php if ($totalPages > 1): ?>
    <nav class="admin-pagination" aria-label="Пагинация заказов">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a class="admin-link-btn<?php echo $p === $page ? ' is-active' : ''; ?>" href="/admin/orders.php?page=<?php echo $p; ?>">
                <?php echo $p; ?>
            </a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
<?php admin_render_footer(); ?>