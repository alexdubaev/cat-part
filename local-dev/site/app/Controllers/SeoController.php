<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;

/**
 * Карта сайта.
 *
 * 🔥 У донора sitemap собирался ОДНОЙ строкой в памяти: 50 000 URL склеивались через
 * `$out .= …` и выплёвывались разом (SeoController.php:27-38). На наших 22 000+ товаров
 * это десятки мегабайт в переменной — плюс сам файл упирался бы в лимиты протокола
 * (50 000 URL / 50 МБ на файл).
 *
 * У нас sitemap-index: /sitemap.xml — оглавление, а сами URL разложены по файлам
 * по CHUNK штук. Строки печатаются по одной (echo в цикле), а не копятся в памяти.
 */
final class SeoController extends Controller
{
    /** URL в одном файле-чанке. С запасом ниже протокольного лимита в 50 000. */
    private const CHUNK = 5000;

    /** Оглавление: перечисляет остальные файлы карты. */
    public function sitemap(): never
    {
        $parts = ['/sitemap-pages.xml'];

        $chunks = $this->productChunks();
        for ($i = 1; $i <= $chunks; $i++) {
            $parts[] = '/sitemap-products-' . $i . '.xml';
        }

        $this->xmlHeader();
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($parts as $part) {
            echo '  <sitemap><loc>' . $this->loc($part) . '</loc></sitemap>' . "\n";
        }
        echo '</sitemapindex>';
        exit;
    }

    /**
     * /llms.txt — краткое описание сайта для ИИ-краулеров и ассистентов.
     *
     * Зачем отдельный файл, если есть sitemap: карта отвечает на «какие адреса есть»,
     * а этот — на «что здесь и как этим пользоваться». Модель, попавшая на сайт,
     * должна понять за один запрос: чем торгуем, как устроен поиск, чего тут НЕТ
     * (цен и наличия онлайн) — иначе она додумает и выдаст пользователю выдумку.
     *
     * Генерируем из БД, а не держим статикой: разделы каталога и их наполнение
     * меняются импортом, а рассинхронизированный «файл-визитка» вреднее отсутствующего.
     *
     * ⚠️ Никаких обещаний по срокам, ценам и гарантиям — их нам не называли.
     */
    public function llms(): never
    {
        $base = rtrim((string) App::config('app.base_url'), '/');
        $name = (string) App::config('site.name');

        header('Content-Type: text/plain; charset=utf-8');
        // Файл читают роботы и он меняется вместе с каталогом — сутки кеша достаточно.
        header('Cache-Control: public, max-age=86400');

        $out = "# {$name}\n\n";
        $out .= "> Независимый поставщик запчастей для техники Caterpillar. Сайт решает одну\n"
              . "> задачу: опознать деталь по номеру, кросс-номеру или узлу и подтвердить,\n"
              . "> что она подойдёт к конкретной машине.\n\n";

        $out .= "Язык: русский. Регион поставки: Россия.\n"
              . "Caterpillar и Cat — товарные знаки Caterpillar Inc. Сайт не является\n"
              . "официальным дилером; названия техники используются для указания\n"
              . "совместимости запчастей.\n\n";

        $out .= "## Чего на сайте НЕТ\n\n"
              . "- Цен: они не публикуются, на карточках стоит «по запросу».\n"
              . "- Онлайн-наличия и сроков доставки: подтверждаются по запросу.\n"
              . "- Оформления заказа онлайн: заявка — это имя и телефон, дальше связывается менеджер.\n\n";

        $out .= "## Как устроен поиск\n\n"
              . "- Поиск по артикулу и кросс-номерам: {$base}/poisk?q=АРТИКУЛ\n"
              . "- Разделители в номере не важны: «3B-8489», «3b 8489» и «3B.8489» — одно и то же.\n"
              . "- Карточка товара: {$base}/tovar/{артикул} (слаг совпадает с артикулом в нижнем регистре).\n"
              . "- Раздел каталога: {$base}/catalog/{слаг-раздела}\n\n";

        $out .= "## Разделы каталога\n\n";
        foreach (Category::roots() as $c) {
            $cnt = (int) ($c['products_count'] ?? 0);
            $out .= '- [' . $c['name'] . '](' . $base . '/catalog/' . $c['slug'] . ')'
                  . ($cnt > 0 ? ' — позиций: ' . $cnt : '') . "\n";
        }

        $out .= "\n## Страницы\n\n"
              . "- [Каталог]({$base}/catalog)\n"
              . "- [О компании]({$base}/o-kompanii)\n"
              . "- [Доставка]({$base}/dostavka)\n"
              . "- [Оплата]({$base}/oplata)\n"
              . "- [Гарантия]({$base}/garantiya)\n"
              . "- [Контакты]({$base}/kontakty)\n"
              . "- [Статьи]({$base}/stati)\n"
              . "- [Карта сайта]({$base}/sitemap.xml)\n";

        echo $out;
        exit;
    }

