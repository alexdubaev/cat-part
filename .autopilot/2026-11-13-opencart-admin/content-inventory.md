# Инвентарь публичного содержимого CatPart

2026-11-13, read-only исследование текущей `origin/main` (`9f9ba0b`). Изменений приложения/БД и отправок форм нет. `config.php` и `config.local.php` **не читались**. Публичные значения, отсутствующие в безопасном branding, сверены обычным GET главной на локальном 8080 без cookies. Это инвентарь исходников, не утверждение о реализации полей.

Все ссылки `Views/...`, `Controllers/...`, `Models/...`, `Support/...`, `Config/...` ниже относительно `local-dev/site/app/`. Номера строк относятся к исходной main до работы над админкой. Предлагаемые field keys — внутренняя карта миграции; пользователю нужны русские подписи обычных полей, не редактор JSON.

## Правила переноса

- Один источник для контактов/названия/реквизитов. Повторяющийся телефон или email в body/CTA — ссылка на настройку, а не независимое поле с копией значения.
- Для каждого блока главной: `enabled`, `sort_order`, title/text/image/alt и набор кнопок `label,url,enabled,sort_order`; начальный порядок ниже сохраняет текущее отображение. Наличие этих полей в будущем не означает, что они уже есть в источнике.
- Структура компонентов, локализация ошибок/валидации, роли ARIA, названия технических действий могут оставаться в коде. Рекламные и поставочные обещания — управляемое содержимое. Визуальный редактор не должен принимать PHP; seed извлекает текст с подстановкой безопасных ссылок на настройки.
- Изображения — существующие пути и alt; не превращать декоративное пустое alt в выдуманное описание. Выбор нового изображения/alt должен сохранять старый результат до изменения владельцем.
- Полные тексты информационных страниц переносить из отмеченного body-фрагмента, включая списки и note, а не только первый абзац из этой таблицы. Здесь даны начальные фразы/заголовки, чтобы не дублировать многостраничные исходники.

## Общие настройки и ресурсы

| Ключи | Источник | Текущее публичное значение / замечание |
|---|---|---|
| `site.name`, `site.domain` | layout `Views/layouts/main.php:47`; footer `:78`; публичный GET | `CatPart`, `cat-part.ru` |
| `site.company` | `Config/branding.php:8` | `СМ ТЕХНО` |
| `site.tagline` | `Views/partials/footer.php:29`; public GET | `Запчасти для техники Caterpillar` |
| `site.logo_mark` | `Views/partials/footer.php:26`; public GET | `CP` |
| `site.logo`, `site.logo_alt` | `Views/partials/header.php:32` | `/assets/img/logo.webp`; alt = site.name; размер 266×34 — оформление |
| `site.favicon` | `Views/layouts/main.php:132` | `/assets/img/favicon.svg` |
| `seo.default_og_image` | `Views/layouts/main.php:32` | `/assets/img/og-default.jpg` |
| `site.email`, `mail.notify` | `Config/branding.php:9`; `Core/App.php:34` | `info@cmteh.ru`; App сейчас принудительно задаёт получателя из branding, это перекрытие требуется убрать при интеграции |
| `site.phones[]` (`label`,`tel`,`sort_order`) | `Config/branding.php:10` | `+7 (812) 436-82-99` / `+78124368299`; `+7 911 921-22-14` / `+79119212214`; `+7 965 785-57-85` / `+79657855785`; `+7 965 818-56-87` / `+79658185687` |
| `site.primary_phone` | `Core/App.php:31`; `Views/partials/header.php:20` | App берёт первый label; шапка требует **и** `site.phone_raw`, которого branding не задаёт. При переносе primary phone должен ссылаться на выбранную строку телефонов |
| `site.address` | `Config/branding.php:16` | `г. Санкт-Петербург, Кондратьевский проспект, 2, корп. 4, офис 704/1` |
| `site.legal`, `site.inn`, `site.ogrn` | `Views/pages/contacts.php:28`; `privacy.php:26`; footer `:19` | Потребители есть, значения из приватных конфигов не извлекались. На публичной главной юрлицо в copyright не показано; не заполнять выдуманным |
| `site.city` | `Views/layouts/main.php:36` | Используется в Store JSON-LD addressLocality; хранить рядом с адресом, не вторую публичную адресную строку |
| `site.hours`, `site.messengers[]`, `site.socials[]`, `site.map_widget` | В текущих templates отсутствуют | Новые поля по ТЗ, seed пустой; не придумывать часы/каналы/карту |
| `mail.from_email`, `mail.from_name` | `Controllers/LeadController.php:158`; `CartController.php:112` | Используются в транспортных заголовках; существующие секреты/приватные значения не читать/не коммитить; нормальные поля sender/recipient отдельно от секретного transport config |
| `legal.disclaimer` | `Views/partials/footer.php:74`; public GET | `CatPart — независимый поставщик запчастей. Мы не являемся официальным дилером и не аффилированы с Caterpillar Inc. CAT, CATERPILLAR, их логотипы и фирменное оформление являются товарными знаками Caterpillar Inc. Названия, марки и номера деталей приведены исключительно для идентификации применимости и подбора запчастей.` |

