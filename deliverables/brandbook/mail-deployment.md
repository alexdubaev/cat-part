# Публикация получателя заявок

Публичный адрес CatPart/СМ ТЕХНО: `info@cmteh.ru`. На production задайте его в versioned safe branding/settings file, который подключается к основному bootstrap приложения, и отдельно задайте production `mail.notify` этим же адресом.

Локальный `local-dev/site/app/bootstrap.php` принудительно обнуляет `mail.notify` и пишет тестовые заказы в `storage/order-captures`; его нельзя копировать на хостинг как production-конфигурацию. Секреты SMTP/БД остаются в host-only `config.local.php` или environment variables.

После публикации проверьте GET `/kontakty`, наличие всех `mailto:info@cmteh.ru` и `tel:` ссылок. Реальную отправку письма проверяйте только согласованным тестовым обращением на production.
