'use strict';
/* DS FASE 3 · PILOTO INICIO MÃ“VIL (pasada 2) â€” E2E + capturas.
 *
 * Uso:
 *   node dev/ds_piloto_capturas.js <docroot> [outdir]
 *
 * Capturas (393px, DPR 2):
 *   inicio-mobile-393-v3.1.png          â†’ datos REALES de la partida
 *   inicio-mobile-393-v3.1-estados.png  â†’ estados alternativos con MOCKS SOLO DE
 *                                    CAPTURA (banner evento, 2Âº plan en curso,
 *                                    pareja): inyectados en el DOM del arnÃ©s,
 *                                    nunca en runtime ni en partidas.
 *   inicio-desktop-smoke-1440.png  â†’ humo PC
 *
 * AdemÃ¡s valida funcionalmente: 4 modales, reloj dÃ­a/noche, 0 errores JS,
 * sin overflow horizontal, PC sin skin mÃ³vil.
 */
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const DOCROOT = process.argv[2] || path.join(__dirname, '..');
const OUTDIR = process.argv[3] || path.join(__dirname, 'screenshots-ds-piloto-inicio');
const PORT = 8137;
const BASE = 'http://127.0.0.1:' + PORT;

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}
const sleep = ms => new Promise(r => setTimeout(r, ms));

async function waitForHome(page) {
  await page.waitForSelector('.game-shell', { timeout: 60000 });
  try {
    await page.waitForSelector('[data-tut-intro]:not([hidden])', { timeout: 4000 });
    await page.click('[data-tut-skip]', { timeout: 4000 });
    await sleep(600);
    console.log('OK: tutorial intro saltado (partida nueva)');
  } catch (e) { /* sin tutorial */ }
  await page.waitForFunction(() => {
    const el = document.querySelector('[data-vecinos-poblacion]');
    return el && el.textContent && /\d/.test(el.textContent);
  }, { timeout: 60000 });
  await page.waitForFunction(() => document.querySelectorAll('[data-mapa-zonas] .mapa-zona-hit').length > 0,
    { timeout: 30000 });
  await sleep(1400);
}

async function sinOverflowHorizontal(page, label) {
  const sw = await page.evaluate(() => document.scrollingElement.scrollWidth);
  const iw = await page.evaluate(() => window.innerWidth);
  ok(sw <= iw + 1, label + ': sin overflow horizontal (' + sw + 'â‰¤' + iw + ')');
}

async function flujoModales(page) {
  const abreYCierra = async (openSel, capaSel, nombre) => {
    await page.click(openSel, { timeout: 8000 });
    await page.waitForSelector(capaSel, { state: 'visible', timeout: 8000 });
    ok(true, nombre + ' abre');
    await page.click(capaSel + ' [data-close], ' + capaSel + ' .cerrar', { timeout: 8000 });
    await sleep(350);
    const oculta = await page.evaluate(sel => {
      const el = document.querySelector(sel);
      return !el || !el.classList.contains('is-on');
    }, capaSel);
    ok(oculta, nombre + ' cierra');
  };
  await abreYCierra('[data-open="buzon"]', '.capa-buzon', 'Mensajitos');
  await abreYCierra('[data-open="vecinos"]', '.capa-vecinos', 'Vecinos');
  await abreYCierra('[data-open="organizar"]', '.capa-organizar', 'Nuevo Plan');
  await abreYCierra('.obj-cotilleo-par', '.capa-diario', 'Cotilleos');
}

async function pasarElRato(page) {
  const antes = await page.evaluate(() => document.querySelector('[data-hora]').textContent.trim());
  await page.click('[data-pasar-rato]');
  await sleep(1600);
  const despues = await page.evaluate(() => document.querySelector('[data-hora]').textContent.trim());
  ok(antes !== despues, 'Pasar el rato avanza el reloj (' + antes + 'â†’' + despues + ')');
}

/* "Â¿CÃ³mo va esto?" es un botÃ³n real que queda oculto tras saltar el
   tutorial; para la captura se muestra en su estado normal de juego. */
const GUIA = function () {
  const guia = document.querySelector('[data-tut-reopen]');
  if (guia) guia.removeAttribute('hidden');
};

/* MOCKS SOLO DE CAPTURA: inyectan DOM efÃ­mero en la pÃ¡gina del arnÃ©s.
   No tocan partida, ni endpoints, ni runtime del juego.
   Debe ejecutarse DESPUÃ‰S de la recarga final de capturaFeed. */