Потребители единых настроек: header, footer, contacts, privacy, home/page email CTA, lead/cart notification recipients, JSON-LD Store/Organization/WebSite и SEO fallback. Не мигрировать `app.base_url` из локального 8080 как production домен.

## Аналитика и права

| Ключ | Источник | Значение/правило |
|---|---|---|
| `analytics.metrika_id` | `Config/branding.php:6` | `114229167` |
| `analytics.allowed_hosts` | `Config/branding.php:7` | `cat-part.ru`, `www.cat-part.ru` |
| Согласованные опции Metrika | `Views/layouts/main.php:171` | `ssr:true`, `webvisor:true`, `clickmap:true`, `ecommerce:"dataLayer"`, `referrer:document.referrer`, `url:location.href`, `accurateTrackBounce:true`, `trackLinks:true` |
| `analytics.ga4_id`, `verification.yandex_content`, `verification.google_content` | Отсутствуют | Новые поля по ТЗ; seed пустой |
| Яндекс verification файл | `local-dev/site/yandex_130ab6b1c0898944.html` | Существующий отслеживаемый файл, сохранить URL/содержимое при упаковке; не заменять метатегом |

Счётчик выводится общим layout :107–110, :159–178, включая noscript в body. Граница локального запрета должна сохраниться. Ни админка, ни локальные визиты не должны отправлять события.

## Шапка и навигация

`Views/partials/header.php:31` — logo link `/`; :42 — search placeholder `Поиск по артикулу или названию`; :45 button `Найти`; :56 подпись email fallback `Заявки и подбор`. Последнюю стоит хранить как `header.contact_caption`. Search label/button — локализация, placeholder можно редактировать через настройки формы поиска. Cart/menu functional labels могут оставаться локализацией.

`menus.header.items[]` — каждая строка имеет label/url/order/enabled. Начальные строки **в этом порядке** (`Views/partials/header.php:80`):

| Порядок | Подпись | URL |
|---|---|---|
| 10 | Каталог | `/catalog` |
| 20 | О компании | `/o-kompanii` |
| 30 | Доставка | `/dostavka` |
| 40 | Оплата | `/oplata` |
| 50 | Гарантия | `/garantiya` |
| 60 | Статьи | `/stati` |
| 70 | Контакты | `/kontakty` |

Каталог сейчас визуально accent (:80); сохранить допустимым компонентным style, не HTML от владельца. Desktop/mobile используют одну nav (:79), второе независимое меню не требуется.

## Главная — блоки и исходный порядок

