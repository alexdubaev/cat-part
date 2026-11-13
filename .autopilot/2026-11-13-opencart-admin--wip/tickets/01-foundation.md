# 01 — Стенд, одна БД и настоящая админка OpenCart

**Требования:** R001, R002, R003, R004, R005, R006, R007, R008, R009, R010, R011, R012, R013, R014, R015, R016, R017, R018, R019, R020, R021, R022, R023, R024, R025, R026, R027, R028, R178, R179, R180, R181, R182, R183, R184, R185, R186, R187, G01, G02, G03, G04, G05
**Зависит от:** —
**Зона:** `opencart/extension/catpart/system/Factory.php` · `opencart/extension/catpart/system/AdminController.php` · `opencart/extension/catpart/admin/controller/event` · `tools/admin-dev.py` · `local-dev/router.php` · `local-dev/site/app/bootstrap.php` · `local-dev/site/app/Core/Database.php`
**Волна:** 1
**Ревью:** нет — один итоговый независимый review по owner policy
**Модель:** сильная

## Что должно заработать

Стенд, одна БД и настоящая админка OpenCart. Реализовать связанные разделы spec полностью; interfaces.md — обязательные границы.

## Из брифа, дословно

> «Главное требование: **админка управляет действующим сайтом CatPart, его товарами, содержимым, заказами и обращениями через единый источник данных.** Изменение в админке должно давать проверяемый результат на витрине.»
> «Основание — текущая `main` проекта CatPart и опыт СМ ТЕХНО. Сейчас CatPart использует собственную PHP-витрину, а отправка корзины реализована как запрос менеджеру без онлайн-оплаты. Эту бизнес-логику необходимо сохранить при подключении OpenCart. [Текущие маршруты](https://github.com/alexdubaev/cat-part/blob/a79b43453f70ba8f564e3841fe4081050b9381a7/local-dev/site/app/routes.php), [обработка корзины](https://github.com/alexdubaev/cat-part/blob/a79b43453f70ba8f564e3841fe4081050b9381a7/local-dev/site/app/Controllers/CartController.php).»
> «Использовать одну установку OpenCart и одну административную панель.»

## Разделы спецификации

Все требования из заголовка, spec.md и interfaces.md область foundation.

## Критерии приёмки

- [ ] Строгий loopback guard
- [ ] безопасная копия только whitelist каталога
- [ ] native login/roles/ACL
- [ ] no demo catalog
- [ ] native core unchanged
- [ ] тест отказа remote DB и access/modify
- [ ] запуск 8095/собственная task DB. Зависимости CMTEH и upstream SHA проверены. Не писать доменную функциональность других тасков.
