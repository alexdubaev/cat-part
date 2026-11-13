# 03 — Управляемое содержимое, редактор статей и настройки

**Требования:** R029, R030, R031, R032, R033, R034, R035, R036, R037, R038, R039, R040, R041, R069, R070, R071, R072, R073, R074, R075, R076, R077, R078, R079, R080, R081, R082, R083, R084, R085, R086, R087, R139, R140, R141, R142, R143, R144, R145, R146, R147, R148, R149, R150, R151, R152, R153, R154, R155, R156, R157, R158, R159, R160, R161, R162, R163, R164, R165, R166, R167, R168, R169, R170, R171, R172, R173, R174, R175, R176, R177, R188, R189, R190, R191, R192, R193, R194, R195
**Зависит от:** 01
**Зона:** `opencart/extension/catpart/system/Content.php` · `opencart/extension/catpart/admin/controller/module/content.php` · `opencart/extension/catpart/admin/controller/module/settings.php` · `tests/admin-content.php` · `local-dev/site/app/Core/App.php` · `local-dev/site/app/Models/Post.php` · `local-dev/site/app/Views/layouts` · `local-dev/site/app/Views/home.php` · `local-dev/site/app/Views/pages` · `local-dev/site/app/Views/blog` · `local-dev/site/app/Views/partials/header.php` · `local-dev/site/app/Views/partials/footer.php` · `local-dev/site/app/Controllers/SeoController.php` · `local-dev/site/app/routes.php` · `local-dev/site/app/Support/helpers.php`
**Волна:** 2
**Ревью:** нет — один итоговый независимый review по owner policy
**Модель:** сильная

## Что должно заработать

Управляемое содержимое, редактор статей и настройки. Реализовать связанные разделы spec полностью; interfaces.md — обязательные границы.

## Из брифа, дословно

> «Все бизнесовые данные и содержимое сайта должны управляться из админки.»
> «\| Общие настройки \| Название сайта и компании, логотипы, favicon, контакты, реквизиты \|»
> «\| Шапка \| Навигация, контакты, подписи и адреса ссылок \|»

## Разделы спецификации

Все требования из заголовка, spec.md и interfaces.md область content.

## Критерии приёмки

- [ ] Всё бизнес-содержимое текущего CatPart переносится в DB и редактируется полями. Article rich editor sanitize/Word/cover/alt/table/author/date/status/revisions restore/related/authorized preview
- [ ] consistent UTC/list/detail/sitemap
- [ ] settings phone all surfaces/map secure set-clear/analytics IDs preserve Metrika options/local none/verification existing
- [ ] menus enable/order/manual products
- [ ] SEO real HTML+301. Чужие бизнес-тексты не переносить.