| Порядок/ключ | Источник | Редактируемые поля и исходные значения |
|---|---|---|
| 10 `home.hero` | `Views/home.php:89`, `:107` | `image=/assets/img/hero-wide.webp`, decorative alt empty; title `Запчасти для техники Caterpillar по артикулу` (текущий `<br>` перед Caterpillar — оформление); text `Введите артикул, кросс-номер или название узла. Подберём оригинал или совместимый аналог и рассчитаем поставку.` |
| hero.buttons | `Views/home.php:112` | `Перейти в каталог` → `/catalog`; `Запрос на подбор` → `/kontakty` |
| 20 `home.facts` | `Views/home.php:124` | Три строки title/text/order: ниже |
| 30 `home.categories` | `Views/home.php:144` | title `Разделы каталога`; expand label `Весь каталог`, collapse `Свернуть каталог` (ещё `assets/js/app.js:66`); fallback link `/catalog`; category title/link/count из реального каталога |
| categories.featured_ids/order | `Views/home.php:29` | Сейчас первые шесть **зашиты slugs**: `dvigatel`, `gidravlika`, `silovaya-peredacha`, `hodovaya-chast`, `shlangi-i-trubki`, `kabiny`; остальные root categories после них. Это отдельная ручная подборка, не только categories.sort |
| categories.empty_text | `Views/home.php:156` | `Каталог наполняется. Напишите нам — подберём деталь по номеру вручную:` + settings.email link |
| 40 `home.products` | `Views/home.php:183`; `Models/Product.php:339`; `Controllers/HomeController.php:49` | title `Товары`; link `Весь каталог →` → `/catalog`; **автоматический** режим, default limit 5, выбор MAX(id) по категориям, активные, сортировка in_stock DESC/id DESC. В форме явно показать автоматический режим либо ручной выбор IDs; не обещать существующую ручную подборку |
| 50 `home.about` | `Views/home.php:246` | title `О компании`; link `Подробнее →` → `/o-kompanii`; два body paragraphs ниже |
| 60 `home.benefits` | `Views/home.php:272` | title `Почему у нас покупают запчасти`; четыре карточки image/title/text/order ниже |
| 70 `home.help` | `Views/home.php:303` | title `Не нашли деталь по номеру?`; text `Напишите номер с детали или таблички, модель и серийный номер машины и опишите узел — ответим с вариантами применимости и условиями поставки.`; buttons `Написать нам` → mailto settings.email, `Контакты` → `/kontakty` |
| 80 `home.lead` | `Views/home.php:326` | formSource `home`; formTitle `Оставить заявку на подбор`; остальное общий leadform |
| 90 `home.articles` | `Views/home.php:358`; `Controllers/HomeController.php:55` | title `Новости и статьи`; link `Все статьи →` → `/stati`; automatic latest 3 published posts; блок скрыт при пустом списке. Статьи стоят последними, сохранить порядок seed |

`home.facts.items` (source `Views/home.php:124`, `:129`, `:134`):

1. `Поиск по номеру и кроссу` — `Ищем по артикулу производителя и по кросс-номерам — даже если номер на снятой детали чужой или устаревший.`
2. `Оригинал и аналоги` — `Показываем варианты применимости: оригинальную деталь и совместимые замены, чтобы выбор остался за вами.`
3. `Подбор по узлу` — `Не знаете номер — опишите узел и модель машины, поможем определить деталь.`

`home.about.body_html` (`Views/home.php:252`):

- `{site.name} — сайт компании {site.company}, поставщика оригинальных деталей и качественных аналогов для техники Caterpillar.` (в источнике СМ ТЕХНО зашито прямо в home, хотя about page уже вызывает настройку).
- `Поставляем запчасти по России: позиции бывают в наличии и под заказ. Подберём деталь по артикулу, каталогу, фотографии или описанию узла.`

`home.benefits.items` (`Views/home.php:278`, `:283`, `:288`, `:293`; graphic resolver `Support/helpers.php:94`):

| Изображение | Title | Text |
|---|---|---|
| `/assets/img/benefits/stock.webp` | Запчасти в наличии | Ходовые позиции доступны к отгрузке без долгого ожидания. |
| `/assets/img/benefits/special-order.webp` | Поставка под заказ | Найдём и поставим редкие позиции, узлы и комплектующие под конкретную технику. |
| `/assets/img/benefits/original-analog.webp` | Оригиналы и аналоги | Подберём решение под бюджет и задачи эксплуатации, а выбор останется за вами. |
| `/assets/img/benefits/selection.webp` | Точный подбор | Идентифицируем деталь по артикулу, каталогу, фото или описанию узла. |

