# OpenCart / СМ ТЕХНО: исследование до переноса

Дата: 2026-11-13. Только чтение исходников и имен каталогов; БД, файлы конфигурации, пароли, пользовательские данные и хостинг не открывались и не менялись.

## Найденные источники

- CatPart: `D:/codex/cat-part/cat-part`, новый worktree `D:/codex/cat-part/admin-unified`; `origin/main` = `9f9ba0b1919e83c0b979ade910475e73f89bd56b`. `git branch -a` / `git log --all --grep=admin|OpenCart|opencart` не обнаружили прежнюю ветку разработки админки. В доступных CatPart checkout и именах handoff найдено только это новое ТЗ. Это отсутствие в проверенных источниках, не доказательство отсутствия исходников у владельца.
- Рабочая реализация СМ ТЕХНО: `D:/codex/cmteh/opencart`, clean `main` / `origin/main`, исходник `8a5ada0da2428f274a54ac2b8d430d8b0ddc08f2` (PR26, аналитика). Корневое Next.js-приложение — эталон, не действующая PHP-админка.
- Старый чужой worktree `D:/codex/worktrees/cmteh-admin-experience` существует; не менялся. Более свежий канонический источник выше предпочтительнее.
- Handoff `D:/codex/_handoffs/cmteh-admin-result-2026-11-10.md` и `cmteh-admin-unification-result-2026-11-11.md` описывают CMTEH, не CatPart. Их разрешения на хостинг, учётные записи и очистку данных не переносятся на эту задачу.

## Версии и дистрибутив

`opencart/packaging/open-cart.lock.json` фиксирует OpenCart **4.1.0.4**, PHP >=8.1, официальный ZIP `https://github.com/opencart/opencart/releases/download/4.1.0.4/opencart-4.1.0.4.zip`.

Доступен локальный кеш `D:/codex/temp/cmteh-opencart-cache/opencart-4.1.0.4.zip`, 32,816,480 bytes. Фактически вычисленный SHA-256 совпал с lock: `cdef537f5a0a7e1b4ddbe16f589dd2e80b10150a09bfbe93ce76ef2d5c3218c0`. Это повторное использование готового артефакта, не установка/переустановка движка. В доступном CatPart исходнике установленная версия OpenCart не найдена; нельзя объявлять 4.1.0.4 фактической версией CatPart хостинга.

`extension/cmteh/install.json`: расширение «СМ ТЕХНО», версия 1.0.0. В исходнике 127 файлов. README сообщает проверенные локальные PHP8.3.36/MySQL8.4.13; handoff сообщает CMTEH REG.RU MySQL5.7.44/CLI PHP8.2.35. Эти сведения не являются новыми проверками CatPart и не заменяют фактическую совместимость кандидата.

OpenCart upstream `composer.json` и `LICENSE.md` — GPL-3.0-or-later. Русские словари расширения явно лицензированы GPL-3.0-or-later в `extension/cmteh/admin/language/README.md`. Общая отдельная лицензия всего пользовательского кода CMTEH в проверенных файлах не найдена; не следует приписывать её всем файлам. При переносе сохранять upstream лицензии/происхождение и маркировать адаптацию.

## Что можно адаптировать

