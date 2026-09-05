#!/usr/bin/env node
'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

function patch(file, fn) {
  const p = path.join(root, file);
  fn(fs.readFileSync(p, 'utf8'), (c) => fs.writeFileSync(p, c));
}

// --- inicio-mobile: quitar estructura mapa (autoridad inicio-mapa) ---
patch('assets/css/inicio/inicio-mobile.css', (m) => {
  m = m.replace(/\n\.inicio-stage > \.inicio-map-host \{\s*min-height: 220px;\s*position: relative;\s*\}\s*/g, '\n');
  m = m.replace(/\n\.inicio-stage > \.inicio-map-host \.play-stage \{\s*min-height: 200px;\s*height: auto;\s*\}\s*/g, '\n');
  m = m.replace(/\n\.inicio-stage > \.inicio-map-host \.mapa-canonico,\s*\n\.inicio-stage > \.inicio-map-host \.mapa-canonico-bg \{\s*width: 100%;\s*height: auto;\s*display: block;\s*\}\s*/g, '\n');
  m = m.replace(/\n\.play-v3 \.inicio-stage > \.inicio-map-host \.board-fit \{\s*border:[^}]+\}\s*/g, '\n');
  return m;
});

// --- inicio-mapa: añadir reglas migradas + decoración marco mapa móvil ---
patch('assets/css/inicio/inicio-mapa.css', (m) => {
  if (m.includes('INICIO-MAPA-MOBILE-CANON')) return m;
  const extra = `
/* INICIO-MAPA-MOBILE-CANON */
@media (max-width: 768px) {
  .play-v3 .inicio-stage > .inicio-map-host {
    min-height: 220px;
  }

  .play-v3 .inicio-stage > .inicio-map-host .play-stage {
    min-height: 200px;
    height: auto;
  }

  .play-v3 .inicio-stage > .inicio-map-host .mapa-canonico,
  .play-v3 .inicio-stage > .inicio-map-host .mapa-canonico-bg {
    width: 100%;
    height: auto;
    display: block;
  }

  .play-v3 .inicio-stage > .inicio-map-host .board-fit {
    border: 2px solid rgba(107, 81, 56, .34);
    border-radius: 20px 18px 22px 16px / 18px 22px 16px 20px;
    overflow: hidden;
    box-shadow:
      1.5px 2.5px 0 rgba(51, 38, 30, .10),
      0 5px 14px rgba(70, 50, 30, .11);
    box-sizing: border-box;
  }
}
`;
  return m.trimEnd() + extra;
});

// --- inicio-views: sin width estructural en stage ---
patch('assets/css/design-system/screens/inicio-views.css', (v) =>
  v.replace(/\n\.inicio-stage \{\s*width: 100%;\s*box-sizing: border-box;\s*\}\s*/g, '\n')
);

// --- inicio-mobile: width stage en FIEL ---
patch('assets/css/inicio/inicio-mobile.css', (m) => {
  if (m.includes('INICIO-STAGE-WIDTH-MOB')) return m;
  return m.replace(
    /@media \(max-width: 768px\) \{\s*\n  \.play-v3 \.inicio-stage \.inicio-mobile \.control-audio/,
    `@media (max-width: 768px) {
  /* INICIO-STAGE-WIDTH-MOB */
  .play-v3 .inicio-stage {
    width: 100%;
    box-sizing: border-box;
  }

  .play-v3 .inicio-stage .inicio-mobile .control-audio`
  );
});

// --- desktop-shell: excluir inicio del mapa pc ---
patch('assets/css/play-v3-desktop-shell.css', (d) =>
  d.replace(
    /\.play-v3 \.play-root\.pc \.board-fit \{/g,
    '.play-v3 .game-map-wrap:not(.inicio-map-host) .play-root.pc .board-fit, .play-v3:not(:has(.inicio-map-host)) .play-root.pc .board-fit {'
  )
);

// --- shell-art: encursos fuera de inicio-stage ---
patch('assets/css/play-v3-shell-art.css', (s) => {
  return s.replace(
    /:is\(\.shell-grupo-planes, \.encursos-movil\)/g,
    ':is(.game-right .shell-grupo-planes, .game-right .encursos-movil)'
  );
});

// --- responsive: encursos fuera inicio si aplica ---
patch('assets/css/play-v3-responsive.css', (r) => {
  if (!r.includes('.encursos-movil') && !r.includes('.proxplanes-movil')) return r;
  return r.replace(/(\.encursos-movil|\.proxplanes-movil)/g, '.game-right $1');
});

console.log('patch_inicio_finalize OK');