Изображения advantages decorative alt empty; SVG icon — fallback компонента. Картинки категорий идут через `Support/helpers.php:73` (slug → asset name); article covers через `:106` (slug → asset name). При миграции materialize выбранный существующий image URL в записи, иначе новая image настройка не перекроет старый helper. Product images из общего каталога; не отдельные копии полей домашней карточки.

## Общая форма и оформление запроса

| Ключ | Источник | Исходное значение/поведение |
|---|---|---|
| `forms.lead.default_title` | `Views/partials/leadform.php:34` | `Оставить заявку на подбор` |
| `forms.lead.text` | `Views/partials/leadform.php:63` | `Оставьте имя и телефон — свяжемся с вами, чтобы уточнить деталь и условия поставки.` |
| `forms.lead.note` | `Views/partials/leadform.php:65` | `Пригодится к разговору: номер с детали или таблички, модель и серийный номер машины. Номера нет — определим деталь по узлу, это нормальная рабочая ситуация.` |
| `forms.lead.name_label/placeholder` | `Views/partials/leadform.php:83` | `Имя` / `Как к вам обращаться` |
| `forms.lead.phone_label/placeholder` | `Views/partials/leadform.php:90` | `Телефон` / `+7 900 000-00-00` |
| `forms.lead.consent_text` | `Views/partials/leadform.php:112` | `Согласен на обработку персональных данных и принимаю политику конфиденциальности.`; policy link `/politika-konfidencialnosti` отдельным полем/выбором страницы |
| `forms.lead.submit_label` | `Views/partials/leadform.php:122` | `Отправить заявку` |
| `forms.lead.recipients` | `Core/App.php:34`; `Core/Mailer.php:10` | ссылка на единые `mail.notify`; не независимый адрес в каждой форме |
| per placement titles/source | `Views/home.php:326`; `Views/pages/contacts.php:177`; `Views/catalog/listing.php:68`; `Views/catalog/product.php:254` | home: `Оставить заявку на подбор`/home; contacts: `Оставить заявку`/contacts; catalog: `Не нашли нужную деталь?`/catalog; product: `Запросить цену и срок по {sku}` либо `Запросить цену и срок`/product |
| `forms.checkout.title/text/submit_label` | `Views/cart/index.php:27` | `Отправить запрос`; `Цена и наличие подтверждаются менеджером. Онлайн-оплаты нет.`; `Отправить запрос` |
| checkout field labels/consent | `Views/cart/index.php:32` | `Имя`, `Телефон`, `Email (необязательно)`, `Согласен на обработку персональных данных.`; в текущей форме комментария нет |
| `cart.empty_title/text/button` | `Views/cart/index.php:12` | `Корзина пуста`; `Добавьте позиции из каталога, затем отправьте запрос на подтверждение наличия и стоимости.`; `Перейти в каталог` → `/catalog` |

CSRF, honeypot и source tokens — системные поля, не дать владельцу ломать их через content editor. Сообщения проверки/ошибок и `Количество/Обновить/Удалить` могут остаться локализацией. Ответ после сохранения зависит от реального результата, не произвольное рекламное поле; не обещать email delivery до сохранения и фактического транспорта.

## Информационные страницы

У каждой страницы: `title_h1`, безопасный `body_html`, `seo_title`, `seo_description`, `canonical`, indexability, OG image, published state; help CTA отдельно как structured block. Canonical URL seed прежний. Динамические настройки внутри prose не дублировать текстовым снимком.

