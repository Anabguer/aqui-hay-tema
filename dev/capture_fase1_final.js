const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const outDir = path.join(__dirname, 'screenshots-art');
fs.mkdirSync(outDir, { recursive: true });

const FASES = {
  1: ['bar', 'cine', 'restaurante', 'cafeteria', 'gimnasio', 'picnic'],
};

(async function () {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const url = 'http://localhost:8765/play.php?lab=1&config=playtest_01&seed=fase1-visual-' + Date.now();

  await page.goto(url, { waitUntil: 'networkidle', timeout: 90000 });
  await page.waitForSelector('.game-shell', { timeout: 30000 });
  await page.waitForSelector('[data-edificios-layer] .edif', { state: 'attached', timeout: 45000 });
  await page.waitForTimeout(2500);

  await page.evaluate(function (active) {
    const set = new Set(active);
    document.querySelectorAll('.edificios-layer .edif[data-fase]').forEach(function (img) {
      const id = img.getAttribute('data-fase');
      const hab = set.has(id);
      img.classList.toggle('is-on', hab);
      img.classList.toggle('is-off', !hab);
    });
  }, FASES[1]);

  await page.waitForTimeout(500);

  const audit = await page.evaluate(function () {
    const mesa = document.querySelector('.mesa');
    const mesaStyle = mesa ? getComputedStyle(mesa).display : 'none';
    const legacy = {
      mesaVisible: mesaStyle !== 'none',
      tiempo: document.querySelector('.tiempo-juego') && getComputedStyle(document.querySelector('.tiempo-juego')).display !== 'none',
      organizar: document.querySelector('.btn-organizar-pc') && getComputedStyle(document.querySelector('.btn-organizar-pc')).display !== 'none',
      buzonLegacy: document.querySelector('.mesa .buzon') && getComputedStyle(document.querySelector('.mesa .buzon')).display !== 'none',
    };
    const cards = ['.ui-card', '.cotilleo-card', '.bloques-card', '.parejas-panel', '.buzon-compact', '.proximo-card', '.pueblo-card'].map(function (s) {
      return { sel: s, n: document.querySelectorAll('.game-shell ' + s).length };
    });
    const edifs = { on: 0, off: 0 };
    document.querySelectorAll('.edif[data-fase]').forEach(function (e) {
      if (e.classList.contains('is-on')) edifs.on++;
      if (e.classList.contains('is-off')) edifs.off++;
    });
    const heart = document.querySelector('.corazon-fill-rect');
    const heartBox = heart ? heart.getBoundingClientRect() : null;
    const headerGap = (function () {
      const b = document.querySelector('.brand');
      const m = document.querySelector('.top-meta');
      if (!b || !m) return null;
      return m.getBoundingClientRect().left - b.getBoundingClientRect().right;
    })();
    return { legacy, cards, edifs, heartHeight: heartBox && heartBox.height, headerGap };
  });

  await page.screenshot({ path: path.join(outDir, 'fase-1-escritorio-final.png'), fullPage: false });

  // click tests
  const tests = [];
  async function click(label, sel, capa) {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 90000 });
    await page.waitForTimeout(2000);
    await page.evaluate(function (active) {
      const set = new Set(active);
      document.querySelectorAll('.edificios-layer .edif[data-fase]').forEach(function (img) {
        const hab = set.has(img.getAttribute('data-fase'));
        img.classList.toggle('is-on', hab);
        img.classList.toggle('is-off', !hab);
      });
    }, FASES[1]);
    await page.click(sel);
    await page.waitForTimeout(400);
    const got = await page.evaluate(function () {
      const r = document.querySelector('.play-root');
      return r ? r.getAttribute('data-capa') : null;
    });
    tests.push({ label, sel, ok: got === capa, capa: got });
  }

  await click('Cotilleo', '.obj-cotilleo', 'diario');
  await click('Buzón', '.obj-buzon', 'buzon');
  await click('Nuevo plan', '.obj-nuevo-plan', 'organizar');
  await click('El pueblo', '.obj-pueblo', 'vecinos');

  await browser.close();
  console.log(JSON.stringify({ audit, tests, shot: path.join(outDir, 'fase-1-escritorio-final.png') }, null, 2));
})();
