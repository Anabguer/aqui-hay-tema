const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
  const errs = [];
  page.on('pageerror', e => errs.push(e.message));
  await page.goto('http://127.0.0.1:8765/play.php?lab=1&config=playtest_01', { waitUntil: 'networkidle' });
  await page.waitForTimeout(3000);
  for (const [name, sel] of [
    ['buzon', '.obj-buzon'],
    ['organizar', '.obj-nuevo-plan'],
    ['vecinos', '.obj-vecinos-resumen'],
  ]) {
    await page.locator(sel).click();
    await page.waitForTimeout(400);
    const open = await page.evaluate(() => ({
      capa: document.querySelector('.play-root')?.getAttribute('data-capa'),
      visible: [...document.querySelectorAll('.capa')].filter(c => getComputedStyle(c).visibility === 'visible').length
    }));
    await page.locator('.capa-' + name + ' .cerrar').click();
    await page.waitForTimeout(300);
    const closed = await page.evaluate(() => document.querySelector('.play-root')?.getAttribute('data-capa'));
    console.log(name, open, 'closed', closed === null);
  }
  console.log('ERRS', errs);
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
