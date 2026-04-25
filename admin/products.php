<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

admin_require_auth();

global $pdo;

function admin_try_delete_public_file(string $publicPath): void
{
    $publicPath = trim($publicPath);
    if ($publicPath === '' || $publicPath[0] !== '/') {
        return;
    }
    $absolutePath = admin_public_path_to_absolute($publicPath);

    if ($absolutePath !== '' && is_file($absolutePath)) {

        if (!@unlink($absolutePath)) {
            error_log("РќРµ СѓРґР°Р»РѕСЃСЊ СѓРґР°Р»РёС‚СЊ С„Р°Р№Р»: " . $absolutePath);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'delete') {
        $productId = admin_safe_int($_POST['product_id'] ?? 0);

        if ($productId <= 0) {
            admin_set_flash('error', 'Товар не найден.');
            admin_redirect('/admin/products.php');
        }

        try {
            $categorySlug = 'misc';
            $stmtCategory = $pdo->prepare(
                'SELECT c.slug
                FROM products p
                LEFT JOIN product_categories pc ON pc.product_id = p.id
                LEFT JOIN categories c ON c.id = pc.category_id
                WHERE p.id = ? LIMIT 1'
            );
            $stmtCategory->execute([$productId]);
            $slug = trim((string)($stmtCategory->fetchColumn() ?: ''));
            if ($slug !== '') {
                $categorySlug = $slug;
            }

            $stmtPaths = $pdo->prepare(
                'SELECT image_path FROM product_images WHERE product_id = ?
                 UNION ALL
                 SELECT image_path FROM product_variant_images WHERE product_id = ?'
            );
            $stmtPaths->execute([$productId, $productId]);
            $paths = $stmtPaths->fetchAll(PDO::FETCH_COLUMN) ?: [];

            foreach ($paths as $path) {
                admin_try_delete_public_file((string)$path);
            }

            $pdo->beginTransaction();

            $pdo->prepare('DELETE FROM product_variant_images WHERE product_id = ?')->execute([$productId]);
            $pdo->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$productId]);
            $pdo->prepare('DELETE FROM product_variants WHERE product_id = ?')->execute([$productId]);
            $pdo->prepare('DELETE FROM product_parameters WHERE product_id = ?')->execute([$productId]);

            $stmtProduct = $pdo->prepare('DELETE FROM products WHERE id = ? LIMIT 1');
            $stmtProduct->execute([$productId]);

            if ($stmtProduct->rowCount() !== 1) {
                throw new RuntimeException('Товар не найден в базе данных.');
            }

            $pdo->commit();
            $productDir = admin_public_path_to_absolute('/assets/product_images/' . $categorySlug . '/' . $productId);
            admin_remove_dir_recursive($productDir);
            admin_set_flash('success', 'Товар и связанные файлы успешно удалены.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            admin_log_error('products_delete', $e);
            admin_set_flash('error', 'Ошибки при удалении: ' . $e->getMessage());
        }

        admin_redirect('/admin/products.php');
    }
}

$products = [];
$search = trim((string)($_GET['q'] ?? ''));

try {
    $sql = 'SELECT p.id, p.name, p.price, p.stock_quantity, p.updated_at,
                   (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_path
            FROM products p';

    if ($search !== '') {
        $searchLike = '%' . $search . '%';
        $searchId = ctype_digit($search) ? (int)$search : 0;
        $stmt = $pdo->prepare($sql . ' WHERE p.id = ? OR p.name LIKE ? OR p.description LIKE ? ORDER BY p.id DESC');
        $stmt->execute([$searchId, $searchLike, $searchLike]);
    } else {
        $stmt = $pdo->query($sql . ' ORDER BY p.id DESC');
    }
    $products = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    admin_log_error('products_list', $e);
    admin_set_flash('error', 'Ошибки при загрузке списка товаров.');
}

admin_render_header('Редактирование товаров', 'products');
?>

<section class="admin-table-wrap">
    <div class="admin-section-head">
        <h2>Управление товарами</h2>
        <a class="admin-link-btn" href="/admin/product_create.php">Добавить товар</a>
    </div>

    <form method="get" class="admin-form-inline">
        <label>
            Название
            <input type="text" name="q" value="<?php echo admin_e($search); ?>" placeholder="ID, название или описание">
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
                <tr>
                    <td colspan="7">Товары не найдены.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo admin_e((string)$product['id']); ?></td>
                        <td>
                            <?php if (!empty($product['image_path'])): ?>
                                <img class="admin-thumb" src="<?php echo admin_e((string)$product['image_path']); ?>" alt="" width="50">
                            <?php else: ?>
                                <span>Нет изображения</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo admin_e((string)$product['name']); ?></td>
                        <td><?php echo admin_e(number_format((float)$product['price'], 2, '.', ' ')); ?></td>
                        <td><?php echo admin_e((string)$product['stock_quantity']); ?></td>
                        <td><?php echo admin_e(date('d.m.Y H:i', strtotime((string)$product['updated_at']))); ?></td>
                        <td class="admin-actions">
                            <a href="/admin/product_edit.php?id=<?php echo admin_e((string)$product['id']); ?>">Редактировать</a>
                            <form method="post" onsubmit="return confirm('Вы уверены, что хотите удалить этот товар и все связанные файлы?');" style="display:inline;">
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