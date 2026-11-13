<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Models\Category;
use App\Models\Product;

final class CatalogController extends Controller
{
    /** Товары с деревом категорий и фильтрами. */
    public function index(): void
    {
        $this->listing(null);
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
                'canonical'   => ($product['canonical'] ?? '') ?: '/tovar/' . (string) $product['slug'],
                'noindex' => !empty($product['noindex']), 'og_image' => (string) ($product['og_image'] ?? $product['image_url'] ?? ''),
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
        if ($category === null) $this->notFound();
        $this->listing($category);
    }

    public function brand(string $slug): void
    {
        $row = App::db()->fetch('SELECT id FROM brands WHERE slug=:slug AND is_active=1', ['slug'=>$slug]);
        if ($row === null) $this->notFound();
        $brand = (new \Catpart\Catalog(App::db()->pdo()))->record('brand', (int) $row['id']);
        $this->listing(null, $brand);
    }

    private function listing(?array $category, ?array $brand = null): void
    {
        $get = static fn (string $key, string $default = ''): string => isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : $default;
        $stock = $get('stock');
        if (!in_array($stock, ['in', 'order'], true)) $stock = '';
        $query = mb_substr($get('q'), 0, 120);
        $sort = $get('sort', 'default');
        if (!in_array($sort, ['default', 'name', 'sku', 'price', 'price_desc'], true)) $sort = 'default';
        $pageNum = max(1, (int) $get('page', '1'));
        $maxPage = max(1, (int) App::config('catalog.max_page', 200));
        $perPage = max(1, min(60, (int) App::config('catalog.per_page', 24)));
        if ($pageNum > $maxPage) $this->notFound();
        $result = Product::catalogListing($category !== null ? Category::subtreePrefix($category) : null, $stock, $query, $sort, $perPage, ($pageNum - 1) * $perPage, $brand !== null ? (int) $brand['id'] : null);
        $pages = max(1, min($maxPage, (int) ceil($result['total'] / $perPage)));
        if ($pageNum > $pages) $this->notFound();
        $route = $brand !== null ? '/brand/' . $brand['slug'] : ($category !== null ? '/catalog/' . $category['slug'] : '/catalog');
        $context = $brand ?? $category;
        $filters = array_filter(['stock' => $stock, 'q' => $query, 'sort' => $sort === 'default' ? '' : $sort], static fn ($v) => $v !== '');
        $suffix = $filters ? '?' . http_build_query($filters) : '';
        $h1 = $context !== null ? (string) (($context['seo_h1'] ?? '') ?: $context['name']) : 'Каталог запчастей Caterpillar';
        if($context===null)$h1=(string)(cms_meta('/catalog')['seo_h1']??$h1);
        $trail = $category !== null ? Category::trail($category) : [];
        $crumbs = $category !== null || $brand !== null ? [['name' => 'Каталог', 'url' => '/catalog']] : [];
        if ($brand !== null) $crumbs[] = ['name' => $brand['name']];
        foreach ($trail as $node) $crumbs[] = ['name' => $node['name'], 'url' => '/catalog/' . $node['slug']];
        if ($crumbs !== []) unset($crumbs[count($crumbs) - 1]['url']);
        else $crumbs = [['name' => 'Каталог']];
        if ($filters) header('X-Robots-Tag: noindex, follow');
        $this->view('catalog/listing', [
            'page' => [
                'title' => $context !== null ? (string) (($context['seo_title'] ?? '') ?: $h1 . ' | CatPart') : $h1 . ' | CatPart',
                'description' => (string) (($context['seo_description'] ?? '') ?: $h1 . '. Подбор по номеру детали, поставка по России.'),
                'keywords' => (string) ($context['seo_keywords'] ?? ''),
                'canonical' => ($context['canonical'] ?? '') ?: page_url($route, $pageNum), 'breadcrumb' => $crumbs,
                'noindex' => !empty($context['noindex']), 'og_image' => (string) ($context['og_image'] ?? $context['image_url'] ?? ''),
                'jsonld' => [[
                    '@context' => 'https://schema.org', '@type' => 'CollectionPage',
                    'name' => $h1, 'url' => url(page_url($route, $pageNum)),
                    'isPartOf' => ['@type' => 'WebSite', 'url' => rtrim((string) App::config('app.base_url'), '/') . '/'],
                ]],
            ],
            'category' => $category, 'brand' => $brand, 'navigation' => Category::navigation(),
            'activeIds' => array_column($trail, 'id'), 'h1' => $h1,
            'products' => $result['products'], 'total' => $result['total'],
            'pageNum' => $pageNum, 'pages' => $pages, 'baseUrl' => $route . $suffix,
            'route' => $route, 'suffix' => $suffix, 'stock' => $stock, 'query' => $query, 'sort' => $sort,
        ]);
    }
}
