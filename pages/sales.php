<?php
require_once __DIR__ . '/../includes/security.php';
app_start_session();
require_once __DIR__ . '/../includes/config.php';

try {
    $stmt = $pdo->query("
        SELECT
            id,
            title,
            short_text,
            image_path,
            date_start,
            date_end,
            effective_status AS status
        FROM v_promotion_status
        ORDER BY date_start DESC, id DESC
    ");
    $all_promos = $stmt->fetchAll() ?: [];
} catch (PDOException $e) {
    error_log('Database error (promotions view): ' . $e->getMessage());
    try {
        $stmt = $pdo->query("
            SELECT
                id,
                title,
                short_text,
                image_path,
                date_start,
                date_end,
                CASE
                    WHEN date_start > CURDATE() THEN 'draft'
                    WHEN date_end IS NOT NULL AND date_end < CURDATE() THEN 'finished'
                    ELSE 'active'
                END AS status
            FROM promotions
            ORDER BY date_start DESC, id DESC
        ");
        $all_promos = $stmt->fetchAll() ?: [];
    } catch (PDOException $e2) {
        error_log('Database error (promotions fallback): ' . $e2->getMessage());
        $all_promos = [];
    }
}

$active_promos = [];
$expired_promos = [];

foreach ($all_promos as $promo) {
    if (($promo['status'] ?? '') === 'active') {
        $active_promos[] = $promo;
    } elseif (($promo['status'] ?? '') === 'finished') {
        $expired_promos[] = $promo;
    }
}

$banner_files = glob(__DIR__ . '/../assets/banners/*.{png,jpg,jpeg,webp}', GLOB_BRACE);
$banner_urls = [];
foreach ($banner_files as $file) {
    $basename = basename($file);
    if (stripos($basename, 'hero') !== false) {
        continue;
    }
    $banner_urls[] = BASE_PATH . '/assets/banners/' . $basename;
}

$assignImages = function (array &$promos) use ($banner_urls) {
    if (empty($banner_urls)) {
        return;
    }
    $count = count($banner_urls);
    foreach ($promos as $idx => &$promo) {
        if (empty($promo['image_path'])) {
            $promo['image_path'] = $banner_urls[$idx % $count];
        }
    }
};

$assignImages($active_promos);
$assignImages($expired_promos);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/styles/global.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/styles/header.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/styles/footer.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/styles/sales.css">
    <title>Акции — Канцария</title>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main class="main sales-page">
        <section class="sales-hero" aria-label="Акции магазина">
            <h1 class="sales-hero__title">Акции</h1>
        </section>

        <?php if (!empty($active_promos)): ?>
            <section class="sales-section sales-section--current" aria-label="Действующие акции">
                <h2 class="sales-section__title">Действующие акции</h2>
                <div class="sales-grid sales-grid--current">
                    <?php foreach ($active_promos as $promo): ?>
                        <article class="sales-card">
                            <div class="sales-card__image-wrapper">
                                <img src="<?php echo htmlspecialchars($promo['image_path'], ENT_QUOTES); ?>"
                                     alt="<?php echo htmlspecialchars($promo['title'], ENT_QUOTES); ?>"
                                     class="sales-card__image">
                            </div>
                            <div class="sales-card__content">
                                <h3 class="sales-card__title"><?php echo htmlspecialchars($promo['title']); ?></h3>
                                <p class="sales-card__text"><?php echo htmlspecialchars($promo['short_text']); ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($expired_promos)): ?>
            <section class="sales-section sales-section--expired" aria-label="Завершенные акции">
                <h2 class="sales-section__title">Завершенные акции</h2>
                <div class="sales-grid sales-grid--expired">
                    <?php foreach ($expired_promos as $promo): ?>
                        <article class="sales-card sales-card--expired">
                            <div class="sales-card__image-wrapper">
                                <img src="<?php echo htmlspecialchars($promo['image_path'], ENT_QUOTES); ?>"
                                     alt="<?php echo htmlspecialchars($promo['title'], ENT_QUOTES); ?>"
                                     class="sales-card__image">
                            </div>
                            <div class="sales-card__content">
                                <h3 class="sales-card__title"><?php echo htmlspecialchars($promo['title']); ?></h3>
                                <p class="sales-card__text"><?php echo htmlspecialchars($promo['short_text']); ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
