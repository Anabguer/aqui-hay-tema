const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://localhost:8765/play.php?lab=1&config=playtest_01&agenda_demo=1&art_demo=1', { waitUntil: 'networkidle', timeout: 90000 });
  await page.waitForSelector('.game-shell', { timeout: 30000 });
  await page.waitForTimeout(2000);
  const info = await page.evaluate(() => {
    const blocks = [...document.querySelectorAll('[data-bloques-row] button, [data-bloques-row] .obj-residencia-mini')].map((b) => ({
      cls: b.className,
      txt: b.textContent.trim().slice(0, 40),
    }));
    const parejas = [...document.querySelectorAll('.obj-pareja-piece, .obj-pareja-fila')].map((p) => ({
      crisis: p.classList.contains('is-crisis'),
      txt: p.textContent.trim().slice(0, 50),
    }));
    const stats = document.querySelector('[data-resumen-stats]')?.innerHTML.slice(0, 200);
    const grupos = [...document.querySelectorAll('.shell-grupo')].map((g) => g.className);
    const pend = document.querySelector('[data-planes-pend]')?.textContent?.trim();
    const buzon = document.querySelector('[data-buzon-badge]')?.textContent;
    return { blocks, parejas, stats, grupos, pend, buzon, residenciasTit: document.querySelector('.shell-grupo-residencias .zona-tit')?.textContent };
  });
  console.log(JSON.stringify(info, null, 2));
  await browser.close();
})();
