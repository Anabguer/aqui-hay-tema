'use strict';
/* Contrato estático: inicio desktop V4 (pantallazos aprobados). */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const inicioDesktop = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-desktop.css'), 'utf8');
const inicioCroma = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-cromatica-desktop.css'), 'utf8');
const dsShell = fs.readFileSync(path.join(root, 'assets/css/play-v3-desktop-shell.css'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

ok(/inicio\/inicio-desktop\.css/.test(php), 'play.php: enlaza inicio-desktop.css canónico');
ok(/inicio\/inicio-cromatica-desktop\.css/.test(php), 'play.php: enlaza inicio-cromatica-desktop.css');
ok(!/design-system\/screens\/inicio-desktop\.css/.test(php), 'play.php: sin hoja legacy inicio-desktop');

ok(!/function renderProximosPlanesDesktop/.test(js), 'js: sin renderProximosPlanesDesktop legacy');
ok(/function renderProximosPlanesMovil/.test(js) && /function renderEncursosMovil/.test(js),
  'js: render compartido enc-mov / pp-mov presente');

ok(!/encursos-movil[\s\S]{0,80}display:\s*none/.test(dsShell), 'desktop-shell: no oculta encursos-movil');
ok(/@media \(min-width: 769px\)/.test(inicioDesktop) || /@media \(min-width: 769px\)/.test(inicioCroma),
  'inicio desktop: bloque >=769px');
ok(/\.obj-cotilleo/.test(inicioCroma) || /\.obj-cotilleo/.test(inicioDesktop), 'inicio desktop: bloque cotilleos');
ok(/data-inicio-view="desktop"/.test(php), 'play.php: vista desktop separada');

if (failures) {
  console.error('\n' + failures + ' fallo(s)');
  process.exit(1);
}
console.log('\ninicio_desktop_ui_test OK');
