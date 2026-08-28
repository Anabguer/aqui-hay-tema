'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.join(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
let fail = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) fail++;
}

ok(js.includes('ctaEncuentroMovVisible'), 'helper visibilidad CTA');
ok(js.includes('ctaTxtEncuentroMov'), 'helper copy CTA');
ok(!js.includes("return 'Ver qu\\u00e9 pasa'"), 'sin fallback Ver qué pasa');
ok(!js.includes("return 'Ver encuentro'"), 'sin fallback Ver encuentro');
ok(js.includes("if (ctaVisible) {"), 'botón condicionado a ctaVisible');

const fnBlock = js.match(
  /function ctaEncuentroMovVisible\(enc, iv\) \{[\s\S]*?function ctaTxtEncuentroMov\(enc, iv\) \{[\s\S]*?\n  \}/
);
ok(!!fnBlock, 'extrae helpers CTA');
const sandbox = {};
vm.runInNewContext(fnBlock[0], sandbox);
const vis = sandbox.ctaEncuentroMovVisible;
const txt = sandbox.ctaTxtEncuentroMov;

const ivMentes = { disponible: true, acciones: [{ id: 'hobby' }] };
const ivNo = { disponible: false, acciones: [] };

// CASO 1: jugador + pareja + MENTES
const enc1 = { estado: 'en_curso', intencion: 'celeste_organizado', participantes: ['a', 'b'] };
ok(vis(enc1, ivMentes), 'CASO 1: CTA visible');
ok(txt(enc1, ivMentes).includes('cuece'), 'CASO 1: copy MENTES');

// CASO 2: autónomo NPC
const enc2 = { estado: 'en_curso', intencion: 'autonomo', participantes: ['a', 'b'] };
ok(!vis(enc2, ivMentes), 'CASO 2: autónomo sin CTA');

// CASO 3: evento pueblo
const enc3 = { estado: 'en_curso', intencion: 'evento_pueblo', participantes: ['a', 'b', 'c'] };
ok(!vis(enc3, ivMentes), 'CASO 3: evento pueblo sin CTA');

// CASO 4: salida individual
const enc4 = { estado: 'en_curso', intencion: 'celeste_organizado', tipo: 'individual', participantes: ['a'] };
ok(!vis(enc4, ivMentes), 'CASO 4: individual sin CTA');

// CASO E: organizado pero MENTES no disponible
const enc5 = { estado: 'en_curso', intencion: 'celeste_organizado', participantes: ['a', 'b'] };
ok(!vis(enc5, ivNo), 'organizado sin MENTES: sin CTA');
ok(txt(enc5, ivNo) === '', 'organizado sin MENTES: copy vacío');

process.exit(fail === 0 ? 0 : 1);
