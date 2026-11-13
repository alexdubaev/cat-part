# Слепая приёмка G4 — CatPart / OpenCart

Дата: 2026-11-13. Исполнитель: независимый blind_acceptance. Прочитан полностью единственный исходный brief `D:/codex/cat-part/admin-unified/.autopilot/2026-11-13-opencart-admin--wip/2026-11-13-brief.md`, включая последнее уточнение. Никакие остальные `.autopilot`/spec/tickets не читались. Навыки и подагенты не вызывались. Проверяется соответствие словам заказчика, не качество реализации кода.

Предел: **только изолированный стенд**; действующие CatPart/CMTEH и готовый владелец8095 не менялись/не останавливались. Только source catalog whitelist local_catpart read-only. Клиентские данные/production configs/secrets не читались/не переносились; синтетические пароли использовались из собственного private runtime без печати.

Clean clone `D:/codex/cat-part/admin-acceptance-20261113`: до старта и после сценариев `git status --short` пуст, HEAD `a5bc99a5d738f4ae76d9c588b7cf58394e8001c6`. Код/тесты не изменялись, commit/push не выполнялись. Исходный admin-unified не использовался для кода/тестов.

**Основной путь реально прошёл в браузере:** settings phone→3публичных страницы; home copy→витрина; новый published SKU`000-G4-13`, id12274→public price1234.56/SEO/delivery/search→cart qty150→refresh→checkout→один native order после replay→manager status «В работе»/note; contacts form→один persisted lead. Сервисы сами по себе не приняты за UI.

Полного соответствия всему ТЗ нет: категории сохраняют image без публичного потребителя; админский поиск не находит сохранённый кросс-номер; часть бизнесовых товарных текстов не управляется; native сотрудники преимущественно на английском; отдельные UX/maintenance/SEO границы указаны по строкам. Это не заменяется зелёными repository tests.

## Доказательства

- E1 — documented clean-clone setup exit0; собственный runtime `D:/codex/cat-part/admin-acceptance-runtime-20261113`, schema`catpart_admin_6316fc3e7dd6`, loopback8096. Bootstrap: brands1/categories1161/products7134/attributes302/values17966/images23459/compat122194/cross0/posts5/native_products0.
- E2 — собственный живой browser inventory `.../private/ui-inventory.json`; скриншоты dashboard/catalog/product/settings/contacts/analytics/home/menus/forms/article/import/orders/leads/reports/maintenance.png. Никакие пароли в inventory не включались.
- E3 — foundation integration:7 guard+27 HTTP/login/ACL cases,0fail.
- E4 — catalog:30 DB cases +25 HTTP cases,0fail; CSV125create+1update; XLSX, upload, native editors/SEO/public/category/brand/redirect/permissions.
- E5 — content:16 DB+21 HTTP+7 browser cases,0fail; CKEditor draft/publish/future/restore; map/phone/analytics/meta/verification files.
- E6 — requests:29 DB cases,0fail +7 checkout unittest cases OK; idempotency/concurrency/rollback/failure capture/status/history/quote/no auto customer/no reserve.
- E7 — SEO31 orchestration/HTTP cases,0fail; published/noindex sitemap, direct301 rename-back/encoded paths, aliases.
- E8 — собственный browser script `.../g4-browser.cjs`, results `.../private/g4-browser-results.json`, exit0. Скриншоты public-home.png/public-product.png/order.png. Все формы и checkout только synthetic example.invalid, capture.
- E9 — собственный edge browser `.../g4-edge.cjs`, exit0; `.../private/g4-edge-results.json`: cross publicFound=true/adminFound=false/adminCount0; category retained image, publicImages только logo; category-public.png.
- E10 — `.../native-users-probe.cjs`, exit0, `.../private/native-users-ui.json`: реальные native users/usergroups/add-user labels English.
- E11 — `admin-data.php inventory`, exit0; private/migration-report.json: all source IDs mapped, product/category extras ровно собственные G4 fixtures; missing IDs0, product category orphan0/image orphan0,23459 references/missing0. Полный inventory private, не копируется в публичный отчёт.
- E12 — package build/verify/install: ZIP SHA256`6a848d03c5a02bcf517afad3ca8727ddea88ff347eb85ab96ff9022629585532`,175payloadfiles, source revision выше, source tree`343b7d02176612d273ecbda90359e642abf487076a564d435c31a7d4cef38d44`; второй собственный stand `D:/codex/cat-part/admin-acceptance-installed-20261113`,schema`catpart_admin_183e4a19d41e`,8097. Shipping/package completion ниже.
- E13 — прочитанные обычные документы clean clone: docs/admin-guide.md, admin-migration.md, admin-architecture.md, admin-reuse.md, ADR001; docs описывают реальные границы/команды, не служат заменой run evidence.
- E14 — после фактического UI осмотра точечное чтение public consumers/controllers подтвердило отсутствие редакторов business literals/category image; это функциональная трассировка, не code-quality review.

## Каждый атомарный пункт

Статус «реализовано» означает подтверждённый локальный результат в указанной группе/экране; не обещание hosting. «частично» означает конкретный неполный потребитель/UI либо требование с оговорённой проверкой NOT RUN; не всякая такая строка — дефект разрешённого локального выпуска. Проверки toolbar-функций/инфраструктурных правил могут опираться на наблюдаемый UI и целевой DB/HTTP integration, метод указан в evidence. Процессную хронологию до разработки из результата доказать невозможно.

Проверено пунктов: **235**; реализовано: **221**; частично: **14**; нет: **0**. Полный matched не заявлен.

