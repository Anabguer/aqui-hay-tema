'use strict';
// Prueba UI móvil: los controles canónicos de avance (reloj + "Es de noche" +
// "Pasar el rato/noche") deben estar visibles en la cabecera móvil REUTILIZANDO
// los mismos nodos/handlers de desktop. Sin segunda lógica ni endpoints nuevos.
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
const js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');
const cssResp = fs.readFileSync(path.join(root, 'assets/css/play-v3-responsive.css'), 'utf8');
const cssArt = fs.readFileSync(path.join(root, 'assets/css/play-v3-shell-art.css'), 'utf8');

let failures = 0;
function ok(c, m) {
  console.log((c ? 'OK' : 'FAIL') + ': ' + m);
  if (!c) failures++;
}

// ── 1. Sistema único: mismos nodos, sin duplicados móviles ──
ok((php.match(/data-pasar-rato/g) || []).length === 1, 'play.php: UN solo botón de avance (sin clon móvil)');
ok((php.match(/data-es-noche/g) || []).length === 1, 'play.php: UN solo indicador de noche');
ok(!/(pasar-rato-movil|data-pasar-rato-movil|es-noche-movil|avance-movil-btn)/.test(php + js + cssResp), 'sin segunda versión funcional móvil');
ok((js.match(/\[data-pasar-rato\]/g) || []).length >= 1 && !/getElementById\('btnPasar/.test(js), 'JS: handlers sobre el nodo canónico');

// ── 2. La cabecera móvil ya NO oculta .top-center ──
ok(!/\.play-v3:has\(\.game-shell\) \.top-center\s*\{[^}]*display:\s*none/.test(cssResp), 'responsive: .top-center ya no display:none en móvil');
  ok(/\.play-v3:has\(\.game-shell\) \.top-center\s*\{\s*display:\s*contents !important;\s*\}/.test(cssResp), 'responsive: .top-center cuelga del grid vía display:contents');
  ok(/"brand guia vida"\s*\n\s*"meta avance avance"/.test(cssResp), 'responsive: fila única meta+avance bajo brand/vida');
  ok(/\.play-v3:has\(\.game-shell\) \.brand-col\s*\{\s*display:\s*contents !important;\s*\}/.test(cssResp), 'responsive: brand-col contents (¿Cómo va esto? y meta al grid)');
  const tail30 = cssResp.slice(cssResp.lastIndexOf('batch 30'));
  ok(/\.top-meta-line\s*\{[^}]*white-space:\s*nowrap !important;/.test(tail30) && /\.top-meta-prim,[^}]*display:\s*inline !important;/.test(tail30), 'batch30: día/fecha + hora en una sola línea');
  ok(/\.top-reloj \.es-noche-txt\s*\{\s*display:\s*none !important;/.test(tail30), 'batch30: móvil sin texto "Es de noche", solo luna');
  ok(/\.top-reloj \.es-noche\s*\{[^}]*background:\s*none !important;/.test(tail30), 'batch30: luna sin tarjeta');
  const dsInicio = fs.readFileSync(path.join(root, 'assets/css/design-system/screens/inicio.css'), 'utf8');
  ok(!/\.top-reloj \.pasar-rato\s*\{[^}]*border-radius:[^;}]*!important/.test(cssResp) &&
     /\.top-reloj \.pasar-rato\s*\{[^}]*min-height:\s*44px/.test(dsInicio) &&
     /transform:\s*rotate\(-1\.2deg\) scale\(\.97\)/.test(dsInicio),
    'ds-piloto: pasar-rato piel en el DS (pill lavanda 44px, tilt+press); responsive sin border-radius !important');
  ok(/\.play-v3:has\(\.game-shell\) \.top-reloj\s*\{[^}]*flex-wrap:\s*nowrap !important;/.test(tail30), 'batch30: fila de avance sin wrap');
ok(/\.play-v3:has\(\.game-shell\) \.top-reloj\s*\{[^}]*grid-area:\s*avance !important;[^}]*display:\s*flex !important;/.test(cssResp), 'responsive: .top-reloj anclado a la fila avance');
ok(!/\.play-v3:has\(\.game-shell\) \.top-reloj \.obj-dia[^}]*}\s*[^~]*display:\s*flex/.test(cssResp.split('.obj-dia,')[1] || ''), 'sanity: objetos papel ocultos por regla dedicada');
ok(/\.play-v3:has\(\.game-shell\) \.top-reloj \.obj-dia,\s*\n\s*\.play-v3:has\(\.game-shell\) \.top-reloj \.obj-hora\s*\{[^}]*display:\s*none !important;/.test(cssResp), 'responsive: obj-dia/obj-hora ocultos (meta-line evita duplicado)');
ok(/\.play-v3:has\(\.game-shell\) \.top-reloj \.es-noche\s*\{[^}]*position:\s*static/.test(cssResp), 'responsive: "Es de noche" en flujo junto al botón');
ok(/\.play-v3:has\(\.game-shell\) \.top-reloj \.es-noche\[hidden\]\s*\{[^}]*display:\s*none !important;/.test(cssResp), 'responsive: hidden sigue ganando de día');

