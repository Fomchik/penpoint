<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../includes/product_options.php';
require_once __DIR__ . '/../includes/product_characteristics.php';
require_once __DIR__ . '/includes/product_attribute_catalog.php';

admin_require_auth();

global $pdo;
// Оставляем вызовы инициализации схем, как в вашем исходнике
product_options_ensure_schema($pdo);
product_characteristics_ensure_schema($pdo);

/**
 * Удаление физического файла с сервера.
 */
function admin_try_delete_public_file(string $publicPath): void
{
    $publicPath = trim($publicPath);
    if ($publicPath === '' || $publicPath[0] !== '/') {
        return;
    }

    $absolutePath = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\') . $publicPath;

    if ($absolutePath !== '' && is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

$productId = admin_safe_int($_GET['id'] ?? 0);
if ($productId <= 0) {
    admin_set_flash('error', 'Некорректный ID товара.');
    admin_redirect('/admin/products.php');
}

$categories = [];
$categorySlugs = [];
$errors = [];

// Загрузка категорий
try {
    $stmt = $pdo->query('SELECT id, name, slug FROM categories ORDER BY name ASC');
    $categories = $stmt->fetchAll() ?: [];
    foreach ($categories as $category) {
        $categorySlugs[(int)$category['id']] = trim((string)($category['slug'] ?? ''));
    }
} catch (Throwable $e) {
    admin_log_error('product_edit_categories', $e);
}

// Загрузка данных товара
try {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.category_id, p.name, p.description, p.price, p.stock_quantity, p.pickup_available,
                (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_path
         FROM products p
         WHERE p.id = ? LIMIT 1'
    );
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!is_array($product)) {
        admin_set_flash('error', 'Товар не найден.');
        admin_redirect('/admin/products.php');
    }
} catch (Throwable $e) {
    admin_log_error('product_edit_fetch', $e);
    admin_set_flash('error', 'Не удалось загрузить товар.');
    admin_redirect('/admin/products.php');
}

$form = [
    'name' => (string)$product['name'],
    'description' => (string)$product['description'],
    'price' => (string)$product['price'],
    'stock_quantity' => (string)$product['stock_quantity'],
    'category_id' => (string)$product['category_id'],
    'pickup_available' => ((int)($product['pickup_available'] ?? 1) === 1) ? '1' : '0',
];
$currentImage = (string)($product['image_path'] ?? '');
$variantPayload = product_admin_form_payload($productId);
$attributeCatalog = admin_attribute_catalog_all($pdo);
$characteristics = product_characteristics_fetch($pdo, $productId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();

    $form['name'] = trim((string)($_POST['name'] ?? ''));
    $form['description'] = trim((string)($_POST['description'] ?? ''));
    $form['price'] = trim((string)($_POST['price'] ?? ''));
    $form['stock_quantity'] = trim((string)($_POST['stock_quantity'] ?? ''));
    $form['category_id'] = trim((string)($_POST['category_id'] ?? ''));
    $form['pickup_available'] = isset($_POST['pickup_available']) ? '1' : '0';

    $categoryId = admin_safe_int($form['category_id'], 0);
    $price = (float)str_replace(',', '.', $form['price']);
    $stock = admin_safe_int($form['stock_quantity'], -1);
    $pickupAvailable = $form['pickup_available'] === '1' ? 1 : 0;

    if ($form['name'] === '') $errors[] = 'Название обязательно.';
    if ($form['description'] === '') $errors[] = 'Описание обязательно.';
    if ($price <= 0) $errors[] = 'Цена должна быть больше 0.';
    if ($stock < 0) $errors[] = 'Остаток должен быть 0 или больше.';
    if ($categoryId <= 0) $errors[] = 'Выберите категорию.';

    $newImagePath = null;
    if (!$errors && !empty($_FILES['image']['name'])) {
        try {
            $categorySlug = $categorySlugs[$categoryId] ?? 'misc';
            $newImagePath = admin_handle_image_upload($_FILES['image'], [
                'target' => 'product_images',
                'sub_path' => $categorySlug . '/' . date('Y/m'),
                'prefix' => (string)($form['name'] !== '' ? $form['name'] : 'product'),
            ]);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // 1. Обновляем основные данные товара
            $stmtUpdate = $pdo->prepare(
                'UPDATE products
                 SET category_id = ?, name = ?, description = ?, price = ?, stock_quantity = ?, pickup_available = ?
                 WHERE id = ? LIMIT 1'
            );
            $stmtUpdate->execute([$categoryId, $form['name'], $form['description'], $price, $stock, $pickupAvailable, $productId]);

            // 2. Обработка изображения (Доработано)
            if ($newImagePath !== null) {
                // Получаем запись о текущем главном фото
                $stmtExisting = $pdo->prepare('SELECT id, image_path FROM product_images WHERE product_id = ? ORDER BY id ASC LIMIT 1');
                $stmtExisting->execute([$productId]);
                $existingImageRow = $stmtExisting->fetch();

                if ($existingImageRow) {
                    $oldPath = (string)$existingImageRow['image_path'];
                    $imageId = (int)$existingImageRow['id'];

                    // Обновляем путь в БД на новый
                    $stmtImage = $pdo->prepare('UPDATE product_images SET image_path = ? WHERE id = ? LIMIT 1');
                    $stmtImage->execute([$newImagePath, $imageId]);

                    // Удаляем старый физический файл, если пути реально изменились
                    if ($oldPath !== '' && $oldPath !== $newImagePath) {
                        admin_try_delete_public_file($oldPath);
                    }
                } else {
                    // Если фото не было — создаем новую запись
                    $stmtImage = $pdo->prepare('INSERT INTO product_images (product_id, image_path) VALUES (?, ?)');
                    $stmtImage->execute([$productId, $newImagePath]);
                }
                $currentImage = $newImagePath;
            }

            // 3. Сохранение связанных данных (Параметры, Варианты, Характеристики)
            product_save_parameters($pdo, $productId, (array)($_POST['product_parameters'] ?? []), $_FILES['product_parameters'] ?? []);
            product_save_variants($pdo, $productId, (array)($_POST['product_variants'] ?? []));
            product_characteristics_save($pdo, $productId, (array)($_POST['product_characteristics'] ?? []));

            $pdo->commit();
            admin_set_flash('success', 'Товар обновлён.');
            admin_redirect('/admin/products.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            admin_log_error('product_edit', $e);
            $errors[] = 'Не удалось обновить товар: ' . $e->getMessage();
        }
    }
}

admin_render_header('Редактирование товара', 'products');
?>

<section class="admin-form-wrap">
    <div class="admin-section-head">
        <h2>Товар #<?php echo admin_e((string)$productId); ?></h2>
        <a class="admin-link-btn" href="/admin/products.php">К списку</a>
    </div>

    <?php if ($errors): ?>
        <div class="admin-alert admin-alert--error">
            <?php echo admin_e(implode(' ', $errors)); ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="admin-form admin-form-grid">
        <?php echo admin_csrf_input(); ?>

        <label>
            Название
            <input type="text" name="name" value="<?php echo admin_e($form['name']); ?>" required>
        </label>

        <label>
            Категория
            <select name="category_id" required>
                <option value="">Выберите категорию</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo admin_e((string)$category['id']); ?>"
                        <?php echo ((string)$category['id'] === $form['category_id']) ? 'selected' : ''; ?>>
                        <?php echo admin_e((string)$category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="admin-full">
            Описание
            <textarea name="description" rows="5" required><?php echo admin_e($form['description']); ?></textarea>
        </label>
        <label class="admin-full" style="display:flex; align-items:center; gap:10px;">
            <input type="checkbox" name="pickup_available" value="1" <?php echo $form['pickup_available'] === '1' ? 'checked' : ''; ?>>
            <span>Доступен для самовывоза сегодня</span>
        </label>

        <label>
            Цена
            <input type="number" name="price" value="<?php echo admin_e($form['price']); ?>" step="0.01" min="0.01" required>
        </label>

        <label>
            Остаток
            <input type="number" name="stock_quantity" value="<?php echo admin_e($form['stock_quantity']); ?>" min="0" required>
            <small style="color:#6b7280;">Базовый остаток товара. Используется только если для параметров/вариантов не задан собственный остаток.</small>
        </label>

        <label class="admin-full">
            Новое изображение (JPG, PNG, WEBP)
            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
            <?php if ($currentImage !== ''): ?>
                <div class="admin-edit-img-preview">
                    <p>Текущее изображение:</p>
                    <img class="admin-thumb admin-thumb-large" src="<?php echo admin_e($currentImage); ?>" alt="">
                </div>
            <?php endif; ?>
        </label>

        <div class="admin-variants"
            data-parameter-catalog="<?php echo admin_e(json_encode(array_map(static function (array $item): string {
                                        return (string)$item['name'];
                                    }, $attributeCatalog), JSON_UNESCAPED_UNICODE)); ?>"
            data-initial-parameters="<?php echo admin_e(json_encode($variantPayload['parameters'], JSON_UNESCAPED_UNICODE)); ?>"
            data-initial-variants="<?php echo admin_e(json_encode($variantPayload['variants'], JSON_UNESCAPED_UNICODE)); ?>"
            data-csrf-token="<?php echo admin_e(admin_csrf_token()); ?>">
            <div>
                <h3 class="admin-variants__section-title">Параметры товара</h3>
                <button type="button" class="admin-text-btn" id="add-parameter" style="margin-bottom: 20px;">+ Добавить параметр</button>
                <div class="admin-param-grid" id="parameters-container" data-parameters-container></div>
                <p class="admin-variants__hint">Добавьте параметры товара (например, Цвет) и их значения с изображениями, ценами и остатками.</p>
            </div>
        </div>

        <div class="admin-full admin-characteristics" data-characteristics
            data-initial-characteristics="<?php echo admin_e(json_encode($characteristics, JSON_UNESCAPED_UNICODE)); ?>">
            <h3 class="admin-variants__section-title">Характеристики товара</h3>
            <div class="admin-characteristics__list" data-characteristics-list></div>
            <button type="button" class="admin-text-btn" data-characteristics-add>Добавить характеристику</button>
        </div>

        <button type="submit" class="admin-save-btn">Сохранить изменения</button>
    </form>
</section>

<script src="/admin/assets/product-variants.js"></script>
<script src="/admin/assets/product-characteristics.js" defer></script>

<?php admin_render_footer(); ?>
