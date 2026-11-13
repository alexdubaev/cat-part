# Исследование витрины CatPart

Дата: 2026-11-13. Read-only исследование исходников `origin/main` (`9f9ba0b`) и агрегатов **только** локальной БД `127.0.0.1:3307/local_catpart`. Production-конфигурация, пользовательские строки, пароли, письма и файлы обращений не читались. Мутаций БД не было.

## Что уже есть

- Собственный PHP MVC без Composer/framework и без OpenCart. Код витрины: `local-dev/site/app`; проектный `AGENTS.md` запрещает менять исходный snapshot `cat-part.ru` и подключаться к production БД.
- В `git log --all` нет разработки админки/OpenCart. Из локальных веток известны `main`, `design/home-polish`, `content/seo-article-20261113` и новая текущая ветка. Среди отслеживаемых файлов нет админки, миграций OpenCart или его ядра. На верхнем уровне исходной рабочей копии есть ignored `catpart-data`, `local-dev/bin,data,private`, `site/db,storage,upload`; наличие отдельной существующей установки в другом проекте этим исследованием не исключалось.
- Сервер витрины уже слушает `127.0.0.1:8080`; MariaDB — `127.0.0.1:3307`. Runtime находится в `D:/codex/cat-part/cat-part/local-dev/bin`: PHP **8.3.36**, MariaDB **10.11.11**. PHP содержит PDO MySQL, mbstring, DOM, XMLReader, ZipArchive, curl, openssl, fileinfo, **GD отсутствует** в выведенном списке модулей.
- В новом worktree нет runtime, конфигов, БД и upload: они штатно игнорируются. Нельзя запускать его `start-local.ps1` как независимый экземпляр без подготовки: фиксированные порты и имена столкнутся с готовым окружением.

## Реальный локальный набор данных

| Сущность | Количество / состояние |
|---|---|
| products | 7 134, все активны, у всех цена NULL или <= 0 |
| categories | 1 161 активных, 18 корневых |
| brands | 1 |
| blog_posts | 5, все опубликованы по текущему условию модели |
| product_images | 23 459 |
| product_compat | 122 194 |
| attributes / product_attr_values | 302 / 17 966 |
| product_cross | 0 |
| orders | 0 |

Нет товаров со ссылкой на отсутствующую категорию и изображений со ссылкой на отсутствующий товар. Счётчики в комментариях к коду (22 000 товаров и т.п.) не соответствуют текущей локальной копии; исходить из фактического инвентаря. Среди локальных схем, содержащих `cat`/`open`, найдена только `local_catpart`.

## Схема и контракты чтения

`Core/Database.php` — тонкий PDO wrapper, нативные подготовленные запросы, `pdo()`, `fetch`, `fetchAll`, `execute`, `insert`. Прямой SQL сосредоточен в трёх моделях и импортёре; адаптация этих границ позволяет сохранить контроллеры/оформление.

- `products`: `id`, nullable `category_id/brand_id`, уникальный `slug`, строковые `sku/sku_norm` VARCHAR(120), `name`, nullable decimal `price`, `currency`, boolean `in_stock`, `image_url`, `body_html`, `seo_h1/title/description/keywords`, `is_active`, даты, `source_desc/source_url`.
- `categories`: `id,parent_id`, материализованный `path` вида `/1/16/`, `depth`, `slug`, `name`, `image_url/icon`, `intro_html`, `products_count`, `sort,is_active`, SEO. Не заменять иерархию плоским списком.
- `brands`: `id,slug,name,is_oem,is_active,sort,intro_html,seo_h1/title/description`.
- `product_images`: URL, alt, is_primary, sort. `product_attr_values` ссылается на `attributes` (code, name, unit, type, sort); `product_compat` хранит группы совместимости (machine_type/models); `product_cross` — строковые cross_sku/cross_sku_norm, brand_name, kind.
- `Product.php:98` `findBySlug`: p.* плюс категория/бренд; `:113` `findActiveById`: свежий серверный snapshot; `attributes/images/compat/neighbours` используются карточкой. `catalogListing` (:304) отдаёт `products,total`; сортировка allowlist, фильтр по наличию и артикулу/названию. `forHome` (:339) — пять реальных позиций в автоматическом режиме.
- `Category.php`: roots/children/navigation/findBySlug/trail, активность; subtreePrefix из проверенных числовых сегментов. Список родителя включает товары всех потомков через префикс path. Карточка товара дополнительно соединяет категорию/бренд.
- `sku_norm` — единый helper `Support/helpers.php:217`, использовать и в админке/импорте. Выводимый артикул не нормализовать. В `Product::byArticle` поиск и по собственному номеру, и по `product_cross`; `byText` — FULLTEXT(name,sku). Точное совпадение приоритетнее кросса.

