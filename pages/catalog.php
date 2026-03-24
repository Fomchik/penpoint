<?php
require_once __DIR__ . '/../includes/security.php';
app_start_session();
require_once __DIR__ . '/../includes/config.php';

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'new';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;

$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
$sale_only = isset($_GET['sale']) && $_GET['sale'] === '1';
$pickup_only = isset($_GET['pickup']) && $_GET['pickup'] === '1';

$category_ids = [];
if (isset($_GET['category']) && is_array($_GET['category'])) {
    foreach ($_GET['category'] as $cid) {
        $cid = (int)$cid;
        if ($cid > 0) {
            $category_ids[] = $cid;
        }
    }
    $category_ids = array_values(array_unique($category_ids));
}

$order_by = 'p.created_at DESC';
if ($sort === 'price_asc') {
    $order_by = 'p.final_price ASC';
}
if ($sort === 'price_desc') {
    $order_by = 'p.final_price DESC';
}

$where = ['p.is_active = 1'];
$params = [];

if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}

if ($min_price !== null) {
    $where[] = 'p.final_price >= ?';
    $params[] = $min_price;
}

if ($max_price !== null) {
    $where[] = 'p.final_price <= ?';
    $params[] = $max_price;
}

if (!empty($category_ids)) {
    $in = implode(',', array_fill(0, count($category_ids), '?'));
    $where[] = "p.category_id IN ($in)";
    foreach ($category_ids as $cid) {
        $params[] = $cid;
    }
}

if ($pickup_only) {
    $where[] = '(p.pickup_available IS NULL OR p.pickup_available = 1)';
}

if ($sale_only) {
    $where[] = 'p.discount_percent > 0';
}

$where_sql = implode(' AND ', $where);

$stmt_total = $pdo->prepare("SELECT COUNT(*) as cnt FROM v_product_pricing p WHERE $where_sql");
$stmt_total->execute($params);
$total = (int)($stmt_total->fetch()['cnt'] ?? 0);

