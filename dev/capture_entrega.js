const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const outDir = path.join(__dirname, 'screenshots-art');
fs.mkdirSync(outDir, { recursive: true });

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e)));

  await page.goto('http://localhost:8765/play.php?lab=1&config=playtest_01&agenda_demo=1', {
    waitUntil: 'networkidle',
    timeout: 90000,
  });
  await page.waitForSelector('.game-shell', { timeout: 30000 });
  await page.waitForTimeout(4500);

  const overflow = await page.evaluate(() => {
    function delta(el) {
      return el ? el.scrollWidth - el.clientWidth : 0;
    }
    function topOffenders(root) {
      if (!root) return [];
      const list = [];
      root.querySelectorAll('*').forEach(function (el) {
        const d = el.scrollWidth - el.clientWidth;
        if (d > 1) {
          list.push({
            cls: (el.className || el.tagName || '').toString().slice(0, 70),
            d: d,
          });
        }
      });
      return list.sort(function (a, b) { return b.d - a.d; }).slice(0, 5);
    }
    const left = document.querySelector('.game-left');
    const right = document.querySelector('.game-right');
    return {
      doc: delta(document.documentElement),
      shell: delta(document.querySelector('.game-shell')),
      left: delta(left),
      right: delta(right),
      leftOffenders: topOffenders(left),
      rightOffenders: topOffenders(right),
    };
  });

  const audit = await page.evaluate(() => {
    const stats = Array.from(document.querySelectorAll('.vecinos-stat')).map(function (el) {
      return (el.querySelector('.vecinos-stat-k')?.textContent || '') + ':' +
        (el.querySelector('.vecinos-stat-v')?.textContent || '');
    });
    const pend = document.querySelector('[data-planes-pend]');
    const act = document.querySelector('.zona-actividad');
    return {
      stats: stats,
      hasBuzonStat: stats.some(function (s) { return /buz/i.test(s); }),
      parejasZeroShown: stats.some(function (s) { return /^parejas:0$/i.test(s); }),
      pendVisible: pend ? !pend.hidden : false,
      pendTxt: pend?.textContent?.replace(/\s+/g, ' ').trim() || '',
      proxHtml: document.querySelector('[data-proximo-plan]')?.innerHTML?.slice(0, 200) || '',
      hasLugId: act ? /lug_/.test(act.textContent || '') : false,
    };
  });

  await page.screenshot({ path: path.join(outDir, 'entrega-metricas-sociales.png'), fullPage: false });

  await page.screenshot({ path: path.join(outDir, 'entrega-planes-pendientes.png') });

  await page.click('[data-planes-pend]', { timeout: 5000 }).catch(function () {});
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(outDir, 'entrega-listado-planes.png') });

  await page.evaluate(function () {
    const host = document.querySelector('[data-plan-notif]');
    const nom = document.querySelector('[data-plan-notif-nombres]');
    const meta = document.querySelector('[data-plan-notif-meta]');
    document.querySelector('.play-root')?.removeAttribute('data-capa');
    if (host && nom && meta) {
      nom.textContent = 'Álex + Dani';
      meta.textContent = 'Parque · Hoy 17:00';
      host.hidden = false;
      host.classList.add('is-on');
    }
  });
  await page.waitForTimeout(400);
  await page.screenshot({ path: path.join(outDir, 'entrega-plan-confirmado.png') });

  await browser.close();
  console.log(JSON.stringify({ overflow, audit, typeErrors: errors.filter(function (e) { return e.includes('TypeError'); }) }, null, 2));
})();
