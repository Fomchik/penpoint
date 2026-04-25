<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../includes/product_options.php';
require_once __DIR__ . '/../includes/product_characteristics.php';
require_once __DIR__ . '/includes/product_attribute_catalog.php';

admin_require_auth();

global $pdo;
product_options_ensure_schema($pdo);
product_characteristics_ensure_schema($pdo);
product_category_links_ensure_schema($pdo);

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

try {
    $categories = get_categories();
    $categories = array_values($categories);
    usort($categories, static fn (array $a, array $b): int => strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
    foreach ($categories as $category) {
        $categorySlugs[(int)$category['id']] = trim((string)($category['slug'] ?? ''));
    }
    admin_ensure_category_image_dirs($pdo);
} catch (Throwable $e) {
    admin_log_error('product_edit_categories', $e);
}

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
    'category_ids' => [],
    'pickup_available' => ((int)($product['pickup_available'] ?? 1) === 1) ? '1' : '0',
];
$form['category_ids'] = product_fetch_category_ids($pdo, $productId);
if ($form['category_ids'] === []) {
    $fallbackCategoryId = (int)$form['category_id'];
    if ($fallbackCategoryId > 0) {
        $form['category_ids'] = [$fallbackCategoryId];
    }
}
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
    $form['category_ids'] = product_normalize_category_ids((array)($_POST['category_ids'] ?? []));
    $form['pickup_available'] = isset($_POST['pickup_available']) ? '1' : '0';

    if ($form['category_ids'] === [] && $form['category_id'] !== '') {
        $form['category_ids'] = product_normalize_category_ids([(int)$form['category_id']]);
    }
    if ($form['category_ids'] !== []) {
        $allowedCategoryIds = array_map('intval', array_keys($categorySlugs));
        $form['category_ids'] = array_values(array_filter(
            $form['category_ids'],
            static fn (int $id): bool => in_array($id, $allowedCategoryIds, true)
        ));
    }
    if ($form['category_ids'] !== []) {
        $form['category_id'] = (string)$form['category_ids'][0];
    }

    $categoryId = admin_safe_int($form['category_id'], 0);
    $price = (float)str_replace(',', '.', $form['price']);
    $stock = admin_safe_int($form['stock_quantity'], -1);
    $pickupAvailable = $form['pickup_available'] === '1' ? 1 : 0;

    if ($form['name'] === '') $errors[] = 'Название обязательно.';
    if ($form['description'] === '') $errors[] = 'Описание обязательно.';
    if ($price <= 0) $errors[] = 'Цена должна быть положительной.';
    if ($stock < 0) $errors[] = 'Остаток должен быть 0 или больше.';
    if ($categoryId <= 0) $errors[] = 'Выберите категорию.';

    $newImagePath = null;
    if (!$errors && !empty($_FILES['image']['name'])) {
        try {
            $categorySlug = $categorySlugs[$categoryId] ?? 'misc';
            $newImagePath = admin_handle_image_upload($_FILES['image'], [
                'target' => 'product_images',
                'sub_path' => $categorySlug . '/' . $productId,
                'file_name' => 'main',
            ]);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmtUpdate = $pdo->prepare(
                'UPDATE products
                 SET name = ?, description = ?, price = ?, stock_quantity = ?, pickup_available = ?
                 WHERE id = ? LIMIT 1'
            );
            $stmtUpdate->execute([$categoryId, $form['name'], $form['description'], $price, $stock, $pickupAvailable, $productId]);
            product_sync_categories($pdo, $productId, $form['category_ids']);

            if ($newImagePath !== null) {
                $stmtExisting = $pdo->prepare('SELECT id, image_path FROM product_images WHERE product_id = ? ORDER BY id ASC LIMIT 1');
                $stmtExisting->execute([$productId]);
                $existingImageRow = $stmtExisting->fetch();

                if ($existingImageRow) {
                    $oldPath = (string)$existingImageRow['image_path'];
                    $imageId = (int)$existingImageRow['id'];

                    $stmtImage = $pdo->prepare('UPDATE product_images SET image_path = ? WHERE id = ? LIMIT 1');
                    $stmtImage->execute([$newImagePath, $imageId]);

                    if ($oldPath !== '' && $oldPath !== $newImagePath) {
                        admin_try_delete_public_file($oldPath);
                    }
                } else {
                    $stmtImage = $pdo->prepare('INSERT INTO product_images (product_id, image_path) VALUES (?, ?)');
                    $stmtImage->execute([$productId, $newImagePath]);
                }
                $currentImage = $newImagePath;
            }
            product_save_parameters($pdo, $productId, (array)($_POST['product_parameters'] ?? []), $_FILES['product_parameters'] ?? []);
            product_save_variants($pdo, $productId, (array)($_POST['product_variants'] ?? []));
            product_characteristics_save($pdo, $productId, (array)($_POST['product_characteristics'] ?? []));

            $pdo->commit();
            admin_set_flash('success', 'Товар обновлен.');
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
        <a class="admin-link-btn" href="/admin/products.php">К Списку</a>
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

        <div class="admin-full admin-list__box" id="product-categories-field">
            <span class="admin-field-title">Категории</span>
            <input type="text" class="admin-list__search" id="product-categories-search" placeholder="Поиск категорий">
            <div class="list admin-list__grid" data-product-category-picker>
                <?php $selectedCategoryIds = product_normalize_category_ids((array)($form['category_ids'] ?? [])); ?>
                <?php foreach ($categories as $category): ?>
                    <?php $isSelected = in_array((int)$category['id'], $selectedCategoryIds, true); ?>
                    <div class="list-item admin-list__item <?php echo $isSelected ? 'active' : ''; ?>" data-picker-option data-input-id="product-category-<?php echo admin_e((string)$category['id']); ?>" data-filter-text="<?php echo admin_e(mb_strtolower((string)$category['name'])); ?>">
                        <span class="admin-list__item-text">
                            <span><?php echo admin_e((string)$category['name']); ?></span>
                            <span class="admin-list__item-note">Категория</span>
                        </span>
                    </div>
                    <input type="checkbox" class="admin-picker__input" id="product-category-<?php echo admin_e((string)$category['id']); ?>" name="category_ids[]" value="<?php echo admin_e((string)$category['id']); ?>" <?php echo $isSelected ? 'checked' : ''; ?>>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="category_id" id="primary-category-id" value="<?php echo admin_e($form['category_id']); ?>">
            <small style="color:#6b7280;">
                Можно выбрать несколько категорий. Нажмите на категорию, чтобы выбрать ее, и нажмите повторно, чтобы снять выбор.
                Выбранные категории подсвечиваются, как в акциях.
            </small>
        </div>

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
            <small style="color:#6b7280;">Базовый остаток товара. Используется только если для параметров/вариантов не задан специальный остаток.</small>
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
                <p class="admin-variants__hint">Добавьте параметры товара (например, цвет) и их значения с изображениями, ценами и остатками.</p>
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

<script>
(function () {
    const picker = document.querySelector('[data-product-category-picker]');
    const search = document.getElementById('product-categories-search');
    const primary = document.getElementById('primary-category-id');
    if (!picker || !primary) {
        return;
    }

    function syncPrimaryCategory() {
        const checked = picker.querySelectorAll('.admin-picker__input:checked');
        if (!checked.length) {
            primary.value = '';
            return;
        }
        primary.value = String(checked[0].value || '');
    }

    picker.querySelectorAll('[data-picker-option]').forEach(function (item) {
        item.addEventListener('click', function () {
            const inputId = item.getAttribute('data-input-id');
            const input = inputId ? document.getElementById(inputId) : null;
            if (!input) {
                return;
            }
            input.checked = !input.checked;
            item.classList.toggle('active', input.checked);
            syncPrimaryCategory();
        });
    });

    if (search) {
        search.addEventListener('input', function () {
            const query = String(search.value || '').trim().toLowerCase();
            picker.querySelectorAll('[data-picker-option]').forEach(function (item) {
                const text = String(item.getAttribute('data-filter-text') || '').toLowerCase();
                item.style.display = (query === '' || text.indexOf(query) !== -1) ? '' : 'none';
            });
        });
    }

    syncPrimaryCategory();
})();
</script>
<script src="/admin/assets/product-variants.js"></script>
<script src="/admin/assets/product-characteristics.js" defer></script>

<?php admin_render_footer(); ?>
