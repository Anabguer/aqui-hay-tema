'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

const shellUi = fs.readFileSync(path.join(root, 'assets/css/play-v3-shell-ui.css'), 'utf8');
const deskShell = fs.readFileSync(path.join(root, 'assets/css/play-v3-desktop-shell.css'), 'utf8');
const frame = fs.readFileSync(path.join(root, 'assets/css/v4/screen-frame.css'), 'utf8');
const desk = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-desktop.css'), 'utf8');
const mob = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-mobile.css'), 'utf8');
const misc = fs.readFileSync(path.join(root, 'assets/css/v4/bodies/misc-screens.css'), 'utf8');
const screens = fs.readFileSync(path.join(root, 'assets/css/v4/screens.css'), 'utf8');

ok(/INICIO-SHELL-UI-MIGRATED-20260906/.test(shellUi), 'shell-ui: marcador migracion Inicio');
ok(!/\.inicio-desktop \.obj-buzon\s*\{/.test(shellUi), 'shell-ui: sin .inicio-desktop .obj-buzon');
ok(!/\.inicio-desktop \.obj-cotilleo\s*\{/.test(shellUi), 'shell-ui: sin .inicio-desktop .obj-cotilleo');
ok(/INICIO-DESKTOP-SHELL-QUARANTINE-20260906/.test(deskShell), 'desktop-shell: marcador cuarentena');
ok(/not\(:has\(\.inicio-desktop\.is-inicio-view-active\)\) \.celestine-nota/.test(deskShell), 'desktop-shell: celestine acotado');
ok(/AHT-VELO-CANON-20260906/.test(frame), 'screen-frame: velo canonico');
ok(/\.play-v3 \.play-root > \.velo[\s\S]*display:\s*none/.test(frame), 'screen-frame: velo legacy oculto');
ok(/INICIO-VISUAL-PASS3-20260906/.test(desk), 'desktop: pass3 derecha');
ok(/INICIO-VISUAL-PASS3-20260906/.test(mob), 'mobile: pass3 tiles');
ok(/MISIONES-VISUAL-PNG-20260906/.test(misc), 'misc: misiones pill');
ok(/MISC-SHELL-MIGRATED-20260906/.test(misc), 'misc: shell migrado');
ok(!/\[data-capa="agenda"\][\s\S]{0,200}\.aht-screen\[data-aht-screen="agenda"\][\s\S]{0,120}border:/.test(misc), 'misc: sin borde shell agenda');
ok(/INVENTARIO-VISUAL-PNG-20260906/.test(screens), 'screens: inventario body');
ok(!/!important/.test(shellUi + deskShell + frame + desk + mob + misc + screens), 'pass2: cero !important');

if (failures) {
  console.error('\n' + failures + ' fallo(s)');
  process.exit(1);
}
console.log('\npass2_integral_test OK');
