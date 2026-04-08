<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

function admin_nav_items(): array
{
    return [
        'dashboard' => ['label' => 'Dashboard', 'href' => '/admin/index.php'],
        'products' => ['label' => 'Товары', 'href' => '/admin/products.php'],
        'promotions' => ['label' => 'Акции', 'href' => '/admin/promotions.php'],
        'orders' => ['label' => 'Заказы', 'href' => '/admin/orders.php'],
        'reviews' => ['label' => 'Отзывы', 'href' => '/admin/reviews.php'],
    ];
}

function admin_render_header(string $title, string $active = ''): void
{
    $user = admin_require_auth();
    $flash = admin_get_flash();
    $nav = admin_nav_items();
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    <link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/admin/assets/admin.css">
    <title><?php echo admin_e($title); ?> | Admin</title>
</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a class="admin-brand" href="/admin/index.php">Канцария Admin</a>
            <nav class="admin-nav">
                <?php foreach ($nav as $key => $item): ?>
                    <a class="admin-nav__item <?php echo $active === $key ? 'is-active' : ''; ?>" href="<?php echo admin_e($item['href']); ?>">
                        <?php echo admin_e($item['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <a class="admin-back-link" href="/index.php">Вернуться на сайт</a>
            <form method="post" action="/admin/logout.php" class="admin-logout-form">
                <?php echo admin_csrf_input(); ?>
                <button class="admin-logout-btn" type="submit">Выйти</button>
            </form>
        </aside>
        <main class="admin-main">
            <header class="admin-topbar">
                <h1 class="admin-title"><?php echo admin_e($title); ?></h1>
                <div class="admin-user"><?php echo admin_e($user['name'] ?? 'Admin'); ?></div>
            </header>

            <?php if ($flash): ?>
                <div class="admin-alert admin-alert--<?php echo admin_e($flash['type'] ?? 'info'); ?>">
                    <?php echo admin_e($flash['message'] ?? ''); ?>
                </div>
            <?php endif; ?>
<?php
}

function admin_render_footer(): void
{
    ?>
        </main>
    </div>
</body>
</html>
<?php
}
