/* eslint-disable */
const fs = require('fs');
const path = require('path');

function extractFn(src, name) {
  const sig = 'function ' + name + '(';
  const start = src.indexOf(sig);
  if (start < 0) throw new Error('missing ' + name);
  let i = src.indexOf('{', start);
  let depth = 0;
  for (; i < src.length; i++) {
    const ch = src[i];
    if (ch === '{') depth++;
    else if (ch === '}') {
      depth--;
      if (depth === 0) return src.slice(start, i + 1);
    }
  }
  throw new Error('unclosed ' + name);
}

const file = path.join(__dirname, '..', 'assets', 'js', 'play-v3.js');
const src = fs.readFileSync(file, 'utf8');
const code = extractFn(src, 'nombreLugar') + '\n' + extractFn(src, 'metricasSociales') + '\n';
const fn = new Function('cachePueblo', 'DESTINO_NOMBRE', code + 'return { nombreLugar: nombreLugar, metricasSociales: metricasSociales };');
const api = fn(
  {
    complejos: [{
      destinos: [{ id: 'lug_biblioteca', nombre: 'Biblioteca' }],
      destinos_operativos: [{ id: 'lug_cafeteria', nombre: 'Cafetería' }],
    }],
  },
  { lug_biblioteca: 'Biblioteca', lug_parque: 'Parque' }
);

const nl = api.nombreLugar;
const ms = api.metricasSociales;
const checks = [
  ['lug_biblioteca from catalog', nl('lug_biblioteca') === 'Biblioteca'],
  ['lug_cafeteria from operativos', nl('lug_cafeteria') === 'Cafetería'],
  ['never raw id', nl('lug_biblioteca').indexOf('lug_') === -1],
  ['fallback map', nl('lug_parque') === 'Parque'],
  ['prox plan format', nl('lug_biblioteca') + ' · 16:00' === 'Biblioteca · 16:00'],
  ['vecinos count real', ms({ residentes: { a: { presencia: 'residente', runtime: { estado_emocional: { id: 'alegre' } } }, b: { presencia: 'residente', runtime: { estado_emocional: { id: 'neutro' } } } }, relaciones_romanticas: [] }).vecinos === 2],
  ['alegre from runtime', ms({ residentes: { a: { presencia: 'residente', runtime: { estado_emocional: { id: 'alegre' } } } }, relaciones_romanticas: [] }).emo.alegre === 1],
  ['parejas canonical', ms({ residentes: {}, relaciones_romanticas: [{ estado_pareja: 'pareja' }] }).parejas === 1],
  ['crisis canonical', ms({ residentes: {}, relaciones_romanticas: [{ estado_pareja: 'crisis' }] }).crisis === 1],
];

let fail = 0;
checks.forEach(function (c) {
  console.log((c[1] ? 'OK' : 'FAIL') + ': ' + c[0]);
  if (!c[1]) fail = 1;
});
process.exit(fail);
