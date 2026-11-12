<?php

declare(strict_types=1);

/**
 * Главная: hero с крупным поиском по артикулу → разделы каталога → о компании →
 * преимущества → статьи → блок помощи.
 *
 * Порядок блоков не случаен. В каталоге запчастей человек приходит с номером
 * детали, снятой с машины, а не «посмотреть ассортимент»: поиск обязан быть
 * первым экраном, разделы — вторым, тексты — последними.
 *
 * ⚠️ ПРО ФАКТЫ. О заказчике не известно НИЧЕГО из того, чем обычно набивают
 * главную: ни года начала работы, ни склада, ни числа клиентов, ни городов,
 * ни сроков доставки, ни гарантийных сроков, ни объёма номенклатуры, ни юрлица
 * (plans/2026-07-15_catpart_plan.md, «Открытые вопросы»). Ни одной такой цифры
 * в этом файле нет и появиться не должно: выдуманный срок или размер склада —
 * это обещание, которое кто-то предъявит. Всё, что тут написано, следует из
 * устройства сайта и проверяемо по нему же.
 *
 * @var array<string,mixed> $page
 * @var list<array<string,mixed>> $rootCategories Корневые разделы:
 *      id, slug, name, icon, products_count
 * @var list<array<string,mixed>> $articles Последние статьи:
 *      slug, title, excerpt, image_url, published_at
 */

$cats = $rootCategories ?? [];
$featuredSlugs = ['dvigatel', 'gidravlika', 'silovaya-peredacha', 'hodovaya-chast', 'shlangi-i-trubki', 'kabiny'];
$catsBySlug = array_column($cats, null, 'slug');
$featuredCats = [];
foreach ($featuredSlugs as $slug) {
    if (isset($catsBySlug[$slug])) {
        $featuredCats[] = $catsBySlug[$slug];
        unset($catsBySlug[$slug]);
    }
}
$moreCats = array_values($catsBySlug);

/*
 * Статьи приходят из HomeController (его пишет другой агент). Вьюха не считает
 * переменную гарантированной: пока бэка нет — $posts пуст и блок не рисуется.
 */
$posts = isset($articles) && is_array($articles) ? $articles : [];

/*
 * Дата по-русски: «14 июля 2026».
 *
 * Замыкание, а не функция в helpers.php: тот файл сейчас правит другой агент,
 * и две глобальные функции с одним именем — фатальный redeclare на боевом.
 * Формат нужен ровно в одном месте, глобальное имя того не стоит.
 *
 * strftime() тут не годится принципиально: он deprecated с 8.1, а IntlDateFormatter
 * требует ext-intl, которого на shared-хостинге может не быть. Массив месяцев —
 * скучно, зато работает везде и даёт родительный падеж («14 июля», а не «14 июль»).
 *
 * @return array{iso:string,human:string}|null null = дату показывать не будем
 */
$dateRu = static function (mixed $raw): ?array {
    $v = trim((string) $raw);
    if ($v === '') {
        return null;
    }
    try {
        $d = new DateTimeImmutable($v);
    } catch (Exception) {
        // Мусор в published_at не должен ронять главную — просто без даты.
        return null;
    }

    $months = [
        1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
        'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря',
    ];

    return [
        // Машинный ISO для datetime= и человеческий текст для глаз — это разные
        // строки, и <time> существует ровно для того, чтобы их развести.
        'iso'   => $d->format('Y-m-d'),
        'human' => $d->format('j') . ' ' . $months[(int) $d->format('n')] . ' ' . $d->format('Y'),
    ];
};

/*
 * Цельный панорамный фон с достроенным правым краем; WebP для двух размеров.
 * Описание исходника и генерации: deliverables/home-hero.md.
 * Если файла нет, сохраняем текстовый hero без битого изображения.
 */
$heroImage  = 'img/hero-wide.webp';
$heroExists = asset_exists($heroImage);

