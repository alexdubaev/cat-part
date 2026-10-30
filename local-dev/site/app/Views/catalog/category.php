<?php

declare(strict_types=1);

/**
 * Раздел каталога: подразделы + сетка товаров + пагинация + SEO-текст.
 *
 * @var array<string,mixed> $page
 * @var array<string,mixed> $category Строка БД: name, seo_h1, intro_html, …
 * @var string $h1 Заголовок, уже вычисленный контроллером (seo_h1 ?: name)
 * @var list<array<string,mixed>> $children Подразделы
 * @var list<array<string,mixed>> $products id, slug, sku, name, price, in_stock, image_url
 * @var int $pageNum Текущая страница (от 1)
 * @var int $pages   Всего страниц
 * @var int $total   Всего позиций в разделе
 * @var string $baseUrl База для ссылок пагинации ('/catalog/gidravlika')
 */

// $h1 считает контроллер; фолбэк — на случай вызова вьюхи без него.
$h1 = trim((string) ($h1 ?? '')) ?: (trim((string) ($category['seo_h1'] ?? ''))
    ?: (string) ($category['name'] ?? 'Раздел каталога'));

$children = $children ?? [];
$products = $products ?? [];
$pageNum  = max(1, (int) ($pageNum ?? 1));
$pages    = max(1, (int) ($pages ?? 1));
$total    = (int) ($total ?? 0);
$baseUrl  = (string) ($baseUrl ?? '/catalog');

?>
<section class="cp-wrap cp-section">
    <h1 class="cp-h1"><?= e($h1) ?></h1>

    <?php if ($children !== []): ?>
        <div class="cp-subcats">
            <?php foreach ($children as $c): ?>
                <?php $ccnt = (int) ($c['products_count'] ?? 0); ?>
                <a class="cp-subcat" href="<?= e(url('/catalog/' . (string) $c['slug'])) ?>">
                    <span><?= e($c['name']) ?></span>
                    <?php if ($ccnt > 0): ?>
                        <span class="cp-subcat__cnt"><?= e(number_format($ccnt, 0, ',', ' ')) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($products === []): ?>
        <p class="cp-empty">В этом разделе пока нет позиций. Напишите номер детали на
            <a href="mailto:<?= e(cfg('site.email')) ?>"><?= e(cfg('site.email')) ?></a> —
            проверим наличие и подберём аналог.</p>
    <?php else: ?>
        <p class="cp-count">
            Найдено: <?= e(number_format($total, 0, ',', ' ')) ?><?php if ($pages > 1): ?>
                · страница <?= e((string) $pageNum) ?> из <?= e((string) $pages) ?><?php endif; ?>
        </p>

        <div class="cp-grid cp-grid--prod">
            <?php foreach ($products as $p): ?>
                <?php
                $href  = url('/tovar/' . (string) $p['slug']);
                $price = (float) ($p['price'] ?? 0);
                $sku   = trim((string) ($p['sku'] ?? ''));
                $img   = trim((string) ($p['image_url'] ?? ''));
                ?>
                <article class="cp-prod">
                    <a class="cp-prod__img" href="<?= e($href) ?>" tabindex="-1" aria-hidden="true">
                        <?php if ($img !== ''): ?>
                            <img src="<?= e($img) ?>" alt="" loading="lazy" width="240" height="180">
                        <?php else: ?>
                            <?php /* Фото есть далеко не у всех позиций — знак вместо
                                     битой картинки держит сетку ровной. */ ?>
                            <svg class="cp-prod__ph" aria-hidden="true"><use href="#cp-i-part"></use></svg>
                        <?php endif; ?>
                    </a>
                    <div class="cp-prod__body">
                        <?php if ($sku !== ''): ?>
                            <span class="cp-prod__sku"><?= e($sku) ?></span>
                        <?php endif; ?>
                        <a class="cp-prod__name" href="<?= e($href) ?>"><?= e($p['name']) ?></a>

                        <?php if (!empty($p['in_stock'])): ?>
                            <span class="cp-prod__stock cp-prod__stock--in">В наличии</span>
                        <?php else: ?>
                            <span class="cp-prod__stock cp-prod__stock--out">Под заказ</span>
                        <?php endif; ?>

                        <div class="cp-prod__foot">
                            <?php /* money() сам отдаёт «по запросу» при NULL/0 — но стиль
                                     у цены и у запроса разный, отсюда отдельный класс. */ ?>
                            <span class="cp-prod__price<?= $price > 0 ? '' : ' cp-prod__price--ask' ?>"><?= e(money($p['price'] ?? null)) ?></span>
                            <form method="post" action="<?= e(url('/korzina/add')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= e((string) $p['id']) ?>">
                                <button class="cp-btn cp-btn--light cp-btn--sm" type="submit">В корзину</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <?php
            /**
             * 🔥 Баг донора: пагинация печатала ВСЕ номера страниц подряд
             * (`for ($i = 1; $i <= $pages; $i++)`). При 22 000 товаров по 24 на
             * страницу это 833 ссылки в каждом ответе — лишние килобайты,
             * нечитаемая «простыня» и размытый краулинговый бюджет.
             *
             * Окно ±3 + первая/последняя + многоточия считает paginate_window(),
             * адреса собирает page_url() — те же функции зовёт вьюха поиска.
             * Своя копия логики в шаблоне означала бы, что листание каталога и
             * листание поиска однажды разъедутся. page_url() к тому же сам
             * выбирает '?' или '&' — для /poisk?q=… это обязательно.
             */
            ?>
            <nav class="cp-pager" aria-label="Страницы раздела">
                <?php if ($pageNum > 1): ?>
                    <a class="cp-pager__nav" href="<?= e(page_url($baseUrl, $pageNum - 1)) ?>" rel="prev">← Назад</a>
                <?php endif; ?>

                <?php foreach (paginate_window($pageNum, $pages) as $item): ?>
                    <?php if ($item === '…'): ?>
                        <span class="cp-pager__gap" aria-hidden="true">…</span>
                    <?php elseif ((int) $item === $pageNum): ?>
                        <span class="cp-pager__cur" aria-current="page"><?= e((string) $item) ?></span>
                    <?php else: ?>
                        <a href="<?= e(page_url($baseUrl, (int) $item)) ?>"
                           aria-label="Страница <?= e((string) $item) ?>"><?= e((string) $item) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($pageNum < $pages): ?>
                    <a class="cp-pager__nav" href="<?= e(page_url($baseUrl, $pageNum + 1)) ?>" rel="next">Вперёд →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if (!empty($category['intro_html'])): ?>
    <section class="cp-wrap cp-section cp-section--tight">
        <?php /* intro_html — заведомо HTML из БД, чистится на импорте, не тут. */ ?>
        <div class="cp-doc"><div class="cp-prose"><?= $category['intro_html'] ?></div></div>
    </section>
<?php endif; ?>

<?php
/*
 * Форма внизу раздела. Здесь она уместнее, чем где-либо: человек долистал раздел
 * до конца — значит, скорее всего, не нашёл свою деталь. Пустой раздел (позиций
 * ещё нет) даёт ту же ситуацию, поэтому блок показывается всегда, а не только
 * при непустой выдаче.
 *
 * Заголовок отличается от главной: там «оставить заявку на подбор» вообще, тут —
 * ответ на конкретную неудачу поиска. Текст в партиале нейтральный, сроков не обещает.
 */
$formSource = 'catalog';
$formTitle  = 'Не нашли нужную деталь?';
require __DIR__ . '/../partials/leadform.php';
?>
