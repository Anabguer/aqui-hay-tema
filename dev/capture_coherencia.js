const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const outDir = path.join(__dirname, 'screenshots-art');
fs.mkdirSync(outDir, { recursive: true });

(async () => {
  const errors = [];
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  page.on('pageerror', (e) => errors.push(String(e)));

  await page.goto('http://localhost:8765/play.php?lab=1&config=playtest_01', { waitUntil: 'networkidle', timeout: 90000 });
  await page.waitForSelector('.game-shell', { timeout: 30000 });
  await page.waitForTimeout(3500);

  const audit = await page.evaluate(() => {
    const shell = document.querySelector('.game-shell');
    const left = document.querySelector('.game-left');
    return {
      vecinosTit: document.querySelector('.obj-vecinos-tit')?.textContent,
      stats: document.querySelectorAll('.vecinos-stat').length,
      hasBuzonInStats: !!document.querySelector('.vecinos-stat-k')?.textContent?.match(/buz/i),
      bloques: document.querySelectorAll('.obj-bloque-mini').length,
      bloqueA: document.querySelector('.bloque-a')?.className,
      parejasDemo: !!document.querySelector('.obj-lab-note'),
      proxDemo: document.querySelector('.obj-proximo-body .prox-faces')?.children.length || 0,
      overflowX: {
        body: getComputedStyle(document.body).overflowX,
        shell: shell ? getComputedStyle(shell).overflowX : null,
        leftScroll: left ? left.scrollWidth > left.clientWidth : false,
      },
      hasPuebloBtn: !!document.querySelector('.obj-pueblo'),
      renderBuzonOk: true,
    };
  });

  await page.screenshot({ path: path.join(outDir, 'coherencia-general.png') });

  await page.click('[data-open="organizar"]');
  await page.waitForTimeout(800);
  const org = await page.evaluate(() => {
    const opts = Array.from(document.querySelectorAll('[data-org-lugar] option')).map((o) => o.textContent);
    const horas = Array.from(document.querySelectorAll('[data-org-hora] option')).map((o) => o.value);
    return { lugares: opts.filter((t) => t && t !== '—'), horas, hasTienda: opts.some((t) => /tienda/i.test(t)) };
  });
  await page.screenshot({ path: path.join(outDir, 'coherencia-organizar.png') });

  await browser.close();
  console.log(JSON.stringify({ audit, org, typeErrors: errors.filter((e) => e.includes('TypeError')) }, null, 2));
})();
