<?php

declare(strict_types=1);

/**
 * Доставка.
 *
 * ⚠️ TODO ЗАКАЗЧИКУ — без этих данных страница остаётся описанием ПОРЯДКА,
 * а не условий. Ни одного срока и тарифа тут выдумывать нельзя:
 *   • транспортные компании, с которыми работаем (СДЭК, ПЭК, Деловые линии…);
 *   • сроки отгрузки со склада и сроки поставки под заказ;
 *   • тарифы: за чей счёт доставка, есть ли бесплатный порог;
 *   • есть ли самовывоз и по какому адресу (адреса в конфиге нет);
 *   • отгрузочные документы, страхование груза, доставка негабарита.
 * Когда ответы будут — сюда добавляется таблица «направление / срок / стоимость».
 *
 * @var array<string,mixed> $page
 */
?>
<section class="cp-wrap cp-section">
    <div class="cp-doc">
        <h1 class="cp-h1"><?php $seoH1=(string)(cms_meta((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH))['seo_h1']??'');if($seoH1!==''): ?><?= e($seoH1) ?><?php else: ?><?= e(cms_text('pages.delivery.text.001')) ?><?php endif; ?></h1>
        <div class="cp-prose"><?= cms_html('pages.delivery.body') ?></div>
    </div>
</section>

<section class="cp-wrap cp-section cp-section--tight">
    <div class="cp-help">
        <div class="cp-help__in">
            <div>
                <h2 class="cp-help__t"><?= e(cms_text('pages.delivery.text.002')) ?></h2>
                <p class="cp-help__d"><?= e(cms_text('pages.delivery.text.003')) ?></p>
            </div>
            <div class="cp-help__act">
                <a class="cp-btn cp-btn--primary" href="mailto:<?= e(brand_setting('email', cfg('site.email'))) ?>"><?= e(cms_text('pages.delivery.text.004')) ?></a>
            </div>
        </div>
    </div>
</section>