const MOCKS = function () {
  const gr = document.querySelector('.game-right');

  // "Â¿CÃ³mo va esto?" es un botÃ³n real que queda oculto tras saltar el tutorial
  const guia = document.querySelector('[data-tut-reopen]');
  if (guia) guia.removeAttribute('hidden');

  // 1) Banner PROXIMO EVENTO (componente EV futuro, sin motor; posicion: mapa -> evento -> cotilleo)
  const ev = document.createElement('div');
  ev.className = 'ds-event ds-event--proximo';
  ev.innerHTML =
    '<span class="ds-event-ico" aria-hidden="true">\u{1FAA9}</span>' +
    '<span class="ds-event-body">' +
      '<span class="ds-event-title">Proximo evento</span>' +
      '<span class="ds-event-meta">Se aproxima fiesta en la disco</span>' +
    '</span>';
  gr.insertBefore(ev, gr.firstChild);

  // Retratos reales ya cargados en la pÃ¡gina (para avatares de los mocks)
  const cara = document.querySelector('.obj-vecinos-preview-cara');
  const url = cara && cara.tagName === 'IMG' ? cara.getAttribute('src') : null;
  const img = url ? '<img class="pp-face-img" src="' + url + '" alt=""/>' : '<span class="cara-ini">·</span>';
  const img2 = url ? '<img src="' + url + '" alt=""/>' : '';

  // 2) PLANES EN CURSO: bloque visible + dos tarjetas de composiciÃ³n
  const enc = document.querySelector('[data-encursos-block]');
  if (enc) {
    enc.classList.add('is-on');
    enc.hidden = false;
    enc.style.display = '';
    const track = enc.querySelector('[data-encursos-track]');
    if (track) {
      track.innerHTML =
        '<article class="enc-mov-card">' +
          '<div class="prox-faces">' + img + img2 + '</div>' +
          '<p class="enc-mov-nombres">Paula · Sergio</p>' +
          '<p class="enc-mov-lugar">Bingo</p>' +
          '<p class="enc-mov-hora"><span class="enc-mov-punto" aria-hidden="true"></span>AHORA · 21:00</p>' +
          '<button type="button" class="enc-mov-cta"><span class="enc-mov-cta-txt">Ver encuentro</span><span class="enc-mov-cta-flecha" aria-hidden="true">›</span></button>' +
        '</article>' +
        '<article class="enc-mov-card">' +
          '<div class="prox-faces">' + img + '</div>' +
          '<p class="enc-mov-nombres">David</p>' +
          '<p class="enc-mov-lugar">Cine</p>' +
          '<p class="enc-mov-hora"><span class="enc-mov-punto" aria-hidden="true"></span>AHORA · 21:00</p>' +
          '<button type="button" class="enc-mov-cta"><span class="enc-mov-cta-txt">Ver encuentro</span><span class="enc-mov-cta-flecha" aria-hidden="true">›</span></button>' +
        '</article>';
    }
  }

  // 3) PRÃ“XIMOS PLANES: bloque visible + dos tarjetas punteadas
  const pp = document.querySelector('[data-proxplanes-block]');
  if (pp) {
    pp.classList.add('is-on');
    pp.hidden = false;
    pp.style.display = '';
    const track = pp.querySelector('[data-proxplanes-track]');
    if (track) {
      track.innerHTML =
        '<article class="pp-mov-card">' +
          '<p class="pp-mov-hora">HOY · 20:00</p>' +
          '<div class="prox-faces">' + img + img2 + '</div>' +
          '<p class="pp-mov-nombres">Paula · Sergio</p>' +
          '<p class="pp-mov-lugar">Bingo</p>' +
        '</article>' +
        '<article class="pp-mov-card">' +
          '<p class="pp-mov-hora">HOY · 20:00</p>' +
          '<div class="prox-faces">' + img + '</div>' +
          '<p class="pp-mov-nombres">David</p>' +
          '<p class="pp-mov-lugar">Cine</p>' +
        '</article>';
    }
  }

  // 4) Pareja (mock visual con retrato real)
  const strip = document.querySelector('[data-parejas-strip]');
  if (strip) {
    const caraImg = url
      ? '<img class="obj-pareja-cara" src="' + url + '" alt=""/>'
      : '<span class="obj-pareja-cara"></span>';
    const piece = document.createElement('div');
    piece.className = 'obj-pareja-piece';
    piece.innerHTML =
      '<span class="obj-pareja-fotos">' + caraImg +
      '<span class="obj-pareja-enlace" aria-hidden="true"></span>' + caraImg + '</span>' +
      '<span class="obj-pareja-nombres">Paula · Sergio</span>';
    strip.innerHTML = '';
    strip.appendChild(piece);
  }
};

/* Captura de pÃ¡gina completa honesta: mide alto, recarga con viewport
   definitivo (primer raster ya DS), ejecuta preShot (mocks de captura)
   y oculta flotantes de laboratorio. */