| ID | Требование исходного раздела | Статус | Где видно / результат |
|---|---|---|---|
| 1.1 | Одна установка OpenCart и одна админка | реализовано | E1/E4/E8/E13: реальный стенд, canonical IDs, native_products=0; OpenCart upstream lock, docs/admin-architecture.md, docs/admin-reuse.md. |
| 1.2 | Общий источник каталога, содержимого, заказов и обращений | реализовано | E1/E4/E8/E13: реальный стенд, canonical IDs, native_products=0; OpenCart upstream lock, docs/admin-architecture.md, docs/admin-reuse.md. |
| 1.3 | Сохранить оформление CatPart | реализовано | E1/E4/E8/E13: реальный стенд, canonical IDs, native_products=0; OpenCart upstream lock, docs/admin-architecture.md, docs/admin-reuse.md. |
| 1.4 | Разные представления читают общие данные | реализовано | E1/E4/E8/E13: реальный стенд, canonical IDs, native_products=0; OpenCart upstream lock, docs/admin-architecture.md, docs/admin-reuse.md. |
| 1.5 | Расширение без изменения ядра | реализовано | E1/E4/E8/E13: реальный стенд, canonical IDs, native_products=0; OpenCart upstream lock, docs/admin-architecture.md, docs/admin-reuse.md. |
| 1.6 | Версия OpenCart4.1.0.4 и зависимости зафиксированы | реализовано | E1/E4/E8/E13: реальный стенд, canonical IDs, native_products=0; OpenCart upstream lock, docs/admin-architecture.md, docs/admin-reuse.md. |
| 1.7 | Нет двух независимых каталогов | реализовано | E1/E4/E8/E13: реальный стенд, canonical IDs, native_products=0; OpenCart upstream lock, docs/admin-architecture.md, docs/admin-reuse.md. |
| 1.8 | Дополнительные таблицы ссылаются на исходные товары/категории | реализовано | E1/E4/E8/E13: реальный стенд, canonical IDs, native_products=0; OpenCart upstream lock, docs/admin-architecture.md, docs/admin-reuse.md. |
| 1.9 | До реализации составить таблицу сущность → источник → редактор → потребители | частично | E13: архитектурное описание/таблица действий есть, отдельная полная таблица именно четырёх связей в доступных docs не найдена; время её создания слепая приёмка не доказывает. |
| 2.1 | Главная показывает реальные заказы/обращения/ошибки уведомлений | реализовано | E2/E3/E4/E5/E6/E8: UI-inventory, native menu, живой заказ/lead; dashboard/reports содержат реальные счетчики. |
| 2.2 | Каталог: товары, категории, бренды, характеристики | реализовано | E2/E3/E4/E5/E6/E8: UI-inventory, native menu, живой заказ/lead; dashboard/reports содержат реальные счетчики. |
| 2.3 | Заказы: запросы, позиции, история | реализовано | E2/E3/E4/E5/E6/E8: UI-inventory, native menu, живой заказ/lead; dashboard/reports содержат реальные счетчики. |
| 2.4 | Обратная связь: заявки и сообщения | реализовано | E2/E3/E4/E5/E6/E8: UI-inventory, native menu, живой заказ/lead; dashboard/reports содержат реальные счетчики. |
| 2.5 | Содержимое: статьи, страницы, главная | реализовано | E2/E3/E4/E5/E6/E8: UI-inventory, native menu, живой заказ/lead; dashboard/reports содержат реальные счетчики. |
| 2.6 | Меню и подвал: ссылки, порядок, видимость | реализовано | E2/E3/E4/E5/E6/E8: UI-inventory, native menu, живой заказ/lead; dashboard/reports содержат реальные счетчики. |
| 2.7 | Настройки: контакты, реквизиты, карта, аналитика, права | реализовано | E2/E3/E4/E5/E6/E8: UI-inventory, native menu, живой заказ/lead; dashboard/reports содержат реальные счетчики. |
| 2.8 | Пользователи и права: сотрудники/роли | реализовано | E2/E3/E4/E5/E6/E8: UI-inventory, native menu, живой заказ/lead; dashboard/reports содержат реальные счетчики. |
| 2.9 | Отчёты используют фактические данные без неподтверждённой выручки | реализовано | E2/E3/E4/E5/E6/E8: UI-inventory, native menu, живой заказ/lead; dashboard/reports содержат реальные счетчики. |
| 2.10 | Обслуживание: почта, журнал ошибок, импорт, технические инструменты | частично | E2: import и outbox/audit доступны. В экране «Обслуживание» только уведомления и журнал действий; просмотр общего PHP/error log и технических инструментов через UI не найден (maintenance.php). |
| 2.11 | Русские понятные названия и оформление CatPart | частично | E2/E10: основные разделы русские, CatPart brand есть. user/user, user/user_permission, user/user.form показывают Users, Filter, Username, User Group, Add User, First Name, Password и др.; native-users-ui.json. |
| 2.12 | Нет дублирующих разделов с разными данными | реализовано | E1/E2/E3/E13: native_products=0, 4 synthetic users; settings UI; direct native catalog deny; admin-guide.md. |
| 2.13 | Нет демонстрационных товаров/категорий/покупателей/заказов OpenCart | реализовано | E1/E2/E3/E13: native_products=0, 4 synthetic users; settings UI; direct native catalog deny; admin-guide.md. |
| 2.14 | Настройки сразу открывают вкладки без site/mock-site | реализовано | E1/E2/E3/E13: native_products=0, 4 synthetic users; settings UI; direct native catalog deny; admin-guide.md. |
| 2.15 | Повседневная работа без JSON, SQL и PHP | реализовано | E1/E2/E3/E13: native_products=0, 4 synthetic users; settings UI; direct native catalog deny; admin-guide.md. |
| 2.16 | Неподключённые native функции скрыты и сервером запрещены | реализовано | E1/E2/E3/E13: native_products=0, 4 synthetic users; settings UI; direct native catalog deny; admin-guide.md. |
| 2.17 | Оставшиеся native возможности перечислены отдельно | реализовано | E1/E2/E3/E13: native_products=0, 4 synthetic users; settings UI; direct native catalog deny; admin-guide.md. |
| 3.1 | Все бизнесовые данные и содержимое управляются без программиста | частично | E14/E8: product.php содержит неизменяемые из админки «Цена зависит от объёма и срока», USP «Поставка из наличия и под заказ, доставка по России», абзац «Отправляем транспортными компаниями…». Эти тексты реально видны на public-product.png; editor/cp_content для них нет. |
| 3.2 | Название сайта/компании, логотип и favicon | реализовано | E2/E5/E8: нормальные поля; UI sections home/pages/menus/forms; content seed; живой hero text/link save. |
| 3.3 | Контакты и реквизиты | реализовано | E2/E5/E8: нормальные поля; UI sections home/pages/menus/forms; content seed; живой hero text/link save. |
| 3.4 | Шапка: навигация, контакты, подписи/URL | реализовано | E2/E5/E8: нормальные поля; UI sections home/pages/menus/forms; content seed; живой hero text/link save. |
| 3.5 | Главная: заголовки, тексты, картинки, преимущества, кнопки | реализовано | E2/E5/E8: нормальные поля; UI sections home/pages/menus/forms; content seed; живой hero text/link save. |
| 3.6 | Каталог: поля товаров/категорий/брендов/SEO | реализовано | E2/E5/E8: нормальные поля; UI sections home/pages/menus/forms; content seed; живой hero text/link save. |
| 3.7 | Статьи: заголовок, анонс, содержание, обложка, публикация/SEO | реализовано | E2/E5/E8: нормальные поля; UI sections home/pages/menus/forms; content seed; живой hero text/link save. |
| 3.8 | Информационные страницы: о компании, доставка, оплата, гарантия, контакты, политика | реализовано | E2/E5/E8: нормальные поля; UI sections home/pages/menus/forms; content seed; живой hero text/link save. |
| 3.9 | Формы: заголовки, пояснения, кнопки, согласие и централизованные получатели | реализовано | E2/E5/E8: нормальные поля; UI sections home/pages/menus/forms; content seed; живой hero text/link save. |
| 3.10 | Подвал: меню/контакты/юридические ссылки/примечания | реализовано | E2/E5/E8: нормальные поля; UI sections home/pages/menus/forms; content seed; живой hero text/link save. |
| 3.11 | Главная: включение, выключение, порядок блоков | реализовано | E2/E5/E8: нормальные поля; UI sections home/pages/menus/forms; content seed; живой hero text/link save. |
| 3.12 | Подборки: ручной выбор или явно автоматический режим | реализовано | E2/E5/E8: нормальные поля; UI sections home/pages/menus/forms; content seed; живой hero text/link save. |
| 3.13 | Существующее основное содержимое перенесено в управляемые записи | реализовано | E2/E5/E8: нормальные поля; UI sections home/pages/menus/forms; content seed; живой hero text/link save. |
| 3.14 | Title/description/H1/canonical/noindex/OG всего поискового представления | частично | E4/E5/E7/E2: entity SEO и home/info/articles управляются; для общего /catalog и /poisk редактора SEO/H1 не найдено (catalog listing controller/search controller задают их в коде). |
| 3.15 | Бизнесовые условия и рекламные тексты не разбросаны по шаблонам | частично | E14: публичные pcard USP/price hint/delivery paragraph остаются литералами product.php; не системные сообщения валидации. |
| 4.1 | Название, строковый артикул и OEM/кросс-номера | реализовано | E4/E8: 30 DB+25 HTTP catalog checks; живой SKU000-G4-13/price1234.56/qty150; галерея и relations. |
| 4.2 | Бренд, основная и дополнительные категории | реализовано | E4/E8: 30 DB+25 HTTP catalog checks; живой SKU000-G4-13/price1234.56/qty150; галерея и relations. |
| 4.3 | Краткое и полное описание | реализовано | E4/E8: 30 DB+25 HTTP catalog checks; живой SKU000-G4-13/price1234.56/qty150; галерея и relations. |
| 4.4 | Характеристики | реализовано | E4/E8: 30 DB+25 HTTP catalog checks; живой SKU000-G4-13/price1234.56/qty150; галерея и relations. |
| 4.5 | Основное изображение, галерея, alt и подписи | реализовано | E4/E8: 30 DB+25 HTTP catalog checks; живой SKU000-G4-13/price1234.56/qty150; галерея и relations. |
| 4.6 | Цена, валюта, режим по запросу | реализовано | E4/E8: 30 DB+25 HTTP catalog checks; живой SKU000-G4-13/price1234.56/qty150; галерея и relations. |
| 4.7 | Наличие и условия поставки | реализовано | E4/E8: 30 DB+25 HTTP catalog checks; живой SKU000-G4-13/price1234.56/qty150; галерея и relations. |
| 4.8 | Связанные товары и аналоги | реализовано | E4/E8: 30 DB+25 HTTP catalog checks; живой SKU000-G4-13/price1234.56/qty150; галерея и relations. |
| 4.9 | Slug, SEO, публикация и архивирование | реализовано | E4/E8: 30 DB+25 HTTP catalog checks; живой SKU000-G4-13/price1234.56/qty150; галерея и relations. |
| 4.10 | Сохраняются ведущие нули, дефисы и написание SKU | реализовано | E4/E8: 30 DB+25 HTTP catalog checks; живой SKU000-G4-13/price1234.56/qty150; галерея и relations. |
| 4.11 | Единые правила поиска и сопоставления сайта, админки и импорта | частично | E9: нормализация артикула общая; сохранённый cross009-G4-CROSS находит «Приёмка G4 товар» в /poisk, admin catalog?q=009-G4-CROSS показывает «Записей: 0». g4-edge-results.json. |
| 4.12 | Категории: иерархия, описание, порядок, публикация/SEO | реализовано | E4/E8/E12: реальные DB/HTTP проверки, предупреждение archive, native qty не списывается. |
| 4.13 | Дочерние товары выводятся в родительских разделах | реализовано | E4/E8/E12: реальные DB/HTTP проверки, предупреждение archive, native qty не списывается. |
| 4.14 | Бренды: название, логотип, описание/SEO | реализовано | E4/E8/E12: реальные DB/HTTP проверки, предупреждение archive, native qty не списывается. |
| 4.15 | Разрешённые редакторы изменяют общий товар витрины | реализовано | E4/E8/E12: реальные DB/HTTP проверки, предупреждение archive, native qty не списывается. |
| 4.16 | Импорт без дубликатов | реализовано | E4/E8/E12: реальные DB/HTTP проверки, предупреждение archive, native qty не списывается. |
| 4.17 | Смена категории сохраняет связи | реализовано | E4/E8/E12: реальные DB/HTTP проверки, предупреждение archive, native qty не списывается. |
| 4.18 | Связанная запись архивируется с понятным именованным подтверждением | реализовано | E4/E8/E12: реальные DB/HTTP проверки, предупреждение archive, native qty не списывается. |
| 4.19 | Неизвестные записи не удаляются как демонстрационные | реализовано | E4/E8/E12: реальные DB/HTTP проверки, предупреждение archive, native qty не списывается. |
| 4.20 | Признак наличия не превращён в количественный остаток | реализовано | E4/E8/E12: реальные DB/HTTP проверки, предупреждение archive, native qty не списывается. |
| 4.21 | Категория поддерживает редактируемое изображение с результатом на витрине | частично | E9: category image retained=/assets/img/benefits/stock.webp; /catalog/g4-cat-image содержит из img только /assets/img/logo.webp. Listing/home используют прежнюю графику, сохранённая category image не потребляется. category-public.png. |
| 5.1 | Загрузка CSV и XLSX | реализовано | E4: реальный CSV125+1, повторный apply/upload, XLSX textual SKU, malformed XML/XXE deny; UI16MiB/100000rows/200cols, chunk250/10s и apply100, report. |
| 5.2 | Сопоставление колонок | реализовано | E4: реальный CSV125+1, повторный apply/upload, XLSX textual SKU, malformed XML/XXE deny; UI16MiB/100000rows/200cols, chunk250/10s и apply100, report. |
| 5.3 | Предварительная проверка без изменения каталога | реализовано | E4: реальный CSV125+1, повторный apply/upload, XLSX textual SKU, malformed XML/XXE deny; UI16MiB/100000rows/200cols, chunk250/10s и apply100, report. |
| 5.4 | Показ создаваемых/изменяемых строк и ошибок | реализовано | E4: реальный CSV125+1, повторный apply/upload, XLSX textual SKU, malformed XML/XXE deny; UI16MiB/100000rows/200cols, chunk250/10s и apply100, report. |
| 5.5 | Явное подтверждение применения | реализовано | E4: реальный CSV125+1, повторный apply/upload, XLSX textual SKU, malformed XML/XXE deny; UI16MiB/100000rows/200cols, chunk250/10s и apply100, report. |
| 5.6 | Отчёт результата | реализовано | E4: реальный CSV125+1, повторный apply/upload, XLSX textual SKU, malformed XML/XXE deny; UI16MiB/100000rows/200cols, chunk250/10s и apply100, report. |
| 5.7 | Неуказанные товары не удаляются | реализовано | E4: реальный CSV125+1, повторный apply/upload, XLSX textual SKU, malformed XML/XXE deny; UI16MiB/100000rows/200cols, chunk250/10s и apply100, report. |
| 5.8 | Неуказанные поля не обнуляются | реализовано | E4: реальный CSV125+1, повторный apply/upload, XLSX textual SKU, malformed XML/XXE deny; UI16MiB/100000rows/200cols, chunk250/10s и apply100, report. |
| 5.9 | Повторный файл не дублирует | реализовано | E4: реальный CSV125+1, повторный apply/upload, XLSX textual SKU, malformed XML/XXE deny; UI16MiB/100000rows/200cols, chunk250/10s и apply100, report. |
| 5.10 | Обоснованные размер/строки/время, продолжение без обрезания | реализовано | E4: реальный CSV125+1, повторный apply/upload, XLSX textual SKU, malformed XML/XXE deny; UI16MiB/100000rows/200cols, chunk250/10s и apply100, report. |
| 5.11 | Ошибка содержит конкретную строку и причину | реализовано | E4: реальный CSV125+1, повторный apply/upload, XLSX textual SKU, malformed XML/XXE deny; UI16MiB/100000rows/200cols, chunk250/10s и apply100, report. |
| 6.1 | Обязательный раздел статей и визуальный редактор | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.2 | Абзацы, H2–H4 | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.3 | Списки, ссылки, цитаты, таблицы | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.4 | Изображения в тексте, alt и подписи | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.5 | Обложка и анонс | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.6 | Автор и дата публикации | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.7 | Черновик, публикация, отложенная публикация, архив | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.8 | Предпросмотр | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.9 | История и восстановление версии | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.10 | Slug, title, description, OG image | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.11 | Связанные статьи и товары | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.12 | Отдельное поле H1 | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.13 | Очистка Word formatting | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.14 | Скрипты/опасные атрибуты не сохраняются | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.15 | Черновик закрыт анонимно и отсутствует в sitemap | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.16 | Предпросмотр требует авторизацию | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.17 | Единое UTC/Moscow трактование расписания | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 6.18 | Миграция blog_posts учитывает исходные статьи/адреса/изображения | реализовано | E5/E7/E11: native CKEditor real browser draft→preview→publish→future→restore; DB sanitation и dates; 5 исходных статей/IDs сохранены. Списки/ссылки/цитаты и выбор связей присутствуют UI; не каждый toolbar-action набирался вручную. |
| 7.1 | Корзина общих реальных товаров | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 7.2 | Добавление из карточки | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 7.3 | Добавление из каталога | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 7.4 | Добавление из поиска | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 7.5 | Изменение количества | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 7.6 | Удаление позиции | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 7.7 | Сохранение после обновления | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 7.8 | Скрытый/изменённый товар обработан | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 7.9 | Цена по запросу понятна | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 7.10 | Отправка заказа-запроса | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 7.11 | Сервер проверяет товар/количество/актуальную цену | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 7.12 | Браузерная цена не доверена | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 7.13 | При сбое корзина остаётся | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 7.14 | После commit очищаются только отправленные позиции | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 7.15 | Replay после потери ответа не создаёт заказ | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 7.16 | Вместо99 явный предел и ошибка, qty150 сохранено | реализовано | E6/E8: 29 request checks +7 checkout HTTP cases; реальный browser search→cart150→reload→checkout→replay. |
| 8.1 | Успешная корзина создаёт постоянный заказ в БД/админке | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.2 | Штатная сущность OpenCart order и единый реестр | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.3 | Уникальный номер, дата/время, источник | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.4 | Имя, телефон, email, клиентский комментарий | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.5 | Позиции с product ID/SKU/name/quantity | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.6 | Снимок цены и условий | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.7 | Отметка необходимости подтверждения цены | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.8 | Статус и ответственный менеджер | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.9 | Внутренние комментарии | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.10 | История и состояние уведомления | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.11 | Все шесть требуемых базовых статусов | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.12 | Изменение товара не переписывает старый заказ | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.13 | Расценка менеджера отдельно и с историей | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.14 | Нет онлайн-оплаты | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.15 | Менеджер подтверждает наличие/цену/условия | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.16 | Запрос не означает оплату | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.17 | Нет автоматического резерва/списания | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 8.18 | Нет выручки из неподтверждённых запросов | реализовано | E6/E8: native oc_order/oc_order_product/snapshot, readonly history, actual manager status/note; service concurrency and immutable snapshot checks. |
| 9.1 | Формы сохраняют обратный звонок/подбор/контакты/другие блоки | реализовано | E6/E8: все формы используют persisted lead workflow; contacts browser submission и manager registry; типы формы registry; no native customer/subscriber assertion. |
| 9.2 | Обращение: номер, тип, контакты, сообщение, источник, время | реализовано | E6/E8: все формы используют persisted lead workflow; contacts browser submission и manager registry; типы формы registry; no native customer/subscriber assertion. |
| 9.3 | Обращение: статус, ответственный, история | реализовано | E6/E8: все формы используют persisted lead workflow; contacts browser submission и manager registry; типы формы registry; no native customer/subscriber assertion. |
| 9.4 | Фильтры и поиск по контактам | реализовано | E6/E8: все формы используют persisted lead workflow; contacts browser submission и manager registry; типы формы registry; no native customer/subscriber assertion. |
| 9.5 | Изменение статуса/внутренний комментарий | реализовано | E6/E8: все формы используют persisted lead workflow; contacts browser submission и manager registry; типы формы registry; no native customer/subscriber assertion. |
| 9.6 | Не создаётся покупатель/подписчик автоматически | реализовано | E6/E8: все формы используют persisted lead workflow; contacts browser submission и manager registry; типы формы registry; no native customer/subscriber assertion. |
| 10.1 | Проверка → commit → outbox → клиентское подтверждение | реализовано | E6/E2/E13: mail failure/retry DB checks и HTTP capture MIME/failure; UI Outbox states; capture-only setup. |
| 10.2 | Сбой почты не теряет запрос | реализовано | E6/E2/E13: mail failure/retry DB checks и HTTP capture MIME/failure; UI Outbox states; capture-only setup. |
| 10.3 | В админке состояние и понятная причина уведомления | реализовано | E6/E2/E13: mail failure/retry DB checks и HTTP capture MIME/failure; UI Outbox states; capture-only setup. |
| 10.4 | Retry и защита от дубля | реализовано | E6/E2/E13: mail failure/retry DB checks и HTTP capture MIME/failure; UI Outbox states; capture-only setup. |
| 10.5 | Централизованные получатели/адрес/имя отправителя | реализовано | E6/E2/E13: mail failure/retry DB checks и HTTP capture MIME/failure; UI Outbox states; capture-only setup. |
| 10.6 | Секреты только private config | реализовано | E6/E2/E13: mail failure/retry DB checks и HTTP capture MIME/failure; UI Outbox states; capture-only setup. |
| 10.7 | Локальный mail capture | реализовано | E6/E2/E13: mail failure/retry DB checks и HTTP capture MIME/failure; UI Outbox states; capture-only setup. |
| 10.8 | Принятие сервером не называется inbox delivery | реализовано | E6/E2/E13: mail failure/retry DB checks и HTTP capture MIME/failure; UI Outbox states; capture-only setup. |
| 10.9 | Контрольная отправка на целевом хостинге с получением | частично | NOT RUN / deferred: последнее уточнение владельца ограничило работу изолированным стендом; реальные письма запрещены текущим поручением. Это не локальный PASS и не дефект разрешённого стенда. |
| 11.1 | Компания и реквизиты | реализовано | E2/E5/E8: реальные поля contacts; map validation cases; browser save/clear, общий synthetic phone3pages и structured data HTTP test. |
| 11.2 | Список телефонов и основная кнопка | реализовано | E2/E5/E8: реальные поля contacts; map validation cases; browser save/clear, общий synthetic phone3pages и structured data HTTP test. |
| 11.3 | Email/адрес/офис/часы | реализовано | E2/E5/E8: реальные поля contacts; map validation cases; browser save/clear, общий synthetic phone3pages и structured data HTTP test. |
| 11.4 | Мессенджеры/социальные ссылки | реализовано | E2/E5/E8: реальные поля contacts; map validation cases; browser save/clear, общий synthetic phone3pages и structured data HTTP test. |
| 11.5 | Поле Код или ссылка виджета Яндекс Карт | реализовано | E2/E5/E8: реальные поля contacts; map validation cases; browser save/clear, общий synthetic phone3pages и structured data HTTP test. |
| 11.6 | Принимается штатный iframe или widget URL | реализовано | E2/E5/E8: реальные поля contacts; map validation cases; browser save/clear, общий synthetic phone3pages и structured data HTTP test. |
| 11.7 | HTTPS allowlist и безопасный iframe template | реализовано | E2/E5/E8: реальные поля contacts; map validation cases; browser save/clear, общий synthetic phone3pages и structured data HTTP test. |
| 11.8 | Предпросмотр карты | реализовано | E2/E5/E8: реальные поля contacts; map validation cases; browser save/clear, общий synthetic phone3pages и structured data HTTP test. |
| 11.9 | Очистка удаляет карту без её контейнера | реализовано | E2/E5/E8: реальные поля contacts; map validation cases; browser save/clear, общий synthetic phone3pages и structured data HTTP test. |
| 11.10 | Произвольный JavaScript отклоняется | реализовано | E2/E5/E8: реальные поля contacts; map validation cases; browser save/clear, общий synthetic phone3pages и structured data HTTP test. |
| 11.11 | Шапка/подвал/формы/structured data/контакты читают общие настройки | реализовано | E2/E5/E8: реальные поля contacts; map validation cases; browser save/clear, общий synthetic phone3pages и structured data HTTP test. |
| 12.1 | Отдельная вкладка Аналитика и подтверждение прав | реализовано | E5/E2/E13: content checks real local HTML and analyticsHtml output for allowed host без реального provider network;2старых verification files200; brand Metrika source. Actual third-party outage не имитировался сетевым chaos; async независим от render. |
| 12.2 | Metrika по номеру | реализовано | E5/E2/E13: content checks real local HTML and analyticsHtml output for allowed host без реального provider network;2старых verification files200; brand Metrika source. Actual third-party outage не имитировался сетевым chaos; async независим от render. |
| 12.3 | GA4 по measurement ID | реализовано | E5/E2/E13: content checks real local HTML and analyticsHtml output for allowed host без реального provider network;2старых verification files200; brand Metrika source. Actual third-party outage не имитировался сетевым chaos; async независим от render. |
| 12.4 | Яндекс Вебмастер meta content | реализовано | E5/E2/E13: content checks real local HTML and analyticsHtml output for allowed host без реального provider network;2старых verification files200; brand Metrika source. Actual third-party outage не имитировался сетевым chaos; async независим от render. |
| 12.5 | Google Search Console meta content | реализовано | E5/E2/E13: content checks real local HTML and analyticsHtml output for allowed host без реального provider network;2старых verification files200; brand Metrika source. Actual third-party outage не имитировался сетевым chaos; async независим от render. |
| 12.6 | Без HTML ввода | реализовано | E5/E2/E13: content checks real local HTML and analyticsHtml output for allowed host без реального provider network;2старых verification files200; brand Metrika source. Actual third-party outage не имитировался сетевым chaos; async независим от render. |
| 12.7 | Серверная валидация | реализовано | E5/E2/E13: content checks real local HTML and analyticsHtml output for allowed host без реального provider network;2старых verification files200; brand Metrika source. Actual third-party outage не имитировался сетевым chaos; async независим от render. |
| 12.8 | Одна инициализация, async | реализовано | E5/E2/E13: content checks real local HTML and analyticsHtml output for allowed host без реального provider network;2старых verification files200; brand Metrika source. Actual third-party outage не имитировался сетевым chaos; async независим от render. |
| 12.9 | Нет counters в admin | реализовано | E5/E2/E13: content checks real local HTML and analyticsHtml output for allowed host без реального provider network;2старых verification files200; brand Metrika source. Actual third-party outage не имитировался сетевым chaos; async независим от render. |
| 12.10 | Нет тестовых analytics hits localhost | реализовано | E5/E2/E13: content checks real local HTML and analyticsHtml output for allowed host без реального provider network;2старых verification files200; brand Metrika source. Actual third-party outage не имитировался сетевым chaos; async независим от render. |
| 12.11 | Очистка отключает | реализовано | E5/E2/E13: content checks real local HTML and analyticsHtml output for allowed host без реального provider network;2старых verification files200; brand Metrika source. Actual third-party outage не имитировался сетевым chaos; async независим от render. |
| 12.12 | Ошибка провайдера не блокирует сайт | реализовано | E5/E2/E13: content checks real local HTML and analyticsHtml output for allowed host без реального provider network;2старых verification files200; brand Metrika source. Actual third-party outage не имитировался сетевым chaos; async независим от render. |
| 12.13 | Сохранён существующий Metrika114229167 и параметры | реализовано | E5/E2/E13: content checks real local HTML and analyticsHtml output for allowed host без реального provider network;2старых verification files200; brand Metrika source. Actual third-party outage не имитировался сетевым chaos; async независим от render. |
| 12.14 | Сохранены прежние Yandex verification files | реализовано | E5/E2/E13: content checks real local HTML and analyticsHtml output for allowed host без реального provider network;2старых verification files200; brand Metrika source. Actual third-party outage не имитировался сетевым chaos; async независим от render. |
| 12.15 | Поле метатега не выдается за подтверждение кабинета | реализовано | E5/E2/E13: content checks real local HTML and analyticsHtml output for allowed host без реального provider network;2старых verification files200; brand Metrika source. Actual third-party outage не имитировался сетевым chaos; async независим от render. |
| 13.1 | Сохранён /catalog и категории | реализовано | E4/E5/E7/E8:31SEO checks + catalog HTTP; старые IDs/slugs inventory; публичный SEO title native save. При наличии fixed price Product schema не изобретает Offer; цена на экране фактическая. |
| 13.2 | Сохранён /tovar/{slug} | реализовано | E4/E5/E7/E8:31SEO checks + catalog HTTP; старые IDs/slugs inventory; публичный SEO title native save. При наличии fixed price Product schema не изобретает Offer; цена на экране фактическая. |
| 13.3 | Сохранён /poisk | реализовано | E4/E5/E7/E8:31SEO checks + catalog HTTP; старые IDs/slugs inventory; публичный SEO title native save. При наличии fixed price Product schema не изобретает Offer; цена на экране фактическая. |
| 13.4 | Сохранён /korzina | реализовано | E4/E5/E7/E8:31SEO checks + catalog HTTP; старые IDs/slugs inventory; публичный SEO title native save. При наличии fixed price Product schema не изобретает Offer; цена на экране фактическая. |
| 13.5 | Сохранён /stati и статьи | реализовано | E4/E5/E7/E8:31SEO checks + catalog HTTP; старые IDs/slugs inventory; публичный SEO title native save. При наличии fixed price Product schema не изобретает Offer; цена на экране фактическая. |
| 13.6 | Сохранены информационные страницы | реализовано | E4/E5/E7/E8:31SEO checks + catalog HTTP; старые IDs/slugs inventory; публичный SEO title native save. При наличии fixed price Product schema не изобретает Offer; цена на экране фактическая. |
| 13.7 | Сохранены sitemap/robots/verification files | реализовано | E4/E5/E7/E8:31SEO checks + catalog HTTP; старые IDs/slugs inventory; публичный SEO title native save. При наличии fixed price Product schema не изобретает Offer; цена на экране фактическая. |
| 13.8 | Slug: занятость и reserved routes | реализовано | E4/E5/E7/E8:31SEO checks + catalog HTTP; старые IDs/slugs inventory; публичный SEO title native save. При наличии fixed price Product schema не изобретает Offer; цена на экране фактическая. |
| 13.9 | Slug: явное подтверждение | реализовано | E4/E5/E7/E8:31SEO checks + catalog HTTP; старые IDs/slugs inventory; публичный SEO title native save. При наличии fixed price Product schema не изобретает Offer; цена на экране фактическая. |
| 13.10 | Прямой301 со старого URL | реализовано | E4/E5/E7/E8:31SEO checks + catalog HTTP; старые IDs/slugs inventory; публичный SEO title native save. При наличии fixed price Product schema не изобретает Offer; цена на экране фактическая. |
| 13.11 | Без циклов/длинных цепочек | реализовано | E4/E5/E7/E8:31SEO checks + catalog HTTP; старые IDs/slugs inventory; публичный SEO title native save. При наличии fixed price Product schema не изобретает Offer; цена на экране фактическая. |
| 13.12 | SEO выводится в HTML | реализовано | E4/E5/E7/E8:31SEO checks + catalog HTTP; старые IDs/slugs inventory; публичный SEO title native save. При наличии fixed price Product schema не изобретает Offer; цена на экране фактическая. |
| 13.13 | Sitemap только публичных indexable страниц | реализовано | E4/E5/E7/E8:31SEO checks + catalog HTTP; старые IDs/slugs inventory; публичный SEO title native save. При наличии fixed price Product schema не изобретает Offer; цена на экране фактическая. |
| 13.14 | Structured data не выдумывает цены | реализовано | E4/E5/E7/E8:31SEO checks + catalog HTTP; старые IDs/slugs inventory; публичный SEO title native save. При наличии fixed price Product schema не изобретает Offer; цена на экране фактическая. |
| 14.1 | Администратор: настройки/пользователи/обслуживание | реализовано | E3/E4/E5/E6/E2/E10:4 fresh synthetic роли; direct ACL and private import ownership; upload verified MIME/content/size; audit and request revisions. |
| 14.2 | Редактор: статьи/страницы/каталог | реализовано | E3/E4/E5/E6/E2/E10:4 fresh synthetic роли; direct ACL and private import ownership; upload verified MIME/content/size; audit and request revisions. |
| 14.3 | Менеджер: заказы/обращения | реализовано | E3/E4/E5/E6/E2/E10:4 fresh synthetic роли; direct ACL and private import ownership; upload verified MIME/content/size; audit and request revisions. |
| 14.4 | Access и modify отдельно | реализовано | E3/E4/E5/E6/E2/E10:4 fresh synthetic роли; direct ACL and private import ownership; upload verified MIME/content/size; audit and request revisions. |
| 14.5 | Серверные ограничения прямых запросов | реализовано | E3/E4/E5/E6/E2/E10:4 fresh synthetic роли; direct ACL and private import ownership; upload verified MIME/content/size; audit and request revisions. |
| 14.6 | File download права проверяются | реализовано | E3/E4/E5/E6/E2/E10:4 fresh synthetic роли; direct ACL and private import ownership; upload verified MIME/content/size; audit and request revisions. |
| 14.7 | Нет blanket полных прав всем | реализовано | E3/E4/E5/E6/E2/E10:4 fresh synthetic роли; direct ACL and private import ownership; upload verified MIME/content/size; audit and request revisions. |
| 14.8 | Не перенесены пользователи/пароли/CMTEH private config | реализовано | E3/E4/E5/E6/E2/E10:4 fresh synthetic роли; direct ACL and private import ownership; upload verified MIME/content/size; audit and request revisions. |
| 14.9 | Приватные файлы вне public | реализовано | E3/E4/E5/E6/E2/E10:4 fresh synthetic роли; direct ACL and private import ownership; upload verified MIME/content/size; audit and request revisions. |
| 14.10 | Тип/содержимое/размер загрузки проверяются | реализовано | E3/E4/E5/E6/E2/E10:4 fresh synthetic роли; direct ACL and private import ownership; upload verified MIME/content/size; audit and request revisions. |
| 14.11 | Автор и время изменений/публикаций/импортов/обработки | реализовано | E3/E4/E5/E6/E2/E10:4 fresh synthetic роли; direct ACL and private import ownership; upload verified MIME/content/size; audit and request revisions. |
| 14.12 | Реальная рабочая роль на целевом окружении | частично | NOT RUN / deferred: localhost synthetic editor/manager реально входили; hosting и реальные сотрудники вне разрешённого scope. |
| 15.1 | Содержимое без больших пустот сверху | реализовано | E2/E5/E8:1440x1000 screenshots; named archive/restore; import report; сохранение flash. Только targeted desktop осмотр, не полная device matrix. |
| 15.2 | После сохранения/preview виден результат | реализовано | E2/E5/E8:1440x1000 screenshots; named archive/restore; import report; сохранение flash. Только targeted desktop осмотр, не полная device matrix. |
| 15.3 | После проверки импорта сразу результаты | реализовано | E2/E5/E8:1440x1000 screenshots; named archive/restore; import report; сохранение flash. Только targeted desktop осмотр, не полная device matrix. |
| 15.4 | Без неоправданного дублирования навигации/кнопок | реализовано | E2/E5/E8:1440x1000 screenshots; named archive/restore; import report; сохранение flash. Только targeted desktop осмотр, не полная device matrix. |
| 15.5 | Опасные действия требуют named confirmation | реализовано | E2/E5/E8:1440x1000 screenshots; named archive/restore; import report; сохранение flash. Только targeted desktop осмотр, не полная device matrix. |
| 15.6 | Ошибки возле полей, введённое сохраняется | частично | E4/E5/E6: введённое сохраняется, public формы имеют field errors, ContentUi часть ошибок привязывает к полю; catalog.php validation выводит общий alert над формой, без сообщения у проблемного поля. |
| 15.7 | Фиксированные панели не закрывают кнопки/сообщения/поля | частично | E8 public-product.png: при viewport1440x1000 нижний cookie banner перекрывает область кнопок Доставка/Оплата/Гарантия. Это наблюдаемый исходный UI, а не регрессия доказанного сравнения с прежней витриной. |
| 15.8 | Пригодность длинных названий и большого каталога | частично | E2:7k product и1161 categories реально загружены, списки paginated. В product form related/analog/category native select содержат все тысячи опций без поиска (в отличие content.js для статей); desktop функционален, usability полного каталога ограничена. Не производился нагрузочный тест hosting. |
| 16.1 | Инвентаризация существующих сущностей/изображений/маршрутов/настроек | реализовано | E1/E4/E11/E12: inventory missing IDs0, orphans0,23459 image references/missing0; original IDs unchanged. Shipping status см. E12 ниже. |
| 16.2 | Old→new ID map | реализовано | E1/E4/E11/E12: inventory missing IDs0, orphans0,23459 image references/missing0; original IDs unchanged. Shipping status см. E12 ниже. |
| 16.3 | Изолированная миграция | реализовано | E1/E4/E11/E12: inventory missing IDs0, orphans0,23459 image references/missing0; original IDs unchanged. Shipping status см. E12 ниже. |
| 16.4 | Сверка counts/links/images/addresses | реализовано | E1/E4/E11/E12: inventory missing IDs0, orphans0,23459 image references/missing0; original IDs unchanged. Shipping status см. E12 ниже. |
| 16.5 | Повторная миграция без дубликатов | реализовано | E1/E4/E11/E12: inventory missing IDs0, orphans0,23459 image references/missing0; original IDs unchanged. Shipping status см. E12 ниже. |
| 16.6 | Старые корзины после IDs | реализовано | E1/E4/E11/E12: inventory missing IDs0, orphans0,23459 image references/missing0; original IDs unchanged. Shipping status см. E12 ниже. |
| 16.7 | Резервная копия и проверяемое восстановление | реализовано | E1/E4/E11/E12: inventory missing IDs0, orphans0,23459 image references/missing0; original IDs unchanged. Shipping status см. E12 ниже. |
| 16.8 | Оригинал и неизвестные записи не удаляются | реализовано | E1/E4/E11/E12: inventory missing IDs0, orphans0,23459 image references/missing0; original IDs unchanged. Shipping status см. E12 ниже. |
| 16.9 | Настройки/тексты CatPart, не demo/CMTEH client data | реализовано | E1/E4/E11/E12: inventory missing IDs0, orphans0,23459 image references/missing0; original IDs unchanged. Shipping status см. E12 ниже. |
| 16.10 | Code rollback сохраняет новые запросы | реализовано | E1/E4/E11/E12: inventory missing IDs0, orphans0,23459 image references/missing0; original IDs unchanged. Shipping status см. E12 ниже. |
| 17.1 | Телефон → все места | реализовано | E3–E8/E12: реально выполненные repository scenarios плюс собственный браузерный полный бизнес-путь; ограничение E12 до завершения отмечено ниже. |
| 17.2 | Блок главной → текст/изображение/порядок/URL | реализовано | E3–E8/E12: реально выполненные repository scenarios плюс собственный браузерный полный бизнес-путь; ограничение E12 до завершения отмечено ниже. |
| 17.3 | Создать товар → категория и поиск | реализовано | E3–E8/E12: реально выполненные repository scenarios плюс собственный браузерный полный бизнес-путь; ограничение E12 до завершения отмечено ниже. |
| 17.4 | Цена/наличие → витрина | реализовано | E3–E8/E12: реально выполненные repository scenarios плюс собственный браузерный полный бизнес-путь; ограничение E12 до завершения отмечено ниже. |
| 17.5 | Публикация статьи → rich text/images/SEO/sitemap | реализовано | E3–E8/E12: реально выполненные repository scenarios плюс собственный браузерный полный бизнес-путь; ограничение E12 до завершения отмечено ниже. |
| 17.6 | Черновик → editor only | реализовано | E3–E8/E12: реально выполненные repository scenarios плюс собственный браузерный полный бизнес-путь; ограничение E12 до завершения отмечено ниже. |
| 17.7 | Корзина → один заказ с правильными позициями | реализовано | E3–E8/E12: реально выполненные repository scenarios плюс собственный браузерный полный бизнес-путь; ограничение E12 до завершения отмечено ниже. |
| 17.8 | Replay после потери ответа → нет дубля | реализовано | E3–E8/E12: реально выполненные repository scenarios плюс собственный браузерный полный бизнес-путь; ограничение E12 до завершения отмечено ниже. |
| 17.9 | Почта отключена → durable record/error | реализовано | E3–E8/E12: реально выполненные repository scenarios плюс собственный браузерный полный бизнес-путь; ограничение E12 до завершения отмечено ниже. |
| 17.10 | Форма → нужный реестр | реализовано | E3–E8/E12: реально выполненные repository scenarios плюс собственный браузерный полный бизнес-путь; ограничение E12 до завершения отмечено ниже. |
| 17.11 | Карта настроена/очищена | реализовано | E3–E8/E12: реально выполненные repository scenarios плюс собственный браузерный полный бизнес-путь; ограничение E12 до завершения отмечено ниже. |
| 17.12 | Аналитика/meta правильно без дублей | реализовано | E3–E8/E12: реально выполненные repository scenarios плюс собственный браузерный полный бизнес-путь; ограничение E12 до завершения отмечено ниже. |
| 17.13 | Редактор/менеджер → разрешённые операции | реализовано | E3–E8/E12: реально выполненные repository scenarios плюс собственный браузерный полный бизнес-путь; ограничение E12 до завершения отмечено ниже. |
| 17.14 | Обновление пакета сохраняет данные/новые запросы | реализовано | E3–E8/E12: реально выполненные repository scenarios плюс собственный браузерный полный бизнес-путь; ограничение E12 до завершения отмечено ниже. |
| 17.15 | Реальные тестовая БД и браузер, короткие критические E2E | реализовано | E3–E8/E12: реально выполненные repository scenarios плюс собственный браузерный полный бизнес-путь; ограничение E12 до завершения отмечено ниже. |
| 17.16 | Проверка установленного пакета и разрешённых сценариев на целевом хостинге | частично | NOT RUN / deferred: владельцем разрешён только локальный стенд; установка package на собственный8097 реально сделана, это не hosting. |
| 18.1 | Рабочая единая админка, подключённая к витрине | реализовано | E8/E12/E13: admin-guide.md, admin-architecture.md, admin-reuse.md, admin-migration.md, zipped source revision; фактические пробелы перечислены отдельно, полный blanket-ready не заявляется. |
| 18.2 | Перенесённые каталог/статьи/содержимое | реализовано | E8/E12/E13: admin-guide.md, admin-architecture.md, admin-reuse.md, admin-migration.md, zipped source revision; фактические пробелы перечислены отдельно, полный blanket-ready не заявляется. |
| 18.3 | Инструкция владельцу где редактировать | реализовано | E8/E12/E13: admin-guide.md, admin-architecture.md, admin-reuse.md, admin-migration.md, zipped source revision; фактические пробелы перечислены отдельно, полный blanket-ready не заявляется. |
| 18.4 | Реестр подключённых функций/зависимостей OpenCart | реализовано | E8/E12/E13: admin-guide.md, admin-architecture.md, admin-reuse.md, admin-migration.md, zipped source revision; фактические пробелы перечислены отдельно, полный blanket-ready не заявляется. |
| 18.5 | Список будущих неинтегрированных возможностей | реализовано | E8/E12/E13: admin-guide.md, admin-architecture.md, admin-reuse.md, admin-migration.md, zipped source revision; фактические пробелы перечислены отдельно, полный blanket-ready не заявляется. |
| 18.6 | Фактические local проверки отдельно от hosting NOT RUN | реализовано | E8/E12/E13: admin-guide.md, admin-architecture.md, admin-reuse.md, admin-migration.md, zipped source revision; фактические пробелы перечислены отдельно, полный blanket-ready не заявляется. |
| 18.7 | Пакет/версия исходников/rollback procedure | реализовано | E8/E12/E13: admin-guide.md, admin-architecture.md, admin-reuse.md, admin-migration.md, zipped source revision; фактические пробелы перечислены отдельно, полный blanket-ready не заявляется. |
| 18.8 | Базовый scope без оплаты/резервов/мультисклада/кабинета/1С/рассылки | реализовано | E8/E12/E13: admin-guide.md, admin-architecture.md, admin-reuse.md, admin-migration.md, zipped source revision; фактические пробелы перечислены отдельно, полный blanket-ready не заявляется. |
| 18.9 | Критический переход admin → витрина → запрос → менеджер без PHP/SQL | реализовано | E8/E12/E13: admin-guide.md, admin-architecture.md, admin-reuse.md, admin-migration.md, zipped source revision; фактические пробелы перечислены отдельно, полный blanket-ready не заявляется. |

