'use strict';
const fs = require('fs');
const path = require('path');
const js = fs.readFileSync(path.join(__dirname, '..', 'assets/js/play-v3.js'), 'utf8');
let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}
ok(/async function abrirFicha\(id, opts\)/.test(js), 'abrirFicha acepta opts');
ok(/toast\(r\.mensaje_ui/.test(js), 'toast si API falla');
ok(/await refresh\(\)/.test(js.match(/async function abrirFicha[\s\S]{0,900}/)?.[0] || ''), 'refresh antes de reintentar');
ok(/catch \(err\)[\s\S]{0,120}pintarFicha/.test(js.match(/async function abrirFicha[\s\S]{0,1200}/)?.[0] || ''), 'try/catch pintarFicha');
if (failures) process.exit(1);
console.log('abrir_ficha_feedback_test OK');
