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

ok(/data-open="necesidades_global"/.test(php), 'play.php: nav necesidades');
ok(/data-open="historia"/.test(php), 'play.php: nav historia');
ok(/repeat\(6, minmax\(0, 1fr\)\)/.test(desk), 'desktop: nav inferior 6 botones');
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
ok(/obj-vida-kicker::after[\s\S]{0,520}filter:\s*blur/.test(desk), 'desktop: subrayado vida difuminado');
ok(!/inicio-desktop > \.game-top \.control-audio/.test(desk), 'desktop: sin controles flotantes en cabecera');
ok(/obj-dia[\s\S]{0,500}height:\s*54px/.test(desk), 'desktop: pastilla dia altura fija');
ok(/obj-hora[\s\S]{0,500}height:\s*54px/.test(desk), 'desktop: pastilla hora altura fija');
ok(/top-reloj[\s\S]{0,200}align-items:\s*center/.test(desk), 'desktop: reloj alineado al centro');
ok(/obj-dia[\s\S]{0,700}filter:\s*none/.test(desk), 'desktop: pastilla dia sin drop-shadow');
ok(/obj-hora[\s\S]{0,700}filter:\s*none/.test(desk), 'desktop: pastilla hora sin drop-shadow');
ok(/pasar-rato[\s\S]{0,400}height:\s*auto/.test(desk), 'desktop: pasar-rato altura natural');
ok(/obj-dia-estacion[\s\S]{0,80}display:\s*none/.test(desk), 'desktop: sin Primavera en cabecera');

ok(/inicio-desktop-left \.obj-buzon[\s\S]{0,500}border-radius:\s*14px/.test(desk), 'desktop: mensajitos radio ref PNG');
ok(/inicio-desktop-left \.obj-buzon[\s\S]{0,500}min-height:\s*68px/.test(desk), 'desktop: mensajitos mas alto');
ok(/inicio-desktop-left \.obj-buzon[\s\S]{0,600}rgba\(232,\s*90,\s*120/.test(desk), 'desktop: mensajitos borde/sombra rosa');
ok(/inicio-desktop-left \.obj-buzon-txt[\s\S]{0,220}font-size:\s*\.98rem/.test(desk), 'desktop: mensajitos texto grande');
ok(/inicio-desktop-left \.obj-buzon-ico-wrap[\s\S]{0,420}background:\s*url\("data:image\/svg\+xml/.test(desk), 'desktop: mensajitos sobre outline svg');
ok(/inicio-desktop-left \.obj-buzon-img[\s\S]{0,120}display:\s*none/.test(desk), 'desktop: mensajitos sin png legacy');
ok(/inicio-desktop-left \.obj-buzon-badge[\s\S]{0,280}background:\s*#e85a78/.test(desk), 'desktop: mensajitos bolita rosa');
ok(/celestine-nota \.libreta-kicker[\s\S]{0,280}color:\s*#2a2218/.test(desk), 'desktop: celestine negro');
ok(/celestine-nota\.obj-vecinos-resumen::after[\s\S]{0,320}chincheta\.png/.test(desk), 'desktop: chincheta derecha');
ok(/obj-vecinos-preview-cara[\s\S]{0,120}width:\s*54px/.test(desk), 'desktop: caras vecinos grandes');
ok(/celeste-cuenta-vecinos[\s\S]{0,80}display:\s*none/.test(desk), 'desktop: sin fila en el pueblo');
ok(/celeste-necesitan-algo[\s\S]{0,500}border-top:\s*1px solid/.test(desk), 'desktop: necesitan apartado vecinos');
ok(/celeste-necesitan-algo[\s\S]{0,500}margin-left:\s*auto/.test(desk), 'desktop: necesitan numero alineado derecha');
ok(/obj-cotilleo-tit::before[\s\S]{0,80}content:\s*"# "/.test(desk), 'desktop: cotilleos hash titulo');
ok(/obj-cotilleo\.obj-cotilleo-par::after[\s\S]{0,600}opacity:\s*\.45/.test(desk), 'desktop: cotilleos corazon sutil');
ok(/obj-misiones-papel::before[\s\S]{0,220}grid-column:\s*1 \/ -1/.test(desk), 'desktop: misiones linea cabecera continua');
ok(/obj-misiones-papel-tit[\s\S]{0,260}border-bottom:\s*none/.test(desk), 'desktop: misiones titulo sin borde partido');
ok(/obj-misiones-papel-tit[\s\S]{0,400}font-size:\s*\.9rem/.test(desk), 'desktop: misiones titulo tamano vecinos');
ok(/obj-misiones-papel-meta[\s\S]{0,400}rgba\(200,\s*91,\s*120/.test(desk), 'desktop: misiones pendientes rosa');
ok(/inicio-planes-libreta-tit[\s\S]{0,200}font-size:\s*\.9rem/.test(desk), 'desktop: planes titulo tamano vecinos');
ok(/INICIO-PLANES-BLOQUE-CANON-20260906/.test(desk), 'desktop: planes bloque canon');
ok(/inicio-planes-agenda[\s\S]{0,200}border:\s*1\.5px dashed/.test(desk), 'desktop: agenda proximos dashed');
ok(!/INICIO-VISUAL-PASS3-20260906/.test(desk), 'desktop: sin PASS3 duplicado');
ok(/zona-tit-parejas[\s\S]{0,300}font-size:\s*\.9rem/.test(desk), 'desktop: parejas titulo tamano vecinos');

ok(!/:not\(:has\(\.inicio-stage\)\) \.game-top \.top-vida/.test(art), 'shell-art: sin top-vida legacy en cabecera');
ok(!/!important/.test(mob + desk), 'cabeceras: cero !important');

if (failures) {
  console.error('\n' + failures + ' fallo(s)');
  process.exit(1);
}
console.log('\ninicio_cabecera_ui_test OK');
