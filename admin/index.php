<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

admin_require_auth();

global $pdo;

$productCount = 0;
$orderCount = 0;
$userCount = 0;
$latestOrders = [];

try {
    $productCount = (int)($pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() ?: 0);
    $orderCount = (int)($pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() ?: 0);
    $userCount = (int)($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() ?: 0);

    $stmt = $pdo->query(
        'SELECT o.id, o.total_price, o.created_at, u.name AS user_name, os.name AS status_name
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         LEFT JOIN order_statuses os ON os.id = o.status_id
         ORDER BY o.created_at DESC
         LIMIT 10'
    );
    $latestOrders = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    admin_log_error('dashboard', $e);
    admin_set_flash('error', 'Не удалось загрузить данные dashboard.');
}

admin_render_header('Dashboard', 'dashboard');
?>
<section class="admin-metrics">
    <article class="admin-card">
        <h2>Товаров</h2>
        <p><?php echo admin_e((string)$productCount); ?></p>
    </article>
    <article class="admin-card">
        <h2>Заказов</h2>
        <p><?php echo admin_e((string)$orderCount); ?></p>
    </article>
    <article class="admin-card">
        <h2>Пользователей</h2>
        <p><?php echo admin_e((string)$userCount); ?></p>
    </article>
</section>

<section class="admin-table-wrap">
    <div class="admin-section-head">
        <h2>Последние заказы</h2>
        <a class="admin-link-btn" href="/admin/orders.php">Все заказы</a>
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
        <?php if (!$latestOrders): ?>
            <tr><td colspan="6">Заказов пока нет.</td></tr>
        <?php else: ?>
            <?php foreach ($latestOrders as $order): ?>
                <tr>
                    <td><?php echo admin_e((string)$order['id']); ?></td>
                    <td><?php echo admin_e((string)($order['user_name'] ?? 'Гость')); ?></td>
                    <td><?php echo admin_e(number_format((float)$order['total_price'], 2, '.', ' ')); ?></td>
                    <td><?php echo admin_e((string)($order['status_name'] ?? '—')); ?></td>
                    <td><?php echo admin_e(date('d.m.Y H:i', strtotime((string)$order['created_at']))); ?></td>
                    <td><a href="/admin/order_view.php?id=<?php echo admin_e((string)$order['id']); ?>">Открыть</a></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php admin_render_footer(); ?>
