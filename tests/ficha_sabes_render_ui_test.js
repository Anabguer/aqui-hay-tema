// Test UI P05: contrato de render de «Lo que sabes» (recuperación ficha).
// Blindajes: oculto sin pistas; líneas condicionales por grupo; solo pistas_grupos;
// jamás toca claves de «gente»; orden markup Hobbies < Lo que sabes < Relaciones.
const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
let failures = 0;
function ok(c, m) { console.log((c ? 'OK' : 'FAIL') + ': ' + m); if (!c) failures++; }

const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');

const fn = js.slice(js.indexOf('function pintarLoQueSabes('), js.indexOf('function textoPlanesVacios('));
ok(fn.includes('function pintarLoQueSabes('), 'bloque pintarLoQueSabes localizado');

ok(/if\s*\(!animas\.length\s*&&\s*!disgustas\.length\)[\s\S]*sec\.hidden\s*=\s*true/.test(fn),
  'sin descubrimientos → sección oculta (sec.hidden = true)');

ok(/if\s*\(animas\.length\)\s*\{[\s\S]*?Le anima: /.test(fn), 'solo gusto descubierto → línea «Le anima»');
ok(/if\s*\(disgustas\.length\)\s*\{[\s\S]*?No le gusta: /.test(fn), 'solo rechazo descubierto → línea «No le gusta»');

ok(fn.includes('vista.pistas_grupos') && !fn.includes('gusta_en_gente') && !fn.includes('no_gusta_en_gente'),
  'se alimenta solo de pistas_grupos; nunca de claves de «gente»');

ok(fn.includes("g.animas") && fn.includes("g.disgustas"), 'grupos animas/disgustas desde pistas_grupos');

const pH = php.indexOf('data-ficha-hobbies');
const pS = php.indexOf('data-ficha-sabes');
const pR = php.indexOf('data-ficha-relaciones');
ok(pH > -1 && pS > pH && pR > pS, 'markup: Hobbies < Lo que sabes < Relaciones');

ok(/data-ficha-sabes hidden/.test(php), 'sección oculta por defecto en markup');

ok(!php.includes('gusta_en_gente') && !php.includes('no_gusta_en_gente'),
  'play.php sin hooks de «gente»');

console.log(failures === 0 ? '\nficha_sabes_render_ui_test OK' : '\nficha_sabes_render_ui_test FAIL (' + failures + ')');
process.exit(failures > 0 ? 1 : 0);
