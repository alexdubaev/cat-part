<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $lines */
$lines = $lines ?? [];
$old=$old??[];
$fieldErrors=$fieldErrors??[];
$fieldError=static fn(string $key):string => isset($fieldErrors[$key])?'<span class="cp-form-error" data-field-error="'.$key.'">'.e($fieldErrors[$key]).'</span>':'';
$formText=static fn(string $key,string $fallback):string => (string)\App\Core\App::config('forms.'.$key,$fallback);
?>
<section class="cp-wrap cp-section cp-cart-page">
    <h1 class="cp-h1">Корзина</h1>
    <?php if (!empty($ok)): ?><p class="cp-form-ok" role="status"><?= e($ok) ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="cp-form-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if ($lines === []): ?>
        <div class="cp-empty"><h2>Корзина пуста</h2><p>Добавьте позиции из каталога, затем отправьте запрос на подтверждение наличия и стоимости.</p><a class="cp-btn cp-btn--primary" href="<?= e(url('/catalog')) ?>">Перейти в каталог</a></div>
    <?php else: ?>
        <div class="cp-cart-layout">
            <div class="cp-cart-lines">
                <?php foreach ($lines as $line): ?>
                    <article class="cp-cart-line">
                        <div><span class="cp-prod__sku"><?= e($line['sku']) ?></span><h2><?= e($line['name']) ?></h2><span class="cp-cart-line__price"><?= e(money($line['price'],$line['currency']??'RUB')) ?></span><?php if (empty($line['available'])): ?><p class="cp-form-error">Позиция недоступна для отправки.</p><?php endif; ?></div>
                        <form method="post" action="<?= e(url('/korzina/update')) ?>" class="cp-cart-line__qty">
                            <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= e((string) $line['id']) ?>"><label>Количество <input type="number" name="qty" min="0" max="999999" value="<?= e((string) $line['qty']) ?>"></label><button class="cp-btn cp-btn--light cp-btn--sm" type="submit">Обновить</button>
                        </form>
                        <form method="post" action="<?= e(url('/korzina/remove')) ?>"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= e((string) $line['id']) ?>"><button class="cp-cart-line__remove" type="submit">Удалить</button></form>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="cp-cart-checkout">
                <h2><?= e($formText('checkout_title','Отправить запрос')) ?></h2><p><?= e($formText('checkout_hint','Цена и наличие подтверждаются менеджером. Онлайн-оплаты нет.')) ?></p>
                <form method="post" action="<?= e(url('/korzina/checkout')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="checkout_token" value="<?= e($checkoutToken) ?>">
                    <label style="position:absolute;left:-10000px" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
                    <label class="cp-fld"><span class="cp-fld__lbl"><?= e($formText('name_label','Имя')) ?></span><input class="cp-fld__inp" name="name" required maxlength="120" autocomplete="name" value="<?= e($old['name']??'') ?>"<?= isset($fieldErrors['name'])?' aria-invalid="true"':'' ?>><?= $fieldError('name') ?></label>
                    <label class="cp-fld"><span class="cp-fld__lbl"><?= e($formText('phone_label','Телефон')) ?></span><input class="cp-fld__inp" type="tel" name="phone" required minlength="10" autocomplete="tel" maxlength="40" value="<?= e($old['phone']??'') ?>"<?= isset($fieldErrors['phone'])?' aria-invalid="true"':'' ?>><?= $fieldError('phone') ?></label>
                    <label class="cp-fld"><span class="cp-fld__lbl"><?= e($formText('email_label','Email (необязательно)')) ?></span><input class="cp-fld__inp" type="email" name="email" maxlength="254" autocomplete="email" value="<?= e($old['email']??'') ?>"<?= isset($fieldErrors['email'])?' aria-invalid="true"':'' ?>><?= $fieldError('email') ?></label>
                    <label class="cp-fld"><span class="cp-fld__lbl"><?= e($formText('comment_label','Комментарий (необязательно)')) ?></span><textarea class="cp-fld__inp" name="comment" maxlength="4000"><?= e($old['comment']??'') ?></textarea><?= $fieldError('comment') ?></label>
                    <label class="cp-leadform__agree"><input type="checkbox" name="consent" value="1" required<?= !empty($old['consent'])?' checked':'' ?>><span><?= e($formText('checkout_consent','Согласен на обработку персональных данных.')) ?></span><?= $fieldError('consent') ?></label>
                    <button class="cp-btn cp-btn--primary" type="submit"><?= e($formText('checkout_button','Отправить запрос')) ?></button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</section>
