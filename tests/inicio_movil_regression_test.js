'use strict';
/* INICIO-MOVIL-REGRESSION-v9 — 6 puntos post d33c703 (solo móvil) */
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const inicio = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio.css'), 'utf8');
const ov = fs.readFileSync(path.join(root, 'assets/css/play-v3-inicio-override.css'), 'utf8');
const desk = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio-desktop.css'), 'utf8');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');

const r = [];
function check(id, ok, note) { r.push({ id, ok, note }); }

// 1 feed ancho completo
check(1,
  !/grid-column:\s*auto !important/.test(ov) &&
  /shell-grupo-cotilleo-par \{ grid-column: 1 \/ -1 !important/.test(ov) &&
  /shell-grupo-misiones-par \{ grid-column: 1 \/ -1 !important/.test(ov) &&
  /shell-grupo-parejas \{ grid-column: 1 \/ -1 !important/.test(ov) &&
  !/\.game-right \{\s*\n    display: flex !important/.test(ov),
  'feed grid-column 1/-1; sin game-right flex override');

// 2 mensajitos tile selector + marco
check(2,
  /\.game-left\.zona-actividad \.obj-buzon[\s\S]{0,400}border: 2px solid/.test(ov) &&
  !/\.game-left \.zona-actividad \.obj-buzon/.test(ov),
  'selector .game-left.zona-actividad + marco obj-buzon');

// 3 iconos sin ? rotos en cabecera + CSS escapes
check(3,
  /control-musica-ico[\s\S]{0,80}9834/.test(php) &&
  /control-efectos-ico[\s\S]{0,80}10022/.test(php) &&
  /zona-tit-parejas::before[\s\S]{0,60}\\2665/.test(inicio) &&
  /obj-misiones-papel-tit::before[\s\S]{0,60}\\1F4CB/.test(inicio),
  'iconos HTML entities + CSS unicode escapes');

// 4 cabecera compacta
check(4,
  /row-gap: 2px/.test(inicio) &&
  /\.play-v3:has\(\.game-shell\) \.top-meta-hora[\s\S]{0,200}align-self: center/.test(inicio) &&
  /\.play-v3:has\(\.game-shell\) \.top-reloj[\s\S]{0,200}gap: 4px/.test(inicio),
  'meta/hora/rato compactos');

// 5 pasar rato más bajo + PLAY
check(5,
  /\.top-reloj \.pasar-rato[\s\S]{0,80}min-height: 26px/.test(inicio) &&
  /pasar-rato[\s\S]{0,80}9654/.test(php) &&
  /\.top-reloj \.pasar-rato[\s\S]{0,120}min-height: 26px !important/.test(ov),
  'pasar-rato 26px + PLAY');

// 6 desktop intacto (sin REGRESSION-v9 en desktop)
check(6,
  !/INICIO-MOVIL-REGRESSION-v9/.test(desk) &&
  /@media \(min-width: 769px\)/.test(desk),
  'inicio-desktop.css sin cambios móvil v9');

let fail = 0;
r.forEach(function (x) {
  console.log('P' + x.id + ' ' + (x.ok ? 'OK' : 'NO OK') + ': ' + x.note);
  if (!x.ok) fail++;
});
console.log(fail ? '\n' + fail + ' FAIL' : '\nTODO OK 6/6');
process.exit(fail ? 1 : 0);
