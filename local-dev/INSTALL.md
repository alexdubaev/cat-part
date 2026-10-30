# Восстановление локальной копии

Исходники находятся в `site/`. Игнорируемые PHP/MariaDB, база, загрузки и секреты в Git не входят. Для запуска нужны Windows PowerShell, PHP 8.2+ с PDO MySQL, mbstring и GD WebP, MariaDB и Python с Pillow для подготовки графики.

1. Восстановить доверенный локальный runtime: `bin/php/php.exe`, `bin/mariadb/bin/mariadbd.exe` и `bin/mariadb/bin/mariadb.exe`; структура должна соответствовать `start-local.ps1`. Не загружать runtime с production.
2. Скопировать `site/app/Config/config.example.php` в игнорируемые `config.php` и `config.local.php`, указать отдельного локального пользователя БД и пароль. Только `127.0.0.1:3307/local_catpart`: bootstrap отказывается от других адресов.
3. Восстановить существующий локальный datadir в `data/mariadb/` либо положить отдельно сохранённый дамп `u2797422_catpart.sql` в корень репозитория для первичного импорта стартовым скриптом; фотографии — в `site/upload/`. Эти файлы не публиковать и не добавлять в Git. Без них страницы товаров и фотографии неполны. Параметры восстановления уточнять по `start-local.ps1` и имеющемуся backup; production не подключать.
4. Запустить `powershell -ExecutionPolicy Bypass -File local-dev/start-local.ps1` из корня репозитория. Открыть `http://127.0.0.1:8080/`.
5. Выполнить `python tools/check-site.py` и `python tools/test-checkout.py`. Для последнего порт 8081 должен быть свободен. Тест использует только локальную БД и синтетические контакты; реальная почта принудительно заменена захватом в `local-dev/private/mail-captures/`, вне document root. Каталог `private/` игнорируется Git.

Для публикации читать `deliverables/brandbook/mail-deployment.md`. Локальные runtime, bootstrap, router теста и конфиги не копировать на хостинг.
