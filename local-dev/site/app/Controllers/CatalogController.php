<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Models\Category;
use App\Models\Product;

final class CatalogController extends Controller
{
    /** Хаб «Каталог»: корневые разделы, у каждого — свои подразделы. */
    public function index(): void
    {
        $roots = Category::roots();

        // Дети всех корней — ОДИН запрос вместо цикла с запросом внутри (N+1).
        // На 15 корнях наивный путь стоил бы 15 запросов на отрисовку одной страницы.
        $rootIds = array_map(static fn (array $c): int => (int) $c['id'], $roots);
        $children = Category::childrenOfMany($rootIds);

        // Раскладываем детей по родителям в памяти: сортировка уже сделана SQL,
        // порядок внутри группы сохраняется.
        $byParent = [];
        foreach ($children as $child) {
            $byParent[(int) $child['parent_id']][] = $child;
        }
        foreach ($roots as $i => $root) {
            $roots[$i]['children'] = $byParent[(int) $root['id']] ?? [];
        }

        $this->view('catalog/index', [
            'page' => [
                'title'       => 'Каталог запчастей для техники Caterpillar | ' . App::config('site.name'),
                'description' => 'Каталог запчастей Caterpillar по разделам: гидравлика, двигатель, силовая '
                    . 'передача, ходовая часть, кабины, фильтры и жидкости, навесное оборудование, электрика. '
                    . 'Подбор по артикулу.',
                'canonical'   => '/catalog',
                'breadcrumb'  => [
                    // Последний элемент — без url: это текущая страница, ссылка на саму себя не нужна.
                    ['name' => 'Каталог'],
                ],
            ],
            'categories' => $roots,
        ]);
    }

    /**
     * Карточка товара /tovar/{slug}.
     *
     * Крошки строим через Category::trail() — он разбирает материализованный path
     * одним запросом; подъём по parent_id циклом стоил бы запрос на каждый уровень.
     */
    public function product(string $slug): void
    {
        $product = Product::findBySlug($slug);
        if ($product === null) {
            $this->notFound();
        }

        $id   = (int) $product['id'];
        $sku  = trim((string) ($product['sku'] ?? ''));
        $name = (string) $product['name'];
        $h1   = trim((string) ($product['seo_h1'] ?? '')) ?: ($sku !== '' ? $sku . ' — ' . $name : $name);

        $images     = Product::images($id);
        $attributes = Product::attributes($id);
        $compat     = Product::compat($id);

        $crumbs = [['name' => 'Каталог', 'url' => '/catalog']];
        if (!empty($product['category_slug'])) {
            $category = Category::findBySlug((string) $product['category_slug']);
            if ($category !== null) {
                foreach (Category::trail($category) as $node) {
                    $crumbs[] = ['name' => $node['name'], 'url' => '/catalog/' . $node['slug']];
                }
            }
        }
        // Последняя крошка — без url: это текущая страница.
        $crumbs[] = ['name' => $sku !== '' ? $sku : $name];

        // Ровно 5 — это одна полная строка сетки. Восемь дали бы 5 + 3 и дырку во второй.
        $neighbours = Product::neighbours((int) ($product['category_id'] ?? 0), $id, 5);

        /**
         * JSON-LD Product. Блок offers НЕ отдаём осознанно: цены в базе нет («по запросу»),
         * а Offer без price — либо невалидная разметка, либо враньё о наличии.
         * Появится прайс — появится и offers.
         */
        $jsonld = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $name,
            'sku'         => $sku,
            'url'         => url('/tovar/' . (string) $product['slug']),
            'description' => (string) ($product['seo_description'] ?? ''),
        ];
        if ($images !== []) {
            $jsonld['image'] = url((string) $images[0]['url']);
        }
        if (!empty($product['category_name'])) {
            $jsonld['category'] = (string) $product['category_name'];
        }

