'use strict';
/** Smoke estático: Mensajitos v19 + Nuevo Plan auto en play-v3.js integrado */
const fs = require('fs');
const path = require('path');
const js = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'play-v3.js'), 'utf8');
let f = 0;
function ok(c, m) { console.log((c ? 'OK' : 'FAIL') + ': ' + m); if (!c) f++; }
ok(js.includes('function renderBuzon(msgs)'), 'mensajitos: renderBuzon');
ok(js.includes('function renderMensajitosPop'), 'mensajitos: renderMensajitosPop');
ok(js.includes('const ORG_MAX_VECINOS = 2'), 'organizar: ORG_MAX_VECINOS');
ok(js.includes('let orgProponiendo = false'), 'organizar: anti doble submit');
ok(js.includes('ORG_BTN_BUSY'), 'organizar: feedback processing');
ok(js.includes('function orgModo()'), 'organizar: orgModo');
ok(js.includes('function actualizarOrgModoEstado'), 'organizar: modo auto estado');
ok(!js.includes('data-org-modo-solo') || js.includes('function orgModo()'), 'organizar: sin chips solo legacy o con modo auto');
if (f) process.exit(1);
console.log('prod_integrated_js_smoke_test OK');