## Условное / отложенное / NOT RUN

- Упоминание готовой ветки/заготовки админки отменено дополнением владельца; её отсутствие не дефект.
- Публичные загрузки списков/файлов **не включены** (admin-guide). Условные требования «Если включается…» — отдельный quote, availability found/unfound, explicit add selected, original file manager — не объявлены обязательными и не оценены как отсутствующая базовая функция. Приватный **админский импорт** проверен отдельно.
- Production/hosting install, реальные рабочие сотрудники хостинга, реальная контрольная почта/inbox, подтверждение в кабинетах поисковиков — **NOT RUN**, запрещены текущей границей. Настройки meta не равны подтверждению кабинетами.
- Варианты оформления не создавались: проверен общий источник существующей витрины и admin. Полная визуальная пиксельная идентичность старому hosting не доказана; preserved source layout/targeted local screenshots.
- Все возможные toolbar-комбинации редактора, каждая бизнес-страница/каждая кнопка во всех ширинах и hosting load не проверялись. Реально browser проверен CKEditor/HTML с table/img/alt/caption, public forms и критический полный путь; остальное отмечено UI/DB evidence.
- Ничего не публиковалось, не отправлялось реально, owner stand8095 не затронут. Собственные G4synthetic fixtures намеренно оставлены в private приёмочной БД как воспроизводимое доказательство; приложение/исходная БД не изменены.

