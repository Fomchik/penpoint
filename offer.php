<?php
require_once __DIR__ . '/includes/security.php';
app_start_session();
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Пользовательское соглашение (публичная оферта) интернет-магазина Канцария.">
    <link rel="icon" href="<?php echo htmlspecialchars(app_asset_url('/assets/icons/favicon.ico'), ENT_QUOTES, 'UTF-8'); ?>" sizes="any">
    <?php app_print_styles([
        '/styles/global.css',
        '/styles/header.css',
        '/styles/footer.css',
        '/styles/legal-pages.css',
    ]); ?>
    <title>Пользовательское соглашение (оферта) — Канцария</title>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="main">
    <section class="legal-page">
        <h1>Пользовательское соглашение (публичная оферта)</h1>
        <p class="legal-page__updated">Дата обновления: 27.03.2026</p>

        <h2>1. Общие положения</h2>
        <p>Настоящий документ является публичной офертой Продавца о заключении договора розничной купли-продажи товаров дистанционным способом.</p>

        <h2>2. Термины</h2>
        <ul>
            <li>Продавец — интернет-магазин «Канцария».</li>
            <li>Покупатель — физическое или юридическое лицо, оформившее заказ на сайте.</li>
            <li>Сайт — совокупность страниц, размещенных по доменному имени Продавца.</li>
            <li>Заказ — оформленный запрос Покупателя на приобретение товара.</li>
        </ul>

        <h2>3. Предмет договора</h2>
        <p>Продавец обязуется передать товар в собственность Покупателю, а Покупатель обязуется принять и оплатить товар в порядке и на условиях настоящей оферты.</p>

        <h2>4. Оформление заказа</h2>
        <ul>
            <li>Заказ оформляется Покупателем самостоятельно через интерфейс сайта.</li>
            <li>Покупатель несет ответственность за достоверность и полноту предоставленных данных.</li>
        </ul>

        <h2>5. Цена и оплата</h2>
        <p>Цена товара указывается на сайте на дату оформления заказа. Порядок оплаты определяется разделом <a href="<?php echo htmlspecialchars(app_url('/pages/delivery-payment.php'), ENT_QUOTES, 'UTF-8'); ?>">«Оплата»</a>.</p>

        <h2>6. Доставка и передача товара</h2>
        <p>Порядок доставки определяется разделом <a href="<?php echo htmlspecialchars(app_url('/pages/delivery-payment.php'), ENT_QUOTES, 'UTF-8'); ?>">«Доставка»</a>.</p>

        <h2>7. Возврат и обмен товара</h2>
        <p>Порядок возврата и обмена определяется разделом <a href="<?php echo htmlspecialchars(app_url('/returns.php'), ENT_QUOTES, 'UTF-8'); ?>">«Возврат и обмен»</a>.</p>

        <h2>8. Ответственность сторон</h2>
        <p>Стороны несут ответственность в соответствии с законодательством Российской Федерации и условиями настоящей оферты.</p>

        <h2>9. Прочие условия</h2>
        <p>Продавец вправе вносить изменения в оферту. Актуальная редакция размещается на сайте и вступает в силу с момента публикации.</p>
    </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
