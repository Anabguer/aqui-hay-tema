'use strict';
/* Contrato INICIO-CORRECCION-11 — 11 puntos obligatorios (estático DOM/CSS) */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const inicio = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio.css'), 'utf8');
const desk = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio-desktop.css'), 'utf8');
const leg = fs.readFileSync(path.join(root, 'assets/css/design-system/legibilidad-global.css'), 'utf8');
const ov = fs.readFileSync(path.join(root, 'assets/css/play-v3-inicio-override.css'), 'utf8');
const shell = fs.readFileSync(path.join(root, 'assets/css/play-v3-desktop-shell.css'), 'utf8');

const results = [];

function check(id, ok, note) {
  results.push({ id, ok, note });
}

// 1 móvil sin VER TODOS
check(1, !/plan-seccion-ver|obj-planes-prox-ver/.test(php), 'play.php sin botones VER TODOS');

// 2 avatares redondos
check(2,
  /aspect-ratio:\s*1/.test(inicio) && /border-radius:\s*50%/.test(inicio) &&
  /border-radius:\s*50% !important/.test(ov),
  'CSS caras circulares móvil + override');

// 3 nombres proporcionados (no --aht-type-name)
check(3,
  !/pp-mov-nombres[\s\S]{0,80}--aht-type-name/.test(leg) &&
  /pp-mov-nombres[\s\S]{0,80}0\.875rem/.test(leg),
  'legibilidad: nombres tarjetas 0.875rem');

// 4 badge lila contador
check(4,
  /plan-seccion-cnt[\s\S]{0,400}border-radius:\s*999px/.test(leg) &&
  /plan-seccion-cnt[\s\S]{0,400}ds-lavender-deep/.test(leg) &&
  !/cntEl\.textContent = ' ' \+/.test(js),
  'badge lila + JS sin espacio suelto');

// 5 pasar rato PLAY compacto móvil
check(5,
  /pasar-rato-ico[\s\S]{0,120}9654/.test(php) &&
  /\.top-reloj \.pasar-rato[\s\S]{0,120}min-height:\s*26px/.test(inicio),
  'PLAY icon + min-height 26px móvil');

// 6 desktop prox en columna izquierda
check(6,
  /INICIO-DESKTOP-CORRECCION-v8[\s\S]*\.encursos-movil[\s\S]*grid-column:\s*1/.test(desk) &&
  /proxplanes-movil[\s\S]*grid-column:\s*1/.test(desk) &&
  php.indexOf('data-proxplanes-block') < php.indexOf('shell-grupo-planes'),
  'desktop grid col1 + DOM enc/prox antes de +plan');

// 7 desktop sin VER TODOS
check(7, /plan-seccion-ver[\s\S]*display:\s*none !important/.test(desk), 'desktop oculta plan-seccion-ver');

// 8 cotilleo legible desktop
check(8,
  /\.obj-cotilleo-txt[\s\S]{0,120}-webkit-line-clamp:\s*3/.test(desk) &&
  /\.obj-cotilleo-txt[\s\S]{0,80}font-size:\s*\.72rem !important/.test(desk),
  'cotilleo desktop 3 líneas .72rem');

// 9 btn-guia igual móvil desktop
check(9,
  /\.btn-guia[\s\S]{0,500}text-decoration:\s*underline/.test(inicio) &&
  /\.game-top \.btn-guia[\s\S]{0,500}text-decoration:\s*underline !important/.test(desk) &&
  !/\.game-top \.btn-guia[\s\S]{0,80}padding:\s*\.32rem/.test(shell),
  'btn-guia manuscrito subrayado; shell no pisa');

// 10 pasar rato desktop = móvil base
check(10,
  /\.top-reloj \.pasar-rato[\s\S]{0,200}min-height:\s*32px !important/.test(desk) &&
  /\.top-reloj \.pasar-rato-ico/.test(desk),
  'pasar-rato desktop compacto con icono');

// 11 celestine centrado desktop
check(11,
  /\.game-left \.celestine-nota\.obj-vecinos-resumen[\s\S]{0,120}align-items:\s*center !important/.test(desk) &&
  /\.game-left \.libreta-kicker[\s\S]{0,80}text-align:\s*center !important/.test(desk),
  'Celestine apunta + avatares centrados desktop');

let failures = 0;
results.forEach(function (r) {
  const label = r.ok ? 'OK' : 'NO OK';
  if (!r.ok) failures++;
  console.log('P' + r.id + ' ' + label + ': ' + r.note);
});
console.log(failures ? '\n' + failures + ' FAIL' : '\nTODO OK 11/11');
process.exit(failures ? 1 : 0);
