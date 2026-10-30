<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\App;

/**
 * Товары каталога.
 *
 * Здесь сознательно НЕТ метода «похожие товары». У донора он был (Product::related(),
 * Product.php:79-87) и делал `ORDER BY RAND() LIMIT 8`: MySQL присваивает случайное
 * число КАЖДОЙ строке категории, сортирует всё это и берёт 8 — то есть материализует
 * весь раздел на каждый показ карточки. На донорских 2 672 товарах терпимо, на наших
 * 22 000 — смерть. Карточки товара в Ф1 нет, поэтому и метода нет: болезнь не
 * «переписана», а не занесена. Когда в Ф2 понадобится — дешёвый приём такой:
 * взять products_count раздела, выбрать случайный OFFSET и снять оттуда N строк
 * подряд (одна дешёвая выборка по индексу вместо сортировки всего раздела).
 */
final class Product
{
    /** Колонки витрины — ровно то, что ждут вьюхи (контракт с фронтом). */
    private const LIST_COLUMNS = 'p.id, p.slug, p.sku, p.name, p.price, p.in_stock, p.image_url';

    /**
     * Минимальная длина слова для FULLTEXT.
     * InnoDB не индексирует токены короче innodb_ft_min_token_size (по умолчанию 3).
     * Слово в 1-2 символа, помеченное как обязательное ('+ab'), обнулит ВСЮ выдачу —
     * без ошибки, просто пусто. Поэтому такие слова из boolean-запроса выкидываем.
     */
    private const FT_MIN_TOKEN = 3;

    /**
     * 🔥 ROLLUP: товары раздела И ВСЕХ ЕГО ПОТОМКОВ.
     *
     * У донора товары искались строго `WHERE category_id = :c`, поэтому родительский
     * раздел показывал НОЛЬ товаров, если они висят на детях (у него это маскировалось
     * тем, что 71% товаров свалено в один плоский раздел). У нас дерево настоящее:
     * «Гидравлика» → «Гидроцилиндры и компоненты» → товары. Открыв «Гидравлику»,
     * человек обязан увидеть товары всей ветки.
     *
     * Как: categories.path — материализованный путь, поддерево = префиксный LIKE.
     * Рекурсия не нужна (её и нельзя — на MySQL 5.7 нет CTE, а хостинг может отдать 5.7).
     *
     * Почему это не дорого, хотя тут JOIN:
     *   • categories — таблица на сотни строк, LIKE '/1/%' идёт по idx_categories_path
     *     префиксным range-сканом (префикс без ведущего '%' — индекс работает);
     *   • для каждой найденной категории товары берутся по idx_products_cat_list,
     *     который начинается с category_id;
     *   • на листе запрос вырождается в одну категорию — то есть ровно в донорский
     *     случай, но без его дефекта.
     *
     * Сортировка — ТОЛЬКО по колонкам: in_stock (в наличии выше), price, name. Все три
     * входят в idx_products_cat_list в этом же порядке, поэтому строки читаются из
     * индекса. У донора было `ORDER BY (p.price IS NULL), p.price, p.name` — первый
     * ключ сортировки это ВЫРАЖЕНИЕ, его невозможно проиндексировать в принципе:
     * MySQL обязан вычислить его для каждой строки раздела и отсортировать всё целиком.
     *
     * Плата за отказ от выражения: price ASC ставит NULL (цена «по запросу») в начало
     * внутри своей группы. Это осознанный размен — in_stock DESC идёт первым ключом,
     * так что сверху всё равно товары в наличии, а не бесценные позиции.
     *
     * @return list<array<string,mixed>>
     */
    public static function bySubtree(string $pathPrefix, int $limit, int $offset): array
    {
        return App::db()->fetchAll(
            'SELECT ' . self::LIST_COLUMNS . '
               FROM products p
               JOIN categories c ON c.id = p.category_id
              WHERE c.path LIKE :prefix
                AND c.is_active = 1
                AND p.is_active = 1
              ORDER BY p.in_stock DESC, p.price, p.name
              LIMIT :lim OFFSET :off',
            ['prefix' => $pathPrefix, 'lim' => $limit, 'off' => $offset]
        );
    }

