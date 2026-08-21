const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
  await page.goto('http://127.0.0.1:8765/play.php?lab=1&config=playtest_01', { waitUntil: 'networkidle' });
  await page.waitForTimeout(3000);
  await page.screenshot({ path: 'W:/juegos/aqui-hay-tema/dev/_regression_initial.png' });
  await page.evaluate(() => {
    const r = document.querySelector('.play-root');
    r.setAttribute('data-capa', 'agenda');
    r.setAttribute('data-capa-origin', 'left');
  });
  await page.waitForTimeout(400);
  const planes = await page.evaluate(() => ({
    capa: document.querySelector('.play-root')?.getAttribute('data-capa'),
    visible: [...document.querySelectorAll('.capa')].filter(c => getComputedStyle(c).visibility === 'visible').map(c => c.className)
  }));
  console.log('PLANES', JSON.stringify(planes));
  await page.screenshot({ path: 'W:/juegos/aqui-hay-tema/dev/_regression_planes.png' });
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
