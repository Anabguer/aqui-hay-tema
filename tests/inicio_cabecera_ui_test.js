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
ok(!/inicio-mobile \.control-audio/.test(mob), 'mobile: sin reglas control-audio en cabecera');
ok(/inicio-temporal-pill[\s\S]{0,200}flex-wrap:\s*nowrap/.test(mob), 'mobile: píldora en una línea');
ok(/INICIO-CABECERA-MOVIL[\s\S]*pasar-rato-txt[\s\S]{0,100}display:\s*none/.test(mob), 'mobile: sin texto Pasar el rato');
ok(/inicio-header-brand-row[\s\S]{0,500}top-vida[\s\S]{0,200}width:\s*auto/.test(mob), 'mobile: corazón vida compacto');

ok(/INICIO-DESKTOP-CABECERA-20260906/.test(desk), 'desktop: bloque cabecera canónico');
ok(/brand-text[\s\S]{0,200}clamp\(2\.75rem,\s*4\.6vw,\s*3\.65rem\)/.test(desk), 'desktop: marca grande ref PNG');
ok(/INICIO-DESKTOP-BRAND-ALIGN-20260906/.test(desk), 'desktop: marca alineada ref PNG');
ok(/brand-heart[\s\S]{0,200}clamp\(22px,\s*2\.6vw,\s*30px\)/.test(desk), 'desktop: corazón marca más pequeño');
ok(/game-top[\s\S]{0,300}border:\s*1px solid #d9d2e1/.test(desk), 'desktop: tarjeta blanca cabecera');
ok(/top-center[\s\S]{0,200}justify-content:\s*center/.test(desk), 'desktop: reloj centrado');
ok(/INICIO-DESKTOP-CABECERA-RELOJ-CANON-20260906/.test(desk), 'desktop: reloj canon en cabecera');
ok(!/INICIO-DESKTOP-TOP-RELOJ-20260906/.test(desk), 'desktop: sin bloque corrector TOP-RELOJ');
ok(!/obj-dia-num::before[\s\S]{0,80}content:\s*"Día /.test(desk), 'desktop: sin prefijo Día duplicado');
ok(/INICIO-DESKTOP-VIDA-SCALE-20260906/.test(desk), 'desktop: escala vida del pueblo ref PNG');
ok(/obj-vida-kicker[\s\S]{0,280}clamp\(1\.5rem,\s*2\.4vw,\s*1\.95rem\)/.test(desk), 'desktop: kicker vida grande');
ok(/corazon-svg[\s\S]{0,120}clamp\(72px,\s*5\.8vw,\s*92px\)/.test(desk), 'desktop: corazón vida grande');
ok(/obj-vida-kicker[\s\S]{0,280}text-decoration:\s*underline/.test(desk), 'desktop: subrayado vida del pueblo');
ok(!/inicio-desktop > \.game-top \.control-audio/.test(desk), 'desktop: sin controles flotantes en cabecera');
ok(/obj-dia[\s\S]{0,500}height:\s*54px/.test(desk), 'desktop: pastilla dia altura fija');
ok(/obj-hora[\s\S]{0,500}height:\s*54px/.test(desk), 'desktop: pastilla hora altura fija');
ok(/obj-dia[\s\S]{0,600}box-shadow:\s*none/.test(desk), 'desktop: pastilla dia sin sombra');
ok(/obj-hora[\s\S]{0,600}box-shadow:\s*none/.test(desk), 'desktop: pastilla hora sin sombra');
ok(/obj-dia-estacion[\s\S]{0,80}display:\s*none/.test(desk), 'desktop: sin Primavera en cabecera');

ok(!/:not\(:has\(\.inicio-stage\)\) \.game-top \.top-vida/.test(art), 'shell-art: sin top-vida legacy en cabecera');
ok(!/!important/.test(mob + desk), 'cabeceras: cero !important');

if (failures) {
  console.error('\n' + failures + ' fallo(s)');
  process.exit(1);
}
console.log('\ninicio_cabecera_ui_test OK');
