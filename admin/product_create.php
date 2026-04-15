<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../includes/product_options.php';
require_once __DIR__ . '/includes/product_attribute_catalog.php';

admin_require_auth();

global $pdo;
product_options_ensure_schema($pdo);

$categories = [];
$errors = [];
$form = [
    'name' => '',
    'description' => '',
    'price' => '',
    'stock_quantity' => '',
    'category_id' => '',
];
$variantPayload = product_admin_form_payload(0);
$attributeCatalog = admin_attribute_catalog_all($pdo);

try {
    $stmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
    $categories = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    admin_log_error('product_create_categories', $e);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();

    $form['name'] = trim((string)($_POST['name'] ?? ''));
    $form['description'] = trim((string)($_POST['description'] ?? ''));
    $form['price'] = trim((string)($_POST['price'] ?? ''));
    $form['stock_quantity'] = trim((string)($_POST['stock_quantity'] ?? ''));
    $form['category_id'] = trim((string)($_POST['category_id'] ?? ''));

    $categoryId = admin_safe_int($form['category_id'], 0);
    $price = (float)str_replace(',', '.', $form['price']);
    $stock = admin_safe_int($form['stock_quantity'], -1);

    if ($form['name'] === '') {
        $errors[] = 'Название обязательно.';
    }
    if ($form['description'] === '') {
        $errors[] = 'Описание обязательно.';
    }
    if ($price <= 0) {
        $errors[] = 'Цена должна быть больше 0.';
    }
    if ($stock < 0) {
        $errors[] = 'Остаток должен быть 0 или больше.';
    }
    if ($categoryId <= 0) {
        $errors[] = 'Выберите категорию.';
    }

    $imagePath = null;
    if (!$errors) {
        try {
            $imagePath = admin_handle_image_upload($_FILES['image'] ?? []);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO products (category_id, name, description, price, stock_quantity, pickup_available, is_active)
                 VALUES (?, ?, ?, ?, ?, 1, 1)'
            );
            $stmt->execute([$categoryId, $form['name'], $form['description'], $price, $stock]);

            $productId = (int)$pdo->lastInsertId();
            if ($imagePath !== null) {
                $stmtImage = $pdo->prepare('INSERT INTO product_images (product_id, image_path) VALUES (?, ?)');
                $stmtImage->execute([$productId, $imagePath]);
            }

            product_save_parameters($pdo, $productId, (array)($_POST['product_parameters'] ?? []));
            product_save_variants($pdo, $productId, (array)($_POST['product_variants'] ?? []));

            $pdo->commit();
            admin_set_flash('success', 'Товар создан.');
            admin_redirect('/admin/products.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            admin_log_error('product_create', $e);
            $errors[] = 'Не удалось создать товар.';
        }
    }
}

admin_render_header('Создание товара', 'products');
?>
<section class="admin-form-wrap">
    <div class="admin-section-head">
        <h2>Новый товар</h2>
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
                    <option value="<?php echo admin_e((string)$category['id']); ?>" <?php echo ((string)$category['id'] === $form['category_id']) ? 'selected' : ''; ?>>
                        <?php echo admin_e((string)$category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="admin-full">
            Описание
            <textarea name="description" rows="5" required><?php echo admin_e($form['description']); ?></textarea>
        </label>
        <label>
            Цена
            <input type="number" name="price" value="<?php echo admin_e($form['price']); ?>" step="0.01" min="0.01" required>
        </label>
        <label>
            Остаток
            <input type="number" name="stock_quantity" value="<?php echo admin_e($form['stock_quantity']); ?>" min="0" required>
        </label>
        <label class="admin-full">
            Изображение (JPG, PNG, WEBP)
            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
        </label>
        <div class="admin-full admin-variants"
            data-parameter-catalog="<?php echo admin_e(json_encode(array_map(static function (array $item): string { return (string)$item['name']; }, $attributeCatalog), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'); ?>"
            data-initial-parameters="<?php echo admin_e(json_encode($variantPayload['parameters'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'); ?>"
            data-initial-variants="<?php echo admin_e(json_encode($variantPayload['variants'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'); ?>"
            data-attribute-create-url="/admin/api/attribute_create.php"
            data-csrf-token="<?php echo admin_e(admin_csrf_token()); ?>">
            <div>
                <h3 class="admin-variants__section-title">Параметры товара</h3>
                <p class="admin-variants__hint">Комбинации вариантов формируются автоматически при изменении значений и переключении чекбоксов.</p>
                <div data-parameters></div>
            </div>
            <div>
                <h3 class="admin-variants__section-title">Варианты</h3>
                <div data-variants></div>
            </div>
        </div>
        <button type="submit">Создать товар</button>
    </form>
</section>
<script src="/admin/assets/product-variants.js" defer></script>
<?php admin_render_footer(); ?>
