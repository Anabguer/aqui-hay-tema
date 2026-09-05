'use strict';
/* Contrato estático: cabeceras Inicio móvil + desktop (pantallazos aprobados). */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const mob = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-mobile.css'), 'utf8');
const desk = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-desktop.css'), 'utf8');
const art = fs.readFileSync(path.join(root, 'assets/css/play-v3-shell-art.css'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

ok(/inicio-header-card/.test(php), 'play.php: cabecera móvil con inicio-header-card');
ok(/inicio-temporal-pill/.test(php), 'play.php: píldora temporal móvil');
ok(/obj-vida-kicker/.test(php), 'play.php: kicker vida desktop');

ok(/INICIO-CABECERA-MOVIL-20260906/.test(mob), 'mobile: bloque cabecera canónico');
ok(/inicio-temporal-pill[\s\S]{0,200}flex-wrap:\s*nowrap/.test(mob), 'mobile: píldora en una línea');
ok(/INICIO-CABECERA-MOVIL[\s\S]*pasar-rato-txt[\s\S]{0,100}display:\s*none/.test(mob), 'mobile: sin texto Pasar el rato');
ok(/inicio-header-brand-row[\s\S]{0,500}top-vida[\s\S]{0,200}width:\s*auto/.test(mob), 'mobile: corazón vida compacto');

ok(/INICIO-DESKTOP-CABECERA-20260906/.test(desk), 'desktop: bloque cabecera canónico');
ok(/game-top[\s\S]{0,300}border:\s*1px solid #d9d2e1/.test(desk), 'desktop: tarjeta blanca cabecera');
ok(/top-center[\s\S]{0,200}justify-content:\s*center/.test(desk), 'desktop: reloj centrado');
ok(/obj-vida-kicker[\s\S]{0,200}text-transform:\s*uppercase/.test(desk), 'desktop: VIDA DEL PUEBLO');
ok(/obj-dia-estacion[\s\S]{0,80}display:\s*none/.test(desk), 'desktop: sin Primavera en cabecera');

ok(!/:not\(:has\(\.inicio-stage\)\) \.game-top \.top-vida/.test(art), 'shell-art: sin top-vida legacy en cabecera');
ok(!/!important/.test(mob + desk), 'cabeceras: cero !important');

if (failures) {
  console.error('\n' + failures + ' fallo(s)');
  process.exit(1);
}
console.log('\ninicio_cabecera_ui_test OK');
