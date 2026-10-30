<?php

declare(strict_types=1);

/**
 * Карточка товара /tovar/{slug}.
 *
 * Состав определён тем, что реально приезжает из выгрузки: галерея, артикул,
 * характеристики, совместимая техника, описание. Ничего сверх этого не рисуем —
 * блоки «Отзывы» и «Доставка за 1 день» на карточке запчасти были бы обещаниями,
 * которых нам не давали.
 *
 * ⚠️ Вкладки — ПРОГРЕССИВНОЕ УЛУЧШЕНИЕ. Без JS все панели видны подряд, заголовки
 * остаются обычными h2, ничего не прячется: контент карточки — то, ради чего сюда
 * приходит и человек, и робот, и он не имеет права зависеть от скрипта.
 * Переключалку рисует app.js (модуль cp-tabs), он же скрывает лишние панели.
 *
 * ⚠️ Цену числом не показываем, пока её нет в базе: money() сам отдаёт «по запросу».
 *
 * @var array<string,mixed> $page
 * @var array<string,mixed> $product
 * @var string $h1
 * @var list<array<string,mixed>> $images     url, alt, is_primary
 * @var list<array<string,mixed>> $attributes name, unit, value_str, value_num
 * @var list<array<string,mixed>> $compat     machine_type, models
 * @var list<array<string,mixed>> $neighbours id, slug, sku, name, price, in_stock, image_url
 */

$product    = $product ?? [];
$images     = $images ?? [];
$attributes = $attributes ?? [];
$compat     = $compat ?? [];
$neighbours = $neighbours ?? [];

$sku     = trim((string) ($product['sku'] ?? ''));
$name    = (string) ($product['name'] ?? '');
$inStock = !empty($product['in_stock']);
$body    = trim((string) ($product['body_html'] ?? ''));

// Главное фото — первое в списке (сортировка уже по is_primary DESC).
$main = product_image_url((string) ($images[0]['url'] ?? ($product['image_url'] ?? '')));

/**
 * Значение характеристики строкой. value_num без value_str встречается редко,
 * но встречается — тогда режем хвостовые нули, иначе в таблице «18.0000».
 */
$specValue = static function (array $a): string {
    $val = trim((string) ($a['value_str'] ?? ''));
    if ($val === '' && ($a['value_num'] ?? null) !== null) {
        $val = rtrim(rtrim(number_format((float) $a['value_num'], 4, '.', ''), '0'), '.');
    }
    if ($val === '') {
        return '';
    }
    $unit = trim((string) ($a['unit'] ?? ''));
    return $val . ($unit !== '' ? ' ' . $unit : '');
};

// Три главные характеристики выносим в шапку: они отвечают на «то ли это, что мне надо»
// до того, как человек долистает до таблицы.
$quick = [];
foreach ($attributes as $a) {
    $v = $specValue($a);
    if ($v !== '') {
        $quick[] = ['name' => (string) $a['name'], 'value' => $v];
    }
    if (count($quick) === 3) {
        break;
    }
}

