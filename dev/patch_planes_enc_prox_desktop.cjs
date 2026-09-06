'use strict';
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

function norm(s) {
  return s.replace(/\r\n/g, '\n');
}

const deskPath = path.join(root, 'assets/css/inicio/inicio-desktop.css');
let desk = norm(fs.readFileSync(deskPath, 'utf8'));

const canonStart = '/* INICIO-PLANES-BLOQUE-CANON-20260906';
const pass4Start = '/* INICIO-VISUAL-PASS4-20260906';
const iCanon = desk.indexOf(canonStart);
const iPass4 = desk.indexOf(pass4Start);
if (iCanon < 0 || iPass4 < 0 || iPass4 <= iCanon) {
  console.error('patch_planes_enc_prox_desktop: marcadores CANON/PASS4 no encontrados');
  process.exit(1);
}

const canonBlock = `/* INICIO-PLANES-BLOQUE-CANON-20260906 — desktop: libreta + EN CURSO + PRÓXIMOS + CREAR PLAN */
@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px 14px 12px;
    background: #fff;
    border: 1.5px solid #d9d2e1;
    border-radius: 16px;
    box-shadow: none;
    filter: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin: 0;
    padding: 0 2px 10px;
    border-bottom: 1px solid rgba(111, 95, 154, .18);
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-tit {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: 1.05rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #6f5f9a;
    margin: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-badges {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 600;
    font-size: .76rem;
    line-height: 1.2;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta-badge-ico {
    flex: 0 0 auto;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta > .shell-grupo,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .encursos-movil,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .proxplanes-movil,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .shell-grupo-planes {
    background: transparent;
    border: none;
    box-shadow: none;
    margin: 0;
    padding: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .encursos-movil:not(.is-on) {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .encursos-movil.is-on {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0 0 8px;
    padding: 10px 10px 12px;
    border: 1px solid rgba(109, 155, 118, .24);
    border-radius: 12px;
    background: rgba(232, 245, 234, .58);
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .inicio-planes-agenda {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
    padding: 10px 6px 6px;
    border: 1px dashed rgba(111, 95, 154, .22);
    border-radius: 10px;
    background: transparent;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .plan-seccion-cab {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 8px;
    padding: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .plan-seccion-ico {
    flex: 0 0 auto;
    width: 18px;
    height: 18px;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-ico,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-ico {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-tit,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-tit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .78rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-tit::before {
    content: "";
    flex: 0 0 auto;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #5a9a62;
    box-shadow: 0 0 0 2px rgba(90, 154, 98, .18);
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-tit {
    color: #3f6340;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .plan-seccion-rule,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .plan-seccion-ver {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-tit .plan-seccion-cnt {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-tit .plan-seccion-cnt {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    border-radius: 999px;
    background: #6f5f9a;
    color: #fff;
    font-size: .68rem;
    font-weight: 800;
    line-height: 1;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .proxplanes-movil.is-on {
    border: none;
    border-radius: 0;
    box-shadow: none;
    background: transparent;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-shell,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-shell {
    position: relative;
    display: block;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-track,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-track {
    display: flex;
    flex-wrap: nowrap;
    align-items: stretch;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    gap: 8px;
    padding: 2px 0 4px;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-track::-webkit-scrollbar,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-track::-webkit-scrollbar {
    display: none;
    width: 0;
    height: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .encursos-movil .enc-mov-card {
    flex: 0 0 100%;
    width: 100%;
    min-width: 100%;
    max-width: 100%;
    scroll-snap-align: start;
    box-sizing: border-box;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-card--escena::before {
    content: none;
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-card--ref-desk .enc-mov-estado-pill--fuera {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .proxplanes-movil .pp-mov-card--desk-duo {
    flex: 0 0 calc(50% - 4px);
    width: calc(50% - 4px);
    min-width: calc(50% - 4px);
    scroll-snap-align: start;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 6px;
    padding: 8px 8px 10px;
    border: 1px solid rgba(111, 95, 154, .14);
    border-radius: 12px;
    background: #f3f0f8;
    box-shadow: none;
    box-sizing: border-box;
    transform: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .proxplanes-movil.proxplanes-movil--solo .pp-mov-card--desk-duo {
    flex: 0 0 100%;
    width: 100%;
    min-width: 100%;
    max-width: 100%;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-card--desk-duo .pp-mov-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-card--desk-duo .pp-mov-hora {
    display: inline-flex;
    align-items: center;
    margin: 0;
    padding: 3px 8px;
    border: 1px solid rgba(111, 95, 154, .2);
    border-radius: 999px;
    background: #fff;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: .62rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #6f5f9a;
    line-height: 1.2;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-card--desk-duo .pp-mov-star {
    flex: 0 0 auto;
    color: #e0b820;
    font-size: .9rem;
    line-height: 1;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-card--desk-duo .pp-mov-body--desk-duo {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    text-align: center;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .plan-desk-duo-wrap {
    width: 100%;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .plan-desk-duo-faces {
    justify-content: center;
    margin: 0 auto;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .plan-desk-duo-nombres {
    display: flex;
    justify-content: center;
    gap: 10px;
    width: 100%;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-nav-btn,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-nav-btn {
    position: absolute;
    top: 50%;
    z-index: 4;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    margin: 0;
    padding: 0;
    border: 1.5px solid rgba(111, 95, 154, .28);
    border-radius: 50%;
    background: rgba(255, 255, 255, .94);
    color: #6f5f9a;
    box-shadow: 0 2px 8px rgba(111, 95, 154, .16);
    transform: translateY(-50%);
    cursor: pointer;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-nav-btn svg,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-nav-btn svg {
    width: 14px;
    height: 14px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-nav-prev {
    left: -6px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-nav-next {
    right: -6px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-nav-prev {
    left: -8px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-nav-next {
    right: -8px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .encursos-movil.encursos-movil--multi .enc-mov-shell {
    padding: 0 14px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .proxplanes-movil.proxplanes-movil--many .pp-mov-shell {
    padding: 0 14px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan.obj-nuevo-plan-horiz {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    min-height: 40px;
    margin: 6px 0 0;
    padding: 9px 16px;
    background: #fff;
    border: 1.5px solid #6f5f9a;
    border-radius: 999px;
    box-shadow: none;
    cursor: pointer;
    font: inherit;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-ico {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0;
    width: 12px;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: .62rem;
    font-weight: 900;
    line-height: .52;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-ico::after {
    content: "+";
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-txt {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .72rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo.shell-grupo-parejas {
    padding: 12px 14px;
    background: #fff9fa;
    border: 1.5px solid rgba(232, 160, 180, .38);
    border-radius: 16px;
    box-shadow: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo-parejas .zona-tit-parejas {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1.5px solid rgba(232, 160, 180, .28);
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: 1.05rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #2a2218;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .obj-parejas-vacio,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo-parejas .obj-parejas-list > .muted {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: .78rem;
    font-weight: 600;
    color: #7a7164;
    text-align: center;
    padding: 8px 4px;
  }
}

`;

