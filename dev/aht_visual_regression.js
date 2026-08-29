#!/usr/bin/env node
'use strict';
/**
 * Regresion visual barata: Inicio mobile 390x844 + desktop 1440x900.
 * Compara auditoria geometrica y similitud de captura vs Golden Master.
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { chromium } = require('playwright');

const ROOT = path.join(__dirname, '..');
const manifest = JSON.parse(fs.readFileSync(path.join(ROOT, 'scripts/aht_visual_manifest.json'), 'utf8'));
const GM = manifest.goldenMaster;
const PROD_BASE = manifest.prodBase;
const SEED = GM.seed;

const args = process.argv.slice(2);
let captureOnly = false;
let targetBase = PROD_BASE;
for (let i = 0; i < args.length; i++) {
  if (args[i] === '--capture-only') captureOnly = true;
  if (args[i] === '--url' && args[i + 1]) targetBase = args[++i].replace(/\/$/, '');
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const sha256 = (buf) => crypto.createHash('sha256').update(buf).digest('hex');

async function dismissOverlays(page) {
  try {
    await page.waitForSelector('[data-tut-intro]:not([hidden])', { timeout: 4000 });
    await page.click('[data-tut-skip]', { timeout: 4000 });
    await sleep(500);
  } catch (e) { /* */ }
  for (const sel of [
    '[data-evento-pueblo-siguiente]',
    '[data-evt-pueblo-next]',
    '.evento-pueblo-modal button',
    '[data-inicio-evento-close]',
  ]) {
    try {
      const el = await page.$(sel);
      if (el && await el.isVisible()) {
        await el.click({ timeout: 2000 });
        await sleep(400);
      }
    } catch (e) { /* */ }
  }
}

async function captureViewport(label, viewport, fullPage, outfile) {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ serviceWorkers: 'block' });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  await page.setViewportSize(viewport);
  const url = `${targetBase}/play.php?config=playtest_01&seed=${SEED}`;
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForSelector('.game-shell', { timeout: 90000 });
  await dismissOverlays(page);
  await page.waitForFunction(
    () => document.querySelectorAll('[data-mapa-zonas] .mapa-zona-hit').length > 0,
    { timeout: 90000 }
  );
  await sleep(2000);
  await page.addStyleTag({
    content: '.aht-debug-float,.taller-debug,.playtest-cheats,.playtest-guia,.aht-design-tool{display:none!important}',
  });
  await page.evaluate(() => window.scrollTo(0, 0));
  await sleep(500);

  const audit = await page.evaluate(() => {
    const q = (s) => document.querySelector(s);
    const cs = (el) => (el ? getComputedStyle(el) : null);
    const tiles = q('.inicio-mobile-tiles');
    const stage = q('.inicio-stage');
    const stylesheets = Array.from(document.querySelectorAll('link[rel=stylesheet]')).map((l) => l.href);
    return {
      playRootClass: q('.play-root')?.className || null,
      inicioMobileDisplay: cs(q('.inicio-mobile'))?.display || null,
      inicioDesktopDisplay: cs(q('.inicio-desktop'))?.display || null,
      tilesHeight: tiles ? Math.round(tiles.getBoundingClientRect().height) : null,
      stageGrid: stage ? cs(stage).gridTemplateColumns : null,
      mapaHits: document.querySelectorAll('[data-mapa-zonas] .mapa-zona-hit').length,
      designToolVisible: !!(q('.aht-design-tool') && cs(q('.aht-design-tool')).display !== 'none'),
      hasMensajitosTile: !!q('.obj-buzon'),
      hasCotilleosBlock: !!q('.obj-cotilleo-par, .inicio-cotilleos-card'),
      stylesheetCount: stylesheets.length,
      hasInicioMobileCss: stylesheets.some((h) => /inicio-mobile\.css/.test(h)),
      hasInicioDesktopCss: stylesheets.some((h) => /inicio-desktop\.css/.test(h)),
    };
  });

  fs.mkdirSync(path.dirname(outfile), { recursive: true });
  await page.screenshot({ path: outfile, fullPage });
  await browser.close();
  const bytes = fs.readFileSync(outfile);
  return { outfile, sha256: sha256(bytes), bytes: bytes.length, url, audit, errors };
}