    /**
     * Точное совпадение по артикулу + поиск по кросс-номерам — одним запросом.
     *
     * Вход №1 в каталог запчастей — артикул. Клиент приходит с номером, который прочитал
     * на снятой детали, и номер этот запросто окажется чужим или устаревшим — поэтому
     * кросс-номера (product_cross) равноправная половина поиска, а не довесок.
     *
     * Оба условия — сравнение с индексированной колонкой (*_norm), без функций над полем:
     * любая обёртка вида REPLACE(sku,'-','') убила бы индекс и увела в full scan 22 000 строк.
     * Ради этого нормализованные колонки и заведены.
     *
     * src=0 (свой артикул) сортируется выше src=1 (кросс) — прямое попадание всегда первое.
     * Параметры :n1/:n2 намеренно разные при одном значении: PDO с нативными prepared
     * (EMULATE_PREPARES=false) не даёт переиспользовать одно имя дважды.
     *
     * @return list<array<string,mixed>>
     */
    public static function findBySlug(string $slug): ?array
    {
        return App::db()->fetch(
            'SELECT p.*, c.slug AS category_slug, c.name AS category_name, c.path AS category_path,
                    b.name AS brand_name
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
               LEFT JOIN brands b ON b.id = p.brand_id
              WHERE p.slug = :s AND p.is_active = 1
              LIMIT 1',
            ['s' => $slug]
        );
    }

