'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const file = path.join(root, 'assets/css/inicio/inicio-desktop.css');
const css = fs.readFileSync(file, 'utf8');

const S = '.play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top';
const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const targets = [
  'top-reloj',
  'obj-dia',
  'obj-dia-placa',
  'obj-dia-cuerpo',
  'obj-dia-num',
  'obj-dia-meta',
  'obj-hora',
  'pasar-rato',
];

let failures = 0;
for (const sel of targets) {
  const re = new RegExp(esc(S) + ' \\.' + sel + '(?![-a-z])\\s*\\{', 'g');
  const count = (css.match(re) || []).length;
  if (count > 1) {
    console.error('FAIL: duplicado', sel, 'x', count);
    failures++;
  } else if (count === 0) {
    console.error('FAIL: ausente', sel);
    failures++;
  } else {
    console.log('OK: una regla base', sel);
  }
}

const corrective = [
  'INICIO-DESKTOP-TOP-RELOJ-20260906',
  'INICIO-DESKTOP-TOP-RELOJ-FIX-20260906',
];
for (const m of corrective) {
  if (css.includes(m)) {
    console.error('FAIL: bloque corrector', m);
    failures++;
  } else {
    console.log('OK: sin bloque', m);
  }
}

if (!/INICIO-DESKTOP-CABECERA-RELOJ-CANON-20260906/.test(css)) {
  console.error('FAIL: marcador canon reloj ausente');
  failures++;
} else {
  console.log('OK: autoridad canon reloj en CABECERA');
}

if (failures) {
  console.error('inicio_desktop_reloj_authority_test FAIL', failures);
  process.exit(1);
}
console.log('inicio_desktop_reloj_authority_test OK');
