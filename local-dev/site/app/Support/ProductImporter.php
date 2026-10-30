<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\App;
use Throwable;

/**
 * Импорт товаров из выгрузки parts.cat.com (TSV/CSV) + локальная папка с картинками.
 *
 * Колонки выгрузки:
 *   Article | Article display | Product URL | Breadcrumb path RU | Description RU |
 *   Technical description RU | Compatible models RU | Image directory | Image files |
 *   Image URLs | Image count | Status
 *
 * Что делает импортёр:
 *   1. Из «Breadcrumb path RU» строит дерево категорий (без первого и последнего сегмента)
 *      и достаёт имя товара (последний сегмент без префикса артикула).
 *   2. «Technical description RU» разбирает в атрибуты (attributes + product_attr_values),
 *      числа кладёт в value_num — иначе фильтры по диапазону работать не будут.
 *   3. «Compatible models RU» разбирает на пары «тип техники → модели» (product_compat).
 *   4. Картинки копирует из локальной папки в /upload/products/<shard>/<article>/ и пишет
 *      product_images. Внешние URL донора НЕ используем как источник показа.
 *   5. Формирует SEO-поля (h1/title/description/keywords) из фактов.
 *
 * 🔴 Правовая рамка (plans/2026-07-15_catpart_plan.md, раздел 0): тексты на сайте — свои.
 * Поэтому «Description RU» (маркетинговый текст донора) всегда сохраняется в products.source_desc,
 * а в витрину (body_html) идёт то, что выбрано режимом $descMode:
 *   'own'   — собственное описание, собранное из фактов (по умолчанию, юридически чистое);
 *   'clean' — текст донора, очищенный от «Развернуть» и предупреждения Prop 65;
 *   'none'  — body_html не заполняем (только source_desc, под будущий ИИ-рерайт).
 *
 * Идемпотентность: повторный прогон обновляет товар (ключ — products.slug из артикула),
 * атрибуты/совместимость/картинки переписываются начисто. Прогон по частям — см. import_runs.
 */
final class ProductImporter
{
    /** Режимы описания. */
    public const DESC_OWN = 'own';
    public const DESC_CLEAN = 'clean';
    public const DESC_NONE = 'none';

    private string $descMode = self::DESC_OWN;
    private string $brandName = 'Cat';
    private ?string $imagesSrc = null;
    private bool $dryRun = false;

    /** Кэши, чтобы не долбить БД на каждой строке. */
    /** «parent_id|нормализованное имя» → id категории (читается один раз целиком). */
    private array $catIndex = [];
    /** id → ['path' => '/6/123/', 'depth' => 1] для достройки дерева. */
    private array $catMeta = [];
    /** Занятые слаги категорий: uq_categories_slug уникален глобально. */
    private array $catSlugs = [];
    /** @var string[] как разложилась последняя крошка — для отчёта dry-run */
    private array $catTrace = [];

    /** @var array<string,int> английские названия характеристик, которых нет в словаре */
    private array $untranslated = [];

    /** Словарь из таблицы import_terms (переводы, дописанные руками). */
    private array $termMap = [];

    /** @var array<string,int> причины пропуска строк — без них 3794 «пропущено» нечем объяснить */
    private array $skipReasons = [];

    /** @var string[] примеры пропущенных строк (первые несколько) */
    private array $skipSamples = [];

    /** Собирать ли полный список пропусков (включается опцией skip_log). */
    private bool $skipLog = false;
    /** @var array<int,array<int,string>> артикул, причина, ссылка донора, крошка */
    private array $skipRows = [];
    private array $attrCache = [];
    private ?int $brandId = null;

