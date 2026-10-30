<?php

declare(strict_types=1);

/**
 * Блок «Оставить заявку» — переиспользуемая форма (главная, контакты, раздел каталога).
 *
 * Подключается через require прямо из вьюхи, поэтому видит её локальные переменные.
 * Оба параметра необязательны — партиал самодостаточен и без них:
 *
 *   $formTitle  — заголовок блока (дефолт ниже);
 *   $formSource — метка «откуда пришла заявка»: 'home' | 'contacts' | 'catalog'.
 *                 Уезжает в скрытое поле source и попадает в тему письма: без неё
 *                 в почте лежит десяток одинаковых заявок без намёка на страницу.
 *
 * ⚠️ ПОЛЯ: имя + телефон, и всё. Ни «Комментарий», ни e-mail, ни модель техники,
 * ни артикул. Это единое требование заказчика по всем его сайтам, а не недоделка:
 * захотелось добавить поле — сначала спросите заказчика, потом правьте.
 *
 * ⚠️ ФАКТЫ: рядом с формой нет ни одного обещания — ни «перезвоним за 15 минут»,
 * ни сроков, ни гарантий. Ничего этого нам не называли (plans/2026-07-15_catpart_plan.md,
 * «Открытые вопросы»), а срок ответа на витрине — это обещание, которое кто-то предъявит.
 *
 * ⚠️ ОДИН БЛОК НА СТРАНИЦУ. На это завязаны три вещи: id полей (label→for), якорь
 * #zayavka (на него возвращает LeadController после отправки без JS) и флеш-сообщение
 * (getFlash читает и гасит ключ — второму блоку не досталось бы ничего).
 *
 * @var string|null $formTitle
 * @var string|null $formSource
 */

use App\Core\Session;

$lfTitle  = trim((string) ($formTitle ?? '')) ?: 'Оставить заявку на подбор';
$lfSource = trim((string) ($formSource ?? '')) ?: 'site';

/*
 * Результат отправки БЕЗ JS. LeadController кладёт его во флеш и редиректит назад
 * на #zayavka — показать ответ больше негде, страница перезагрузилась.
 * С JS эти ключи всегда пусты: fetch редиректу не следует, ответ рисует app.js.
 */
$lfOk  = trim((string) Session::getFlash('lead_ok', ''));
$lfErr = trim((string) Session::getFlash('lead_error', ''));

/*
 * Введённое возвращаем на место при ошибке: без JS страница перезагрузилась целиком,
 * и заставлять человека набирать телефон заново из-за опечатки в нём же — верный
 * способ не получить вторую попытку.
 */
$lfOld   = (array) Session::getFlash('lead_old', []);
$lfName  = trim((string) ($lfOld['name'] ?? ''));
$lfPhone = trim((string) ($lfOld['phone'] ?? ''));

// Успех и ошибка делят один узел: JS пишет в него же, второй копии разметки не нужно.
$lfMsg = $lfOk !== '' ? $lfOk : $lfErr;

?>
<section class="cp-wrap cp-section cp-section--tight" id="zayavka">
    <div class="cp-leadform">
        <div class="cp-leadform__in">
            <div class="cp-leadform__txt">
                <h2 class="cp-leadform__t"><?= e($lfTitle) ?></h2>
                <p class="cp-leadform__d">Оставьте имя и телефон — свяжемся с вами, чтобы уточнить
                    деталь и условия поставки.</p>
                <p class="cp-leadform__note">Пригодится к разговору: номер с детали или таблички,
                    модель и серийный номер машины. Номера нет — определим деталь по узлу,
                    это нормальная рабочая ситуация.</p>
            </div>

            <?php /* Без JS это обычный POST-переход: action ведёт на реальный маршрут,
                     method=post, поля именованные. data-lead — только зацепка для app.js,
                     на работоспособность формы она не влияет. */ ?>
            <form class="cp-leadform__form" method="post" action="<?= e(url('/zayavka')) ?>" data-lead>
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="<?= e($lfSource) ?>">

                <?php /* role=status — вежливая live-область: скринридер объявит ответ сервера,
                         не перебивая пользователя. hidden снимает app.js. */ ?>
                <p class="cp-leadform__msg<?= $lfMsg === '' ? '' : ($lfOk !== '' ? ' cp-leadform__msg--ok' : ' cp-leadform__msg--err') ?>"
                   data-lead-msg role="status"<?= $lfMsg === '' ? ' hidden' : '' ?>><?= e($lfMsg) ?></p>

                <label class="cp-fld">
                    <span class="cp-fld__lbl">Имя</span>
                    <input class="cp-fld__inp" type="text" name="name" required
                           autocomplete="name" maxlength="120" placeholder="Как к вам обращаться"
                           value="<?= e($lfName) ?>">
                </label>

                <label class="cp-fld">
                    <span class="cp-fld__lbl">Телефон</span>
                    <?php /* type=tel + inputmode=tel — на телефоне открывается цифровая клавиатура.
                             Формат не навязываем маской: человек диктует номер как привык,
                             а «минимум 10 цифр» проверит сервер (см. LeadController). */ ?>
                    <input class="cp-fld__inp" type="tel" name="phone" required
                           autocomplete="tel" inputmode="tel" maxlength="32"
                           placeholder="+7 900 000-00-00" value="<?= e($lfPhone) ?>">
                </label>

                <?php /* Ловушка для ботов. Не type=hidden: скрытые поля бот пропускает, а видимое
                         текстовое с «человеческим» именем — заполняет. Прячем стилями
                         (.cp-leadform__hp уводит за левый край), tabindex=-1 и aria-hidden убирают
                         поле с пути клавиатуры и скринридера, autocomplete=off — чтобы его не
                         набил менеджер паролей за живого человека. */ ?>
                <div class="cp-leadform__hp" aria-hidden="true">
                    <label for="cp-lead-website">Сайт</label>
                    <input id="cp-lead-website" type="text" name="website"
                           tabindex="-1" autocomplete="off">
                </div>

                <label class="cp-leadform__agree">
                    <input type="checkbox" name="consent" value="1" required>
                    <span>Согласен на обработку персональных данных и принимаю
                        <a href="<?= e(url('/politika-konfidencialnosti')) ?>" target="_blank" rel="noopener">политику
                        конфиденциальности</a>.</span>
                </label>

                <?php /* Иконка + текст. Горизонтальный padding и центрирование содержимого дают
                         .cp-btn и .cp-btn--primary (раздел 4 site.css) — здесь их не переопределяем
                         намеренно: обнулишь padding у кнопки с иконкой, и знак слипнется с краем. */ ?>
                <button class="cp-btn cp-btn--primary cp-leadform__btn" type="submit">
                    <svg class="cp-leadform__btn-ic" aria-hidden="true"><use href="#cp-i-mail"></use></svg>
                    <span>Отправить заявку</span>
                </button>
            </form>
        </div>
    </div>
</section>
