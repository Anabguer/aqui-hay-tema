const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
  const logs = [];
  page.on('pageerror', e => logs.push(e.message));
  await page.goto('http://127.0.0.1:8765/play.php?lab=1&config=playtest_01', { waitUntil: 'networkidle' });
  await page.waitForTimeout(3000);
  async function test(name, click) {
    await click();
    await page.waitForTimeout(400);
    const s = await page.evaluate(() => ({
      capa: document.querySelector('.play-root')?.getAttribute('data-capa'),
      visible: [...document.querySelectorAll('.capa')].filter(c => getComputedStyle(c).visibility === 'visible').map(c => c.className)
    }));
    await page.locator('.capa .cerrar[data-close]').first().click();
    await page.waitForTimeout(300);
    const closed = await page.evaluate(() => document.querySelector('.play-root')?.getAttribute('data-capa'));
    console.log(name, 'open', s, 'closed', closed);
    return s.visible.length === 1 && s.capa === name.replace('open-','') && closed === null;
  }
  const r1 = await test('buzon', () => page.locator('.obj-buzon').click());
  const r2 = await test('organizar', () => page.locator('.game-left [data-open=organizar], .dock [data-open=organizar]').first().click());
  const r3 = await test('vecinos', () => page.locator('.obj-vecinos-resumen').click());
  const r4 = await test('agenda', () => page.evaluate(() => { const r=document.querySelector('.play-root'); r.setAttribute('data-capa','agenda'); r.setAttribute('data-capa-origin','left'); }));
  console.log('ALL', r1&&r2&&r3&&r4, 'ERRS', logs);
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
