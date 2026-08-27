'use strict';
/**
 * Tests de render runtime Inicio (Playwright + partida fixture).
 * node tests/inicio_render_test.js
 */
const { chromium } = require('playwright');
const path = require('path');
const http = require('http');
const fs = require('fs');
const { spawn } = require('child_process');

const root = path.join(__dirname, '..');
const { prepInicioPage, PARTIDA_FIXTURE } = require('../dev/inicio_playwright_boot.js');

function waitForServer(url, ms) {
  const start = Date.now();
  return new Promise(function (resolve, reject) {
    (function poll() {
      http.get(url, function (res) { res.resume(); resolve(); }).on('error', function () {
        if (Date.now() - start > ms) reject(new Error('server timeout'));
        else setTimeout(poll, 400);
      });
    })();
  });
}

const MOJIBAKE = /Ã|Â|ï¿½|â€|AQUÃ/;

function cssDisplay(sel) {
  const el = document.querySelector(sel);
  return el ? getComputedStyle(el).display : 'missing';
}

(async function () {
  if (!fs.existsSync(path.join(root, 'node_modules/playwright'))) {
    console.error('SKIP: playwright not installed');
    process.exit(0);
  }
  const php = spawn('php', ['-S', '127.0.0.1:8765', '-t', root], { cwd: root, stdio: 'ignore' });
  await waitForServer('http://127.0.0.1:8765/play.php', 15000);
  const browser = await chromium.launch();
  let failures = 0;
  function ok(c, m) { console.log((c ? 'OK' : 'FAIL') + ': ' + m); if (!c) failures++; }

  try {
    const mob = await browser.newPage({ viewport: { width: 393, height: 852 } });
    await prepInicioPage(mob, PARTIDA_FIXTURE);
    const m = await mob.evaluate(function () {
      const r = document.querySelector('.inicio-map-host')?.getBoundingClientRect();
      const feedEl = document.querySelector('.inicio-mobile.inicio-mobile-feed');
      const deskEl = document.querySelector('.inicio-stage > .inicio-desktop');
      return {
        mapW: r ? r.width : 0,
        mapH: r ? r.height : 0,
        feedVis: feedEl ? getComputedStyle(feedEl).display !== 'none' : false,
        deskVis: deskEl ? getComputedStyle(deskEl).display === 'none' : true,
        cotilleo: document.querySelector('[data-cotilleo-teaser]')?.textContent?.trim().length || 0,
        vecinos: document.querySelector('[data-resumen-stats]')?.innerHTML?.length || 0,
        headerHtml: [...document.querySelectorAll('.inicio-mobile:not(.inicio-mobile-feed) .game-top')].map(function (e) { return e.innerHTML; }).join(''),
      };
    });
    ok(m.mapW > 50 && m.mapH > 50, 'mobile: mapa visible con dimensiones');
    ok(m.feedVis, 'mobile: feed visible');
    ok(m.deskVis, 'mobile: desktop oculto');
    ok(m.cotilleo > 5, 'mobile: cotilleo con texto');
    ok(m.vecinos > 10, 'mobile: vecinos renderizados');
    ok(!MOJIBAKE.test(m.headerHtml), 'mobile: cabecera sin mojibake');

    const desk = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    await prepInicioPage(desk, PARTIDA_FIXTURE);
    const d = await desk.evaluate(function () {
      const r = document.querySelector('.inicio-map-host')?.getBoundingClientRect();
      const mobEl = document.querySelector('.inicio-mobile:not(.inicio-mobile-feed)');
      return {
        mapW: r ? r.width : 0,
        mapH: r ? r.height : 0,
        mobVis: mobEl ? getComputedStyle(mobEl).display === 'none' : true,
        left: document.querySelectorAll('.inicio-desktop-left .shell-grupo').length,
        right: document.querySelectorAll('.inicio-desktop-right .shell-grupo').length,
        misiones: document.querySelectorAll('[data-misiones-strip] > *').length,
        vecinos: document.querySelector('[data-resumen-stats]')?.innerHTML?.length || 0,
        meta: document.querySelector('[data-dia-meta]')?.textContent || '',
        headerHtml: [...document.querySelectorAll('.inicio-desktop .game-top')].map(function (e) { return e.innerHTML; }).join(''),
      };
    });
    ok(d.mapW > 100 && d.mapH > 80, 'desktop: mapa central visible');
    ok(d.mobVis, 'desktop: móvil oculto');
    ok(d.left >= 3 && d.right >= 3, 'desktop: columnas con bloques');
    ok(d.misiones >= 1, 'desktop: misiones renderizadas');
    ok(d.vecinos > 10, 'desktop: vecinos renderizados');
    ok(!MOJIBAKE.test(d.headerHtml), 'desktop: cabecera sin mojibake');
    ok(!/Ã|Â/.test(d.meta), 'desktop: cabecera fecha sin mojibake');
  } finally {
    await browser.close();
    php.kill();
  }

  console.log(failures ? '\n' + failures + ' FAIL' : '\nRENDER OK');
  process.exit(failures ? 1 : 0);
})().catch(function (e) {
  console.error(e);
  process.exit(1);
});
