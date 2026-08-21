/**
 * Capturas pass 2 — composicion y capas.
 */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const outDir = path.join(__dirname, 'screenshots-art');
const URL = 'http://localhost:8765/play.php?lab=1&config=playtest_01&agenda_demo=1&art_demo=1';

async function shot(page, name) {
  const p = path.join(outDir, name);
  await page.screenshot({ path: p, fullPage: false });
  console.log('Saved', p);
}

async function closeCapa(page) {
  await page.evaluate(() => {
    const root = document.querySelector('.play-root');
    if (root) {
      root.removeAttribute('data-capa');
      root.removeAttribute('data-capa-origin');
    }
  });
  await page.waitForTimeout(400);
}

(async () => {
  fs.mkdirSync(outDir, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto(URL, { waitUntil: 'networkidle', timeout: 90000 });
  await page.waitForSelector('.game-shell', { timeout: 60000 });
  await page.waitForTimeout(2500);
  await shot(page, 'pass2-shell-general.png');

  const hasPend = await page.locator('[data-planes-pend]:not([hidden])').count();
  if (hasPend > 0) {
    await page.click('[data-planes-pend]');
  } else {
    await page.evaluate(() => {
      const root = document.querySelector('.play-root');
      if (root) {
        root.setAttribute('data-capa', 'agenda');
        root.setAttribute('data-capa-origin', 'left');
      }
    });
  }
  await page.waitForSelector('.play-root[data-capa="agenda"]', { timeout: 10000 });
  await page.waitForTimeout(800);
  await shot(page, 'pass2-capa-planes-izq.png');
  await closeCapa(page);

  await page.click('.game-left [data-open="buzon"]');
  await page.waitForSelector('.play-root[data-capa="buzon"]', { timeout: 10000 });
  await page.waitForTimeout(800);
  await shot(page, 'pass2-capa-buzon.png');
  await closeCapa(page);

  await page.click('.game-left [data-open="organizar"]');
  await page.waitForSelector('.play-root[data-capa="organizar"]', { timeout: 10000 });
  await page.waitForTimeout(800);
  await shot(page, 'pass2-capa-organizar.png');

  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
