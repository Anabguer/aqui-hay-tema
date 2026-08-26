'use strict';
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const OUT = path.join(__dirname, 'visual-play-evidence', 'inicio-real-mobile.png');
const BASE = 'http://127.0.0.1:8765/play.php';
const PARTIDA = 'e2erit-part_5af4821';

(async () => {
  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 393, height: 852 }, deviceScaleFactor: 2 });
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));

  await page.goto(BASE, { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.evaluate((pid) => {
    localStorage.setItem('aht_partida_id_juego', pid);
    localStorage.setItem('aht_partida_id', pid);
  }, PARTIDA);
  await page.reload({ waitUntil: 'networkidle', timeout: 120000 }).catch(() => page.reload({ waitUntil: 'domcontentloaded', timeout: 120000 }));
  await page.waitForSelector('.game-shell', { timeout: 90000 }).catch(() => {});

  try {
    await page.waitForSelector('[data-tut-intro]:not([hidden])', { timeout: 4000 });
    await page.click('[data-tut-skip]', { timeout: 4000 });
    await page.waitForTimeout(600);
  } catch (_) {}

  try {
    const derrota = page.locator('[data-vida-derrota]:not([hidden])');
    if (await derrota.count()) {
      await page.click('[data-vida-derrota-ok]', { timeout: 3000 });
      await page.waitForTimeout(400);
    }
  } catch (_) {}

  await page.waitForTimeout(5000);

  const metrics = await page.evaluate(() => {
    const y = (sel) => Math.round((document.querySelector(sel)?.getBoundingClientRect().y) || -1);
    const order = (sel) => {
      const el = document.querySelector(sel);
      return el ? getComputedStyle(el).order : null;
    };
    const feed = [...document.querySelectorAll('.game-right > section')].map(el => ({
      cls: el.className.split(' ').filter(c => c.includes('movil') || c.includes('shell')).join(' '),
      y: Math.round(el.getBoundingClientRect().y),
      display: getComputedStyle(el).display,
    })).filter(x => x.display !== 'none').sort((a,b) => a.y - b.y);
    return {
      mapY: y('.game-map-wrap'),
      eventY: y('[data-proximo-evento-slot]'),
      eventHidden: document.querySelector('[data-proximo-evento-slot]')?.hidden,
      feed,
      hasPasarRato: !!document.querySelector('[data-pasar-rato]'),
      hasBuzon: !!document.querySelector('[data-open="buzon"]'),
      hasVecinos: !!document.querySelector('[data-open="vecinos"]'),
      hasOrganizar: !!document.querySelector('[data-open="organizar"]'),
      hasInventario: !!document.querySelector('[data-open="inventario"], .control-inventario'),
      hasCotilleo: !!document.querySelector('.obj-cotilleo-par'),
      encTit: document.querySelector('.enc-mov-tit')?.textContent?.trim(),
      ppTit: document.querySelector('.pp-mov-tit')?.textContent?.trim(),
      phone: document.querySelector('.play-root')?.classList.contains('phone'),
    };
  });
  console.log('METRICS', JSON.stringify(metrics, null, 2));
  if (errors.length) console.log('JS_ERRORS', errors.join(' | '));

  const alto = await page.evaluate(() => Math.min(9000, Math.max(852, document.documentElement.scrollHeight)));
  await page.setViewportSize({ width: 393, height: alto });
  await page.waitForTimeout(300);
  await page.evaluate(() => window.scrollTo(0, 0));
  const shell = page.locator('.game-shell').first();
  if (await shell.count()) await shell.screenshot({ path: OUT });
  else await page.screenshot({ path: OUT, fullPage: true });
  console.log('OK', OUT);
  await browser.close();
})();
