# Реестр зависимостей и происхождения

| Установлено | Источник/версия/лицензия | Зависимости и подтверждение |
|---|---|---|
| OpenCart core | Официальный ZIP 4.1.0.4; SHA256 в `opencart/packaging/open-cart.lock.json`; GPL-3.0 | PHP8.3.36/MariaDB10.11.11, mysqli/PDO/mbstring/DOM/libxml/zip/fileinfo/GD/iconv. Core untouched; native session/ACL/schema проверяются реальным stand checkset. Полный vendor tree и notices сохранены в ZIP. |
| CatPart extension 1.0.0 | Собственные controllers/services/admin assets; source ref/tree/file hashes в package manifest | Один канонический legacy catalog, native request tables + cp metadata/outbox. Native editor/checkout каталога не включены. |
| DOM sanitizer | Адаптация `CMTEH extension/cmteh/system/CatalogInput.php`, commit `8a5ada0da2428f274a54ac2b8d430d8b0ddc08f2` → `Catpart/CatalogSafety.php` | Только text/url/HTML allowlist и CatPart media paths; DOM/libxml/mbstring. Никакой Inventory/CMTEH config/DB. HTML/URL/file tests в catalog/content checkset. |
| Русские словари | `CMTEH admin/language/ru-ru`, тот же commit, GPL-3.0-or-later notices сохранены | Проверены ключи OpenCart4.1.0.4. Это ограниченный набор UI, не весь language pack. Исходный README сохранён как notice; его команды CMTEH не являются командами CatPart. |
| CKEditor | Штатный OpenCart CKEditor4.22.1 revision4a1fb11f44; GPL2+/LGPL2.1+/MPL1.1 | Native jQuery adapter/скрипты из pinned core. Реальный browser draft→publish→future→revision restore/HTML sanitation, caption/alt. Лицензия копируется verbatim. Платный LTS4.23+ не установлен. |
| Server XLSX/CSV | Собственный CatPart `CatalogImport` и существующий `App/Support/XlsxReader` | ZipArchive/DOM/libxml/iconv, ограниченные chunk/size/time и server validation; реальный import >100 строк и replay. |
| Уведомления | Собственные Requests/Outbox/capture transport; использован паттерн durable outbox/idempotency | PDO/transactions/GET_LOCK, private filesystem capture. CMTEH Quote/Lead/Inventory/MTA/SMTP не переносились; реальные адресаты/секреты не копировались. |

Browser `read-excel-file9.3.10`, fflate/saxen/unzipper, CMTEH storefront/defaults/Inventory/warehouse/whole import module **не установлены**. Сторонние CDN и новые composer/npm runtime зависимости отсутствуют. Playwright нужен только для локальных приёмочных тестов, берётся из готового cached runtime; в приложение и ZIP его node_modules не попадают.

OpenCart/CKEditor тексты лицензий: `opencart/packaging/licenses` внутри готового ZIP; оригиналы и все vendor notices дополнительно находятся в точном upstream ZIP. CMTEH notices: `CMTEH-NOTICE.md`, headers и исходный language README. Пакет включает исходники адаптаций. Совместимость подтверждается только фактически выполненными локальными DB/HTTP/browser проверками; hosting и real mail остаются NOT RUN.
