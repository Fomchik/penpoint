<?php
require_once __DIR__ . '/../includes/security.php';
app_start_session();
require_once __DIR__ . '/../includes/config.php';

sync_promotion_statuses();

$all_promos = [];
try {
    $stmt = $pdo->query(
        'SELECT v.id, v.title, v.short_text, v.effective_status AS status, p.promotion_type, p.image_path, p.image_main, p.image_list
         FROM v_promotion_status v
         INNER JOIN promotions p ON p.id = v.id
         ORDER BY v.date_start DESC, v.id DESC'
    );
    $all_promos = $stmt->fetchAll() ?: [];
} catch (Throwable $e) {
    error_log('Sales promotions error: ' . $e->getMessage());
}

$active_promos = [];
$finished_promos = [];
foreach ($all_promos as $promo) {
    $promo['image_resolved'] = (string)(
        ($promo['promotion_type'] ?? 'regular') === 'seasonal'
            ? ($promo['image_list'] ?: $promo['image_main'] ?: $promo['image_path'])
            : ($promo['image_path'] ?: $promo['image_list'] ?: $promo['image_main'])
    );

    if (($promo['status'] ?? '') === 'active') {
        $active_promos[] = $promo;
        continue;
    }

    $status = (string)($promo['status'] ?? '');
    if ($status !== '' && $status !== 'active') {
        $finished_promos[] = $promo;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/styles/global.css">
    <link rel="stylesheet" href="/styles/header.css">
    <link rel="stylesheet" href="/styles/footer.css">
    <link rel="stylesheet" href="/styles/sales.css">
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
                        <a class="sales-card" href="<?php echo htmlspecialchars(app_promotion_catalog_url((int)$promo['id']), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="sales-card__image-wrapper">
                                <img src="<?php echo htmlspecialchars((string)$promo['image_resolved'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string)$promo['title']); ?>" class="sales-card__image">
                            </div>
                            <div class="sales-card__content">
                                <h3 class="sales-card__title"><?php echo htmlspecialchars((string)$promo['title']); ?></h3>
                                <p class="sales-card__text"><?php echo htmlspecialchars((string)$promo['short_text']); ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="sales-section sales-section--past" aria-label="Завершенные акции">
            <h2 class="sales-section__title">Завершенные акции</h2>
            <?php if (!empty($finished_promos)): ?>
                <div class="sales-grid sales-grid--past">
                    <?php foreach ($finished_promos as $promo): ?>
                        <article class="sales-card sales-card--expired">
                            <div class="sales-card__image-wrapper">
                                <img src="<?php echo htmlspecialchars((string)$promo['image_resolved'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string)$promo['title']); ?>" class="sales-card__image">
                            </div>
                            <div class="sales-card__content">
                                <h3 class="sales-card__title"><?php echo htmlspecialchars((string)$promo['title']); ?></h3>
                                <p class="sales-card__text"><?php echo htmlspecialchars((string)$promo['short_text']); ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="sales-section__empty">Пока нет завершенных акций.</p>
            <?php endif; ?>
        </section>

    </main>
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
