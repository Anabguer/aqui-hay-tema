const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const outDir = path.join(__dirname, 'screenshots-art');
fs.mkdirSync(outDir, { recursive: true });
const FASE1 = ['bar', 'cine', 'restaurante', 'cafeteria', 'gimnasio', 'picnic'];

(async function () {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const url = 'http://localhost:8765/play.php?lab=1&config=playtest_01&seed=comp-' + Date.now();

  await page.goto(url, { waitUntil: 'networkidle', timeout: 90000 });
  await page.waitForSelector('.game-shell', { timeout: 30000 });
  await page.waitForSelector('[data-edificios-layer] .edif', { state: 'attached', timeout: 45000 });
  await page.waitForTimeout(2500);

  await page.evaluate(function (active) {
    const set = new Set(active);
    document.querySelectorAll('.edificios-layer .edif[data-fase]').forEach(function (img) {
      const hab = set.has(img.getAttribute('data-fase'));
      img.classList.toggle('is-on', hab);
      img.classList.toggle('is-off', !hab);
    });
  }, FASE1);

  const audit = await page.evaluate(function () {
    const bib = document.querySelector('.edif.b-biblioteca.is-off');
    const bibFilter = bib ? getComputedStyle(bib).filter : null;
    const bibAfter = bib ? getComputedStyle(bib, '::after').content : null;
    return {
      headerGrid: getComputedStyle(document.querySelector('.game-top')).gridTemplateColumns,
      vidaRight: document.querySelector('.top-vida') ? document.querySelector('.top-vida').getBoundingClientRect().right : 0,
      viewportRight: window.innerWidth,
      activityInLeft: !!document.querySelector('.zona-actividad .obj-buzon'),
      personasInRight: !!document.querySelector('.zona-personas .obj-pueblo'),
      puebloFaces: document.querySelectorAll('[data-pueblo-faces] img, [data-pueblo-faces] .cara-ini').length,
      badgeHidden: document.querySelector('[data-buzon-badge]')?.hidden,
      edifs: { on: document.querySelectorAll('.edif.is-on').length, off: document.querySelectorAll('.edif.is-off').length },
      bibFilter,
      bibHasAfter: bibAfter && bibAfter !== 'none',
      legacyMesa: getComputedStyle(document.querySelector('.mesa')).display,
    };
  });

  const tests = [];
  for (const t of [
    ['Cotilleo', '.obj-cotilleo', 'diario'],
    ['Buzón', '.obj-buzon', 'buzon'],
    ['Nuevo plan', '.obj-nuevo-plan', 'organizar'],
    ['El pueblo', '.obj-pueblo', 'vecinos'],
  ]) {
    await page.click(t[1]);
    await page.waitForTimeout(350);
    const capa = await page.evaluate(function () {
      return document.querySelector('.play-root')?.getAttribute('data-capa');
    });
    tests.push({ label: t[0], ok: capa === t[2], capa });
    await page.keyboard.press('Escape').catch(function () {});
    await page.click('.velo').catch(function () {});
    await page.waitForTimeout(200);
  }

  await page.screenshot({ path: path.join(outDir, 'fase-1-composicion.png'), fullPage: false });
  await browser.close();
  console.log(JSON.stringify({ audit, tests, shot: path.join(outDir, 'fase-1-composicion.png') }, null, 2));
})();