$pages = max(1, (int)ceil($total / $per_page));
$page = min($page, $pages);
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("
    SELECT
        p.*,
        p.final_price AS price,
        p.base_price AS price_old
    FROM v_product_pricing p
    WHERE $where_sql
    ORDER BY $order_by
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll() ?: [];

$categories = get_categories();
$sale_products_total = get_active_discounted_products_count();

$category_counts = [];
try {
    $stmt_cat_counts = $pdo->query("
        SELECT category_id, COUNT(*) AS cnt
        FROM products
        WHERE is_active = 1
        GROUP BY category_id
    ");
    foreach ($stmt_cat_counts->fetchAll() as $row) {
        $category_counts[(int)$row['category_id']] = (int)$row['cnt'];
    }
} catch (PDOException $e) {
    error_log('Database error (category_counts): ' . $e->getMessage());
}

$pickup_total = 0;
try {
    $stmt_pickup = $pdo->query("
        SELECT COUNT(*) AS cnt
        FROM products p
        WHERE p.is_active = 1
          AND (p.pickup_available IS NULL OR p.pickup_available = 1)
    ");
    $pickup_total = (int)($stmt_pickup->fetch()['cnt'] ?? 0);
} catch (PDOException $e) {
    error_log('Database error (pickup_total): ' . $e->getMessage());
}

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function build_query(array $overrides = []): string
{
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
        <link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="/styles/global.css">
        <link rel="stylesheet" href="/styles/header.css">
        <link rel="stylesheet" href="/styles/product-card.css">
        <link rel="stylesheet" href="/styles/footer.css">
        <link rel="stylesheet" href="/styles/catalog.css">
        <title>Каталог — Канцария</title>
    </head>

    <body>
        <?php include __DIR__ . '/../includes/header.php'; ?>

        <main class="main catalog">
        <?php endif; ?>
        <?php if (!$is_ajax): ?>
            <aside class="catalog__filters">
                <form class="filters" id="catalog-filters-form" method="get" action="/pages/catalog.php">
                    <div class="filters__section">
                        <div class="filters__title">Доставка</div>
                        <label class="filters__check">
                            <input type="checkbox" name="pickup" value="1" <?php echo $pickup_only ? 'checked' : ''; ?>>
                            <span>Самовывоз сегодня (<?php echo $pickup_total; ?>)</span>
                        </label>
                    </div>

                    <div class="filters__section">
                        <div class="filters__title">Товары со скидкой</div>
                        <label class="filters__check">
                            <input type="checkbox" name="sale" value="1" <?php echo $sale_only ? 'checked' : ''; ?>>
                            <span>Да (<?php echo $sale_products_total; ?>)</span>
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
                                <?php
                                $cat_id = (int)$cat['id'];
                                $checked = in_array($cat_id, $category_ids, true);
                                $cnt = $category_counts[$cat_id] ?? 0;
                                ?>
                                <label class="filters__check">
                                    <input type="checkbox" name="category[]" value="<?php echo $cat_id; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                    <span>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                        <?php if ($cnt > 0): ?>
                                            (<?php echo $cnt; ?>)
                                        <?php endif; ?>
                                    </span>
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
                    $dc = (int)($p['discount_percent'] ?? 0);
                    $old = (float)($p['price_old'] ?? $p['price']);
                    $new = (float)$p['price'];
                    ?>
                    <article class="product-card">
                        <?php if ($dc): ?>
                            <span class="product-card__badge product-card__badge--discount"><?php echo $dc; ?>%</span>
                        <?php endif; ?>
                        <button type="button" class="product-card__wishlist" aria-label="В избранное" data-product-id="<?php echo $pid; ?>">
                            <img src="/assets/icons/heart.svg" alt="" class="product-card__wishlist-icon">
                        </button>
                        <a href="/pages/page-product.php?id=<?php echo $pid; ?>" class="product-card__link">
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="product-card__image" loading="lazy">
                        </a>
                        <h4 class="product-card__name">
                            <a href="/pages/page-product.php?id=<?php echo $pid; ?>"><?php echo htmlspecialchars($p['name']); ?></a>
                        </h4>
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

                        <button
                            type="button"
                            class="product-card__add-to-cart"
                            data-product-id="<?php echo $pid; ?>"
                            data-product-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>"
                            data-product-price="<?php echo (float)$new; ?>"
                            data-product-old-price="<?php echo (float)$old; ?>">
                            <img src="/assets/icons/cart.svg" alt="" class="product-card__add-to-cart-icon" width="18" height="18">
                            В корзину
                        </button>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="catalog__pagination">
                <?php if ($page > 1): ?>
                    <a class="catalog__page-nav catalog__page-nav--prev"
                        href="/pages/catalog.php?<?php echo htmlspecialchars(build_query(['page' => $page - 1])); ?>"
                        aria-label="Предыдущая страница">
                        <img src="/assets/icons/arrow.svg" alt="" aria-hidden="true">
                    </a>
                <?php else: ?>
                    <span class="catalog__page-nav catalog__page-nav--prev catalog__page-nav--disabled" aria-hidden="true">
                        <img src="/assets/icons/arrow.svg" alt="">
                    </span>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $pages; $p++): ?>
                    <?php $is_active = $p === $page; ?>
                    <a class="catalog__page <?php echo $is_active ? 'catalog__page--active' : ''; ?>"
                        href="/pages/catalog.php?<?php echo htmlspecialchars(build_query(['page' => $p])); ?>">
                        <?php echo $p; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $pages): ?>
                    <a class="catalog__page-nav catalog__page-nav--next"
                        href="/pages/catalog.php?<?php echo htmlspecialchars(build_query(['page' => $page + 1])); ?>"
                        aria-label="Следующая страница">
                        <img src="/assets/icons/arrow.svg" alt="" aria-hidden="true">
                    </a>
                <?php else: ?>
                    <span class="catalog__page-nav catalog__page-nav--next catalog__page-nav--disabled" aria-hidden="true">
                        <img src="/assets/icons/arrow.svg" alt="">
                    </span>
                <?php endif; ?>
            </div>
        </section>
        <?php if (!$is_ajax): ?>
        </main>

        <?php include __DIR__ . '/../includes/footer.php'; ?>
        <script src="/scripts/catalog-filters.js"></script>
    </body>

    </html>
<?php endif; ?>
<?php
if ($is_ajax) {
    exit;
}
?>
