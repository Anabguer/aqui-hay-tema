'use strict';
/* INICIO-ENCURSO-REF-v14 — Plan en curso móvil según ref captura */
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const ov = fs.readFileSync(path.join(root, 'assets/css/play-v3-inicio-override.css'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const desk = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio-desktop.css'), 'utf8');

let fail = 0;
function ok(c, m) { console.log((c ? 'OK' : 'FAIL') + ': ' + m); if (!c) fail++; }

const v14 = (() => {
  const i = ov.indexOf('INICIO-ENCURSO-REF-v14');
  return i < 0 ? '' : ov.slice(i);
})();

ok(v14.includes('@media (max-width: 768px)'), 'v14 solo móvil');
ok(!v14.includes('min-width: 769px'), 'v14 no desktop');
ok(/\.encursos-movil \.enc-mov-cab[\s\S]{0,80}display:\s*none/.test(v14), 'cabecera sección oculta (título en tarjeta)');
ok(/\.encursos-movil \.enc-mov-card-tit/.test(v14), 'título en tarjeta');
ok(/\.encursos-movil \.enc-mov-body[\s\S]{0,120}flex-direction:\s*column/.test(v14), 'cuerpo vertical');
ok(/\.encursos-movil \.enc-mov-faces[\s\S]{0,120}justify-content:\s*center/.test(v14), 'avatares centrados');
ok(/\.encursos-movil \.enc-mov-heart/.test(v14), 'corazón entre avatares');
ok(/\.encursos-movil \.enc-mov-meta[\s\S]{0,120}ds-font-hand/.test(v14), 'meta manuscrita');
ok(/\.encursos-movil \.enc-mov-resumen/.test(v14), 'resumen corto');
ok(/\.encursos-movil \.enc-mov-cta[\s\S]{0,200}width:\s*100%/.test(v14), 'CTA ancho completo');
ok(!/INICIO-ENCURSO-REF-v14/.test(desk), 'desktop sin v14');

ok(js.includes('function formatEncursoMetaLine'), 'js: meta actividad+hora');
ok(js.includes('function encCursoFacesHtml'), 'js: caras con corazón');
ok(js.includes('function resumenEncursoMovil'), 'js: resumen');
ok(js.includes('enc-mov-card-tit'), 'js: título en tarjeta');
ok(js.includes('enc-mov-meta'), 'js: línea meta');
ok(js.includes('enc-mov-resumen'), 'js: línea resumen');
ok(js.includes('Ver encuentro') && js.includes('Intervenir'), 'js: CTA ver/intervenir');
ok(js.includes('data-enc-mov-toggle'), 'js: toggle panel conservado');

ok(php.includes('PLAN EN CURSO'), 'play.php: copy PLAN EN CURSO');
ok(!/INICIO-PROXPLANES-REF-v13[\s\S]{0,200}\.encursos-movil/.test(ov.slice(ov.indexOf('INICIO-PROXPLANES-REF-v13'))),
  'v13 proxplanes no toca encursos');

console.log(fail ? '\n' + fail + ' FAIL' : '\nTODO OK');
process.exit(fail ? 1 : 0);
