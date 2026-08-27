'use strict';
/* INICIO-DESKTOP-PLANES-v17 — Plan en curso / Próximos planes desktop = lenguaje móvil v14/v13 */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const desk = fs.readFileSync(
  path.join(root, 'assets/css/design-system/screens/inicio-desktop.css'),
  'utf8'
);
const ov = fs.readFileSync(path.join(root, 'assets/css/play-v3-inicio-override.css'), 'utf8');

let fail = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) fail++;
}

ok(/function htmlEncursoCardMovil\(enc, estado\) \{\s*return htmlEncursoCardMovilV14\(enc, estado\);\s*\}/.test(js),
  'js: desktop usa htmlEncursoCardMovilV14 (sin split legacy)');
ok(!/htmlEncursoCardDesktop\(enc, estado\)/.test(js),
  'js: sin llamada legacy htmlEncursoCardDesktop');
ok(!/function htmlEncursoCardDesktop/.test(js),
  'js: sin función htmlEncursoCardDesktop');

ok(desk.includes('INICIO-ENCURSO-DESKTOP-v17'), 'css: bloque encurso desktop v17');
ok(desk.includes('INICIO-PROXPLANES-DESKTOP-v17'), 'css: bloque proxplanes desktop v17');
ok(!desk.includes('Planes en curso: carrusel'), 'css: sin sección legacy carrusel estrecho');
ok(/\.encursos-movil \.enc-mov-card-tit/.test(desk), 'css: título PLAN EN CURSO en tarjeta');
ok(/\.encursos-movil \.enc-mov-cab[\s\S]{0,60}display:\s*none/.test(desk),
  'css: cabecera sección oculta (como móvil v14)');
ok(/\[data-encursos-indice\][\s\S]{0,60}display:\s*none/.test(desk),
  'css: sin paginación 1/2');
ok(/\.encursos-movil \.enc-mov-shell/.test(desk) && /\.enc-mov-nav-btn/.test(desk),
  'css: navegación flechas desktop');
ok(/\.proxplanes-movil \.pp-mov-card[\s\S]{0,400}border:\s*2px dashed/.test(desk),
  'css: tarjetas crema borde punteado');
ok(/\.pp-mov-star[\s\S]{0,40}display:\s*none/.test(desk), 'css: sin estrella legacy');
ok(!desk.includes('Ajustes reales de maqueta'), 'css: sin bloque maqueta que invertía orden');
ok(/\.game-right \.shell-grupo-cotilleo-par \{ grid-column: 3; grid-row: 1/.test(desk),
  'css: cotilleo grid-row 1');
ok(/\.game-right \.shell-grupo-misiones-par \{ grid-column: 3; grid-row: 2/.test(desk),
  'css: misiones grid-row 2');
ok(!/\.game-right \.shell-grupo-misiones-par \{ grid-column: 3; grid-row: 1/.test(desk),
  'css: misiones no primero');
ok(/\.game-right \.obj-cotilleo-tit[\s\S]{0,520}white-space:\s*nowrap/.test(desk),
  'css: etiqueta COTILLEO horizontal');
ok(!/width:\s*63px;\s*height:\s*63px/.test(desk), 'css: sin etiqueta cuadrada 63px');

const v14Block = (() => {
  const i = ov.indexOf('INICIO-ENCURSO-REF-v14');
  const j = ov.indexOf('INICIO-ENCURSO-FIX-v15');
  return i < 0 ? '' : ov.slice(i, j > i ? j : undefined);
})();
ok(v14Block.includes('@media (max-width: 768px)'), 'override v14 sigue solo móvil');

const v13Block = (() => {
  const i = ov.indexOf('INICIO-PROXPLANES-REF-v13');
  const j = ov.indexOf('INICIO-ENCURSO-REF-v14');
  return i < 0 ? '' : ov.slice(i, j > i ? j : undefined);
})();
ok(v13Block.includes('@media (max-width: 768px)'), 'override v13 sigue solo móvil');

console.log(fail ? '\n' + fail + ' FAIL' : '\nTODO OK');
process.exit(fail ? 1 : 0);
