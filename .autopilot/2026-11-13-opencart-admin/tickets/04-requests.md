# 04 — Надёжная корзина, заказы, обращения и очередь уведомлений

**Требования:** R088, R089, R090, R091, R092, R093, R094, R095, R096, R097, R098, R099, R100, R101, R102, R103, R104, R105, R106, R107, R108, R109, R110, R111, R112, R113, R114, R115, R116, R117, R118, R119, R120, R121, R122, R123, R124, R125, R126, R127, R128, R129, R130, R131, R132, R133, R134, R135, R136, R138
**Зависит от:** 01
**Зона:** `opencart/extension/catpart/system/Requests.php` · `opencart/extension/catpart/system/Outbox.php` · `opencart/extension/catpart/admin/controller/module/orders.php` · `opencart/extension/catpart/admin/controller/module/leads.php` · `opencart/extension/catpart/admin/controller/module/dashboard.php` · `opencart/extension/catpart/admin/controller/module/maintenance.php` · `tests/admin-requests.php` · `tools/send-admin-notifications.php` · `local-dev/site/app/Controllers/CartController.php` · `local-dev/site/app/Controllers/LeadController.php` · `local-dev/site/app/Views/cart` · `local-dev/site/app/Views/partials/leadform.php` · `tools/test-checkout.py` · `tools/checkout-test-router.php`
**Волна:** 2
**Ревью:** нет — один итоговый независимый review по owner policy
**Модель:** сильная

## Что должно заработать

Надёжная корзина, заказы, обращения и очередь уведомлений. Реализовать связанные разделы spec полностью; interfaces.md — обязательные границы.

## Из брифа, дословно

> «Корзина должна работать с реальными товарами общего каталога.»
> «добавление из карточки, каталога и поиска;»
> «изменение количества;»

## Разделы спецификации

Все требования из заголовка, spec.md и interfaces.md область requests.

## Критерии приёмки

- [ ] validation→native order or lead+outbox commit→confirm
- [ ] immutable snapshots/manager states/history/repricing
- [ ] persistent idempotency and replay after lost response/concurrent
- [ ] qty explicit bounds no clamp
- [ ] unavailable item no silent loss
- [ ] mail fault persists and admin shows/retry
- [ ] private capture/no real mail
- [ ] no stock subtraction/reserve/payment
- [ ] roles server ACL
- [ ] reports actual no revenue
- [ ] real DB tests.
