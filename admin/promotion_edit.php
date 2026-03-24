<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/promotions_tools.php';

admin_require_auth();

global $pdo;

$promotionId = admin_safe_int($_GET['id'] ?? 0);
if ($promotionId <= 0) {
    admin_set_flash('error', 'Некорректный ID акции.');
    admin_redirect('/admin/promotions.php');
}

$errors = [];
$scopeLabels = admin_promotion_scope_labels();
$categories = [];
$products = [];

try {
    $categories = admin_promotion_fetch_categories($pdo);
    $products = admin_promotion_fetch_products($pdo);
} catch (Throwable $e) {
    admin_log_error('promotion_edit_lists', $e);
    $errors[] = 'Не удалось загрузить справочники товаров и категорий.';
}

try {
    $promotion = admin_promotion_fetch_one($pdo, $promotionId);
    if (!$promotion) {
        admin_set_flash('error', 'Акция не найдена.');
        admin_redirect('/admin/promotions.php');
    }
} catch (Throwable $e) {
    admin_log_error('promotion_edit_fetch', $e);
    admin_set_flash('error', 'Не удалось загрузить акцию.');
    admin_redirect('/admin/promotions.php');
}

$form = [
    'title' => (string)$promotion['title'],
    'short_text' => (string)$promotion['short_text'],
    'discount_percent' => (string)$promotion['discount_percent'],
    'apply_scope' => (string)$promotion['apply_scope'],
    'date_start' => (string)$promotion['date_start'],
    'date_end' => (string)($promotion['date_end'] ?? ''),
];
$currentImage = (string)($promotion['image_path'] ?? '');

