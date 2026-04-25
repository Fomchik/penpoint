<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

admin_require_auth();

global $pdo;

function admin_fetch_metric(PDO $pdo, string $sql, array $params = []): float
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float)($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        admin_log_error('dashboard_metrics', $e);
        return 0;
    }
}

$productCount = (int)admin_fetch_metric($pdo, 'SELECT COUNT(*) FROM products');

$orderCount = (int)admin_fetch_metric($pdo, 'SELECT COUNT(*) FROM orders WHERE status_id != 5');

$userCount = (int)admin_fetch_metric(
    $pdo,
    'SELECT COUNT(u.id) FROM users u 
     INNER JOIN roles r ON u.role_id = r.id 
     WHERE r.name = ?',
    ['user']
);

$revenue = admin_fetch_metric(
    $pdo,
    'SELECT SUM(total_price) FROM orders 
     WHERE status_id IN (2, 3, 4, 6) 
     AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
);

$latestOrders = [];
try {
    $stmt = $pdo->query(
        'SELECT o.id, o.total_price, o.created_at, o.status_id, u.name AS user_name, os.name AS status_name
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         LEFT JOIN order_statuses os ON os.id = o.status_id
         ORDER BY o.created_at DESC
         LIMIT 10'
    );
    $latestOrders = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    admin_log_error('dashboard_orders', $e);
}

admin_render_header('Панель управления', 'dashboard');
?>

<section class="admin-metrics">
    <article class="admin-card">
        <h3>Товары</h3>
        <p class="admin-card__value"><?php echo admin_e((string)$productCount); ?></p>
        <span class="admin-card__hint"></span>
    </article>

    <article class="admin-card">
        <h3>Заказы</h3>
        <p class="admin-card__value"><?php echo admin_e((string)$orderCount); ?></p>
        <span class="admin-card__hint"></span>
    </article>

    <article class="admin-card">
        <h3>Клиенты</h3>
        <p class="admin-card__value"><?php echo admin_e((string)$userCount); ?></p>
        <span class="admin-card__hint"></span>
    </article>

    <article class="admin-card admin-card--highlight">
        <h3>Доход (30 дн.)</h3>
        <p class="admin-card__value"><?php echo admin_e(number_format($revenue, 0, '.', ' ')); ?> ₽</p>
        <span class="admin-card__hint"></span>
    </article>
</section>

<section class="admin-table-wrap">
    <div class="admin-section-head">
        <h2>Последняя активность</h2>
        <a class="admin-link-btn" href="/admin/orders.php">Весь журнал</a>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Покупатель</th>
                <th>Сумма</th>
                <th>Статус</th>
                <th>Дата</th>
                <th class="text-right">Управление</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($latestOrders as $order):
                // Выделяем отмененные заказы визуально
                $isCancelled = (int)$order['status_id'] === 5;
            ?>
                <tr style="<?php echo $isCancelled ? 'opacity: 0.6; background: #f9f9f9;' : ''; ?>">
                    <td>#<?php echo admin_e((string)$order['id']); ?></td>
                    <td><?php echo admin_e((string)($order['user_name'] ?? 'Гость')); ?></td>
                    <td>
                        <span style="<?php echo $isCancelled ? 'text-decoration: line-through;' : ''; ?>">
                            <?php echo admin_e(number_format((float)$order['total_price'], 2, '.', ' ')); ?> ₽
                        </span>
                    </td>
                    <td>
                        <span class="admin-status-badge status-<?php echo $order['status_id']; ?>">
                            <?php echo admin_e((string)($order['status_name'] ?? '—')); ?>
                        </span>
                    </td>
                    <td><?php echo admin_e(date('d.m.Y H:i', strtotime((string)$order['created_at']))); ?></td>
                    <td class="text-right">
                        <a class="admin-action-link" href="/admin/order_view.php?id=<?php echo admin_e((string)$order['id']); ?>">Просмотр</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php admin_render_footer(); ?>