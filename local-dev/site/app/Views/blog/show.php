<?php

declare(strict_types=1);

/**
 * Страница статьи.
 *
 * @var array<string,mixed> $page
 * @var array<string,mixed> $post Полная строка: title, body_html, image_url, published_at, …
 * @var list<array<string,mixed>> $related Другие статьи для блока «Читайте также»
 */

$related = $related ?? [];

$title = (string) ($post['title'] ?? '');
$cover = trim((string) ($post['image_url'] ?? ''));
if ($cover === '') {
    $mapped = article_graphic((string) ($post['slug'] ?? ''));
    if (asset_exists($mapped)) { $cover = asset($mapped); }
}

// Битая/пустая дата → блок не выводим вовсе, а не печатаем '01.01.1970'.
$ts = strtotime((string) ($post['published_at'] ?? ''));

?>
<section class="cp-wrap cp-section">
    <article class="cp-doc cp-art">
        <h1 class="cp-h1"><?= e($title) ?></h1>

        <?php if ($ts !== false): ?>
            <p class="cp-art__meta">
                Опубликовано <time datetime="<?= e(date('Y-m-d', $ts)) ?>"><?= e(date('d.m.Y', $ts)) ?></time>
            </p>
        <?php endif; ?>

        <?php if ($cover !== ''): ?>
            <?php /* Обложек у статей сейчас нет (image_url = NULL) — и страница обязана
                     читаться без них. Никакой заглушки на месте картинки: текст просто
                     начинается сразу под заголовком. Появится файл — обложка включится сама.
                     alt пустой: обложка декоративная, заголовок рядом уже всё сказал. */ ?>
            <img class="cp-art__cover" src="<?= e($cover) ?>" alt="" loading="lazy" decoding="async" width="1200" height="675">
        <?php endif; ?>

        <?php /* body_html — заведомо HTML из БД (чистый набор тегов из db/seed_articles.sql),
                 единственное место на странице без e(). Всё остальное экранируется. */ ?>
        <div class="cp-prose"><?= $post['body_html'] ?? '' ?></div>

        <div class="cp-art__cta">
            <a class="cp-btn cp-btn--primary" href="<?= e(url('/catalog')) ?>">Перейти в каталог</a>
            <?php /* --light, а НЕ --ghost. Ghost сделан под тёмный фон: у него текст
                     #f4f4f5 и полупрозрачная белая заливка. На белом полотне статьи
                     такая кнопка становится белой на белом — видно только рамку.
                     Правило простое: --ghost на тёмном, --light на светлом. */ ?>
            <a class="cp-btn cp-btn--light" href="<?= e(url('/stati')) ?>">Все статьи</a>
        </div>
    </article>
</section>

<?php if ($related !== []): ?>
    <section class="cp-wrap cp-section cp-section--tight">
        <div class="cp-section__head">
            <h2>Читайте также</h2>
            <a class="cp-section__more" href="<?= e(url('/stati')) ?>">Все статьи →</a>
        </div>
        <div class="cp-posts">
            <?php foreach ($related as $p): ?>
                <?php require __DIR__ . '/_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
