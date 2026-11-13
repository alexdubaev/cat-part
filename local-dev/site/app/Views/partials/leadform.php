<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Session;

$lfText=static fn(string $key,string $fallback):string => (string)App::config('forms.'.$key,$fallback);
$lfSource = trim((string)($formSource??''))?:'site';
$lfTitle=trim((string)App::config('forms.lead_titles.'.$lfSource,''))?:trim((string)App::config('forms.lead_title',''))?:trim((string)($formTitle??''))?:'Оставить заявку на подбор';
$leadToken=bin2hex(random_bytes(32));
if(!is_string(Session::get('request_owner')))Session::set('request_owner',bin2hex(random_bytes(32)));
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
$lfOld = (array)Session::getFlash('lead_old',[]);
$lfErrors=(array)Session::getFlash('lead_field_errors',[]);
$lfFieldError=static fn(string $key):string => isset($lfErrors[$key])?'<span class="cp-form-error" data-field-error="'.$key.'">'.e($lfErrors[$key]).'</span>':'';
$lfName  = trim((string) ($lfOld['name'] ?? ''));
$lfPhone = trim((string) ($lfOld['phone'] ?? ''));
$lfEmail=(string)($lfOld['email']??'');
$lfComment=(string)($lfOld['comment']??'');

// Успех и ошибка делят один узел: JS пишет в него же, второй копии разметки не нужно.
$lfMsg = $lfOk !== '' ? $lfOk : $lfErr;

?>
<section class="cp-wrap cp-section cp-section--tight" id="zayavka">
    <div class="cp-leadform">
        <div class="cp-leadform__in">
            <div class="cp-leadform__txt">
                <h2 class="cp-leadform__t"><?= e($lfTitle) ?></h2>
                <p class="cp-leadform__d"><?= e($lfText('lead_hint','Оставьте имя и телефон — свяжемся с вами, чтобы уточнить деталь и условия поставки.')) ?></p>
                <p class="cp-leadform__note"><?= e($lfText('lead_note','Пригодится к разговору: номер с детали или таблички, модель и серийный номер машины. Номера нет — определим деталь по узлу, это нормальная рабочая ситуация.')) ?></p>
            </div>

            <?php /* Без JS это обычный POST-переход: action ведёт на реальный маршрут,
                     method=post, поля именованные. data-lead — только зацепка для app.js,
                     на работоспособность формы она не влияет. */ ?>
            <form class="cp-leadform__form" method="post" action="<?= e(url('/zayavka')) ?>" data-lead>
                <?= csrf_field() ?>
                <input type="hidden" name="lead_token" value="<?= e($leadToken) ?>">
                <input type="hidden" name="source" value="<?= e($lfSource) ?>">

                <?php /* role=status — вежливая live-область: скринридер объявит ответ сервера,
                         не перебивая пользователя. hidden снимает app.js. */ ?>
                <p class="cp-leadform__msg<?= $lfMsg === '' ? '' : ($lfOk !== '' ? ' cp-leadform__msg--ok' : ' cp-leadform__msg--err') ?>"
                   data-lead-msg role="status"<?= $lfMsg === '' ? ' hidden' : '' ?>><?= e($lfMsg) ?></p>

                <label class="cp-fld">
                    <span class="cp-fld__lbl"><?= e($lfText('name_label','Имя')) ?></span>
                    <input class="cp-fld__inp" type="text" name="name" required
                           autocomplete="name" maxlength="120" placeholder="Как к вам обращаться"
                           value="<?= e($lfName) ?>"<?= isset($lfErrors['name'])?' aria-invalid="true"':'' ?>><?= $lfFieldError('name') ?>
                </label>

                <label class="cp-fld">
                    <span class="cp-fld__lbl"><?= e($lfText('phone_label','Телефон')) ?></span>
                    <?php /* type=tel + inputmode=tel — на телефоне открывается цифровая клавиатура.
                             Формат не навязываем маской: человек диктует номер как привык,
                             а «минимум 10 цифр» проверит сервер (см. LeadController). */ ?>
                    <input class="cp-fld__inp" type="tel" name="phone" required
                           autocomplete="tel" inputmode="tel" maxlength="40"
                           placeholder="+7 900 000-00-00" value="<?= e($lfPhone) ?>"<?= isset($lfErrors['phone'])?' aria-invalid="true"':'' ?>><?= $lfFieldError('phone') ?>
                </label>

                <label class="cp-fld"><span class="cp-fld__lbl"><?= e($lfText('email_label','Email (необязательно)')) ?></span><input class="cp-fld__inp" type="email" name="email" maxlength="254" autocomplete="email" value="<?= e($lfEmail) ?>"<?= isset($lfErrors['email'])?' aria-invalid="true"':'' ?>><?= $lfFieldError('email') ?></label>
                <label class="cp-fld"><span class="cp-fld__lbl"><?= e($lfText('message_label','Сообщение (необязательно)')) ?></span><textarea class="cp-fld__inp" name="comment" maxlength="4000"><?= e($lfComment) ?></textarea><?= $lfFieldError('comment') ?></label>

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
                    <span><?= e($lfText('lead_consent','Согласен на обработку персональных данных и принимаю политику конфиденциальности.')) ?> <a href="<?= e(url('/politika-konfidencialnosti')) ?>" target="_blank" rel="noopener">Политика конфиденциальности</a></span><?= $lfFieldError('consent') ?>
                </label>

                <?php /* Иконка + текст. Горизонтальный padding и центрирование содержимого дают
                         .cp-btn и .cp-btn--primary (раздел 4 site.css) — здесь их не переопределяем
                         намеренно: обнулишь padding у кнопки с иконкой, и знак слипнется с краем. */ ?>
                <button class="cp-btn cp-btn--primary cp-leadform__btn" type="submit">
                    <svg class="cp-leadform__btn-ic" aria-hidden="true"><use href="#cp-i-mail"></use></svg>
                    <span><?= e($lfText('lead_button','Отправить заявку')) ?></span>
                </button>
            </form>
        </div>
    </div>
</section>
