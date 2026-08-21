/**
 * Valida composición definitiva: posiciones FASE 3 fijas y conteos habilitados por fase.
 * Uso: node dev/validate_composicion.js
 */
const fs = require('fs');
const path = require('path');
const http = require('http');

const root = path.join(__dirname, '..');
const comp = JSON.parse(fs.readFileSync(path.join(root, 'assets/play-v3/edificios_composicion.json'), 'utf8'));
const FASE_DESTINO = {
  cafeteria: 'lug_cafeteria', biblioteca: 'lug_biblioteca', tienda: 'lug_tienda_ropa',
  restaurante: 'lug_restaurante', bingo: 'lug_bingo',
  cine: 'lug_cine', recreativo: 'lug_arcade',
  bar: 'lug_bar', discoteca: 'lug_discoteca', karaoke: 'lug_karaoke',
  gimnasio: 'lug_gimnasio', spa: 'lug_spa',
  picnic: 'lug_picnic', mirador: 'lug_mirador',
};

const FASES = {
  1: ['bar', 'cine', 'restaurante', 'cafeteria', 'gimnasio', 'picnic'],
  2: ['bar', 'discoteca', 'cine', 'recreativo', 'restaurante', 'bingo', 'cafeteria', 'biblioteca', 'gimnasio', 'spa', 'picnic', 'mirador'],
  3: Object.keys(comp.edificios),
};

function posKey(id) {
  const c = comp.edificios[id];
  return [c.x, c.y, c.ancho, c.alto].join(',');
}

function habilitados(faseNum) {
  return FASES[faseNum].map(function (id) { return FASE_DESTINO[id]; });
}

function fetchJson(url) {
  return new Promise(function (resolve, reject) {
    http.get(url, function (res) {
      let raw = '';
      res.on('data', function (c) { raw += c; });
      res.on('end', function () {
        try { resolve(JSON.parse(raw)); } catch (e) { reject(e); }
      });
    }).on('error', reject);
  });
}

async function main() {
  const failures = [];
  const ids = Object.keys(comp.edificios);
  if (ids.length !== 14) failures.push('edificios count=' + ids.length + ' expected 14');

  const basePos = {};
  ids.forEach(function (id) { basePos[id] = posKey(id); });

  [1, 2, 3].forEach(function (f) {
    ids.forEach(function (id) {
      if (posKey(id) !== basePos[id]) {
        failures.push('fase ' + f + ' moved ' + id);
      }
    });
    const exp = FASES[f].length;
    const got = habilitados(f).length;
    if (exp !== got) failures.push('fase ' + f + ' habilitados count');
  });

  if (FASES[2].indexOf('karaoke') >= 0 || FASES[2].indexOf('tienda') >= 0) {
    failures.push('fase2 should not include karaoke/tienda in habilitados list');
  }
  if (FASES[1].indexOf('discoteca') >= 0) {
    failures.push('fase1 should not include discoteca');
  }

  try {
    const j = await fetchJson('http://localhost:8765/assets/play-v3/edificios_composicion.json');
    if (!j.edificios || Object.keys(j.edificios).length !== 14) {
      failures.push('HTTP JSON edificios_composicion invalid');
    }
  } catch (e) {
    failures.push('HTTP fetch edificios_composicion: ' + e.message);
  }

  try {
    const html = await new Promise(function (resolve, reject) {
      http.get('http://localhost:8765/play.php?lab=1', function (res) {
        let raw = '';
        res.on('data', function (c) { raw += c; });
        res.on('end', function () { resolve(raw); });
      }).on('error', reject);
    });
    if (html.indexOf('data-edificios-layer') < 0) failures.push('play.php missing edificios layer');
    if (html.indexOf('play-v3.js') < 0) failures.push('play.php missing play-v3.js');
  } catch (e) {
    failures.push('HTTP play.php: ' + e.message);
  }

  console.log('=== Validación composición ===');
  console.log('Edificios en JSON:', ids.length);
  console.log('FASE 1 habilitados:', FASES[1].length, '→', FASES[1].join(', '));
  console.log('FASE 2 habilitados:', FASES[2].length, '→ deshabilitados: karaoke, tienda');
  console.log('FASE 3 habilitados:', FASES[3].length);
  console.log('Posiciones base (muestra bar):', comp.edificios.bar);

  if (failures.length) {
    console.error('FALLOS:', failures.join('; '));
    process.exit(1);
  }
  console.log('OK: posiciones fijas y conteos de fase correctos');
}

main();
