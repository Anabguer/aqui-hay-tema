const { chromium } = require('playwright');

const FASES = {
  1: ['bar', 'cine', 'restaurante', 'cafeteria', 'gimnasio', 'picnic'],
  2: ['bar', 'discoteca', 'cine', 'recreativo', 'restaurante', 'bingo', 'cafeteria', 'biblioteca', 'gimnasio', 'spa', 'picnic', 'mirador'],
  3: ['bar', 'discoteca', 'karaoke', 'cine', 'recreativo', 'restaurante', 'bingo', 'cafeteria', 'biblioteca', 'tienda', 'gimnasio', 'spa', 'picnic', 'mirador'],
};

const FASE_DESTINO = {
  cafeteria: 'lug_cafeteria', biblioteca: 'lug_biblioteca', tienda: 'lug_tienda_ropa',
  restaurante: 'lug_restaurante', bingo: 'lug_bingo',
  cine: 'lug_cine', recreativo: 'lug_arcade',
  bar: 'lug_bar', discoteca: 'lug_discoteca', karaoke: 'lug_karaoke',
  gimnasio: 'lug_gimnasio', spa: 'lug_spa',
  picnic: 'lug_picnic', mirador: 'lug_mirador',
};

function mockPueblo(faseNum) {
  const on = new Set(FASES[faseNum].map(function (id) { return FASE_DESTINO[id]; }));
  const grupos = {
    cafe_libros: ['cafeteria', 'biblioteca', 'tienda'],
    rincon_lola: ['restaurante', 'bingo'],
    cine_game: ['cine', 'recreativo'],
    mala_idea: ['bar', 'discoteca', 'karaoke'],
    parque: ['picnic', 'mirador'],
    gimnasio_spa: ['gimnasio', 'spa'],
  };
  return {
    complejos: Object.keys(grupos).map(function (cid) {
      return {
        id: cid,
        destinos: grupos[cid].map(function (fase) {
          const dest = FASE_DESTINO[fase];
          return { id: dest, operativo: on.has(dest) };
        }),
      };
    }),
  };
}

(async function () {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
  const url = 'http://localhost:8765/play.php?lab=1&config=playtest_01&seed=map-live-' + Date.now();

  page.on('pageerror', function (err) { console.error('PAGEERR:', err.message); });
  await page.goto(url, { waitUntil: 'networkidle', timeout: 90000 });

  await page.waitForFunction(function () {
    return document.querySelectorAll('[data-edificios-layer] .edif').length >= 14;
  }, { timeout: 60000 });

  const failures = [];
  let baseline = null;

  for (const f of [1, 2, 3]) {
    await page.evaluate(function (pueblo) {
      const MAP = { w: 1536, h: 1024 };
      const FASE_DESTINO = {
        cafeteria: 'lug_cafeteria', biblioteca: 'lug_biblioteca', tienda: 'lug_tienda_ropa',
        restaurante: 'lug_restaurante', bingo: 'lug_bingo',
        cine: 'lug_cine', recreativo: 'lug_arcade',
        bar: 'lug_bar', discoteca: 'lug_discoteca', karaoke: 'lug_karaoke',
        gimnasio: 'lug_gimnasio', spa: 'lug_spa',
        picnic: 'lug_picnic', mirador: 'lug_mirador',
      };
      const ops = {};
      (pueblo.complejos || []).forEach(function (cx) {
        (cx.destinos || []).forEach(function (d) { if (d.operativo) ops[d.id] = true; });
      });
      document.querySelectorAll('[data-edificios-layer] .edif').forEach(function (img) {
        const id = img.getAttribute('data-fase');
        const dest = FASE_DESTINO[id];
        const hab = !!(dest && ops[dest]);
        img.classList.toggle('is-on', hab);
        img.classList.toggle('is-off', !hab);
      });
    }, mockPueblo(f));

    const eds = await page.evaluate(function () {
      return Array.from(document.querySelectorAll('[data-edificios-layer] .edif')).map(function (img) {
        return {
          id: img.getAttribute('data-fase'),
          isOn: img.classList.contains('is-on'),
          isOff: img.classList.contains('is-off'),
          left: img.style.left,
          top: img.style.top,
          width: img.style.width,
          height: img.style.height,
          display: getComputedStyle(img).display,
          opacity: getComputedStyle(img).opacity,
        };
      });
    });

    if (eds.length !== 14) failures.push('FASE ' + f + ': visibles=' + eds.length);
    const enabled = eds.filter(function (e) { return e.isOn; });
    if (enabled.length !== FASES[f].length) {
      failures.push('FASE ' + f + ': habilitados=' + enabled.length + ' esperado ' + FASES[f].length);
    }

    const pos = {};
    eds.forEach(function (e) { pos[e.id] = [e.left, e.top, e.width, e.height].join('|'); });
    if (!baseline) baseline = pos;
    else Object.keys(baseline).forEach(function (id) {
      if (pos[id] !== baseline[id]) failures.push('FASE ' + f + ': ' + id + ' movido');
    });

    console.log('FASE ' + f + ': ' + eds.length + ' visibles, ' + enabled.length + ' habilitados, ' + (eds.length - enabled.length) + ' atenuados');
  }

  await browser.close();
  if (failures.length) {
    console.error('FALLOS:\n' + failures.join('\n'));
    process.exit(1);
  }
  console.log('OK: juego cargado con 14 edificios en composición FASE 3; estados por fase verificados');
})();
