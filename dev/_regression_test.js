const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
  const logs = [];
  page.on('console', (m) => logs.push(m.type() + ': ' + m.text()));
  page.on('pageerror', (e) => logs.push('ERR: ' + e.message));

  await page.goto('http://127.0.0.1:8765/play.php?lab=1&config=playtest_01', {
    waitUntil: 'networkidle',
    timeout: 60000,
  });
  await page.waitForTimeout(4000);

  const state = await page.evaluate(() => {
    const root = document.querySelector('.play-root');
    const capas = [...document.querySelectorAll('.capa')].map((c) => {
      const r = c.getBoundingClientRect();
      const st = getComputedStyle(c);
      const onScreen = r.width > 0 && r.left < innerWidth && r.right > 0 && r.top < innerHeight && r.bottom > 0;
      return {
        cls: c.className,
        transform: st.transform,
        pe: st.pointerEvents,
        onScreen,
        rect: { x: r.x, y: r.y, w: r.width, h: r.height },
      };
    });
    const closeBtns = [...document.querySelectorAll('.capa .cerrar')].map((b) => ({
      cls: b.className,
      display: getComputedStyle(b).display,
      visible: b.getBoundingClientRect().width > 0 && getComputedStyle(b).display !== 'none',
    }));
    return {
      dataCapa: root?.getAttribute('data-capa'),
      dataOrigin: root?.getAttribute('data-capa-origin'),
      capas,
      closeBtns,
      title: document.title,
      celestine: document.querySelector('.celestine-nota')?.textContent?.slice(0, 80),
      proximo: document.querySelector('.obj-proximo-tit')?.textContent,
    };
  });

  console.log('INITIAL', JSON.stringify(state, null, 2));
  console.log('CONSOLE', logs.slice(0, 15));
  await page.screenshot({ path: 'W:/juegos/aqui-hay-tema/dev/_regression_initial.png' });

  async function testCapa(openSel, capaName) {
    await page.click(openSel);
    await page.waitForTimeout(400);
    const open = await page.evaluate(() => ({
      capa: document.querySelector('.play-root')?.getAttribute('data-capa'),
      visible: [...document.querySelectorAll('.capa')].filter((c) => {
        const r = c.getBoundingClientRect();
        const st = getComputedStyle(c);
        return st.transform === 'none' || st.transform.includes('matrix(1, 0, 0, 1, 0, 0)');
      }).map((c) => c.className),
    }));
    console.log('OPEN', capaName, open);
    const closeBtn = await page.$('.capa-' + capaName + ' .cerrar');
    if (closeBtn) {
      const vis = await closeBtn.evaluate((b) => getComputedStyle(b).display);
      console.log('CLOSE DISPLAY', capaName, vis);
      await closeBtn.click();
      await page.waitForTimeout(400);
      const closed = await page.evaluate(() => document.querySelector('.play-root')?.getAttribute('data-capa'));
      console.log('CLOSED', capaName, closed);
    }
  }

  await testCapa('[data-open="buzon"]', 'buzon');
  await testCapa('[data-open="organizar"]', 'organizar');
  await testCapa('[data-open="vecinos"]', 'vecinos');

  const pend = await page.$('[data-planes-pend]');
  if (pend) {
    await pend.click();
    await page.waitForTimeout(400);
    await page.screenshot({ path: 'W:/juegos/aqui-hay-tema/dev/_regression_planes.png' });
    const agenda = await page.evaluate(() => document.querySelector('.play-root')?.getAttribute('data-capa'));
    console.log('PLANES', agenda);
    await page.click('.capa-agenda .cerrar');
    await page.waitForTimeout(400);
  }

  await browser.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