## Точные команды и результаты

Рабочая директория clone `D:/codex/cat-part/admin-acceptance-20261113`, кроме явно указанного installed target. Секреты не являются аргументами команд.

```powershell
git status --short
git rev-parse HEAD
python tools/admin-dev.py --help
python tools/admin-dev.py setup --source D:/codex/cat-part/cat-part --runtime D:/codex/cat-part/admin-acceptance-runtime-20261113 --upstream D:/codex/temp/cmteh-opencart-cache/opencart-4.1.0.4.zip --port 8096
$env:NODE_PATH=Join-Path $env:USERPROFILE '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'
node D:/codex/cat-part/admin-acceptance-runtime-20261113/ui-probe.cjs
python tools/test-admin-integration.py --runtime D:/codex/cat-part/admin-acceptance-runtime-20261113 --group foundation --group catalog --group content --group requests --group browser --group seo
node D:/codex/cat-part/admin-acceptance-runtime-20261113/g4-browser.cjs
node D:/codex/cat-part/admin-acceptance-runtime-20261113/g4-edge.cjs
node D:/codex/cat-part/admin-acceptance-runtime-20261113/native-users-probe.cjs
$env:CATPART_ADMIN_RUNTIME='D:/codex/cat-part/admin-acceptance-runtime-20261113'
& D:/codex/cat-part/cat-part/local-dev/bin/php/php.exe -c D:/codex/cat-part/admin-acceptance-runtime-20261113/php.ini tools/admin-data.php inventory
python tools/admin-package.py build --output D:/codex/cat-part/admin-acceptance-runtime-20261113/catpart-admin.zip
python tools/admin-package.py verify --package D:/codex/cat-part/admin-acceptance-runtime-20261113/catpart-admin.zip
python tools/admin-package.py install --package D:/codex/cat-part/admin-acceptance-runtime-20261113/catpart-admin.zip --target D:/codex/cat-part/admin-acceptance-installed-20261113 --source D:/codex/cat-part/cat-part --port 8097 --apply
```

