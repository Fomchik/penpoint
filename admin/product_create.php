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

$categories = [];
$categorySlugs = [];
$errors = [];
$form = [
    'name' => '',
    'description' => '',
    'price' => '',
    'stock_quantity' => '',
    'category_id' => '',
    'pickup_available' => '1',
];

$variantPayload = product_admin_form_payload(0);
$attributeCatalog = admin_attribute_catalog_all($pdo);

try {
    $stmt = $pdo->query('SELECT id, name, slug FROM categories ORDER BY name ASC');
    $categories = $stmt->fetchAll() ?: [];
    foreach ($categories as $cat) {
        $categorySlugs[(int)$cat['id']] = trim((string)($cat['slug'] ?? ''));
    }
} catch (Throwable $e) {
    admin_log_error('product_create_categories', $e);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_validate_csrf_or_fail();

    $form = [
        'name' => trim((string)($_POST['name'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'price' => trim((string)($_POST['price'] ?? '')),
        'stock_quantity' => trim((string)($_POST['stock_quantity'] ?? '')),
        'category_id' => trim((string)($_POST['category_id'] ?? '')),
        'pickup_available' => isset($_POST['pickup_available']) ? '1' : '0',
    ];

    $categoryId = (int)$form['category_id'];
    $price = (float)str_replace(',', '.', $form['price']);
    $stock = (int)$form['stock_quantity'];
    $pickupAvailable = $form['pickup_available'] === '1' ? 1 : 0;

    if (!$form['name']) $errors[] = 'Укажите название.';
    if ($price <= 0) $errors[] = 'Цена должна быть больше 0.';
    if ($categoryId <= 0) $errors[] = 'Выберите категорию.';

    $imagePath = null;
    if (!$errors) {
        try {
            if (!empty($_FILES['image']['name'])) {
                $categorySlug = $categorySlugs[$categoryId] ?? 'misc';
                $imagePath = admin_handle_image_upload($_FILES['image'], [
                    'target' => 'product_images',
                    'sub_path' => $categorySlug . '/' . date('Y/m'),
                    'prefix' => $form['name'],
                ]);
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO products (category_id, name, description, price, stock_quantity, pickup_available, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)');
            $stmt->execute([$categoryId, $form['name'], $form['description'], $price, $stock, $pickupAvailable]);
            $productId = (int)$pdo->lastInsertId();

            if ($imagePath) {
                $pdo->prepare('INSERT INTO product_images (product_id, image_path) VALUES (?, ?)')->execute([$productId, $imagePath]);
            }

            // ПАРАМЕТРЫ И ФОТО: Передаем массив файлов для вариантов
            product_save_parameters($pdo, $productId, (array)($_POST['product_parameters'] ?? []), $_FILES['product_parameters'] ?? []);

            product_save_variants($pdo, $productId, (array)($_POST['product_variants'] ?? []));
            product_characteristics_save($pdo, $productId, (array)($_POST['product_characteristics'] ?? []));

            $pdo->commit();
            admin_redirect('/admin/products.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            admin_log_error('product_create', $e);
            $errors[] = 'Ошибка сохранения: ' . $e->getMessage();
        }
    }
}

admin_render_header('Новый товар', 'products');
?>

<section class="admin-form-wrap">
    <div class="admin-section-head">
        <h2>Добавление товара</h2>
        <a class="admin-link-btn" href="/admin/products.php">← К списку</a>
    </div>

    <?php if ($errors): ?>
        <div class="admin-alert danger">
            <?php echo admin_e(implode(' ', $errors)); ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="admin-form" id="product-create-form">
        <?php echo admin_csrf_input(); ?>

        <div class="admin-grid-2">
            <div class="admin-panel">
                <h3>Основная информация</h3>
                <label>Название
                    <input type="text" name="name" value="<?php echo admin_e($form['name']); ?>" required>
                </label>
                <label>Категория
                    <select name="category_id" required>
                        <option value="">-- Выберите --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($cat['id'] == $form['category_id']) ? 'selected' : ''; ?>>
                                <?php echo admin_e($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Описание
                    <textarea name="description" rows="6"><?php echo admin_e($form['description']); ?></textarea>
                </label>
                <label style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" name="pickup_available" value="1" <?php echo $form['pickup_available'] === '1' ? 'checked' : ''; ?>>
                    <span>Доступен для самовывоза сегодня</span>
                </label>
            </div>

            <div class="admin-panel">
                <h3>Склад и превью</h3>
                <div class="admin-grid-2" style="gap:10px; grid-template-columns: 1fr 1fr;">
                    <label>Цена
                        <input type="number" name="price" value="<?php echo admin_e($form['price']); ?>" step="0.01" required>
                    </label>
                    <label>Остаток
                        <input type="number" name="stock_quantity" value="<?php echo admin_e($form['stock_quantity']); ?>" required>
                        <small style="color:#6b7280;">Базовый остаток товара. Используется только если для параметров/вариантов не задан собственный остаток.</small>
                    </label>
                </div>
                <label style="margin-top:15px;">Главное фото товара
                    <input type="file" name="image" accept="image/*">
                </label>
            </div>
        </div>

        <div class="admin-panel" style="margin-top:20px;">
    <div class="admin-section-head" style="margin-bottom: 15px;">
        <h3>Вариации и параметры</h3>
        <button type="button" class="admin-btn-second" id="add-parameter">+ Создать группу (напр. Размер)</button>
    </div>

    <div class="admin-variants" 
         id="variants-root"
         data-initial-parameters='<?php echo json_encode($variantPayload['parameters'], JSON_UNESCAPED_UNICODE); ?>'>
        
        <div id="parameters-container" data-parameters-container>
            </div>

        <p class="admin-list__item-note" style="margin-top: 15px;">
            Группируйте параметры (Цвет, Материал). Внутри каждой группы можно добавить неограниченное количество значений.
        </p>
    </div>
</div>

        <div class="admin-panel" style="margin-top:20px;">
            <h3>Характеристики</h3>
            <div class="admin-characteristics" data-characteristics>
                <div class="admin-characteristics__list" data-characteristics-list></div>
                <button type="button" class="admin-text-btn" data-characteristics-add>+ Добавить характеристику</button>
            </div>
        </div>

        <div class="admin-actions" style="margin-top:30px;">
            <button type="submit" class="admin-btn">Опубликовать товар</button>
        </div>
    </form>
</section>

<script src="/admin/assets/product-variants.js"></script>
<script src="/admin/assets/product-characteristics.js"></script>

<?php admin_render_footer(); ?>
