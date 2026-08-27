'use strict';
/**
 * Navegación circular en ficha vecino: primero ← último, último → primero.
 */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.join(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');

function extraer(src, needle) {
  const i = src.indexOf(needle);
  if (i < 0) return '';
  let prof = 0;
  const start = src.indexOf('{', i);
  for (let j = start; j < src.length; j++) {
    if (src[j] === '{') prof++;
    else if (src[j] === '}') {
      prof--;
      if (prof === 0) return src.slice(i, j + 1);
    }
  }
  return '';
}

const fnCircular = extraer(js, 'function vecinoFichaCircular(');
const fnIndice = extraer(js, 'function fichaIndiceEnLista(');

if (!fnCircular || !fnIndice) {
  console.error('FAIL: helpers ficha nav no encontrados');
  process.exit(1);
}

const sandbox = {};
vm.createContext(sandbox);
vm.runInContext(
  'var fichaActualId = "";\n' + fnIndice + '\n' + fnCircular + '\n' +
  'function test(ids, cur, delta) {\n' +
  '  fichaActualId = cur;\n' +
  '  return vecinoFichaCircular(ids, delta);\n' +
  '}',
  sandbox
);

const ids = ['a', 'b', 'c', 'd'];
let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

ok(sandbox.test(ids, 'a', -1) === 'd', 'primero + izquierda → último');
ok(sandbox.test(ids, 'd', 1) === 'a', 'último + derecha → primero');
ok(sandbox.test(ids, 'b', 1) === 'c', 'medio + derecha → siguiente');
ok(sandbox.test(ids, 'b', -1) === 'a', 'medio + izquierda → anterior');

console.log(failures === 0 ? 'ficha_nav_circular_test OK' : 'FALLOS: ' + failures);
process.exit(failures === 0 ? 0 : 1);
