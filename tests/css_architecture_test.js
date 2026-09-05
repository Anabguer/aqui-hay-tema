'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
let failures = 0;
function ok(cond, msg) { console.log((cond ? 'OK' : 'FAIL') + ': ' + msg); if (!cond) failures++; }
function countImp(dir) {
  let n = 0;
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) n += countImp(p);
    else if (e.name.endsWith('.css')) n += (fs.readFileSync(p, 'utf8').match(/!important/gi) || []).length;
  }
  return n;
}
const legacy = [
  'play-v3-capas.css','modal-core.css','modals-shell-lavanda-mobile.css','modals-secondary-unified.css',
  'screens-secondary.css','play-v3-ficha.css','play-v3-visual-review.css','play-v3-inicio-override.css',
  'design-system/screens/inicio-mobile.css','design-system/screens/inicio-desktop-cromatica.css'
];
legacy.forEach(f => ok(!php.includes(f), 'play.php sin ' + f));
['inicio/inicio-base.css','inicio/inicio-mobile.css','v4/bodies/misc-screens.css'].forEach(f => ok(php.includes(f), 'play enlaza ' + f));
const v4imp = countImp(path.join(root, 'assets/css/v4'));
ok(v4imp <= 5, 'v4 !important <= 5 (actual ' + v4imp + ')');
const inicioImp = countImp(path.join(root, 'assets/css/inicio'));
ok(inicioImp < 100, 'inicio !important < 100 (actual ' + inicioImp + ')');
const frame = fs.readFileSync(path.join(root, 'assets/css/v4/screen-frame.css'), 'utf8');
ok(/AHT-FRAME-CANON-v4/.test(frame), 'marcador frame');
console.log(failures ? '\n' + failures + ' FAIL' : '\nTODO OK');
process.exit(failures ? 1 : 0);
