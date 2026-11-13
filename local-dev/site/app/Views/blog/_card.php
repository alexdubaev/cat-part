<?php

declare(strict_types=1);

/**
 * Карточка статьи в ленте. Общий фрагмент для /stati и блока «Читайте также».
 *
 * Зачем отдельный файл: у донора разметка карточки была слово в слово скопирована
 * в blog/index.php и blog/show.php. Два одинаковых куска — это гарантия, что однажды
 * они разъедутся: правишь ленту, «Читайте также» молчит и остаётся прежним.
 *
 * Подключается через require внутри foreach — переменная $p видна из области цикла.
 *
 * @var array<string,mixed> $p Строка статьи: slug, title, excerpt, image_url, published_at
 */

$pTitle   = (string) ($p['title'] ?? '');
$pHref    = url('/stati/' . (string) ($p['slug'] ?? ''));
$pExcerpt = trim((string) ($p['excerpt'] ?? ''));
$pImage   = trim((string) ($p['image_url'] ?? ''));
if ($pImage === '') {
    $mapped = article_graphic((string) ($p['slug'] ?? ''), true);
    if (asset_exists($mapped)) { $pImage = asset($mapped); }
}

// strtotime вернёт false на пустой/битой дате — тогда блок с датой просто не выводим,
// а не печатаем '01.01.1970'.
$pTs = (!empty($p['published_at']) ? (new DateTimeImmutable((string)$p['published_at'],new DateTimeZone('UTC')))->getTimestamp() : false);

?>
<article class="cp-post">
    <?php if ($pImage !== ''): ?>
        <?php /* Обложка выводится ТОЛЬКО если файл реально задан. Сейчас у всех статей
                 image_url = NULL, и карточка обязана выглядеть законченной без неё —
                 никаких «серых квадратов»-заглушек и битых <img> с крестиком.
                 Появится картинка — блок включится сам, править шаблон не нужно. */ ?>
        <div class="cp-post__media">
            <img class="cp-post__img" src="<?= e($pImage) ?>" alt="" loading="lazy" decoding="async" width="600" height="338">
        </div>
    <?php endif; ?>

    <div class="cp-post__body">
        <?php if ($pTs !== false): ?>
            <?php /* <time datetime> — машиночитаемая дата рядом с человеческой. */ ?>
            <time class="cp-post__date" datetime="<?= e(date('Y-m-d', $pTs)) ?>"><?= e(date('d.m.Y', $pTs)) ?></time>
        <?php endif; ?>

        <h3 class="cp-post__title">
            <?php /* Кликабельна вся карточка (CSS растягивает ::after этой ссылки на
                     весь блок), но в ссылку обёрнут ТОЛЬКО заголовок. Донор заворачивал
                     в <a> всю карточку целиком — тогда скринридер зачитывает имя ссылки
                     как «дата + заголовок + анонс + читать статью» одной простынёй. */ ?>
            <a href="<?= e($pHref) ?>"><?= e($pTitle) ?></a>
        </h3>

        <?php if ($pExcerpt !== ''): ?>
            <p class="cp-post__excerpt"><?= e($pExcerpt) ?></p>
        <?php endif; ?>

        <?php /* Декоративный «зов» — не ссылка: ссылка тут уже есть, и дублировать её
                 в дереве доступности незачем. */ ?>
        <span class="cp-post__more" aria-hidden="true">Читать статью →</span>
    </div>
</article>
