// Run with the cached Playwright package available through NODE_PATH.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { chromium } = require('playwright');

const base = 'http://127.0.0.1:8080';
const expected = ['Двигатель', 'Гидравлика', 'Силовая передача', 'Ходовая часть', 'Шланги и трубки', 'Кабины'];
const output = fs.mkdtempSync(path.join(os.tmpdir(), 'catpart-catalog-e2e-'));

async function geometry(page) {
  return page.locator('.cp-section--catalog').evaluate(section => {
    const cards = [...section.querySelectorAll('.cp-cat')].filter(card => card.checkVisibility());
    const control = [...section.querySelectorAll('button, summary')].find(el => el.checkVisibility());
    const box = el => {
      const rect = el.getBoundingClientRect();
      return { top: rect.top + scrollY, bottom: rect.bottom + scrollY, height: rect.height };
    };
    return {
      cards: cards.map(box),
      control: control ? box(control) : null,
      names: cards.map(card => card.querySelector('.cp-cat__name').textContent.trim()),
      contentsFit: cards.every(card => {
        const outer = card.getBoundingClientRect();
        const text = card.querySelector('.cp-cat__txt').getBoundingClientRect();
        return text.top >= outer.top && text.bottom <= outer.bottom;
      }),
      overflow: document.documentElement.scrollWidth > innerWidth,
    };
  });
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [1920, 390]) {
      const page = await browser.newPage({ viewport: { width, height: 1080 } });
      await page.goto(base, { waitUntil: 'networkidle' });
      const cookieButton = page.locator('#cpCookieOk');
      if (await cookieButton.isVisible()) {
        await cookieButton.click();
        await page.locator('#cpCookie').waitFor({ state: 'hidden' });
      }
      await page.mouse.move(0, 0); // Keep the existing hover lift out of layout measurements.
      await page.waitForFunction(() => [...document.querySelectorAll('.cp-section--catalog .cp-cat')].every(card => getComputedStyle(card).transform === 'none'));
      await page.evaluate(() => document.fonts.ready);
      const closed = await geometry(page);
      assert.deepEqual(closed.names, expected, 'Only the six selected categories should be visible, in order');
      const defects = [];
      if (!closed.cards.every(card => card.height >= 80 && card.height <= 84)) defects.push('featured category cards must be about 30% shorter than 116px');
      if (!closed.contentsFit) defects.push('category text must fit within its card');
      if (!closed.control || closed.control.bottom > closed.cards[0].top) defects.push('catalog control must be above every card');
      assert.equal(defects.length, 0, defects.join('; '));
      if (width === 1920) assert.equal(new Set(closed.cards.map(card => card.top)).size, 1, 'Desktop categories must occupy one row');
      assert.equal(closed.overflow, false, 'Collapsed catalog must not overflow horizontally');
      await page.locator('.cp-section--catalog').screenshot({ path: path.join(output, `catalog-${width}-closed.png`) });

      const toggle = page.getByRole('button', { name: 'Весь каталог', exact: true });
      await toggle.click();
      assert.equal(await page.getByRole('button', { name: 'Свернуть каталог', exact: true }).getAttribute('aria-expanded'), 'true', 'Expanded state must be exposed to assistive technology');
      const opened = await geometry(page);
      assert.equal(opened.cards.length, 18, 'Expanding must expose all 18 categories');
      assert.equal(opened.contentsFit, true, 'Long category names must fit without vertical clipping');
      assert.equal(opened.overflow, false, 'Expanded catalog must not overflow horizontally');
      assert.ok(Math.abs(opened.cards[0].height - closed.cards[0].height) <= 1, 'Expanding must preserve the first row card height');
      assert.ok(opened.control.bottom <= opened.cards[0].top, 'Control must remain above the expanded grid');
      assert.ok(Math.abs(opened.control.top - closed.control.top) <= 1, 'Control must stay in the section heading');
      const rows = [...new Set(opened.cards.map(card => card.top))];
      const rowHeights = rows.map(top => {
        const cards = opened.cards.filter(card => card.top === top);
        assert.ok(Math.max(...cards.map(card => card.height)) - Math.min(...cards.map(card => card.height)) <= 1, 'Cards within each row must have equal height');
        return cards[0].height;
      });
      for (let index = 1; index < rows.length; index++) {
        assert.ok(Math.abs(rows[index] - rows[index - 1] - rowHeights[index - 1] - 8) <= 1, 'Rows must have a continuous, regular gap');
      }
      await page.locator('.cp-section--catalog').screenshot({ path: path.join(output, `catalog-${width}-open.png`) });
      await page.getByRole('button', { name: 'Свернуть каталог', exact: true }).press('Enter');
      assert.equal((await geometry(page)).cards.length, 6, 'Enter must collapse the catalog');
      await page.getByRole('button', { name: 'Весь каталог', exact: true }).press('Space');
      assert.equal((await geometry(page)).cards.length, 18, 'Space must expand the catalog');
      console.log(`PASS ${width}: 6 → 18 → 6 → 18, compact cards, equal heights within rows, top control, regular rows, keyboard, no overflow`);
      await page.close();
    }
    const noScript = await browser.newPage({ javaScriptEnabled: false });
    await noScript.goto(base);
    await noScript.locator('.cp-section--catalog').getByRole('link', { name: 'Весь каталог', exact: true }).click();
    assert.equal(new URL(noScript.url()).pathname, '/catalog', 'Without JavaScript the full catalog must remain accessible');
    console.log('PASS without JavaScript: full catalog remains accessible');
    console.log(`Screenshots: ${output}`);
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
