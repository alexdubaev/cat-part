# 02 — Редактирование каталога и пошаговый CSV/XLSX импорт

**Требования:** R042, R043, R044, R045, R046, R047, R048, R049, R050, R051, R052, R053, R054, R055, R056, R057, R058, R059, R060, R061, R062, R063, R064, R065, R066, R067, R068
**Зависит от:** 01
**Зона:** `opencart/extension/catpart/system/Catalog.php` · `opencart/extension/catpart/system/CatalogImport.php` · `opencart/extension/catpart/admin/controller/module/catalog.php` · `opencart/extension/catpart/admin/controller/module/imports.php` · `tests/admin-catalog.php` · `local-dev/site/app/Models/Product.php` · `local-dev/site/app/Models/Category.php` · `local-dev/site/app/Views/product`
**Волна:** 2
**Ревью:** нет — один итоговый независимый review по owner policy
**Модель:** сильная

## Что должно заработать

Редактирование каталога и пошаговый CSV/XLSX импорт. Реализовать связанные разделы spec полностью; interfaces.md — обязательные границы.

## Из брифа, дословно

> «название, артикул, OEM/кросс-номера;»
> «бренд, основную и дополнительные категории;»
> «краткое и полное описание;»

## Разделы спецификации

Все требования из заголовка, spec.md и interfaces.md область catalog.

## Критерии приёмки

- [ ] Товар/категория/бренд/характеристики/галерея/main/alt и дополнительные категории видны витрине
- [ ] path cycles/ref integrity
- [ ] строковые SKU
- [ ] импорт mapping/preview/apply/повтор/строки ошибок/не терять неуказанные поля
- [ ] >100 строк
- [ ] новые slug direct301
- [ ] real DB tests.
