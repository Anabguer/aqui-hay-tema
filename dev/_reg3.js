const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
  await page.goto('http://127.0.0.1:8765/play.php?lab=1&config=playtest_01', { waitUntil: 'networkidle' });
  await page.waitForTimeout(4000);
  await page.click('.obj-vecinos-resumen');
  await page.waitForTimeout(800);
  const txt = await page.evaluate(() => {
    const list = document.querySelector('[data-vecinos-list]');
    return list ? list.innerText.slice(0, 500) : '';
  });
  console.log('VECINOS TEXT:\n', txt);
  const checks = ['Álex','Raúl','José','Cafetería','Próximo','¿Con quién?','Día'];
  for (const c of checks) console.log(c, txt.includes(c) ? 'in vecinos' : 'not in vecinos');
  const org = async () => {
    await page.click('[data-open="organizar"]');
    await page.waitForTimeout(500);
    return page.evaluate(() => ({
      labels: [...document.querySelectorAll('.capa-organizar label')].map(l => l.textContent),
      options: [...document.querySelectorAll('[data-org-a] option')].slice(0,8).map(o => o.textContent)
    }));
  };
  await page.click('.capa-vecinos .cerrar');
  await page.waitForTimeout(300);
  const orgData = await org();
  console.log('ORG', JSON.stringify(orgData, null, 2));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
