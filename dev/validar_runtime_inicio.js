'use strict';
/* Captura del RUNTIME REAL (sin partida: la API exige MySQL local no disponible).
   Valida la integracion visual de los bloques estaticos + humo funcional de capas. */
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const DOCROOT = process.argv[2];
const OUTDIR = process.argv[3];
const PORT = 8182;
const BASE = 'http://127.0.0.1:' + PORT;

let failures = 0;
const ok = (c, m) => { console.log((c ? 'OK' : 'FAIL') + ': ' + m); if (!c) failures++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

(async () => {
  fs.mkdirSync(OUTDIR, { recursive: true });
  const srv = spawn('php', ['-S', '127.0.0.1:' + PORT, '-t', DOCROOT], { stdio: 'ignore' });
  await sleep(1200);
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ deviceScaleFactor: 2 });
  const errores = [];

  try {
    const p = await ctx.newPage();
    await p.setViewportSize({ width: 393, height: 852 });
    p.on('pageerror', e => errores.push(e.message.slice(0, 100)));
    await p.goto(BASE + '/play.php', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await p.waitForSelector('.game-shell', { timeout: 60000 });
    await sleep(2500);

    // integracion CSS: los 3 tiles con marco familia (borde tinta 2px)
    const tiles = await p.evaluate(() => {
      const b = document.querySelector('.zona-actividad .obj-buzon');
      const v = document.querySelector('.celestine-nota.obj-vecinos-resumen');
      const n = document.querySelector('.obj-nuevo-plan');
      const box = el => { const r = el.getBoundingClientRect(); return { y: +r.y.toFixed(1), w: +r.width.toFixed(1), h: +r.height.toFixed(1) }; };
      return {
        bordeB: getComputedStyle(b).borderTopWidth,
        bordeN: getComputedStyle(n).borderTopWidth,
        planLabel: (n.querySelector('.obj-nuevo-plan-txt::before') ? 'pseudo' : n.querySelector('.obj-nuevo-plan-txt').textContent.trim()),
        cajas: { mensajitos: box(b), vecinos: box(v), plan: box(n) }
      };
    });
    ok(tiles.bordeB === '2px' && tiles.bordeN === '2px', 'tiles con marco familia 2px');
    ok(Math.abs(tiles.cajas.mensajitos.y - tiles.cajas.vecinos.y) < 1 &&
       Math.abs(tiles.cajas.vecinos.y - tiles.cajas.plan.y) < 1 &&
       Math.abs(tiles.cajas.mensajitos.h - tiles.cajas.plan.h) < 1 &&
       Math.abs(tiles.cajas.mensajitos.w - tiles.cajas.plan.w) < 1,
       'tiles alineados (y/h/w): ' + JSON.stringify(tiles.cajas));
    ok(/plan/i.test(tiles.planLabel), 'etiqueta PLAN presente');

    // capas abren/cierran (estaticas, sin datos)
    const abreYCierra = async (openSel, capaSel, nombre) => {
      await p.click(openSel, { timeout: 8000 });
      await p.waitForSelector(capaSel, { state: 'visible', timeout: 8000 });
      ok(true, nombre + ' abre');
      await p.click(capaSel + ' [data-close], ' + capaSel + ' .cerrar', { timeout: 8000 });
      await sleep(300);
    };
    await abreYCierra('[data-open="buzon"]', '.capa-buzon', 'Mensajitos');
    await abreYCierra('[data-open="vecinos"]', '.capa-vecinos', 'Vecinos');
    await abreYCierra('[data-open="organizar"]', '.capa-organizar', 'Nuevo Plan');
    await abreYCierra('.obj-cotilleo-par', '.capa-diario', 'Cotilleos');

    // captura del runtime real
    await p.addStyleTag({ content: '.aht-debug-float,.taller-debug{display:none!important}' });
    const alto = await p.evaluate(() => Math.min(8000, Math.max(852, document.documentElement.scrollHeight)));
    await p.setViewportSize({ width: 393, height: alto });
    await sleep(800);
    await p.evaluate(() => window.scrollTo(0, 0));
    await sleep(300);
    await p.screenshot({ path: path.join(OUTDIR, 'inicio-mobile-393-runtime.png') });
    console.log('CAPTURA: inicio-mobile-393-runtime.png');
    await p.close();

    // humo PC
    const pc = await ctx.newPage();
    await pc.setViewportSize({ width: 1440, height: 900 });
    pc.on('pageerror', e => errores.push('pc: ' + e.message.slice(0, 80)));
    await pc.goto(BASE + '/play.php', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await pc.waitForSelector('.game-shell', { timeout: 60000 });
    await sleep(1500);
    ok(await pc.evaluate(() => document.querySelector('.play-root').classList.contains('pc')), 'PC 1440 en modo pc');
    await pc.close();
  } finally {
    await browser.close().catch(() => {});
    srv.kill();
  }

  ok(errores.length === 0, '0 errores JS' + (errores.length ? ' -> ' + errores.join(' | ') : ''));
  console.log(failures ? '\n' + failures + ' FAIL' : '\nVALIDACION OK (sin partida)');
  process.exit(failures ? 1 : 0);
})().catch(e => { console.error('CRASH:', e.message || e); process.exit(1); });