## Корзина, заказы, обращения и почта

- `CartController.php:14`: `MAX_QTY=99`; add/update молча обрезают количество. `lines()` (:130) повторно читает активные товары и молча пропускает недоступные. Session cart — ассоциативный массив `product_id => qty`; сохранение исходных product_id при переносе позволит сохранить корзины.
- Checkout (:70) проверяет CSRF, honeypot, session checkout_token, имя/телефон/email/consent, свежие товары и IP throttle. Цены браузера не использует. **Никаких INSERT заказа нет**: формирует письмо, вызывает Mailer, лишь при успехе удаляет токен и всю корзину. Ошибка почты оставляет корзину. Текущая защита повторов только в сессии; персистентной idempotency нет, истории, менеджера и снимка условий нет. Комментарий клиента пока не принимается.
- Таблицы `orders`/`order_items` в локальной схеме существуют, но не используются контроллером. `orders`: number, customer_name/phone/email/comment, total,currency,status enum new/processing/paid/shipped/done/cancelled, payment_status; это неподходящая готовая модель для требуемых шести статусов без доработки. Наличие старой пустой таблицы не означает, что заказ уже сохраняется.
- `LeadController.php` принимает `/zayavka`, имя/телефон/consent/source, проверяет CSRF/honeypot, source sanitize и свой Referer. БД не использует; письмо — единственный экземпляр. Общая форма `Views/partials/leadform.php` применяется в разных блоках; поля сообщения/email и файл пока отсутствуют. Honeypot имитирует успех без отправки.
- `Core/Mailer.php:8` — единый транспорт: `mail.notify`, `mail.delivery`, при capture создаёт файлы вне docroot, отказывает если capture path внутри docroot. Нет очереди/повтора/статуса.
- `Core/App.php:27` получает публичные факты из `Config/branding.php`, перекрывает site.email/company/phone/phones/address и **принудительно задаёт mail.notify из branding.email**. Это нужно заменить чтением единой управляемой настройки после миграции, иначе настройки админки будут перекрываться.
- `bootstrap.php` требует локальный override, строго проверяет **ровно** `127.0.0.1:3307/local_catpart`, затем принудительно включает capture. Изолированное task DB имя сейчас не разрешено: изменение этого guard — отдельная защищённая граница, нельзя просто отключать guard.
- Основной `local-dev/router.php` блокирует POST `/zayavka` с 403; сценарии форм идут через `tools/checkout-test-router.php`, где тестовые заголовки могут форсировать transport failure/throttle. Не принимать этот dev router за целевой production обработчик.

## Статьи, содержимое, настройки и SEO

