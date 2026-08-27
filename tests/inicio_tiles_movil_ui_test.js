'use strict';
/* Tiles móvil Inicio: Mensajitos / Vecinos / Plan — contrato INICIO-TILES-MOVIL-v7 */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const inicio = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio.css'), 'utf8');
const override = fs.readFileSync(path.join(root, 'assets/css/play-v3-inicio-override.css'), 'utf8');
const leg = fs.readFileSync(path.join(root, 'assets/css/design-system/legibilidad-global.css'), 'utf8');
const resp = fs.readFileSync(path.join(root, 'assets/css/play-v3-responsive.css'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

const v7 = (() => {
  const i = override.indexOf('INICIO-TILES-MOVIL-v7');
  return i < 0 ? '' : override.slice(i);
})();
ok(v7.includes('@media (max-width: 768px)'), 'override: bloque v7 acotado a móvil');
ok(!v7.includes('min-width: 769px'), 'override: v7 no toca desktop');

ok(/width:\s*56px/.test(inicio), 'inicio.css: sobre Mensajitos 56px');
ok(/content:\s*"PLAN"/.test(inicio), 'inicio.css: etiqueta PLAN (sin + en texto)');
ok(/font-size:\s*1\.1875rem/.test(inicio), 'inicio.css: títulos tiles 1.1875rem');
ok(/\.game-left \.obj-vecinos-head/.test(inicio), 'inicio.css: cabecera vecinos en columna');
ok(/margin-left:\s*-7px/.test(inicio), 'inicio.css: avatares vecinos agrupados');

ok(/obj-buzon-img[\s\S]*width:\s*56px\s*!important/.test(v7),
  'override v7: sobre 56px gana a batch 17');
ok(/game-left-tile-label[\s\S]*font-size:\s*1\.1875rem\s*!important/.test(v7),
  'override v7: labels alineados y más grandes');
ok(/obj-nuevo-plan-ico[\s\S]*font-size:\s*2\.85rem\s*!important/.test(v7),
  'override v7: + grande en Plan');
ok(/obj-vecinos-poblacion[\s\S]*display:\s*block\s*!important/.test(v7),
  'override v7: contador vecinos visible');
ok(/border-radius:\s*20px 15px 23px 14px/.test(v7),
  'override v7: borde artesanal Mensajitos (no cuadrado 10px)');
ok(/transform:\s*translateY\(3px\) rotate\(-0\.7deg\)/.test(v7),
  'override v7: inclinación artesanal shell-grupo-buzon');

ok(/game-left \.obj-buzon-txt[\s\S]*max\(1\.1875rem,\s*var\(--aht-type-block\)\)/.test(leg),
  'legibilidad-global: tiles izquierda con mismo token tipográfico');
ok(!/obj-vecinos-tit \{\s*\n\s*font-size: var\(--aht-type-block\)/.test(leg) ||
   /game-left \.obj-vecinos-tit/.test(leg),
  'legibilidad-global: vecinos tit en scope game-left');

ok(/Fix batch 17/.test(resp), 'responsive: batch 17 sigue existiendo (v7 lo neutraliza en móvil)');

console.log(failures ? '\n' + failures + ' FAIL' : '\nTODO OK');
process.exit(failures ? 1 : 0);
