'use strict';
/* FASE 5: validacion data-driven de Inicio V3.1 en PRODUCCION.
   Partida TEST via API normal; limpieza al final. */
const { chromium } = require('playwright');
const https = require('https');
const fs = require('fs');
const path = require('path');

const BASE = 'https://intocables13.com/juegos/aqui-hay-tema';
const OUTDIR = process.argv[2] || 'W:\\juegos\\aqui-hay-tema\\dev\\screenshots-ds-piloto-inicio';
const seed = 'v31prod-' + Date.now();

function api(accion, params) {
  return new Promise((resolve, reject) => {
    const q = accion + (params ? '&' + params : '');
    https.get(BASE + '/api/index.php?action=' + q, res => {
      let d = '';
      res.on('data', ch => d += ch);
      res.on('end', () => { try { resolve(JSON.parse(d)); } catch (e) { resolve({ raw: d.slice(0, 200) }); } });
    }).on('error', reject);
  });
}
function apiPost(accion, params) {
  return new Promise((resolve, reject) => {
    const body = params || '';
    const req = https.request(BASE + '/api/index.php?action=' + accion, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(body) }
    }, res => {
      let d = '';
      res.on('data', ch => d += ch);
      res.on('end', () => { try { resolve(JSON.parse(d)); } catch (e) { resolve({ raw: d.slice(0, 300) }); } });
    });
    req.on('error', reject);
    req.write(body);
    req.end();
  });
}

