'use strict';
/* INICIO-ENCURSO-NAV-v16 — flechas carrusel + iconos canónicos */
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const ov = fs.readFileSync(path.join(root, 'assets/css/play-v3-inicio-override.css'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const desk = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio-desktop.css'), 'utf8');

let fail = 0;
function ok(c, m) { console.log((c ? 'OK' : 'FAIL') + ': ' + m); if (!c) fail++; }

const v16 = (() => {
  const i = ov.indexOf('INICIO-ENCURSO-NAV-v16');
  const j = ov.indexOf('INICIO-ENCURSO-FIX-v15', i + 1);
  return i < 0 ? '' : ov.slice(i, j > i ? j : undefined);
})();

ok(v16.includes('@media (max-width: 768px)'), 'v16 solo móvil');
ok(/enc-mov-shell/.test(v16), 'shell carrusel');
ok(/enc-mov-nav-btn/.test(v16), 'botones flecha');
ok(/enc-mov-indice[\s\S]{0,80}display:\s*none/.test(v16), 'sin indice textual');
ok(/org-tipo-ico/.test(v16), 'estilo icono canonico');
ok(!/INICIO-ENCURSO-NAV-v16/.test(desk), 'desktop sin v16');

ok(js.includes('function orgTipoIdDesdeEnc'), 'js orgTipoIdDesdeEnc');
ok(js.includes('orgTipoIco(orgId)'), 'js reutiliza orgTipoIco');
ok(js.includes('iconoMision({ familia:'), 'js fallback iconoMision');
ok(!js.includes('function familiaTipoEncuentro'), 'js sin iconos inventados');
ok(js.includes('function encMovIrA'), 'js navegacion flechas');
ok(js.includes('function renderEncursosMovilNav'), 'js render nav');
ok(!js.includes("textContent = (idx + 1) + ' / ' + n"), 'js sin 1/2');
ok(js.includes('data-enc-mov-prev') && js.includes('data-enc-mov-next'), 'js handlers flechas');

ok(php.includes('data-encursos-shell'), 'play shell nav');
ok(php.includes('data-enc-mov-prev'), 'play flecha prev');
ok(!php.includes('data-encursos-indice'), 'play sin indice');

console.log(fail ? '\n' + fail + ' FAIL' : '\nTODO OK');
process.exit(fail ? 1 : 0);
