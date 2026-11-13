# 05 — Проверенный установочный пакет, обновление, инструкция и сверка

**Требования:** R196, R197, R198, R199, R200, R201, R202, R203, R204, R205, R206, R207, R208, R209, R210, R211, R212, R213, R214, R215, R216, R217, R218, R219, R220, R221, R222, R223, R224, R225, R226, R227, R228, R229
**Зависит от:** 02, 03, 04
**Зона:** `opencart/packaging` · `tools/test-admin-integration.py` · `docs/admin-guide.md` · `docs/admin-architecture.md` · `docs/admin-migration.md` · `docs/admin-reuse.md`
**Волна:** 3
**Ревью:** нет — один итоговый независимый review по owner policy
**Модель:** сильная

## Что должно заработать

Проверенный установочный пакет, обновление, инструкция и сверка. Реализовать связанные разделы spec полностью; interfaces.md — обязательные границы.

## Из брифа, дословно

> «Инвентаризацию существующих товаров, категорий, статей, изображений, маршрутов и настроек.»
> «Таблицу соответствия старых и новых идентификаторов.»
> «Перенос данных на изолированный стенд.»

## Разделы спецификации

Все требования из заголовка, spec.md и interfaces.md область package.

## Критерии приёмки

- [ ] deterministic package/source hash/no secrets/core pin
- [ ] update/migration twice preserves edits/new orders
- [ ] backup restore in own test schema and old-cart identity check
- [ ] clean clone run+critical browser scenarios
- [ ] owner guide/connected-functions matrix/remaining optional features/actual check evidence
- [ ] no hosting actions.
