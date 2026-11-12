// Critical catalog journey against the running local site and its real fixture database.
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const base = process.env.CATPART_TEST_URL || 'http://127.0.0.1:8080';

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
    await page.goto(base + '/catalog');
    if (await page.locator('#cpCookieOk').isVisible()) await page.locator('#cpCookieOk').click();
    assert.equal(await page.locator('.cp-catalog__results .cp-prod').count(), 24);
    assert.equal(await page.locator('.cp-category-tree img, .cp-category-tree svg').count(), 0);
    assert.equal(await page.locator('select[name="brand"]').count(), 0);
    const geometry = await page.evaluate(() => ({
      side: document.querySelector('.cp-catalog-panel').getBoundingClientRect().right,
      grid: document.querySelector('.cp-catalog__results').getBoundingClientRect().left,
      overflow: document.documentElement.scrollWidth > innerWidth,
    }));
    assert(geometry.side < geometry.grid);
    assert.equal(geometry.overflow, false);
    const first = await page.locator('.cp-prod__sku').allTextContents();
    await page.getByRole('link', { name: 'Страница 2', exact: true }).click();
    assert.equal(await page.locator('.cp-prod').count(), 24);
    const second = await page.locator('.cp-prod__sku').allTextContents();
    assert(second.every(sku => !first.includes(sku)), 'Pagination must return different products');
    await page.locator('.cp-category-tree').getByRole('link', { name: 'Гидравлика', exact: true }).click();
    assert.equal(await page.locator('.cp-category-tree [aria-current="page"]').innerText(), 'Гидравлика');
    assert.equal(await page.locator('.cp-prod').count(), 24, 'Parent category must show subtree products immediately');
    assert.equal(await page.locator('.cp-category-branch[open]').count() > 0, true);
    await page.locator('#catalogStock').selectOption('order');
    await page.getByRole('button', { name: 'Применить фильтры', exact: true }).click();
    await page.locator('#catalogSort').selectOption('sku');
    await page.getByRole('button', { name: 'Применить', exact: true }).click();
    await page.getByRole('link', { name: 'Страница 2', exact: true }).click();
    let url = new URL(page.url());
    assert.equal(url.searchParams.get('stock'), 'order');
    assert.equal(url.searchParams.get('sort'), 'sku');
    await page.locator('.cp-category-tree').getByRole('link', { name: 'Все категории', exact: true }).click();
    assert.equal(new URL(page.url()).searchParams.get('page'), null);
    await page.locator('#catalogQuery').fill(first[0].trim());
    await page.getByRole('button', { name: 'Применить фильтры', exact: true }).click();
    assert.equal(await page.locator('.cp-prod').count(), 1, 'Exact article must be found');
    await page.locator('.cp-prod button').click();
    assert(await page.locator('main').innerText().then(text => text.includes(first[0].trim())), 'Selected article must reach cart');
    await page.goto(base + '/catalog?stock=in');
    assert.equal(await page.locator('.cp-prod').count(), 0);
    assert(await page.locator('.cp-empty').isVisible());
    assert.equal(await page.locator('form[action$="/zayavka"]').count(), 1, 'Existing part request form stays available below results');
    await page.goto(base + '/catalog?q=%25');
    assert.equal(await page.locator('.cp-prod').count(), 0, 'Percent must be a literal, not an SQL wildcard');
    let response = await page.goto(base + '/catalog?page=201');
    assert.equal(response.status(), 404);
    response = await page.goto(base + '/catalog?stock[]=in&sort[]=sku&q[]=x&page[]=2');
    assert.equal(response.status(), 200, 'Malformed array inputs must fall back safely');
    assert.equal(await page.locator('.cp-prod').count(), 24);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload();
    assert.equal(await page.locator('.cp-catalog-panel').getAttribute('open'), null);
    await page.locator('.cp-catalog-panel > summary').click();
    assert(await page.locator('#catalogQuery').isVisible());
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await page.locator('.cp-catalog-panel > summary').click();
    assert(await page.locator('.cp-prod').first().isVisible());
    console.log('PASS: immediate products, left text tree, subtree navigation, filter/sort/pagination, article/cart, empty/literal search, page guard, malformed input, mobile disclosure.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
