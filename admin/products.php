<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

admin_require_auth();

global $pdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'delete') {
        $productId = admin_safe_int($_POST['product_id'] ?? 0);

        if ($productId <= 0) {
            admin_set_flash('error', 'Некорректный ID товара.');
            admin_redirect('/admin/products.php');
        }

        try {
            $pdo->beginTransaction();
            $stmtImages = $pdo->prepare('DELETE FROM product_images WHERE product_id = ?');
            $stmtImages->execute([$productId]);

            $stmtProduct = $pdo->prepare('DELETE FROM products WHERE id = ? LIMIT 1');
            $stmtProduct->execute([$productId]);

            if ($stmtProduct->rowCount() !== 1) {
                throw new RuntimeException('Товар не найден.');
            }

            $pdo->commit();
            admin_set_flash('success', 'Товар удалён.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            admin_log_error('products_delete', $e);
            admin_set_flash('error', 'Не удалось удалить товар. Возможно, он есть в заказах.');
        }

        admin_redirect('/admin/products.php');
    }
}

$products = [];
$search = trim((string)($_GET['q'] ?? ''));

try {
    if ($search !== '') {
        $searchLike = '%' . $search . '%';
        $searchId = ctype_digit($search) ? (int)$search : 0;
        $stmt = $pdo->prepare(
            'SELECT p.id, p.name, p.price, p.stock_quantity, p.updated_at,
                    (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_path
             FROM products p
             WHERE p.id = ? OR p.name LIKE ? OR p.description LIKE ?
             ORDER BY p.id DESC'
        );
        $stmt->execute([$searchId, $searchLike, $searchLike]);
    } else {
        $stmt = $pdo->query(
            'SELECT p.id, p.name, p.price, p.stock_quantity, p.updated_at,
                    (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_path
             FROM products p
             ORDER BY p.id DESC'
        );
    }
    $products = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    admin_log_error('products_list', $e);
    admin_set_flash('error', 'Не удалось загрузить список товаров.');
}

admin_render_header('Товары', 'products');
?>
<section class="admin-table-wrap">
    <div class="admin-section-head">
        <h2>Управление товарами</h2>
        <a class="admin-link-btn" href="/admin/product_create.php">Добавить товар</a>
    </div>
    <form method="get" class="admin-form-inline">
        <label>
            Поиск
            <input type="text" name="q" value="<?php echo admin_e($search); ?>" placeholder="ID, название, описание">
        </label>
        <button type="submit">Найти</button>
        <?php if ($search !== ''): ?>
            <a class="admin-link-btn" href="/admin/products.php">Сбросить</a>
        <?php endif; ?>
    </form>
    <table class="admin-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Изображение</th>
            <th>Название</th>
            <th>Цена</th>
            <th>Остаток</th>
            <th>Обновлён</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$products): ?>
            <tr><td colspan="7">Товары не найдены.</td></tr>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo admin_e((string)$product['id']); ?></td>
                    <td>
                        <?php if (!empty($product['image_path'])): ?>
                            <img class="admin-thumb" src="<?php echo admin_e((string)$product['image_path']); ?>" alt="">
                        <?php else: ?>
                            <span>—</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo admin_e((string)$product['name']); ?></td>
                    <td><?php echo admin_e(number_format((float)$product['price'], 2, '.', ' ')); ?></td>
                    <td><?php echo admin_e((string)$product['stock_quantity']); ?></td>
                    <td><?php echo admin_e(date('d.m.Y H:i', strtotime((string)$product['updated_at']))); ?></td>
                    <td class="admin-actions">
                        <a href="/admin/product_edit.php?id=<?php echo admin_e((string)$product['id']); ?>">Редактировать</a>
                        <form method="post" onsubmit="return confirm('Удалить товар?');">
                            <?php echo admin_csrf_input(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="product_id" value="<?php echo admin_e((string)$product['id']); ?>">
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
