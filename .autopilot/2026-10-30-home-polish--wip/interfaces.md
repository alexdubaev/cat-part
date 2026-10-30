# Границы и правила
Рабочая зона local-dev/site. Исходник cat-part.ru не менять, production базы не трогать. Секреты не читать/не коммитить.
PHP MVC без framework/build dependencies. Существующая типографика, жёлтый/графит/белый, знак CP и CatPart сохраняются.
Assets: local-dev/site/assets/img/catalog-icons/<semantic-name>.webp (18), benefits/<name>.webp (4), articles/<slug>.webp + <slug>-thumb.webp (4).
Общие slug/name mappings должны работать во всех представлениях и в брендбуке. Реестр готовых assets в .autopilot/2026-10-30-home-polish--wip/asset-manifest.json. ImageGen исходники сначала в папке генераций, копии в design-assets/source внутри run; использовать Pillow для resize/WebP и служебных производных разрешено.
Брендбук deliverables/brandbook/index.html и brandbook.pdf; при использовании локальных относительных assets все положить рядом.
Одна команда проверки python tools/check-site.py (создаётся исполнителем; PHP lint, HTTP, assets) и визуальная проверка Playwright локально. Сначала измерить исходную высоту каталога и сделать screenshots before. Не читать `.sql` целиком, не печатать пользовательские данные.
Git коммиты только явные пути; базовые config игнорируются. Изображения товаров не коммитить тысячами.
