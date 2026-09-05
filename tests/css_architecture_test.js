'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
let failures = 0;
function ok(cond, msg) { console.log((cond ? 'OK' : 'FAIL') + ': ' + msg); if (!cond) failures++; }

function countImpInDir(dir) {
  let n = 0;
  const offenders = [];
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) {
      const sub = countImpInDir(p);
      n += sub.total;
      offenders.push(...sub.offenders);
    } else if (e.name.endsWith('.css')) {
      const m = fs.readFileSync(p, 'utf8').match(/!important/gi);
      if (m && m.length) {
        n += m.length;
        offenders.push(path.relative(root, p).replace(/\\/g, '/') + ':' + m.length);
      }
    }
  }
  return { total: n, offenders };
}

const legacy = [
  'play-v3-capas.css', 'modal-core.css', 'modals-shell-lavanda-mobile.css', 'modals-secondary-unified.css',
  'screens-secondary.css', 'play-v3-ficha.css', 'play-v3-visual-review.css', 'play-v3-inicio-override.css',
  'design-system/screens/inicio-mobile.css', 'design-system/screens/inicio-desktop-cromatica.css',
  'mensajitos-cartas-persona-v1.css', 'mensajitos-carta-regalo-v1.css'
];
legacy.forEach(f => ok(!php.includes(f), 'play.php sin ' + f));
['inicio/inicio-base.css', 'inicio/inicio-mobile.css', 'v4/bodies/misc-screens.css', 'design-system/mensajitos-body.css'].forEach(f => ok(php.includes(f), 'play enlaza ' + f));

const cssImp = countImpInDir(path.join(root, 'assets/css'));
ok(cssImp.total === 0, 'assets/css !important total = 0 (actual ' + cssImp.total + (cssImp.offenders.length ? ': ' + cssImp.offenders.join(', ') : '') + ')');

const styleBlocks = php.match(/<style>[\s\S]*?<\/style>/g) || [];
let inlineImp = 0;
styleBlocks.forEach(block => {
  inlineImp += (block.match(/!important/gi) || []).length;
});
ok(inlineImp === 0, 'play.php inline !important = 0 (actual ' + inlineImp + ')');

const frame = fs.readFileSync(path.join(root, 'assets/css/v4/screen-frame.css'), 'utf8');
ok(/AHT-FRAME-CANON-v4/.test(frame), 'marcador frame');
ok((php.match(/class="aht-screen"/g) || []).length >= 19, '>=19 screens');

console.log(failures ? '\n' + failures + ' FAIL' : '\nTODO OK');
process.exit(failures ? 1 : 0);
