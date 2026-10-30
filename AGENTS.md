<!-- autopilot:start -->
# CatPart
PHP-сайт каталога запчастей. Рабочая локальная копия: local-dev/site. Оригинал с хостинга: cat-part.ru. База и секреты не коммитятся.
Запуск: powershell -ExecutionPolicy Bypass -File local-dev/start-local.ps1
Локальный адрес: http://127.0.0.1:8080/
Требования и прогресс Autopilot: .autopilot/.

## Память проекта
- Основная область изменений: `local-dev/site`; оригинальный snapshot `cat-part.ru` не изменять. Не трогать production БД, не читать и не коммитить секреты.
- Сайт — PHP MVC без framework/build dependencies. Сохранять существующую типографику, жёлтый/графит/белый стиль, знак CP и CatPart.
- Для безопасной смены брендинга использовать `local-dev/site/app/Config/branding.php` (название компании, телефоны, email, офис), не править разрозненные вхождения без необходимости.
- Core Mailer настроен на локальный private capture; реальная почта не отправляется. Не выводить содержимое пользовательских данных.
- Команды проверки, уже подтверждённые в этой копии: `powershell -ExecutionPolicy Bypass -File local-dev/start-local.ps1`, `python tools/check-site.py`, `python tools/test-checkout.py` (10 passed). Локальный адрес `http://127.0.0.1:8080/`.
- Для новых helper scripts runtime/state paths должны определяться динамически, а не через жёстко заданные временные пути. Подготовка новых изображений: `python tools/prepare-assets.py`; товарных WebP: `python tools/prepare-product-webp.py`; не запускать массовую конвертацию без явной задачи. Брендбук: `deliverables/brandbook/index.html` и `deliverables/brandbook/brandbook.pdf`; локальные относительные assets хранить рядом. Сверять slug/name mappings с реестром `asset-manifest.json` в текущей папке сборки (`.autopilot/state.js` → `dir`).
- Для будущих небольших правок сначала читать `.autopilot/PROGRESS.md` и относящиеся к правке требования; не загружать лишние артефакты и не выводить данные/секреты.
- Git-коммиты делать только с явным перечнем путей. Не добавлять локальные config, секреты, `.sql`, runtime/uploads/DB inputs или тысячи товарных изображений. Инструкция `deliverables/brandbook/mail-deployment.md` требует не копировать local-only config/секреты на production.
- `CLAUDE.md` указывает на этот файл (`@AGENTS.md`); сохранять этот указатель.
<!-- autopilot:end -->
