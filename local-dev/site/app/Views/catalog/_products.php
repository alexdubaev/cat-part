<?php declare(strict_types=1); ?>
    <?php if ($products === []): ?>
        <p class="cp-empty">По выбранным условиям товары не найдены. Измените фильтры или напишите номер детали на
            <a href="mailto:<?= e(brand_setting('email', cfg('site.email'))) ?>"><?= e(brand_setting('email', cfg('site.email'))) ?></a> —
            проверим наличие и подберём аналог.</p>
    <?php else: ?>
        <div class="cp-grid cp-grid--catalog-products">
            <?php foreach ($products as $p): ?>
                <?php
                $href  = url('/tovar/' . (string) $p['slug']);
                $price = (float) ($p['price'] ?? 0);
                $sku   = trim((string) ($p['sku'] ?? ''));
                $img   = product_image_url((string) ($p['image_url'] ?? ''));
                ?>
                <article class="cp-prod">
                    <a class="cp-prod__img" href="<?= e($href) ?>" tabindex="-1" aria-hidden="true">
                        <?php if ($img !== ''): ?>
                            <img src="<?= e($img) ?>" alt="" loading="lazy" width="240" height="180">
                        <?php else: ?>
                            <?php /* Фото есть далеко не у всех позиций — знак вместо
                                     битой картинки держит сетку ровной. */ ?>
                            <svg class="cp-prod__ph" aria-hidden="true"><use href="#cp-i-part"></use></svg>
                        <?php endif; ?>
                    </a>
                    <div class="cp-prod__body">
                        <?php if ($sku !== ''): ?>
                            <span class="cp-prod__sku"><?= e($sku) ?></span>
                        <?php endif; ?>
                        <a class="cp-prod__name" href="<?= e($href) ?>"><?= e($p['name']) ?></a>

                        <?php if (!empty($p['in_stock'])): ?>
                            <span class="cp-prod__stock cp-prod__stock--in">В наличии</span>
                        <?php else: ?>
                            <span class="cp-prod__stock cp-prod__stock--out">Под заказ</span>
                        <?php endif; ?>

                        <div class="cp-prod__foot">
                            <?php /* money() сам отдаёт «по запросу» при NULL/0 — но стиль
                                     у цены и у запроса разный, отсюда отдельный класс. */ ?>
                            <span class="cp-prod__price<?= $price > 0 ? '' : ' cp-prod__price--ask' ?>"><?= e(money($p['price'] ?? null, (string) ($p['currency'] ?? 'RUB'))) ?></span>
                            <form method="post" action="<?= e(url('/korzina/add')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= e((string) $p['id']) ?>">
                                <button class="cp-btn cp-btn--light cp-btn--sm" type="submit">В корзину</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <?php
            /**
             * 🔥 Баг донора: пагинация печатала ВСЕ номера страниц подряд
             * (`for ($i = 1; $i <= $pages; $i++)`). При 22 000 товаров по 24 на
             * страницу это 833 ссылки в каждом ответе — лишние килобайты,
             * нечитаемая «простыня» и размытый краулинговый бюджет.
             *
             * Окно ±3 + первая/последняя + многоточия считает paginate_window(),
             * адреса собирает page_url() — те же функции зовёт вьюха поиска.
             * Своя копия логики в шаблоне означала бы, что листание каталога и
             * листание поиска однажды разъедутся. page_url() к тому же сам
             * выбирает '?' или '&' — для /poisk?q=… это обязательно.
             */
            ?>
            <nav class="cp-pager" aria-label="Страницы раздела">
                <?php if ($pageNum > 1): ?>
                    <a class="cp-pager__nav" href="<?= e(page_url($baseUrl, $pageNum - 1)) ?>" rel="prev">← Назад</a>
                <?php endif; ?>

                <?php foreach (paginate_window($pageNum, $pages) as $item): ?>
                    <?php if ($item === '…'): ?>
                        <span class="cp-pager__gap" aria-hidden="true">…</span>
                    <?php elseif ((int) $item === $pageNum): ?>
                        <span class="cp-pager__cur" aria-current="page"><?= e((string) $item) ?></span>
                    <?php else: ?>
                        <a href="<?= e(page_url($baseUrl, (int) $item)) ?>"
                           aria-label="Страница <?= e((string) $item) ?>"><?= e((string) $item) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($pageNum < $pages): ?>
                    <a class="cp-pager__nav" href="<?= e(page_url($baseUrl, $pageNum + 1)) ?>" rel="next">Вперёд →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
