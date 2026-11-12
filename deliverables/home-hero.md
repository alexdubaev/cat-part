# Фон главной — 12 ноября 2026

Сценарий: экскаватор целиком в кадре, с продолжением карьера справа и отступом около 110 CSS px от края экрана при ширине 1920 px. Текст и карточки остаются HTML поверх одного непрерывного изображения. Изменение ограничено главной; фон страницы контактов сохранён.

Редактирование выполнено встроенным инструментом ImageGen по исходнику `local-dev/site/assets/img/hero.webp`. Исходный файл сохранён. Новые файлы:

- `local-dev/site/assets/img/hero-wide.webp` — 2078 × 757, 176 012 байт, WebP quality 80, method 6;
- `local-dev/site/assets/img/hero-wide-960.webp` — 960 × 350, 42 418 байт, WebP quality 77, method 6.

Это на 25,5% и 82% меньше прежнего фона (236 302 байта). После генерации Pillow использован только для уменьшения и кодирования WebP. Выбор размера — через `srcset`/`sizes`; основной фон загружается с `fetchpriority="high"`, размеры указаны явно. Сгенерированный PNG не загружается сайтом.

## Промпт ImageGen

Edit target: the attached existing CatPart website hero photograph (1983×793). Extend / outpaint the scene to the RIGHT by approximately 160–200 original-image pixels, making one seamless panoramic photograph approximately 2176×793 pixels. Preserve the original image composition and excavator identity, boom, bucket, tracks, yellow body, existing CAT markings, rocky terrain, sunset, cloud patterns and dark negative space on the left as faithfully as possible. Complete the small portions of the machine currently cut by the right image boundary. The entire excavator, including the rear body and both tracks, must fit within the frame, with a clear margin of realistic quarry scenery to its right, approximately 6–7% of the final image width. Outpaint the continuation of clouds, horizon and rocky ground with matching camera perspective, texture and light. This is a single continuous realistic photograph, no seams, mirrored strips, blurred edge panels, borders, graphic overlays, added text or additional vehicles. Leave the left half dark and uncluttered for existing HTML text. Do not add any website interface or text. Keep panoramic proportions about 2.7:1. The key edit is more complete scenery at the right and breathing room between the excavator and right edge.
