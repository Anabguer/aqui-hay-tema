'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

const checks = [
  ['03 mensajitos', 'assets/css/play-v3-mensajitos.css', 'CIERRE-VISUAL-03-MENSAJITOS-20260906'],
  ['04 vecinos body', 'assets/css/design-system/vecinos-body.css', 'CIERRE-VISUAL-04-VECINOS-20260906'],
  ['05 relaciones', 'assets/css/play-v3-vecinos.css', 'CIERRE-VISUAL-05-RELACIONES-20260906'],
  ['06 ficha', 'assets/css/v4/bodies/ficha-relaciones.css', 'CIERRE-VISUAL-06-FICHA-20260906'],
  ['07-08 ficha sub', 'assets/css/v4/screens.css', 'CIERRE-VISUAL-07-08-FICHA-SUB-20260906'],
  ['09 cotilleos', 'assets/css/play-v3-cotilleos.css', 'CIERRE-VISUAL-09-COTILLEOS-20260906'],
  ['11-12 organizar', 'assets/css/play-v3-organizar.css', 'CIERRE-VISUAL-11-12-ORGANIZAR-20260906'],
  ['13-15 encuentro', 'assets/css/play-v3-enc-int.css', 'CIERRE-VISUAL-13-15-ENCUENTRO-20260906'],
  ['16-17 misc', 'assets/css/v4/bodies/misc-screens.css', 'CIERRE-VISUAL-16-17-MISC-20260906'],
  ['18 ajustes', 'assets/css/v4/bodies/ajustes.css', 'CIERRE-VISUAL-18-AJUSTES-20260906'],
  ['inicio pass4 desktop', 'assets/css/inicio/inicio-desktop.css', 'INICIO-VISUAL-PASS4-20260906'],
  ['inicio pass4 mobile', 'assets/css/inicio/inicio-mobile.css', 'INICIO-VISUAL-PASS4-20260906'],
];

let failed = 0;
for (const [label, file, marker] of checks) {
  const css = read(file);
  if (!css.includes(marker)) {
    console.error('FAIL:', label, 'sin marcador', marker);
    failed++;
  } else {
    console.log('OK:', label);
  }
}

if (failed) {
  console.error('cierre_visual_test FAIL', failed);
  process.exit(1);
}
console.log('cierre_visual_test OK');
