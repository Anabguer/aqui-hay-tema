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
    if (src[i] === '{') depth++;
    else if (src[i] === '}') {
      depth--;
      if (depth === 0) return src.slice(start, i + 1);
    }
  }
  throw new Error('unclosed ' + name);
}

const src = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'play-v3.js'), 'utf8');
const fns = [
  'relojAbs', 'horaEnc', 'esEncuentroFuturo', 'demoEncuentrosFuturos', 'encuentrosFuturos',
  'diaCortoPlan', 'formatPlanMeta', 'nombreLugar', 'htmlProximoPlan'
].map(function (n) { return extractFn(src, n); }).join('\n');

const estado = {
  reloj: { dia_pueblo: 3, hora_actual: 14 },
  reloj_vista: {
    proximos_dias: [
      { dia_pueblo: 3, dia_semana_ui: 'Miércoles', fecha_corta: '21/08', es_hoy: true },
      { dia_pueblo: 4, dia_semana_ui: 'Jueves', fecha_corta: '22/08' },
    ],
  },
};
const partida = {
  encuentros: [
    { id: 'e1', estado: 'programado', dia: 3, hora: 16, lugar: 'lug_biblioteca', participantes: ['a', 'b'] },
    { id: 'e2', estado: 'programado', dia: 3, hora: 18, lugar: 'lug_cafeteria', participantes: ['a', 'c'] },
    { id: 'e3', estado: 'programado', dia: 4, hora: 11, lugar: 'lug_parque', participantes: ['b', 'c'] },
    { id: 'past', estado: 'programado', dia: 3, hora: 10, lugar: 'lug_bar', participantes: ['a', 'b'] },
    { id: 'done', estado: 'terminado', dia: 5, hora: 12, lugar: 'lug_cine', participantes: ['a', 'b'] },
  ],
};

const api = new Function(
  'cachePueblo', 'cacheEstado', 'cacheInsp', 'DESTINO_NOMBRE', 'AGENDA_DEMO', 'qs',
  fns + '\nreturn { encuentrosFuturos, formatPlanMeta, htmlProximoPlan, nombreLugar };'
)(
  { complejos: [{ destinos: [{ id: 'lug_biblioteca', nombre: 'Biblioteca' }] }] },
  estado,
  partida,
  { lug_biblioteca: 'Biblioteca', lug_cafeteria: 'Cafetería', lug_parque: 'Parque', lug_bar: 'Bar' },
  false,
  { get: function () { return null; } }
);

const fut = api.encuentrosFuturos(partida, estado);
const checks = [
  ['3 futuros (excluye pasado y terminado)', fut.length === 3],
  ['orden cronológico', fut[0].id === 'e1' && fut[1].id === 'e2' && fut[2].id === 'e3'],
  ['pendientes = total-1', fut.length - 1 === 2],
  ['sin lug_ en meta', api.formatPlanMeta(fut[0], estado).indexOf('lug_') === -1],
  ['Biblioteca público', api.formatPlanMeta(fut[0], estado).indexOf('Biblioteca') >= 0],
  ['proximo html sin lug_', api.htmlProximoPlan(fut[0], estado).indexOf('lug_') === -1],
  ['formato hora', api.formatPlanMeta(fut[0], estado).indexOf('16:00') >= 0],
];

let fail = 0;
checks.forEach(function (c) {
  console.log((c[1] ? 'OK' : 'FAIL') + ': ' + c[0]);
  if (!c[1]) fail = 1;
});
if (!fail) {
  console.log('\nLab demo: play.php?lab=1&config=playtest_01&agenda_demo=1');
}
process.exit(fail);
