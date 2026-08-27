'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const leg = fs.readFileSync(path.join(root, 'assets/css/design-system/legibilidad-global.css'), 'utf8');
const tokens = fs.readFileSync(path.join(root, 'assets/css/design-system/tokens.css'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

ok(/legibilidad-global\.css/.test(php), 'play.php enlaza legibilidad-global.css al final');
ok(php.indexOf('legibilidad-global.css') > php.indexOf('visual-replica.css'), 'legibilidad-global.css carga después de visual-replica');
ok(/--aht-type-display/.test(tokens), 'tokens.css define --aht-type-display');
ok(/--aht-type-body/.test(tokens), 'tokens.css define --aht-type-body');
ok(/LEGIBILIDAD-GLOBAL-v1/.test(leg), 'legibilidad-global.css presente');
ok(/var\(--aht-type-block\)/.test(leg), 'mapeo títulos bloque a token');
ok(/var\(--aht-type-body\)/.test(leg), 'mapeo cuerpo a token');
ok(/TUTORIAL-ESTABLE-v5/.test(fs.readFileSync(path.join(root, 'assets/css/play-v3-tutorial-ds.css'), 'utf8')), 'marco tutorial estable intacto');
ok(!/height:\s*min\(87dvh/.test(leg), 'legibilidad no toca altura modal tutorial');

console.log(failures ? '\n' + failures + ' FAIL' : '\nTODO OK');
process.exit(failures ? 1 : 0);
