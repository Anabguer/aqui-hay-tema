'use strict';
const { spawn, execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const root = __dirname.replace(/\\dev$/, '').replace(/\/dev$/, '');
const PORT = 8765;
const BASE = 'http://127.0.0.1:' + PORT;
const OUTDIR = path.join(root, 'dev', 'screenshots', 'tutorial-neni-v3');

function loadFixture(mode) {
  const raw = execSync('php dev/tutorial_neni_refresh.php ' + mode, {
    cwd: root,
    encoding: 'utf8',
    maxBuffer: 20e6,
    stdio: ['ignore', 'pipe', 'ignore'],
  });
  return JSON.parse(raw);
}

async function routePartida(page, fixture) {
  const { partida_id: pid, refresh } = fixture;
  await page.route('**/api/index.php?action=partida.listar**', (route) => {
    route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({ ok: true, partidas: [{ partida_id: pid, nombre: 'Tutorial Neni' }] }),
    });
  });
  await page.route('**/api/index.php?action=partida.refresh**', (route) => {
    route.fulfill({ contentType: 'application/json; charset=utf-8', body: JSON.stringify(refresh) });
  });
  await page.route('**/api/index.php?action=partida.nueva**', (route) => {
    route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, partida_id: pid }) });
  });
  await page.addInitScript((id) => {
    try {
      localStorage.setItem('aht_partida_id_juego', id);
      localStorage.setItem('aht_partida_id', id);
      Object.keys(localStorage).forEach((k) => {
        if (k.startsWith('aht_intro_v1_')) localStorage.removeItem(k);
      });
    } catch (_) {}
  }, pid);
}

async function waitTutorial(page) {
  await page.waitForSelector('[data-tut-intro]:not([hidden])', { timeout: 60000 });
  await page.waitForSelector('.tut-hero-img', { timeout: 15000 });
  await page.waitForTimeout(600);
}

async function metrics(page) {
  return page.evaluate(() => {
    const papel = document.querySelector('[data-tut-intro]:not([hidden]) .tut-papel, [data-tut-finale]:not([hidden]) .tut-papel');
    const atras = document.querySelector('[data-tut-atras]');
    const hero = document.querySelector('.tut-hero-img, [data-tut-fin-hero] .tut-hero-img');
    const cs = papel ? getComputedStyle(papel) : null;
    const bodyCs = papel ? getComputedStyle(papel.querySelector('.tut-papel-cuerpo') || papel) : null;
    return {
      shellH: papel ? papel.getBoundingClientRect().height : 0,
      overflowY: cs ? cs.overflowY : '',
      cuerpoOverflow: bodyCs ? bodyCs.overflowY : '',
      scrollH: papel ? papel.scrollHeight : 0,
      clientH: papel ? papel.clientHeight : 0,
      heroSrc: hero ? hero.getAttribute('src') : '',
      atrasText: atras ? atras.textContent : '',
    };
  });
}

async function shot(page, file) {
  const target = path.join(OUTDIR, file);
  await page.screenshot({ path: target, fullPage: false });
  console.log('SHOT ' + file);
  return target;
}

(async () => {
  fs.mkdirSync(OUTDIR, { recursive: true });
  const introFix = loadFixture('intro');
  const finFix = loadFixture('finale');
  const phpSrv = spawn('php', ['-S', '127.0.0.1:' + PORT, '-t', root], { stdio: 'ignore', cwd: root });
  await new Promise((r) => setTimeout(r, 1200));
  const browser = await chromium.launch({ headless: true });
  const checks = [];

  try {
    for (const [label, vp] of [['desktop', { width: 1280, height: 800 }], ['mobile', { width: 390, height: 844 }]]) {
      const page = await browser.newPage({ viewport: vp, deviceScaleFactor: 1 });
      await routePartida(page, introFix);
      await page.goto(BASE + '/play.php', { waitUntil: 'domcontentloaded', timeout: 120000 });
      await waitTutorial(page);
      for (let step = 1; step <= 4; step++) {
        const m = await metrics(page);
        checks.push({ label, step, ...m });
        await shot(page, label + '-step-' + step + '.png');
        if (step < 4) {
          await page.click('[data-tut-siguiente]');
          await page.waitForTimeout(500);
        }
      }
      await page.close();

      const fp = await browser.newPage({ viewport: vp, deviceScaleFactor: 1 });
      await routePartida(fp, finFix);
      await fp.goto(BASE + '/play.php', { waitUntil: 'domcontentloaded', timeout: 120000 });
      await fp.waitForTimeout(1500);
      await fp.waitForSelector('[data-tut-finale]:not([hidden])', { timeout: 20000 });
      await fp.waitForSelector('[data-tut-finale] .tut-hero-img', { timeout: 10000 });
      await fp.waitForTimeout(500);
      const m5 = await metrics(fp);
      checks.push({ label, step: 5, ...m5 });
      await shot(fp, label + '-step-5-finale.png');
      await fp.close();
    }
  } finally {
    await browser.close().catch(() => {});
    phpSrv.kill();
  }

  const report = path.join(OUTDIR, 'checks.json');
  fs.writeFileSync(report, JSON.stringify(checks, null, 2));
  let ok = true;
  for (const c of checks) {
    const heroOk = (c.heroSrc || '').includes('Cabecera.png');
    const scrollOk = c.scrollH <= c.clientH + 2;
    const overflowOk = c.overflowY === 'hidden' && (c.step === 5 || c.cuerpoOverflow === 'hidden');
    const atrasOk = c.step !== 2 || c.atrasText === 'Atrás';
    if (!heroOk || !scrollOk) ok = false;
    console.log(JSON.stringify({ ...c, heroOk, scrollOk, overflowOk, atrasOk }));
  }
  if (!ok) process.exit(1);
  console.log('CAPTURE OK -> ' + OUTDIR);
})().catch((e) => { console.error(e); process.exit(1); });