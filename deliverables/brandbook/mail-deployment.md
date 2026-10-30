# Перенос общих изменений на хостинг

Публичная почта и получатель всех форм: `info@cmteh.ru`. Безопасные факты компании находятся в `app/Config/branding.php`. Обновлённый `App::boot()` применяет их поверх старой конфигурации: `site.email`, компания, телефон, адрес, `mail.notify`. Поэтому `App::config()` и `cfg()` видят одинаковые данные, включая метаданные и текст при ошибке почты. Секретные `config.php` и `config.local.php` не переносятся и не коммитятся.

Перенести versioned PHP-контроллеры, модели, Core (включая App, Mailer, RequestThrottle), Support, routes, Views и assets; оставить исходный production `app/bootstrap.php`. Локальный bootstrap с проверкой `127.0.0.1:3307/local_catpart`, локальный router и `tools/checkout-test-router.php` на хостинг не копировать.

Если production bootstrap имеет собственное слияние настроек, перед существующим вызовом `App::boot($config)` и после всех host-only overrides добавить безопасный overlay:

```php
$branding = require BASE_PATH . '/app/Config/branding.php';
$config['branding'] = $branding;
$config['site'] = array_replace($config['site'] ?? [], [
    'email' => $branding['email'], 'company' => $branding['company'],
    'phone' => $branding['phones'][0]['label'], 'phones' => $branding['phones'],
    'address' => $branding['address'],
]);
$config['mail']['notify'] = [$branding['email']];
$config['mail']['delivery'] = 'mail';
```

Это единственное изменение production bootstrap; локальные DB guards не переносить. `mail.from_email` оставить прежним: `noreply@cat-part.ru`; перенос отправителя на другой домен требует отдельной SPF/DKIM настройки. Для ограничения заказов создать вне document root каталог `private/rate-limits` на уровне рядом с корнем приложения, доступный PHP на запись (0700). Лимит: 5 проверенных запросов за 10 минут с одного IP, общая блокировка файла; при недоступности хранилища отправка отклоняется с сохранением корзины. При reverse proxy проверить корректность `REMOTE_ADDR` на уровне сервера, не доверять пользовательскому X-Forwarded-For.

На локальной копии Mailer всегда заменяет mail() частным захватом в `local-dev/private/mail-captures/` (вне `site/`), не отправляя настоящих писем. Старые локальные захваты перемещены в `local-dev/private/legacy-order-captures/`. Эти каталоги не публиковать. Локальные успехи явно сообщают, что письмо не отправлялось. Production использует настоящий mail() и сообщает об успехе только после принятия транспортом; это ещё не подтверждает получение письма адресатом.

Статьи не требуют изменения production БД: сопоставление slug → WebP находится в `article_graphic()`, новые slug используют `article-default.webp`; перенести также все cover и thumb файлы. Главная показывает последние 3 статьи, `/stati` — все 4 текущие. Товарные оригиналы PNG сохраняются отдельно, WebP-производные подготавливаются `tools/prepare-product-webp.py`.

Перед публикацией выполнить локально `python tools/check-site.py` и `python tools/test-checkout.py`. После переноса проверить `/kontakty`, метаданные, mailto, телефоны, адрес и корзину. Затем выполнить согласованную реальную тестовую заявку и отдельный тестовый заказ на production, подтвердить получение на `info@cmteh.ru`; повтор того же checkout-токена не должен создавать письмо, новая корзина с теми же контактами должна приниматься. Здесь реальная доставка и production не проверялись.