- `blog_posts`: id,slug,title,excerpt,body_html,image_url,is_active,published_at,seo_title,seo_description,created_at. Нет author, revisions, archive/status, связанных записей и preview API.
- `Post.php:40` единое `VISIBLE = is_active=1 AND published_at<=NOW()` применяется к latest, findBySlug, others и allPublished. Список/деталь/sitemap уже синхронны по этому фильтру. Но `Database` не задаёт session time_zone, локально значение **SYSTEM**, PHP `Europe/Moscow`; планировщик публикации требует явной общей временной семантики.
- `BlogController.php:55` строит metadata, canonical `/stati/{slug}`, cover/OG, Article JSON-LD; `Views/blog/show.php:46` выводит body_html как доверенный HTML. Внедрение визуального редактора требует серверной очистки до сохранения, иначе создаётся stored XSS.
- Информационные страницы и главная пока содержат бизнесовые тексты непосредственно в `Views/home.php`, `Views/pages/*.php`, header/footer/leadform. Нужен инвентарь управляемых полей, а не только один WYSIWYG для статей. Контакты частично унифицированы branding, но legal/hours/messenger и другие поля читаются из cfg; графические fallback mappings — helpers.
- `branding.php`: действующий публичный Metrika ID **114229167**, allowlist `cat-part.ru,www.cat-part.ru`, четыре телефона, СМ ТЕХНО, info@cmteh.ru, адрес с офисом 704/1. Это данные для начального наполнения, не копировать из другого проекта.
- `Views/layouts/main.php:107` проверяет analytics host по **configured app.base_url**, :159–178 подключает Metrika единожды с ssr/webvisor/clickmap/ecommerce:dataLayer/referrer/url/accurateTrackBounce/trackLinks и noscript в body. Локальные визиты исключены. Сохранить опции и host restriction при переносе.
- Map iframe/validator сейчас отсутствует. GA4 и meta verification fields отсутствуют. Существующий verification HTML отслеживается в main; не удалить при сборке пакета.
- `SeoController`: `/sitemap.xml` индекс; pages включает статические страницы, активные категории, опубликованные статьи; товары по чанкам 5 000. `llms.txt` генерируется, содержит устаревшую фразу «нет оформления заказа онлайн»; не менять бизнес-смысл без необходимости, но при интеграции заказов сверить.
- Public routes полностью в `app/routes.php`: `/`, `/catalog[/slug]`, `/tovar/{slug}`, `/korzina` + POST add/update/remove/checkout, `/poisk`, `/stati[/slug]`, `/zayavka` POST, шесть информационных страниц, llms/sitemaps. Сохранить эти адреса при замене источника.

## Auth и ограничения

Есть `Session` (cookie psid, HttpOnly/SameSite=Lax, regen on login/logout, CSRF), nullable user_id и `Controller::requireAuth`, но нет login/user controller/routes, ролей, серверного ACL или admin middleware. Не выдавать это за готовую админскую auth. Граница админки должна опираться на одну настоящую OpenCart установку/её пользователей и права; публичная витрина не должна автоматически считать OC admin session покупателем.

## Что можно переиспользовать и что менять

Практический путь к одному источнику: сохранить маршруты/вьюхи CatPart, перевести чтение моделей на **тот же** каталог OpenCart с extension metadata для legacy slug, nullable price/price-mode, availability flag, old category path/compatibility/SEO/alt. Перенести исходные ID напрямую, где нет конфликта, или вести однозначный id map и миграцию session cart; не держать два изменяемых каталога с асинхронной синхронизацией. Стандартный редактор OC и редактор расширения должны менять одну запись. Подбор schema/адаптера зависит от фактической существующей версии OC; её здесь нет.

Заказ — стандартная сущность OC плюс extension snapshot/assignment/history/idempotency и transactional outbox. Публичный API сначала сохраняет заказ/обращение и outbox в одной транзакции, затем отвечает; worker/retry — отдельный путь. Никакого резервирования/stock decrement по факту заявки. Не переносить старую email-first семантику.

`Support/ProductImporter.php`/`XlsxReader.php` — полезный опыт parsing, но старый импортёр напрямую перезаписывает поля товара/атрибуты/совместимость/изображения, идентифицирует товар по slug, имеет `cleanEmptyCategories()` и не соответствует безопасному интерактивному импорту ТЗ. Не подключать его как готовый import UI без dry-run/apply distinction и новых правил частичного обновления. XLSX reader потоковый, без внешних зависимостей; границы ZIP/XML/строк всё равно требуют проверки.

## Затронутые проверки и инфраструктура

