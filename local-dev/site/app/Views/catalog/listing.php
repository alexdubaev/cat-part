<?php
declare(strict_types=1);
$byParent = [];
foreach ($navigation as $node) $byParent[(int) ($node['parent_id'] ?? 0)][] = $node;
$selectedId = (int) ($category['id'] ?? 0);
$activeIds = array_map('intval', $activeIds);
$renderTree = static function (int $parent, int $depth = 0) use (&$renderTree, $byParent, $activeIds, $selectedId, $suffix): void {
    if ($depth > 12) return;
    foreach ($byParent[$parent] ?? [] as $node) {
        $id = (int) $node['id'];
        $active = $id === $selectedId;
        $link = '<a class="cp-category-link' . ($active ? ' is-active' : '') . '" href="' . e(url('/catalog/' . $node['slug'] . $suffix)) . '"' . ($active ? ' aria-current="page"' : '') . '>' . e($node['name']) . '</a>';
        if (!empty($byParent[$id])) {
            echo '<details class="cp-category-branch"' . (in_array($id, $activeIds, true) ? ' open' : '') . '><summary>' . $link . '</summary><div class="cp-category-children">';
            $renderTree($id, $depth + 1);
            echo '</div></details>';
        } else echo $link;
    }
};
?>
<section class="cp-wrap cp-section cp-catalog">
    <h1 class="cp-h1"><?= e($h1) ?></h1>
    <?php if (!empty($brand['logo_url'])): ?><img src="<?= e($brand['logo_url']) ?>" alt="<?= e($brand['name']) ?>" width="140" style="max-height:80px;object-fit:contain;margin-bottom:20px"><?php endif; ?>
    <?php if (!empty($brand['intro_html'])): ?><div class="cp-prose" style="margin-bottom:24px"><?= $brand['intro_html'] ?></div><?php endif; ?>
    <?php if (!empty($category['image_url'])): ?><img class="cp-category-image" src="<?= e(product_image_url((string)$category['image_url'])) ?>" alt="<?= e((string)$category['name']) ?>" width="140" height="100"><?php endif; ?>
    <div class="cp-catalog__layout">
        <details class="cp-catalog-panel" open>
            <summary>Фильтры и категории</summary>
            <div class="cp-catalog-panel__body">
                <form class="cp-catalog-filters" action="<?= e(url($route)) ?>" method="get">
                    <?php if ($sort !== 'default'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
                    <label for="catalogQuery">Артикул или название</label>
                    <input id="catalogQuery" type="search" name="q" value="<?= e($query) ?>" maxlength="120" placeholder="Номер детали">
                    <label for="catalogStock">Наличие</label>
                    <select id="catalogStock" name="stock">
                        <option value=""<?= $stock === '' ? ' selected' : '' ?>>Любое</option>
                        <option value="in"<?= $stock === 'in' ? ' selected' : '' ?>>В наличии</option>
                        <option value="order"<?= $stock === 'order' ? ' selected' : '' ?>>Под заказ</option>
                    </select>
                    <button class="cp-btn cp-btn--light cp-btn--sm" type="submit">Применить фильтры</button>
                    <?php if ($query !== '' || $stock !== ''): ?><a href="<?= e(url($route)) ?>">Сбросить фильтры</a><?php endif; ?>
                </form>
                <nav class="cp-category-tree" aria-label="Категории каталога">
                    <h2>Категории</h2>
                    <a class="cp-category-link<?= $category === null ? ' is-active' : '' ?>" href="<?= e(url('/catalog' . $suffix)) ?>"<?= $category === null ? ' aria-current="page"' : '' ?>>Все категории</a>
                    <?php $renderTree(0); ?>
                </nav>
            </div>
        </details>
        <div class="cp-catalog__results">
            <form class="cp-catalog-sort" action="<?= e(url($route)) ?>" method="get">
                <span>Найдено: <strong><?= e(number_format($total, 0, ',', ' ')) ?></strong><?php if ($pageNum > 1): ?> · страница <?= e((string) $pageNum) ?><?php endif; ?></span>
                <?php if ($stock !== ''): ?><input type="hidden" name="stock" value="<?= e($stock) ?>"><?php endif; ?>
                <?php if ($query !== ''): ?><input type="hidden" name="q" value="<?= e($query) ?>"><?php endif; ?>
                <label class="cp-vh" for="catalogSort">Сортировка</label>
                <select name="sort" id="catalogSort">
                    <?php foreach (['default' => 'По умолчанию', 'name' => 'По названию', 'sku' => 'По артикулу', 'price' => 'Цена по возрастанию', 'price_desc' => 'Цена по убыванию'] as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="cp-btn cp-btn--light cp-btn--sm">Применить</button>
            </form>
            <?php require __DIR__ . '/_products.php'; ?>
        </div>
    </div>
</section>
<?php if (!empty($category['intro_html'])): ?>
<section class="cp-wrap cp-section cp-section--tight"><div class="cp-doc"><div class="cp-prose"><?= $category['intro_html'] ?></div></div></section>
<?php endif; ?>
<?php
$formSource = 'catalog';
$formTitle = 'Не нашли нужную деталь?';
require __DIR__ . '/../partials/leadform.php';
?>