?>
<section class="cp-hero cp-hero--home<?= $heroExists ? ' cp-hero--media' : '' ?>">
    <?php if ($heroExists): ?>
    <div class="cp-hero__media" aria-hidden="true">
        <img class="cp-hero__img" src="<?= e(asset($heroImage)) ?>" alt=""
             srcset="<?= e(asset('img/hero-wide-960.webp')) ?> 960w, <?= e(asset($heroImage)) ?> 2078w"
             sizes="(min-width: 961px) calc(100vw + 5vw), 100vw"
             loading="eager" fetchpriority="high" decoding="async" width="2078" height="757">
    </div>
    <?php endif; ?>
    <div class="cp-wrap cp-hero__in">
      <div class="cp-hero__col">
        <?php /* Перенос строки жёсткий: заголовок должен читаться в две строки,
                 как решил заказчик. На узких экранах он и так переносится сам,
                 лишний <br> там не мешает — вторая строка просто короче. */ ?>
        <h1 class="cp-hero__title">Запчасти для техники<br><b>Caterpillar</b> по артикулу</h1>
        <p class="cp-hero__text">Введите артикул, кросс-номер или название узла. Подберём оригинал
            или совместимый аналог и рассчитаем поставку.</p>

        <div class="cp-hero__cta">
            <a class="cp-btn cp-btn--primary" href="<?= e(url('/catalog')) ?>">Перейти в каталог</a>
            <a class="cp-btn cp-btn--ghost" href="<?= e(url('/kontakty')) ?>">Запрос на подбор</a>
        </div>
      </div>

    </div>

    <div class="cp-wrap cp-hero__facts">
        <?php /* Факты только проверяемые. Ни «лет на рынке», ни числа позиций,
                 ни сроков доставки: ничего этого заказчик не подтверждал. */ ?>
        <div class="cp-facts">
            <div class="cp-fact">
                <span class="cp-fact__t">Поиск по номеру и кроссу</span>
                <span class="cp-fact__d">Ищем по артикулу производителя и по кросс-номерам —
                    даже если номер на снятой детали чужой или устаревший.</span>
            </div>
            <div class="cp-fact">
                <span class="cp-fact__t">Оригинал и аналоги</span>
                <span class="cp-fact__d">Показываем варианты применимости: оригинальную деталь
                    и совместимые замены, чтобы выбор остался за вами.</span>
            </div>
            <div class="cp-fact">
                <span class="cp-fact__t">Подбор по узлу</span>
                <span class="cp-fact__d">Не знаете номер — опишите узел и модель машины,
                    поможем определить деталь.</span>
            </div>
        </div>
    </div>
</section>

<section class="cp-wrap cp-section cp-section--catalog">
    <div class="cp-section__head">
        <h2>Разделы каталога</h2>
        <?php if ($moreCats !== []): ?>
            <button class="cp-catalog-toggle" id="cpCatalogToggle" type="button"
                    aria-expanded="false" aria-controls="cpHomeCatalog" hidden>
                <span data-catalog-label>Весь каталог</span>
                <span data-catalog-arrow aria-hidden="true">↓</span>
            </button>
            <noscript><a class="cp-catalog-toggle" href="<?= e(url('/catalog')) ?>">Весь каталог</a></noscript>
        <?php endif; ?>
    </div>

    <?php if ($cats === []): ?>
        <p class="cp-empty">Каталог наполняется. Напишите нам — подберём деталь по номеру вручную:
            <a href="mailto:<?= e(brand_setting('email', cfg('site.email'))) ?>"><?= e(brand_setting('email', cfg('site.email'))) ?></a>.</p>
    <?php else: ?>
        <div class="cp-grid cp-grid--cat cp-grid--home-cat" id="cpHomeCatalog">
            <?php foreach (array_merge($featuredCats, $moreCats) as $index => $c): ?>
                <?php $cnt = (int) ($c['products_count'] ?? 0); ?>
                <a class="cp-cat" href="<?= e(url('/catalog/' . (string) $c['slug'])) ?>"<?= $index >= count($featuredCats) ? ' data-catalog-extra hidden' : '' ?>>
                    <?php $catGraphic = category_graphic((string) ($c['slug'] ?? ''), (string) ($c['icon'] ?? '')); ?>
                    <?php if (asset_exists($catGraphic)): ?>
                        <img class="cp-cat__ic" src="<?= e(asset($catGraphic)) ?>" alt="" loading="lazy" decoding="async" width="72" height="72">
                    <?php else: ?>
                        <svg class="cp-cat__ic" aria-hidden="true"><use href="#cp-i-<?= e(($c['icon'] ?? '') !== '' ? (string) $c['icon'] : 'part') ?>"></use></svg>
                    <?php endif; ?>
                    <span class="cp-cat__txt">
                        <span class="cp-cat__name"><?= e($c['name']) ?></span>
                        <?php /* Счётчик — только реальный. До импорта он 0, и «0 позиций»
                                 на витрине честнее не показывать вовсе. */ ?>
                        <?php if ($cnt > 0): ?>
                            <span class="cp-cat__cnt"><?= e(number_format($cnt, 0, ',', ' ')) ?> позиций</span>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php $homeProducts = isset($homeProducts) && is_array($homeProducts) ? $homeProducts : []; ?>
