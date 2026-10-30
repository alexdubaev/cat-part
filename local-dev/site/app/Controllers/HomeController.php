<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Models\Category;
use App\Models\Post;

final class HomeController extends Controller
{
    public function index(): void
    {
        $siteName = (string) App::config('site.name');
        $tagline = (string) App::config('site.tagline');
        $baseUrl = rtrim((string) App::config('app.base_url'), '/');

        $this->view('home', [
            'page' => [
                'title'       => $siteName . ' — ' . mb_strtolower($tagline, 'UTF-8') . ': подбор по артикулу',
                'description' => 'Запчасти для техники Caterpillar: гидравлика, двигатель, ходовая часть, '
                    . 'фильтры, навесное оборудование. Подбор по номеру детали и кросс-номерам, '
                    . 'поставка по России.',
                'canonical'   => '/',
                'breadcrumb'  => [],
                'jsonld'      => [
                    // WebSite + SearchAction: даёт поисковику строку поиска по сайту прямо в выдаче.
                    // Адрес не выдуман — это наш реальный маршрут /poisk.
                    [
                        '@context'        => 'https://schema.org',
                        '@type'           => 'WebSite',
                        'name'            => $siteName,
                        'url'             => $baseUrl . '/',
                        'potentialAction' => [
                            '@type'       => 'SearchAction',
                            'target'      => [
                                '@type'       => 'EntryPoint',
                                'urlTemplate' => $baseUrl . '/poisk?q={search_term_string}',
                            ],
                            'query-input' => 'required name=search_term_string',
                        ],
                    ],
                ],
            ],
            'rootCategories' => Category::roots(),

            // Три свежие статьи для блока на главной. Ключи строк —
            // slug, title, excerpt, image_url, published_at (см. Post::latest()).
            // Пустой массив, пока статьи не залиты, — вьюха обязана это пережить
            // и просто не показать блок.
            'articles' => Post::latest(3),
        ]);
    }
}
