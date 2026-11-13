<?php

declare(strict_types=1);

/**
 * О компании.
 *
 * ⚠️ TODO ЗАКАЗЧИКУ — фактура, которой у нас нет и которую нельзя выдумывать:
 *   • юрлицо/ИП, ИНН, ОГРН (лягут в config: site.legal / site.inn / site.ogrn);
 *   • год начала работы, число сотрудников;
 *   • есть ли собственный склад и где, регионы присутствия;
 *   • объём номенклатуры (цифру из parts.cat.com брать нельзя — это ИХ каталог);
 *   • работа с юрлицами/физлицами, отсрочка, договоры.
 * До ответов страница говорит только о том, что мы реально делаем.
 *
 * @var array<string,mixed> $page
 */
?>
<section class="cp-wrap cp-section">
    <div class="cp-doc">
        <h1 class="cp-h1"><?php $seoH1=(string)(cms_meta((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH))['seo_h1']??'');if($seoH1!==''): ?><?= e($seoH1) ?><?php else: ?><?= e(cms_text('pages.about.text.001')) ?><?php endif; ?></h1>
        <div class="cp-prose"><?= cms_html('pages.about.body') ?></div>
    </div>
</section>

<section class="cp-wrap cp-section cp-section--tight">
    <div class="cp-help">
        <div class="cp-help__in">
            <div>
                <h2 class="cp-help__t"><?= e(cms_text('pages.about.text.002')) ?></h2>
                <p class="cp-help__d"><?= e(cms_text('pages.about.text.003')) ?></p>
            </div>
            <div class="cp-help__act">
                <a class="cp-btn cp-btn--primary" href="mailto:<?= e(brand_setting('email', cfg('site.email'))) ?>"><?= e(cms_text('pages.about.text.004')) ?></a>
                <a class="cp-btn cp-btn--ghost" href="<?= e(url(cms_text('pages.about.link.001'))) ?>"><?= e(cms_text('pages.about.text.005')) ?></a>
            </div>
        </div>
    </div>
</section>
