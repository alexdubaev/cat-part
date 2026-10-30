<?php
/**
 * SVG-спрайт иконок разделов каталога.
 *
 * Ключ symbol'а = categories.icon из БД (см. db/seed_categories.sql):
 * hydraulic, engine, cab, kit, frame, fastener, workshop, upgrade,
 * attachment, get, drivetrain, filter, undercarriage, hose, electric.
 * Плюс `part` — запасной знак для разделов, приехавших без иконки.
 *
 * Вторая группа — интерфейсные знаки (check, search, cross, layers, shield,
 * mail, phone, pin): блок преимуществ на главной и карточки контактов.
 * Они НЕ маппятся на categories.icon и в БД не приезжают — живут тут потому,
 * что спрайт и так подключён на каждой странице, а восемь отдельных <svg>
 * в разметке пришлось бы дублировать в двух шаблонах.
 *
 * Зачем спрайт, а не <img> на каждую плитку: 15 иконок = 15 запросов и
 * невозможность перекрасить их под ховер. Тут они наследуют currentColor.
 *
 * Подключается из layout один раз сразу после <body> — <use> ищет symbol
 * по id в текущем документе, поэтому спрайт обязан быть ВЫШЕ плиток.
 *
 * ⚠️ Правовая рамка: осознанно нет ни одной треугольной формы — ничего,
 * что читалось бы как отсылка к знаку Caterpillar (plans/, раздел 0).
 * Зубья ковша (`get`) поэтому нарисованы прямоугольными, а не клиньями.
 */
