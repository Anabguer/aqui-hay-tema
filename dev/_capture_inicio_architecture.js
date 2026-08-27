'use strict';
const { chromium } = require('playwright');
const path = require('path');
const http = require('http');
const fs = require('fs');
const { spawn } = require('child_process');

const root = path.join(__dirname, '..');
const outDir = path.join(root, 'dev', 'visual-play-evidence');
fs.mkdirSync(outDir, { recursive: true });

const mobOut = path.join(outDir, 'inicio-architecture-mobile-393.png');
const deskOut = path.join(outDir, 'inicio-architecture-desktop-1280.png');

function waitForServer(url, ms) {
  const start = Date.now();
  return new Promise(function (resolve, reject) {
    (function poll() {
      http.get(url, function (res) {
        res.resume();
        resolve();
      }).on('error', function () {
        if (Date.now() - start > ms) reject(new Error('server timeout'));
        else setTimeout(poll, 400);
      });
    })();
  });
}

(async () => {
  const php = spawn('php', ['-S', '127.0.0.1:8765', '-t', root], { cwd: root, stdio: 'ignore' });
  await waitForServer('http://127.0.0.1:8765/play.php', 15000);
  const browser = await chromium.launch();
  try {
    const base = 'http://127.0.0.1:8765/play.php';

    const mob = await browser.newPage({ viewport: { width: 393, height: 852 } });
    await mob.goto(base, { waitUntil: 'networkidle', timeout: 120000 });
    await mob.waitForTimeout(4000);
    const mobMetrics = await mob.evaluate(() => ({
      mobileVisible: !!document.querySelector('.inicio-mobile:not([hidden])'),
      desktopHidden: document.querySelector('.inicio-desktop')?.hidden,
      mapHost: !!document.querySelector('.inicio-map-host'),
      tiles: document.querySelectorAll('.inicio-mobile-tiles .shell-grupo').length,
      feed: document.querySelectorAll('.inicio-mobile-feed-inner .shell-grupo').length,
    }));
    console.log('mobile metrics', JSON.stringify(mobMetrics));
    await mob.screenshot({ path: mobOut, fullPage: true });

    const desk = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    await desk.goto(base, { waitUntil: 'networkidle', timeout: 120000 });
    await desk.waitForTimeout(4000);
    const deskMetrics = await desk.evaluate(() => ({
      mobileHidden: document.querySelector('.inicio-mobile')?.hidden,
      desktopVisible: !!document.querySelector('.inicio-desktop:not([hidden])'),
      leftBlocks: document.querySelectorAll('.inicio-desktop-left .shell-grupo').length,
      rightBlocks: document.querySelectorAll('.inicio-desktop-right .shell-grupo').length,
    }));
    console.log('desktop metrics', JSON.stringify(deskMetrics));
    await desk.screenshot({ path: deskOut, fullPage: true });

    console.log('screenshots:', mobOut, deskOut);
  } finally {
    await browser.close();
    php.kill();
  }
})().catch(function (e) {
  console.error(e);
  process.exit(1);
});
