<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\App;

/**
 * Категории каталога — дерево на материализованном пути.
 *
 * Ключевое отличие от донора: помимо parent_id у строки есть path вида '/1/16/'
 * (id всех предков + свой). Из него без единого доп. запроса известны и предки
 * (для крошек), и поддерево (для rollup товаров).
 *
 * Возвращает ассоциативные массивы строк — специально, вьюхи ждут именно их.
 */
final class Category
{
    /**
     * Лёгкий набор колонок для списков и плиток.
     * Осознанно НЕ 'SELECT *': в строке лежит intro_html (MEDIUMTEXT), и тащить его
     * ради сетки категорий — гонять килобайты HTML, которые никто не выведет.
     */
    private const LIST_COLUMNS = 'id, parent_id, slug, name, icon, image_url, products_count';

    /** @return list<array<string,mixed>> Корневые разделы (главная и хаб «Каталог»). */
    public static function roots(): array
    {
        return App::db()->fetchAll(
            'SELECT ' . self::LIST_COLUMNS . '
               FROM categories
              WHERE parent_id IS NULL AND is_active = 1
              ORDER BY sort, name'
        );
    }

    public static function navigation(): array
    {
        return App::db()->fetchAll('SELECT id, parent_id, slug, name, path FROM categories WHERE is_active = 1 ORDER BY sort, name');
    }

    /** @return list<array<string,mixed>> Прямые потомки категории. */
    public static function childrenOf(int $parentId): array
    {
        return App::db()->fetchAll(
            'SELECT ' . self::LIST_COLUMNS . '
               FROM categories
              WHERE parent_id = :p AND is_active = 1
              ORDER BY sort, name',
            ['p' => $parentId]
        );
    }

    /**
     * Потомки СРАЗУ НЕСКОЛЬКИХ родителей — одним запросом.
     *
     * Зачем отдельный метод: хабу «Каталог» нужны дети всех 15 корневых разделов.
     * Наивный путь — цикл с childrenOf() внутри, то есть 15 запросов вместо одного
     * (классический N+1). Группировку по parent_id делает вызывающий код.
     *
     * @param list<int> $parentIds
     * @return list<array<string,mixed>>
     */
    public static function childrenOfMany(array $parentIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $parentIds), static fn (int $i): bool => $i > 0));
        if ($ids === []) {
            return [];
        }

        // Плейсхолдеры по числу id — в SQL не попадает ни байта пользовательских данных.
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        return App::db()->fetchAll(
            'SELECT ' . self::LIST_COLUMNS . "
               FROM categories
              WHERE parent_id IN ($placeholders) AND is_active = 1
              ORDER BY sort, name",
            $ids
        );
    }

    /** @return array<string,mixed>|null Полная строка (нужны intro_html и seo_*). */
    public static function findBySlug(string $slug): ?array
    {
        $row = App::db()->fetch(
            'SELECT * FROM categories WHERE slug = :s AND is_active = 1 LIMIT 1',
            ['s' => $slug]
        );
        return $row === null ? null : array_replace($row, \Catpart\Catalog::metadata(App::db()->pdo(), 'category', (int) $row['id']));
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return App::db()->fetch('SELECT * FROM categories WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    /**
     * Разбор path в список id: '/1/16/' → [1, 16]. Последний id — сама категория.
     *
     * Заодно это валидация: path заполняет импортёр, и если он оставил мусор,
     * дальше поедет не мусор, а пустой список — вызывающий код это ловит.
     *
     * @param array<string,mixed> $category
     * @return list<int>
     */
    private static function pathIds(array $category): array
    {
        $raw = trim((string) ($category['path'] ?? ''), '/');
        if ($raw === '') {
            return [];
        }

        $ids = [];
        foreach (explode('/', $raw) as $chunk) {
            // Строго цифры: path — машинное поле, всё остальное считаем битым.
            if (preg_match('/^\d+$/', $chunk) !== 1) {
                return [];
            }
            $ids[] = (int) $chunk;
        }
        return $ids;
    }

    /**
     * Префикс для выборки поддерева: категория '/1/' → '/1/%'.
     *
     * Хвостовой слеш в path не декоративный, а несущий: без него LIKE '/1%' поймал бы
     * и '/1/', и '/12/', и '/100/' — товары чужих разделов. С ним '/1/%' совпадает
     * ровно с самой категорией ('%' матчит и пустую строку) и её потомками.
     *
     * Префикс СОБИРАЕМ ЗАНОВО из разобранных int, а не берём path из строки как есть:
     * в LIKE символы '%' и '_' — метасимволы, и пришли бы они в path (битый импорт,
     * ручная правка в phpMyAdmin) — выборка молча захватила бы лишние разделы.
     * После pathIds() в префиксе физически не может быть ничего, кроме цифр и слешей.
     *
     * @param array<string,mixed> $category
     */
    public static function subtreePrefix(array $category): string
    {
        $ids = self::pathIds($category);
        if ($ids === []) {
            // Аварийный путь: path пуст/битый — сузимся до самой категории.
            return '/' . (int) ($category['id'] ?? 0) . '/%';
        }
        return '/' . implode('/', $ids) . '/%';
    }

    /**
     * Хлебные крошки от корня к категории включительно — ОДНИМ запросом.
     *
     * 🔥 Главная правка относительно донора (Category.php:49-60). Там breadcrumb() шёл
     * вверх по parent_id циклом: SELECT на каждый уровень. Хуже — CatalogController
     * звал breadcrumb() ДВАЖДЫ за запрос (второй раз ради корня ветки), то есть на
     * дереве глубиной 4 это 8 запросов на отрисовку строчки навигации.
     *
     * Нам подниматься не надо: path уже содержит id всех предков по порядку. Достаём
     * их одним `WHERE id IN (...)` и раскладываем в порядке path (MySQL возвращает
     * строки в произвольном порядке, полагаться на него нельзя).
     *
     * @param array<string,mixed> $category
     * @return list<array{id:int,name:string,slug:string}>
     */
    public static function trail(array $category): array
    {
        $ids = self::pathIds($category);
        if ($ids === []) {
            // path не заполнен — крошка из одной себя честнее, чем пустота.
            return [[
                'id'   => (int) ($category['id'] ?? 0),
                'name' => (string) ($category['name'] ?? ''),
                'slug' => (string) ($category['slug'] ?? ''),
            ]];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = App::db()->fetchAll(
            "SELECT id, name, slug FROM categories WHERE id IN ($placeholders)",
            $ids
        );

        /** @var array<int,array<string,mixed>> $byId */
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $trail = [];
        foreach ($ids as $id) {
            // Предка могли деактивировать/удалить — пропускаем, но ветку не роняем.
            if (!isset($byId[$id])) {
                continue;
            }
            $trail[] = [
                'id'   => $id,
                'name' => (string) $byId[$id]['name'],
                'slug' => (string) $byId[$id]['slug'],
            ];
        }
        return $trail;
    }

    /** @return list<array<string,mixed>> Все активные категории (sitemap). */
    public static function allActive(): array
    {
        return App::db()->fetchAll('SELECT c.slug FROM categories c LEFT JOIN cp_catalog_meta m ON m.entity=\'category\' AND m.entity_id=c.id WHERE c.is_active=1 AND COALESCE(JSON_EXTRACT(m.data,\'$.noindex\'),0)<>1 ORDER BY c.id');
    }
}
