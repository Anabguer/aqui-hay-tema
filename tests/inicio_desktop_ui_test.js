'use strict';
/* Contrato estático: inicio desktop DS (Cotilleo, Parejas, Planes). */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const dsDesktop = fs.readFileSync(
  path.join(root, 'assets/css/design-system/screens/inicio-desktop.css'),
  'utf8'
);
const dsShell = fs.readFileSync(path.join(root, 'assets/css/play-v3-desktop-shell.css'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

ok(/design-system\/screens\/inicio-desktop\.css/.test(php),
  'play.php: enlaza inicio-desktop.css tras override');
ok(php.indexOf('inicio-desktop.css') > php.indexOf('inicio-override.css'),
  'play.php: inicio-desktop.css carga después de inicio-override');

ok(!/function renderProximosPlanesDesktop/.test(js),
  'js: sin renderProximosPlanesDesktop legacy');
ok(!/function htmlProximoPlanCardDesktop/.test(js),
  'js: sin htmlProximoPlanCardDesktop legacy');
ok(/function renderProximosPlanesMovil/.test(js) && /function renderEncursosMovil/.test(js),
  'js: render compartido enc-mov / pp-mov presente');

ok(!/encursos-movil[\s\S]{0,80}display:\s*none/.test(dsShell),
  'desktop-shell: no oculta encursos-movil');
ok(!/proxplanes-movil[\s\S]{0,80}display:\s*none/.test(dsShell),
  'desktop-shell: no oculta proxplanes-movil');

ok(/@media \(min-width: 769px\)/.test(dsDesktop),
  'inicio-desktop.css: bloque >=769px');
ok(/\.obj-cotilleo\.obj-cotilleo-par[\s\S]*display:\s*flex/.test(dsDesktop),
  'inicio-desktop.css: cotilleo flex DS');
ok(/\.enc-mov-card-tit/.test(dsDesktop) && /\.pp-mov-card[\s\S]{0,400}border:\s*2px dashed/.test(dsDesktop),
  'inicio-desktop.css: planes DS v17 (tarjeta papel + borde punteado)');
ok(/\.shell-grupo-parejas/.test(dsDesktop),
  'inicio-desktop.css: parejas DS');
ok(/obj-planes-lateral > \.obj-proximo[\s\S]*display:\s*none/.test(dsDesktop),
  'inicio-desktop.css: oculta polaroid legacy izquierda');

console.log(failures ? '\n' + failures + ' FAIL' : '\nTODO OK');
process.exit(failures ? 1 : 0);
