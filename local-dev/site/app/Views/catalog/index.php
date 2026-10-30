<?php

declare(strict_types=1);

/**
 * Хаб каталога: все корневые разделы плитками, у каждого — его подразделы.
 *
 * Подразделы выведены прямо на плитке, а не спрятаны за переходом: в каталоге
 * запчастей человек мыслит узлом («гидроцилиндры»), а не разделом верхнего
 * уровня, и лишний клик тут стоит дороже, чем плотность страницы.
 *
 * @var array<string,mixed> $page
 * @var list<array<string,mixed>> $categories Корневые разделы, у каждого
 *      ключ children = list подкатегорий
 */

$cats = $categories ?? [];

/**
 * Сколько подразделов видно на плитке до нажатия «Раскрыть».
 *
 * Разделы вышли слишком разными: у «Навесного оборудования» 30 подразделов, у
 * «Фильтров» — 2. Без порога плитки разъезжались по высоте и сетка ломалась.
 * 10 — компромисс: столько влезает, не превращая плитку в простыню, и почти
 * все разделы упираются в порог, то есть карточки выходят одного роста.
 */
$visible = 10;

?>
<section class="cp-hero cp-hero--page">
    <div class="cp-wrap cp-hero__in">
        <h1 class="cp-hero__title">Каталог запчастей</h1>
        <p class="cp-hero__text">Разделы каталога повторяют логику узлов машины: от гидравлики
            и двигателя до ходовой части и электрооборудования. Знаете номер детали —
            быстрее искать через поиск по артикулу.</p>
        <div class="cp-hero__cta">
            <a class="cp-btn cp-btn--primary" href="#razdely">К разделам</a>
            <a class="cp-btn cp-btn--ghost" href="<?= e(url('/kontakty')) ?>">Запрос на подбор</a>
        </div>
    </div>
</section>

<section class="cp-wrap cp-section" id="razdely">
    <?php if ($cats === []): ?>
        <p class="cp-empty">Разделы каталога появятся после загрузки данных. Нужна деталь сейчас —
            напишите: <a href="mailto:<?= e(cfg('site.email')) ?>"><?= e(cfg('site.email')) ?></a>.</p>
    <?php else: ?>
        <div class="cp-grid cp-grid--group">
            <?php foreach ($cats as $c): ?>
                <?php
                $href = url('/catalog/' . (string) $c['slug']);
                $cnt  = (int) ($c['products_count'] ?? 0);
                // array_values — чтобы считать «первые $visible» по индексу и не
                // зависеть от того, пришёл ли из модели список или карта с дырами.
                $children = array_values((array) ($c['children'] ?? []));
                // Кнопка нужна, только когда есть что раскрывать: у «Фильтров»
                // с двумя подразделами она была бы кнопкой в никуда.
                $hasMore = count($children) > $visible;
                // Связка button.aria-controls -> ul.id. id раздела уникален в
                // пределах страницы, поэтому хватает его.
                $listId = 'cp-group-list-' . (int) $c['id'];
                ?>
                <article class="cp-group">
                    <div class="cp-group__head">
                        <svg class="cp-group__ic" aria-hidden="true">
                            <use href="#cp-i-<?= e(($c['icon'] ?? '') !== '' ? (string) $c['icon'] : 'part') ?>"></use>
                        </svg>
                        <div>
                            <a class="cp-group__name" href="<?= e($href) ?>"><?= e($c['name']) ?></a>
                            <?php if ($cnt > 0): ?>
                                <span class="cp-group__cnt"><?= e(number_format($cnt, 0, ',', ' ')) ?> позиций</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($children !== []): ?>
                        <?php /* Печатаем ВСЕ подразделы, лишние прячет CSS (класс
                                 --extra), а не PHP. Это ссылки на разделы каталога:
                                 робот обязан их видеть в HTML, иначе половина
                                 таксономии останется без внутренних ссылок. */ ?>
                        <ul class="cp-group__list" id="<?= e($listId) ?>">
                            <?php foreach ($children as $i => $ch): ?>
                                <li<?= $i >= $visible ? ' class="cp-group__li--extra"' : '' ?>>
                                    <a href="<?= e(url('/catalog/' . (string) $ch['slug'])) ?>"><?= e($ch['name']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <?php /* Подразделы известны не у всех разделов — они приедут из
                                 источника данных. Выдуманной таксономии тут нет, поэтому
                                 плитка честно ведёт сразу в раздел. */ ?>
                        <p class="cp-group__empty">Позиции раздела — на его странице.</p>
                    <?php endif; ?>

                    <div class="cp-group__foot">
                        <?php if ($hasMore): ?>
                            <?php /* hidden снимает app.js — та же конвенция, что у cookie-баннера.
                                     Без JS кнопки нет вовсе: она не должна притворяться рабочей,
                                     когда раскрывать список некому. Полный перечень подразделов в
                                     этом случае человек видит на странице раздела — ссылка рядом. */ ?>
                            <button type="button" class="cp-group__more" data-group-more
                                    aria-expanded="false" aria-controls="<?= e($listId) ?>" hidden>Раскрыть</button>
                        <?php endif; ?>
                        <a class="cp-group__all" href="<?= e($href) ?>">Открыть раздел →</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="cp-wrap cp-section cp-section--tight">
    <div class="cp-help">
        <div class="cp-help__in">
            <div>
                <h2 class="cp-help__t">Знаете номер детали?</h2>
                <p class="cp-help__d">Поиск по артикулу быстрее, чем обход разделов: он понимает
                    кросс-номера и не придирается к пробелам, дефисам и регистру.</p>
            </div>
            <div class="cp-help__act">
                <a class="cp-btn cp-btn--primary" href="<?= e(url('/poisk')) ?>">Поиск по артикулу</a>
            </div>
        </div>
    </div>
</section>