function auditOk(label, audit, goldenAudit) {
  const issues = [];
  const th = GM.auditThresholds || {};

  if (audit.mapaHits < (th.mapaHitsMin || 9)) {
    issues.push(`mapaHits=${audit.mapaHits} < min ${th.mapaHitsMin || 9}`);
  }
  if (!audit.hasMensajitosTile) issues.push('falta tile mensajitos');
  if (!audit.hasCotilleosBlock) issues.push('falta bloque cotilleos');
  if (audit.designToolVisible) issues.push('design tool visible');

  if (label === 'mobile') {
    if (audit.inicioMobileDisplay !== 'block' && audit.inicioMobileDisplay !== 'flex') {
      issues.push(`inicioMobileDisplay=${audit.inicioMobileDisplay}`);
    }
    if (audit.tilesHeight != null) {
      const min = th.tilesHeightMin || 100;
      const max = th.tilesHeightMax || 140;
      if (audit.tilesHeight < min || audit.tilesHeight > max) {
        issues.push(`tilesHeight=${audit.tilesHeight} fuera de [${min},${max}]`);
      }
    }
    if (goldenAudit && goldenAudit.tilesHeight != null && audit.tilesHeight != null) {
      const delta = Math.abs(audit.tilesHeight - goldenAudit.tilesHeight);
      if (delta > 25) issues.push(`tilesHeight delta=${delta}px vs golden`);
    }
  }

  if (label === 'desktop') {
    if (audit.inicioDesktopDisplay !== 'contents' && audit.inicioDesktopDisplay !== 'block') {
      issues.push(`inicioDesktopDisplay=${audit.inicioDesktopDisplay}`);
    }
    if (!audit.stageGrid || audit.stageGrid === 'none') {
      issues.push('stageGrid ausente');
    }
    if (goldenAudit && goldenAudit.stageGrid && audit.stageGrid !== goldenAudit.stageGrid) {
      issues.push(`stageGrid cambio: ${goldenAudit.stageGrid} -> ${audit.stageGrid}`);
    }
  }

  return issues;
}

function loadGoldenManifest() {
  const p = path.join(ROOT, 'golden-master/manifest.json');
  if (!fs.existsSync(p)) return null;
  return JSON.parse(fs.readFileSync(p, 'utf8'));
}

(async () => {
  const mobileOut = path.join(ROOT, GM.mobile.file);
  const desktopOut = path.join(ROOT, GM.desktop.file);
  const golden = loadGoldenManifest();

  const mobile = await captureViewport('mobile', { width: GM.mobile.width, height: GM.mobile.height }, true, mobileOut);
  const desktop = await captureViewport('desktop', { width: GM.desktop.width, height: GM.desktop.height }, false, desktopOut);

  const report = {
    checkedAt: new Date().toISOString(),
    targetBase,
    captureOnly,
    mobile,
    desktop,
    goldenAvailable: !!golden,
    ok: true,
    issues: [],
  };

  const mobileIssues = auditOk('mobile', mobile.audit, golden?.mobile?.audit);
  const desktopIssues = auditOk('desktop', desktop.audit, golden?.desktop?.audit);
  report.issues.push(...mobileIssues.map((i) => `mobile: ${i}`));
  report.issues.push(...desktopIssues.map((i) => `desktop: ${i}`));

  if (golden && !captureOnly) {
    if (golden.mobile?.sha256 && golden.mobile.sha256 !== mobile.sha256) {
      const sizeDelta = Math.abs((golden.mobile.bytes || 0) - mobile.bytes);
      if (sizeDelta > 50000) {
        report.issues.push(`mobile: captura muy distinta (delta bytes ${sizeDelta})`);
      }
    }
    if (golden.desktop?.sha256 && golden.desktop.sha256 !== desktop.sha256) {
      const sizeDelta = Math.abs((golden.desktop.bytes || 0) - desktop.bytes);
      if (sizeDelta > 80000) {
        report.issues.push(`desktop: captura muy distinta (delta bytes ${sizeDelta})`);
      }
    }
  }

  report.ok = captureOnly ? true : report.issues.length === 0;

  const manifestOut = {
    capturedAt: report.checkedAt,
    prodBase: targetBase,
    seed: SEED,
    originDeployIntegratedHead: require('child_process')
      .execSync('git rev-parse HEAD', { cwd: ROOT, encoding: 'utf8' }).trim(),
    mobile: {
      file: GM.mobile.file,
      sha256: mobile.sha256,
      bytes: mobile.bytes,
      audit: mobile.audit,
    },
    desktop: {
      file: GM.desktop.file,
      sha256: desktop.sha256,
      bytes: desktop.bytes,
      audit: desktop.audit,
    },
  };

  fs.mkdirSync(path.join(ROOT, 'golden-master'), { recursive: true });
  fs.writeFileSync(path.join(ROOT, 'golden-master/manifest.json'), JSON.stringify(manifestOut, null, 2));

  const logPath = path.join(ROOT, 'logs/aht-visual-regression-latest.json');
  fs.mkdirSync(path.dirname(logPath), { recursive: true });
  fs.writeFileSync(logPath, JSON.stringify(report, null, 2));

  console.log(JSON.stringify({
    ok: report.ok,
    issues: report.issues,
    mobileAudit: mobile.audit,
    desktopAudit: desktop.audit,
    mobileSha: mobile.sha256.slice(0, 16),
    desktopSha: desktop.sha256.slice(0, 16),
    manifest: 'golden-master/manifest.json',
    log: logPath,
  }, null, 2));

  process.exit(report.ok ? 0 : 1);
})().catch((e) => {
  console.error('FATAL', e);
  process.exit(2);
});
