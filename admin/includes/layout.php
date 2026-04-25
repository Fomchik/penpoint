<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

function admin_nav_items(): array
{
    return [
        'dashboard'  => ['label' => 'Dashboard', 'href' => '/admin/index.php'],
        'products'   => ['label' => 'Товары', 'href' => '/admin/products.php'],
        'promotions' => ['label' => 'Акции', 'href' => '/admin/promotions.php'],
        'orders'     => ['label' => 'Заказы', 'href' => '/admin/orders.php'],
        'messages'   => ['label' => 'Сообщения', 'href' => '/admin/messages.php'],
        'reviews'    => ['label' => 'Отзывы', 'href' => '/admin/reviews.php'],
    ];
}

function admin_render_header(string $title, string $active = ''): void
{
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;");
    $user = admin_require_auth();
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

        <link rel="stylesheet" href="/admin/assets/css/admin-base.css">
        <link rel="stylesheet" href="/admin/assets/css/admin-layout.css">
        <link rel="stylesheet" href="/admin/assets/css/admin-components.css">
        <link rel="stylesheet" href="/admin/assets/css/admin-modules.css">
        <link rel="stylesheet" href="/admin/assets/css/admin-responsive.css">

        <title><?php echo admin_e($title); ?> | Канцария Admin</title>
    </head>

    <body class="admin-body">
        <div class="admin-shell">
            <aside class="admin-sidebar">
                <a class="admin-brand" href="/admin/index.php">Канцария <span>Admin</span></a>

                <nav class="admin-nav">
                    <?php foreach ($nav as $key => $item): ?>
                        <a class="admin-nav__item <?php echo $active === $key ? 'is-active' : ''; ?>"
                            href="<?php echo admin_e($item['href']); ?>">
                            <?php echo admin_e($item['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <div class="admin-sidebar-footer">
                    <a class="admin-back-link" href="/index.php">Вернуться на сайт</a>

                    <form method="post" action="logout.php" class="admin-logout-form"
                        onsubmit="return confirm('Выйти из системы?');">
                        <?php echo admin_csrf_input(); ?>
                        <button class="admin-logout-btn" type="submit">Выйти</button>
                    </form>
                </div>
            </aside>

            <main class="admin-main">
                <header class="admin-topbar">
                    <h1 class="admin-title"><?php echo admin_e($title); ?></h1>
                    <div class="admin-user">
                        <span class="admin-user__role">Администратор:</span>
                        <strong><?php echo admin_e($user['name'] ?? 'Admin'); ?></strong>
                    </div>
                </header>

                <div class="admin-content">
                    <?php if ($flash = admin_get_flash()): ?>
                        <div class="admin-alert admin-alert--<?php echo $flash['type']; ?> js-auto-close">
                            <div class="admin-alert__message"><?php echo admin_e($flash['message']); ?></div>
                        </div>
                    <?php endif; ?>
                <?php
}

function admin_render_footer(array $scripts = []): void
{
    ?>
                </div>
            </main>
        </div>

        <?php foreach ($scripts as $script): ?>
            <script src="<?php echo admin_e($script); ?>"></script>
        <?php endforeach; ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.js-auto-close').forEach(function (alert) {
                    window.setTimeout(function () {
                        alert.classList.add('is-hiding');
                        window.setTimeout(function () {
                            if (alert.parentNode) {
                                alert.parentNode.removeChild(alert);
                            }
                        }, 260);
                    }, 3200);
                });
            });
        </script>
    </body>

    </html>
<?php
}