Все перечисленные команды exit0. `g4-edge` exit0 — означает успешную фиксацию **несоответствий**, а не PASS этих требований. Затем из `D:/codex/cat-part/admin-acceptance-installed-20261113`:

```powershell
python tools/test-admin-integration.py --runtime D:/codex/cat-part/admin-acceptance-installed-20261113/local-dev/admin-runtime --package D:/codex/cat-part/admin-acceptance-runtime-20261113/catpart-admin.zip --group shipping --group package
```

Итог этой последней команды и завершение серверов добавляются ниже по факту. Никакой недоступный/незавершённый check не представлен как PASS.


## Фактическое завершение E12 и изоляция

Shipping/package command **exit0**. Реально выполнено: два update; сохранены product edits/identity/image metadata/public phone/native config/settings/unknown table sentinel/new native order+lead; после дополнительных заявок code-only rollback; прежняя HTTP cookie корзины сохранила исходные product IDs/SKU и qty2/150; private streaming backup восстановлен в новую собственную random schema, все таблицы и строки совпали по digest, native orders count совпал. Shipping вывел группы1+10+24+1 checks PASS и exact fixture cleanup. Итог inventory установленного стенда снова7134products/1161categories/5posts, missingIDs0/orphans0/image missing0.

Package group: два byte-identical ZIP, allowlist без private/config/runtime/DB dump, pinned upstream и все175payloadhashes, испорченный ZIP отвергнут — PASS. ZIP SHA256 совпал с E12. Предупреждение zipfile о duplicate index.php было намеренным tamper fixture, не ошибкой выпуска.

