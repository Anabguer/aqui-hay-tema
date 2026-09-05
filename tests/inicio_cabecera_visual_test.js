'use strict';
/* Validación visual mínima cabeceras Inicio (Playwright). */
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
      const card = document.querySelector('.inicio-mobile:not(.inicio-mobile-feed) .inicio-header-card');
      const pill = document.querySelector('.inicio-mobile:not(.inicio-mobile-feed) .inicio-temporal-pill');
      const pr = card?.getBoundingClientRect();
      const pp = pill?.getBoundingClientRect();
      const btn = document.querySelector('.inicio-mobile:not(.inicio-mobile-feed) .inicio-temporal-pill .pasar-rato');
      const br = btn?.getBoundingClientRect();
      const txt = btn?.querySelector('.pasar-rato-txt');
      return {
        cardW: pr?.width || 0,
        cardH: pr?.height || 0,
        pillW: pp?.width || 0,
        pillH: pp?.height || 0,
        pillWrap: pill ? getComputedStyle(pill).flexWrap : '',
        btnTxt: txt ? getComputedStyle(txt).display : 'missing',
        btnRound: br && Math.abs(br.width - br.height) < 4,
        brand: document.querySelector('.inicio-mobile:not(.inicio-mobile-feed) .brand-text')?.textContent?.trim() || '',
      };
    });
    ok(m.cardW > 300 && m.cardH > 40 && m.cardH < 130, 'mobile: tarjeta horizontal (ancho > alto relativo)');
    ok(m.pillW > 200 && m.pillH < 48, 'mobile: píldora compacta horizontal');
    ok(m.pillWrap === 'nowrap', 'mobile: píldora sin wrap');
    ok(m.btnTxt === 'none', 'mobile: sin texto en botón avance');
    ok(m.btnRound, 'mobile: botón ▶ circular');
    ok(/AQU[IÍ] HAY TEMA/i.test(m.brand), 'mobile: marca visible');

    const desk = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    await prepInicioPage(desk, PARTIDA_FIXTURE);
    const d = await desk.evaluate(function () {
      const top = document.querySelector('.inicio-desktop .game-top');
      const tr = top?.getBoundingClientRect();
      const brand = top?.querySelector('.brand-col')?.getBoundingClientRect();
      const center = top?.querySelector('.top-center')?.getBoundingClientRect();
      const vida = top?.querySelector('.top-vida')?.getBoundingClientRect();
      const pasar = top?.querySelector('.pasar-rato-txt');
      const est = top?.querySelector('.obj-dia-estacion');
      return {
        topW: tr?.width || 0,
        topH: tr?.height || 0,
        brandLeft: brand?.left || 0,
        centerX: center ? center.left + center.width / 2 : 0,
        vidaRight: vida ? vida.right : 0,
        topRight: tr?.right || 0,
        pasarTxt: pasar ? getComputedStyle(pasar).display : 'missing',
        estacion: est ? getComputedStyle(est).display : 'missing',
        kicker: top?.querySelector('.obj-vida-kicker')?.textContent?.trim() || '',
        bg: top ? getComputedStyle(top).backgroundColor : '',
      };
    });
    ok(d.topW > 900, 'desktop: cabecera a ancho completo');
    ok(d.topH > 70 && d.topH < 180, 'desktop: cabecera altura razonable');
    ok(d.brandLeft < d.centerX && d.vidaRight > d.centerX, 'desktop: brand | centro | vida');
    ok(d.pasarTxt !== 'none', 'desktop: texto Pasar el rato visible');
    ok(d.estacion === 'missing' || d.estacion === 'none', 'desktop: sin Primavera');
    ok(/vida del pueblo/i.test(d.kicker), 'desktop: kicker vida del pueblo');
    ok(d.bg === 'rgb(255, 255, 255)', 'desktop: fondo blanco cabecera');
  } finally {
    await browser.close();
    php.kill();
  }

  console.log(failures ? '\n' + failures + ' FAIL' : '\nCABECERA VISUAL OK');
  process.exit(failures ? 1 : 0);
})().catch(function (e) {
  console.error(e);
  process.exit(1);
});
