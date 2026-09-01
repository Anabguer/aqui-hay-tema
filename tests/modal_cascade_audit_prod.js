'use strict';
/* Auditoría runtime: computed styles modales en producción (desktop 1280px) */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const OUT = path.join(__dirname, 'modal_cascade_audit_prod.json');
const SHOTS = path.join(__dirname, 'modal_cascade_audit_shots');

async function readShell(page, capaSel) {
  const shell = page.locator(capaSel).first();
  if (!(await shell.count())) return { error: 'capa no encontrada' };
  const cs = await shell.evaluate((el) => {
    const s = getComputedStyle(el);
    return {
      width: s.width,
      maxWidth: s.maxWidth,
      maxHeight: s.maxHeight,
      height: s.height,
      position: s.position,
      transform: s.transform,
      zIndex: s.zIndex,
      visibility: s.visibility,
      display: s.display,
      backgroundColor: s.backgroundColor,
      padding: s.padding,
    };
  });
  const rect = await shell.evaluate((el) => {
    const r = el.getBoundingClientRect();
    return { width: r.width, height: r.height, top: r.top, left: r.left };
  });
  const classes = await shell.getAttribute('class');
  const close = page.locator(`${capaSel} .cerrar, ${capaSel} .ds-modal-close`).first();
  let closeCs = null;
  if (await close.count()) {
    closeCs = await close.evaluate((el) => {
      const s = getComputedStyle(el);
      return { backgroundColor: s.backgroundColor, color: s.color, border: s.border };
    });
  }
  const veloZ = await page.locator('.velo').first().evaluate((el) => getComputedStyle(el).zIndex).catch(() => null);
  return { classes, computed: cs, rect, close: closeCs, veloZ };
}

async function closeModal(page) {
  const close = page.locator('.play-root[data-capa] .cerrar[data-close], .play-root[data-capa] .ds-modal-close[data-close]').first();
  if (await close.count()) await close.click({ timeout: 3000 }).catch(() => {});
  await page.waitForTimeout(450);
}

(async () => {
  if (!fs.existsSync(SHOTS)) fs.mkdirSync(SHOTS, { recursive: true });
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await page.goto('https://intocables13.com/juegos/aqui-hay-tema/play.php', {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });
  await page.waitForTimeout(3500);

  const report = { viewport: '1280x900', url: page.url(), modals: {} };

  const steps = [
    { key: 'vecinos', trigger: '[data-inicio-view="desktop"] [data-open="vecinos"]', capa: '.capa-vecinos' },
    { key: 'misiones', trigger: '[data-inicio-view="desktop"] .obj-misiones-papel', capa: '.capa-misiones' },
    { key: 'inventario', trigger: '[data-inicio-view="desktop"] .control-inventario', capa: '.capa-inventario' },
    { key: 'organizar', trigger: '[data-inicio-view="desktop"] .obj-nuevo-plan', capa: '.capa-organizar' },
    { key: 'ajustes', trigger: '[data-open="ajustes"]', capa: '.capa-ajustes' },
    { key: 'vida_pueblo', trigger: '[data-open="vida_pueblo"]', capa: '.capa-vida-pueblo' },
  ];

  for (const step of steps) {
    try {
      const btn = page.locator(step.trigger).first();
      if (!(await btn.count())) {
        report.modals[step.key] = { error: 'sin trigger', trigger: step.trigger };
        continue;
      }
      await btn.click({ timeout: 8000 });
      await page.waitForTimeout(700);
      report.modals[step.key] = await readShell(page, step.capa);
      report.modals[step.key].dataCapa = await page.locator('.play-root').first().getAttribute('data-capa');
      await page.screenshot({ path: path.join(SHOTS, `desktop-${step.key}.png`) });
      await closeModal(page);
    } catch (e) {
      report.modals[step.key] = { error: String(e.message).slice(0, 300) };
    }
  }

  try {
    await page.locator('[data-inicio-view="desktop"] [data-open="vecinos"]').first().click();
    await page.waitForTimeout(600);
    await page.locator('[data-vec-tab="relaciones"]').click();
    await page.waitForTimeout(600);
    report.modals.relaciones = await readShell(page, '.capa-vecinos');
    report.modals.relaciones.tab = 'relaciones';
    await page.screenshot({ path: path.join(SHOTS, 'desktop-relaciones.png') });
    await closeModal(page);
  } catch (e) {
    report.modals.relaciones = { error: String(e.message).slice(0, 300) };
  }

  report.parejas = {
    desktopTrigger: await page.locator('[data-inicio-view="desktop"] [data-open="parejas"]').count(),
    mobileTrigger: await page.locator('[data-inicio-view="mobile"] [data-open="parejas"]').count(),
    desktopStrip: await page.locator('[data-inicio-view="desktop"] [data-parejas-strip]').count(),
    dataCapaAfterClickStrip: null,
  };
  try {
    const strip = page.locator('[data-inicio-view="desktop"] [data-parejas-strip]').first();
    if (await strip.count()) {
      await strip.click({ timeout: 3000 });
      await page.waitForTimeout(500);
      report.parejas.dataCapaAfterClickStrip = await page.locator('.play-root').first().getAttribute('data-capa');
    }
  } catch (e) {
    report.parejas.stripClickError = String(e.message).slice(0, 200);
  }

  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log('OK', OUT);
  await browser.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