async function capturaFeed(page, file, url, preShot) {
  const w = page.viewportSize().width;
  await page.setViewportSize({ width: w, height: 852 });
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForSelector('.game-shell', { timeout: 60000 });
  try {
    await page.waitForSelector('[data-tut-intro]:not([hidden])', { timeout: 3000 });
    await page.click('[data-tut-skip]', { timeout: 3000 });
  } catch (e) { /* sin tutorial */ }
  await sleep(1200);
  const alto = await page.evaluate(() =>
    Math.min(8000, Math.max(852, document.documentElement.scrollHeight)));
  await page.setViewportSize({ width: w, height: alto });
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await page.waitForSelector('.game-shell', { timeout: 60000 });
  try {
    await page.waitForSelector('[data-tut-intro]:not([hidden])', { timeout: 3000 });
    await page.click('[data-tut-skip]', { timeout: 3000 });
  } catch (e) { /* sin tutorial */ }
  await page.addStyleTag({ content: '.aht-debug-float,.taller-debug{display:none!important}' });
  await sleep(1600);
  if (preShot) {
    await page.evaluate(preShot);
    await sleep(600);
    // los mocks pueden crecer la pÃ¡gina: re-medir y re-encuadrar
    const alto2 = await page.evaluate(() =>
      Math.min(8000, Math.max(852, document.documentElement.scrollHeight)));
    if (Math.abs(alto2 - alto) > 4) {
      await page.setViewportSize({ width: w, height: alto2 });
      await sleep(700);
    }
  }
  await page.evaluate(() => new Promise(resolve => {
    document.body.style.display = 'none';
    void document.body.offsetHeight;
    document.body.style.display = '';
    requestAnimationFrame(() => requestAnimationFrame(resolve));
  }));
  await sleep(300);
  await page.evaluate(() => window.scrollTo(0, 0));
  await sleep(400);
  await page.screenshot({ path: path.join(OUTDIR, file) });
  console.log('CAPTURA: ' + file);
}

(async function main() {
  fs.mkdirSync(OUTDIR, { recursive: true });
  const phpSrv = spawn('php', ['-S', '127.0.0.1:' + PORT, '-t', DOCROOT], { stdio: 'ignore' });
  await sleep(1200);

  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ deviceScaleFactor: 2 });
  const erroresJS = [];

  try {
    // ---- 393: datos reales + validaciÃ³n funcional ----
    const m = await ctx.newPage();
    await m.setViewportSize({ width: 393, height: 852 });
    m.on('pageerror', e => erroresJS.push('393: ' + e.message));
    await m.goto(BASE + '/play.php?config=playtest_01&seed=ds2-' + Date.now(),
      { waitUntil: 'domcontentloaded', timeout: 90000 });
    await waitForHome(m);
    await sinOverflowHorizontal(m, '393');
    const candados = await m.evaluate(() =>
      document.querySelectorAll('.mapa-zona--cerrada-ahora').length);
    console.log('INFO: zonas con candado cerrado-ahora = ' + candados + ' (segÃºn hora del pueblo)');
    await flujoModales(m);
    await pasarElRato(m);
    await capturaFeed(m, 'inicio-mobile-393-v3.1.png', BASE + '/play.php', GUIA);
    await m.close();

    // ---- 393 estados alternativos (mocks solo de captura, tras la recarga) ----
    const m2 = await ctx.newPage();
    await m2.setViewportSize({ width: 393, height: 852 });
    m2.on('pageerror', e => erroresJS.push('393b: ' + e.message));
    await m2.goto(BASE + '/play.php', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await waitForHome(m2);
    await sinOverflowHorizontal(m2, '393-estados');
    await capturaFeed(m2, 'inicio-mobile-393-v3.1-estados.png', BASE + '/play.php', MOCKS);
    await m2.close();

    // ---- Desktop 1440: humo (sin skin mÃ³vil) ----
    const pc = await ctx.newPage();
    await pc.setViewportSize({ width: 1440, height: 900 });
    pc.on('pageerror', e => erroresJS.push('pc: ' + e.message));
    await pc.goto(BASE + '/play.php', { waitUntil: 'domcontentloaded', timeout: 90000 });
    await pc.waitForSelector('.game-shell', { timeout: 60000 });
    await sleep(2000);
    const esPc = await pc.evaluate(() => document.querySelector('.play-root').classList.contains('pc'));
    ok(esPc, 'desktop: .play-root.pc activo a 1440');
    const sinSkin = await pc.evaluate(() => {
      const tile = document.querySelector('.zona-actividad .obj-buzon');
      return tile && getComputedStyle(tile).borderTopWidth !== '2px';
    });
    ok(sinSkin, 'desktop: skin del piloto NO aplicado');
    await pc.screenshot({ path: path.join(OUTDIR, 'inicio-desktop-smoke-1440.png') });
    console.log('CAPTURA: inicio-desktop-smoke-1440.png');
    await pc.close();
  } finally {
    await browser.close().catch(() => {});
    phpSrv.kill();
  }

  ok(erroresJS.length === 0, '0 errores JS (pageerror)' + (erroresJS.length ? ' â†’ ' + erroresJS.join(' | ') : ''));
  console.log(failures ? '\n' + failures + ' FAIL' : '\nE2E OK');
  process.exit(failures ? 1 : 0);
})().catch(e => { console.error('E2E CRASH:', e); process.exit(1); });
