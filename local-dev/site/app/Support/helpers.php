<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Session;

/** Экранирование для HTML-вывода. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Абсолютный URL внутреннего пути относительно base_url. */
function url(string $path = '/'): string
{
    if (preg_match('#^https?://#', $path) === 1) {
        return $path;
    }
    return (string) App::config('app.base_url') . '/' . ltrim($path, '/');
}

/** Путь к ассету (под docroot=/public ассеты лежат в /assets). */
function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

/**
 * Лежит ли ассет физически на диске. Путь — как для asset(): 'img/hero-parts.png'.
 *
 * Нужна там, где блок рисуется только при наличии картинки (hero главной,
 * hero контактов): нет файла — нет колонки, вместо битого <img> с крестиком.
 *
 * 🔥 ПРОВЕРЯЕМ ОТ DOCUMENT_ROOT, А НЕ ОТ КОРНЯ ПРОЕКТА — и это принципиально.
 *
 * Раскладок у нас две (см. DEPLOY.md, шаг 0): docroot смотрит на public/, либо
 * плоская — содержимое public/ лежит в корне сайта. Путь от корня ПРОЕКТА до
 * assets/ в них РАЗНЫЙ, и захардкоженный '/public/assets/' верен ровно в одной.
 *
 * Ровно на этом обожглись: на боевом стояла плоская раскладка, картинка лежала
 * в /assets/img/ и отдавалась по HTTP с кодом 200, а is_file() искал её в
 * /public/assets/img/, не находил — и hero молча оставался одноколоночным.
 * Ошибки при этом нет никакой: файл просто «не существует», и всё выглядит
 * штатным поведением. Худший вид бага — тихий.
 *
 * Инвариант: asset() отдаёт '/assets/…' — адрес, по которому файл запросит
 * браузер. DOCUMENT_ROOT + этот же адрес = то самое место на диске, в любой
 * раскладке. Проверять надо там, куда пойдёт браузер, а не там, где мы думаем.
 */
function asset_exists(string $path): bool
{
    $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), "/\\");
    if ($docRoot === '') {
        // CLI или экзотический SAPI: DOCUMENT_ROOT пуст. Честно говорим «нет»,
        // а не гадаем — вьюхи всё равно рендерятся только по HTTP.
        return false;
    }

    return is_file($docRoot . '/assets/' . ltrim($path, '/'));
}

/** Цена в рублях: 12 500 ₽ (или «по запросу», если null/0). */
function money(int|float|string|null $value): string
{
    $n = (float) $value;
    if ($n <= 0) {
        return 'по запросу';
    }
    return number_format($n, 0, ',', ' ') . ' ₽';
}

function csrf_token(): string
{
    return Session::csrf();
}

/** Скрытое поле CSRF для форм. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Залогинен ли посетитель. */
function is_authenticated(): bool
{
    return Session::userId() !== null;
}

/** Короткий доступ к конфигу из шаблонов: cfg('site.name'). */
function cfg(string $key, mixed $default = null): mixed
{
    return App::config($key, $default);
}

/** Количество позиций в корзине (для бейджа в шапке). */
function cart_count(): int
{
    $cart = Session::get('cart', []);
    return is_array($cart) ? array_sum(array_map(static fn ($i) => (int) ($i['qty'] ?? 0), $cart)) : 0;
}

/**
 * Транслитерация строки в slug (латиница/цифры/дефис).
 *
 * Живёт именно здесь, а не в импортёре. У донора эта функция (ps_slug) была объявлена
 * ВНУТРИ db/import.php — то есть в рантайме её не существует, и любой контроллер,
 * которому понадобился бы slug, обязан был бы её продублировать. Дубль карты
 * транслита = два расхождения в написании = битые ссылки.
 */
function cp_slug(string $value): string
{
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = strtr($value, $map);
    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
    return trim($value, '-');
}

/**
 * Нормализация артикула: верхний регистр, без пробелов, дефисов, точек и подчёркиваний.
 * '3B-8489', '3b 8489', '3B.8489' → '3B8489'.
 *
 * 🔥 ОДНА функция на весь проект — и импортёр (пишет products.sku_norm и
 * product_cross.cross_sku_norm), и поиск (нормализует запрос) ОБЯЗАНЫ звать именно её.
 * Разъедься эти две нормализации хоть на один символ — поиск по артикулу молча
 * перестанет находить товары: ошибки не будет, будет пустая выдача.
 */
function sku_norm(string $sku): string
{
    $sku = preg_replace('/[\s\-\._]+/u', '', trim($sku)) ?? '';
    return mb_strtoupper($sku, 'UTF-8');
}

/**
 * Окно страниц для пагинации: [1, '…', 4, 5, 6, 7, 8, '…', 833].
 *
 * У донора вьюха печатала ВСЕ страницы разом (catalog/category.php:62-72) — на
 * 20 000 товаров это 833 ссылки в каждом футере списка: мусор для пользователя и
 * размывание внутреннего веса для поисковика.
 *
 * @return list<int|string> числа страниц и разделители '…'
 */
function paginate_window(int $current, int $total, int $side = 3): array
{
    if ($total < 1) {
        return [];
    }
    $current = max(1, min($current, $total));
    $from = max(1, $current - $side);
    $to = min($total, $current + $side);

    $out = [];
    if ($from > 1) {
        $out[] = 1;
        // Разделитель нужен, только если между первой страницей и окном реально есть пропуск.
        if ($from > 2) {
            $out[] = '…';
        }
    }
    for ($i = $from; $i <= $to; $i++) {
        $out[] = $i;
    }
    if ($to < $total) {
        if ($to < $total - 1) {
            $out[] = '…';
        }
        $out[] = $total;
    }
    return $out;
}

/**
 * URL страницы пагинации. Сам выбирает '?' или '&' — базовый адрес может уже
 * содержать query-строку (/poisk?q=3B-8489), и наивная склейка дала бы '?q=…?page=2'.
 * Первая страница — без параметра: /catalog/gidravlika и ?page=1 не должны быть
 * двумя разными URL с одинаковым содержимым.
 */
function page_url(string $baseUrl, int $page): string
{
    if ($page <= 1) {
        return $baseUrl;
    }
    return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . 'page=' . $page;
}