?>
<svg class="cp-vh" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <!-- Общие параметры обводки заданы на каждом symbol: <use> не наследует
             презентационные атрибуты от ссылающегося <svg> во всех браузерах. -->

        <!-- Гидравлика: гидроцилиндр со штоком -->
        <symbol id="cp-i-hydraulic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="8" width="12" height="8" rx="2"/>
            <path d="M14 12h5"/>
            <circle cx="21" cy="12" r="1.6"/>
            <path d="M5 8V6M9 8V6"/>
        </symbol>

        <!-- Двигатель: блок с патрубками -->
        <symbol id="cp-i-engine" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="9" width="13" height="9" rx="2"/>
            <path d="M7 9V5h4v4"/>
            <path d="M16 12h2a3 3 0 0 1 3 3v3"/>
            <path d="M3 14H1"/>
        </symbol>

        <!-- Кабина: остекление оператора -->
        <symbol id="cp-i-cab" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 20V9l6-4h6a2 2 0 0 1 2 2v13"/>
            <path d="M4 20h16"/>
            <path d="M9 10v5h8"/>
        </symbol>

        <!-- Комплекты для ремонта: ящик с инструментом -->
        <symbol id="cp-i-kit" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="8" width="20" height="12" rx="2"/>
            <path d="M8 8V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            <path d="M2 13h20"/>
            <path d="M10 13v2h4v-2"/>
        </symbol>

        <!-- Конструкции: рама с раскосами -->
        <symbol id="cp-i-frame" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="6" width="18" height="12" rx="1"/>
            <path d="M3 6l18 12M21 6L3 18"/>
        </symbol>

        <!-- Крепёж: гайка «под ключ» -->
        <symbol id="cp-i-fastener" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2.5l7.5 4.3v8.4L12 19.5l-7.5-4.3V6.8z"/>
            <circle cx="12" cy="11" r="3.2"/>
        </symbol>

        <!-- Мастерская: ключ -->
        <symbol id="cp-i-workshop" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.5 5.5l-3 3-2-2 3-3a5 5 0 0 0-6.4 6.4L4 16.9V20h3.1l7-7a5 5 0 0 0 6.4-6.4z"/>
        </symbol>

        <!-- Модернизация: стрелка вверх -->
        <symbol id="cp-i-upgrade" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 16.5V8M8.5 11.5L12 8l3.5 3.5"/>
        </symbol>

        <!-- Навесное оборудование: ковш -->
        <symbol id="cp-i-attachment" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 6v6a5 5 0 0 0 5 5h9V6"/>
            <path d="M18 10h3"/>
            <path d="M4 6h4"/>
        </symbol>

        <!-- Оснастка землеройных орудий: коронки (прямоугольные, без клиньев) -->
        <symbol id="cp-i-get" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 6h18v6H3z"/>
            <path d="M6 12v5h3v-5M15 12v5h3v-5"/>
        </symbol>

        <!-- Силовая передача: шестерня -->
        <symbol id="cp-i-drivetrain" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3.2"/>
            <path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.2 2.2M16.9 16.9l2.2 2.2M19.1 4.9l-2.2 2.2M7.1 16.9l-2.2 2.2"/>
        </symbol>

        <!-- Фильтры: фильтрующий элемент -->
        <symbol id="cp-i-filter" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="7" y="3" width="10" height="18" rx="3"/>
            <path d="M7 8h10M7 12h10M7 16h10"/>
        </symbol>

        <!-- Ходовая часть: гусеничная тележка -->
        <symbol id="cp-i-undercarriage" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="8" width="20" height="8" rx="4"/>
            <circle cx="7" cy="12" r="2"/>
            <circle cx="17" cy="12" r="2"/>
            <path d="M11.5 12h1"/>
        </symbol>

        <!-- Шланги и трубки: РВД с фитингами -->
        <symbol id="cp-i-hose" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 18c7 0 7-12 14-12"/>
            <rect x="2" y="16" width="4" height="4" rx="1"/>
            <rect x="18" y="4" width="4" height="4" rx="1"/>
        </symbol>

        <!-- Электрооборудование: разряд -->
        <symbol id="cp-i-electric" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M13 2L5 13h6l-1 9 8-11h-6z"/>
        </symbol>

        <!-- Запасной знак: раздел без иконки -->
        <symbol id="cp-i-part" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="8.5"/>
            <circle cx="12" cy="12" r="3"/>
            <path d="M12 3.5v3M12 17.5v3M3.5 12h3M17.5 12h3"/>
        </symbol>

        <!-- ── Интерфейсные знаки (не из categories.icon) ────────────────── -->

        <!-- Галочка: тезисы hero на /kontakty -->
        <symbol id="cp-i-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9"/>
            <path d="M8 12.4l2.7 2.7L16 9.7"/>
        </symbol>

        <!-- Лупа: поиск по артикулу -->
        <symbol id="cp-i-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="7"/>
            <path d="M20 20l-3.7-3.7"/>
        </symbol>

        <!-- Кросс-номера: встречные стрелки — «этот номер = вот этот» -->
        <symbol id="cp-i-cross" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 8h14M14 5l3 3-3 3"/>
            <path d="M21 16H7M10 13l-3 3 3 3"/>
        </symbol>

        <!-- Оригинал и аналог: две плашки со сдвигом -->
        <symbol id="cp-i-layers" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="13" height="13" rx="2"/>
            <path d="M8 21h11a2 2 0 0 0 2-2V8"/>
        </symbol>

        <!-- Независимость: щит с галочкой. Щит, а не треугольник — см. шапку файла -->
        <symbol id="cp-i-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3l7.5 3v5.6c0 4.3-3.2 8-7.5 9.4-4.3-1.4-7.5-5.1-7.5-9.4V6z"/>
            <path d="M9 12l2.2 2.2L15.5 10"/>
        </symbol>

        <!-- Почта: конверт -->
        <symbol id="cp-i-mail" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="5" width="18" height="14" rx="2"/>
            <path d="M3.6 7.2l8.4 5.6 8.4-5.6"/>
        </symbol>

        <!-- Телефон: трубка. Пока не используется — карточка телефона на /kontakty
             появится сама, как только config.site.phone перестанет быть пустым -->
        <symbol id="cp-i-phone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6.5 3h3l1.5 4-2 1.5a12 12 0 0 0 6.5 6.5l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A17.5 17.5 0 0 1 4.5 5.2 2 2 0 0 1 6.5 3z"/>
        </symbol>

        <!-- Адрес: метка. Тоже ждёт config.site.address -->
        <symbol id="cp-i-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/>
            <circle cx="12" cy="10" r="2.6"/>
        </symbol>
    </defs>
</svg>