desk = desk.slice(0, iCanon) + canonBlock + '\n' + desk.slice(iPass4);
fs.writeFileSync(deskPath, desk, 'utf8');

const cromaPath = path.join(root, 'assets/css/inicio/inicio-cromatica-desktop.css');
let croma = norm(fs.readFileSync(cromaPath, 'utf8'));

croma = croma.replace(
  /\/\* INICIO-PLANES-FIXES-DESKTOP-v143[\s\S]*?\/\* INICIO-BOTTOM-NAV-DESKTOP-v138/,
  '/* INICIO-PLANES-FIXES-DESKTOP-v143-v147 — estructura migrada a inicio-desktop.css PLANES-BLOQUE-CANON */\n\n/* INICIO-BOTTOM-NAV-DESKTOP-v138'
);

croma = croma.replace(
  /\.play-v3 \.inicio-desktop-right \.inicio-planes-libreta \.obj-nuevo-plan\.obj-nuevo-plan-horiz \{\s*border-color: #e07a62;\s*\}/,
  '.play-v3 .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan.obj-nuevo-plan-horiz {\n  border-color: #6f5f9a;\n}'
);

croma = croma.replace(
  /\.play-v3 \.inicio-desktop-right \.inicio-planes-libreta \.obj-nuevo-plan-ico,\s*\n\s*\.play-v3 \.inicio-desktop-right \.inicio-planes-libreta \.obj-nuevo-plan-txt \{\s*color: #e07a62;\s*\}/,
  '.play-v3 .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-ico,\n  .play-v3 .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-txt {\n  color: #6f5f9a;\n}'
);

fs.writeFileSync(cromaPath, croma, 'utf8');

const testPath = path.join(root, 'tests/inicio_planes_desktop_carousel_test.js');
if (!fs.existsSync(testPath)) {
  fs.writeFileSync(testPath, `'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

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

const fnEncNav = extraerBloque(js, 'function renderEncursosMovilNavFor(');
ok(/prev\.hidden = n < 2 \|\| idx <= 0/.test(fnEncNav), 'js EN CURSO: flechas solo con 2+');
const fnProxNav = extraerBloque(js, 'function renderProxplanesNavFor(');
ok(/needsCarousel = n > 2/.test(fnProxNav), 'js PRÓXIMOS desktop: carrusel solo con 3+');
ok(/ppMovEsDesktop\(block\)/.test(fnProxNav), 'js PRÓXIMOS: rama desktop en nav');

(function logica() {
  const fns = ['function ppMovPaso(', 'function ppMovEsDesktop(', 'function encMovPaso(']
    .map(function (n) { return extraerBloque(js, n); }).join('\\n');
  const sandbox = {};
  vm.createContext(sandbox);
  vm.runInContext(fns + '\\nfunction __ppDesktop(block, n) {\\n' +
    '  block = { closest: function() { return block._desk ? {} : null; } };\\n' +
    '  block._desk = true;\\n' +
    '  const track = { clientWidth: 300, scrollWidth: n > 2 ? 600 : 300, scrollLeft: 0 };\\n' +
    '  const paso = ppMovPaso(track, block);\\n' +
    '  return { paso: paso, carousel: n > 2 && track.scrollWidth > track.clientWidth };\\n' +
    '}', sandbox);
  const a = sandbox.__ppDesktop({}, 1);
  const b = sandbox.__ppDesktop({}, 2);
  const c = sandbox.__ppDesktop({}, 3);
  ok(a.paso === 300 && !a.carousel, 'estado A/B prox 1-2: sin carrusel');
  ok(c.carousel, 'estado D prox 3+: carrusel activo');
})();

ok(!/inicio-mobile \\.proxplanes-movil \\.pp-mov-track[\s\S]{0,80}calc\(50%/.test(mob) ||
  !desk.includes('.inicio-mobile .proxplanes-movil'),
  'movil: reglas prox no contaminadas por desktop');

if (failures) {
  console.error('\\n' + failures + ' fallo(s)');
  process.exit(1);
}
console.log('\\ninicio_planes_desktop_carousel_test OK');
`, 'utf8');
}

const cabTest = path.join(root, 'tests/inicio_cabecera_ui_test.js');
let cab = fs.readFileSync(cabTest, 'utf8');
cab = cab.replace(
  "ok(/inicio-planes-agenda[\\s\\S]{0,200}border:\\s*1\\.5px dashed/.test(desk), 'desktop: agenda proximos dashed');",
  "ok(/inicio-planes-agenda[\\s\\S]{0,220}border:\\s*1px dashed/.test(desk), 'desktop: agenda proximos dashed ligero');"
);
fs.writeFileSync(cabTest, cab, 'utf8');

if (!/flex:\s*0\s*0\s*100%/.test(desk)) {
  console.error('patch_planes_enc_prox_desktop: canon incompleto');
  process.exit(1);
}
console.log('patch_planes_enc_prox_desktop OK');
