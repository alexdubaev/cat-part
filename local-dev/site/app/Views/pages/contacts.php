<?php

declare(strict_types=1);

/**
 * Контакты: тёмный hero с тезисами и картинкой → ряд карточек-контактов →
 * что прислать для быстрого ответа → правовой статус.
 *
 * ⚠️ Сейчас у заказчика на связи ТОЛЬКО почта — телефона, адреса и юрлица нет
 * (config: site.phone / site.address / site.legal пустые). Карточки ниже
 * условные: появится значение в конфиге — карточка покажется сама, без правки
 * шаблона. Заглушек вида «+7 (___) ___-__-__» и карты с офисом «где-то в Москве»
 * тут нет намеренно: выдуманный адрес на странице контактов — это ложь, за
 * которой кто-то поедет.
 *
 * TODO ЗАКАЗЧИКУ: телефон (+ phone_raw для tel:), мессенджеры, адрес и карта,
 * часы работы, юрлицо/ИП + ИНН/ОГРН. Всё это — в config.php, не в вёрстку.
 * Карта появится тогда же, когда адрес: рисовать её раньше нечем.
 *
 * @var array<string,mixed> $page
 */

$phone    = trim((string) cfg('site.phone', ''));
$phoneRaw = trim((string) cfg('site.phone_raw', ''));
$email    = trim((string) brand_setting('email', cfg('site.email', '')));
$brandPhones = (array) brand_setting('phones', []);
$address  = trim((string) brand_setting('address', cfg('site.address', '')));
$legal    = trim((string) cfg('site.legal', ''));
$inn      = trim((string) cfg('site.inn', ''));
$ogrn     = trim((string) cfg('site.ogrn', ''));

/*
 * Картинка hero — тот же приём, что на главной (app/Views/home.php).
 * Промт для генерации: docs/hero-image-prompt.md, раздел 2.
 *
 * Правая колонка рендерится ТОЛЬКО если файл реально лежит на диске. Пока его
 * нет, сетка схлопывается в одну колонку и hero выглядит законченным — вместо
 * битого <img> с крестиком. Файл появится — колонка включится сама.
 */
$heroImage = cms_text('pages.contacts.hero_image');
$heroExists = cms_image_exists($heroImage);

?>
<section class="cp-hero cp-hero--page<?= $heroExists ? ' cp-hero--media' : '' ?>">
    <?php if ($heroExists): ?>
    <div class="cp-hero__media" aria-hidden="true">
        <img class="cp-hero__img" src="<?= e(asset($heroImage)) ?>" alt=""
             loading="eager" fetchpriority="high" decoding="async" width="2560" height="852">
    </div>
    <?php endif; ?>
    <div class="cp-wrap cp-hero__in">
        <div class="cp-hero__col">
            <?php /* Две строки жёстко: акцент должен начинать вторую строку, как в hero
                     главной. На узких экранах заголовок переносится и сам — лишний <br>
                     там просто делает вторую строку короче, ничего не ломая. */ ?>
            <h1 class="cp-hero__title"><?php $seoH1=(string)(cms_meta((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH))['seo_h1']??'');if($seoH1!==''): ?><?= e($seoH1) ?><?php else: ?><?= e(cms_text('pages.contacts.text.001')) ?><br><b><?= e(cms_text('pages.contacts.text.002')) ?></b> <?= e(cms_text('pages.contacts.text.003')) ?><?php endif; ?></h1>
            <p class="cp-hero__text"><?= e(cms_text('pages.contacts.text.004')) ?></p>

            <?php /* Список, а не три абзаца: это перечисление, и скринридер обязан
                     объявить его как список из трёх пунктов. */ ?>
            <ul class="cp-hero__list">
                <li class="cp-hero__li">
                    <svg class="cp-hero__li-ic" aria-hidden="true"><use href="#cp-i-check"></use></svg>
                    <span><b><?= e(cms_text('pages.contacts.text.005')) ?></b> <?= e(cms_text('pages.contacts.text.006')) ?></span>
                </li>
                <li class="cp-hero__li">
                    <svg class="cp-hero__li-ic" aria-hidden="true"><use href="#cp-i-check"></use></svg>
                    <span><b><?= e(cms_text('pages.contacts.text.007')) ?></b> <?= e(cms_text('pages.contacts.text.008')) ?></span>
                </li>
                <li class="cp-hero__li">
                    <svg class="cp-hero__li-ic" aria-hidden="true"><use href="#cp-i-check"></use></svg>
                    <span><b><?= e(cms_text('pages.contacts.text.009')) ?></b> <?= e(cms_text('pages.contacts.text.010')) ?></span>
                </li>
            </ul>

            <div class="cp-hero__cta">
                <?php if ($email !== ''): ?>
                    <a class="cp-btn cp-btn--primary" href="mailto:<?= e($email) ?>"><?= e(cms_text('pages.contacts.text.011')) ?></a>
                <?php endif; ?>
                <a class="cp-btn cp-btn--ghost" href="<?= e(url(cms_text('pages.contacts.link.001'))) ?>"><?= e(cms_text('pages.contacts.text.012')) ?></a>
            </div>
        </div>

    </div>
