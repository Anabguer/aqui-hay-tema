'use strict';
/* INICIO-ENCURSO-FIX-v15 — tipo real, avatares, orden feed, desktop ojo */
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const ov = fs.readFileSync(path.join(root, 'assets/css/play-v3-inicio-override.css'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const desk = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio-desktop.css'), 'utf8');

let fail = 0;
function ok(c, m) { console.log((c ? 'OK' : 'FAIL') + ': ' + m); if (!c) fail++; }

const v15 = (() => {
  const i = ov.indexOf('INICIO-ENCURSO-FIX-v15');
  return i < 0 ? '' : ov.slice(i);
})();

ok(v15.includes('grid-row: 3'), 'v15 cotilleo grid-row 3');
ok(/encursos-movil\.is-on[\s\S]{0,80}grid-row:\s*4/.test(v15), 'v15 encurso grid-row 4');
ok(/proxplanes-movil\.is-on[\s\S]{0,80}grid-row:\s*5/.test(v15), 'v15 proxplanes grid-row 5');
ok(/enc-mov-faces[\s\S]{0,80}gap:\s*12px/.test(v15), 'v15 avatares separados');
ok(/enc-mov-tipo-ico/.test(v15), 'v15 icono tipo');
ok(/min-width:\s*769px[\s\S]{0,400}enc-mov-cta-ico/.test(v15), 'v15 desktop oculta ojo');

ok(js.includes('function orgTipoIdDesdeEnc'), 'js familia tipo');
ok(js.includes('function iconoEncuentroCentroHtml'), 'js icono centro');
ok(!js.includes('enc-mov-heart" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M12 21'), 'js sin corazon fijo');
ok(js.includes('htmlEncursoCardDesktop'), 'js desktop card');
ok(js.includes('htmlEncursoCardMovilV14'), 'js movil v14 card');
ok(js.includes('esInicioLayoutMovil()'), 'js split movil/desktop');
ok(js.includes("enc.tipo") || js.includes('enc.tipo'), 'js usa enc.tipo');

ok(!/INICIO-ENCURSO-FIX-v15/.test(desk), 'desktop.css sin v15 movil');

console.log(fail ? '\n' + fail + ' FAIL' : '\nTODO OK');
process.exit(fail ? 1 : 0);
