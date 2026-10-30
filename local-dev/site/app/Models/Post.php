<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\App;

/**
 * Статьи раздела «Статьи» (таблица blog_posts).
 *
 * Возвращает ассоциативные массивы строк — как Category и Product: вьюхи ждут именно их.
 */
final class Post
{
    /**
     * Колонки для лент и карточек — контракт с вьюхами (blog/index, home) и с
     * HomeController. Осознанно НЕ 'SELECT *': в строке лежит body_html (MEDIUMTEXT,
     * ~6 КБ на статью), и тащить его ради списка из трёх заголовков на главной —
     * гонять килобайты HTML, которые никто не выведет. Ровно тот же довод, по которому
     * у Category заведён LIST_COLUMNS.
     */
    private const LIST_COLUMNS = 'slug, title, excerpt, image_url, published_at';

    /**
     * Условие «статья видна публике» — ОДНО определение на весь класс.
     *
     * Зачем константой, а не копией в каждом запросе: правило видимости обязано быть
     * одинаковым в ленте, на странице статьи и в sitemap. Разъедься они хоть на одно
     * условие — получим либо статью, доступную по прямой ссылке, но отсутствующую в
     * ленте, либо адрес в карте сайта, который отдаёт роботу 404.
     *
     * published_at <= NOW() решает сразу две задачи:
     *   • отложенная публикация — статья с будущей датой ждёт своего часа сама;
     *   • черновик без даты отсекается автоматически: в MySQL сравнение NULL <= NOW()
     *     даёт NULL, то есть «не истина», и строка в выдачу не попадает.
     * Отдельное `published_at IS NOT NULL` поэтому не нужно.
     *
     * Порядок условий совпадает с idx_blog_active_pub (is_active, published_at):
     * равенство по первой колонке + диапазон по второй — индекс отрабатывает целиком.
     *
     * ⚠️ NOW() берёт время СЕРВЕРА MySQL, а PHP живёт в Europe/Moscow (config: app.timezone).
     * Разъезд часовых поясов сдвинет момент публикации на несколько часов — для статей
     * это несущественно, но при отложенной публикации «к 10:00» об этом стоит помнить.
     */
    private const VISIBLE = 'is_active = 1 AND published_at <= NOW()';

    /**
     * Свежие статьи: лента раздела и блок из трёх на главной.
     *
     * LIMIT привязан параметром, а не вклеен в строку запроса: Database биндит int
     * как PDO::PARAM_INT, и при EMULATE_PREPARES=false MySQL принимает такой LIMIT
     * (донор конкатенировал число в SQL именно потому, что не умел биндить тип).
     *
     * Второй ключ сортировки id DESC не декоративный: у четырёх стартовых статей
     * даты разные, но как только две выйдут одним днём, порядок между ними без
     * второго ключа станет недетерминированным — лента начнёт «прыгать» между хитами.
     *
     * @return list<array<string,mixed>>
     */
    public static function latest(int $limit = 20): array
    {
        // Потолок жёсткий: наружу число попадает только из кода, но лимит-страховка
        // стоит дешевле, чем однажды выбранная целиком таблица.
        $limit = max(1, min(50, $limit));

        return App::db()->fetchAll(
            'SELECT ' . self::LIST_COLUMNS . '
               FROM blog_posts
              WHERE ' . self::VISIBLE . '
              ORDER BY published_at DESC, id DESC
              LIMIT :lim',
            ['lim' => $limit]
        );
    }

    /**
     * Полная строка статьи (нужны id, body_html и seo_*).
     *
     * @return array<string,mixed>|null
     */
    public static function findBySlug(string $slug): ?array
    {
        return App::db()->fetch(
            'SELECT * FROM blog_posts
              WHERE slug = :s AND ' . self::VISIBLE . '
              LIMIT 1',
            ['s' => $slug]
        );
    }

    /**
     * Другие статьи — для блока «Читайте также» под статьёй.
     *
     * @return list<array<string,mixed>>
     */
    public static function others(int $exceptId, int $limit = 3): array
    {
        $limit = max(1, min(12, $limit));

        return App::db()->fetchAll(
            'SELECT ' . self::LIST_COLUMNS . '
               FROM blog_posts
              WHERE ' . self::VISIBLE . ' AND id <> :id
              ORDER BY published_at DESC, id DESC
              LIMIT :lim',
            ['id' => $exceptId, 'lim' => $limit]
        );
    }

    /**
     * Все опубликованные статьи для sitemap.
     *
     * Без LIMIT намеренно: статей десятки, не десятки тысяч — разбивать карту на чанки,
     * как для товаров, тут не из-за чего. published_at отдаём ради <lastmod>.
     *
     * @return list<array<string,mixed>>
     */
    public static function allPublished(): array
    {
        return App::db()->fetchAll(
            'SELECT slug, published_at
               FROM blog_posts
              WHERE ' . self::VISIBLE . '
              ORDER BY published_at DESC, id DESC'
        );
    }
}
