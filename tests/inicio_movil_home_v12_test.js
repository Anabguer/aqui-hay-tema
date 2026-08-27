'use strict';
/* INICIO-MOVIL-HOME-v12 — tiles iguales, mapa simétrico, Próximos planes columna (solo móvil) */
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const ov = fs.readFileSync(path.join(root, 'assets/css/play-v3-inicio-override.css'), 'utf8');
const desk = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio-desktop.css'), 'utf8');

let fail = 0;
function ok(c, m) { console.log((c ? 'OK' : 'FAIL') + ': ' + m); if (!c) fail++; }

const v12 = (() => {
  const i = ov.indexOf('INICIO-MOVIL-HOME-v12');
  return i < 0 ? '' : ov.slice(i);
})();

ok(v12.includes('@media (max-width: 768px)'), 'bloque v12 solo móvil');
ok(!v12.includes('min-width: 769px'), 'v12 no toca desktop');

ok(/min-height:\s*108px\s*!important/.test(v12) && v12.includes('.obj-buzon'), 'tiles: min-height 108px unificado');
ok(/flex-direction:\s*column\s*!important/.test(v12) && v12.includes('.proxplanes-movil'), 'proxplanes: columna');
ok(/:not\(:has\(\.encursos-movil\.is-on\)\)[\s\S]{0,200}grid-row:\s*3/.test(v12), 'mapa: cotilleo sube a fila 3 sin encursos');
ok(/\.game-map-wrap[\s\S]{0,120}margin-top:\s*4px\s*!important/.test(v12), 'mapa: compensación margen superior simétrico');
ok(/\.proxplanes-movil \.pp-mov-track[\s\S]{0,120}width:\s*100%/.test(v12), 'proxplanes: track ancho completo debajo');

ok(!/INICIO-MOVIL-HOME-v12/.test(desk), 'desktop intacto (sin v12)');

console.log(fail ? '\n' + fail + ' FAIL' : '\nTODO OK');
process.exit(fail ? 1 : 0);