        $this->view('catalog/product', [
            'page' => [
                'title'       => (string) ($product['seo_title'] ?: $h1 . ' | ' . App::config('site.name')),
                'description' => (string) ($product['seo_description'] ?: $name
                    . ' — запчасть для техники Caterpillar. Подбор по артикулу, поставка по России.'),
                'keywords'    => (string) ($product['seo_keywords'] ?? ''),
                'canonical'   => '/tovar/' . (string) $product['slug'],
                'breadcrumb'  => $crumbs,
                'jsonld'      => [$jsonld],
            ],
            'product'    => $product,
            'h1'         => $h1,
            'images'     => $images,
            'attributes' => $attributes,
            'compat'     => $compat,
            'neighbours' => $neighbours,
        ]);
    }

    public function category(string $slug): void
    {
        $category = Category::findBySlug($slug);
        if ($category === null) {
            $this->notFound();
        }

        $perPage = max(1, (int) App::config('catalog.per_page', 24));
        $maxPage = max(1, (int) App::config('catalog.max_page', 200));
        $pageNum = max(1, (int) ($_GET['page'] ?? 1));

        // Потолок глубины листания: LIMIT 24 OFFSET 19800 заставляет MySQL прочитать и
        // выбросить 19 800 строк. Живой человек туда не ходит — ходит бот, и выедает ресурс.
        // Отдаём 404, а не молчаливый клип до последней страницы: несуществующая страница
        // и должна отвечать 404, иначе робот будет ходить по ней вечно.
        if ($pageNum > $maxPage) {
            $this->notFound();
        }

        /**
         * 🔥 Никакого COUNT(*). Итог берём из готового счётчика categories.products_count,
         * который пересчитывает импортёр (он считает товары ВКЛЮЧАЯ потомков — как и rollup ниже).
         *
         * У донора Product::countByCategory() выполнял COUNT(*) на КАЖДЫЙ показ категории:
         * на разделе в 20 000 строк это полный скан индекса ради одного числа, которое
         * меняется раз в импорт.
         */
        $total = (int) ($category['products_count'] ?? 0);

        $children = Category::childrenOf((int) $category['id']);

        // Товары раздела и всей его ветки — см. комментарий к Product::bySubtree().
        $products = Product::bySubtree(
            Category::subtreePrefix($category),
            $perPage,
            ($pageNum - 1) * $perPage
        );

        // Счётчик — источник правды для пагинации, но если импортёр его не пересчитал,
        // а товары уже есть, мы всё равно покажем первую страницу, а не пустоту.
        $pages = (int) ceil($total / $perPage);
        if ($pages < 1 && $products !== []) {
            $pages = 1;
        }
        if ($pageNum > 1 && $products === []) {
            $this->notFound();
        }

        $h1 = (string) ($category['seo_h1'] ?: $category['name']);
        $baseUrl = '/catalog/' . (string) $category['slug'];

        // Крошки: Каталог → …предки… → текущая. trail() — один запрос на всю цепочку.
        $crumbs = [['name' => 'Каталог', 'url' => '/catalog']];
        $trail = Category::trail($category);
        $lastIndex = count($trail) - 1;
        foreach ($trail as $i => $node) {
            $crumb = ['name' => $node['name']];
            // У последней крошки url нет — она и есть текущая страница.
            if ($i !== $lastIndex) {
                $crumb['url'] = '/catalog/' . $node['slug'];
            }
            $crumbs[] = $crumb;
        }

        $this->view('catalog/category', [
            'page' => [
                'title'       => (string) ($category['seo_title'] ?: $h1 . ' для техники Caterpillar | ' . App::config('site.name')),
                'description' => (string) ($category['seo_description'] ?: $h1
                    . ' для техники Caterpillar. Подбор по номеру детали, поставка по России.'),
                'keywords'    => (string) ($category['seo_keywords'] ?? ''),
                // Страницы листания канонизируем на самих себя, а не на первую: иначе
                // товары со 2-й и дальше страниц выпадают из индекса вместе с ней.
                'canonical'   => page_url($baseUrl, $pageNum),
                'breadcrumb'  => $crumbs,
                'jsonld'      => [
                    [
                        '@context'    => 'https://schema.org',
                        '@type'       => 'CollectionPage',
                        'name'        => $h1,
                        'url'         => url(page_url($baseUrl, $pageNum)),
                        'isPartOf'    => ['@type' => 'WebSite', 'url' => rtrim((string) App::config('app.base_url'), '/') . '/'],
                    ],
                ],
            ],
            'category' => $category,
            'h1'       => $h1,
            'children' => $children,
            'products' => $products,
            'total'    => $total,
            'pageNum'  => $pageNum,
            'pages'    => $pages,
            'baseUrl'  => $baseUrl,
        ]);
    }
}
