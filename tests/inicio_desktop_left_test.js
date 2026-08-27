'use strict';
/* INICIO-DESKTOP-LEFT-v10 — Mensajitos/Vecinos bloques apilados, no tiles móvil */
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const desk = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio-desktop.css'), 'utf8');
const inicio = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio.css'), 'utf8');
const ov = fs.readFileSync(path.join(root, 'assets/css/play-v3-inicio-override.css'), 'utf8');

let fail = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'NO OK') + ': ' + m);
  if (!c) fail++;
}

ok(/INICIO-DESKTOP-LEFT-v10/.test(desk), 'bloque v10 presente');
ok(/INICIO-DESKTOP-LEFT-v10[\s\S]*\.game-left[\s\S]*flex-direction: column !important/.test(desk),
  'game-left columna única desktop');
ok(/INICIO-DESKTOP-LEFT-v10[\s\S]*shell-grupo-buzon[\s\S]*width: 100% !important/.test(desk),
  'Mensajitos ancho columna');
ok(/INICIO-DESKTOP-LEFT-v10[\s\S]*shell-grupo-resumen[\s\S]*width: 100% !important/.test(desk),
  'Vecinos ancho columna');
ok(/INICIO-DESKTOP-LEFT-v10[\s\S]*gap: 14px !important/.test(desk),
  'espaciado vertical moderado');
ok(/INICIO-DESKTOP-LEFT-v10[\s\S]*obj-buzon[\s\S]*flex-direction: row !important/.test(desk),
  'Mensajitos layout horizontal desktop');
ok(/INICIO-DESKTOP-LEFT-v10[\s\S]*celestine-nota[\s\S]*text-align: left !important/.test(desk),
  'Vecinos piel desktop (no tile centrado)');

// móvil intacto
ok(/@media \(max-width: 768px\)/.test(inicio) &&
  /grid-template-columns: repeat\(3, minmax\(0, 1fr\)\)/.test(inicio) &&
  !/INICIO-DESKTOP-LEFT-v10/.test(inicio),
  'móvil: grid 3 tiles sin v10');
ok(/INICIO-TILES-MOVIL-v7/.test(ov) && /@media \(max-width: 768px\)/.test(ov),
  'override tiles solo móvil');

console.log(fail ? '\n' + fail + ' FAIL' : '\nTODO OK');
process.exit(fail ? 1 : 0);
