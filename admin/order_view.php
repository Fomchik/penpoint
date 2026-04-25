<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/order_statuses.php';
require_once __DIR__ . '/../includes/orders.php';

admin_require_auth();
global $pdo;
app_ensure_order_schema($pdo);

$orderId = admin_safe_int($_GET['id'] ?? 0);
if ($orderId <= 0) admin_redirect('/admin/orders.php');

// Autosubmit статуса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();
    $statusSlug = (string)($_POST['status_slug'] ?? '');
    $statusMap = admin_order_status_map();
    if (isset($statusMap[$statusSlug])) {
        $stmt = $pdo->prepare('UPDATE orders SET status_id = ? WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$statusMap[$statusSlug]['id'], $orderId]);
    }
    admin_redirect('/admin/order_view.php?id=' . $orderId);
}

$stmt = $pdo->prepare('SELECT o.id, o.user_id, o.customer_name, o.customer_phone, o.customer_email, o.status_id, o.delivery_method_id, o.payment_method, o.total_price, o.delivery_price, o.discount_total, o.address, o.payment_status, o.payment_id, o.paid_at, o.created_at, o.updated_at, os.name AS status_name, u.name AS user_name, u.email AS user_email FROM orders o LEFT JOIN order_statuses os ON os.id = o.status_id LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ? LIMIT 1');
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) admin_redirect('/admin/orders.php');

$stmtItems = $pdo->prepare('SELECT oi.*, p.name AS product_name FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? ORDER BY oi.id ASC');
$stmtItems->execute([$orderId]);
$items = $stmtItems->fetchAll() ?: [];

$statusMap = admin_order_status_map();
$currentStatusSlug = admin_order_status_slug_by_id((int)$order['status_id']);
$customerEmail = (string)($order['customer_email'] ?: $order['user_email'] ?: '');

admin_render_header('Заказ #' . $orderId, 'orders');
?>
<section class="admin-form-wrap">
    <div class="admin-section-head">
        <h2>Заказ #<?php echo $orderId; ?></h2>
        <a class="admin-link-btn" href="/admin/orders.php">К списку</a>
    </div>
    <div class="admin-grid-2">
        <article class="admin-panel">
            <h3>Информация</h3>
            <p><strong>Дата:</strong> <?php echo date('d.m.Y H:i', strtotime((string)$order['created_at'])); ?></p>
            <p><strong>Сумма:</strong> <?php echo number_format((float)$order['total_price'], 2, '.', ' '); ?></p>
            <p><strong>Адрес:</strong> <?php echo admin_e((string)$order['address']); ?></p>
            <div style="margin-top:10px">
                <strong>Статус:</strong>
                <form method="post" style="display:inline">
                    <?php echo admin_csrf_input(); ?>
                    <select name="status_slug" onchange="this.form.submit()">
                        <?php foreach ($statusMap as $slug => $st): ?>
                            <option value="<?php echo $slug; ?>" <?php echo $slug === $currentStatusSlug ? 'selected' : ''; ?>><?php echo $st['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </article>
        <article class="admin-panel">
            <h3>Покупатель</h3>
            <p><strong>Имя:</strong> <?php echo admin_e((string)($order['customer_name'] ?: $order['user_name'] ?: 'Гость')); ?></p>
            <p><strong>Email:</strong> <a href="#" id="show-reply-form" class="admin-link-btn"><?php echo admin_e($customerEmail); ?></a></p>
            <div id="reply-panel" class="admin-reply-box">
                <form action="mailto:<?php echo $customerEmail; ?>" method="GET" class="admin-form">
                    <textarea name="body" rows="3" placeholder="Текст сообщения..."></textarea>
                    <div class="admin-actions">
                        <button type="submit">Написать</button>
                        <button type="button" id="hide-reply-form" class="admin-text-btn danger">Отмена</button>
                    </div>
                </form>
            </div>
        </article>
    </div>
</section>

<section class="admin-table-wrap">
    <h3>Товары</h3>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Товар</th>
                <th>Кол-во</th>
                <th>Цена</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo admin_e((string)($item['title'] ?: $item['product_name'] ?: 'Удален')); ?></td>
                    <td><?php echo (int)$item['quantity']; ?></td>
                    <td><?php echo number_format((float)$item['price'], 2, '.', ' '); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<script src="/admin/assets/feedback.js"></script>
<?php admin_render_footer(); ?>