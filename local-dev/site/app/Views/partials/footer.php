<?php

declare(strict_types=1);

/**
 * Подвал: навигация + контакты + ПРАВОВАЯ ОГОВОРКА + копирайт + cookie.
 *
 * ⚠️ Блок .cp-ft__legal удалять нельзя. CAT и Caterpillar — чужие товарные
 * знаки, и заявление о том, что мы независимый поставщик, обязано быть
 * на каждой странице (plans/2026-07-15_catpart_plan.md, раздел 0).
 * Текст правится в одном месте — config.php → legal.disclaimer.
 */

$phone    = trim((string) cfg('site.phone', ''));
$phoneRaw = trim((string) cfg('site.phone_raw', ''));
$email    = trim((string) brand_setting('email', cfg('site.email', '')));
$brandPhones = (array) brand_setting('phones', []);
$brandAddress = trim((string) brand_setting('address', ''));
$legal    = trim((string) cfg('site.legal', ''));

?>
<footer class="cp-ft">
    <div class="cp-wrap cp-ft__in">
        <div class="cp-ft__col">
            <div class="cp-logo">
                <span class="cp-logo__mark" aria-hidden="true"><?= e(cfg('site.logo_mark')) ?></span>
                <span class="cp-logo__txt">
                    <span class="cp-logo__name"><?= e(cfg('site.name')) ?></span>
                    <span class="cp-logo__sub"><?= e(cfg('site.tagline')) ?></span>
                </span>
            </div>
            <p class="cp-ft__tag"><?= e(cms_text('partials.footer.text.001')) ?></p>

            <?php /* Пусто в конфиге — блока просто нет. Заглушек не ставим. */ ?>
            <?php if ($phone !== '' && $phoneRaw !== ''): ?>
                <a class="cp-ft__mail" href="tel:<?= e($phoneRaw) ?>"><?= e($phone) ?></a>
            <?php endif; ?>
            <?php if ($email !== ''): ?>
                <a class="cp-ft__mail" href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
            <?php endif; ?>
            <?php foreach ($brandPhones as $phoneItem): ?>
                <a class="cp-ft__mail" href="tel:<?= e((string) ($phoneItem['tel'] ?? '')) ?>"><?= e((string) ($phoneItem['label'] ?? '')) ?></a>
            <?php endforeach; ?>
            <?php if ($brandAddress !== ''): ?><span class="cp-ft__mail"><?= e($brandAddress) ?></span><?php endif; ?>
<?php foreach(array_merge(cms_social_links('messengers'),cms_social_links('socials')) as $social): ?><a class="cp-ft__mail" href="<?= e($social['url']) ?>" rel="noopener noreferrer"><?= e($social['label']) ?></a><?php endforeach; ?>
        </div>

        <div class="cp-ft__col"><b><?= e(cms_text('partials.footer.text.002')) ?></b><?php foreach(cms_menu('footer_catalog') as $item): ?><a href="<?= e(cms_link($item['url'])) ?>"><?= e($item['label']) ?></a><?php endforeach; ?></div>

        <div class="cp-ft__col"><b><?= e(cms_text('partials.footer.text.003')) ?></b><?php foreach(cms_menu('footer_buyers') as $item): ?><a href="<?= e(cms_link($item['url'])) ?>"><?= e($item['label']) ?></a><?php endforeach; ?></div>

        <div class="cp-ft__col"><b><?= e(cms_text('partials.footer.text.004')) ?></b><?php foreach(cms_menu('footer_company') as $item): ?><a href="<?= e(cms_link($item['url'])) ?>"><?= e($item['label']) ?></a><?php endforeach; ?></div>
    </div>

    <div class="cp-wrap cp-ft__legal">
        <p><?= e(cfg('legal.disclaimer')) ?></p>
    </div>

    <div class="cp-wrap cp-ft__copy">
        <span><?= e(cfg('site.name')) ?> · <?= e(cfg('site.domain')) ?> © <?= e(date('Y')) ?><?php
            /* Реквизиты появятся в конфиге — подтянутся сюда сами. */
            if ($legal !== ''): ?> · <?= e($legal) ?><?php endif; ?></span>
        <a href="<?= e(url('/politika-konfidencialnosti')) ?>"><?= e(cms_text('partials.footer.text.005')) ?></a>
    </div>
</footer>

<?php /* Показ и запоминание — в app.js. Скрыт по умолчанию: без JS баннер,
         который нечем закрыть, только мешает. */ ?>
<div class="cp-cookie" id="cpCookie" hidden>
    <div class="cp-wrap cp-cookie__in">
        <p class="cp-cookie__txt"><?= e(cms_text('partials.footer.text.006')) ?>
            <a href="<?= e(url('/politika-konfidencialnosti')) ?>"><?= e(cms_text('partials.footer.text.007')) ?></a>.</p>
        <button class="cp-btn cp-btn--primary cp-btn--sm" id="cpCookieOk" type="button"><?= e(cms_text('partials.footer.text.008')) ?></button>
    </div>
</div>
