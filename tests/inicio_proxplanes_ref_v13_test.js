'use strict';
/* INICIO-PROXPLANES-REF-v13 — composición ref captura móvil */
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const ov = fs.readFileSync(path.join(root, 'assets/css/play-v3-inicio-override.css'), 'utf8');
const desk = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio-desktop.css'), 'utf8');

let fail = 0;
function ok(c, m) { console.log((c ? 'OK' : 'FAIL') + ': ' + m); if (!c) fail++; }

const v13 = (() => {
  const i = ov.indexOf('INICIO-PROXPLANES-REF-v13');
  const j = ov.indexOf('INICIO-ENCURSO-REF-v14');
  return i < 0 ? '' : ov.slice(i, j > i ? j : undefined);
})();

ok(v13.includes('@media (max-width: 768px)'), 'v13 solo móvil');
ok(!v13.includes('min-width: 769px'), 'v13 no desktop');
ok(/\.proxplanes-movil \.pp-mov-tit[\s\S]{0,200}ds-font-hand/.test(v13), 'cabecera tipografía manuscrita');
ok(/\.proxplanes-movil \.pp-mov-body[\s\S]{0,120}flex-direction:\s*column/.test(v13), 'tarjeta cuerpo vertical');
ok(/\.proxplanes-movil \.pp-mov-star[\s\S]{0,80}display:\s*none/.test(v13), 'sin estrella lateral');
ok(/border:\s*2px dashed var\(--ds-lavender-deep/.test(v13), 'tarjeta borde punteado lila');
ok(/\.proxplanes-movil \.pp-mov-nombres[\s\S]{0,120}text-align:\s*center/.test(v13), 'nombres centrados');
ok(/\.proxplanes-movil \.pp-mov-track[\s\S]{0,120}overflow-x:\s*auto/.test(v13), 'carrusel horizontal');
ok(!/INICIO-PROXPLANES-REF-v13/.test(desk), 'desktop sin v13');

console.log(fail ? '\n' + fail + ' FAIL' : '\nTODO OK');
process.exit(fail ? 1 : 0);
