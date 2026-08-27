'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
let fail = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) fail++;
}

ok(js.includes('ctaTxtEncuentroMov'), 'helper CTA centralizado');
ok(js.includes("enc.intencion === 'celeste_organizado'"), 'CTA distingue encuentros organizados');
ok(js.includes("return '\\u00bfQu\\u00e9 se cuece ah\\u00ed?'"), 'copy MENTES presente');
ok(js.includes("return 'Ver qu\\u00e9 pasa'"), 'copy encuentros no organizados');
ok(!js.includes("puedeIntervenir ? '\u00bfQu\u00e9 se cuece"), 'sin ternario CTA duplicado legacy');

process.exit(fail === 0 ? 0 : 1);
