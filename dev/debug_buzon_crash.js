/** Reproduce buzon crash: fetch messages and find null de entries */
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('http://localhost:8765/play.php?lab=1&config=playtest_01', { waitUntil: 'networkidle', timeout: 90000 });
  await page.waitForTimeout(4000);

  const data = await page.evaluate(async () => {
    const api = async (accion) => {
      const r = await fetch('api/index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion }),
      });
      return r.json();
    };
    const buzon = await api('buzon.listar');
    const msgs = (buzon.mensajes || []).filter((m) => (m.canal || 'buzon') !== 'cotilleo');
    const problems = msgs.map((m, i) => ({
      i,
      id: m.id,
      tipo: m.tipo,
      clasificacion: m.clasificacion,
      de_persona: m.de_persona,
      de: m.de,
      actores: m.actores,
      estado: m.estado,
      texto: (m.texto || '').slice(0, 80),
    }));
    return { count: msgs.length, problems, errors: window.__buzonErr || null };
  });

  console.log(JSON.stringify(data, null, 2));

  const consoleErrors = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  page.on('pageerror', (err) => consoleErrors.push(String(err)));

  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(3000);
  console.log('console errors:', consoleErrors.filter((e) => e.includes('play-v3') || e.includes('TypeError')));

  await browser.close();
})();