    /** Fresh active product snapshot for cart/order validation. */
    public static function findActiveById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        return App::db()->fetch(
            'SELECT id, slug, sku, name, price, in_stock, image_url
               FROM products WHERE id = :id AND is_active = 1 LIMIT 1',
            ['id' => $id]
        );
    }

    /**
     * Характеристики карточки. Единица измерения лежит в attributes.unit ОТДЕЛЬНО
     * от имени (см. комментарий к схеме) — склеиваем только при выводе.
     *
     * @return list<array<string,mixed>>
     */
    public static function attributes(int $productId): array
    {
        return App::db()->fetchAll(
            'SELECT a.name, a.unit, v.value_str, v.value_num
               FROM product_attr_values v
               JOIN attributes a ON a.id = v.attribute_id
              WHERE v.product_id = :p
              ORDER BY a.sort, a.name',
            ['p' => $productId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function images(int $productId): array
    {
        return App::db()->fetchAll(
            'SELECT url, alt, is_primary FROM product_images
              WHERE product_id = :p ORDER BY is_primary DESC, sort, id',
            ['p' => $productId]
        );
    }

    /**
     * Совместимая техника: тип машины → перечень моделей.
     * @return list<array<string,mixed>>
     */
    public static function compat(int $productId): array
    {
        return App::db()->fetchAll(
            'SELECT machine_type, models FROM product_compat
              WHERE product_id = :p ORDER BY sort, id',
            ['p' => $productId]
        );
    }

    /**
     * Соседи по разделу вместо «похожих» через ORDER BY RAND() (см. шапку класса):
     * одна выборка по idx_products_cat_list, текущий товар исключён.
     *
     * @return list<array<string,mixed>>
     */
    public static function neighbours(int $categoryId, int $exceptId, int $limit): array
    {
        if ($categoryId <= 0) {
            return [];
        }
        return App::db()->fetchAll(
            'SELECT ' . self::LIST_COLUMNS . '
               FROM products p
              WHERE p.category_id = :c AND p.id <> :id AND p.is_active = 1
              ORDER BY p.in_stock DESC, p.name
              LIMIT :lim',
            ['c' => $categoryId, 'id' => $exceptId, 'lim' => $limit]
        );
    }

    public static function byArticle(string $skuNorm, int $limit): array
    {
        $rows = App::db()->fetchAll(
            'SELECT p.id, p.slug, p.sku, p.name, p.price, p.in_stock, p.image_url, 0 AS src
               FROM products p
              WHERE p.sku_norm = :n1 AND p.is_active = 1
              UNION
             SELECT p.id, p.slug, p.sku, p.name, p.price, p.in_stock, p.image_url, 1 AS src
               FROM products p
               JOIN product_cross x ON x.product_id = p.id
              WHERE x.cross_sku_norm = :n2 AND p.is_active = 1
              ORDER BY src, in_stock DESC, name
              LIMIT :lim',
            ['n1' => $skuNorm, 'n2' => $skuNorm, 'lim' => $limit]
        );

        // Один товар может совпасть и своим артикулом, и кросс-номером (или несколькими
        // кросс-номерами сразу) — UNION схлопывает лишь полностью одинаковые строки,
        // а у них разный src. Дедуплицируем по id, оставляя первое (лучшее) попадание.
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            unset($row['src']);  // служебное поле наружу не отдаём
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Сырой запрос → безопасное выражение для FULLTEXT ... IN BOOLEAN MODE.
     *
     * В boolean mode символы + - > < ( ) ~ * " @ — операторы. Пользовательский ввод
     * с ними («шланг (РВД)») — это в лучшем случае пустая выдача, в худшем SQL-ошибка.
     * Поэтому: вычищаем операторы, требуем каждое слово ('+') и разрешаем совпадение
     * по префиксу ('*') — «гидроцил» найдёт «гидроцилиндр».
     *
     * Пустая строка на выходе = искать нечего (например, запрос из одних коротких слов);
     * вызывающий код обязан это проверить и не ходить в БД.
     */
    public static function toBooleanQuery(string $raw): string
    {
        $clean = preg_replace('/[+\-><()~*"@]+/u', ' ', $raw) ?? '';
        $tokens = preg_split('/\s+/u', trim($clean), -1, PREG_SPLIT_NO_EMPTY);
        if ($tokens === false || $tokens === []) {
            return '';
        }

        $out = [];
        foreach ($tokens as $token) {
            if (mb_strlen($token, 'UTF-8') < self::FT_MIN_TOKEN) {
                continue;
            }
            $out[] = '+' . $token . '*';
            // Потолок слов: защита от запроса-простыни, скормленного ботом.
            if (count($out) >= 8) {
                break;
            }
        }
        return implode(' ', $out);
    }

    /**
     * Полнотекстовый поиск по названию и артикулу.
     *
     * Индекс ft_products_search создан по (name, sku) — MATCH обязан перечислять
     * колонки ровно в этом составе, иначе MySQL не найдёт индекс и упадёт с ошибкой.
     *
     * Сортируем по in_stock/name, а не по релевантности: в boolean mode релевантность
     * InnoDB — грубый счётчик вхождений слова, а повтор MATCH в ORDER BY заставляет
     * считать её вторым проходом. Для каталога запчастей «в наличии сверху» полезнее.
     *
     * @return list<array<string,mixed>>
     */
    public static function byText(string $booleanQuery, int $limit, int $offset): array
    {
        return App::db()->fetchAll(
            'SELECT ' . self::LIST_COLUMNS . '
               FROM products p
              WHERE MATCH(p.name, p.sku) AGAINST (:q IN BOOLEAN MODE)
                AND p.is_active = 1
              ORDER BY p.in_stock DESC, p.name
              LIMIT :lim OFFSET :off',
            ['q' => $booleanQuery, 'lim' => $limit, 'off' => $offset]
        );
    }

    /**
     * Число совпадений для пагинации поиска.
     *
     * Единственный COUNT(*) в проекте, и он неизбежен: для произвольного запроса
     * счётчик не предрассчитаешь (в отличие от категорий, где есть products_count).
     * Работает по FULLTEXT-индексу и только на странице поиска — не на каждом хите каталога.
     */
    public static function countByText(string $booleanQuery): int
    {
        $row = App::db()->fetch(
            'SELECT COUNT(*) AS c
               FROM products p
              WHERE MATCH(p.name, p.sku) AGAINST (:q IN BOOLEAN MODE)
                AND p.is_active = 1',
            ['q' => $booleanQuery]
        );
        return (int) ($row['c'] ?? 0);
    }

    public static function countActive(): int
    {
        $row = App::db()->fetch('SELECT COUNT(*) AS c FROM products WHERE is_active = 1');
        return (int) ($row['c'] ?? 0);
    }

    /**
     * Порция товаров для чанка sitemap. ORDER BY id — чтобы разбивка на файлы была
     * стабильной между запросами и товар не пропал/не задвоился между чанками.
     *
     * @return list<array<string,mixed>>
     */
    public static function slugChunk(int $limit, int $offset): array
    {
        return App::db()->fetchAll(
            'SELECT slug, updated_at FROM products WHERE is_active = 1 ORDER BY id LIMIT :lim OFFSET :off',
            ['lim' => $limit, 'off' => $offset]
        );
    }
}