После проверки выполнены только собственные безопасные stop:

```powershell
# из clean clone
python tools/admin-dev.py stop --runtime D:/codex/cat-part/admin-acceptance-runtime-20261113
# из собственного installed target
python tools/admin-dev.py stop --runtime D:/codex/cat-part/admin-acceptance-installed-20261113/local-dev/admin-runtime
```

Обе команды exit0; owner8095 и общий MariaDB не останавливались. Итог `git status --short` clean; HEAD всё тот же a5bc99a5d738f4ae76d9c588b7cf58394e8001c6. Воспроизведение: `python tools/admin-dev.py start --runtime D:/codex/cat-part/admin-acceptance-runtime-20261113` из clean clone; credentials только в собственном private/accounts.json.

Время: первый PHPbootstrap наблюдался15:13:53MSK, собственный webserver создан15:16:01MSK; все продуктовые/packaging/shipping проверки завершены не позднее15:25:42MSK. Между ними выполнялась браузерная/DB/HTTP приёмка и подготовка отчёта; серверное время не объявляется временем всей задачи. Cleanupstop около15:26MSK, отчётный хвост отдельно. Полное request-to-result время всего задания измеряет основной исполнитель, поскольку приёмка является вложенной частью его работы.


# G4 — узкое независимое закрытие прежних local partial (2026-11-13)

