# F1 — Исправления после код-ревью
**Требования:** G06, G09, R07, R08
**Зависит от:** —
**Зона:** `local-dev/site/app/` · `local-dev/site/assets/` · `deliverables/brandbook/` · `tools/`
**Волна:** 2
**Ревью:** да — ремонт ветки
**Модель:** сильная

## Что должно заработать
Общие настройки почты, счётчик и безопасная отправка заказа, приватность тестовых данных. Завершённые проверенные screenshots и брендбук с реальными иллюстрациями.

## Из брифа, дословно
> «все заявки обращения с форм обратной связи, заказы из корзины, должны идти на эту почту»
> «Да, доделать корзину и отправку заказа»

## Разделы спецификации
Почта, Корзина, Статьи, Проверка и границы.

## Критерии приёмки — замечания ревью
- [ ] P1 mail-routing: Lead forms still use stale info@cat-part.ru via mail.notify; fallback metadata also exposes the old address. LeadController reads config.mail.notify; config.php retains info@cat-part.ru and /kontakty HTML contains the old address twice. Deployment docs require manual unversioned config edits.
- [ ] P1 privacy: Local order captures containing names, phones, emails, and cart contents are written below the document root. Production .htaccess allows existing files/directories; only app/ is denied. Apache could serve /storage/order-captures/<timestamp-random>.txt.
- [ ] P2 cart-state: Cart badge expects entries shaped as {qty: ...}, while CartController stores product_id => integer, so the header always shows zero.
- [ ] P2 spam-protection: Checkout has no honeypot, rate limit, or throttle; only an exact-body duplicate hash is checked. An attacker with a CSRF token can submit unlimited unique names/phones and trigger mail() or local captures.
- [ ] P2 content-accuracy: Product cards claim “Отгрузка со склада”, an unconfirmed warehouse fact explicitly forbidden by the spec.
- [ ] P2 idempotency: last_order_hash is retained indefinitely, suppressing every later legitimate identical order in the same session. After a successful checkout, re-adding the same product and submitting the same contact data returns “Этот запрос уже принят” without sending a new order.

## Дополнительные проверяемые условия визуальной приёмки
- [ ] На главной вернуть последние3 статьи в один ряд, все4 доступны в /stati и имеют обложки; screenshot заставляет lazy images загрузиться и принимает cookie banner перед capture.
- [ ] Брендбук PDF заново собран с реальными готовыми26 assets, есть PNG-превью страниц и контактный лист иконок для проверки.

## Ограничения
Не читать/коммитить секреты config.php/config.local.php. Подключить versioned безопасную branding.php так, чтобы App::config и cfg видели согласованные данные; локальная отправка реальных писем отключена. Production bootstrap не должен получать local-only guard: точная инструкция внесения safe branding overlay в исходный production bootstrap, без переноса локального runtime. Тестовый capture хранить за пределами docroot и не выдавать raw PII публично. Не чистить чужие файлы/базы. Старый локальный task storage тестовые файлы можно переместить только после проверки путей внутри local-dev. Только bounded исправления.
