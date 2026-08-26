/**
 * Capturas POST móvil + desktop para réplica visual.
 * Uso: node dev/visual_replica_capture.js [outDir] [iterN] [screenId]
 */
const { chromium } = require('playwright');
const path = require('path');

const BASE = 'http://127.0.0.1:8765/dev/visual_validate_content.php';
const OUT = process.argv[2] || path.join(__dirname, 'visual-replica-evidence');
const ITER = process.argv[3] || '4';
const ONLY = process.argv[4] || '';

const SCREENS = [
  { id: 'inicio', partida: 'e2erit-part_5af4821', capa: 'inicio', sel: '.game-shell', wait: 4500 },
  { id: 'mensajitos', partida: 'e2erit-part_5af4821', capa: 'buzon', sel: '.capa-buzon', wait: 5000 },
  { id: 'vecinos', partida: 'e2erit-part_5af4821', capa: 'vecinos', sel: '.capa-vecinos', wait: 4500 },
  { id: 'relaciones', partida: 'e2erit-part_5af4821', capa: 'vecinos_rel', sel: '.capa-vecinos', wait: 4500 },
  { id: 'ficha', partida: 'e2erit-part_5af4821', capa: 'ficha', sel: '.capa-ficha', wait: 5000 },
  { id: 'estado-animo', partida: 'e2erit-part_5af4821', capa: 'ficha_animo', sel: '.ficha-modal-animo', wait: 7000 },
  { id: 'diario', partida: 'e2erit-part_5af4821', capa: 'ficha_diario', sel: '.capa-ficha-diario', wait: 5000 },
  { id: 'cotilleos', partida: 'part_64d94bdea0acca34', capa: 'diario', sel: '.capa-diario', wait: 5000 },
  { id: 'nuevo-plan', partida: 'e2erit-part_5af4821', capa: 'organizar', sel: '.capa-organizar', wait: 4500 },
  { id: 'agenda', partida: 'e2erit-part_5af4821', capa: 'agenda', sel: '.capa-agenda', wait: 4500 },
  { id: 'misiones', partida: 'e2erit-part_5af4821', capa: 'misiones', sel: '.capa-misiones', wait: 4500 },
  { id: 'vida-pueblo', partida: 'e2erit-part_5af4821', capa: 'vida_pueblo', sel: '.capa-vida-pueblo', wait: 4500 },
  { id: 'inventario', partida: 'e2erit-part_5af4821', capa: 'inventario', sel: '.capa-inventario', wait: 4500 },
  { id: 'intervencion', partida: 'e2erit-part_5af4821', capa: 'intervencion', sel: '.enc-int', wait: 6000 },
  { id: 'notas-mapa', partida: 'e2erit-part_5af4821', capa: 'notas_mapa', sel: '.nota-mapa, .quien.nota-mapa', wait: 7000, extra: 'lugar=parque' },
  { id: 'tutorial-1', partida: 'e2erit-part_5af4821', capa: 'tutorial', extra: 'tut_step=1', sel: '.tut-intro .tut-papel, .tut-papel', wait: 4500 },
  { id: 'tutorial-2', partida: 'e2erit-part_5af4821', capa: 'tutorial', extra: 'tut_step=2', sel: '.tut-intro .tut-papel, .tut-papel', wait: 4500 },
  { id: 'tutorial-3', partida: 'e2erit-part_5af4821', capa: 'tutorial', extra: 'tut_step=3', sel: '.tut-intro .tut-papel, .tut-papel', wait: 4500 },
  { id: 'tutorial-4', partida: 'e2erit-part_5af4821', capa: 'tutorial', extra: 'tut_step=4', sel: '.tut-intro .tut-papel, .tut-papel', wait: 4500 },
  { id: 'tutorial-5', partida: 'e2erit-part_5af4821', capa: 'tutorial', extra: 'tut_step=5', sel: 'aside[data-tut-finale] .tut-papel, .tut-papel', wait: 4500 },
];

async function capture(page, s, view) {
  const q = new URLSearchParams({
    partida_id: s.partida,
    capa: s.capa,
    view,
  });
  if (s.extra) {
    s.extra.split('&').forEach((pair) => {
      const [k, v] = pair.split('=');
      if (k) q.set(k, v || '');
    });
  }
  const url = `${BASE}?${q.toString()}`;
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForTimeout(s.wait || 3500);
  const file = path.join(OUT, `${s.id}-post-${view}-iter${ITER}.png`);
  const selectors = (s.sel || '.capa').split(',').map((x) => x.trim()).filter(Boolean);
  let shot = false;
  for (const sel of selectors) {
    const loc = page.locator(sel).first();
    if (!(await loc.count())) continue;
    const visible = await loc.evaluate((el) => {
      const cs = getComputedStyle(el);
      const r = el.getBoundingClientRect();
      return cs.visibility !== 'hidden' && cs.display !== 'none' && cs.opacity !== '0' && r.width > 20 && r.height > 20;
    }).catch(() => false);
    if (!visible) continue;
    await loc.scrollIntoViewIfNeeded().catch(() => {});
    await page.waitForTimeout(300);
    await loc.screenshot({ path: file, timeout: 20000 });
    shot = true;
    break;
  }
  if (!shot) await page.screenshot({ path: file, fullPage: false });
  console.log('OK', file);
}

(async () => {
  const browser = await chromium.launch();
  const list = ONLY ? SCREENS.filter((s) => s.id === ONLY || s.id.startsWith(ONLY)) : SCREENS;
  for (const view of ['mobile', 'desktop']) {
    const vp = view === 'desktop' ? { width: 1280, height: 900 } : { width: 393, height: 852 };
    const page = await browser.newPage({ viewport: vp });
    for (const s of list) {
      await capture(page, s, view);
    }
    await page.close();
  }
  await browser.close();
})();
