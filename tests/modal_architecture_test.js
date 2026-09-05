'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const frame = fs.readFileSync(path.join(root, 'assets/css/v4/screen-frame.css'), 'utf8');
const screens = fs.readFileSync(path.join(root, 'assets/css/v4/screens.css'), 'utf8');
const tokens = fs.readFileSync(path.join(root, 'assets/css/v4/tokens-v4.css'), 'utf8');
let failures = 0;
function ok(cond, msg) { console.log((cond ? 'OK' : 'FAIL') + ': ' + msg); if (!cond) failures++; }
['play-v3-capas.css','modal-core.css','modals-shell-lavanda-mobile.css','modals-secondary-unified.css'].forEach(f => ok(!php.includes(f), 'play.php sin ' + f));
ok(/tokens-v4\.css/.test(php) && /screen-frame\.css/.test(php) && /screens\.css/.test(php), 'stack V4');
ok(/AHT-FRAME-CANON-v4/.test(frame), 'marcador frame');
ok(/--aht-shell-bg:\s*#FCFBFE/.test(tokens), 'shell token');
ok((php.match(/class="aht-screen"/g) || []).length >= 19, '>=19 screens');
console.log(failures ? '\n' + failures + ' FAIL' : '\nTODO OK');
process.exit(failures ? 1 : 0);