- Штатно: `python tools/check-site.py` — lint фиксированного списка + HTTP routes + assets; зашиты runtime path и 8080. Может подтвердить только существующую витрину, не интеграцию/админку.
- `python tools/test-checkout.py` — 10 локальных HTTP/session сценариев, запускает PHP на **8081**, реальная локальная БД, synthetic контакты, private mail capture. Два старых ожидания **должны измениться по ТЗ**: qty 999 -> 99 и сбой почты -> checkout failure/сохранённая корзина. Тест replay ожидает отказ второго запроса (в ТЗ важнее единственный сохранённый заказ и повторяемый подтверждённый результат). Текущие tests не проверяют DB persistence/ACL/concurrency.
- `node tools/test-catalog-sidebar.cjs` — критический каталог/поиск/родитель/пагинация/корзина, поддерживает `CATPART_TEST_URL`; `test-home-catalog.cjs` фиксирует 8080. Нужен cached `playwright` через `NODE_PATH` и установленный браузер; `capture-acceptance.cjs` ищет `%LOCALAPPDATA%/ms-playwright`.
- Изменяемая БД должна быть отдельной task schema/локальным аккаунтом; готовый MariaDB процесс и binary переиспользовать, не останавливать чужие сервисы. Read-only source clone/catalog copy доступен локально, но personal tables/captures не копировать без нужды. Реальные runtime, uploads, config и SQL не коммитить.
- Production-проверки, получение писем и целевой установленный пакет этим исследованием **не проверены**. Не выдавать локальный runtime за хостинг.

## Предлагаемое соответствие единому источнику (до реализации)

Версия OpenCart в CatPart не обнаружена, поэтому названия стандартных OC таблиц ниже обозначают **типовые сущности**, а не проверенную совместимость с установленной версией. Префикс `oc_` должен браться из выбранной установки, а не зашиваться по предположению.

| Сущность | Источник после переноса | Редактор | Потребители витрины / сохранённый контракт |
|---|---|---|---|
| Товар | один `oc_product` + `oc_product_description`; `catpart_product` с FK product_id для slug/legacy ID/sku_norm/price_mode/nullable исходной цены/availability flag/SEO extras | штатный OC catalog/product и CatPart поля этой же записи | `Product::findBySlug/findActiveById/catalogListing/byArticle/byText/forHome/slugChunk`, карточка/поиск/корзина/sitemap |
| Категория | `oc_category`, `oc_category_description`, `oc_category_path`; CatPart FK metadata для slug/icon/intro/path compatibility | штатный OC category + CatPart поля | `Category` API, дерево, родительская выборка всех потомков, breadcrumbs, sitemap |
| Основная/дополнительные связи категорий | `oc_product_to_category`; явная основная категория в metadata при необходимости | тот же редактор товара | карточка и listing без дублей товаров, legacy `category_id` — projection основной связи |
| Бренд | `oc_manufacturer`; CatPart metadata FK manufacturer_id для slug/description/SEO/logo metadata | один editor бренда | имя/логотип/SEO, legacy `brand_id` — projection |
| Характеристики | `oc_attribute`, descriptions, `oc_product_attribute`; extension metadata для unit/value_num при необходимости | OC product/attribute editor | `Product::attributes` возвращает name/unit/value_str/value_num |
| Галерея | стандартное главное image + `oc_product_image`, extension alt/caption metadata keyed by same product/image | OC image manager и product editor | `Product::images`, legacy image_url и alt/order; пути к файлам сохраняются или явно маппятся |
| OEM/кросс-номера, совместимость | CatPart `product_cross` / `product_compat`, **FK к тому же oc_product.product_id**, не отдельные товары | CatPart поля в едином product editor | единый `sku_norm`, byArticle и карточка |
| Статьи | CatPart extension blog/revision tables; исходные `blog_posts` могут стать canonical extension table без копирования параллельной ленты | один визуальный editor в OC extension | `Post` API, /stati, /stati/slug, home latest, sitemap, admin-only preview |
| Инфостраницы | OC information + CatPart route/SEO metadata или одна extension content entity | Содержимое | прежние `/o-kompanii` и другие маршруты, текущие компоненты шаблонов |
| Главная, меню, формы | одна CatPart block/navigation/form-settings модель, не PHP/JSON в пользовательской форме | Содержимое, Меню и подвал, Настройки | home/header/footer/leadform, включение/порядок/ручные подборки |
| Контакты, реквизиты, карта, аналитика | единая группа настроек OC/CatPart, seed из branding/current CatPart | Настройки сайта | cfg/brand_setting совместимый resolver, JSON-LD, формы, карта, head/body analytics |
| Заказы-запросы | стандартные `oc_order`, `oc_order_product`, `oc_order_history` + CatPart FK snapshot/assignment/idempotency/quote revisions | Заказы | checkout + один admin register/dashboard/reports, без stock decrement и платежной логики |
| Обращения | CatPart lead/history tables и outbox, FK ответственного к OC user | Обратная связь | все /zayavka формы, один реестр, dashboard/reports |
| Уведомления | CatPart transactional outbox с unique ключом события | обслуживание и retry разрешённым менеджером | mail worker/capture, статусы не равны «во Входящих» |
| Сотрудники и ACL | OC user/user_group/permissions с access/modify; серверный ACL расширения для прямых endpoints | Пользователи и права | admin sections, preview, private download; существующие customer users не импортировать как сотрудников |
| Изменения URL | CatPart slug registry + direct redirects, unique/reserved checks | при сохранении entity с явным подтверждением смены | router до ordinary route dispatch, 301 без chains/cycles, canonical/sitemap новой записи |

