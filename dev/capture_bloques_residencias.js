const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const outDir = path.join(__dirname, 'screenshots-bloques');
fs.mkdirSync(outDir, { recursive: true });

const BASE = 'http://localhost:8765/play.php?lab=1&config=playtest_01';

async function waitGame(page) {
  await page.waitForSelector('[data-open-bloques]', { timeout: 90000 });
  await page.waitForTimeout(2000);
}

function setBloquesImg(page, key) {
  return page.evaluate((k) => {
    const img = document.querySelector('.obj-bloques-img');
    if (img) img.src = 'assets/play-v3/shell/bloques_estado_' + k + '.png';
  }, key);
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

  await page.addInitScript(() => {
    const orig = window.fetch.bind(window);
    window.fetch = async function (input, init) {
      const res = await orig(input, init);
      const url = String(input);
      if (!url.includes('partida.inspeccionar')) return res;
      try {
        const data = await res.clone().json();
        if (data && data.ok && data.partida) {
          data.partida.celeste = data.partida.celeste || {};
          data.partida.celeste.bloques_abiertos = ['a', 'b', 'c'];
          const vacias = (bloque) => {
            const out = [];
            const B = bloque.toUpperCase();
            for (let i = 1; i <= 16; i++) {
              out.push({
                id: B + String(i).padStart(2, '0'),
                ocupante_id: null,
                estado: 'libre',
              });
            }
            return out;
          };
          ['b', 'c'].forEach((letra) => {
            const key = 'bloque_' + letra;
            data.partida[key] = data.partida[key] || { capacidad: 16, viviendas: [] };
            if (!data.partida[key].viviendas || data.partida[key].viviendas.length < 16) {
              data.partida[key].viviendas = vacias(letra);
            }
            data.partida[key].capacidad = 16;
          });
        }
        return new Response(JSON.stringify(data), {
          status: res.status,
          statusText: res.statusText,
          headers: res.headers,
        });
      } catch (e) {
        return res;
      }
    };
  });

  await page.goto(BASE, { waitUntil: 'networkidle', timeout: 120000 });
  await waitGame(page);

  const clipRight = { x: 1180, y: 60, width: 240, height: 340 };

  await setBloquesImg(page, 'a');
  await page.screenshot({ path: path.join(outDir, '01-estado-solo-a.png'), clip: clipRight });

  await setBloquesImg(page, 'ab');
  await page.screenshot({ path: path.join(outDir, '02-estado-a-b.png'), clip: clipRight });

  await setBloquesImg(page, 'abc');
  await page.screenshot({ path: path.join(outDir, '03-estado-a-b-c.png'), clip: clipRight });

  await page.click('[data-open-bloques]');
  await page.waitForTimeout(900);

  await page.evaluate(() => {
    const grid = document.querySelector('[data-res-grid]');
    if (!grid) return;
    const sample = grid.querySelector('.res-celda');
    if (!sample) return;
    const chunk = grid.innerHTML;
    grid.innerHTML = chunk + chunk;
  });

  await page.screenshot({ path: path.join(outDir, '04-capa-residentes-16.png') });

  const bloqueB = await page.$('[data-res-bloque="b"]');
  if (bloqueB) {
    await bloqueB.click();
    await page.waitForTimeout(500);
    await page.screenshot({ path: path.join(outDir, '06-cambio-bloque-b.png') });
  }

  await page.fill('[data-res-busca]', 'a');
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(outDir, '05-buscador.png') });

  await browser.close();
  console.log('OK ->', outDir);
})();
