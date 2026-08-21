const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const outDir = path.join(__dirname, 'screenshots-ui');
fs.mkdirSync(outDir, { recursive: true });

(async function () {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const url = 'http://localhost:8765/play.php?lab=1&config=playtest_01&seed=ui-shell-' + Date.now();
  const results = [];

  async function testClick(label, selector, expectCapa) {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 90000 });
    await page.waitForSelector('.game-shell', { timeout: 30000 });
    await page.waitForTimeout(2500);
    const el = await page.$(selector);
    if (!el) {
      results.push({ label, ok: false, detail: 'selector no encontrado: ' + selector });
      return;
    }
    await el.click();
    await page.waitForTimeout(600);
    const capa = await page.evaluate(function () {
      const r = document.querySelector('.play-root');
      return r ? r.getAttribute('data-capa') : null;
    });
    const ok = expectCapa ? capa === expectCapa : true;
    results.push({ label, ok, selector, capa, expectCapa });
  }

  await page.goto(url, { waitUntil: 'networkidle', timeout: 90000 });
  await page.waitForSelector('[data-edificios-layer] .edif', { timeout: 45000, state: 'attached' });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: path.join(outDir, '01-escritorio-completo.png'), fullPage: false });

  await testClick('Cotilleo → Diario', '.cotilleo-card', 'diario');
  await page.screenshot({ path: path.join(outDir, '02-diario-abierto.png'), fullPage: false });
  await page.click('.capa-diario [data-close], .velo').catch(function () {});
  await page.waitForTimeout(400);

  await testClick('Buzón compacto', '.buzon-compact', 'buzon');
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(outDir, '03-buzon-abierto.png'), fullPage: false });

  const buzonLayout = await page.evaluate(function () {
    const capa = document.querySelector('.capa-buzon');
    const c = document.querySelector('.carta-msg .cuerpo');
    const capaR = capa ? capa.getBoundingClientRect() : null;
    if (!c) return { hasCarta: false, capaWidth: capaR && capaR.width };
    const r = c.getBoundingClientRect();
    return { hasCarta: true, cuerpoWidth: r.width, capaWidth: capaR && capaR.width };
  });

  await testClick('+ NUEVO PLAN', '.btn-nuevo-plan', 'organizar');
  await page.goto(url, { waitUntil: 'networkidle', timeout: 90000 });
  await page.waitForTimeout(2000);
  await testClick('EL PUEBLO', '.pueblo-card', 'vecinos');

  const playtestBars = await page.evaluate(function () {
    const t = document.querySelector('.taller');
    const c = document.querySelector('.playtest-cheats');
    const f = document.querySelector('.playtest-float');
    return {
      tallerVisible: t ? getComputedStyle(t).display !== 'none' : false,
      cheatsVisible: c && !c.hidden ? getComputedStyle(c).display !== 'none' : false,
      floatVisible: !!f
    };
  });

  const dinero = await page.evaluate(function () {
    const el = document.querySelector('[data-dinero]');
    return el ? el.textContent.trim() : null;
  });

  const obras = await page.evaluate(function () {
    return document.querySelectorAll('.edif.is-off').length;
  });

  await browser.close();

  console.log(JSON.stringify({ results, buzonLayout, playtestBars, dinero, obrasCount: obras, shots: outDir }, null, 2));
})();
