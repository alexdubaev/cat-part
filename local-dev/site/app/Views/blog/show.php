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

$title = (string)(($post['seo_h1']??'') ?: ($post['title']??''));
$cover = trim((string) ($post['image_url'] ?? ''));
if ($cover === '') {
    $mapped = article_graphic((string) ($post['slug'] ?? ''));
    if (asset_exists($mapped)) { $cover = asset($mapped); }
}

// Битая/пустая дата → блок не выводим вовсе, а не печатаем '01.01.1970'.
$rawDate=trim((string)($post['published_at']??''));
$ts=$rawDate!==''?(new DateTimeImmutable($rawDate,new DateTimeZone('UTC')))->getTimestamp():false;

?>
<section class="cp-wrap cp-section">
    <article class="cp-doc cp-art">
        <h1 class="cp-h1"><?= e($title) ?></h1>

        <?php if ($ts !== false): ?>
            <p class="cp-art__meta">
                <?= e(cms_text('blog.show.text.001')) ?> <time datetime="<?= e(date('Y-m-d', $ts)) ?>"><?= e(date('d.m.Y', $ts)) ?></time>
            </p>
        <?php endif; ?>

        <?php if ($cover !== ''): ?>
            <?php /* Обложек у статей сейчас нет (image_url = NULL) — и страница обязана
                     читаться без них. Никакой заглушки на месте картинки: текст просто
                     начинается сразу под заголовком. Появится файл — обложка включится сама.
                     alt пустой: обложка декоративная, заголовок рядом уже всё сказал. */ ?>
            <img class="cp-art__cover" src="<?= e($cover) ?>" alt="<?= e($post['cover_alt']??'') ?>" loading="lazy" decoding="async" width="1200" height="675">
        <?php endif; ?>

        <?php /* body_html — заведомо HTML из БД (чистый набор тегов из db/seed_articles.sql),
                 единственное место на странице без e(). Всё остальное экранируется. */ ?>
        <div class="cp-prose"><?= $post['body_html'] ?? '' ?></div>
<?php if(!empty($post['author'])): ?><p class="cp-art__meta"><?= e($post['author']) ?></p><?php endif; ?>

        <div class="cp-art__cta">
            <a class="cp-btn cp-btn--primary" href="<?= e(url(cms_text('blog.show.link.001'))) ?>"><?= e(cms_text('blog.show.text.002')) ?></a>
            <?php /* --light, а НЕ --ghost. Ghost сделан под тёмный фон: у него текст
                     #f4f4f5 и полупрозрачная белая заливка. На белом полотне статьи
                     такая кнопка становится белой на белом — видно только рамку.
                     Правило простое: --ghost на тёмном, --light на светлом. */ ?>
            <a class="cp-btn cp-btn--light" href="<?= e(url(cms_text('blog.show.link.002'))) ?>"><?= e(cms_text('blog.show.text.003')) ?></a>
        </div>
    </article>
</section>

<?php if ($related !== []): ?>
    <section class="cp-wrap cp-section cp-section--tight">
        <div class="cp-section__head">
            <h2><?= e(cms_text('blog.show.text.004')) ?></h2>
            <a class="cp-section__more" href="<?= e(url(cms_text('blog.show.link.003'))) ?>"><?= e(cms_text('blog.show.text.005')) ?></a>
        </div>
        <div class="cp-posts">
            <?php foreach ($related as $p): ?>
                <?php require __DIR__ . '/_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php $productIds=json_decode((string)($post['related_products']??'[]'),true);$relatedProducts=[];foreach(is_array($productIds)?$productIds:[] as $productId){$product=\App\Models\Product::findActiveById((int)$productId);if($product!==null)$relatedProducts[]=$product;} ?>
<?php if($relatedProducts!==[]): ?><section class="cp-wrap cp-section"><h2>Товары по теме</h2><div class="cp-grid cp-grid--prod"><?php foreach($relatedProducts as $product): ?><article class="cp-prod"><div class="cp-prod__body"><span class="cp-prod__sku"><?= e($product['sku']) ?></span><a class="cp-prod__name" href="<?= e(url('/tovar/'.$product['slug'])) ?>"><?= e($product['name']) ?></a><span><?= e(money($product['price']??null)) ?></span></div></article><?php endforeach; ?></div></section><?php endif; ?>