| Область | Реальный код / контракт | Ограничение для CatPart |
|---|---|---|
| Каталог | `system/Catalog.php`, `CatalogWrites.php`, `CatalogAdmin.php`, `NativeCatalog.php`, `CatalogSchema.php` | Native product/category/manufacturer и связанные cmteh_* metadata с теми же native ID; не второй независимый каталог. Требуется миграция CatPart и адаптация DTO/поиска/адресов. |
| Редакторы | `admin/controller/module/cmteh_catalog.php`, Twig формы, штатный менеджер изображений | Обычные поля, связи, SEO, выбор изображений, status draft/published/archived; один общий маршрут ACL для разных типов недостаточен для CatPart editor/settings separation. |
| Содержимое | `cmteh_content` types site/home/navigation/page/article | Нужно заменить CMTEH default seed на содержимое CatPart, не переносить чужие контакты/тексты/оформление. |
| HTML и карта | `CatalogInput::html`, `yandexMapEmbed`, `siteAnalytics` | DOM allowlist H2–H6, списки/таблицы/figcaption; strip scripts/style/unsafe attrs. HTTPS allowlist виджетов Яндекса. Готовая серверная проверка, но перенос требует интеграционных регрессий. |
| Публикации | UTC `published_at`, `Catalog::content/sitemap`, `CatalogInput::date` | Есть отложенная видимость; пользовательская история версий и восстановление не обнаружены. `sections_revision` — защита конфликта поля, не история публикации. |
| Предпросмотр | `cmteh_catalog.php:293`, sanitized srcdoc | Доступ внутри авторизованного редактора; надо проверить требуемый публичный дизайн CatPart. |
| Меню/русификация | `admin/controller/event/cmteh_admin.php`; events из module/cmteh.php | Не меняет core, язык данных, ACL. Скрытие native меню не запрещает прямые URL и не интегрирует native сущности. |
| Защита каталога | `admin/controller/event/cmteh_catalog.php`, native synchronize | Нельзя переносить складские guards/cutover/резервы автоматически: это вне базового выпуска CatPart. |
| Обращения | `system/Lead.php`, `Quote.php`, Outbox/Mail/Input классы; `admin/.../cmteh_leads.php` | Сохраняют в extension lead/quote таблицы, не native order. Idempotency/outbox/приватные вложения полезны, но менеджерский lifecycle/цены/статусы/ответственный требуют разработки. |
| Отчёты | `system/AdminBusiness.php` | Читаемые projections существующих обращений, фильтры и пагинация; не продажи/выручка. |
| Импорт | `CatalogImport.php`, CLI `bin/import-catalog.php` | NDJSON preview/apply/resume, не требуемый UI CSV/XLSX mapping. |
| Складской импорт | `InventoryImport.php`, `cmteh_catalog::inventory` | CSV/XLSX с fixed columns article/warehouse_code/quantity/price/delivery_term и лимитами5MiB/10k. Это другой бизнес-процесс; не объявлять готовым импортом товаров CatPart. |
| Runtime/пакет | `dev/runtime.py`, compose, `packaging/build.py` | Кеш upstream и Docker-образы пригодны для изолированного стенда; не использовать CMTEH secrets/БД/private runtime для CatPart. |

## Ключевые API и границы

- `Catalog(PDO,prefix,languageId=1,storeId=0,privateDir=null)`; `migrate`, `products`, `product`, `categories`, `brands`, `content`, `routes`, `sitemap`.
- Admin trait: `editRows`, `editRecord`, `saveFromAdmin(type,input,confirmSlugChange)`, `deleteRecord`, `editorChoices`. Смена slug проверяет занятость/явное подтверждение и обновляет ссылки; адреса сейчас `/product`, `/category`, `/articles`, а не русские CatPart.
- `CatalogSchema` — native identity metadata; не native order. `CatalogWrites` writes shared native records и projections.
- `Database::connectFromOpenCart` использует DB_* установленных конфигов; `privateDir` запрещает document root; `siteUrl` требует HTTPS, кроме loopback. Не копировать private конфиг.
- `Lead::submit`, `Quote::submit` сохраняют записи+outbox с idempotency; Quote имеет защищённый attachment/retry. Не создают зарегистрированных покупателей. Для заказа CatPart выбрать native order либо отдельно обосновать исключение ТЗ.
- `module/cmteh.php` install/upgrade регистрируют собственные events и миграции, не выдают роли новые права. Старый набор включает InventorySchema/InventoryImport — перенос всего модуля без адаптации навяжет лишнюю схему.

## Непокрытые требования, которые нельзя замолчать