Проверяемая версия clean clone: **4595afbaa8c74291abe85c34f5d19238185341f0**; `git status --short` пуст до и после. Предыдущая приёмка и её фактические failures выше сохранены без изменения. Это **не второй полный проход235пунктов** и не повтор неизменённых shipping/full suites. Прочитан только обычный docs/admin-architecture.md и относящийся affected code/tests; запрещённые .autopilot/spec/manifest/tickets не открывались; skills/subagents не вызывались; code/tests не правились.

Собственный runtime refreshed, затем реально работал на8096; отдельные schema/data из первой приёмки сохранены.8097 не запускался, owner8095 не затрагивался.Production/realmail по-прежнему не проверялись.

**Итог узкого closure:**11 прежних локальных partial-строк проверены/закрыты, оставшихся локальных несоответствий из этого набора не обнаружено.3строки10.9/14.12/17.16 остаются намеренно **NOT RUN/deferred** (hosting, реальные сотрудники и настоящая почта) согласно последнему пределу владельца. Это не local drift. Арифметическое сведение с первой таблицей:221ранее matched+11закрытых=232из235,3NOTRUN. Такая арифметика **не означает повторную проверку остальных221пунктов на новом SHA**.

| Прежний ID | Было | Узкий результат | Личное наблюдение и дополнительное evidence |
|---|---|---|---|
| 1.9 | частично | закрыто в доступных документах | docs/admin-architecture.md теперь содержит все4колонки «Сущность / Источник / Редактор / Потребители» с каталогом, статьями, страницами, настройками, orders/leads/outbox/ACL. Root отдельно предоставил факт: tableSHA256 63dfc5724e099b65fc6749898de832e105d04db9eb4f8ef0c03f13e239a20291 совпадает с plancommit3f2f046;3f предшествует первому code002bb72. Это атрибутированное историческое evidence root, запрещённый план независимо не открывался; прежний вывод был discoverability gap ownerdocs, а не доказанное отсутствие preimplementationtable. |
| 2.10 | частично | закрыто | В живом «Обслуживание» появились фиксированные PHP/OpenCart errorlogs; лично добавлен только синтетический маркер собственного PHPlog, прочитан boundedtail, HTML escaped, password/token скрыты, arbitrary filequery проигнорирован, anonymous не получает лог.35affectedchecks также проверили native actualerrorlog и editor/manager/reader403. Только чтение, нет editor/download/pathchooser. |
| 2.11 | частично | закрыто | Лично открыты native Users/UserGroups/AddUser: «Сотрудники», «Роли и права», «Добавить сотрудника», прежних EnglishUsername/FirstName/UserGroup/Password нет. closure-users.png. |
| 3.1 | частично | закрыт прежний конкретный пробел | Обычный экран Содержимое→Тексты карточки товара;5полей сохранены через UI и видны на реальной /tovar/g4-product-13. Не blanket-переаудит всех бизнесовых данных. closure-product-copy.png. |
| 3.14 | частично | закрыт прежний конкретный пробел | Обычный редактор страниц Общий каталог/Поиск: Title/Description/H1/canonical/OG сохранены; реальный HTML/title/H1/meta изменились. CategoryH1 не заменён общим catalogH1; query<unsafe> не создал DOMelement; searchnoindex сохранился даже при снятом editorcheckbox.35checks дополнительно покрыли meta/escape cases. |
| 3.15 | частично | закрыт прежний конкретный пробел | Публичные pricehint,3USP,deliverycopy действительно читают сохранённые G4closure значения из normaleditor; ранее зашитые бизнесовые фразы больше не остаются единственным источником этих consumers. |
| 4.11 | частично | закрыто | Тот же исходный exactcross009-G4-CROSS теперь находит тот же canonicalтовар публично и в admin. Лично загружен CSV и явно сопоставлены SKU/name; preview показывает crosserror «Укажите канонический ID», без silentrename/newproduct. Sourceaffected DBcheck проверил2ambiguouscandidates; canonicalSKU по кроссу автоматически не подменяется. closure-cross-import.png. |
| 4.21 | частично | закрыто | Через categoryeditor сохранён /assets/img/benefits/stock.webp; реальная categorypage содержит img.cp-category-image с этимsrc. После очистки field и save элемент исчез. closure-category-image.png. |
| 15.6 | частично | закрыто | В producteditor введены новое name и BADPRICE; после servervalidation возле price есть cp-price-error, поле aria-describedby/invalid связано с ошибкой, оба введённых значения сохранены.35checks отдельно покрыли name/SKU/slug/currency/category/media/parentcycle errors. closure-field-error.png. |
| 15.7 | частично | закрыто | Лично до принятия cookie consent deliverybutton доступна через elementFromPoint и реально нажата; после accept+reload consent сохранён. Для существующего G4товара с единственной panel tab не создаётся, directdeliveryanchor работает. closure-cookie.png. |
| 15.8 | частично | закрыт прежний конкретный пробел | Лично отфильтрованы и выбраны4fullcatalogselector: primarycategory/additionalcategory/related/analog; при несовпадающем фильтре выбранная опция остаётся selected+visible, полный список возвращается при очистке.35checks дополнительно подтвердили savepersistence и>1000кандидатов. Hostingload не проверялся и не заявляется. |

