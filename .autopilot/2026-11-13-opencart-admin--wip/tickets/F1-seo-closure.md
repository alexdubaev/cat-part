# F1 — Исправление трёх подтверждённых ошибок SEO

**Требования:** R050, R078, R173, R174, R175, R176, R177
**Зависит от:** —
**Зона:** `local-dev/site/app/Models/Product.php` · `local-dev/site/app/Models/Category.php` · `local-dev/site/app/Controllers/SeoController.php` · `local-dev/site/app/routes.php` · `opencart/extension/catpart/system/Catalog.php` · `opencart/extension/catpart/system/CatalogImport.php` · `opencart/extension/catpart/system/Content.php` · `tests/admin-seo.php` · `tools/test-admin-seo.py` · `tools/test-admin-integration.py`
**Волна:** 4
**Ревью:** нет — одно независимое whole-change ревью выполнено; narrow closure checks по owner policy
**Модель:** сильная

## Что должно заработать
Закрыть ровно три подтверждённых замечания независимого ревью. Не менять другие функции, роли, contracts и архитектуру. Проверять на собственном permanent stand8095 и task DB, исходные сайты не трогать.

## Из брифа, дословно
> «проверять занятость и зарезервированные маршруты;»
> «создавать прямой 301 со старого адреса;»
> «SEO-поля должны реально выводиться в HTML. Sitemap включает только опубликованные доступные страницы.»

## Условия ревью, дословно
1. FIX NOW: Spec §13 · local-dev/site/app/Models/Product.php:368, Category.php:208 · Checked noindex products/categories remain in sitemap. Catalog stores JSON boolean true; MariaDB evaluates current COALESCE(JSON_EXTRACT(...),0)<>1 as true. Confirmed read-only on own stand. Exclude boolean true and use the same indexable predicate for sitemap chunk count (SeoController.php:207), preventing links to empty chunks.
2. FIX NOW: Spec §13 · opencart/extension/catpart/system/Catalog.php:65, Content.php:119 · Rename A alpha→beta, then create B with slug alpha: creation succeeds, but /tovar/alpha still redirects to A. Applies to products/categories/brands/articles. Reject historical path conflicts or explicitly reconcile redirects atomically; every accepted record must open at its saved URL. Reproduced using rollback-only synthetic fixtures.
3. FIX NOW: Spec §13 · local-dev/site/app/routes.php:22–24 · Catalog accepts Cyrillic slugs, stores decoded historical paths, but redirect lookup uses percent-encoded REQUEST_URI. After renaming a Cyrillic slug, a normal browser request to its previous URL misses the redirect and returns404. Normalize the lookup consistently with Router while retaining path safety. Confirmed encoded lookup misses and decoded lookup matches in rollback-only fixture.

## Критерии приёмки
- [ ] JSON true noindex исключает продукт/категорию из реального sitemap; индексируемые остаются; product chunk count использует тот же фильтр (не портить общий countActive каталога/главной).
- [ ] Все четыре типа не позволяют новому/чужому объекту занять исторический redirect path; собственная осмысленная смена назад не создаёт cycle/цепочку. Импорт preview/apply не скрывает конфликт.
- [ ] Кириллический старый адрес в обычном percent-encoded browser request301прямо на текущий; encoded separator/null/traversal не расширяют разрешённые пути, нормализация совпадает с Router.
- [ ] Реальные DB+HTTP regression tests красные до правки и зелёные после; уникальные fixture IDs/slugs и точная cleanup, own runtime selectors.
- [ ] Тесты F1 подключены к master check entry; изменения существующей функциональности/ненужные broad reruns отсутствуют.

## Разделы спецификации
Spec§13 и interfaces.md. Один bounded fix pass, затем root узкий closure check; второй whole-change review не нужен по owner policy.
