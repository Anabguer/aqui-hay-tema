'use strict';
/**
 * Captura REAL Inicio móvil 393×852 + desktop 1280×900 con partida fixture.
 * Valida contenido renderizado (no solo contenedores).
 * node dev/_capture_inicio_architecture.js
 */
const { chromium } = require('playwright');
const path = require('path');
const http = require('http');
const fs = require('fs');
const { spawn } = require('child_process');

const root = path.join(__dirname, '..');
const outDir = path.join(root, 'dev', 'visual-play-evidence');
fs.mkdirSync(outDir, { recursive: true });

const mobOut = path.join(outDir, 'inicio-architecture-mobile-393.png');
const deskOut = path.join(outDir, 'inicio-architecture-desktop-1280.png');
const { prepInicioPage, PARTIDA_FIXTURE } = require('./inicio_playwright_boot.js');
const MOJIBAKE = /Ã|Â|ï¿½|â€|AQUÃ|PrÃ³ximo evento/;

function waitForServer(url, ms) {
  const start = Date.now();
  return new Promise(function (resolve, reject) {
    (function poll() {
      http.get(url, function (res) {
        res.resume();
        resolve();
      }).on('error', function () {
        if (Date.now() - start > ms) reject(new Error('server timeout'));
        else setTimeout(poll, 400);
      });
    })();
  });
}

async function prepPage(page) {
  await prepInicioPage(page, PARTIDA_FIXTURE);
}

function evalInicio(page) {
  return page.evaluate(function () {
    function rect(sel) {
      const el = document.querySelector(sel);
      if (!el) return { w: 0, h: 0, display: 'none' };
      const r = el.getBoundingClientRect();
      return { w: Math.round(r.width), h: Math.round(r.height), display: getComputedStyle(el).display };
    }
    function text(sel) {
      const el = document.querySelector(sel);
      return el ? (el.textContent || '').trim() : '';
    }
    function allText(sel) {
      return Array.from(document.querySelectorAll(sel)).map(function (el) {
        return (el.textContent || '').trim();
      }).join('|');
    }
    const isMob = window.matchMedia('(max-width: 768px)').matches;
    const mobile = document.querySelector('.inicio-mobile:not(.inicio-mobile-feed)');
    const feed = document.querySelector('.inicio-mobile.inicio-mobile-feed');
    const desktop = document.querySelector('.inicio-desktop');
    const map = rect('.inicio-map-host');
    const mapImg = rect('.mapa-canonico-bg');
    return {
      viewport: isMob ? 'mobile' : 'desktop',
      mobileDisplay: mobile ? getComputedStyle(mobile).display : 'missing',
      feedDisplay: feed ? getComputedStyle(feed).display : 'missing',
      desktopDisplay: desktop ? getComputedStyle(desktop).display : 'missing',
      desktopInert: desktop ? desktop.inert : null,
      map,
      mapImg,
      vecinosStatsLen: allText('[data-resumen-stats]').length,
      vecinosPob: text('[data-vecinos-poblacion]'),
      cotilleoLen: allText('[data-cotilleo-teaser]').length,
      misionesItems: document.querySelectorAll('[data-misiones-strip] > *').length,
      parejasItems: document.querySelectorAll('[data-parejas-strip] .obj-pareja-piece').length,
      encCards: document.querySelectorAll('[data-encursos-track] .enc-mov-card').length,
      proxCards: document.querySelectorAll('[data-proxplanes-track] .pp-mov-card, [data-proxplanes-track] .prox-card').length,
      headerMeta: allText('[data-dia-meta]'),
      headerHora: allText('[data-hora]'),
      buzonBadge: allText('[data-buzon-badge]'),
    };
  });
}

(async function () {
  const php = spawn('php', ['-S', '127.0.0.1:8765', '-t', root], { cwd: root, stdio: 'ignore' });
  await waitForServer('http://127.0.0.1:8765/play.php', 15000);
  const browser = await chromium.launch();
  const failures = [];

  try {
    const mob = await browser.newPage({ viewport: { width: 393, height: 852 }, deviceScaleFactor: 2 });
    await prepPage(mob);
    const mobM = await evalInicio(mob);
    console.log('mobile metrics', JSON.stringify(mobM, null, 2));
    const mobHtml = await mob.locator('.inicio-mobile .game-top').innerHTML().catch(function () { return ''; });
    if (MOJIBAKE.test(mobHtml)) failures.push('mobile mojibake in stage');
    if (mobM.map.w < 50 || mobM.map.h < 50) failures.push('mobile map not visible');
    if (mobM.feedDisplay === 'none') failures.push('mobile feed hidden');
    if (mobM.cotilleoLen < 5) failures.push('mobile cotilleo empty');
    if (mobM.vecinosStatsLen < 3 && mobM.vecinosPob.length < 3) failures.push('mobile vecinos empty');
    const mobShell = mob.locator('.game-shell').first();
    if (await mobShell.count()) await mobShell.screenshot({ path: mobOut });
    else await mob.screenshot({ path: mobOut, fullPage: true });

    const desk = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    await prepPage(desk);
    const deskM = await evalInicio(desk);
    console.log('desktop metrics', JSON.stringify(deskM, null, 2));
    const deskHtml = await desk.locator('.inicio-desktop .game-top').innerHTML().catch(function () { return ''; });
    if (MOJIBAKE.test(deskHtml)) failures.push('desktop mojibake in stage');
    if (deskM.map.w < 100 || deskM.map.h < 80) failures.push('desktop map not visible');
    if (deskM.desktopDisplay === 'none') failures.push('desktop section hidden');
    if (deskM.vecinosStatsLen < 3) failures.push('desktop vecinos empty');
    if (deskM.misionesItems < 1) failures.push('desktop misiones empty');
    await desk.screenshot({ path: deskOut, fullPage: true });

    console.log('screenshots:', mobOut, deskOut);
    if (failures.length) {
      console.error('CAPTURE VALIDATION FAIL:', failures.join('; '));
      process.exit(1);
    }
    console.log('CAPTURE OK');
  } finally {
    await browser.close();
    php.kill();
  }
})().catch(function (e) {
  console.error(e);
  process.exit(1);
});
