'use strict';
/* Captura cada .prop del arnes de propuestas -> dev/screenshots-ds-propuestas/ */
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', '..').split('/').join('\\');
const OUT = path.join(ROOT, 'dev', 'screenshots-ds-propuestas');
const PORT = 8169;
const NAMES = {
  p02: '02-mensajitos',
  p03: '03-cotilleos',
  p04: '04-vecinos',
  p05: '05-relaciones',
  p06: '06-ficha-vecino',
  p07: '07-estado-animo',
  p08: '08-diario-vecino',
  p09: '09-nuevo-plan',
  p10: '10-agenda',
  p11: '11-misiones-modal',
  p12: '12-vida-pueblo',
  p13: '13-intervencion-encuentro',
  p14: '14-notas-mapa',
  p15: '15-tutorial',
  p16: '16-avisos-derrota'
};

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const srv = spawn('php', ['-S', '127.0.0.1:' + PORT, '-t', ROOT], { stdio: 'ignore' });
  await new Promise(r => setTimeout(r, 1200));
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 393, height: 900 }, deviceScaleFactor: 2 });
  const url = 'http://127.0.0.1:' + PORT + '/dev/propuestas/arnes.html';
  const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  console.log('HTTP', resp.status(), '->', url);
  await new Promise(r => setTimeout(r, 2500));
  const ids = await page.evaluate(() =>
    Array.from(document.querySelectorAll('.prop')).map(el => el.id));
  console.log('secciones:', ids.length, JSON.stringify(ids));
  for (const id of ids) {
    const el = await page.$('#' + id);
    if (!el) continue;
    const name = NAMES[id] || id;
    await el.screenshot({ path: path.join(OUT, name + '.png') });
    console.log('CAPTURA: ' + name + '.png');
  }
  await browser.close();
  srv.kill();
  console.log('LISTO: ' + ids.length + ' propuestas');
})().catch(e => { console.error(e); process.exit(1); });
