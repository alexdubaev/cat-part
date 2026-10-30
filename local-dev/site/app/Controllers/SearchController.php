<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Models\Product;

/**
 * Поиск по каталогу. Вход №1 в каталог запчастей — артикул, а не название,
 * поэтому логика построена вокруг номера детали.
 *
 * Порядок ровно такой:
 *   1. Нормализуем запрос той же функцией sku_norm(), что и импортёр — иначе поиск
 *      молча не найдёт то, что лежит в базе.
 *   2. Точное совпадение по products.sku_norm (индекс) — прямое попадание.
 *   3. Кросс-номера product_cross.cross_sku_norm — клиент приходит с чужим или
 *      устаревшим номером, для запчастей это норма, а не исключение.
 *   4. Артикул не дал ничего → FULLTEXT по названию и артикулу.
 */
final class SearchController extends Controller
{
    /**
     * Потолок выдачи по артикулу. Номер детали — запрос почти уникальный: совпадений
     * единицы (оригинал + совместимые). Сотня строк заведомо перекрывает реальность,
     * поэтому режем их в PHP, не гоняя в БД лишний COUNT ради пагинации.
     */
    private const ARTICLE_LIMIT = 100;

    /** Артикул короче двух символов искать бессмысленно — совпадёт пол-каталога. */
    private const ARTICLE_MIN_LEN = 2;

    public function index(): void
    {
        // Страницы поиска не должны попадать в индекс: это бесконечное множество URL
        // с перетасованным содержимым каталога — классический источник SEO-мусора.
        // Отдаём заголовком, а не мета-тегом: контракт $page фиксирован, а заголовок
        // работает независимо от раскладки.
        header('X-Robots-Tag: noindex, follow');

        $q = trim((string) ($_GET['q'] ?? ''));
        $perPage = max(1, (int) App::config('catalog.per_page', 24));
        $maxPage = max(1, (int) App::config('catalog.max_page', 200));
        $pageNum = max(1, (int) ($_GET['page'] ?? 1));
        if ($pageNum > $maxPage) {
            $pageNum = $maxPage;
        }

        $products = [];
        $total = 0;
        $pages = 0;

        if ($q !== '') {
            $skuNorm = sku_norm($q);

            // ── Шаг 1: артикул (свой + кросс-номера) ──
            $hits = [];
            if (mb_strlen($skuNorm, 'UTF-8') >= self::ARTICLE_MIN_LEN) {
                $hits = Product::byArticle($skuNorm, self::ARTICLE_LIMIT);
            }

            if ($hits !== []) {
                // Нашли по номеру — это главный результат, полнотекст уже не нужен:
                // он бы только разбавил точное попадание похожими названиями.
                $total = count($hits);
                $pages = (int) ceil($total / $perPage);
                $pageNum = $this->clampPage($pageNum, $pages);
                $products = array_slice($hits, ($pageNum - 1) * $perPage, $perPage);
            } else {
                // ── Шаг 2: полнотекст по названию и артикулу ──
                $booleanQuery = Product::toBooleanQuery($q);
                if ($booleanQuery !== '') {
                    $total = Product::countByText($booleanQuery);
                    $pages = (int) ceil($total / $perPage);
                    $pageNum = $this->clampPage($pageNum, $pages);
                    if ($total > 0) {
                        $products = Product::byText($booleanQuery, $perPage, ($pageNum - 1) * $perPage);
                    }
                }
            }
        }

        // Пустой запрос — это форма без результатов, а не 404: человек просто открыл поиск.
        $this->view('search/index', [
            'page' => [
                'title'       => $q !== ''
                    ? 'Поиск: ' . $q . ' — ' . App::config('site.name')
                    : 'Поиск запчастей по артикулу — ' . App::config('site.name'),
                'description' => 'Поиск запчастей для техники Caterpillar по номеру детали, '
                    . 'кросс-номерам и названию.',
                'canonical'   => '/poisk',
                'breadcrumb'  => [['name' => 'Поиск']],
            ],
            'q'        => $q,
            'products' => $products,
            'total'    => $total,
            'pageNum'  => $pageNum,
            'pages'    => $pages,
            'baseUrl'  => '/poisk?q=' . rawurlencode($q),
        ]);
    }

    /**
     * Прижимаем номер страницы к реальной выдаче.
     *
     * Иначе /poisk?q=3B-8489&page=99 при трёх совпадениях выдал бы пустую сетку с
     * подписью «ничего не нашлось» — и тут же «Найдено: 3». Страница за пределами
     * выдачи не 404: поиск отдаёт noindex, робота мы сюда не пускаем, а человеку
     * (пришёл по старой ссылке из чата) честнее показать последнюю страницу, чем ошибку.
     */
    private function clampPage(int $pageNum, int $pages): int
    {
        return min($pageNum, max(1, $pages));
    }
}
