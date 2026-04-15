<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/promotions_tools.php';

admin_require_auth();
admin_promotion_ensure_schema($pdo);

$promotionId = admin_safe_int($_GET['id'] ?? 0);
if ($promotionId <= 0) {
    admin_set_flash('error', 'Некорректный ID акции.');
    admin_redirect('/admin/promotions.php');
}

$promotion = admin_promotion_fetch_one($pdo, $promotionId);
if (!$promotion) {
    admin_set_flash('error', 'Акция не найдена.');
    admin_redirect('/admin/promotions.php');
}

$errors = [];
$scopeLabels = admin_promotion_scope_labels();
$typeLabels = admin_promotion_type_labels();
$categories = admin_promotion_fetch_categories($pdo);
$products = admin_promotion_fetch_products($pdo);

$form = [
    'title' => (string)$promotion['title'],
    'short_text' => (string)$promotion['short_text'],
    'discount_percent' => (string)$promotion['discount_percent'],
    'apply_scope' => (string)$promotion['apply_scope'],
    'promotion_type' => (string)($promotion['promotion_type'] ?: 'regular'),
    'date_start' => (string)$promotion['date_start'],
    'date_end' => (string)($promotion['date_end'] ?? ''),
];

$selectedCategories = admin_promotion_fetch_links($pdo, $promotionId, 'categories');
$selectedProducts = admin_promotion_fetch_links($pdo, $promotionId, 'products');
$currentImage = (string)($promotion['image_path'] ?? '');
$currentImageMain = (string)($promotion['image_main'] ?? '');
$currentImageList = (string)($promotion['image_list'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();

    $form['title'] = trim((string)($_POST['title'] ?? ''));
    $form['short_text'] = trim((string)($_POST['short_text'] ?? ''));
    $form['discount_percent'] = trim((string)($_POST['discount_percent'] ?? ''));
    $form['apply_scope'] = (string)($_POST['apply_scope'] ?? 'categories');
    $form['promotion_type'] = (string)($_POST['promotion_type'] ?? 'regular');
    $form['date_start'] = trim((string)($_POST['date_start'] ?? ''));
    $form['date_end'] = trim((string)($_POST['date_end'] ?? ''));
    $selectedCategories = admin_promotion_parse_ids($_POST['category_ids'] ?? []);
    $selectedProducts = admin_promotion_parse_ids($_POST['product_ids'] ?? []);

    if ($form['title'] === '') $errors[] = 'Укажите название акции.';
    if ($form['short_text'] === '') $errors[] = 'Укажите описание акции.';
    if (!isset($scopeLabels[$form['apply_scope']])) $errors[] = 'Некорректная область применения.';
    if (!isset($typeLabels[$form['promotion_type']])) $errors[] = 'Некорректный тип акции.';

    $discountPercent = (int)$form['discount_percent'];
    if ($discountPercent < 1 || $discountPercent > 90) $errors[] = 'Скидка должна быть от 1 до 90.';
    if ($form['date_start'] === '') $errors[] = 'Укажите дату начала.';
    if ($form['apply_scope'] === 'categories' && $selectedCategories === []) $errors[] = 'Выберите категории.';
    if ($form['apply_scope'] === 'products' && $selectedProducts === []) $errors[] = 'Выберите товары.';

    $newImage = $currentImage;
    $newImageMain = $currentImageMain;
    $newImageList = $currentImageList;

    if ($errors === []) {
        try {
            if ($form['promotion_type'] === 'seasonal') {
                $uploadedMain = admin_handle_image_upload($_FILES['image_main'] ?? []);
                $uploadedList = admin_handle_image_upload($_FILES['image_list'] ?? []);
                if ($uploadedMain !== null) $newImageMain = $uploadedMain;
                if ($uploadedList !== null) $newImageList = $uploadedList;
                if ($newImageMain === '' || $newImageList === '') {
                    throw new RuntimeException('Для seasonal акции нужны оба изображения.');
                }
                $newImage = null;
            } else {
                $uploadedImage = admin_handle_image_upload($_FILES['image'] ?? []);
                if ($uploadedImage !== null) $newImage = $uploadedImage;
                if ($newImage === '') {
                    throw new RuntimeException('Для regular акции нужно изображение.');
                }
                $newImageMain = null;
                $newImageList = null;
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'UPDATE promotions
                 SET title = ?, short_text = ?, image_path = ?, image_main = ?, image_list = ?, promotion_type = ?,
                     date_start = ?, date_end = ?, discount_percent = ?, apply_scope = ?
                 WHERE id = ? LIMIT 1'
            );
            $stmt->execute([
                $form['title'],
                $form['short_text'],
                $newImage,
                $newImageMain,
                $newImageList,
                $form['promotion_type'],
                $form['date_start'],
                $form['date_end'] !== '' ? $form['date_end'] : null,
                $discountPercent,
                $form['apply_scope'],
                $promotionId,
            ]);
            admin_promotion_sync_links($pdo, $promotionId, $form['apply_scope'], $form['apply_scope'] === 'products' ? $selectedProducts : $selectedCategories);
            $pdo->commit();

            admin_set_flash('success', 'Акция обновлена.');
            admin_redirect('/admin/promotions.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            admin_log_error('promotion_edit', $e);
            $errors[] = 'Не удалось обновить акцию.';
        }
    }
}

admin_render_header('Редактирование акции', 'promotions');
?>
<section class="admin-form-wrap">
    <div class="admin-section-head">
        <h2>Акция #<?php echo admin_e((string)$promotionId); ?></h2>
        <a class="admin-link-btn" href="/admin/promotions.php">К списку</a>
    </div>

    <?php if ($errors): ?>
        <div class="admin-alert admin-alert--error"><?php echo admin_e(implode(' ', $errors)); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="admin-form admin-form-grid">
        <?php echo admin_csrf_input(); ?>
        <label class="admin-full">Название акции
            <input type="text" name="title" value="<?php echo admin_e($form['title']); ?>" required>
        </label>
        <label class="admin-full">Описание акции
            <textarea name="short_text" rows="4" required><?php echo admin_e($form['short_text']); ?></textarea>
        </label>
        <label>Размер скидки (%)
            <input type="number" name="discount_percent" value="<?php echo admin_e($form['discount_percent']); ?>" min="1" max="90" required>
        </label>
        <label>Тип акции
            <select name="promotion_type" id="promotion-type">
                <?php foreach ($typeLabels as $typeKey => $typeLabel): ?>
                    <option value="<?php echo admin_e($typeKey); ?>" <?php echo $form['promotion_type'] === $typeKey ? 'selected' : ''; ?>><?php echo admin_e($typeLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Область применения
            <select name="apply_scope" id="promotion-scope">
                <?php foreach ($scopeLabels as $scopeKey => $scopeLabel): ?>
                    <option value="<?php echo admin_e($scopeKey); ?>" <?php echo $form['apply_scope'] === $scopeKey ? 'selected' : ''; ?>><?php echo admin_e($scopeLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Дата начала
            <input type="date" name="date_start" value="<?php echo admin_e($form['date_start']); ?>" required>
        </label>
        <label>Дата окончания
            <input type="date" name="date_end" value="<?php echo admin_e($form['date_end']); ?>">
        </label>

        <label class="admin-full" id="regular-image-field">Изображение regular акции
            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
            <?php if ($currentImage !== ''): ?><img class="admin-thumb admin-thumb-large" src="<?php echo admin_e($currentImage); ?>" alt=""><?php endif; ?>
        </label>
        <label class="admin-full" id="seasonal-main-field">Главное изображение seasonal акции
            <input type="file" name="image_main" accept=".jpg,.jpeg,.png,.webp">
            <?php if ($currentImageMain !== ''): ?><img class="admin-thumb admin-thumb-large" src="<?php echo admin_e($currentImageMain); ?>" alt=""><?php endif; ?>
        </label>
        <label class="admin-full" id="seasonal-list-field">Изображение для списка seasonal акции
            <input type="file" name="image_list" accept=".jpg,.jpeg,.png,.webp">
            <?php if ($currentImageList !== ''): ?><img class="admin-thumb admin-thumb-large" src="<?php echo admin_e($currentImageList); ?>" alt=""><?php endif; ?>
        </label>

        <div class="admin-full admin-list__box" id="categories-field">
            <span class="admin-field-title">Категории</span>
            <input type="text" class="admin-list__search" data-list-search placeholder="Поиск категорий">
            <div class="list admin-list__grid" data-multi-picker>
                <?php foreach ($categories as $category): ?>
                    <?php $isSelected = in_array((int)$category['id'], $selectedCategories, true); ?>
                    <div class="list-item admin-list__item <?php echo $isSelected ? 'active' : ''; ?>" data-picker-option data-input-id="promotion-category-<?php echo admin_e((string)$category['id']); ?>" data-filter-text="<?php echo admin_e(mb_strtolower((string)$category['name'])); ?>">
                        <span class="admin-list__item-text">
                            <span><?php echo admin_e((string)$category['name']); ?></span>
                            <span class="admin-list__item-note">Категория</span>
                        </span>
                    </div>
                    <input type="checkbox" class="admin-picker__input" id="promotion-category-<?php echo admin_e((string)$category['id']); ?>" name="category_ids[]" value="<?php echo admin_e((string)$category['id']); ?>" <?php echo $isSelected ? 'checked' : ''; ?>>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="admin-full admin-list__box" id="products-field">
            <span class="admin-field-title">Товары</span>
            <input type="text" class="admin-list__search" data-list-search placeholder="Поиск товаров">
            <div class="list admin-list__grid" data-multi-picker>
                <?php foreach ($products as $product): ?>
                    <?php $isSelected = in_array((int)$product['id'], $selectedProducts, true); ?>
                    <div class="list-item admin-list__item <?php echo $isSelected ? 'active' : ''; ?>" data-picker-option data-input-id="promotion-product-<?php echo admin_e((string)$product['id']); ?>" data-filter-text="<?php echo admin_e(mb_strtolower((string)$product['name'] . ' #' . (string)$product['id'])); ?>">
                        <span class="admin-list__item-text">
                            <span>#<?php echo admin_e((string)$product['id']); ?> · <?php echo admin_e((string)$product['name']); ?></span>
                            <span class="admin-list__item-note">Товар</span>
                        </span>
                    </div>
                    <input type="checkbox" class="admin-picker__input" id="promotion-product-<?php echo admin_e((string)$product['id']); ?>" name="product_ids[]" value="<?php echo admin_e((string)$product['id']); ?>" <?php echo $isSelected ? 'checked' : ''; ?>>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit">Сохранить изменения</button>
    </form>
</section>
<script>
(function () {
    const scope = document.getElementById('promotion-scope');
    const type = document.getElementById('promotion-type');
    const categories = document.getElementById('categories-field');
    const products = document.getElementById('products-field');
    const regular = document.getElementById('regular-image-field');
    const seasonalMain = document.getElementById('seasonal-main-field');
    const seasonalList = document.getElementById('seasonal-list-field');

    function refresh() {
        if (categories) categories.style.display = scope.value === 'categories' ? 'grid' : 'none';
        if (products) products.style.display = scope.value === 'products' ? 'grid' : 'none';
        const seasonal = type.value === 'seasonal';
        if (regular) regular.style.display = seasonal ? 'none' : 'grid';
        if (seasonalMain) seasonalMain.style.display = seasonal ? 'grid' : 'none';
        if (seasonalList) seasonalList.style.display = seasonal ? 'grid' : 'none';
    }

    scope.addEventListener('change', refresh);
    type.addEventListener('change', refresh);
    refresh();

    document.querySelectorAll('[data-multi-picker]').forEach(function (picker) {
        picker.querySelectorAll('[data-picker-option]').forEach(function (item) {
            item.addEventListener('click', function () {
                const input = document.getElementById(item.getAttribute('data-input-id'));
                if (!input) {
                    return;
                }
                input.checked = !input.checked;
                item.classList.toggle('active', input.checked);
            });
        });
    });

    document.querySelectorAll('[data-list-search]').forEach(function (input) {
        input.addEventListener('input', function () {
            const query = String(input.value || '').trim().toLowerCase();
            const field = input.closest('.admin-list__box');
            if (!field) {
                return;
            }
            field.querySelectorAll('[data-picker-option]').forEach(function (item) {
                const text = String(item.getAttribute('data-filter-text') || '').toLowerCase();
                item.style.display = query === '' || text.indexOf(query) !== -1 ? '' : 'none';
            });
        });
    });
})();
</script>
<?php admin_render_footer(); ?>