| Ключ/URL | Источник body | Содержимое, которое требуется перенести целиком |
|---|---|---|
| `pages.about`, `/o-kompanii` | `Views/pages/about.php:21` | H1 `О компании`; intro `{site.name} — сайт компании {site.company}, поставщика запчастей для техники Caterpillar. Предлагаем оригинальные детали и качественные аналоги, в наличии и под заказ, с поставкой по России.`; секции `Чем мы занимаемся` (:27, вводный абзац, четыре способа подбора, абзац о дилерской сети), `Как мы работаем` (:47, четыре шага), `Кому это нужно` (:56, аудитории) |
| `pages.delivery`, `/dostavka` | `Views/pages/delivery.php:21` | H1 `Доставка`; intro `Доставляем запчасти по России. Способ отправки, срок и стоимость всегда подтверждаем до оплаты…`; `Как проходит заказ` (:27, 4 шага), note о точных сроках (:37), `От чего зависит срок` (:43, наличие/габариты/регион/состав), `Приёмка груза` (:56, 2 paragraphs) |
| `pages.payment`, `/oplata` | `Views/pages/payment.php:24` | H1 `Оплата`; intro `Заказ подтверждается до оплаты: вы видите итоговый состав, стоимость позиций и доставки…`; `Как формируется стоимость` (:30, 3 варианта), note о способах оплаты (:42, email из настройки), `Что стоит прислать сразу` (:50, 4 пункта), `Изменение заказа` (:59) |
| `pages.warranty`, `/garantiya` | `Views/pages/warranty.php:25` | H1 `Гарантия и возврат`; intro `Если деталь оказалась не той или вышла из строя раньше времени — это решаемо.`; note об индивидуальных гарантийных условиях (:31), `Если деталь не подошла` (:37), `Если есть претензия к качеству` (:44, email + 4 пункта и пояснение), `Что важно знать заранее` (:56, 3 пункта, доставка link), `Права покупателя` (:67) |
| `pages.contacts`, `/kontakty` | `Views/pages/contacts.php:40` | Hero image `/assets/img/contacts.webp`; H1 `Контакты и подбор запчастей` (:56); text `Свяжитесь с СМ ТЕХНО по телефону или почте. Пришлите номер детали или опишите узел: подтвердим применимость и ответим по вашей задаче.`; три bullets `Номер известен.`, `Номер чужой или стёрт.`, `Номера нет вовсе.` (:63–77) |
| contacts cards/body | `Views/pages/contacts.php:93`, `:138` | Контактные карточки: email, все phones, address из настроек; email hint `Номер детали, модель и серийный номер машины, фото — одним письмом.`; title `Что прислать для быстрого ответа`, intro и 4 bullets (номер/машина/фото/количество+город), concluding paragraph; `Реквизиты` (:154) с settings.legal/inn/ogrn; form placement (:177) |
| `pages.privacy`, `/politika-konfidencialnosti` | `Views/pages/privacy.php:33` | H1 `Политика конфиденциальности`; intro с settings.domain; все 9 секций: `1. Оператор` (:40, legal/inn/ogrn или contact fallback), `2. Какие данные мы обрабатываем` (:58), `3. Зачем эти данные нужны` (:73), `4. Основания обработки` (:84), `5. Кому передаются данные` (:89), `6. Cookie и статистика` (:97), `7. Хранение и защита` (:105), `8. Ваши права` (:114), `9. Изменения политики` (:131). Не переписывать юридические утверждения при механической миграции |

Help CTA (каждый: title,text,buttons с label,url,order):

| Ключ | Источник | Title; text; кнопки |
|---|---|---|
| `pages.about.help` | `Views/pages/about.php:68` | `Нужна деталь — напишите`; `Номер с детали или таблички, модель и серийный номер машины, описание узла. Ответим с вариантами применимости.`; `Написать нам` → mailto email, `Каталог` → `/catalog` |
| `pages.delivery.help` | `Views/pages/delivery.php:70` | `Рассчитать доставку`; `Напишите артикулы позиций и город получения — вернёмся со сроком и стоимостью по вашему заказу.`; `Написать нам` → mailto email |
| `pages.payment.help` | `Views/pages/payment.php:70` | `Нужен расчёт по заказу?`; `Пришлите артикулы, количество и город — вернёмся со стоимостью и условиями.`; `Написать нам` → mailto email, `Доставка` → `/dostavka` |
| `pages.warranty.help` | `Views/pages/warranty.php:79` | `Вопрос по гарантии?`; `Напишите номер заказа и артикул — разберёмся и предложим решение.`; `Написать нам` → mailto email |
| `pages.contacts.hero.buttons` | `Views/pages/contacts.php:82` | `Написать на почту` → mailto email; `Перейти в каталог` → `/catalog` |
| `pages.contacts.help` | `Views/pages/contacts.php:186` | `Знаете номер детали?`; `Проверьте наличие в каталоге — поиск понимает кросс-номера и любой формат записи артикула.`; `В каталог` → `/catalog`, `Написать нам` → mailto email |

