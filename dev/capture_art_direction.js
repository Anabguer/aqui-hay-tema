const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const outDir = path.join(__dirname, 'screenshots-art');
fs.mkdirSync(outDir, { recursive: true });

const FASE_DESTINO = {
  bar: 'lug_bar', discoteca: 'lug_discoteca', karaoke: 'lug_karaoke',
  cine: 'lug_cine', recreativo: 'lug_arcade',
  restaurante: 'lug_restaurante', bingo: 'lug_bingo',
  cafeteria: 'lug_cafeteria', biblioteca: 'lug_biblioteca', tienda: 'lug_tienda_ropa',
  gimnasio: 'lug_gimnasio', spa: 'lug_spa',
  picnic: 'lug_picnic', mirador: 'lug_mirador',
};
const FASES = {
  1: ['bar', 'cine', 'restaurante', 'cafeteria', 'gimnasio', 'picnic'],
  2: ['bar', 'discoteca', 'cine', 'recreativo', 'restaurante', 'bingo', 'cafeteria', 'biblioteca', 'gimnasio', 'spa', 'picnic', 'mirador'],
  3: Object.keys(FASE_DESTINO),
};

async function applyFase(page, faseNum) {
  return page.evaluate(function (args) {
    const active = new Set(args.active);
    const imgs = document.querySelectorAll('.edificios-layer .edif[data-fase], .edif[data-fase]');
    let on = 0; let off = 0;
    imgs.forEach(function (img) {
      const id = img.getAttribute('data-fase');
      const hab = active.has(id);
      img.classList.toggle('is-on', hab);
      img.classList.toggle('is-off', !hab);
      if (hab) on++; else off++;
    });
    return { on, off, total: imgs.length };
  }, { active: FASES[faseNum] });
}

(async function () {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const base = 'http://localhost:8765/play.php?lab=1&config=playtest_01&seed=art-dir-' + Date.now();

  await page.goto(base, { waitUntil: 'networkidle', timeout: 90000 });
  await page.waitForSelector('.game-shell', { timeout: 30000 });
  await page.waitForSelector('[data-edificios-layer] .edif', { state: 'attached', timeout: 45000 });
  await page.waitForTimeout(2500);

  const checks = {};

  for (const f of [1, 2, 3]) {
    const counts = await applyFase(page, f);
    checks['fase' + f] = counts;
    await page.waitForTimeout(400);
    await page.screenshot({ path: path.join(outDir, 'fase-' + f + '-escritorio.png'), fullPage: false });
  }

  const ui = await page.evaluate(function () {
    const rootBg = getComputedStyle(document.querySelector('.play-root')).backgroundImage;
    const rootBgColor = getComputedStyle(document.querySelector('.play-root')).backgroundColor;
    const pctVisible = (function () {
      const el = document.querySelector('[data-vida-pct]');
      if (!el) return false;
      const s = getComputedStyle(el);
      return s.width !== '1px' && s.clip !== 'rect(0px, 0px, 0px, 0px)' && el.offsetParent !== null;
    })();
    const emojis = Array.from(document.querySelectorAll('.game-shell *')).filter(function (n) {
      return /[\u{1F300}-\u{1FAFF}]/u.test(n.textContent || '');
    }).length;
    return {
      rootHasStripe: /repeating-linear-gradient|linear-gradient/.test(rootBg) && !/none/.test(rootBg),
      rootBgColor,
      pctVisible,
      emojiNodesInShell: emojis,
      brandFont: getComputedStyle(document.querySelector('.brand-text')).fontFamily,
      heartFill: getComputedStyle(document.querySelector('.corazon-shell-fill')).getPropertyValue('--fill').trim(),
    };
  });

  await page.click('.buzon-compact');
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(outDir, 'buzon-abierto.png'), fullPage: false });

  await browser.close();
  console.log(JSON.stringify({ checks, ui, shots: outDir }, null, 2));
})();