<?php if ($homeProducts !== []): ?>
<section class="cp-wrap cp-section cp-section--tight" id="homeProducts">
    <div class="cp-section__head">
        <h2>Товары</h2>
        <a class="cp-section__more" href="<?= e(url('/catalog')) ?>">Весь каталог →</a>
    </div>
    <div class="cp-grid cp-grid--prod">
        <?php foreach ($homeProducts as $p): ?>
            <?php
            $href = url('/tovar/' . (string) $p['slug']);
            $sku = trim((string) ($p['sku'] ?? ''));
            $img = product_image_url((string) ($p['image_url'] ?? ''));
            $price = (float) ($p['price'] ?? 0);
            ?>
            <article class="cp-prod">
                <a class="cp-prod__img" href="<?= e($href) ?>" tabindex="-1" aria-hidden="true">
                    <?php if ($img !== ''): ?>
                        <img src="<?= e($img) ?>" alt="" loading="lazy" decoding="async" width="240" height="180">
                    <?php else: ?>
                        <svg class="cp-prod__ph" aria-hidden="true"><use href="#cp-i-part"></use></svg>
                    <?php endif; ?>
                </a>
                <div class="cp-prod__body">
                    <?php if ($sku !== ''): ?><span class="cp-prod__sku"><?= e($sku) ?></span><?php endif; ?>
                    <a class="cp-prod__name" href="<?= e($href) ?>"><?= e($p['name']) ?></a>
                    <span class="cp-prod__stock cp-prod__stock--<?= !empty($p['in_stock']) ? 'in' : 'out' ?>"><?= !empty($p['in_stock']) ? 'В наличии' : 'Под заказ' ?></span>
                    <div class="cp-prod__foot">
                        <span class="cp-prod__price<?= $price > 0 ? '' : ' cp-prod__price--ask' ?>"><?= e(money($p['price'] ?? null)) ?></span>
                        <form method="post" action="<?= e(url('/korzina/add')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= e((string) $p['id']) ?>">
                            <button class="cp-btn cp-btn--light cp-btn--sm" type="submit">В корзину</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php
/*
 * ── О компании ───────────────────────────────────────────────────────────────
 *
 * TODO ЗАКАЗЧИКУ — фактура, которой у нас нет и которую нельзя выдумывать:
 *   • год начала работы, число сотрудников;
 *   • собственный склад: есть ли, где, что лежит;
 *   • регионы и города поставки, сроки отгрузки;
 *   • объём номенклатуры (цифру с parts.cat.com брать нельзя — это ИХ каталог);
 *   • число клиентов, отраслевые кейсы;
 *   • юрлицо/ИП, ИНН, ОГРН → config: site.legal / site.inn / site.ogrn.
 * Появятся ответы — сюда добавится абзац с цифрами. До тех пор блок говорит
 * только о том, что видно из устройства сайта и проверяется на нём же.
 *
 * Правая панель тёмная и не для красоты: «мы не дилер» обязано попадаться на
 * глаза там, где человек начинает выбирать деталь, а не только мелким шрифтом
 * в подвале (plans/2026-07-15_catpart_plan.md, раздел 0).
 */
?>
<section class="cp-wrap cp-section">
    <div class="cp-section__head">
        <h2>О компании</h2>
        <a class="cp-section__more" href="<?= e(url('/o-kompanii')) ?>">Подробнее →</a>
    </div>

    <div class="cp-about cp-about--solo">
        <div class="cp-about__txt">
            <p><strong><?= e(cfg('site.name')) ?></strong> — сайт компании СМ ТЕХНО, поставщика
                оригинальных деталей и качественных аналогов для техники Caterpillar.</p>
            <p>Поставляем запчасти по России: позиции бывают в наличии и под заказ. Подберём
                деталь по артикулу, каталогу, фотографии или описанию узла.</p>
        </div>
    </div>
</section>