## Каталог, карточка, поиск, статьи — бизнесовое обрамление

Товар/категория/article content и изображения уже лежат в БД; поля — в discovery-storefront.md. Ниже то, что останется зашитым, если перенести только таблицы.

| Ключ | Источник | Текущий текст/значение |
|---|---|---|
| `catalog.title_h1` | `Controllers/CatalogController.php:121` | `Каталог запчастей Caterpillar` (текущий routed listing) |
| catalog category content/SEO | `Controllers/CatalogController.php:121`; `Views/catalog/listing.php:22` | H1 seo_h1 либо category.name; title/description/keywords из записи; introHtml выводится по текущему контракту. Дерево из БД; не статический список |
| catalog helper old templates | `Views/catalog/index.php:32`; `category.php:49` | index hero `Каталог запчастей`, intro `Разделы каталога повторяют логику узлов машины…`, buttons `К разделам`, `Запрос на подбор`; bottom `Знаете номер детали?` + search CTA. Эти index/category templates **не используются текущими index/category routes**, которые ведут в listing; не возвращать их случайно в публичный UX |
| `catalog.empty_text` | `Views/catalog/_products.php:3` | `По выбранным условиям товары не найдены. Измените фильтры или напишите номер детали на {email} — проверим наличие и подберём аналог.`; сохранить адрес как настройку. Служебные filters/sort labels можно оставить локализацией |
| `product.price_hint` | `Views/catalog/product.php:139` | `Цена зависит от объёма и срока — назовём после проверки наличия` |
| `product.price_request_label` | `Views/catalog/product.php:142` | `Запросить цену` → `#zayavka` |
| `product.usp_items[]` | `Views/catalog/product.php:152` | `Подбор по номеру детали и по модели машины`; `Проверяем применимость по серийному номеру`; `Поставка из наличия и под заказ, доставка по России` |
| `product.compat_note` | `Views/catalog/product.php:194` | `Перечень применимости справочный. Перед заказом сверьте номер детали с каталогом вашей машины или пришлите её серийный номер — проверим.` |
| `product.delivery_note`, links | `Views/catalog/product.php:211` | `Отправляем транспортными компаниями по всей России, самовывоз по договорённости. Условия и способы оплаты — на отдельных страницах:`; `Доставка` `/dostavka`, `Оплата` `/oplata`, `Гарантия` `/garantiya` |
| `product.related_heading` | `Views/catalog/product.php:221` | `Другие детали этого раздела`; автоматические соседи из того же каталога, не ручные аналоги |
| `search.title_h1`, placeholder | `Views/search/index.php:21`, `:29` | `Поиск запчастей`; `Артикул детали или название` |
| `search.hint` | `Views/search/index.php:36` | `Ищем по номеру детали, кросс-номерам и названию. Разделители не важны: 3B-8489 и 3b 8489 — одно и то же.` |
| `search.empty_hint` | `Views/search/index.php:49` | `Проверьте номер детали или попробуйте более короткий запрос — часть артикула тоже найдётся. Либо подберите деталь по разделам каталога.` (link `/catalog`) |
| `articles.title_h1`, intro | `Views/blog/index.php:16` | `Статьи`; `Разбираем то, что мешает быстро найти и правильно выбрать деталь: как читается номер, чем оригинал отличается от аналога и восстановленной детали, когда ходовая уже просит замены и почему поддельный фильтр обходится дороже настоящего.` |
| `articles.empty_text` | `Views/blog/index.php:22` | `Статьи готовятся. А пока нужна деталь — напишите номер на {email}, подберём и проверим применимость.` |
| `articles.help` | `Views/blog/index.php:38` | `Остался вопрос по конкретной машине?`; `Статья отвечает на общий вопрос, а подбор — всегда про конкретный номер. Пришлите номер с детали или таблички, модель и серийный номер машины — ответим по применимости.`; `Написать нам` mailto email, `Каталог` `/catalog` |
| article detail headings/links | `Views/blog/show.php:49`, `:62` | `Перейти в каталог`, `Все статьи`, `Читайте также`, `Все статьи →`; article title/body/excerpt/cover/date/SEO из одной записи, связанные articles по текущему others |

