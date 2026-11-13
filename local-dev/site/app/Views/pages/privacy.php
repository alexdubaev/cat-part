<?php

declare(strict_types=1);

/**
 * Политика конфиденциальности.
 *
 * ⚠️ TODO ЗАКАЗЧИКУ — юридический документ, а не текст для галочки.
 * Он обязан называть ОПЕРАТОРА персональных данных поимённо (152-ФЗ),
 * а у нас в конфиге юрлица пока нет:
 *   • юрлицо/ИП, ИНН/ОГРН, юридический адрес → config: site.legal / inn / ogrn
 *     (появятся — подтянутся в блок «Оператор» ниже автоматически);
 *   • дата редакции документа;
 *   • подтвердить перечень: какие данные собираются формами и кабинетом,
 *     сроки хранения, передача третьим лицам (перевозчики, платёжный сервис),
 *     используемые счётчики (Метрика сейчас выключена — analytics.metrika_id пуст).
 *
 * ⚠️ Текст ниже — рабочая основа, отражающая то, что сайт реально делает.
 * Перед публикацией его должен просмотреть юрист заказчика: шаблон из интернета,
 * не совпадающий с фактическим поведением сайта, хуже отсутствия политики.
 *
 * @var array<string,mixed> $page
 */

$email = trim((string) brand_setting('email', cfg('site.email', '')));
$legal = trim((string) cfg('site.legal', ''));
$inn   = trim((string) cfg('site.inn', ''));
$ogrn  = trim((string) cfg('site.ogrn', ''));

?>
<section class="cp-wrap cp-section">
    <div class="cp-doc">
        <h1 class="cp-h1"><?php $seoH1=(string)(cms_meta((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH))['seo_h1']??'');if($seoH1!==''): ?><?= e($seoH1) ?><?php else: ?><?= e(cms_text('pages.privacy.text.001')) ?><?php endif; ?></h1>
        <div class="cp-prose"><?= cms_html('pages.privacy.body') ?></div>
    </div>
</section>
