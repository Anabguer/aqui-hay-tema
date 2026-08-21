/**
 * Test buzón fix: load partida with candidato_llegada + peticiones, verify no crash.
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const PARTIDA = 'part_e1670c8cc0ee6770.json';
const partidaPath = path.join(__dirname, '..', 'data', 'partidas', PARTIDA);
const partida = JSON.parse(fs.readFileSync(partidaPath, 'utf8'));
const buzonMsgs = (partida.buzon || []).filter((m) => (m.canal || 'buzon') !== 'cotilleo');

(async () => {
  const errors = [];
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  page.on('pageerror', (e) => errors.push(String(e)));
  page.on('console', (msg) => {
    if (msg.type() === 'error') errors.push(msg.text());
  });

  await page.goto('http://localhost:8765/play.php?lab=1&config=playtest_01', {
    waitUntil: 'networkidle',
    timeout: 90000,
  });

  // Inject saved partida via API if possible — use localStorage partida id
  const partidaId = PARTIDA.replace('.json', '').replace('part_', 'part_');
  await page.evaluate(async (pid) => {
    localStorage.setItem('aht_partida_id', pid);
  }, 'part_e1670c8cc0ee6770');

  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForSelector('.game-shell', { timeout: 30000 });
  await page.waitForTimeout(3500);

  const audit1 = await page.evaluate(() => ({
    cartas: document.querySelectorAll('[data-buzon-list] .carta-msg').length,
    vacio: !!document.querySelector('[data-buzon-list] .lista-vacia'),
  }));

  // Open buzon
  await page.click('.obj-buzon');
  await page.waitForTimeout(800);
  const audit2 = await page.evaluate(() => ({
    capa: document.querySelector('.play-root')?.getAttribute('data-capa'),
    cartas: document.querySelectorAll('[data-buzon-list] .carta-msg').length,
    textos: Array.from(document.querySelectorAll('[data-buzon-list] .cuerpo')).map((el) => el.textContent.slice(0, 50)),
  }));

  // Close and reopen
  await page.click('.velo').catch(() => {});
  await page.waitForTimeout(400);
  await page.click('.obj-buzon');
  await page.waitForTimeout(600);

  // Trigger refresh via playtest or evaluate
  await page.evaluate(async () => {
    if (typeof refresh === 'function') await refresh();
  }).catch(() => {});

  await page.waitForTimeout(2500);

  const audit3 = await page.evaluate(() => ({
    cartas: document.querySelectorAll('[data-buzon-list] .carta-msg').length,
  }));

  await browser.close();

  const typeErrors = errors.filter((e) => e.includes('TypeError') || e.includes('play-v3.js'));
  console.log(JSON.stringify({
    partidaMsgs: buzonMsgs.map((m) => ({
      id: m.id,
      tipo: m.tipo,
      de_persona: m.de_persona,
      candidato_catalog_id: m.candidato_catalog_id,
      actores: m.actores,
      estado: m.estado,
    })),
    audit1,
    audit2,
    audit3,
    typeErrors,
    allErrors: errors.slice(0, 5),
  }, null, 2));
})();
