<?php

declare(strict_types=1);

/**
 * Базовая раскладка: <head> (SEO/OG/JSON-LD) → шапка → крошки → контент → подвал.
 *
 * Автоэкранирования в движке нет: всё, что приходит из БД/конфига, гонится
 * через e() руками. Без экранирования идут только заведомо-HTML поля
 * (intro_html, body_html) — они чистятся при импорте, не тут.
 *
 * @var string $content Уже отрендеренный HTML страницы
 * @var array<string,mixed> $page Контракт: title, description, keywords,
 *      canonical, breadcrumb, jsonld, og_image
 */

$P = is_array($page ?? null) ? $page : [];

$title  = trim((string) ($P['title'] ?? '')) ?: (string) cfg('site.name');
$desc   = trim((string) ($P['description'] ?? ''));
$kw     = trim((string) ($P['keywords'] ?? ''));
$crumbs = (array) ($P['breadcrumb'] ?? []);

// canonical в контракте — путь ('/catalog/gidravlika'), наружу нужен абсолютный URL.
$canonical = url((string) ($P['canonical'] ?? '/'));

// 🔥 Баг донора №1: там og:image вычислялся в переменную и НИКУДА не выводился —
// соцсети и мессенджеры показывали ссылку без картинки. Тут переменная реально
// доезжает до <meta> ниже. url() отдаёт внешние ссылки как есть, а внутренний
// путь /assets/... делает абсолютным — og:image по спецификации не принимает
// относительные пути.
$ogImage = url(trim((string) ($P['og_image'] ?? '')) ?: asset('img/og-default.jpg'));

$phone = trim((string) cfg('site.phone', ''));
$email = trim((string) cfg('site.email', ''));
$city  = trim((string) cfg('site.city', ''));

/*
 * JSON-LD: организация + сайт с поиском + крошки + страничные расширения.
 *
 * Пустые поля НЕ добавляем: 'telephone' => '' — это не «нейтрально»,
 * а мусор в разметке. Телефона у заказчика пока нет (config: site.phone = '').
 */
$store = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Store',
    'name'        => cfg('site.name'),
    'description' => cfg('site.tagline'),
    'url'         => cfg('app.base_url'),
];
if ($email !== '') {
    $store['email'] = $email;
}
if ($phone !== '') {
    $store['telephone'] = $phone;
}
if ($city !== '') {
    $store['address'] = [
        '@type'           => 'PostalAddress',
        'addressLocality' => $city,
        'addressCountry'  => 'RU',
    ];
}

$schemas = [$store];

// Поиск по артикулу — главный сценарий сайта. Отдаём его поисковику явно:
// у выдачи появляется поле поиска по сайту.
$schemas[] = [
    '@context'        => 'https://schema.org',
    '@type'           => 'WebSite',
    'name'            => cfg('site.name'),
    'url'             => cfg('app.base_url'),
    'potentialAction' => [
        '@type'       => 'SearchAction',
        'target'      => [
            '@type'       => 'EntryPoint',
            'urlTemplate' => url('/poisk') . '?q={search_term_string}',
        ],
        'query-input' => 'required name=search_term_string',
    ],
];

if ($crumbs !== []) {
    $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => url('/')]];
    foreach ($crumbs as $i => $c) {
        $item = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => (string) ($c['name'] ?? '')];
        if (!empty($c['url'])) {
            $item['item'] = url((string) $c['url']);
        }
        $items[] = $item;
    }
    $schemas[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

foreach ((array) ($P['jsonld'] ?? []) as $extra) {
    if (is_array($extra) && $extra !== []) {
        $schemas[] = $extra;
    }
}

// JSON_HEX_TAG обязателен: без него строка с '</script>' из БД закрыла бы тег
// и превратила разметку в XSS. Экранированный < для JSON-LD валиден.
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;

// 🔥 Баг донора №2: ID Яндекс.Метрики был вбит в шаблон в трёх местах —
// копия сайта слала бы статистику в чужой счётчик. Берём из конфига,
// и пока он пуст — не выводим ни счётчик, ни <noscript>-пиксель.
$metrikaId = trim((string) cfg('analytics.metrika_id', ''));

?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<?php if ($desc !== ''): ?>
<meta name="description" content="<?= e($desc) ?>">
<?php endif; ?>
<?php /* 🔥 Баг донора №5: keywords выводились всегда, с длинным «тематическим»
         дефолтом на каждой странице сайта. Мета давно не влияет на ранжирование,
         а одинаковый список на 2000 страниц — след шаблонной штамповки.
         Выводим только если контроллер задал осмысленное значение. */ ?>
<?php if ($kw !== ''): ?>
<meta name="keywords" content="<?= e($kw) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= e($canonical) ?>">
<meta name="robots" content="index, follow">
<meta name="theme-color" content="#0d0d0d">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="manifest" href="/manifest.webmanifest">
<?php /* Файл для ИИ-краулеров: /llms.txt отдаёт SeoController::llms(). Ссылка в head —
         чтобы его находили те, кто читает разметку страницы, а не только корень домена. */ ?>
<link rel="alternate" type="text/plain" href="/llms.txt" title="Описание сайта для ИИ">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(cfg('site.name')) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<?php if ($desc !== ''): ?>
<meta property="og:description" content="<?= e($desc) ?>">
<?php endif; ?>
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:locale" content="ru_RU">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?= e($ogImage) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">

<?php foreach ($schemas as $s): ?>
<script type="application/ld+json"><?= json_encode($s, $jsonFlags) ?></script>
<?php endforeach; ?>

<?php if ($metrikaId !== ''): ?>
<!-- Yandex.Metrika counter -->
<script type="text/javascript">
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=<?= e($metrikaId) ?>', 'ym');

    <?php /* Номер счётчика — числом, как в коде Метрики: ctype_digit отсекает
             случайный мусор в конфиге, иначе в JS уехал бы синтаксически битый вызов. */ ?>
    ym(<?= ctype_digit($metrikaId) ? $metrikaId : json_encode($metrikaId) ?>, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/<?= e($metrikaId) ?>" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
<?php endif; ?>
</head>
<body>
<?php require __DIR__ . '/../partials/icons.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<?php if ($crumbs !== []): ?>
<nav class="cp-bc" aria-label="Хлебные крошки">
    <div class="cp-wrap cp-bc__in">
        <a href="<?= e(url('/')) ?>">Главная</a>
        <?php $last = count($crumbs) - 1; ?>
        <?php foreach (array_values($crumbs) as $i => $c): ?>
            <span class="cp-bc__sep" aria-hidden="true">/</span>
            <?php if ($i < $last && !empty($c['url'])): ?>
                <a href="<?= e(url((string) $c['url'])) ?>"><?= e($c['name'] ?? '') ?></a>
            <?php else: ?>
                <span class="cp-bc__cur" aria-current="page"><?= e($c['name'] ?? '') ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</nav>
<?php endif; ?>

<main class="cp-main" id="main"><?= $content ?></main>

<?php require __DIR__ . '/../partials/footer.php'; ?>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
