'use strict';
/* Sonda de alineacion por pixeles: los tres tiles del inicio deben compartir
   bounding box (misma y, misma altura, mismo ancho) y estar en la misma linea. */
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const PORT = process.env.PORT || 8137;
const DOCROOT = process.argv[2] || 'C:\\Users\\agl03\\AppData\\Local\\Temp\\opencode\\aht-ds-www';
(async () => {
  const srv = spawn('php', ['-S', '127.0.0.1:' + PORT, '-t', DOCROOT], { stdio: 'ignore' });
  await new Promise(r => setTimeout(r, 1200));
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 393, height: 852 } });
  await page.goto('http://127.0.0.1:' + PORT + '/play.php?config=playtest_01&seed=align-' + Date.now(),
    { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForSelector('.game-shell', { timeout: 60000 });
  await new Promise(r => setTimeout(r, 2500));
  const r = await page.evaluate(() => {
    const box = el => {
      const b = el.getBoundingClientRect();
      return { x: +b.x.toFixed(1), y: +b.y.toFixed(1), w: +b.width.toFixed(1), h: +b.height.toFixed(1) };
    };
    return {
      mensajitos: box(document.querySelector('.zona-actividad .obj-buzon')),
      vecinos: box(document.querySelector('.celestine-nota.obj-vecinos-resumen')),
      plan: box(document.querySelector('.shell-grupo-planes .obj-proximo'))
    };
  });
  const { mensajitos: a, vecinos: b, plan: c } = r;
  const sameY = Math.abs(a.y - b.y) < 0.5 && Math.abs(b.y - c.y) < 0.5;
  const sameH = Math.abs(a.h - b.h) < 0.5 && Math.abs(b.h - c.h) < 0.5;
  const sameW = Math.abs(a.w - b.w) < 0.5 && Math.abs(b.w - c.w) < 0.5;
  console.log(JSON.stringify(r, null, 1));
  console.log('misma Y:', sameY, '| misma altura:', sameH, '| mismo ancho:', sameW);
  await browser.close();
  srv.kill();
  process.exit(sameY && sameH && sameW ? 0 : 1);
})().catch(e => { console.error(e); process.exit(1); });