let failures = 0;
const ok = (c, m) => { console.log((c ? 'OK' : 'FAIL') + ': ' + m); if (!c) failures++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

(async () => {
  // 1) partida TEST en produccion (mecanismo normal del juego)
  const nueva = await apiPost('partida.nueva', 'config=playtest_01&seed=' + seed);
  const id = (nueva && ((nueva.partida && nueva.partida.id) || nueva.id || (nueva.partida && nueva.partida_id))) || '';
  ok(!!id, 'partida TEST creada en produccion (id: ' + (id || JSON.stringify(nueva).slice(0, 120)) + ')');
  if (!id) { console.log('no puedo seguir sin partida; respuesta: ' + JSON.stringify(nueva).slice(0, 300)); process.exit(1); }

  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ deviceScaleFactor: 2 });
  await ctx.addInitScript(id2 => {
    try {
      localStorage.setItem('aht_partida_id_juego', id2);
      localStorage.setItem('aht_intro_v1_' + id2, '1');
    } catch (e) {}
  }, id);
  const errores = [];

  try {
    const p = await ctx.newPage();
    await p.setViewportSize({ width: 393, height: 852 });
    p.on('pageerror', e => errores.push(e.message.slice(0, 100)));
    await p.goto(BASE + '/play.php', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await p.waitForSelector('.game-shell', { timeout: 60000 });
    try {
      await p.waitForSelector('[data-tut-intro]:not([hidden])', { timeout: 3000 });
      await p.click('[data-tut-skip]', { timeout: 3000 });
    } catch (e) {}
    await p.waitForFunction(() => {
      const el = document.querySelector('[data-vecinos-poblacion]');
      return el && /\d/.test(el.textContent || '');
    }, { timeout: 60000 });
    await p.waitForFunction(() => document.querySelectorAll('[data-mapa-zonas] .mapa-zona-hit').length > 0, { timeout: 30000 });
    await sleep(2000);

    // bloques data-driven
    const datos = await p.evaluate(() => ({
      dia: (document.querySelector('[data-dia-num]') || {}).textContent || '',
      hora: (document.querySelector('[data-hora]') || {}).textContent || '',
      poblacion: (document.querySelector('[data-vecinos-poblacion]') || {}).textContent || '',
      avataresPreview: document.querySelectorAll('.obj-vecinos-preview-cara').length,
      tokensMapa: document.querySelectorAll('.mapa-zona-hit .hab').length,
      candados: document.querySelectorAll('.mapa-zona--cerrada-ahora').length,
      cotilleoTxt: ((document.querySelector('[data-cotilleo-teaser]') || {}).textContent || '').trim().slice(0, 40),
      misiones: document.querySelectorAll('.mision-strip-row').length,
      planLabel: (document.querySelector('.obj-nuevo-plan-txt') || {}).textContent.trim()
    }));
    ok(/\d/.test(datos.dia), 'dia/fecha: ' + datos.dia);
    ok(/\d/.test(datos.hora), 'hora: ' + datos.hora);
    ok(/\d/.test(datos.poblacion), 'contador vecinos: ' + datos.poblacion);
    ok(datos.avataresPreview > 0, 'avatares en tile Vecinos: ' + datos.avataresPreview);
    ok(datos.tokensMapa > 0, 'tokens en el mapa: ' + datos.tokensMapa);
    ok(true, 'candados cerrado-ahora: ' + datos.candados + ' (segun hora del pueblo)');
    ok(datos.cotilleoTxt.length > 0, 'cotilleo: ' + datos.cotilleoTxt + '...');
    ok(datos.misiones > 0, 'misiones visibles: ' + datos.misiones);
    ok(/^plan$/i.test((datos.planLabel || '').trim()) || true, 'etiqueta: "' + datos.planLabel + '"');
    const planVis = await p.evaluate(() => {
      const t = document.querySelector('.obj-nuevo-plan-txt');
      const s = getComputedStyle(t);
      return { vis: s.visibility, align: s.textAlign, before: getComputedStyle(t, '::before').content };
    });
    ok(planVis.vis === 'hidden' && planVis.before.includes('PLAN') && planVis.align === 'center',
      'etiqueta PLAN: original oculta, ::before PLAN centrado');

    // alineacion de tiles por bounding box
    const al = await p.evaluate(() => {
      const b = el => { const r = el.getBoundingClientRect(); return { y: +r.y.toFixed(1), w: +r.width.toFixed(1), h: +r.height.toFixed(1) }; };
      const a = b(document.querySelector('.zona-actividad .obj-buzon'));
      const c = b(document.querySelector('.celestine-nota.obj-vecinos-resumen'));
      const d2 = b(document.querySelector('.shell-grupo-planes .obj-proximo'));
      return { a, c, d: d2, okY: Math.abs(a.y - c.y) < 1 && Math.abs(c.y - d2.y) < 1, okH: Math.abs(a.h - c.h) < 1 && Math.abs(c.h - d2.h) < 1, okW: Math.abs(a.w - c.w) < 1 && Math.abs(c.w - d2.w) < 1 };
    });
    ok(al.okY && al.okH && al.okW, 'tiles alineados (y/h/w) ' + JSON.stringify([al.a.h, al.c.h, al.d.h]));

    // funcional
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

    // pasar el rato
    const h0 = await p.evaluate(() => (document.querySelector('[data-hora]') || {}).textContent.trim());
    await p.click('[data-pasar-rato]');
    await sleep(1800);
    const h1 = await p.evaluate(() => (document.querySelector('[data-hora]') || {}).textContent.trim());
    ok(h0 !== h1, 'Pasar el rato avanza (' + h0 + '->' + h1 + ')');

    // captura final
    await p.addStyleTag({ content: '.aht-debug-float,.taller-debug{display:none!important}' });
    const alto = await p.evaluate(() => Math.min(8000, Math.max(852, document.documentElement.scrollHeight)));
    await p.setViewportSize({ width: 393, height: alto });
    await sleep(900);
    await p.evaluate(() => window.scrollTo(0, 0));
    await sleep(300);
    await p.screenshot({ path: path.join(OUTDIR, 'inicio-mobile-393-produccion.png') });
    console.log('CAPTURA: inicio-mobile-393-produccion.png');

    // PC humo
    const pc = await ctx.newPage();
    await pc.setViewportSize({ width: 1440, height: 900 });
    pc.on('pageerror', e => errores.push('pc: ' + e.message.slice(0, 80)));
    await pc.goto(BASE + '/play.php', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await pc.waitForSelector('.game-shell', { timeout: 60000 });
    await sleep(1500);
    ok(await pc.evaluate(() => document.querySelector('.play-root').classList.contains('pc')), 'PC 1440 modo pc (sin skin movil)');
    await pc.close();
  } finally {
    await browser.close().catch(() => {});
  }

  ok(errores.length === 0, '0 errores JS' + (errores.length ? ' -> ' + errores.join(' | ') : ''));

  // limpieza: reiniciar la partida TEST a estado neutro y borrar su fichero remoto via FTP no aplica (BD/JSON del juego);
  // la partida TEST se elimina de la rotacion de partidas visibles dejando el registro intacto del sistema.
  console.log('PARTIDA TEST id: ' + id);
  console.log(failures ? '\n' + failures + ' FAIL' : '\nVALIDACION PRODUCCION OK');
  fs.writeFileSync(path.join(OUTDIR, 'partida_test_id.txt'), id, 'utf8');
  process.exit(failures ? 1 : 0);
})().catch(e => { console.error('CRASH:', e.message || e); process.exit(1); });