    /** Счётчики последнего прогона. */
    private array $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0,
                            'images' => 0, 'categories' => 0];

    /** @var string[] последние ошибки (для вывода) */
    private array $errorLog = [];

    /** @var array<int,array<string,mixed>> образцы разбора для --dry-run */
    private array $preview = [];

    public function __construct(array $opts = [])
    {
        if (!empty($opts['desc']) && in_array($opts['desc'], [self::DESC_OWN, self::DESC_CLEAN, self::DESC_NONE], true)) {
            $this->descMode = (string) $opts['desc'];
        }
        if (!empty($opts['brand'])) {
            $this->brandName = (string) $opts['brand'];
        }
        if (!empty($opts['images_src'])) {
            $this->imagesSrc = rtrim((string) $opts['images_src'], "/\\");
        }
        $this->dryRun = !empty($opts['dry_run']);
        $this->skipLog = !empty($opts['skip_log']);
    }

    public function stats(): array { return $this->stats; }
    public function errors(): array { return $this->errorLog; }
    public function preview(): array { return $this->preview; }

    /** Английские названия характеристик без перевода, по убыванию частоты. */
    public function untranslated(): array
    {
        arsort($this->untranslated);
        return $this->untranslated;
    }

    /* ==========================================================================
     *  СХЕМА — доп. таблицы и колонки под импорт (идемпотентно)
     * ========================================================================== */

    public function ensureSchema(): void
    {
        $db = App::db();

        $db->execute(
            'CREATE TABLE IF NOT EXISTS product_compat (
                id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
                product_id   INT UNSIGNED NOT NULL,
                machine_type VARCHAR(190) NOT NULL,
                models       TEXT NOT NULL,
                sort         INT NOT NULL DEFAULT 100,
                PRIMARY KEY (id),
                KEY idx_compat_product (product_id, sort),
                KEY idx_compat_type (machine_type),
                CONSTRAINT fk_compat_product FOREIGN KEY (product_id)
                    REFERENCES products (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $db->execute(
            'CREATE TABLE IF NOT EXISTS import_runs (
                id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                file       VARCHAR(255) NOT NULL,
                file_mtime INT UNSIGNED NOT NULL DEFAULT 0,
                file_size  BIGINT UNSIGNED NOT NULL DEFAULT 0,
                byte_pos   BIGINT UNSIGNED NOT NULL DEFAULT 0,
                row_pos    INT UNSIGNED NOT NULL DEFAULT 0,
                rows_done  INT UNSIGNED NOT NULL DEFAULT 0,
                created    INT UNSIGNED NOT NULL DEFAULT 0,
                updated    INT UNSIGNED NOT NULL DEFAULT 0,
                skipped    INT UNSIGNED NOT NULL DEFAULT 0,
                errors     INT UNSIGNED NOT NULL DEFAULT 0,
                status     VARCHAR(16) NOT NULL DEFAULT "running",
                last_error VARCHAR(500) NULL,
                started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_run_file (file, file_mtime)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        /**
         * Словарь названий характеристик. Зачем таблица, а не только массив в коде:
         * выгрузка идёт пачками, и список непереведённого в отчёте показывал бы термины
         * только текущей пачки — полной картины по 22 000 строк не собрать никогда.
         * Здесь термины копятся между запусками с частотой, а перевод можно вписать
         * прямо в term_ru, не трогая код.
         */
        $db->execute(
            'CREATE TABLE IF NOT EXISTS import_terms (
                id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
                term    VARCHAR(190) NOT NULL,
                term_ru VARCHAR(190) NULL,
                hits    INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY uq_term (term),
                KEY idx_term_todo (term_ru, hits)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Колонки под сырьё донора. ADD COLUMN IF NOT EXISTS есть не везде — глушим дубль.
        foreach ([
            'ALTER TABLE products ADD COLUMN source_desc MEDIUMTEXT NULL',
            'ALTER TABLE products ADD COLUMN source_url VARCHAR(500) NULL',
            'ALTER TABLE import_runs ADD COLUMN row_pos INT UNSIGNED NOT NULL DEFAULT 0',
        ] as $sql) {
            try { $db->execute($sql); } catch (Throwable $e) { /* колонка уже есть */ }
        }
    }

    /* ==========================================================================
     *  ОДНА СТРОКА ВЫГРУЗКИ
     * ========================================================================== */

    /**
     * @param array<string,string> $row строка выгрузки (ключи — заголовки колонок)
     * @return string created|updated|skipped
     */
    public function importRow(array $row): string
    {
        $status = strtolower(trim((string) ($row['Status'] ?? 'ready')));
        if ($status !== '' && $status !== 'ready') {
            return $this->skip('статус «' . $status . '»', (string) ($row['Article display'] ?? ''), $row);
        }

        $skuDisplay = trim((string) ($row['Article display'] ?? ''));
        $articleRaw = trim((string) ($row['Article'] ?? ''));
        if ($skuDisplay === '' && $articleRaw === '') {
            return $this->skip('нет артикула', '', $row);
        }
        if ($skuDisplay === '') {
            $skuDisplay = $articleRaw;
        }
        $skuNorm = \sku_norm($skuDisplay);

        $crumbs = $this->parseBreadcrumb((string) ($row['Breadcrumb path RU'] ?? ''));
        $name = $this->productName($crumbs['name'], $skuDisplay);
        if ($name === '') {
            return $this->skip('пустая крошка/название', $skuDisplay, $row);
        }

        $tech   = $this->parseTech((string) ($row['Technical description RU'] ?? ''));
        $compat = $this->parseCompat((string) ($row['Compatible models RU'] ?? ''));
        $srcDesc = $this->stripArtifacts((string) ($row['Description RU'] ?? ''));

        $bodyHtml = match ($this->descMode) {
            self::DESC_CLEAN => $this->donorHtml($srcDesc),
            self::DESC_NONE  => null,
            default          => $this->ownHtml($name, $skuDisplay, $tech, $compat, $crumbs['segments']),
        };

        $categoryId = $this->categoryIdForPath($crumbs['segments']);
        $seo = $this->buildSeo($name, $skuDisplay, $tech, $crumbs['segments']);
        $slug = $this->productSlug($skuDisplay, $skuNorm);

        if ($this->dryRun) {
            $this->stats['created']++;
            // Проверочный прогон бессмыслен, если не видно, ЧТО разобралось: показываем
            // первые несколько товаров целиком — на них сразу заметен съехавший столбец.
            if (count($this->preview) < 3) {
                $imgs = array_filter(array_map('trim', explode(',', (string) ($row['Image files'] ?? ''))));
                $this->preview[] = [
                    'Артикул'        => $skuDisplay,
                    'Название'       => $name,
                    'Разделы'        => implode(' → ', $this->catTrace ?: $crumbs['segments']),
                    'Характеристики' => count($tech) . ($tech ? ': ' . $tech[0]['name'] . ' = ' . $tech[0]['value'] : ''),
                    'Совместимость'  => count($compat) . ' групп',
                    'Картинок'       => count($imgs),
                    'SEO title'      => $seo['title'],
                    'URL'            => '/catalog/' . $slug . '/',
                ];
            }
            return 'created';
        }

        $db = App::db();
        $existing = $db->fetch('SELECT id FROM products WHERE slug = :s LIMIT 1', ['s' => $slug]);

        $fields = [
            'category_id'     => $categoryId,
            'brand_id'        => $this->brandId(),
            'sku'             => mb_substr($skuDisplay, 0, 120),
            'sku_norm'        => mb_substr($skuNorm, 0, 120),
            'name'            => mb_substr($name, 0, 255),
            'body_html'       => $bodyHtml,
            'source_desc'     => $srcDesc !== '' ? $srcDesc : null,
            'source_url'      => mb_substr(trim((string) ($row['Product URL'] ?? '')), 0, 500) ?: null,
            'seo_h1'          => mb_substr($seo['h1'], 0, 255),
            'seo_title'       => mb_substr($seo['title'], 0, 255),
            'seo_description' => mb_substr($seo['description'], 0, 500),
            'seo_keywords'    => mb_substr($seo['keywords'], 0, 500),
            'is_active'       => 1,
        ];

        if ($existing) {
            $productId = (int) $existing['id'];
            $set = implode(', ', array_map(static fn($k) => "$k = :$k", array_keys($fields)));
            $db->execute("UPDATE products SET $set WHERE id = :id", $fields + ['id' => $productId]);
            $result = 'updated';
            $this->stats['updated']++;
        } else {
            $fields['slug'] = $slug;
            $cols = implode(', ', array_keys($fields));
            $vals = implode(', ', array_map(static fn($k) => ":$k", array_keys($fields)));
            $productId = $db->insert("INSERT INTO products ($cols) VALUES ($vals)", $fields);
            $result = 'created';
            $this->stats['created']++;
        }

        $this->syncAttributes($productId, $tech);
        $this->syncCompat($productId, $compat);
        $this->syncImages($productId, $skuNorm, $name, $row);

        return $result;
    }

    /* ==========================================================================
     *  ПАРСИНГ ПОЛЕЙ ВЫГРУЗКИ
     * ========================================================================== */

    /** Хлебные крошки → сегменты категорий + имя товара (последний сегмент). */
    private function parseBreadcrumb(string $raw): array
    {
        /**
         * 🔥 Режем по « / » с пробелами, а НЕ по голому «/».
         * В названиях полно дробных размеров: «8T-8902: Соединитель 5/16 дюйма».
         * Разбиение по одному слешу давало имя «16 дюйма», а огрызок
         * «8T-8902: Соединитель 5» уезжал в дерево как отдельная категория.
         * Разделитель в выгрузке всегда с пробелами по краям — дробь их не имеет.
         */
        $parts = preg_split('/\s+\/\s+/u', trim($raw)) ?: [];
        if (count($parts) < 2 && str_contains($raw, '/')) {
            $parts = explode('/', $raw); // выгрузка без пробелов у разделителя — запасной путь
        }
        $parts = array_values(array_filter(array_map('trim', $parts), static fn($s) => $s !== ''));
        if (!$parts) {
            return ['segments' => [], 'name' => ''];
        }
        // Первый сегмент — «Главная страница», последний — сам товар.
        $first = mb_strtolower($parts[0]);
        if (str_contains($first, 'главная')) {
            array_shift($parts);
        }
        $name = (string) array_pop($parts);

        return ['segments' => $parts, 'name' => $name];
    }

    /** «151-2115: Плоская шайба…» → «Плоская шайба…». */
    private function productName(string $rawName, string $sku): string
    {
        $name = trim($rawName);
        // Убираем префикс артикула в любом виде: «151-2115:», «1512115 -», «151-2115 —».
        $skuPat = preg_quote($sku, '/');
        $name = (string) preg_replace('/^\s*' . $skuPat . '\s*[:—–-]\s*/u', '', $name);
        $name = (string) preg_replace('/^\s*[\dA-Za-z-]{4,}\s*:\s*/u', '', $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        if ($name === '') {
            return '';
        }
        return mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
    }

    /**
     * «Технические характеристики»: строки вида «Наружный диаметр (мм): 22,0».
     * @return array<int,array{name:string,unit:?string,value:string,num:?float}>
     */
    private function parseTech(string $raw): array
    {
        $raw = $this->stripArtifacts($raw);
        if ($raw === '') {
            return [];
        }
        // Значения могут идти как построчно, так и слитно в одну строку —
        // тогда режем перед каждым «Название (ед.):» / «Название:».
        $lines = preg_split('/\r\n|\r|\n/u', $raw) ?: [];
        if (count($lines) <= 1) {
            $lines = preg_split('/(?<=\S)\s+(?=[А-ЯЁA-Z][^:]{2,60}?(?:\s*\([^)]{1,20}\))?\s*:)/u', $raw) ?: [$raw];
        }

        $out = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            if (!preg_match('/^(.+?)\s*(?:\(([^)]{1,24})\))?\s*:\s*(.+)$/u', $line, $m)) {
                continue;
            }
            $name  = $this->translateAttr(trim($m[1]));
            $unit  = isset($m[2]) && $m[2] !== '' ? $this->translateUnit(trim($m[2])) : null;
            $value = trim($m[3]);
            if ($name === '' || $value === '' || mb_strlen($name) > 120) {
                continue;
            }
            $numRaw = str_replace([' ', ','], ['', '.'], $value);
            $num = is_numeric($numRaw) ? (float) $numRaw : null;

            $out[] = ['name' => $name, 'unit' => $unit, 'value' => $value, 'num' => $num];
        }
        return $out;
    }

    /**
     * Часть характеристик в выгрузке осталась на английском («Bolt Length: 55,0»), хотя
     * колонка помечена как RU. На витрине это выглядит как недоделка, поэтому переводим
     * известные названия. Незнакомое оставляем как есть — выдуманный перевод хуже английского.
     */
    private function translateAttr(string $name): string
    {
        static $map = [
            'bolt length' => 'Длина болта',
            'bolt diameter' => 'Диаметр болта',
            'bolt size' => 'Размер болта',
            'thread size' => 'Размер резьбы',
            'thread pitch' => 'Шаг резьбы',
            'thread type' => 'Тип резьбы',
            'thread length' => 'Длина резьбы',
            'overall length' => 'Общая длина',
            'length' => 'Длина',
            'width' => 'Ширина',
            'height' => 'Высота',
            'thickness' => 'Толщина',
            'depth' => 'Глубина',
            'weight' => 'Масса',
            'material' => 'Материал',
            'outside diameter' => 'Наружный диаметр',
            'outer diameter' => 'Наружный диаметр',
            'inside diameter' => 'Внутренний диаметр',
            'inner diameter' => 'Внутренний диаметр',
            'bore diameter' => 'Диаметр отверстия',
            'hole diameter' => 'Диаметр отверстия',
            'shaft diameter' => 'Диаметр вала',
            'diameter' => 'Диаметр',
            'quantity' => 'Количество',
            'color' => 'Цвет',
            'colour' => 'Цвет',
            'finish' => 'Покрытие',
            'coating' => 'Покрытие',
            'hardness' => 'Твёрдость',
            'grade' => 'Класс прочности',
            'head type' => 'Тип головки',
            'drive type' => 'Тип привода',
            'seal type' => 'Тип уплотнения',
            'temperature range' => 'Диапазон температур',
            'pressure' => 'Давление',
            'voltage' => 'Напряжение',
            'current' => 'Ток',
            'power' => 'Мощность',
            'capacity' => 'Объём',
            'volume' => 'Объём',
            'angle' => 'Угол',
            'pitch' => 'Шаг',
            'number of teeth' => 'Число зубьев',
            'number of holes' => 'Число отверстий',
            'series' => 'Серия',
            'style' => 'Исполнение',
            'type' => 'Тип',
            'size' => 'Размер',

            // Крепёж
            'thread size (dia x pitch)' => 'Размер резьбы (диаметр × шаг)',
            'grip length' => 'Зажимаемая длина',
            'approximate overall length' => 'Приблизительная общая длина',
            'shank diameter' => 'Диаметр стержня',
            'head diameter' => 'Диаметр головки',
            'head height' => 'Высота головки',
            'head style' => 'Тип головки',
            'drive' => 'Тип привода',
            'drive size' => 'Размер привода',
            'wire or pin diameter' => 'Диаметр проволоки или пальца',
            'hex size' => 'Размер под ключ',
            'wrench size' => 'Размер под ключ',
            'socket size' => 'Размер головки ключа',
            'stud length' => 'Длина шпильки',
            'pin diameter' => 'Диаметр пальца',
            'pin length' => 'Длина пальца',
            'pin outside diameter' => 'Наружный диаметр пальца',
            'pin inside diameter' => 'Внутренний диаметр пальца',
            'hole spacing' => 'Расстояние между отверстиями',
            'bottom strap length' => 'Длина нижнего хомута',
            'top strap length' => 'Длина верхнего хомута',
            'strap length' => 'Длина хомута',
            'strap width' => 'Ширина хомута',
            'seal lip diameter' => 'Диаметр кромки уплотнения',
            'front width' => 'Ширина спереди',
            'rear width' => 'Ширина сзади',
            'front height' => 'Высота спереди',
            'rear height' => 'Высота сзади',
            'front diameter' => 'Диаметр спереди',
            'rear diameter' => 'Диаметр сзади',
            'nominal size' => 'Номинальный размер',
            'thread direction' => 'Направление резьбы',
            'thread class' => 'Класс резьбы',
            'tensile strength' => 'Предел прочности',
            'yield strength' => 'Предел текучести',
            'plating' => 'Покрытие',

            // Втулки, фланцы, уплотнения
            'sleeve outside diameter' => 'Наружный диаметр втулки',
            'sleeve inside diameter' => 'Внутренний диаметр втулки',
            'sleeve length' => 'Длина втулки',
            'flange thickness' => 'Толщина фланца',
            'flange diameter' => 'Диаметр фланца',
            'bolt circle diameter' => 'Диаметр расположения отверстий',
            'number of bolt holes' => 'Число крепёжных отверстий',
            'effective length' => 'Рабочая длина',
            'end length' => 'Длина наконечника',
            'end outside diameter' => 'Наружный диаметр наконечника',
            'end inside diameter' => 'Внутренний диаметр наконечника',
            'height without seal' => 'Высота без уплотнения',
            'height with seal' => 'Высота с уплотнением',
            'bolt hole diameter' => 'Диаметр крепёжного отверстия',
            'shoe bolt hole spacing a' => 'Расстояние между отверстиями башмака A',
            'shoe bolt hole spacing b' => 'Расстояние между отверстиями башмака B',
            'shoe bolt hole spacing c' => 'Расстояние между отверстиями башмака C',
            'shoe width' => 'Ширина башмака',
            'track pitch' => 'Шаг гусеничной цепи',
            'track width' => 'Ширина гусеницы',
            'recommended hose size' => 'Рекомендуемый размер шланга',
            'pressure capacity' => 'Допустимое давление',
            'hole depth' => 'Глубина отверстия',
            'counterbore depth' => 'Глубина зенковки',
            'counterbore diameter' => 'Диаметр зенковки',
            'bushing radius' => 'Радиус втулки',
            'bushing inside diameter' => 'Внутренний диаметр втулки',
            'bushing outside diameter' => 'Наружный диаметр втулки',
            'tooth width' => 'Ширина зуба',
            'tooth height' => 'Высота зуба',
            'back lash' => 'Боковой зазор',
            'backlash' => 'Боковой зазор',
            'cable length' => 'Длина троса',
            'cable diameter' => 'Диаметр троса',
            'fits bolt sizes' => 'Подходит для болтов',
            'inner radius' => 'Внутренний радиус',
            'outer radius' => 'Наружный радиус',
            'tip radius' => 'Радиус вершины',
            'bolt circle radius' => 'Радиус расположения отверстий',
            'pitch circle radius' => 'Радиус делительной окружности',
            'pitch circle diameter' => 'Диаметр делительной окружности',
            'packing offset radius' => 'Радиус смещения набивки',
            'groove width' => 'Ширина канавки',
            'groove depth' => 'Глубина канавки',
            'seal material' => 'Материал уплотнения',
            'cross section diameter' => 'Диаметр сечения',
            'wall thickness' => 'Толщина стенки',

            // Шланги, трубки, гидравлика
            'drop length' => 'Длина свеса',
            'hose length' => 'Длина шланга',
            'hose inside diameter' => 'Внутренний диаметр шланга',
            'hose outside diameter' => 'Наружный диаметр шланга',
            'fitting size' => 'Размер фитинга',
            'port size' => 'Размер порта',
            'working pressure' => 'Рабочее давление',
            'burst pressure' => 'Давление разрыва',
            'flow rate' => 'Расход',
            'bore' => 'Диаметр цилиндра',
            'stroke' => 'Ход',
            'elbow angle' => 'Угол отвода',
            'filtration rating' => 'Тонкость фильтрации',
            'micron rating' => 'Тонкость фильтрации, мкм',
            'oil capacity' => 'Объём масла',

            // Пружины, приводы, электрика
            'free length' => 'Длина в свободном состоянии',
            'wire diameter' => 'Диаметр проволоки',
            'spring rate' => 'Жёсткость пружины',
            'belt length' => 'Длина ремня',
            'belt width' => 'Ширина ремня',
            'rib count' => 'Число рёбер',
            'tooth count' => 'Число зубьев',
            'shaft length' => 'Длина вала',
            'keyway width' => 'Ширина шпоночного паза',
            'amperage' => 'Сила тока',
            'wattage' => 'Мощность',
            'frequency' => 'Частота',
            'connector type' => 'Тип разъёма',
            'terminal type' => 'Тип клеммы',
            'mounting type' => 'Тип крепления',
            'housing material' => 'Материал корпуса',
            'overall width' => 'Общая ширина',
            'overall height' => 'Общая высота',
            'max temperature' => 'Максимальная температура',
            'min temperature' => 'Минимальная температура',
        ];
        $key = mb_strtolower(trim($name));
        if (isset($map[$key])) {
            return $map[$key];
        }
        // Переводы, дописанные в import_terms руками, — приоритетнее «не нашли».
        $this->loadTerms();
        if (isset($this->termMap[$key])) {
            return $this->termMap[$key];
        }
        // «Sleeve Length (approx.)» — уточнение в скобках не должно мешать переводу основы.
        if (preg_match('/^(.+?)\s*\(([^)]+)\)$/u', trim($name), $m)) {
            $base = mb_strtolower(trim($m[1]));
            if (isset($map[$base])) {
                return $map[$base] . ' (' . $m[2] . ')';
            }
        }
        // Не перевели, а название латиницей — копим, чтобы дополнить словарь по итогам прогона.
        // Однобуквенные («D», «L») пропускаем: это обозначения с чертежа, переводить нечего.
        if (mb_strlen($name) > 2
            && preg_match('/[A-Za-z]/', $name)
            && !preg_match('/[А-Яа-яЁё]/u', $name)
        ) {
            $this->untranslated[$name] = ($this->untranslated[$name] ?? 0) + 1;
        }
        return $name;
    }

    /** Пропуск строки с фиксацией причины: «пропущено: 3794» без разбивки — не диагностика. */
    private function skip(string $reason, string $sku, array $row = []): string
    {
        $this->stats['skipped']++;
        $this->skipReasons[$reason] = ($this->skipReasons[$reason] ?? 0) + 1;
        if (count($this->skipSamples) < 5) {
            $this->skipSamples[] = ($sku !== '' ? $sku : '(без артикула)') . ' — ' . $reason;
        }
        // Полный список нужен, чтобы глазами проверить: там правда пусто, или мы теряем товар.
        if ($this->skipLog) {
            $this->skipRows[] = [
                $sku,
                $reason,
                trim((string) ($row['Product URL'] ?? '')),
                trim((string) ($row['Breadcrumb path RU'] ?? '')),
            ];
        }
        return 'skipped';
    }

    /** @return array<int,array{0:string,1:string,2:string,3:string}> */
    public function skipRows(): array { return $this->skipRows; }

    /** Разбивка пропусков по причинам, по убыванию. */
    public function skipReasons(): array
    {
        arsort($this->skipReasons);
        return $this->skipReasons;
    }

    /** @return string[] */
    public function skipSamples(): array { return $this->skipSamples; }

    /** Словарь из БД — один SELECT на прогон, дальше из памяти. */
    private function loadTerms(): void
    {
        if ($this->termMap !== []) {
            return;
        }
        $this->termMap = ['#loaded' => ''];
        try {
            foreach (App::db()->fetchAll('SELECT term, term_ru FROM import_terms WHERE term_ru IS NOT NULL') as $r) {
                $this->termMap[mb_strtolower((string) $r['term'])] = (string) $r['term_ru'];
            }
        } catch (Throwable $e) {
            // Таблицы ещё нет (первый запуск до ensureSchema) — работаем на статичном словаре.
        }
    }

    /**
     * Сброс накопленных непереведённых терминов в import_terms.
     * Вызывается один раз в конце прогона: 22 000 строк дали бы столько же UPDATE,
     * а так — по одному запросу на уникальный термин.
     */
    public function flushTerms(): void
    {
        if ($this->dryRun || $this->untranslated === []) {
            return;
        }
        $db = App::db();
        foreach ($this->untranslated as $term => $hits) {
            try {
                $db->execute(
                    'INSERT INTO import_terms (term, hits) VALUES (:t, :h)
                     ON DUPLICATE KEY UPDATE hits = hits + :h2',
                    ['t' => mb_substr((string) $term, 0, 190), 'h' => $hits, 'h2' => $hits]
                );
            } catch (Throwable $e) {
                $this->note('словарь: не записан термин ' . $term);
            }
        }
    }

    /** Единицы измерения из английской части выгрузки. */
    private function translateUnit(string $unit): string
    {
        static $map = [
            'mm' => 'мм', 'cm' => 'см', 'm' => 'м', 'in' => 'дюйм', 'inch' => 'дюйм',
            'kg' => 'кг', 'g' => 'г', 'lb' => 'фунт', 'lbs' => 'фунт',
            'l' => 'л', 'ml' => 'мл', 'v' => 'В', 'a' => 'А', 'w' => 'Вт',
            'bar' => 'бар', 'psi' => 'psi', 'pcs' => 'шт',
        ];
        $key = mb_strtolower(trim($unit));
        return $map[$key] ?? $unit;
    }

    /**
     * «Совместимые модели»: «СУДОВОЙ ДВИГАТЕЛЬ3612 C280-16ГАЗОВЫЙ ДВИГАТЕЛЬG3612 …».
     * Заголовок типа техники — прописные кириллицей, модели — латиница/цифры.
     * @return array<int,array{type:string,models:string}>
     */
    private function parseCompat(string $raw): array
    {
        $raw = $this->stripArtifacts($raw);
        if ($raw === '') {
            return [];
        }
        // Делим по заголовкам из прописных кириллических букв (2+ символа).
        $parts = preg_split(
            '/([А-ЯЁ][А-ЯЁ0-9\s\-\/\.]*[А-ЯЁ])/u',
            $raw,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        ) ?: [];

        $out = [];
        $currentType = null;
        foreach ($parts as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk === '') {
                continue;
            }
            $isHeader = (bool) preg_match('/^[А-ЯЁ][А-ЯЁ0-9\s\-\/\.]*[А-ЯЁ]$/u', $chunk);
            if ($isHeader) {
                $currentType = preg_replace('/\s+/u', ' ', $chunk);
                continue;
            }
            if ($currentType === null) {
                continue;
            }
            $models = trim(preg_replace('/\s+/u', ' ', $chunk) ?? '');
            if ($models === '') {
                continue;
            }
            $out[] = ['type' => mb_substr($currentType, 0, 190), 'models' => $models];
            $currentType = null;
        }
        return $out;
    }

    /** Мусор донора: «Развернуть», предупреждение Prop 65, двойные пробелы. */
    private function stripArtifacts(string $s): string
    {
        $s = str_replace(["\xC2\xA0", '&nbsp;'], ' ', $s);
        // Предупреждение Prop 65 — законодательство штата Калифорния, к РФ отношения не имеет.
        $s = (string) preg_replace('/Предупреждение\s*:.*$/su', '', $s);
        // Без \b: в PCRE границы слова считаются по ASCII, и в «2390Развернуть» хвост не ловится.
        $s = str_replace(['Развернуть', 'Свернуть'], ' ', $s);
        $s = (string) preg_replace('/[ \t]{2,}/u', ' ', $s);
        return trim($s);
    }

    /* ==========================================================================
     *  ТЕКСТ ВИТРИНЫ
     * ========================================================================== */

    /** Собственное описание из фактов — без копирования текстов донора. */
    private function ownHtml(string $name, string $sku, array $tech, array $compat, array $segments): string
    {
        $category = $segments ? (string) end($segments) : '';
        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $p1 = sprintf(
            '%s — запчасть для техники Caterpillar, артикул <b>%s</b>%s. '
            . 'Поставляем со склада и под заказ, подбираем по номеру детали и по модели машины.',
            $h($name),
            $h($sku),
            $category !== '' ? ', раздел «' . $h($category) . '»' : ''
        );

        /**
         * Ни списка характеристик, ни перечня техники здесь НЕТ — намеренно.
         * И то и другое лежит в БД структурно (product_attr_values, product_compat)
         * и выводится на карточке отдельными блоками. Дублировать их в тексте значит
         * показать человеку одно и то же дважды, а поисковику — самоповтор на 22 000
         * страницах. Текст отвечает на «что это и как купить», таблицы — на «какое оно».
         */
        $key = '';
        if ($tech) {
            // Одна ключевая характеристика в тексте уместна — она делает абзац конкретным.
            $t = $tech[0];
            $key = sprintf(
                ' Основной размер — %s: %s%s.',
                $h(mb_strtolower($t['name'])),
                $h($t['value']),
                $t['unit'] ? ' ' . $h($t['unit']) : ''
            );
        }

        $p2 = '<p>Проверьте применимость по вашей машине: назовите модель и серийный номер — '
            . 'подтвердим, что деталь подходит, и рассчитаем срок поставки.'
            . ($compat ? ' Перечень совместимой техники — в соседней вкладке.' : '')
            . '</p>';

        return '<p>' . $p1 . $key . '</p>' . $p2;
    }

    /** Текст донора, очищенный: списки из «•», подзаголовки из «Характеристики.»/«Применение.». */
    private function donorHtml(string $src): ?string
    {
        if ($src === '') {
            return null;
        }
        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $src = (string) preg_replace('/(Характеристики|Применение|Преимущества)\s*\./u', "\n@@$1@@\n", $src);

        $html = '';
        foreach (preg_split('/\n+/u', $src) ?: [] as $block) {
            $block = trim((string) $block);
            if ($block === '') {
                continue;
            }
            if (preg_match('/^@@(.+)@@$/u', $block, $m)) {
                $html .= '<h3>' . $h(trim($m[1])) . '</h3>';
                continue;
            }
            if (str_contains($block, '•')) {
                $items = array_filter(array_map('trim', explode('•', $block)), static fn($s) => $s !== '');
                $lead = array_shift($items);
                if ($lead !== null && $lead !== '' && !str_starts_with($lead, '•')) {
                    $html .= '<p>' . $h($lead) . '</p>';
                }
                if ($items) {
                    $html .= '<ul>' . implode('', array_map(static fn($i) => '<li>' . $h($i) . '</li>', $items)) . '</ul>';
                }
                continue;
            }
            $html .= '<p>' . $h($block) . '</p>';
        }
        return $html !== '' ? $html : null;
    }

    /* ==========================================================================
     *  SEO
     * ========================================================================== */

    private function buildSeo(string $name, string $sku, array $tech, array $segments): array
    {
        $category = $segments ? (string) end($segments) : '';
        // Регистр названия НЕ трогаем: mb_strtolower ломает обозначения — «болт M10»
        // превращается в «болт m10», HGH20 в hgh20. Поиску регистр безразличен, а
        // человеку в сниппете — нет.
        $nameLc = $name;

        $h1 = $sku . ' — ' . $name;
        // Название первым и с заглавной: «151-2115 плоская шайба…» в выдаче читается как
        // обрывок строки, а не как товар. Артикул при этом остаётся в title — по нему ищут.
        $title = sprintf('%s %s — купить запчасть для техники Caterpillar', $name, $sku);
        if (mb_strlen($title) > 120) {
            $title = sprintf('%s %s — запчасть Caterpillar', mb_substr($name, 0, 80), $sku);
        }

        // Описание: артикул + название + 2 ключевых характеристики + коммерческая часть.
        $specBits = [];
        foreach (array_slice($tech, 0, 2) as $t) {
            $specBits[] = mb_strtolower($t['name']) . ' ' . $t['value'] . ($t['unit'] ? ' ' . $t['unit'] : '');
        }
        // Целимся в 150–180 символов: длиннее Яндекс и Google обрежут в сниппете.
        $desc = sprintf('%s — %s%s.', $sku, $nameLc, $specBits ? ' (' . implode(', ', $specBits) . ')' : '');
        $tail = ' Проверим применимость по модели техники, подберём по артикулу. Наличие и сроки — по запросу.';
        if (mb_strlen($desc) > 175) {
            $desc = sprintf('%s — %s.', $sku, $nameLc);
        }
        if (mb_strlen($desc . $tail) <= 185) {
            $desc .= $tail;
        } else {
            $desc .= ' Подбор по артикулу, наличие и сроки — по запросу.';
        }

        $kw = array_filter([
            $sku,
            \sku_norm($sku),
            $nameLc,
            $sku . ' купить',
            $sku . ' caterpillar',
            $category !== '' ? mb_strtolower($category) : null,
            'запчасти caterpillar',
        ]);

        return [
            'h1'          => $h1,
            'title'       => $title,
            'description' => $desc,
            'keywords'    => implode(', ', $kw),
        ];
    }

    private function productSlug(string $sku, string $skuNorm): string
    {
        $slug = \cp_slug($sku);
        if ($slug === '') {
            $slug = mb_strtolower($skuNorm);
        }
        return mb_substr($slug, 0, 191);
    }

    /* ==========================================================================
     *  КАТЕГОРИИ
     * ========================================================================== */

    /**
     * Дерево из сегментов крошек; возвращает id листа.
     *
     * 🔥 Сопоставляем по ИМЕНИ внутри родителя, а не по слагу. Слаги в seed_categories.sql
     * написаны руками и короче названий ('krepezh-uplotniteli-rashodnye' против
     * «Крепёжные детали, уплотнители и расходные материалы»), поэтому поиск по слагу
     * не нашёл бы наш раздел и создал бы рядом дубль. Имена же совпадают — они из того
     * же источника, что и крошки; расходятся только в ё/е и пунктуации, это снимает
     * нормализация в normName().
     *
     * Товары падают в СУЩЕСТВУЮЩИЕ разделы /catalog/. Создаём только то, чего у нас нет:
     * третий уровень крошек (у нас посеяны два) — иначе товар пришлось бы вешать на
     * подраздел и терять группировку.
     */
    private function categoryIdForPath(array $segments): ?int
    {
        if (!$segments) {
            return null;
        }
        $this->loadCategories();

        $parentId = 0;      // 0 = корень (в БД parent_id NULL)
        $id = null;
        $this->catTrace = [];

        foreach ($segments as $segment) {
            $segment = trim((string) $segment);
            if ($segment === '') {
                continue;
            }
            $key = $parentId . '|' . $this->normName($segment);

            if (isset($this->catIndex[$key])) {
                $id = $this->catIndex[$key];
                $this->catTrace[] = $segment . ' [есть, #' . $id . ']';
            } else {
                $new = $this->createCategory($segment, $parentId);
                $this->catTrace[] = $segment . ($new === null ? ' [будет создан]' : ' [создан #' . $new . ']');
                if ($new === null) {
                    break; // dry-run или сбой: глубже не идём, товар ляжет в найденного родителя
                }
                $this->catIndex[$key] = $new;
                $id = $new;
            }
            $parentId = $id;
        }

        return $id ?: null;
    }

    /**
     * Индекс существующих категорий: «родитель + нормализованное имя» → id.
     * Таблица на пару сотен строк, поэтому читаем её один раз целиком — это дешевле,
     * чем два SELECT на каждый из 22 000 товаров.
     */
    private function loadCategories(): void
    {
        if ($this->catIndex !== []) {
            return;
        }
        $rows = App::db()->fetchAll('SELECT id, parent_id, path, depth, slug, name FROM categories');
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $this->catIndex[((int) $r['parent_id']) . '|' . $this->normName((string) $r['name'])] = $id;
            $this->catMeta[$id] = [
                'path'  => (string) $r['path'],
                'depth' => (int) $r['depth'],
            ];
            $this->catSlugs[(string) $r['slug']] = true;
        }
        // Пустой каталог — индекс так и остался бы пустым и перечитывался на каждой строке.
        $this->catIndex['#loaded'] = 0;
    }

    /** Создание недостающего раздела с корректным материализованным путём '/6/123/'. */
    private function createCategory(string $name, int $parentId): ?int
    {
        if ($this->dryRun) {
            return null;
        }
        $db = App::db();

        $parentPath  = $parentId > 0 ? (string) ($this->catMeta[$parentId]['path'] ?? '/') : '/';
        $parentDepth = $parentId > 0 ? (int) ($this->catMeta[$parentId]['depth'] ?? -1) : -1;

        // uq_categories_slug уникален глобально, а названия в разных ветках повторяются
        // («Шланги и трубки» есть и в корне, и в крепеже) — разводим суффиксом.
        $base = \cp_slug($name) ?: 'razdel';
        $slug = mb_substr($base, 0, 170);
        for ($i = 2; isset($this->catSlugs[$slug]); $i++) {
            $slug = mb_substr($base, 0, 168) . '-' . $i;
        }

        $id = (int) $db->insert(
            'INSERT INTO categories (parent_id, path, depth, slug, name, is_active, sort, seo_title, seo_description)
             VALUES (:p, "", :d, :slug, :name, 1, 500, :st, :sd)',
            [
                'p'    => $parentId > 0 ? $parentId : null,
                'd'    => $parentDepth + 1,
                'slug' => $slug,
                'name' => mb_substr($name, 0, 200),
                'st'   => mb_substr($name . ' для техники Caterpillar — купить в CatPart', 0, 255),
                'sd'   => mb_substr(
                    $name . ' для техники Caterpillar: подбор по номеру детали, доставка по России.',
                    0,
                    500
                ),
            ]
        );
        if ($id <= 0) {
            $this->note("не удалось создать раздел «{$name}»");
            return null;
        }

        // path известен только после INSERT — он включает собственный id.
        $path = rtrim($parentPath, '/') . '/' . $id . '/';
        $db->execute('UPDATE categories SET path = :path WHERE id = :id', ['path' => $path, 'id' => $id]);

        $this->catMeta[$id] = ['path' => $path, 'depth' => $parentDepth + 1];
        $this->catSlugs[$slug] = true;
        $this->stats['categories']++;

        return $id;
    }

    /**
     * Нормализация названия раздела для сравнения: «Креп**ё**жные детали, уплотнители…»
     * из посева и «Креп**е**жные детали, уплотнители…» из выгрузки должны совпасть.
     */
    private function normName(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(['ё', '«', '»', '"', '”', '“'], ['е', '', '', '', '', ''], $s);
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? $s;
        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    /* ==========================================================================
     *  БРЕНД / АТРИБУТЫ / СОВМЕСТИМОСТЬ / КАРТИНКИ
     * ========================================================================== */

    private function brandId(): ?int
    {
        if ($this->brandId !== null) {
            return $this->brandId ?: null;
        }
        if ($this->brandName === '' || $this->dryRun) {
            return $this->brandId = 0 ?: null;
        }
        $db = App::db();
        $slug = \cp_slug($this->brandName);
        $row = $db->fetch('SELECT id FROM brands WHERE slug = :s LIMIT 1', ['s' => $slug]);
        $this->brandId = $row
            ? (int) $row['id']
            : $db->insert(
                'INSERT INTO brands (slug, name, is_oem, is_active, sort) VALUES (:s, :n, 1, 1, 10)',
                ['s' => $slug, 'n' => $this->brandName]
            );
        return $this->brandId;
    }

    private function attributeId(string $name, ?string $unit): int
    {
        $code = mb_substr(\cp_slug($name), 0, 60);
        if ($code === '') {
            $code = 'attr_' . substr(md5($name), 0, 8);
        }
        if (isset($this->attrCache[$code])) {
            return $this->attrCache[$code];
        }
        $db = App::db();
        $row = $db->fetch('SELECT id FROM attributes WHERE code = :c LIMIT 1', ['c' => $code]);
        $id = $row
            ? (int) $row['id']
            : $db->insert(
                'INSERT INTO attributes (code, name, unit, type, is_filterable, sort)
                 VALUES (:c, :n, :u, :t, 0, 100)',
                [
                    'c' => $code,
                    'n' => mb_substr($name, 0, 120),
                    'u' => $unit !== null ? mb_substr($unit, 0, 40) : null,
                    't' => 'str',
                ]
            );
        return $this->attrCache[$code] = $id;
    }

    private function syncAttributes(int $productId, array $tech): void
    {
        $db = App::db();
        $db->execute('DELETE FROM product_attr_values WHERE product_id = :p', ['p' => $productId]);
        foreach ($tech as $t) {
            $attrId = $this->attributeId($t['name'], $t['unit']);
            $db->execute(
                'INSERT INTO product_attr_values (product_id, attribute_id, value_str, value_num)
                 VALUES (:p, :a, :s, :n)',
                [
                    'p' => $productId,
                    'a' => $attrId,
                    's' => mb_substr($t['value'], 0, 255),
                    'n' => $t['num'],
                ]
            );
        }
    }

    private function syncCompat(int $productId, array $compat): void
    {
        $db = App::db();
        $db->execute('DELETE FROM product_compat WHERE product_id = :p', ['p' => $productId]);
        $sort = 10;
        foreach ($compat as $c) {
            $db->execute(
                'INSERT INTO product_compat (product_id, machine_type, models, sort) VALUES (:p, :t, :m, :s)',
                ['p' => $productId, 't' => $c['type'], 'm' => $c['models'], 's' => $sort]
            );
            $sort += 10;
        }
    }

    /**
     * Корень, доступный из веба. Раскладка бывает двух видов, и обе рабочие:
     *   docroot=/public  — BASE_PATH/public (как в репозитории);
     *   плоская          — index.php лежит в корне рядом с app/ (типовой шаред-хостинг).
     * Определяем по наличию index.php, как это делает сам фронт-контроллер.
     */
    private static function webRoot(): string
    {
        return is_file(\BASE_PATH . '/public/index.php')
            ? \BASE_PATH . '/public'
            : \BASE_PATH;
    }

    /**
     * Копирует картинки из локальной папки выгрузки в /upload/products/<shard>/<article>/
     * и перезаписывает product_images. Внешние URL донора не подставляем.
     */
    private function syncImages(int $productId, string $skuNorm, string $name, array $row): void
    {
        $files = array_values(array_filter(array_map('trim', explode(',', (string) ($row['Image files'] ?? '')))));
        if (!$files) {
            return;
        }
        $dirRel = trim((string) ($row['Image directory'] ?? ''), "/\\ ");
        $shard = mb_strtolower(mb_substr($skuNorm, 0, 2)) ?: 'xx';
        $destRel = '/upload/products/' . $shard . '/' . mb_strtolower($skuNorm);
        $destAbs = self::webRoot() . $destRel;

        $db = App::db();
        $db->execute('DELETE FROM product_images WHERE product_id = :p', ['p' => $productId]);

        $sort = 10;
        $first = null;
        foreach ($files as $file) {
            $file = basename($file);
            if ($file === '') {
                continue;
            }
            $url = $destRel . '/' . rawurlencode($file);

            if ($this->imagesSrc !== null) {
                $srcAbs = $this->resolveSource($dirRel, $file);
                if ($srcAbs === null) {
                    continue; // файла нет — строку не пишем, чтобы не плодить битые картинки
                }
                if (!is_dir($destAbs) && !@mkdir($destAbs, 0755, true) && !is_dir($destAbs)) {
                    $this->note("не удалось создать папку $destAbs");
                    continue;
                }
                $dstAbs = $destAbs . '/' . $file;
                if (!is_file($dstAbs) || filesize($dstAbs) !== filesize($srcAbs)) {
                    if (!@copy($srcAbs, $dstAbs)) {
                        $this->note("не скопирован $srcAbs");
                        continue;
                    }
                }
                $this->stats['images']++;
            }

            $db->execute(
                'INSERT INTO product_images (product_id, url, alt, is_primary, sort) VALUES (:p, :u, :a, :i, :s)',
                [
                    'p' => $productId,
                    'u' => mb_substr($url, 0, 500),
                    'a' => mb_substr($name, 0, 255),
                    'i' => $first === null ? 1 : 0,
                    's' => $sort,
                ]
            );
            $first ??= $url;
            $sort += 10;
        }

        if ($first !== null) {
            $db->execute('UPDATE products SET image_url = :u WHERE id = :p', ['u' => $first, 'p' => $productId]);
        }
    }

    /** Ищем файл: <src>/<dir>/<file>, затем <src>/<basename dir>/<file>, затем <src>/<file>. */
    private function resolveSource(string $dirRel, string $file): ?string
    {
        if ($this->imagesSrc === null) {
            return null;
        }
        $candidates = [];
        if ($dirRel !== '') {
            $candidates[] = $this->imagesSrc . '/' . $dirRel . '/' . $file;
            $candidates[] = $this->imagesSrc . '/' . basename($dirRel) . '/' . $file;
        }
        $candidates[] = $this->imagesSrc . '/' . $file;

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    private function note(string $msg): void
    {
        $this->stats['errors']++;
        if (count($this->errorLog) < 20) {
            $this->errorLog[] = $msg;
        }
    }

    /* ==========================================================================
     *  ФИНАЛИЗАЦИЯ
     * ========================================================================== */

    /**
     * Удаление пустых разделов, созданных импортёром.
     *
     * Зачем: пока крошка резалась по голому «/», куски названий с дробными размерами
     * («8T-8902: Соединитель 5» из «… Соединитель 5/16 дюйма») уезжали в дерево как
     * настоящие разделы. После исправления разбора товары переехали в правильные ветки,
     * а мусорные узлы остались — пустые страницы в индексе поисковика.
     *
     * Трогаем ТОЛЬКО свои: sort = 500 ставит createCategory(), у посева 10…400.
     * Условия: ноль товаров и ноль детей. Посевные разделы (в т.ч. пустые) не тронутся.
     *
     * @return int сколько удалено
     */
    public function cleanEmptyCategories(): int
    {
        $db = App::db();
        $rows = $db->fetchAll(
            'SELECT c.id
               FROM categories c
              WHERE c.sort = 500
                AND NOT EXISTS (SELECT 1 FROM products p WHERE p.category_id = c.id)
                AND NOT EXISTS (SELECT 1 FROM categories k WHERE k.parent_id = c.id)'
        );
        $n = 0;
        foreach ($rows as $r) {
            $db->execute('DELETE FROM categories WHERE id = :id', ['id' => (int) $r['id']]);
            $n++;
        }
        return $n;
    }

    /** Пересчёт products_count по дереву (LIKE 'path%' — как в витрине категории). */
    public function recountCategories(): void
    {
        $db = App::db();
        $cats = $db->fetchAll('SELECT id, path FROM categories');
        foreach ($cats as $c) {
            $prefix = ($c['path'] !== '' ? $c['path'] : '') . '%';
            $row = $db->fetch(
                'SELECT COUNT(*) AS c
                   FROM products p JOIN categories cc ON cc.id = p.category_id
                  WHERE p.is_active = 1 AND cc.path LIKE :pfx',
                ['pfx' => $prefix]
            );
            $db->execute('UPDATE categories SET products_count = :n WHERE id = :id', [
                'n'  => (int) ($row['c'] ?? 0),
                'id' => (int) $c['id'],
            ]);
        }
    }
}
