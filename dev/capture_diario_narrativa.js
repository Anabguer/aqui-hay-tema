'use strict';
/**
 * Capturas locales del Diario narrativo (mobile + desktop + filtro relaciones).
 * Uso: node dev/capture_diario_narrativa.js [partida_id]
 */
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const OUTDIR = path.join(ROOT, 'dev/forensic-screenshots-diario-20260829');
const PORT = 8162;
const BASE = 'http://127.0.0.1:' + PORT;
const PARTIDA = process.argv[2] || 'e2erit-part_5af4821';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function startServer() {
  return new Promise((resolve, reject) => {
    const child = spawn('php', ['-S', '127.0.0.1:' + PORT, '-t', ROOT], {
      cwd: ROOT,
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    child.on('error', reject);
    setTimeout(() => resolve(child), 800);
  });
}

async function captureDiario(page, view, filt, outfile) {
  const q = new URLSearchParams({
    partida_id: PARTIDA,
    capa: 'ficha_diario',
    view: view,
  });
  await page.goto(BASE + '/dev/visual_validate_content.php?' + q.toString(), {
    waitUntil: 'networkidle',
    timeout: 90000,
  });
  await sleep(4500);
  if (filt) {
    await page.evaluate((f) => {
      const btn = document.querySelector('[data-diario-filt="' + f + '"]');
      if (btn) btn.click();
    }, filt);
    await sleep(600);
  }
  const sheet = page.locator('.capa-ficha-diario');
  await sheet.waitFor({ state: 'visible', timeout: 20000 });
  await page.screenshot({ path: outfile, fullPage: false });
}

async function main() {
  fs.mkdirSync(OUTDIR, { recursive: true });
  const server = await startServer();
  const browser = await chromium.launch({ headless: true });
  try {
    const mobile = await browser.newPage({ viewport: { width: 390, height: 844 } });
    await captureDiario(
      mobile,
      'mobile',
      null,
      path.join(OUTDIR, 'DIARIO_MOBILE_TODO.png')
    );
    await captureDiario(
      mobile,
      'mobile',
      'relaciones',
      path.join(OUTDIR, 'DIARIO_MOBILE_RELACIONES.png')
    );
    await mobile.close();

    const desktop = await browser.newPage({ viewport: { width: 1440, height: 900 } });
    await captureDiario(
      desktop,
      'desktop',
      null,
      path.join(OUTDIR, 'DIARIO_DESKTOP_TODO.png')
    );
    await desktop.close();
    console.log('OK screenshots ->', OUTDIR);
  } finally {
    await browser.close();
    server.kill('SIGTERM');
  }
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
