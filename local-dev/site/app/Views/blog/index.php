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
    <h1 class="cp-h1">Статьи</h1>
    <p class="cp-lead">Разбираем то, что мешает быстро найти и правильно выбрать деталь: как читается
        номер, чем оригинал отличается от аналога и восстановленной детали, когда ходовая уже просит
        замены и почему поддельный фильтр обходится дороже настоящего.</p>

    <?php if ($posts === []): ?>
        <p class="cp-empty">Статьи готовятся. А пока нужна деталь — напишите номер на
            <a href="mailto:<?= e(cfg('site.email')) ?>"><?= e(cfg('site.email')) ?></a>,
            подберём и проверим применимость.</p>
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
                <h2 class="cp-help__t">Остался вопрос по конкретной машине?</h2>
                <p class="cp-help__d">Статья отвечает на общий вопрос, а подбор — всегда про
                    конкретный номер. Пришлите номер с детали или таблички, модель и серийный номер
                    машины — ответим по применимости.</p>
            </div>
            <div class="cp-help__act">
                <a class="cp-btn cp-btn--primary" href="mailto:<?= e(cfg('site.email')) ?>">Написать нам</a>
                <a class="cp-btn cp-btn--ghost" href="<?= e(url('/catalog')) ?>">Каталог</a>
            </div>
        </div>
    </div>
</section>