<?php
/*
 * ── Почему у нас покупают ────────────────────────────────────────────────────
 *
 * Каждая плитка — про механику сайта и работы, а не про заказчика. Никаких
 * «10 лет на рынке», «склад 5000 м²», «доставка от 3 дней», «гарантия 12 мес.»:
 * ничего этого нам не называли. Собираетесь дописать плитку с цифрой — сначала
 * спросите заказчика, см. TODO у блока «О компании» выше.
 */
?>
<section class="cp-wrap cp-section">
    <div class="cp-section__head">
        <h2>Почему у нас покупают запчасти</h2>
    </div>

    <div class="cp-why">
        <div class="cp-why__i">
            <?php $benefit = benefit_graphic('in-stock'); ?><?php if (asset_exists($benefit)): ?><img class="cp-why__ic" src="<?= e(asset($benefit)) ?>" alt="" loading="lazy" width="128" height="96"><?php else: ?><svg class="cp-why__ic" aria-hidden="true"><use href="#cp-i-check"></use></svg><?php endif; ?>
            <span class="cp-why__t">Запчасти в наличии</span>
            <span class="cp-why__d">Ходовые позиции доступны к отгрузке без долгого ожидания.</span>
        </div>
        <div class="cp-why__i">
            <?php $benefit = benefit_graphic('made-to-order'); ?><?php if (asset_exists($benefit)): ?><img class="cp-why__ic" src="<?= e(asset($benefit)) ?>" alt="" loading="lazy" width="128" height="96"><?php else: ?><svg class="cp-why__ic" aria-hidden="true"><use href="#cp-i-kit"></use></svg><?php endif; ?>
            <span class="cp-why__t">Поставка под заказ</span>
            <span class="cp-why__d">Найдём и поставим редкие позиции, узлы и комплектующие под конкретную технику.</span>
        </div>
        <div class="cp-why__i">
            <?php $benefit = benefit_graphic('originals-and-analogs'); ?><?php if (asset_exists($benefit)): ?><img class="cp-why__ic" src="<?= e(asset($benefit)) ?>" alt="" loading="lazy" width="128" height="96"><?php else: ?><svg class="cp-why__ic" aria-hidden="true"><use href="#cp-i-layers"></use></svg><?php endif; ?>
            <span class="cp-why__t">Оригиналы и аналоги</span>
            <span class="cp-why__d">Подберём решение под бюджет и задачи эксплуатации, а выбор останется за вами.</span>
        </div>
        <div class="cp-why__i">
            <?php $benefit = benefit_graphic('accurate-fit'); ?><?php if (asset_exists($benefit)): ?><img class="cp-why__ic" src="<?= e(asset($benefit)) ?>" alt="" loading="lazy" width="128" height="96"><?php else: ?><svg class="cp-why__ic" aria-hidden="true"><use href="#cp-i-search"></use></svg><?php endif; ?>
            <span class="cp-why__t">Точный подбор</span>
            <span class="cp-why__d">Идентифицируем деталь по артикулу, каталогу, фото или описанию узла.</span>
        </div>
    </div>
</section>

<section class="cp-wrap cp-section cp-section--tight">
    <div class="cp-help">
        <div class="cp-help__in">
            <div>
                <h2 class="cp-help__t">Не нашли деталь по номеру?</h2>
                <p class="cp-help__d">Напишите номер с детали или таблички, модель и серийный номер
                    машины и опишите узел — ответим с вариантами применимости и условиями поставки.</p>
            </div>
            <div class="cp-help__act">
                <a class="cp-btn cp-btn--primary" href="mailto:<?= e(brand_setting('email', cfg('site.email'))) ?>">Написать нам</a>
                <a class="cp-btn cp-btn--ghost" href="<?= e(url('/kontakty')) ?>">Контакты</a>
            </div>
        </div>
    </div>
</section>

<?php
/*
 * ── Заявка ───────────────────────────────────────────────────────────────────
 *
 * Полоса помощи выше зовёт написать письмо — то есть требует от человека самому
 * сформулировать запрос. Форма закрывает второй сценарий: номера под рукой нет,
 * разбираться некогда, проще оставить телефон. Поэтому они идут парой и подряд.
 *
 * Разметка и поля — в партиале, своей копии формы тут нет: три страницы с тремя
 * копиями одной формы разъедутся на первой же правке (см. partials/leadform.php).
 */
