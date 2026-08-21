/** Advance time + refresh, ensure buzon still renders */
const { chromium } = require('playwright');

(async () => {
  const errors = [];
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  page.on('pageerror', (e) => errors.push(String(e)));

  await page.goto('http://localhost:8765/play.php?lab=1&config=playtest_01', { waitUntil: 'networkidle', timeout: 90000 });
  await page.evaluate(() => localStorage.setItem('aht_partida_id', 'part_e1670c8cc0ee6770'));
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(3000);

  const r = await page.evaluate(async () => {
    const api = async (accion, body = {}) => {
      const res = await fetch('api/index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion, ...body }),
      });
      return res.json();
    };
    await api('reloj.avanzar', { horas: 6 });
    return api('buzon.listar');
  });

  await page.waitForTimeout(2000);
  await page.click('.obj-buzon');
  await page.waitForTimeout(800);
  const n = await page.evaluate(() => document.querySelectorAll('[data-buzon-list] .carta-msg').length);

  await browser.close();
  console.log(JSON.stringify({
    buzonCount: (r.mensajes || []).filter((m) => (m.canal || 'buzon') !== 'cotilleo').length,
    rendered: n,
    typeErrors: errors.filter((e) => e.includes('TypeError')),
  }, null, 2));
})();
