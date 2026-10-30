<?php

declare(strict_types=1);

/**
 * Шапка: логотип «CP», крупный поиск по артикулу, меню, корзина, бургер.
 *
 * Две строки не для красоты: поиск по артикулу — вход №1 в каталог запчастей
 * (человек приходит с номером, снятым с детали), поэтому он в первой строке
 * во всю ширину, а не спрятан за иконкой-лупой.
 *
 * ⚠️ Логотип — текстовый знак из cfg('site.logo_mark') = «CP». Никаких
 * треугольников и производных от знака Caterpillar (plans/, раздел 0).
 */

$cartCount = cart_count();

// Телефона у заказчика сейчас нет (config: site.phone = ''). Блок просто
// не выводится — заглушки вида «+7 (___)» в вёрстке не появляется.
$phone     = trim((string) cfg('site.phone', ''));
$phoneRaw  = trim((string) cfg('site.phone_raw', ''));
$email     = trim((string) cfg('site.email', ''));

// Возврат запроса в поле после перехода на /poisk — иначе человек теряет
// то, что искал, и печатает заново.
$q = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';

?>
<header class="cp-hd">
    <div class="cp-wrap cp-hd__top">
        <a class="cp-logo cp-logo--img" href="<?= e(url('/')) ?>" aria-label="<?= e(cfg('site.name')) ?> — на главную">
            <img class="cp-logo__img" src="<?= e(asset('img/logo.png')) ?>" alt="<?= e(cfg('site.name')) ?>" width="266" height="34">
        </a>

        <form class="cp-search" action="<?= e(url('/poisk')) ?>" method="get" role="search">
            <label class="cp-vh" for="cpSearchInput">Поиск по каталогу</label>
            <div class="cp-search__in">
                <svg class="cp-search__ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/>
                </svg>
                <input class="cp-search__inp" id="cpSearchInput" type="search" name="q"
                       value="<?= e($q) ?>"
                       placeholder="Поиск по артикулу или названию"
                       autocomplete="off" enterkeyhint="search">
                <button class="cp-search__btn" type="submit">Найти</button>
            </div>
        </form>

        <div class="cp-hd__actions">
            <?php if ($phone !== '' && $phoneRaw !== ''): ?>
                <span class="cp-hd__mail">
                    <a href="tel:<?= e($phoneRaw) ?>"><?= e($phone) ?></a>
                </span>
            <?php elseif ($email !== ''): ?>
                <span class="cp-hd__mail">
                    <span>Заявки и подбор</span>
                    <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
                </span>
            <?php endif; ?>

            <a class="cp-cart" href="<?= e(url('/korzina')) ?>" aria-label="Корзина<?= $cartCount > 0 ? ', позиций: ' . $cartCount : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>
                </svg>
                <?php if ($cartCount > 0): ?>
                    <span class="cp-cart__badge"><?= e((string) $cartCount) ?></span>
                <?php endif; ?>
            </a>

            <button class="cp-burger" id="cpBurger" type="button"
                    aria-label="Меню" aria-expanded="false" aria-controls="cpNavBar">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>

    <div class="cp-hd__bar" id="cpNavBar">
        <nav class="cp-wrap cp-nav" aria-label="Главное меню">
            <a class="cp-nav__lnk cp-nav__lnk--accent" href="<?= e(url('/catalog')) ?>">Каталог</a>
            <a class="cp-nav__lnk" href="<?= e(url('/dostavka')) ?>">Доставка</a>
            <a class="cp-nav__lnk" href="<?= e(url('/oplata')) ?>">Оплата</a>
            <a class="cp-nav__lnk" href="<?= e(url('/garantiya')) ?>">Гарантия</a>
            <?php /* «Статьи» — рядом с «Контактами», по решению заказчика. */ ?>
            <a class="cp-nav__lnk" href="<?= e(url('/stati')) ?>">Статьи</a>
            <a class="cp-nav__lnk" href="<?= e(url('/kontakty')) ?>">Контакты</a>
        </nav>
    </div>
</header>