$formSource = 'home';
$formTitle  = 'Оставить заявку на подбор';
require __DIR__ . '/partials/leadform.php';
?>

<?php
/*
 * ── Новости и статьи ─────────────────────────────────────────────────────────
 *
 * Идут ПОСЛЕДНИМИ, у самого подвала — по решению заказчика. Логика та же, что
 * и у порядка блоков вообще: человек пришёл с номером детали, а не читать. Кто
 * дочитал до низа и не нашёл нужного — тому статьи и пригодятся.
 *
 * Блока нет вовсе, пока нет статей: пустая рубрика с надписью «скоро тут будет
 * контент» на витрине хуже, чем её отсутствие.
 *
 * Классы (.cp-posts / .cp-post*) и вся геометрия карточки — из раздела 19
 * site.css, его писал агент раздела /stati. Своей копии стилей тут нет
 * намеренно: два набора правил под одну карточку разъедутся на первой же правке.
 *
 * ⚠️ ТЕХДОЛГ, осознанный. Разметка карточки продублирована из
 * app/Views/blog/_card.php — там она общий фрагмент для /stati и «Читайте
 * также». Причина дубля ровно одна: _card.php печатает дату как 14.07.2026,
 * а для главной заказано «14 июля 2026», и переопределить формат снаружи
 * нельзя. Правильное решение — научить _card.php русскому формату и звать его
 * отсюда через require; это правка ЧУЖОГО файла, поэтому вынесена из моей
 * задачи. Пока правите разметку статей — правьте оба места.
 */
?>
<?php if ($posts !== []): ?>
<section class="cp-wrap cp-section">
    <div class="cp-section__head">
        <h2>Новости и статьи</h2>
        <a class="cp-section__more" href="<?= e(url('/stati')) ?>">Все статьи →</a>
    </div>

    <div class="cp-posts">
        <?php foreach ($posts as $a): ?>
            <?php
            $slug  = trim((string) ($a['slug'] ?? ''));
            $title = trim((string) ($a['title'] ?? ''));

            // Без slug ссылка вела бы на /stati/ — в никуда; без заголовка карточка
            // нечитаема. Битая запись молча пропускается, а не ломает всю ленту.
            if ($slug === '' || $title === '') {
                continue;
            }

            $excerpt = trim((string) ($a['excerpt'] ?? ''));
            $img     = trim((string) ($a['image_url'] ?? ''));
            if ($img === '') {
                $mapped = article_graphic($slug, true);
                if (asset_exists($mapped)) { $img = asset($mapped); }
            }
            $when    = $dateRu($a['published_at'] ?? null);
            ?>
            <article class="cp-post">
                <?php if ($img !== ''): ?>
                    <?php /* Обложка рисуется ТОЛЬКО при реальном image_url: сейчас он у всех
                             статей NULL, и карточка обязана быть законченной без картинки —
                             ни серого квадрата-заглушки, ни <img> с пустым src.
                             alt пустой: смысл несёт заголовок рядом, дублировать его в alt —
                             заставлять скринридер читать одно и то же дважды. */ ?>
                    <div class="cp-post__media">
                        <img class="cp-post__img" src="<?= e(str_starts_with($img, '/') ? $img : $img) ?>" alt="" loading="lazy" decoding="async" width="600" height="338">
                    </div>
                <?php endif; ?>

                <div class="cp-post__body">
                    <?php if ($when !== null): ?>
                        <time class="cp-post__date" datetime="<?= e($when['iso']) ?>"><?= e($when['human']) ?></time>
                    <?php endif; ?>

                    <?php /* В <a> обёрнут только заголовок, но кликается вся карточка:
                             CSS растягивает ::after этой ссылки на весь блок. Так у карточки
                             один таб-стоп и короткое осмысленное имя ссылки — вместо
                             простыни «дата + заголовок + анонс + читать». */ ?>
                    <h3 class="cp-post__title">
                        <a href="<?= e(url('/stati/' . $slug)) ?>"><?= e($title) ?></a>
                    </h3>

                    <?php if ($excerpt !== ''): ?>
                        <p class="cp-post__excerpt"><?= e($excerpt) ?></p>
                    <?php endif; ?>

                    <?php /* Не ссылка: ссылка тут уже есть, дублировать её в дереве
                             доступности незачем. */ ?>
                    <span class="cp-post__more" aria-hidden="true">Читать статью →</span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