1. Источник существующей CatPart-админки отсутствует в проверенных ветках/каталогах. Нужен точный путь/ветка, если он существует.
2. Native заказ-запрос с immutable снимком цены/условий, lifecycle, менеджером, историей и защитой идемпотентности.
3. Менеджерская обработка всех форм; уведомление как вторичный экземпляр после commit.
4. Роли Администратор/Редактор/Менеджер с отдельными server access+modify, включая settings и preview/download.
5. Полный UI импорт CSV/XLSX с mapping, dry-run, ambiguity errors и явным apply; warehouse importer не эквивалентен.
6. Ревизии статей и восстановление; сохранение blog_posts и существующих русских URL.
7. Полная миграция бизнес-текстов CatPart и шаблонов без редизайна, общего каталога и существующих корзин.
8. CatPart-параметры Метрики (Webvisor/clickmap/link tracking/ecommerce и domain guard) отличаются от CMTEH defaults; сохранять CatPart принятую конфигурацию.
9. Изолированная миграция/повтор/сверка ID/images/URLs, backup+restore и code rollback после новых обращений.
10. Новая фактическая локальная/браузерная/хостинговая приёмка; CMTEH старые PASS к CatPart неприменимы.

## Проверки этого исследования

Выполнены чтение перечисленных исходников/документации, `git branch -a`, узкий `git log --all`, CMTEH `git status`, upstream `Get-FileHash`. Приложение, миграции, интеграционные и браузерные тесты не запускались. Данные и сервисы не менялись.

## Минимальный безопасный перенос

Рекомендация: собственное расширение `extension/catpart`, использующее native OpenCart каталог и заказы с CatPart metadata; существующая PHP-витрина читает тот же источник через небольшой адаптер. Заимствовать отдельно проверенные валидаторы HTML/URL/карты/аналитики, паттерны event-интеграции/native synchronization, outbox/idempotency, выбранные формы и оригинальные русские словари с указанием происхождения. Переиспользование допускает адаптацию, а не применение готового install целиком.

Причина: CMTEH `module/cmteh.php` одновременно устанавливает собственный storefront event, editorial defaults, Lead/Quote и складские таблицы/guards. `Catalog.php` требует `Inventory.php`, а его DTO умеют warehouse cutover. Полное копирование активирует не запрошенные CatPart представление и складской учёт, импортирует чужие defaults и привязывает русские маршруты к `/product`/`articles`. Требуется сохранить витрину CatPart и не запускать CMTEH installer/upgrade над CatPart данными.

Это архитектурная рекомендация до уточнения отсутствующего исходника CatPart-админки. Если он найден, сначала сравнить его версию/таблицы/права/установленные events, сохранить выполненную работу и адаптировать план; не устанавливать новый core автоматически.

## Доступные кеши и runtime (только имена, без конфигураций)

- Официальный проверенный ZIP и извлечённый upstream: `D:/codex/temp/cmteh-opencart-cache/` (см. SHA выше).
- Старые артефакты+evidence: `D:/codex/temp/cmteh-opencart-final/`.
- CMTEH изолированные runtime-каталоги существуют: `D:/codex/temp/cmteh-opencart-release-runtime/`, `D:/codex/temp/cmteh-admin-final-runtime-20261110-r4/`, `D:/codex/temp/cmteh-analytics-accepted-runtime-20261112/`. Есть private password файлы, их содержимое не открывалось. Не переиспользовать их БД/секреты как CatPart baseline.
- `opencart/dev/runtime.py` выбирает отдельный Docker Compose project по SHA-256 абсолютного runtime path (`cmteh-oc-` + 12символов). Новый приватный путь создаёт отдельные тома/сеть; образ и официальный ZIP можно кешировать. Runtime JSON/config/пароли не нужны для чтения в чат/коммита.
- `D:/codex/cmteh/.gitignore` исключает env и `.autopilot/serve.*`; runtime расположен вне checkout. Само наличие старых runtime не доказывает, что сервис запущен.