Надписи наличия `В наличии`/`Под заказ`, `по запросу` (`Support/helpers.php:135`), кнопка `В корзину`, pagination/filter/tab system labels могут остаться локализацией; условия поставки и price hint должны быть управляемы. Нельзя позволить редактору изменять price display так, чтобы оно противоречило фактическому режиму цены/JSON-LD.

## Подвал и правовые подписи

| Ключ | Источник | Начальное содержимое |
|---|---|---|
| `footer.tagline` | `Views/partials/footer.php:32` | `Подбираем и поставляем запчасти для техники Caterpillar по номеру детали, кросс-номеру или описанию узла. Оригинал и совместимые аналоги.` |
| `menus.footer_catalog.title/items[]` | `Views/partials/footer.php:49` | title `Каталог`; `Все разделы` `/catalog`; `Гидравлика` `/catalog/gidravlika`; `Двигатель` `/catalog/dvigatel`; `Ходовая часть` `/catalog/hodovaya-chast`; `Фильтры и жидкости` `/catalog/filtry-i-zhidkosti` |
| `menus.footer_buyers.title/items[]` | `Views/partials/footer.php:58` | title `Покупателям`; `Доставка` `/dostavka`; `Оплата` `/oplata`; `Гарантия и возврат` `/garantiya`; `Корзина` `/korzina` |
| `menus.footer_company.title/items[]` | `Views/partials/footer.php:66` | title `Компания`; `О компании` `/o-kompanii`; `Контакты` `/kontakty`; `Политика конфиденциальности` `/politika-konfidencialnosti` |
| footer.contacts | `Views/partials/footer.php:36` | primary phone, email, all brand phones, address; настройки общие, не отдельные копии. Сейчас primary может дублировать первый телефон при заполненном phone_raw |
| footer.legal | `Views/partials/footer.php:74` | `legal.disclaimer` из единых настроек; сохраняется на каждой странице |
| `footer.copyright`, policy link | `Views/partials/footer.php:78` | `{site.name} · {site.domain} © {текущий год}` + optional legal; `Политика конфиденциальности` `/politika-konfidencialnosti`; год вычисляемый, не заморозить 2026 |
| `cookie.text` | `Views/partials/footer.php:89` | `Мы используем cookie, чтобы сайт работал корректно и было понятно, какие разделы каталога полезны. Продолжая пользоваться сайтом, вы соглашаетесь с политикой конфиденциальности.` (policy link отдельно) |
| `cookie.accept_label` | `Views/partials/footer.php:92` | `Принять`; consent state/DOM IDs остаются системными |

## SEO-поля и генерация представления