    /** Статические страницы + все категории + статьи. Их сотни, в один файл влезают с запасом. */
    public function sitemapPages(): never
    {
        $paths = [
            '/',
            '/catalog',
            '/stati',
            '/o-kompanii',
            '/dostavka',
            '/oplata',
            '/garantiya',
            '/kontakty',
            '/politika-konfidencialnosti',
        ];
        $paths = array_values(array_filter($paths, static fn(string $path):bool=>empty(cms_meta($path)['noindex'])));
        // /poisk сюда НЕ добавлен намеренно: страница поиска отдаёт noindex.

        foreach (Category::allActive() as $row) {
            $paths[] = '/catalog/' . (string) $row['slug'];
        }

        foreach(App::db()->fetchAll("SELECT b.slug,m.data FROM brands b LEFT JOIN cp_catalog_meta m ON m.entity='brand' AND m.entity_id=b.id WHERE b.is_active=1") as $brand) {
            $brandMeta=json_decode((string)($brand['data']??'{}'),true);if(empty($brandMeta['noindex']))$paths[]='/brand/'.(string)$brand['slug'];
        }

        // Статьи берём ТОЛЬКО опубликованные — Post::allPublished() применяет то же
        // правило видимости, что и лента со страницей статьи. Иначе в карту сайта
        // уехал бы черновик или отложенная публикация, и робот получил бы 404.
        $posts = Post::allPublished();

        $this->xmlHeader();
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($paths as $path) {
            echo '  <url><loc>' . $this->loc($path) . '</loc></url>' . "\n";
        }
        // Статьи — отдельным циклом: у них, в отличие от категорий, есть осмысленная
        // дата для <lastmod>.
        foreach ($posts as $post) {
            echo '  <url><loc>' . $this->loc('/stati/' . (string) $post['slug']) . '</loc>';
            $lastmod = (!empty($post['published_at']) ? (new \DateTimeImmutable((string)$post['published_at'],new \DateTimeZone('UTC')))->getTimestamp() : false);
            if ($lastmod !== false) {
                echo '<lastmod>' . date('Y-m-d', $lastmod) . '</lastmod>';
            }
            echo '</url>' . "\n";
        }
        echo '</urlset>';
        exit;
    }

    /** Чанк товаров: /sitemap-products-1.xml, -2.xml … */
    public function sitemapProducts(string $chunk): never
    {
        $index = (int) $chunk;
        if ($index < 1 || $index > $this->productChunks()) {
            $this->notFound();
        }

        $rows = Product::slugChunk(self::CHUNK, ($index - 1) * self::CHUNK);
        if ($rows === []) {
            $this->notFound();
        }

        $this->xmlHeader();
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($rows as $row) {
            // Печатаем построчно: 5 000 строк в буфер вывода, а не 22 000 в одну переменную.
            echo '  <url><loc>' . $this->loc('/tovar/' . (string) $row['slug']) . '</loc>';
            $lastmod = strtotime((string) ($row['updated_at'] ?? ''));
            if ($lastmod !== false) {
                echo '<lastmod>' . date('Y-m-d', $lastmod) . '</lastmod>';
            }
            echo '</url>' . "\n";
        }
        echo '</urlset>';
        exit;
    }

    /**
     * Сколько файлов-чанков нужно на товары.
     *
     * ⚠️ Ноль, пока в проекте нет карточки товара: класть в карту адреса, которые
     * отдают 404, значит своими руками скармливать роботу битые ссылки.
     * Проверка самовосстанавливающаяся — карточка живёт в CatalogController::product()
     * (Ф2), появился метод — товары поехали в карту без правки этого файла.
     *
     * COUNT(*) тут не жалко: карту дёргает робот раз в сутки, а не человек на каждом хите.
     */
    private function productChunks(): int
    {
        if (!method_exists(CatalogController::class, 'product')) {
            return 0;
        }
        return (int) ceil(Product::countIndexable() / self::CHUNK);
    }

    private function xmlHeader(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
    }

    /** Абсолютный URL с экранированием: в XML голый '&' из query — невалидный документ. */
    private function loc(string $path): string
    {
        $base = rtrim((string) App::config('app.base_url'), '/');
        return htmlspecialchars($base . $path, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
