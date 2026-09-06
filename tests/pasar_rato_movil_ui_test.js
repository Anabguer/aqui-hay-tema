'use strict';
// Prueba UI móvil: los controles canónicos de avance (reloj + indicador día/noche +
// "Pasar el rato/noche") deben estar visibles en la cabecera móvil REUTILIZANDO
// los mismos nodos/handlers de desktop. Sin segunda lógica ni endpoints nuevos.
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const cssMob = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-mobile.css'), 'utf8');
const cssArt = fs.readFileSync(path.join(root, 'assets/css/play-v3-shell-art.css'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

// ── 1. Sistema único: mismos nodos, sin duplicados móviles ──
ok((php.match(/data-pasar-rato/g) || []).length >= 2, 'play.php: botones dual-view móvil+desktop (mismo contrato)');
ok((php.match(/data-es-noche/g) || []).length >= 2, 'play.php: indicador noche en cada vista');
ok(!/(pasar-rato-movil|data-pasar-rato-movil|es-noche-movil|avance-movil-btn)/.test(php + js + cssMob), 'sin segunda versión funcional móvil');
ok(/function pasarRatoBtns\(\)/.test(js) && /bindPasarRatoDelegacion/.test(js) && /relojAvanceRespuestaOk/.test(js), 'JS: delegacion unica y feedback coherente de avance');

// ── 2. Cabecera móvil FIEL: píldora temporal + solo ▶ ──
ok(/inicio-header-card/.test(php), 'play.php: tarjeta cabecera móvil');
ok(/inicio-temporal-pill/.test(php), 'play.php: píldora día/hora/avance');
ok(/INICIO-CABECERA-MOVIL-20260906/.test(cssMob), 'inicio-mobile: bloque cabecera canónico');
ok(/inicio-temporal-pill[\s\S]{0,220}flex-wrap:\s*nowrap/.test(cssMob), 'inicio-mobile: píldora en una línea');
ok(/pasar-rato-txt[\s\S]{0,100}display:\s*none/.test(cssMob), 'inicio-mobile: sin texto Pasar el rato');
ok(/inicio-temporal-pill \.pasar-rato[\s\S]{0,220}border-radius:\s*50%/.test(cssMob), 'inicio-mobile: botón ▶ circular');
ok(!/!important/.test(cssMob.match(/INICIO-CABECERA-MOVIL[\s\S]*/)?.[0] || ''), 'inicio-mobile cabecera: cero !important');

// ── 3. Desktop: indicador oculto (solo visible en mobile de noche) ──
ok(/\.es-noche\s*\{[^}]*display:\s*none/.test(cssArt), 'desktop: indicador oculto por defecto');
ok(/\.pasar-rato\s*\{[^}]*margin-bottom:\s*3px/.test(cssArt), 'desktop: estilo base del botón intacto');

// ── 4. Mobile: indicador en cabecera, solo de noche ──
ok(/inicio-temporal-pill[\s\S]{0,800}es-noche/.test(php), 'play.php: indicador en inicio-temporal-pill (mobile)');
ok(/inicio-temporal-pill \.es-noche[\s\S]{0,200}position:\s*absolute/.test(cssMob), 'mobile: indicador position absolute en pill');
ok(/\.es-noche\.is-noche[\s\S]{0,100}display:\s*inline-flex/.test(cssMob), 'mobile: indicador visible solo con .is-noche');

// ── 5. Arnés: la MISMA lógica única pinta ambos modos (día/noche) ──
(function () {
  const ini = js.indexOf('function aplicarNocheVisual');
  const fin = js.indexOf('\n  }', js.indexOf('function pintarModoReloj'));
  const codigo = js.slice(ini, fin + 4);

  function montar() {
    const clases = new Set();
    const indicador = {
      hidden: true,
      classList: {
        toggle(c, on) { if (on) clases.add(c); else clases.delete(c); },
        contains(c) { return clases.has(c); },
      },
    };
    let etiqueta = '';
    const btn = {
      classList: {
        toggle(c, on) { if (on) clases.add(c); else clases.delete(c); },
        contains(c) { return clases.has(c); },
      },
      title: '',
      querySelector(sel) {
        return sel === '.pasar-rato-txt' ? { set textContent(v) { etiqueta = v; }, get textContent() { return etiqueta; } } : null;
      },
    };
    const shell = { classList: { toggle(c, on) {}, contains() { return false; } } };
    const fn = new Function('$$', '$', 'document', codigo + '\n return pintarModoReloj;');
    const pintar = fn(
      (sel) => (sel === '[data-es-noche]' ? [indicador] : (sel === '[data-pasar-rato]' ? [btn] : [])),
      (sel) => (sel === '[data-pasar-rato]' ? btn : null),
      { querySelector: () => shell }
    );
    return { pintar, indicador, get etiqueta() { return etiqueta; }, clases };
  }

  // A) 14:00 · día — indicador oculto (hidden=false pero sin .is-noche)
  let ctx = montar();
  ctx.pintar(false);
  ok(ctx.indicador.hidden === false, 'móvil A 14:00: indicador hidden=false');
  ok(ctx.clases.has('is-dia'), 'móvil A 14:00: clase is-dia activa');
  ok(!ctx.clases.has('is-noche'), 'móvil A 14:00: sin is-noche');
  ok(ctx.etiqueta === 'Pasar el rato', 'móvil A 14:00: botón "Pasar el rato"');
  // B) 22:00 · todavía día
  ctx.pintar(false);
  ok(ctx.clases.has('is-dia') && ctx.etiqueta === 'Pasar el rato', 'móvil B 22:00: sigue día');
  // C) 22→23: el mismo sistema cambia a noche
  ctx.pintar(true);
  ok(ctx.indicador.hidden === false, 'móvil D 23:00: indicador hidden=false');
  ok(ctx.clases.has('is-noche'), 'móvil D 23:00: clase is-noche activa');
  ok(!ctx.clases.has('is-dia'), 'móvil D 23:00: sin is-dia');
  ok(ctx.etiqueta === 'Pasar la noche', 'móvil D 23:00: botón "Pasar la noche"');
  // F) 08:00 · vuelve el día
  ctx.pintar(false);
  ok(ctx.clases.has('is-dia') && ctx.etiqueta === 'Pasar el rato', 'móvil F 08:00: vuelve "Pasar el rato"');
  // Refresh directo a 23:00 (idempotente)
  let ctxN = montar();
  ctxN.pintar(true);
  ctxN.pintar(true);
  ok(ctxN.clases.has('is-noche') && ctxN.etiqueta === 'Pasar la noche', 'refresh directo 23:00: controles correctos');
  // Refresh directo a 14:00 (idempotente)
  let ctxD = montar();
  ctxD.pintar(false);
  ctxD.pintar(false);
  ok(ctxD.clases.has('is-dia') && ctxD.etiqueta === 'Pasar el rato', 'refresh directo 14:00: controles correctos');
})();


// ── 5. Feedback: no toast si el reloj avanzo aunque r.ok sea falso ──
(function () {
  const ini = js.indexOf('function relojAbsDesdeEstado');
  const fin = js.indexOf('function pintarModoReloj', ini);
  const codigo = js.slice(ini, fin);
  const fn = new Function('cacheEstado', codigo + '\n return { relojAvanceRespuestaOk, relojAbsDesdeEstado, relojAbsAvanzo };');
  const api = fn({ reloj: { dia_pueblo: 10, hora_actual: 16 } });
  ok(api.relojAvanceRespuestaOk({ ok: true }), 'relojAvanceRespuestaOk: ok top-level');
  ok(api.relojAvanceRespuestaOk({ reloj: { ok: true } }), 'relojAvanceRespuestaOk: ok anidado');
  ok(!api.relojAvanceRespuestaOk({ ok: false }), 'relojAvanceRespuestaOk: rechaza ok false');
  ok(api.relojAbsAvanzo(10 * 24 + 16, 10 * 24 + 17), 'relojAbsAvanzo: +1h');
  ok(!api.relojAbsAvanzo(10 * 24 + 16, 10 * 24 + 16), 'relojAbsAvanzo: sin cambio');
  const debeToast = !api.relojAvanceRespuestaOk({ ok: false }) && !api.relojAbsAvanzo(10 * 24 + 16, 10 * 24 + 16);
  ok(debeToast, 'relojAvanceRespuestaOk en arnes: toast solo si no avanzo y respuesta ko');
  const showToastTrasAvance = !api.relojAvanceRespuestaOk({ ok: false }) && !api.relojAbsAvanzo(10 * 24 + 16, 10 * 24 + 17);
  ok(!showToastTrasAvance, 'relojAvanceRespuestaOk en arnes: sin toast si avanzo pese a ok false');
})();

console.log(failures === 0 ? '\nTODO OK\n' : '\nFALLOS: ' + failures + '\n');
process.exit(failures === 0 ? 0 : 1);
