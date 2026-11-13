<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Models\Post;

/**
 * Раздел «Статьи»: лента и страница статьи.
 *
 * Адреса — /stati и /stati/{slug}, а не донорские /blog: раздел русскоязычный,
 * и латинская калька в URL тут ничего не даёт.
 *
 * ⚠️ В отличие от донора, у нашей blog_posts НЕТ колонки seo_keywords (см. db/schema.sql).
 * Донорский BlogController её читал — скопируй как есть, и на каждой статье получим
 * обращение к несуществующему ключу. Мета keywords мы и так не выводим: layout печатает
 * её, только если контроллер задал осмысленное значение, а одинаковый список слов
 * на всех статьях — след шаблонной штамповки, а не оптимизация.
 */
final class BlogController extends Controller
{
    /** Сколько статей показываем в ленте. Раздел небольшой — пагинация не нужна. */
    private const FEED_LIMIT = 50;

    public function index(): void
    {
        $this->view('blog/index', [
            'page' => [
                'title'       => 'Статьи о запчастях для техники Caterpillar | ' . App::config('site.name'),
                'description' => 'Практические статьи о запчастях Caterpillar: как устроен номер детали, '
                    . 'чем оригинал отличается от аналога и Reman, износ ходовой части, фильтры '
                    . 'и рабочие жидкости.',
                'canonical'   => '/stati',
                // Последний элемент — без url: это текущая страница, ссылка на саму себя не нужна.
                'breadcrumb'  => [
                    ['name' => 'Статьи'],
                ],
                'jsonld'      => [
                    [
                        '@context' => 'https://schema.org',
                        '@type'    => 'CollectionPage',
                        'name'     => 'Статьи',
                        'url'      => url('/stati'),
                        'isPartOf' => ['@type' => 'WebSite', 'url' => rtrim((string) App::config('app.base_url'), '/') . '/'],
                    ],
                ],
            ],
            'posts' => Post::latest(self::FEED_LIMIT),
        ]);
    }

    public function show(string $slug): void
    {
        $post = Post::findBySlug($slug);
        if ($post === null) {
            $this->notFound();
        }

        $title = (string) $post['title'];
        $canonical = (string)($post['canonical']??'') ?: '/stati/' . (string) $post['slug'];

        $page = [
            'title'       => (string) ($post['seo_title'] ?: $title . ' | ' . App::config('site.name')),
            // Скобки не для красоты: '??' имеет НИЗШИЙ приоритет, чем '?:', и без них
            // выражение читается как ($seo ?: $excerpt) ?? '' — не то, что кажется.
            // Фолбэк на excerpt: seo_description у статьи может быть не заполнен.
            'description' => (string) ($post['seo_description'] ?: ($post['excerpt'] ?? '')),
            'canonical'   => $canonical,
            'noindex' => !empty($post['noindex']),
            'breadcrumb'  => [
                ['name' => 'Статьи', 'url' => '/stati'],
                ['name' => $title],
            ],
            'jsonld'      => [self::articleSchema((array) $post, $canonical)],
        ];

        // Обложек у статей сейчас нет (image_url = NULL). Ключ og_image добавляем ТОЛЬКО
        // при реальной картинке: пустая строка не «нейтральна» — layout прогнал бы её
        // через url() и выдал в <meta og:image> голый адрес сайта вместо изображения.
        // Пока ключа нет, layout сам подставит дефолтную OG-картинку.
        $image = trim((string) (($post['og_image']??'') ?: ($post['image_url'] ?? '')));
        if ($image === '') {
            $mapped = article_graphic((string) ($post['slug'] ?? ''));
            if (asset_exists($mapped)) {
                $image = asset($mapped);
            }
        }
        if ($image !== '') {
            $page['og_image'] = $image;
        }

        $this->view('blog/show', [
            'page'    => $page,
            'post'    => $post,
            'related' => Post::others((int) $post['id'], 3),
        ]);
    }

    /**
     * Schema.org Article для страницы статьи.
     *
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private static function articleSchema(array $post, string $canonical): array
    {
        $publisher = ['@type' => 'Organization', 'name' => App::config('site.name')];

        $ld = [
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => (string) $post['title'],
            'author' => trim((string)($post['author']??''))!=='' ? ['@type'=>'Person','name'=>$post['author']] : $publisher,
            'publisher'        => $publisher,
            'mainEntityOfPage' => url($canonical),
        ];

        // Дату отдаём в ISO 8601 ('2026-07-01T10:00:00+03:00'), а не как есть из БД.
        // Донор клал в разметку сырое '2026-07-01 10:00:00' — для schema.org это
        // невалидное значение: спецификация требует ISO 8601, там обязателен 'T'
        // между датой и временем. Битую дату валидатор просто выбрасывает.
        $rawDate = trim((string)($post['published_at']??''));
        $ts = $rawDate!=='' ? (new \DateTimeImmutable($rawDate,new \DateTimeZone('UTC')))->getTimestamp() : false;
        if ($ts !== false) {
            $ld['datePublished'] = date('c', $ts);
        }

        if (!empty($post['excerpt'])) {
            $ld['description'] = (string) $post['excerpt'];
        }

        // url() отдаёт внешнюю ссылку как есть, а внутренний путь делает абсолютным —
        // относительный адрес в разметке бесполезен. Донор склеивал base_url с image_url
        // вручную и на абсолютном адресе выдал бы 'https://сайт.ru https://cdn…'.
        $image = trim((string) ($post['image_url'] ?? ''));
        if ($image === '') {
            $mapped = article_graphic((string) ($post['slug'] ?? ''));
            if (asset_exists($mapped)) { $image = asset($mapped); }
        }
        if ($image !== '') {
            $ld['image'] = url($image);
        }

        return $ld;
    }
}
