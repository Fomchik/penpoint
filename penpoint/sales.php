<?php
session_start();
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/styles/global.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/styles/header.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/styles/footer.css">
    <title>Акции — PENPOINT</title>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <main class="main">
        <section>
            <h1>Акции</h1>
            <p>Раздел акций. Товары со скидками можно посмотреть в <a href="<?php echo BASE_PATH; ?>/catalog.php?sale=1">каталоге</a>.</p>
        </section>
    </main>
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
