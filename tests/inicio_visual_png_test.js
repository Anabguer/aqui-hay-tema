'use strict';
/* Contrato estático: bloques INICIO-VISUAL-PNG en autoridad canónica. */
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const mob = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-mobile.css'), 'utf8');
const desk = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-desktop.css'), 'utf8');
const mapa = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-mapa.css'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

ok(/INICIO-VISUAL-PNG-20260906 — movil/.test(mob), 'mobile: marcador visual PNG');
ok(/planes-unif-body/.test(mob), 'mobile: estilos planes-unif');
ok(/inicio-mp-duo/.test(mob), 'mobile: estilos misiones/parejas duo');
ok(/play-bottom-nav-btn/.test(mob), 'mobile: estilos nav inferior');
ok(/obj-cotilleo-compact/.test(mob), 'mobile: cotilleos compacto');

ok(/INICIO-VISUAL-PNG-20260906 — desktop/.test(desk), 'desktop: marcador visual PNG');
ok(/grid-template-areas:[\s\S]{0,120}"\.    nav  \."/.test(desk), 'desktop: nav bajo mapa');

ok(/INICIO-VISUAL-PNG-20260906 — mapa/.test(mapa), 'mapa: marcador visual PNG');
ok(/aspect-ratio:\s*618\s*\/\s*404/.test(mapa), 'mapa movil: proporcion canonica');

ok(!/!important/.test(mob + desk + mapa), 'visual PNG: cero !important');

if (failures) {
  console.error('\n' + failures + ' fallo(s)');
  process.exit(1);
}
console.log('\ninicio_visual_png_test OK');
