<?php

declare(strict_types=1);

use App\Controllers\BlogController;
use App\Controllers\CatalogController;
use App\Controllers\CartController;
use App\Controllers\HomeController;
use App\Controllers\LeadController;
use App\Controllers\PageController;
use App\Controllers\SearchController;
use App\Controllers\SeoController;
use App\Core\Router;

/**
 * Таблица маршрутов. Контроллеры создаются лениво — только при совпадении.
 *
 * HEAD отдельно не регистрируем: роутер обслуживает его теми же GET-обработчиками
 * (см. Router::dispatch).
 */
return static function (Router $r): void {
    $requestPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET','HEAD'], true)) {
        $redirect = \App\Core\App::db()->fetch('SELECT new_path FROM cp_redirect WHERE old_path=?', [$requestPath]);
        if ($redirect && $redirect['new_path'] !== $requestPath && str_starts_with((string)$redirect['new_path'],'/') && !str_starts_with((string)$redirect['new_path'],'//')) {
            header('Location: '.url((string)$redirect['new_path']), true, 301); exit;
        }
    }

    $r->get('/', [HomeController::class, 'index']);

    // ── Каталог ──
    $r->get('/catalog', [CatalogController::class, 'index']);
    $r->get('/catalog/{slug}', [CatalogController::class, 'category']);
    $r->get('/tovar/{slug}', [CatalogController::class, 'product']);
    $r->get('/brand/{slug}', [CatalogController::class, 'brand']);
    $r->get('/korzina', [CartController::class, 'index']);
    $r->post('/korzina/add', [CartController::class, 'add']);
    $r->post('/korzina/update', [CartController::class, 'update']);
    $r->post('/korzina/remove', [CartController::class, 'remove']);
    $r->post('/korzina/checkout', [CartController::class, 'checkout']);

    // ── Поиск ── (запрос в ?q=, см. SearchController)
    $r->get('/poisk', [SearchController::class, 'index']);

    // ── Статьи ──
    // Порядок важен: '/stati' объявлен ДО '/stati/{slug}'. Роутер берёт первое
    // совпадение, и статичный маршрут обязан стоять раньше параметризованного.
    $r->get('/stati', [BlogController::class, 'index']);
    $r->get('/stati/{slug}', [BlogController::class, 'show']);

    // ── Заявки ──
    // Только POST: страницы /zayavka нет и не должно быть — форма живёт блоком внутри
    // главной, контактов и разделов каталога. GET сюда получит честный 404.
    $r->post('/zayavka', [LeadController::class, 'submit']);

    // ── Статические страницы ──
    $r->get('/o-kompanii', [PageController::class, 'about']);
    $r->get('/dostavka', [PageController::class, 'delivery']);
    $r->get('/oplata', [PageController::class, 'payment']);
    $r->get('/garantiya', [PageController::class, 'warranty']);
    $r->get('/kontakty', [PageController::class, 'contacts']);
    $r->get('/politika-konfidencialnosti', [PageController::class, 'privacy']);

    // ── SEO ──
    // /llms.txt — визитка сайта для ИИ-краулеров (генерируется из БД, см. контроллер).
    $r->get('/llms.txt', [SeoController::class, 'llms']);
    // /sitemap.xml — оглавление, остальные файлы карты разбиты по чанкам.
    $r->get('/sitemap.xml', [SeoController::class, 'sitemap']);
    $r->get('/sitemap-pages.xml', [SeoController::class, 'sitemapPages']);
    $r->get('/sitemap-products-{chunk}.xml', [SeoController::class, 'sitemapProducts']);
};
