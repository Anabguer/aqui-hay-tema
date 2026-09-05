#!/usr/bin/env node
'use strict';
/**
 * Elimina autoridades pre-FIEL / CABECERA-GUIA que compiten con INICIO-FIEL en inicio-mobile.css
 */
const fs = require('fs');
const path = require('path');

const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-mobile.css');
let css = fs.readFileSync(file, 'utf8');

function drop(re, label) {
  const next = css.replace(re, '\n');
  if (next === css) {
    console.error('SKIP (no match):', label);
    return;
  }
  css = next;
  console.log('OK:', label);
}

drop(
  /\.inicio-stage \.inicio-mobile \.control-audio\s*\{[\s\S]*?\}\s*\n/,
  'control-audio fixed flotante'
);

drop(
  /\.inicio-stage \.inicio-mobile \.game-top\s*\{[\s\S]*?padding: 10px 13px 0;\s*\}\s*\n[\s\S]*?\.inicio-mobile \.top-vida \.obj-vida-kicker\s*\{\s*display: none;\s*\}\s*\n/,
  'cabecera pre-FIEL grid (game-top legacy)'
);

drop(
  /\.play-v3 \.inicio-mobile:not\(\.inicio-mobile-feed\) \.game-top\s*\{[\s\S]*?filter: drop-shadow\(1px 3px 4px rgba\(70, 50, 30, \.22\)\);\s*\}\s*\n\}\s*\n/,
  'bloque global game-top v47 (compite con FIEL)'
);

drop(
  /\/\* INICIO-CABECERA-GUIA-v48[\s\S]*?@media \(max-width: 768px\) \{\s*\.play-v3 \.inicio-mobile:not\(\.inicio-mobile-feed\) \.top-reloj \{\s*grid-area: rato;[\s\S]*?\}\s*\}\s*\n/,
  'INICIO-CABECERA-GUIA-v48 @media'
);

if (!/\.inicio-stage \.inicio-mobile \.control-audio/.test(css)) {
  const marker = '/* INICIO-FIEL-PANTALLAZOS-20260906 */';
  const insert =
    '\n  .play-v3 .inicio-stage .inicio-mobile .control-audio {\n' +
    '    display: none;\n' +
    '  }\n\n' +
    '  .play-v3 .inicio-stage > .inicio-map-host .board-fit {\n' +
    '    width: 100%;\n' +
    '  }\n\n' +
    '  .play-v3 .inicio-stage .play-root.phone .board-scroll {\n' +
    '    inset: 0;\n' +
    '  }\n\n';
  if (!css.includes(marker)) {
    console.error('FAIL: marcador FIEL no encontrado');
    process.exit(1);
  }
  css = css.replace(marker, marker + insert);
  console.log('OK: reglas FIEL mapa + ocultar control-audio');
}

fs.writeFileSync(file, css);
console.log('inicio-mobile.css actualizado');
