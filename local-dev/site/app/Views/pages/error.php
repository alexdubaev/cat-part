<?php

declare(strict_types=1);

/**
 * Страница ошибки (404 и прочие).
 *
 * $code и $message приходят из Controller::renderNotFound(), но опциональны:
 * без них вьюха рисует обычную 404, а не падает.
 *
 * Заголовок берём НЕ из $page['title']: там лежит мета для <title> вида
 * «Страница не найдена (404) — CatPart». В <h1> название сайта и код
 * в скобках не нужны — это разные по назначению строки.
 *
 * @var array<string,mixed> $page
 * @var int|null $code
 * @var string|null $message
 */

$errCode = (int) ($code ?? 404);

$errHeading = match ($errCode) {
    403     => 'Доступ закрыт',
    500     => 'Ошибка на сервере',
    default => 'Страница не найдена',
};

$errText = trim((string) ($message ?? ''))
    ?: 'Возможно, адрес изменился или в ссылке опечатка. Начните с каталога '
     . 'или найдите деталь по артикулу через поиск.';

?>
<section class="cp-wrap cp-section cp-error">
    <p class="cp-error__code"><?= e((string) $errCode) ?></p>
    <h1 class="cp-h1"><?= e($errHeading) ?></h1>
    <p class="cp-lead"><?= e($errText) ?></p>

    <?php /* С 404 человек чаще всего искал конкретную деталь — даём поиск сразу,
             а не отправляем «на главную и разбирайтесь». */ ?>
    <form class="cp-search cp-search--light" action="<?= e(url('/poisk')) ?>" method="get" role="search">
        <label class="cp-vh" for="cpErrSearch">Поиск по артикулу или названию</label>
        <div class="cp-search__in">
            <svg class="cp-search__ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/>
            </svg>
            <input class="cp-search__inp" id="cpErrSearch" type="search" name="q"
                   placeholder="Поиск по артикулу или названию" autocomplete="off" enterkeyhint="search">
            <button class="cp-search__btn" type="submit">Найти</button>
        </div>
    </form>

    <div class="cp-error__act">
        <a class="cp-btn cp-btn--primary" href="<?= e(url('/catalog')) ?>">В каталог</a>
        <a class="cp-btn cp-btn--light" href="<?= e(url('/')) ?>">На главную</a>
        <a class="cp-btn cp-btn--light" href="<?= e(url('/kontakty')) ?>">Контакты</a>
    </div>
</section>