</section>

<section class="cp-wrap cp-section">
    <?php /* Ряд карточек: только реальные каналы связи. Телефона и адреса в конфиге
             нет — их карточек на странице не будет, и «телефон уточняется» тоже. */ ?>
    <div class="cp-contact">
        <?php if ($email !== ''): ?>
            <div class="cp-contact__item">
                <svg class="cp-contact__ic" aria-hidden="true"><use href="#cp-i-mail"></use></svg>
                <span class="cp-contact__lbl"><?= e(cms_text('pages.contacts.text.013')) ?></span>
                <span class="cp-contact__val">
                    <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
                </span>
                <span class="cp-contact__hint"><?= e(cms_text('pages.contacts.text.014')) ?></span>
            </div>
        <?php endif; ?>

        <?php /* Обе половины телефона обязательны: подпись для глаз и phone_raw для tel:.
                 Есть только одна — карточку не рисуем, иначе ссылка была бы битой. */ ?>
        <?php if ($phone !== '' && $phoneRaw !== ''): ?>
            <div class="cp-contact__item">
                <svg class="cp-contact__ic" aria-hidden="true"><use href="#cp-i-phone"></use></svg>
                <span class="cp-contact__lbl"><?= e(cms_text('pages.contacts.text.015')) ?></span>
                <span class="cp-contact__val">
                    <a href="tel:<?= e($phoneRaw) ?>"><?= e($phone) ?></a>
                </span>
            </div>
        <?php endif; ?>

        <?php foreach ($brandPhones as $phoneItem): ?>
            <div class="cp-contact__item">
                <svg class="cp-contact__ic" aria-hidden="true"><use href="#cp-i-phone"></use></svg>
                <span class="cp-contact__lbl"><?= e(cms_text('pages.contacts.text.016')) ?></span>
                <span class="cp-contact__val"><a href="tel:<?= e((string) ($phoneItem['tel'] ?? '')) ?>"><?= e((string) ($phoneItem['label'] ?? '')) ?></a></span>
            </div>
        <?php endforeach; ?>

        <?php if ($address !== ''): ?>
            <div class="cp-contact__item">
                <svg class="cp-contact__ic" aria-hidden="true"><use href="#cp-i-pin"></use></svg>
                <span class="cp-contact__lbl"><?= e(cms_text('pages.contacts.text.017')) ?></span>
                <span class="cp-contact__val"><?= e($address) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="cp-doc">
        <div class="cp-prose"><?= cms_html('pages.contacts.body') ?></div>
    </div>
</section>

<?php
/*
 * Форма — второй канал связи на странице, где до сих пор была только почта.
 * Стоит ПОСЛЕ «что прислать для быстрого ответа» намеренно: сначала человек
 * читает, что от него нужно, потом решает — писать письмо самому или оставить
 * телефон и разобрать это голосом.
 *
 * ⚠️ Карточка «Телефон» выше по-прежнему условная и не показывается: своего
 * телефона у заказчика в конфиге нет (config.site.phone пуст). Форма просит
 * телефон У КЛИЕНТА — это не то же самое, и заглушкой чужого контакта не является.
 */
$formSource = 'contacts';
$formTitle  = 'Оставить заявку';
require __DIR__ . '/../partials/leadform.php';
?>

<section class="cp-wrap cp-section cp-section--tight">
    <div class="cp-help">
        <div class="cp-help__in">
            <div>
                <h2 class="cp-help__t"><?= e(cms_text('pages.contacts.text.018')) ?></h2>
                <p class="cp-help__d"><?= e(cms_text('pages.contacts.text.019')) ?></p>
            </div>
            <div class="cp-help__act">
                <a class="cp-btn cp-btn--primary" href="<?= e(url(cms_text('pages.contacts.link.002'))) ?>"><?= e(cms_text('pages.contacts.text.020')) ?></a>
                <?php if ($email !== ''): ?>
                    <a class="cp-btn cp-btn--ghost" href="mailto:<?= e($email) ?>"><?= e(cms_text('pages.contacts.text.021')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if(cfg('site.map_widget','')!==''): ?><section class="cp-wrap cp-section"><iframe title="Карта офиса" src="<?= e(cfg('site.map_widget')) ?>" width="100%" height="400" loading="lazy" referrerpolicy="no-referrer-when-downgrade" style="border:0"></iframe></section><?php endif; ?>
<?php if(cfg('site.hours','')!=='' || cfg('site.office','')!=='' || cfg('site.legal','')!==''): ?><section class="cp-wrap cp-section"><div class="cp-prose"><p><?= e(cfg('site.office','')) ?></p><p><?= e(cfg('site.hours','')) ?></p><p><?= e(cfg('site.legal','')) ?> <?= e(cfg('site.inn','')) ?> <?= e(cfg('site.ogrn','')) ?></p></div></section><?php endif; ?>
