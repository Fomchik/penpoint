<?php
session_start();
require_once __DIR__ . '/includes/config.php';

// Параметры
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'new';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;

$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
$sale_only = isset($_GET['sale']) && $_GET['sale'] === '1';
$category_ids = [];
if (isset($_GET['category']) && is_array($_GET['category'])) {
    foreach ($_GET['category'] as $cid) {
        $cid = (int)$cid;
        if ($cid > 0) $category_ids[] = $cid;
    }
    $category_ids = array_values(array_unique($category_ids));
}

// Скидки по ID товара
$discount_map = [
    1 => 30,
    2 => 30,
    3 => 30,
    5 => 20,
    9 => 20,
];

// Сортировка
$order_by = "p.created_at DESC";
if ($sort === 'price_asc') $order_by = "p.price ASC";
if ($sort === 'price_desc') $order_by = "p.price DESC";

// WHERE + params
$where = ["p.is_active = 1"];
$params = [];

if ($q !== '') {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}

if ($min_price !== null) {
    $where[] = "p.price >= ?";
    $params[] = $min_price;
}

if ($max_price !== null) {
    $where[] = "p.price <= ?";
    $params[] = $max_price;
}

if (!empty($category_ids)) {
    $in = implode(',', array_fill(0, count($category_ids), '?'));
    $where[] = "p.category_id IN ($in)";
    foreach ($category_ids as $cid) $params[] = $cid;
}

// sale_only — фильтруем уже на уровне PHP по discount_map (БД скидок нет)
$where_sql = implode(' AND ', $where);

// Total count
$stmt_total = $pdo->prepare("SELECT COUNT(*) as cnt FROM products p WHERE $where_sql");
$stmt_total->execute($params);
$total = (int)($stmt_total->fetch()['cnt'] ?? 0);

$pages = max(1, (int)ceil($total / $per_page));
$page = min($page, $pages);
$offset = ($page - 1) * $per_page;

