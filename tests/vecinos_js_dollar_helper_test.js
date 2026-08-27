'use strict';
/**
 * Detecta uso incorrecto de $() (querySelector) como colección en código Vecinos.
 */
const fs = require('fs');
const path = require('path');

const js = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'play-v3.js'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

function hasBadDollarForEach(src, attr) {
  const bad = `$('[${attr}]').forEach`;
  let i = 0;
  while ((i = src.indexOf(bad, i)) !== -1) {
    if (i === 0 || src[i - 1] !== '$') return true;
    i += bad.length;
  }
  return false;
}

const vecStart = js.indexOf('let vecTabActiva');
const vecEnd = js.indexOf('function canonEmoId(id)');
const block = vecStart >= 0 && vecEnd > vecStart ? js.slice(vecStart, vecEnd) : '';

ok(vecStart >= 0, 'bloque vecTabActiva presente');
ok(!hasBadDollarForEach(block, 'data-vec-tab'), 'sin $([data-vec-tab]).forEach');
ok(!hasBadDollarForEach(block, 'data-vec-panel'), 'sin $([data-vec-panel]).forEach');
ok(block.includes("$$('[data-vec-tab]').forEach"), 'usa $$ para tabs');
ok(block.includes("$$('[data-vec-panel]').forEach"), 'usa $$ para panels');

const bindStart = js.indexOf("$$('[data-vec-tab]').forEach");
const handlers = bindStart >= 0 ? js.slice(bindStart, js.indexOf('const resBuscaInp', bindStart)) : '';
ok(handlers.includes("$$('[data-vec-tab]').forEach"), 'handlers bind: $$ en data-vec-tab');
ok(!hasBadDollarForEach(handlers, 'data-vec-tab'), 'handlers bind: sin $ en data-vec-tab');

if (failures) {
  console.error('\n' + failures + ' fallo(s)');
  process.exit(1);
}
console.log('\nvecinos_js_dollar_helper_test OK');
