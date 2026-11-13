<?php

declare(strict_types=1);

/**
 * Раздел «Статьи» — лента.
 *
 * @var array<string,mixed> $page
 * @var list<array<string,mixed>> $posts Строки: slug, title, excerpt, image_url, published_at
 */

$posts = $posts ?? [];

?>
<section class="cp-wrap cp-section">
    <h1 class="cp-h1"><?= e(cms_text('blog.index.text.001')) ?></h1>
    <p class="cp-lead"><?= e(cms_text('blog.index.text.002')) ?></p>

    <?php if ($posts === []): ?>
        <p class="cp-empty"><?= e(cms_text('blog.index.text.003')) ?>
            <a href="mailto:<?= e(brand_setting('email', cfg('site.email'))) ?>"><?= e(brand_setting('email', cfg('site.email'))) ?></a><?= e(cms_text('blog.index.text.004')) ?></p>
    <?php else: ?>
        <div class="cp-posts">
            <?php foreach ($posts as $p): ?>
                <?php require __DIR__ . '/_card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="cp-wrap cp-section cp-section--tight">
    <div class="cp-help">
        <div class="cp-help__in">
            <div>
                <h2 class="cp-help__t"><?= e(cms_text('blog.index.text.005')) ?></h2>
                <p class="cp-help__d"><?= e(cms_text('blog.index.text.006')) ?></p>
            </div>
            <div class="cp-help__act">
                <a class="cp-btn cp-btn--primary" href="mailto:<?= e(brand_setting('email', cfg('site.email'))) ?>"><?= e(cms_text('blog.index.text.007')) ?></a>
                <a class="cp-btn cp-btn--ghost" href="<?= e(url(cms_text('blog.index.link.001'))) ?>"><?= e(cms_text('blog.index.text.008')) ?></a>
            </div>
        </div>
    </div>
</section>
