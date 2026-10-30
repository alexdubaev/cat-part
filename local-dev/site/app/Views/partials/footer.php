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
$email    = trim((string) cfg('site.email', ''));
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
            <p class="cp-ft__tag">Подбираем и поставляем запчасти для техники Caterpillar по номеру
                детали, кросс-номеру или описанию узла. Оригинал и совместимые аналоги.</p>

            <?php /* Пусто в конфиге — блока просто нет. Заглушек не ставим. */ ?>
            <?php if ($phone !== '' && $phoneRaw !== ''): ?>
                <a class="cp-ft__mail" href="tel:<?= e($phoneRaw) ?>"><?= e($phone) ?></a>
            <?php endif; ?>
            <?php if ($email !== ''): ?>
                <a class="cp-ft__mail" href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
            <?php endif; ?>
        </div>

        <div class="cp-ft__col">
            <b>Каталог</b>
            <a href="<?= e(url('/catalog')) ?>">Все разделы</a>
            <a href="<?= e(url('/catalog/gidravlika')) ?>">Гидравлика</a>
            <a href="<?= e(url('/catalog/dvigatel')) ?>">Двигатель</a>
            <a href="<?= e(url('/catalog/hodovaya-chast')) ?>">Ходовая часть</a>
            <a href="<?= e(url('/catalog/filtry-i-zhidkosti')) ?>">Фильтры и жидкости</a>
        </div>

        <div class="cp-ft__col">
            <b>Покупателям</b>
            <a href="<?= e(url('/dostavka')) ?>">Доставка</a>
            <a href="<?= e(url('/oplata')) ?>">Оплата</a>
            <a href="<?= e(url('/garantiya')) ?>">Гарантия и возврат</a>
            <a href="<?= e(url('/korzina')) ?>">Корзина</a>
        </div>

        <div class="cp-ft__col">
            <b>Компания</b>
            <a href="<?= e(url('/o-kompanii')) ?>">О компании</a>
            <a href="<?= e(url('/kontakty')) ?>">Контакты</a>
            <a href="<?= e(url('/politika-konfidencialnosti')) ?>">Политика конфиденциальности</a>
        </div>
    </div>

    <div class="cp-wrap cp-ft__legal">
        <p><?= e(cfg('legal.disclaimer')) ?></p>
    </div>

    <div class="cp-wrap cp-ft__copy">
        <span><?= e(cfg('site.name')) ?> · <?= e(cfg('site.domain')) ?> © <?= e(date('Y')) ?><?php
            /* Реквизиты появятся в конфиге — подтянутся сюда сами. */
            if ($legal !== ''): ?> · <?= e($legal) ?><?php endif; ?></span>
        <a href="<?= e(url('/politika-konfidencialnosti')) ?>">Политика конфиденциальности</a>
    </div>
</footer>

<?php /* Показ и запоминание — в app.js. Скрыт по умолчанию: без JS баннер,
         который нечем закрыть, только мешает. */ ?>
<div class="cp-cookie" id="cpCookie" hidden>
    <div class="cp-wrap cp-cookie__in">
        <p class="cp-cookie__txt">Мы используем cookie, чтобы сайт работал корректно и было понятно,
            какие разделы каталога полезны. Продолжая пользоваться сайтом, вы соглашаетесь с
            <a href="<?= e(url('/politika-konfidencialnosti')) ?>">политикой конфиденциальности</a>.</p>
        <button class="cp-btn cp-btn--primary cp-btn--sm" id="cpCookieOk" type="button">Принять</button>
    </div>
</div>
