const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ headless: true });
  const p = await b.newPage();
  await p.goto('http://localhost:8765/play.php?lab=1&config=playtest_01&agenda_demo=1&art_demo=1', { waitUntil: 'networkidle' });
  await p.waitForSelector('.game-shell');
  const shell = await p.evaluate(() => ({
    kickers: [...document.querySelectorAll('.shell-grupo-kicker')].map((e) => e.textContent.trim()),
    order: [...document.querySelectorAll('.game-left .shell-grupo')].map((e) => e.className),
    bloquesTit: document.querySelector('.shell-grupo-residencias .zona-tit')?.textContent,
    pin: !!document.querySelector('.celestine-nota::before') || getComputedStyle(document.querySelector('.celestine-nota'), '::before').content !== 'none',
    caraSize: getComputedStyle(document.querySelector('.obj-pareja-cara') || document.body).width,
  }));
  await p.click('.game-left [data-open="buzon"]');
  await p.waitForTimeout(400);
  const capa = await p.evaluate(() => ({
    origin: document.querySelector('.play-root')?.getAttribute('data-capa-origin'),
    capa: document.querySelector('.play-root')?.getAttribute('data-capa'),
  }));
  console.log(JSON.stringify({ shell, capa }, null, 2));
  await b.close();
})();
