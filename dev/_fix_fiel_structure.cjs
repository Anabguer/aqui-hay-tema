#!/usr/bin/env node
'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-mobile.css');
let css = fs.readFileSync(file, 'utf8');

css = css.replace(
  /\/\* INICIO-CABECERA-GUIA-v48[\s\S]*?@media \(max-width: 768px\) \{\s*\.play-v3 \.inicio-mobile:not\(\.inicio-mobile-feed\) \.game-top \{[\s\S]*?\.play-v3 \.inicio-mobile:not\(\.inicio-mobile-feed\) \.top-reloj \{[\s\S]*?\}\s*\}\s*\n/,
  ''
);
if (css.includes('INICIO-CABECERA-GUIA')) {
  console.error('FAIL: CABECERA sigue presente');
  process.exit(1);
}
console.log('OK: CABECERA eliminado');

const loose = `/* INICIO-FIEL-PANTALLAZOS-20260906 */
  .play-v3 .inicio-stage .inicio-mobile .control-audio {
    display: none;
  }

  .play-v3 .inicio-stage > .inicio-map-host .board-fit {
    width: 100%;
  }

  .play-v3 .inicio-stage .play-root.phone .board-scroll {
    inset: 0;
  }



@media (max-width: 768px) {`;

const fixed = `/* INICIO-FIEL-PANTALLAZOS-20260906 */

@media (max-width: 768px) {
  .play-v3 .inicio-stage .inicio-mobile .control-audio {
    display: none;
  }

  .play-v3 .inicio-stage > .inicio-map-host .board-fit {
    width: 100%;
  }

  .play-v3 .inicio-stage .play-root.phone .board-scroll {
    inset: 0;
  }
`;

if (css.includes(loose)) {
  css = css.replace(loose, fixed);
  console.log('OK: reglas FIEL dentro de @media');
} else if (css.includes('.play-v3 .inicio-stage .inicio-mobile .control-audio') &&
  /@media \(max-width: 768px\) \{\s*\n\s*\.play-v3 \.inicio-stage \.inicio-mobile \.control-audio/.test(css)) {
  console.log('OK: FIEL ya correcto');
} else {
  console.error('FAIL: estructura FIEL inesperada');
  process.exit(1);
}

fs.writeFileSync(file, css);
