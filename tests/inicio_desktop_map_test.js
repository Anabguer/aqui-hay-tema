'use strict';
/* INICIO-DESKTOP-MAP-v11 — mapa central visible en desktop */
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const desk = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio-desktop.css'), 'utf8');
const inicio = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio.css'), 'utf8');
const ov = fs.readFileSync(path.join(root, 'assets/css/play-v3-inicio-override.css'), 'utf8');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');

let fail = 0;
function ok(c, m) { console.log((c ? 'OK' : 'NO OK') + ': ' + m); if (!c) fail++; }

ok(/INICIO-DESKTOP-MAP-v11/.test(desk), 'bloque MAP v11');
ok(/INICIO-DESKTOP-MAP-v11[\s\S]*align-items: stretch !important/.test(desk),
  'game-main stretch (no colapso mapa)');
ok(/INICIO-DESKTOP-MAP-v11[\s\S]*\.game-map-wrap[\s\S]*grid-column: 2 !important/.test(desk),
  'mapa columna central');
ok(/INICIO-DESKTOP-MAP-v11[\s\S]*\.game-map-wrap[\s\S]*min-height: min\(76vh, 660px\)/.test(desk),
  'mapa altura mínima canónica');
ok(/INICIO-DESKTOP-MAP-v11[\s\S]*visibility: visible !important/.test(desk),
  'mapa no oculto');
ok(/mapa-canonico/.test(php) && /data-mapa-canonico/.test(php) && /data-edificios-layer/.test(php),
  'DOM mapa canónico intacto');

ok(/@media \(max-width: 768px\)/.test(inicio) &&
  /game-map-wrap[\s\S]{0,80}grid-row: 2/.test(inicio) &&
  !/INICIO-DESKTOP-MAP-v11/.test(inicio),
  'móvil: mapa fila 2 sin v11 desktop');
ok(/INICIO-OVERRIDE-LAYOUT-v8[\s\S]*game-map-wrap[\s\S]*grid-row: 2 !important/.test(ov),
  'override móvil mapa intacto');

console.log(fail ? '\n' + fail + ' FAIL' : '\nTODO OK');
process.exit(fail ? 1 : 0);
