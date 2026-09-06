'use strict';
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const desk = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-desktop.css'), 'utf8');
const croma = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-cromatica-desktop.css'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const mob = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-mobile.css'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

function extraerBloque(src, needle) {
  const i = src.indexOf(needle);
  if (i < 0) return '';
  let prof = 0;
  const start = src.indexOf('{', i);
  for (let j = start; j < src.length; j++) {
    if (src[j] === '{') prof++;
    else if (src[j] === '}') { prof--; if (prof === 0) return src.slice(i, j + 1); }
  }
  return '';
}

ok(/INICIO-PLANES-BLOQUE-CANON-20260906/.test(desk), 'desktop: bloque canon planes');
ok(/enc-mov-track[\s\S]{0,260}overflow-x:\s*auto/.test(desk), 'desktop EN CURSO: track horizontal');
ok(/enc-mov-card[\s\S]{0,260}flex:\s*0\s*0\s*100%/.test(desk), 'desktop EN CURSO: 1 card ancho completo');
ok(/pp-mov-card--desk-duo[\s\S]{0,320}calc\(50% - 4px\)/.test(desk), 'desktop PRÓXIMOS: duo 2 columnas');
ok(/proxplanes-movil--solo[\s\S]{0,220}flex:\s*0\s*0\s*100%/.test(desk), 'desktop PRÓXIMOS: 1 card ancho completo');
ok(/encursos-movil\.is-on[\s\S]{0,320}background:\s*rgba\(232,\s*245,\s*234/.test(desk), 'desktop EN CURSO: zona verde suave');
ok(/obj-nuevo-plan-ico[\s\S]{0,180}display:\s*inline-flex/.test(desk), 'desktop CREAR PLAN: icono ++ visible');
ok(!/obj-nuevo-plan-txt::before/.test(desk), 'desktop CREAR PLAN: sin pseudo ++ en texto');
ok(!/INICIO-PLANES-FIXES-DESKTOP-v145/.test(croma), 'cromatica: sin bloque carrusel duplicado roto');
ok(!/!important/.test(desk), 'desktop planes: cero !important');

ok(/ppMovEsDesktop\(block\)/.test(js), 'js PRÓXIMOS: rama desktop en nav');

(function logica() {
  const fnPaso = extraerBloque(js, 'function ppMovPaso(');
  const fnIrA = extraerBloque(js, 'function ppMovIrA(');
  ok(/ppMovEsDesktop\(block\)\) return track\.clientWidth/.test(fnPaso), 'js PRÓXIMOS desktop: paso = ancho visible (2 cards)');
  ok(/ppMovEsDesktop\(block\) && n > 2/.test(fnIrA), 'js PRÓXIMOS desktop: scroll paginado con 3+');
  const fnEncNav = extraerBloque(js, 'function renderEncursosMovilNavFor(');
  const fnProxNav = extraerBloque(js, 'function renderProxplanesNavFor(');
  ok(/prev\.hidden = n < 2/.test(fnEncNav) && /next\.hidden = n < 2/.test(fnEncNav), 'js EN CURSO: sin flechas con 1 plan');
  ok(/needsCarousel = n > 2/.test(fnProxNav), 'js PRÓXIMOS desktop: carrusel solo con 3+');
})();

ok(!/inicio-mobile \.proxplanes-movil \.pp-mov-track[\s\S]{0,80}calc\(50%/.test(mob) ||
  !desk.includes('.inicio-mobile .proxplanes-movil'),
  'movil: reglas prox no contaminadas por desktop');

if (failures) {
  console.error('\n' + failures + ' fallo(s)');
  process.exit(1);
}
console.log('\ninicio_planes_desktop_carousel_test OK');
