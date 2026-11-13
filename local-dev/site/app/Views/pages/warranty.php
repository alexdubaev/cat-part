<?php

declare(strict_types=1);

/**
 * Гарантия и возврат.
 *
 * ⚠️ TODO ЗАКАЗЧИКУ — тут особенно опасно фантазировать: срок гарантии,
 * напечатанный на сайте, — это публичное обязательство, и спорить потом
 * придётся не с нами. Не подтверждено ничего из списка:
 *   • гарантийный срок: свой или производителя, единый или по группам;
 *   • отличается ли гарантия на оригинал и на аналог;
 *   • порядок возврата и обмена, за чей счёт пересылка;
 *   • есть ли возврат «не подошло» и в какой срок;
 *   • нужна ли экспертиза и кто её проводит;
 *   • исключения: расходники, электрика, детали со следами установки.
 * Пока страница объясняет ПОРЯДОК действий и то, что зависит от закона,
 * а не выдуманные сроки.
 *
 * @var array<string,mixed> $page
 */
?>
<section class="cp-wrap cp-section">
    <div class="cp-doc">
        <h1 class="cp-h1"><?php $seoH1=(string)(cms_meta((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH))['seo_h1']??'');if($seoH1!==''): ?><?= e($seoH1) ?><?php else: ?><?= e(cms_text('pages.warranty.text.001')) ?><?php endif; ?></h1>
        <div class="cp-prose"><?= cms_html('pages.warranty.body') ?></div>
    </div>
</section>

<section class="cp-wrap cp-section cp-section--tight">
    <div class="cp-help">
        <div class="cp-help__in">
            <div>
                <h2 class="cp-help__t"><?= e(cms_text('pages.warranty.text.002')) ?></h2>
                <p class="cp-help__d"><?= e(cms_text('pages.warranty.text.003')) ?></p>
            </div>
            <div class="cp-help__act">
                <a class="cp-btn cp-btn--primary" href="mailto:<?= e(brand_setting('email', cfg('site.email'))) ?>"><?= e(cms_text('pages.warranty.text.004')) ?></a>
            </div>
        </div>
    </div>
</section>
