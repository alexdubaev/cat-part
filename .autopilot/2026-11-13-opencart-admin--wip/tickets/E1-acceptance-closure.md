# E1 — Закрытие функциональных расхождений слепой приёмки

**Требования:** R011, R021, R022, R029, R033, R038, R040, R051, R052, R054, R055, R190, R192, R195
**Зависит от:** —
**Зона:** `opencart/extension/catpart/system` · `opencart/extension/catpart/admin` · `local-dev/site/app/Views/catalog` · `local-dev/site/app/Controllers/CatalogController.php` · `local-dev/site/app/Controllers/SearchController.php` · `local-dev/site/app/Views/search/index.php` · `local-dev/site/app/Support/helpers.php` · `local-dev/site/app/Views/layouts/main.php` · `local-dev/site/assets/css/site.css` · `local-dev/site/assets/js/app.js` · `tests/admin-acceptance.php` · `tools/test-admin-acceptance.py` · `tools/test-admin-acceptance.cjs` · `tools/test-admin-integration.py` · `docs/admin-guide.md`
**Волна:** 5
**Ревью:** нет — функциональное закрытие G4, без повторного whole-change code review; реальные narrow closure checks
**Модель:** сильная

## Источник
Слепая проверка исходного брифа: D:/codex/cat-part/admin-stand/reports/blind-acceptance.md.221из235подтверждены,14partial.3изpartial—разрешённые hostingNOTRUN; таблица источников уже добавлена root в docs/admin-architecture.md (первоначально была в плане до разработки). Оставшиеся реальные local gaps перечислены ниже; ничего нового сверх ТЗ.

## Из брифа, дословно
> «Все бизнесовые данные и содержимое сайта должны управляться из админки.»
> «Русские понятные названия и оформление CatPart.»
> «Изменение через любой разрешённый редактор отражается на витрине.»
> «Правила поиска и сопоставления должны быть едиными для сайта, админки и импорта.»
> «Ошибки находятся возле соответствующих полей; введённые данные сохраняются.»
> «Фиксированные панели не закрывают кнопки, сообщения и поля.»
> «Админка остаётся пригодной для работы с длинными названиями и большим каталогом.»

## Критерии приёмки — все обязательны
- [ ] G4§3.1/3.15: price hint/USP/delivery business copy product.php реально видны, но literals. Перенести существующий точный текст в управляемые обычными полями записи текущего Content источника, подключить publicconsumer. Не редизайн/не придумать новые обещания/не оставлять второй независимый copy override.
- [ ] G4§4.21: изменённая category image сохраняется, но не выводится /catalog/{slug}. Подключить изображение как компактный условный элемент существующей category header/description (без большой hero-пустоты и без возврата иконок в sidebar, ранее убранных владельцем). Пустое изображение не оставляет блок/пустоту; сохранять исходную тему.
- [ ] G4§4.11: public /poisk находит cross009-G4-CROSS, admincatalogq даёт0. Согласовать точный нормализованный cross lookup; импорт не должен молча создавать дубликат при найденном cross. При неоднозначности показать строку/причину; не менять основной SKU автоматически только из-за cross совпадения. Нормализация/leadingzeros/rawSKU сохраняются.
- [ ] G4§2.11: native Users/UserGroups/AddUser имеют английские labels. Русифицировать эти рабочие native страницы расширением/языковым hook, сохраняя native field values/route permission identifiers/серверные права/auth. Core не менять; не менять логику/доступы ради перевода.
- [ ] G4§3.14: общий /catalog и /poisk не имеют редактора SEO/H1. Добавить обычные поля title/description/H1/canonical/noindex/OG и реальные HTMLconsumers, сохранить действующие значения/политику по умолчанию. Не подменять category/product SEO общимcatalog значением. Query в поиске безопасно выводится и функция поиска сохраняется.
- [ ] G4§15.6: catalog.php выдаёт один alert над большой формой. Привязать реальные field validation errors к соответствующим полям/группам (name/sku/slug/price/currency/parent/media и др.), сохранять введённые значения; инфраструктурные/общие ошибки могут оставаться summary. Не ослаблять validators; при необходимости compatible typed exception с field key.
- [ ] G4§15.8: product form related/analog/category selects имеют тысячи опций без поиска. Добавить рабочий поиск/фильтрацию по названию/артикулу, сохранить выбранные значения и доступ ко всему набору, без silent cap и без rawID/JSON обязательного ввода. По возможности использовать уже имеющийся pattern content.js; no new paid/framework deps or ACL relaxation.
- [ ] G4§15.7: cookie banner перекрывает Delivery/Payment/Warranty buttons на1440x1000. Устранить перекрытие, сохранив copy/theme/поведение согласия; не устраивать редизайн. Реальный browser-check без предварительного dismiss.
- [ ] G4§2.10: maintenance содержит outbox/audit, но нет общего error log. Добавить безопасный read-only viewer фактических собственных PHP/OpenCart logs (лишь фиксированные whitelist источники/ограниченный tail, вне public). Только admin maintenance permission, direct unauthorized403, HTMLescape и редактирование/скачивание произвольных путей запрещены. Session user_token/пароли/Authorization и подобные значения редактировать/скрывать при отображении; не выводить private account values. Пустой/недоступный журнал отображается честно, не fabricatedPASS. Не добавлять опасные команды обслуживания.
- [ ] Реальные narrow DB/HTTP/browser regression tests для этих gaps, уникальные syntheticfixtures+точная cleanup; подключить groupacceptance к master. Не повторять весь205 suite; новые методы не меняют auth/session/ACL/payment/data recovery contracts.

## Границы и запуск
Own permanent stand: D:/codex/cat-part/admin-stand . URL: http://127.0.0.1:8095/ . Own runtime: D:/codex/cat-part/admin-stand/local-dev/admin-runtime . DB: catpart_admin_fe52ecf86390. Исходные CatPart/CMTEH/БД не менять. Clean acceptance8096/8097 отдельные папки checker не трогать. Кодовая база в этой branch, не в source snapshot. Изменения применять на own8095 через проверенный рабочий package build/verify/update, сохранить manifest соответствие. Никаких commit/push, autopilotартефактов/архитектурного doc не править. Только условленный scope.