## Команды узкого closure и фактические результаты

Все appкоманды из `D:/codex/cat-part/admin-acceptance-20261113`:

```powershell
git status --short
git rev-parse HEAD
python tools/admin-dev.py refresh --runtime D:/codex/cat-part/admin-acceptance-runtime-20261113
$env:NODE_PATH=Join-Path $env:USERPROFILE '.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules'
python tools/test-admin-integration.py --runtime D:/codex/cat-part/admin-acceptance-runtime-20261113 --group acceptance
```

Refresh exit0, migrations+Ready8096. Acceptance exit0: **35HTTP/DBchecks,0failed**, реальный cachedPlaywrightbrowser для selectors/cookie; exactownedfixtures cleanupPASS. Никакой source/syntax substitute.

Личные независимые browserchecks не сводились к sourcecheckset. Собственные scripts вне clone:

```powershell
node D:/codex/cat-part/admin-acceptance-runtime-20261113/closure-browser.cjs
node D:/codex/cat-part/admin-acceptance-runtime-20261113/closure-browser-tail.cjs
node D:/codex/cat-part/admin-acceptance-runtime-20261113/closure-browser-final.cjs
```

Первые2scripts дали по3и4успешных наблюдения, затем обнаружили ошибки собственного harness, **не PASS всей команды**: в первом не были выбраны mappingcolumnsCSV, во втором ожидался UI-tab у товара с единственнойpanel. При подготовке последнего короткого хвоста также обнаружена Windowsdefaultencoding-порча Cyrilliclocator; script восстановлен явным UTF8. Эти commandfailures не скрыты и не лечились изменением приложения/guards. Повторены только ещё не закрытые checkpoints: CSVmapping затем4checks; cookie/logхвост после исправленияUTF8 **exit0,3checksPASS**. Совокупные **10уникальных личных наблюдений** записаны в `private/closure-browser-results.json`; это не десять новых требований и не полный235rerun. Read-onlylog syntheticappend восстановлен в точные прежние bytes в finally; неизвестные fixtures не удалялись. Новые собственные normaledit/importpreviewproof остались только в своей runtimeDB/private.

После проверок:

```powershell
python tools/admin-dev.py stop --runtime D:/codex/cat-part/admin-acceptance-runtime-20261113
git status --short
git rev-parse HEAD
Get-NetTCPConnection -LocalPort 8096,8097 -State Listen -ErrorAction SilentlyContinue
```

Stop exit0; statusclean; HEAD4595afbaa8c74291abe85c34f5d19238185341f0. TCPprobe не нашёл слушателей8096/8097 (PowerShellexit1 без вывода означает отсутствие совпадений, не appcheckfailure). Owner8095 не проверялся/не трогался.

Время ownwebserver16:08:13–16:13:47MSK ≈5мин34с; внутри этого интервала DB/HTTP/browserchecks и narrowharnessrecovery. Reportappend — отдельный хвост после остановки. Полное request-to-result time rootтаска остаётся у основного исполнителя.
