<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/order_statuses.php';

admin_require_auth();

global $pdo;

$orderId = admin_safe_int($_GET['id'] ?? 0);
if ($orderId <= 0) {
    admin_set_flash('error', 'Некорректный ID заказа.');
    admin_redirect('/admin/orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();
    $statusSlug = (string)($_POST['status_slug'] ?? '');
    $statusMap = admin_order_status_map();

    if (!isset($statusMap[$statusSlug])) {
        admin_set_flash('error', 'Некорректный статус.');
        admin_redirect('/admin/order_view.php?id=' . $orderId);
    }

    try {
        $stmt = $pdo->prepare('UPDATE orders SET status_id = ? WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$statusMap[$statusSlug]['id'], $orderId]);
        admin_set_flash('success', 'Статус заказа обновлён.');
    } catch (Throwable $e) {
        admin_log_error('order_status_update', $e);
        admin_set_flash('error', 'Не удалось обновить статус.');
    }

    admin_redirect('/admin/order_view.php?id=' . $orderId);
}

$order = null;
$items = [];

try {
    $stmt = $pdo->prepare(
        'SELECT o.id, o.total_price, o.address, o.created_at, o.status_id, os.name AS status_name,
                u.id AS user_id, u.name AS user_name, u.email AS user_email, u.phone AS user_phone
         FROM orders o
         LEFT JOIN order_statuses os ON os.id = o.status_id
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.id = ? LIMIT 1'
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!is_array($order)) {
        admin_set_flash('error', 'Заказ не найден.');
        admin_redirect('/admin/orders.php');
    }

    $stmtItems = $pdo->prepare(
        'SELECT oi.product_id, oi.quantity, oi.price, oi.base_price, oi.discount_percent, p.name AS product_name
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?
         ORDER BY oi.id ASC'
    );
    $stmtItems->execute([$orderId]);
    $items = $stmtItems->fetchAll() ?: [];
} catch (Throwable $e) {
    admin_log_error('order_view_fetch', $e);
    admin_set_flash('error', 'Не удалось загрузить заказ.');
    admin_redirect('/admin/orders.php');
}

$statusMap = admin_order_status_map();
$currentStatusSlug = admin_order_status_slug_by_id((int)$order['status_id']);

admin_render_header('Заказ #' . $orderId, 'orders');
?>
<section class="admin-form-wrap">
    <div class="admin-section-head">
        <h2>Заказ #<?php echo admin_e((string)$order['id']); ?></h2>
        <a class="admin-link-btn" href="/admin/orders.php">К списку заказов</a>
    </div>

    <div class="admin-grid-2">
        <article class="admin-panel">
            <h3>Информация о заказе</h3>
            <p><strong>Дата:</strong> <?php echo admin_e(date('d.m.Y H:i', strtotime((string)$order['created_at']))); ?></p>
            <p><strong>Сумма:</strong> <?php echo admin_e(number_format((float)$order['total_price'], 2, '.', ' ')); ?></p>
            <p><strong>Адрес:</strong> <?php echo admin_e((string)($order['address'] ?? '—')); ?></p>
            <p><strong>Текущий статус:</strong> <?php echo admin_e((string)$order['status_name']); ?> (<?php echo admin_e($currentStatusSlug); ?>)</p>
        </article>
        <article class="admin-panel">
            <h3>Покупатель</h3>
            <p><strong>ID:</strong> <?php echo admin_e((string)($order['user_id'] ?? '—')); ?></p>
            <p><strong>Имя:</strong> <?php echo admin_e((string)($order['user_name'] ?? 'Гость')); ?></p>
            <p><strong>Email:</strong> <?php echo admin_e((string)($order['user_email'] ?? '—')); ?></p>
            <p><strong>Телефон:</strong> <?php echo admin_e((string)($order['user_phone'] ?? '—')); ?></p>
        </article>
    </div>

    <form method="post" class="admin-form admin-form-inline">
        <?php echo admin_csrf_input(); ?>
        <label>
            Изменить статус
            <select name="status_slug">
                <?php foreach ($statusMap as $slug => $data): ?>
                    <option value="<?php echo admin_e($slug); ?>" <?php echo $slug === $currentStatusSlug ? 'selected' : ''; ?>>
                        <?php echo admin_e($slug . ' / ' . $data['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Сохранить статус</button>
    </form>
</section>

<section class="admin-table-wrap">
    <h2>Товары в заказе</h2>
    <table class="admin-table">
        <thead>
        <tr>
            <th>Товар</th>
            <th>ID товара</th>
            <th>Количество</th>
            <th>Базовая цена</th>
            <th>Скидка %</th>
            <th>Цена в заказе</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$items): ?>
            <tr><td colspan="6">В заказе нет позиций.</td></tr>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo admin_e((string)($item['product_name'] ?? 'Удалённый товар')); ?></td>
                    <td><?php echo admin_e((string)$item['product_id']); ?></td>
                    <td><?php echo admin_e((string)$item['quantity']); ?></td>
                    <td><?php echo admin_e(number_format((float)$item['base_price'], 2, '.', ' ')); ?></td>
                    <td><?php echo admin_e((string)$item['discount_percent']); ?></td>
                    <td><?php echo admin_e(number_format((float)$item['price'], 2, '.', ' ')); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php admin_render_footer(); ?>