// Las reglas nuevas viven DENTRO del media query móvil (desktop intacto)
{
  const idx = cssResp.indexOf('.play-v3:has(.game-shell) .top-center');
  const media = cssResp.lastIndexOf('@media', idx);
  ok(media !== -1 && /\(max-width:\s*768px\)/.test(cssResp.slice(media, idx)), 'responsive: reglas nuevas dentro de @media max-width:768px');
}

// ── 3. Desktop intacto ──
ok(/\.es-noche\s*\{[^}]*position:\s*absolute/.test(cssArt), 'desktop: chip absoluto bajo el reloj intacto');
ok(/\.es-noche\s*\{[^}]*top:\s*100%/.test(cssArt), 'desktop: chip colgando (top:100%) intacto');
ok(/\.es-noche\s*\{[^}]*pointer-events:\s*none/.test(cssArt), 'desktop: pointer-events none intacto');
ok(/\.pasar-rato\s*\{[^}]*margin-bottom:\s*3px/.test(cssArt), 'desktop: estilo base del botón intacto');

// ── 4. Arnés: la MISMA lógica única pinta ambos modos (día/noche) ──
(function () {
  const ini = js.indexOf('function aplicarNocheVisual');
  const fin = js.indexOf('\n  }', js.indexOf('function pintarModoReloj'));
  const codigo = js.slice(ini, fin + 4);

  function montar() {
    const indicador = { hidden: true };
    let etiqueta = '';
    const clases = new Set();
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
      (sel) => (sel === '[data-es-noche]' ? [indicador] : []),
      (sel) => (sel === '[data-pasar-rato]' ? btn : null),
      { querySelector: () => shell }
    );
    return { pintar, indicador, get etiqueta() { return etiqueta; }, clases };
  }

  // A) 14:00 · día
  let ctx = montar();
  ctx.pintar(false);
  ok(ctx.indicador.hidden === true, 'móvil A 14:00: "Es de noche" oculto');
  ok(ctx.etiqueta === 'Pasar el rato', 'móvil A 14:00: botón "Pasar el rato"');
  // B) 22:00 · todavía día
  ctx.pintar(false);
  ok(ctx.indicador.hidden === true && ctx.etiqueta === 'Pasar el rato', 'móvil B 22:00: sigue día, sin "Es de noche"');
  // C) 22→23: el mismo sistema cambia a noche
  ctx.pintar(true);
  ok(ctx.indicador.hidden === false, 'móvil D 23:00: "Es de noche" visible');
  ok(ctx.etiqueta === 'Pasar la noche', 'móvil D 23:00: botón "Pasar la noche"');
  // F) 08:00 · vuelve el día
  ctx.pintar(false);
  ok(ctx.indicador.hidden === true && ctx.etiqueta === 'Pasar el rato', 'móvil F 08:00: vuelve "Pasar el rato"');
  // Refresh directo a 23:00 (idempotente)
  let ctxN = montar();
  ctxN.pintar(true);
  ctxN.pintar(true);
  ok(ctxN.indicador.hidden === false && ctxN.etiqueta === 'Pasar la noche', 'refresh directo 23:00: controles correctos');
  // Refresh directo a 14:00 (idempotente)
  let ctxD = montar();
  ctxD.pintar(false);
  ctxD.pintar(false);
  ok(ctxD.indicador.hidden === true && ctxD.etiqueta === 'Pasar el rato', 'refresh directo 14:00: controles correctos');
})();

console.log(failures === 0 ? '\nTODO OK\n' : '\nFALLOS: ' + failures + '\n');
process.exit(failures === 0 ? 0 : 1);