$selectedCategories = [];
$selectedProducts = [];
try {
    $selectedCategories = admin_promotion_fetch_links($pdo, $promotionId, 'categories');
    $selectedProducts = admin_promotion_fetch_links($pdo, $promotionId, 'products');
} catch (Throwable $e) {
    admin_log_error('promotion_edit_links_fetch', $e);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();

    $form['title'] = trim((string)($_POST['title'] ?? ''));
    $form['short_text'] = trim((string)($_POST['short_text'] ?? ''));
    $form['discount_percent'] = trim((string)($_POST['discount_percent'] ?? ''));
    $form['apply_scope'] = (string)($_POST['apply_scope'] ?? 'categories');
    $form['date_start'] = trim((string)($_POST['date_start'] ?? ''));
    $form['date_end'] = trim((string)($_POST['date_end'] ?? ''));

    $selectedCategories = admin_promotion_parse_ids($_POST['category_ids'] ?? []);
    $selectedProducts = admin_promotion_parse_ids($_POST['product_ids'] ?? []);

    $discountPercent = admin_safe_int($form['discount_percent'], 0);

    if ($form['title'] === '') {
        $errors[] = 'Название акции обязательно.';
    }
    if ($form['short_text'] === '') {
        $errors[] = 'Описание акции обязательно.';
    }
    if (!isset($scopeLabels[$form['apply_scope']])) {
        $errors[] = 'Выберите корректную область применения.';
    }
    if ($discountPercent < 1 || $discountPercent > 90) {
        $errors[] = 'Размер скидки должен быть от 1 до 90.';
    }
    if ($form['date_start'] === '') {
        $errors[] = 'Укажите дату начала акции.';
    }
    if ($form['apply_scope'] === 'categories' && !$selectedCategories) {
        $errors[] = 'Выберите хотя бы одну категорию.';
    }
    if ($form['apply_scope'] === 'products' && !$selectedProducts) {
        $errors[] = 'Выберите хотя бы один товар.';
    }

    $newImagePath = null;
    try {
        $newImagePath = admin_handle_image_upload($_FILES['image'] ?? []);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmtDeleteProducts = $pdo->prepare('DELETE FROM promotion_products WHERE promotion_id = ?');
            $stmtDeleteCategories = $pdo->prepare('DELETE FROM promotion_categories WHERE promotion_id = ?');
            $stmtDeleteProducts->execute([$promotionId]);
            $stmtDeleteCategories->execute([$promotionId]);

            $stmt = $pdo->prepare(
                'UPDATE promotions
                 SET title = ?, short_text = ?, image_path = ?, date_start = ?, date_end = ?, discount_percent = ?, apply_scope = ?
                 WHERE id = ? LIMIT 1'
            );
            $stmt->execute([
                $form['title'],
                $form['short_text'],
                $newImagePath ?? $currentImage,
                $form['date_start'],
                $form['date_end'] !== '' ? $form['date_end'] : null,
                $discountPercent,
                $form['apply_scope'],
                $promotionId,
            ]);

            if ($form['apply_scope'] === 'products') {
                admin_promotion_sync_links($pdo, $promotionId, 'products', $selectedProducts);
            } else {
                admin_promotion_sync_links($pdo, $promotionId, 'categories', $selectedCategories);
            }

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
        <label class="admin-full">
            Название акции
            <input type="text" name="title" value="<?php echo admin_e($form['title']); ?>" required>
        </label>
        <label class="admin-full">
            Описание акции
            <textarea name="short_text" rows="4" required><?php echo admin_e($form['short_text']); ?></textarea>
        </label>
        <label>
            Размер скидки (%)
            <input type="number" name="discount_percent" value="<?php echo admin_e($form['discount_percent']); ?>" min="1" max="90" required>
        </label>
        <label>
            Область применения
            <select name="apply_scope" id="promotion-scope" required>
                <?php foreach ($scopeLabels as $scopeKey => $scopeName): ?>
                    <option value="<?php echo admin_e($scopeKey); ?>" <?php echo $form['apply_scope'] === $scopeKey ? 'selected' : ''; ?>>
                        <?php echo admin_e($scopeName); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Дата начала
            <input type="date" name="date_start" value="<?php echo admin_e($form['date_start']); ?>" required>
        </label>
        <label>
            Дата окончания (необязательно)
            <input type="date" name="date_end" value="<?php echo admin_e($form['date_end']); ?>">
        </label>
        <label class="admin-full">
            Новое изображение акции (JPG, PNG, WEBP)
            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
            <?php if ($currentImage !== ''): ?>
                <img class="admin-thumb admin-thumb-large" src="<?php echo admin_e($currentImage); ?>" alt="">
            <?php endif; ?>
        </label>

        <label class="admin-full" id="categories-field">
            Категории для акции
            <select name="category_ids[]" multiple size="8">
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo admin_e((string)$category['id']); ?>" <?php echo in_array((int)$category['id'], $selectedCategories, true) ? 'selected' : ''; ?>>
                        <?php echo admin_e((string)$category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="admin-full" id="products-field">
            Товары для акции
            <select name="product_ids[]" multiple size="10">
                <?php foreach ($products as $product): ?>
                    <option value="<?php echo admin_e((string)$product['id']); ?>" <?php echo in_array((int)$product['id'], $selectedProducts, true) ? 'selected' : ''; ?>>
                        #<?php echo admin_e((string)$product['id']); ?> - <?php echo admin_e((string)$product['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit">Сохранить изменения</button>
    </form>
</section>

<script>
    (function () {
        const scope = document.getElementById('promotion-scope');
        const categoriesField = document.getElementById('categories-field');
        const productsField = document.getElementById('products-field');

        function refreshScopeFields() {
            if (!scope || !categoriesField || !productsField) {
                return;
            }
            const value = scope.value;
            categoriesField.style.display = value === 'categories' ? 'grid' : 'none';
            productsField.style.display = value === 'products' ? 'grid' : 'none';
        }

        if (scope) {
            scope.addEventListener('change', refreshScopeFields);
        }
        refreshScopeFields();
    })();
</script>
<?php admin_render_footer(); ?>