- Глобальные default: name, tagline, city, primary phone, email, default OG и favicon — см. общие настройки. `Views/layouts/main.php:19` берёт page.title → site.name; :20 description; :21 keywords; :26 canonical через url(); :32 OG; :129 всегда печатает `index, follow`. Search/catalog filters отдельно ставят HTTP X-Robots-Tag; при появлении редактируемого noindex согласовать HTML и header, не оставлять противоречащие результаты.
- Каждая информационная страница: current meta/route из `Controllers/PageController.php:28` (about), :39 (delivery), :50 (payment), :60 (warranty), :73 (contacts), :84 (privacy). Title строки: `О компании — {site.name}`, `Доставка запчастей по России | {site.name}`, `Оплата — {site.name}`, `Гарантия на запчасти | {site.name}`, `Контакты — {site.name}`, `Политика конфиденциальности — {site.name}`. Descriptions переносить вместе с route из этих полей, не генерировать новые обещания.
- Главная `Controllers/HomeController.php:23`: title `{site.name} — {lowercase site.tagline}: подбор по артикулу`; description `Запчасти для техники Caterpillar: гидравлика, двигатель, ходовая часть, фильтры, навесное оборудование. Подбор по номеру детали и кросс-номерам, поставка по России.`; canonical `/`. Предлагаемые ключи `home.seo_title`, `home.seo_description`, `home.seo_h1`, `home.og_image`, `home.indexable`.
- Каталог `Controllers/CatalogController.php:121–133`: категорийные SEO поля уже есть, root default H1 `Каталог запчастей Caterpillar`, title H1 + ` | CatPart`, description H1 + `. Подбор по номеру детали, поставка по России.`; canonical текущий route + page. Товар :36/:79–83: H1 seo_h1 → SKU/name, title/description/keywords/slug; canonical `/tovar/{slug}`. Нужны editable canonical/indexability/OG и реальные consumers, если включены по ТЗ.
- Статьи index `Controllers/BlogController.php:32`: title `Статьи о запчастях для техники Caterpillar | {site.name}` и description :33. Detail :66–71: title seo_title → title/site; description seo_description → excerpt; canonical `/stati/{slug}`, OG cover :91. Article H1 — title, не первый H1 в HTML. `blog_posts` full inventory в discovery-storefront.md.
- Поиск `Controllers/SearchController.php:88–93`: `Поиск: {query} — {site.name}` либо `Поиск запчастей по артикулу — {site.name}`; canonical `/poisk`; X-Robots-Tag noindex,follow (:41). Не включать query result URL в sitemap.
- JSON-LD: `Views/layouts/main.php:39` Store, :68 WebSite, :82 BreadcrumbList; `HomeController.php:29` дополнительно WebSite; `CatalogController.php:43` Product/Offer по реальным данным; `BlogController.php:107` Article. Это consumers тех же полей, не самостоятельные редакторы дублирующих данных.
- Sitemap/llms: `Controllers/SeoController.php:64` llms использует site.name/base, но бизнесовые абзацы и ссылки зашиты. Предлагаемый `seo.llms_intro/body` или генерация из управляемых страниц и реальных бизнес-режимов; никогда не редактор PHP. Текущий текст «Оформления заказа онлайн: заявка — это имя и телефон…» устарел относительно корзины, нужен явный контроль при интеграции. Sitemap маршруты/условие публикации должны остаться генерируемыми правилами.
- `robots.txt`, `manifest.webmanifest`, verification HTML — публичные файлы packaging; logo/favicon/name в manifest нужно сверить при поддержке редактируемых brand fields, не оставлять отдельный stale branding. Не удалять текущие SEO-файлы при миграции.

PWA business fields (`local-dev/site/manifest.webmanifest:2`): `pwa.name` = `CatPart — запчасти для техники Caterpillar`, `pwa.short_name` = `CatPart`, `pwa.description` = `Подбор запчастей для техники Caterpillar по артикулу, кросс-номеру и модели машины.`; icons `/assets/img/favicon.svg` и `/assets/img/icon-maskable.svg`; shortcuts (:31) `Поиск по артикулу`/`Поиск`/`Найти деталь по номеру или кросс-номеру` → `/poisk`, `Каталог`/`Разделы каталога запчастей` → `/catalog`, `Контакты` → `/kontakty`. При редактируемых global logo/name/favicon manifest должен потреблять те же settings либо явно входить в границы управляемых branding fields. display/orientation/colors/scope/start_url — настройки приложения/оформления, не бизнесовый текст.

## Проверка полноты seed

Сверять после миграции public rendering, не наличие названия поля: телефон во всех consumers; home заголовок/текст/image/порядок/link; первые шесть категорий и остальные; автоматическая/ручная подборка; все четыре advantages; полные page prose/list/note/help CTA; единые form labels/consent/recipients; header и 3 footer группы; legal/cookie/copyright; SEO+OG+JSON-LD; отсутствие локальной аналитики. При миграции изменяемый текст должен находиться в управляемой записи, а старый fallback не должен перекрывать сохранённое пустое значение.