### Canonical таблицы против SQL views

Предпочтение для реализации после нахождения исходной админки: адаптировать SQL **трёх моделей** на canonical OC таблицы и сохранить их публичный массивный контракт. Это небольшая поверхность чтения и даёт ясную единственность редактора/хранения. Нельзя сохранить старые `products/categories` как вторую изменяемую базу и назвать это одним источником.

Compatibility SQL VIEW с legacy именем возможен только как **read-only projection** OC и FK metadata, если выяснится необходимость совместимости внешнего reader. Он не является вторым каталогом. Но VIEW не может иметь собственный FULLTEXT индекс и обычно не поддерживает запись в составной JOIN; текущий `MATCH(name,sku)` и импортёр с INSERT/UPDATE legacy tables нельзя просто оставить без изменений. Также до создания VIEW нужно безопасно переименовать/сохранить существующую таблицу и исключить её использование как живого источника; это миграция с backup, не косметическая смена имени. Поэтому views не выглядят более дешёвым путём для текущего ограниченного числа readers.

Legacy IDs: новый чистый каталог может сохранить product_id/category_id/brand_id из CatPart. Если выбранная существующая OC установка уже имеет записи, **не перезаписывать** их и не считать demo по имени: выделить explicit map `(entity, old_id)->new_id`, проверить uniqueness и конвертировать session cart при первом чтении. Сопоставление по одному slug без карты недостаточно.

### Изолированный локальный запуск

1. Сохранить готовый MariaDB процесс на loopback 3307. Не останавливать 8080 и не менять его `local_catpart`.
2. Выделить явно именованную task schema, например `catpart_admin_20261113_test`, и task DB account с правами **только** на неё. Значение пароля генерирует локальная подготовка и хранит в ignored private config, не выводит и не коммитит. Source catalog clone сделать read-only snapshot query/dump из `local_catpart`; переносить catalog/content и FK metadata, не users/личные данные/captures. До изменения source сделать инвентарь и hash/counts.
3. Исходный `bootstrap.php` должен остаться строгим. Для нового локального test режима допустим явный allowlist из `local_catpart` и конкретного task DB имени при неизменных `host===127.0.0.1`, `port===3307`; запретить wildcard, hostname, env arbitrary name и remote credentials. Или отдельный test bootstrap с теми же проверками и явным выбором task schema. Изменение guard требует регрессии rejected remote/wrong port/wrong DB; не отключать защиту ради запуска.
4. Worktree использовать PHP binary из готового runtime, отдельный порт (проверить свободный, например 8082) и отдельные session_save_path, private/captures, rate limits, logs, uploads. Для чтения существующих product pictures безопаснее явное read-only asset mapping/copy, чем write-through junction в чужой upload. Новые файлы и тестовые загрузки — только task upload.
5. Base URL в конфиге только loopback нового сервера, analytics принудительно off, mail capture принудительно on, capture вне docroot. OC config/storage/cache/session paths тоже только task directories; одинаковая DB schema с витриной, отдельный префикс/канонические сущности.
6. Провести integration checks на task schema: migration twice preserves counts/IDs/relationships; admin update -> storefront read; duplicate/concurrent checkout creates one order + one outbox; mail failure preserves DB record; editor/manager direct forbidden endpoints; article draft/preview/scheduling/sitemap; safe map/analytics clear, URL redirect. Короткий browser test критических сценариев. Локальные helper checks должны принимать явные base/runtime arguments и не обращаться к 8080 по привычке.

Подготовка такой среды — предложение; в ходе исследования ни schema, ни accounts, ни guards не изменялись.
