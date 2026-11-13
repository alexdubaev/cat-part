<?php

declare(strict_types=1);

/**
 * Результаты поиска по каталогу.
 *
 * Карточки кликабельны: ведут на /tovar/{slug} (CatalogController::product).
 *
 * @var string                        $q        поисковый запрос (как ввёл человек)
 * @var list<array<string,mixed>>     $products найденные товары
 * @var int                           $total    всего совпадений
 * @var int                           $pageNum  текущая страница
 * @var int                           $pages    всего страниц
 * @var string                        $baseUrl  база для пагинации (уже содержит ?q=)
 */
?>
<section class="cp-section cp-search">
  <div class="cp-container">

    <h1 class="cp-search__title"><?= e((string)(cms_meta('/poisk')['seo_h1']??'Поиск запчастей')) ?></h1>

    <form class="cp-search__form" action="/poisk" method="get" role="search">
      <input
        class="cp-search__input"
        type="search"
        name="q"
        value="<?= e($q) ?>"
        placeholder="Артикул детали или название"
        aria-label="Артикул или название детали"
        autocomplete="off"
        autofocus>
      <button class="cp-btn cp-search__submit" type="submit">Найти</button>
    </form>

    <p class="cp-search__hint">
      Ищем по номеру детали, кросс-номерам и названию. Разделители не важны:
      <span class="cp-search__example">3B-8489</span> и <span class="cp-search__example">3b 8489</span> — одно и то же.
    </p>

    <?php if ($q === ''): ?>

      <?php // Пустой запрос — просто форма. Это не «ничего не найдено» и тем более не 404. ?>

    <?php elseif ($products === []): ?>

      <div class="cp-search__empty">
        <p class="cp-search__empty-title">По запросу «<?= e($q) ?>» ничего не нашлось.</p>
        <p class="cp-search__empty-text">
          Проверьте номер детали или попробуйте более короткий запрос —
          часть артикула тоже найдётся. Либо подберите деталь по <a href="/catalog">разделам каталога</a>.
        </p>
      </div>

    <?php else: ?>

      <p class="cp-search__meta">
        Найдено: <strong><?= (int) $total ?></strong>
      </p>

      <div class="cp-grid cp-search__results">
        <?php foreach ($products as $product): ?>
          <?php $href = url('/tovar/' . (string) $product['slug']); $productImage = product_image_url((string) ($product['image_url'] ?? '')); ?>
          <article class="cp-card">
            <a class="cp-card__media" href="<?= e($href) ?>" tabindex="-1" aria-hidden="true">
              <?php if ($productImage !== ''): ?>
                <img
                  class="cp-card__img"
                  src="<?= e($productImage) ?>"
                  alt="<?= e($product['name']) ?>"
                  loading="lazy"
                  decoding="async" width="240" height="180">
              <?php endif; ?>
            </a>
            <div class="cp-card__body">
              <?php if (!empty($product['sku'])): ?>
                <div class="cp-card__sku"><?= e($product['sku']) ?></div>
              <?php endif; ?>
              <h2 class="cp-card__name"><a href="<?= e($href) ?>"><?= e($product['name']) ?></a></h2>
              <div class="cp-card__price"><?= e(money($product['price'] ?? null, (string) ($product['currency'] ?? 'RUB'))) ?></div>
              <div class="cp-card__stock<?= !empty($product['in_stock']) ? ' cp-card__stock--in' : '' ?>">
                <?= !empty($product['in_stock']) ? 'В наличии' : 'Под заказ' ?>
              </div>
              <form method="post" action="<?= e(url('/korzina/add')) ?>">
                <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">
                <button class="cp-btn cp-btn--light cp-btn--sm" type="submit">В корзину</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($pages > 1): ?>
        <nav class="cp-pager" aria-label="Страницы результатов">
          <?php foreach (paginate_window($pageNum, $pages) as $item): ?>
            <?php if ($item === '…'): ?>
              <span class="cp-pager__gap" aria-hidden="true">…</span>
            <?php elseif ((int) $item === $pageNum): ?>
              <span class="cp-pager__item cp-pager__item--current" aria-current="page"><?= (int) $item ?></span>
            <?php else: ?>
              <a class="cp-pager__item" href="<?= e(page_url($baseUrl, (int) $item)) ?>"><?= (int) $item ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>

    <?php endif; ?>

  </div>
</section>
