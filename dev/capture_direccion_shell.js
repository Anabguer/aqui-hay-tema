/**
 * Captura única dirección artística shell — 1440×900, lab fixture.
 * URL: play.php?lab=1&config=playtest_01&agenda_demo=1&art_demo=1
 */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const OUT = path.join(__dirname, 'screenshots-art', 'direccion-shell-art.png');
const URL = 'http://localhost:8765/play.php?lab=1&config=playtest_01&agenda_demo=1&art_demo=1';

(async () => {
  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  page.on('console', (m) => { if (m.type() === 'error') console.error('PAGE:', m.text()); });
  await page.goto(URL, { waitUntil: 'networkidle', timeout: 90000 });
  await page.waitForSelector('.game-shell', { timeout: 60000 });
  await page.waitForTimeout(2500);
  await page.screenshot({ path: OUT, fullPage: false });
  console.log('Saved', OUT);
  await browser.close();
})().catch((e) => { console.error(e); process.exit(1); });