// Fetch products
$stmt = $pdo->prepare("
    SELECT p.*
    FROM products p
    WHERE $where_sql
    ORDER BY $order_by
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll() ?: [];

if ($sale_only) {
    $products = array_values(array_filter($products, function ($p) use ($discount_map) {
        return isset($discount_map[(int)$p['id']]);
    }));
}

$categories = get_categories();

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function build_query(array $overrides = []): string {
    $q = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    return http_build_query($q);
}

?>
<?php if (!$is_ajax): ?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/styles/global.css">
    <link rel="stylesheet" href="/styles/header.css">
    <link rel="stylesheet" href="/styles/product-card.css">
    <link rel="stylesheet" href="/styles/footer.css">
    <link rel="stylesheet" href="/styles/catalog.css">
    <title>Каталог — PENPOINT</title>
</head>

<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="main catalog">
<?php endif; ?>
<?php if (!$is_ajax): ?>
        <aside class="catalog__filters">
            <form class="filters" id="catalog-filters-form" method="get" action="/catalog.php">
                <div class="filters__section">
                    <div class="filters__title">Доставка</div>
                    <label class="filters__check">
                        <input type="checkbox" disabled>
                        <span>Самовывоз сегодня (235)</span>
                    </label>
                </div>

                <div class="filters__section">
                    <div class="filters__title">Товары со скидкой</div>
                    <label class="filters__check">
                        <input type="checkbox" name="sale" value="1" <?php echo $sale_only ? 'checked' : ''; ?>>
                        <span>Да (<?php echo count($discount_map); ?>)</span>
                    </label>
                </div>

                <div class="filters__section">
                    <div class="filters__title">Цена</div>
                    <div class="filters__price">
                        <input class="filters__input" type="number" name="min_price" placeholder="От" value="<?php echo $min_price !== null ? htmlspecialchars((string)$min_price) : ''; ?>">
                        <input class="filters__input" type="number" name="max_price" placeholder="До" value="<?php echo $max_price !== null ? htmlspecialchars((string)$max_price) : ''; ?>">
                    </div>
                </div>

                <div class="filters__section">
                    <div class="filters__title">Тип товара</div>
                    <div class="filters__search">
                        <img src="/assets/icons/search.svg" alt="" class="filters__search-icon" aria-hidden="true">
                        <input class="filters__search-input" type="text" name="q" placeholder="Поиск" value="<?php echo htmlspecialchars($q); ?>">
                    </div>

                    <div class="filters__list">
                        <?php foreach ($categories as $cat): ?>
                            <?php $checked = in_array((int)$cat['id'], $category_ids, true); ?>
                            <label class="filters__check">
                                <input type="checkbox" name="category[]" value="<?php echo (int)$cat['id']; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                <span><?php echo htmlspecialchars($cat['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </aside>
<?php endif; ?>

        <section class="catalog__content" id="catalog-content">
            <div class="catalog__toolbar">
                <form method="get" class="catalog__sort" id="catalog-sort-form">
                    <?php
                    // Пробрасываем параметры в сортировку
                    foreach ($_GET as $k => $v) {
                        if ($k === 'sort') continue;
                        if (is_array($v)) {
                            foreach ($v as $vv) {
                                echo '<input type="hidden" name="' . htmlspecialchars($k) . '[]" value="' . htmlspecialchars((string)$vv) . '">';
                            }
                        } else {
                            echo '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars((string)$v) . '">';
                        }
                    }
                    ?>
                    <select name="sort" class="catalog__sort-select">
                        <option value="new" <?php echo $sort === 'new' ? 'selected' : ''; ?>>Новинки</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Цена: по возрастанию</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Цена: по убыванию</option>
                    </select>
                </form>
            </div>

            <div class="catalog__grid">
                <?php foreach ($products as $p): ?>
                    <?php
                    $pid = (int)$p['id'];
                    $img = get_product_image($pid);
                    $r = get_product_rating($pid);
                    $rv = (float)$r['rating'];
                    $dc = $discount_map[$pid] ?? 0;
                    $old = (float)$p['price'];
                    $new = $dc ? round($old * (1 - $dc / 100), 2) : $old;
                    ?>
                    <article class="product-card">
                        <?php if ($dc): ?>
                            <span class="product-card__badge product-card__badge--discount"><?php echo (int)$dc; ?>%</span>
                        <?php endif; ?>
                        <button type="button" class="product-card__wishlist" aria-label="В избранное" data-product-id="<?php echo $pid; ?>">
                            <img src="/assets/icons/heart.svg" alt="" class="product-card__wishlist-icon">
                        </button>
                        <a href="/pages/page-product.php?id=<?php echo $pid; ?>" class="product-card__link">
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="product-card__image" loading="lazy">
                        </a>
                        <h3 class="product-card__name">
                            <a href="/pages/page-product.php?id=<?php echo $pid; ?>"><?php echo htmlspecialchars($p['name']); ?></a>
                        </h3>

                        <div class="product-card__rating">
                            <span class="product-card__stars" aria-label="Рейтинг: <?php echo $rv; ?> из 5">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php
                                    $fill = max(0, min(1, $rv - ($i - 1)));
                                    $fill_percent = (int)round($fill * 100);
                                    ?>
                                    <span class="star" aria-hidden="true">
                                        <img src="/assets/icons/star.svg" alt="" class="star__bg" width="16" height="16">
                                        <span class="star__fg" style="width: <?php echo $fill_percent; ?>%;">
                                            <img src="/assets/icons/star.svg" alt="" class="star__img" width="16" height="16">
                                        </span>
                                    </span>
                                <?php endfor; ?>
                            </span>
                            <span class="product-card__reviews">(<?php echo (int)$r['count']; ?>)</span>
                        </div>

                        <div class="product-card__price">
                            <?php if ($dc): ?>
                                <span class="product-card__price--old"><?php echo format_price($old); ?></span>
                            <?php endif; ?>
                            <span class="product-card__price--new"><?php echo format_price($new); ?></span>
                        </div>

                        <button type="button"
                            class="product-card__add-to-cart"
                            data-product-id="<?php echo $pid; ?>"
                            data-product-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>"
                            data-product-price="<?php echo (float)$new; ?>">
                            <img src="/assets/icons/cart.svg" alt="" class="product-card__add-to-cart-icon" width="18" height="18">
                            В корзину
                        </button>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="catalog__pagination">
                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <?php $is_active = $p === $page; ?>
                    <a class="catalog__page <?php echo $is_active ? 'catalog__page--active' : ''; ?>"
                        href="/catalog.php?<?php echo htmlspecialchars(build_query(['page' => $p])); ?>">
                        <?php echo $p; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </section>
<?php if (!$is_ajax): ?>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="/scripts/cart.js"></script>
    <script src="/scripts/favorites.js"></script>
    <script src="/scripts/search.js"></script>
    <script src="/scripts/catalog-filters.js"></script>
</body>

</html>
<?php endif; ?>
<?php
if ($is_ajax) {
    exit;
}
