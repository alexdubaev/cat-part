<?php

declare(strict_types=1);

/**
 * Оплата.
 *
 * ⚠️ TODO ЗАКАЗЧИКУ — страница сознательно описывает ПОРЯДОК, а не условия.
 * Ничего из перечисленного не подтверждено, выдумывать нельзя:
 *   • способы оплаты: безнал по счёту, карта на сайте, наличные;
 *   • работа с юрлицами и физлицами, договор, отсрочка платежа;
 *   • предоплата — полная, частичная, постоплата;
 *   • закрывающие документы: счёт-фактура, УПД, товарная накладная;
 *   • НДС: работаем с ним или нет (для B2B это первый вопрос);
 *   • эквайринг: есть ли онлайн-оплата и чей (нужен и для 54-ФЗ).
 * Как только ответы будут — сюда встаёт блок «Способы оплаты» списком
 * и реквизиты; часть уедет в config (site.legal, site.inn, site.ogrn).
 *
 * @var array<string,mixed> $page
 */
?>
<section class="cp-wrap cp-section">
    <div class="cp-doc">
        <h1 class="cp-h1"><?php $seoH1=(string)(cms_meta((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH))['seo_h1']??'');if($seoH1!==''): ?><?= e($seoH1) ?><?php else: ?><?= e(cms_text('pages.payment.text.001')) ?><?php endif; ?></h1>
        <div class="cp-prose"><?= cms_html('pages.payment.body') ?></div>
    </div>
</section>

<section class="cp-wrap cp-section cp-section--tight">
    <div class="cp-help">
        <div class="cp-help__in">
            <div>
                <h2 class="cp-help__t"><?= e(cms_text('pages.payment.text.002')) ?></h2>
                <p class="cp-help__d"><?= e(cms_text('pages.payment.text.003')) ?></p>
            </div>
            <div class="cp-help__act">
                <a class="cp-btn cp-btn--primary" href="mailto:<?= e(brand_setting('email', cfg('site.email'))) ?>"><?= e(cms_text('pages.payment.text.004')) ?></a>
                <a class="cp-btn cp-btn--ghost" href="<?= e(url(cms_text('pages.payment.link.001'))) ?>"><?= e(cms_text('pages.payment.text.005')) ?></a>
            </div>
        </div>
    </div>
</section>