?>
<section class="cp-wrap cp-section">
    <div class="cp-pcard">

        <!-- ── Галерея ─────────────────────────────────────────────────────── -->
        <div class="cp-pcard__media">
            <div class="cp-gal">
                <div class="cp-gal__stage">
                    <?php if ($main !== ''): ?>
                        <?php /* fetchpriority=high — это LCP-элемент страницы. */ ?>
                        <img class="cp-gal__img" data-gal-main src="<?= e($main) ?>"
                             alt="<?= e($name) ?>" width="620" height="465" fetchpriority="high">
                    <?php else: ?>
                        <svg class="cp-gal__ph" aria-hidden="true"><use href="#cp-i-part"></use></svg>
                    <?php endif; ?>
                </div>

                <?php if (count($images) > 1): ?>
                    <?php /* Кнопки, а не ссылки: переключение — состояние на странице,
                             а не переход. Без JS они просто ничего не делают, но и не
                             обманывают адресом. Полный размер открывается по клику
                             на главное фото (ссылка ниже). */ ?>
                    <div class="cp-gal__thumbs" data-gal-thumbs>
                        <?php foreach ($images as $i => $img): ?>
                            <button class="cp-gal__thumb<?= $i === 0 ? ' is-active' : '' ?>"
                                    type="button" data-gal-src="<?= e(product_image_url((string) $img['url'])) ?>"
                                    aria-label="Фото <?= e((string) ($i + 1)) ?>">
                                <img src="<?= e(product_image_url((string) $img['url'])) ?>" alt="" loading="lazy" width="88" height="66">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Сводка и покупка ────────────────────────────────────────────── -->
        <div class="cp-pcard__main">
            <div class="cp-pcard__top">
                <?php if ($sku !== ''): ?>
                    <span class="cp-chip cp-chip--sku">Артикул: <b><?= e($sku) ?></b></span>
                <?php endif; ?>
                <span class="cp-chip <?= $inStock ? 'cp-chip--in' : 'cp-chip--out' ?>">
                    <?= $inStock ? 'В наличии' : 'Под заказ' ?>
                </span>
                <?php if (!empty($product['category_name'])): ?>
                    <a class="cp-chip" href="<?= e(url('/catalog/' . (string) $product['category_slug'])) ?>">
                        <?= e((string) $product['category_name']) ?>
                    </a>
                <?php endif; ?>
            </div>

            <h1 class="cp-pcard__h1"><?= e($h1 ?? $name) ?></h1>

            <?php if ($quick !== []): ?>
                <ul class="cp-facts">
                    <?php foreach ($quick as $f): ?>
                        <li class="cp-facts__i">
                            <span class="cp-facts__k"><?= e($f['name']) ?></span>
                            <span class="cp-facts__v"><?= e($f['value']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="cp-buy">
                <div class="cp-buy__price">
                    <span class="cp-buy__val"><?= e(money($product['price'] ?? null)) ?></span>
                    <span class="cp-buy__hint">Цена зависит от объёма и срока — назовём после проверки наличия</span>
                </div>
                <div class="cp-buy__act">
                    <a class="cp-btn cp-btn--primary" href="#zayavka">Запросить цену</a>
                    <form method="post" action="<?= e(url('/korzina/add')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= e((string) ($product['id'] ?? '')) ?>">
                        <button class="cp-btn cp-btn--light" type="submit">В корзину</button>
                    </form>
                </div>
            </div>

            <ul class="cp-usp">
                <li>Подбор по номеру детали и по модели машины</li>
                <li>Проверяем применимость по серийному номеру</li>
                <li>Отгрузка со склада и под заказ, доставка по России</li>
            </ul>
        </div>
    </div>

    <!-- ── Вкладки: описание / характеристики / совместимость ──────────────── -->
    <div class="cp-tabs" data-tabs>
        <?php /* Кнопки рисует app.js из заголовков панелей — дублировать разметку
                 руками значит держать два списка вкладок в синхроне вручную. */ ?>

        <?php if ($body !== ''): ?>
            <section class="cp-tabs__panel" data-tab="Описание">
                <h2 class="cp-tabs__h">Описание</h2>
                <?php /* body_html собирает импортёр из фактов выгрузки — это наш текст,
                         а не копия чужого описания, поэтому выводим как есть. */ ?>
                <div class="cp-prose"><?= $body ?></div>
            </section>
        <?php endif; ?>

        <?php if ($attributes !== []): ?>
            <section class="cp-tabs__panel" data-tab="Характеристики">
                <h2 class="cp-tabs__h">Характеристики</h2>
                <table class="cp-specs">
                    <tbody>
                    <?php foreach ($attributes as $a): ?>
                        <?php $v = $specValue($a); ?>
                        <?php if ($v === '') { continue; } ?>
                        <tr>
                            <th scope="row"><?= e((string) $a['name']) ?></th>
                            <td><?= e($v) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

        <?php if ($compat !== []): ?>
            <section class="cp-tabs__panel" data-tab="Совместимая техника">
                <h2 class="cp-tabs__h">Совместимая техника</h2>
                <p class="cp-note">Перечень применимости справочный. Перед заказом сверьте номер
                    детали с каталогом вашей машины или пришлите её серийный номер — проверим.</p>
                <div class="cp-compat">
                    <?php foreach ($compat as $c): ?>
                        <div class="cp-compat__i">
                            <div class="cp-compat__t"><?= e((string) $c['machine_type']) ?></div>
                            <div class="cp-compat__m"><?= e((string) $c['models']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="cp-tabs__panel" data-tab="Доставка и оплата">
            <h2 class="cp-tabs__h">Доставка и оплата</h2>
            <?php /* Никаких сроков и цен: их нам не называли. Отправляем на страницы,
                     где условия описаны один раз и правятся в одном месте. */ ?>
            <p class="cp-note">Отправляем транспортными компаниями по всей России, самовывоз
                по договорённости. Условия и способы оплаты — на отдельных страницах:</p>
            <p><a class="cp-btn cp-btn--light cp-btn--sm" href="<?= e(url('/dostavka')) ?>">Доставка</a>
               <a class="cp-btn cp-btn--light cp-btn--sm" href="<?= e(url('/oplata')) ?>">Оплата</a>
               <a class="cp-btn cp-btn--light cp-btn--sm" href="<?= e(url('/garantiya')) ?>">Гарантия</a></p>
        </section>
    </div>

    <?php if ($neighbours !== []): ?>
        <div class="cp-also">
            <h2 class="cp-also__h">Другие детали этого раздела</h2>
            <div class="cp-grid cp-grid--prod">
                <?php foreach ($neighbours as $p): ?>
                    <?php
                    $href = url('/tovar/' . (string) $p['slug']);
                    $img  = product_image_url((string) ($p['image_url'] ?? ''));
                    ?>
                    <article class="cp-prod">
                        <a class="cp-prod__img" href="<?= e($href) ?>" tabindex="-1" aria-hidden="true">
                            <?php if ($img !== ''): ?>
                                <img src="<?= e($img) ?>" alt="" loading="lazy" width="240" height="180">
                            <?php else: ?>
                                <svg class="cp-prod__ph" aria-hidden="true"><use href="#cp-i-part"></use></svg>
                            <?php endif; ?>
                        </a>
                        <div class="cp-prod__body">
                            <?php if (trim((string) ($p['sku'] ?? '')) !== ''): ?>
                                <span class="cp-prod__sku"><?= e((string) $p['sku']) ?></span>
                            <?php endif; ?>
                            <a class="cp-prod__name" href="<?= e($href) ?>"><?= e((string) $p['name']) ?></a>
                            <div class="cp-prod__foot">
                                <span class="cp-prod__price cp-prod__price--ask"><?= e(money($p['price'] ?? null)) ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php
// Форма заявки — один блок на страницу (см. шапку партиала).
$formTitle  = $sku !== '' ? 'Запросить цену и срок по ' . $sku : 'Запросить цену и срок';
$formSource = 'product';
require __DIR__ . '/../partials/leadform.php';
