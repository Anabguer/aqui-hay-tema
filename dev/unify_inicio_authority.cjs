#!/usr/bin/env node
'use strict';
/**
 * Unificación autoridad Inicio — una hoja por zona estructural.
 * Uso: node dev/unify_inicio_authority.cjs
 */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

const STRUCTURAL = new Set([
  'display', 'grid-template-columns', 'grid-template-rows', 'grid-template-areas', 'grid-area',
  'grid-column', 'grid-row', 'flex', 'flex-direction', 'flex-wrap', 'flex-grow', 'flex-shrink', 'flex-basis',
  'align-items', 'align-self', 'align-content', 'justify-content', 'justify-self', 'justify-items',
  'gap', 'row-gap', 'column-gap', 'place-items', 'place-content', 'place-self',
  'position', 'inset', 'top', 'right', 'bottom', 'left',
  'width', 'min-width', 'max-width', 'height', 'min-height', 'max-height',
  'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
  'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
  'overflow', 'overflow-x', 'overflow-y', 'box-sizing', 'order', 'contain',
  'aspect-ratio', 'object-fit', 'object-position',
]);

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

function write(rel, content) {
  fs.writeFileSync(path.join(root, rel), content, 'utf8');
}

function stripStructuralFromBlock(body) {
  const decls = body.split(';').map((d) => d.trim()).filter(Boolean);
  const kept = [];
  for (const d of decls) {
    const prop = d.split(':')[0].trim().toLowerCase();
    if (STRUCTURAL.has(prop)) continue;
    kept.push(d);
  }
  return kept.length ? kept.join(';\n  ') + (kept.length ? ';' : '') : '';
}

function processCssStripStructural(css) {
  let out = '';
  let i = 0;
  const mediaStack = [];
  while (i < css.length) {
    if (css[i] === '/' && css[i + 1] === '*') {
      const end = css.indexOf('*/', i + 2);
      out += css.slice(i, end + 2);
      i = end + 2;
      continue;
    }
    const media = css.slice(i).match(/^@media\s*\([^)]+\)\s*\{/);
    if (media) {
      out += media[0];
      mediaStack.push(media[0]);
      i += media[0].length;
      continue;
    }
    if (css[i] === '}') {
      out += '}';
      if (mediaStack.length) mediaStack.pop();
      i++;
      continue;
    }
    const brace = css.indexOf('{', i);
    if (brace === -1) {
      out += css.slice(i);
      break;
    }
    const sel = css.slice(i, brace).trim();
    const end = css.indexOf('}', brace + 1);
    if (end === -1) break;
    const body = css.slice(brace + 1, end);
    i = end + 1;
    if (sel.startsWith('@')) {
      out += sel + '{' + body + '}';
      continue;
    }
    const newBody = stripStructuralFromBlock(body);
    if (newBody.trim()) {
      out += sel + ' {\n  ' + newBody + '\n}\n\n';
    }
  }
  return out.replace(/\n{3,}/g, '\n\n').trim() + '\n';
}

// --- 1. inicio-mapa.css (autoridad mapa) ---
const mapaCss = `/* INICIO-MAPA — autoridad única estructural del mapa en Inicio (.inicio-map-host) */

@media (max-width: 768px) {
  .play-v3 .inicio-stage > .inicio-map-host {
    width: 100%;
    min-width: 0;
    padding: 0 13px 10px;
    box-sizing: border-box;
    position: relative;
    overflow: visible;
  }

  .play-v3 .inicio-stage > .inicio-map-host .play-stage {
    min-height: 0;
    position: relative;
  }

  .play-v3 .inicio-stage > .inicio-map-host .board-fit {
    width: 100%;
    position: relative;
    overflow: hidden;
  }

  .play-v3 .inicio-stage > .inicio-map-host .board-scroll {
    position: static;
    inset: 0;
    overflow: hidden;
  }

  .play-v3 .inicio-stage .inicio-map-host .play-root.phone .board-scroll {
    inset: 0;
    overflow: hidden;
  }
}

@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host {
    width: 100%;
    min-width: 0;
    align-self: start;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 0;
    position: relative;
    overflow: visible;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .play-stage {
    flex: 1 1 auto;
    height: 100%;
    min-height: 0;
    overflow: hidden;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .play-root.pc {
    height: 100%;
    min-height: 0;
    overflow: hidden;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .board-scroll {
    position: absolute;
    inset: 0;
    width: 100%;
    height: auto;
    overflow: hidden;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .board-fit {
    width: 100%;
    max-height: min(76vh, 660px);
    position: relative;
    overflow: hidden;
  }
}
`;
write('assets/css/inicio/inicio-mapa.css', mapaCss);

// --- 2. Merge responsive desktop grid + nav into inicio-desktop.css ---
const responsive = read('assets/css/inicio/inicio-responsive.css');
const deskGridMatch = responsive.match(/@media \(min-width: 769px\) \{\s*\/\* INICIO-FIEL[\s\S]*?\n\}/);
const deskGrid = deskGridMatch ? deskGridMatch[0] : '';

let desktop = read('assets/css/inicio/inicio-desktop.css');
// Remove duplicate .inicio-desktop-layout grid (stage grid is authority now)
desktop = desktop.replace(
  /\.inicio-desktop \.inicio-desktop-layout\s*\{[^}]+\}\s*/,
  '/* layout columnas: ver grid en .inicio-stage (inicio-desktop.css @media 769) */\n\n'
);

const deskAuthority = `/* INICIO-DESKTOP-LAYOUT — autoridad única layout desktop */

${deskGrid}

@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top {
    display: grid;
    grid-template-columns: auto 1fr minmax(240px, 300px);
    align-items: center;
    gap: 0.75rem 1.25rem;
    padding: 7px 0 9px;
    box-sizing: border-box;
    position: relative;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav {
    display: flex;
    position: static;
    width: 100%;
    max-width: 100%;
    margin-top: 2px;
    align-self: end;
  }
}
`;

if (!desktop.includes('INICIO-DESKTOP-LAYOUT')) {
  desktop = desktop.trimEnd() + '\n\n' + deskAuthority;
}
write('assets/css/inicio/inicio-desktop.css', desktop);

// --- 3. Move mobile event block from responsive to inicio-mobile ---
const mobEventEnd = responsive.indexOf('/* INICIO-FIEL-PANTALLAZOS-20260906 */');
const mobEvent = mobEventEnd > 0 ? responsive.slice(0, mobEventEnd).trim() : '';
let mobile = read('assets/css/inicio/inicio-mobile.css');
if (mobEvent && !mobile.includes('INICIO-EVENTO-POBLLO-MOBILE')) {
  mobile = mobile.trimEnd() + '\n\n/* Migrado desde inicio-responsive — evento pueblo móvil */\n' + mobEvent + '\n';
}

// Remove map rules from FIEL mobile (now inicio-mapa)
mobile = mobile.replace(
  /\n  \.play-v3 \.inicio-stage > \.inicio-map-host \.board-fit \{[^}]+\}\n/,
  '\n'
);
mobile = mobile.replace(
  /\n  \.play-v3 \.inicio-stage \.play-root\.phone \.board-scroll \{[^}]+\}\n/,
  '\n'
);
mobile = mobile.replace(
  /\n  \.play-v3 \.inicio-stage > \.inicio-map-host \{\n    grid-area: map;\n    width: 100%;\n    min-width: 0;\n  \}\n/,
  '\n  .play-v3 .inicio-stage > .inicio-map-host {\n    grid-area: map;\n  }\n'
);
mobile = mobile.replace(
  /\n  \.play-v3 \.inicio-stage > \.inicio-map-host \{\n    padding: 0 13px 10px;\n    box-sizing: border-box;\n  \}\n/,
  '\n'
);
write('assets/css/inicio/inicio-mobile.css', mobile);

// --- 4. Gut inicio-responsive (replaced by desktop + mobile + mapa) ---
write('assets/css/inicio/inicio-responsive.css', `/* inicio-responsive — retirado: layout en inicio-mobile / inicio-desktop / inicio-mapa */
`);

// --- 5. Strip structural from cromatica ---
const cromaPath = 'assets/css/inicio/inicio-cromatica-desktop.css';
const cromaHeader = read(cromaPath).split('\n').slice(0, 8).join('\n');
const cromaBody = read(cromaPath);
const cromaStripped = processCssStripStructural(cromaBody);
write(cromaPath, cromaHeader + '\n\n/* Estructura retirada — solo cromática/decoración. Layout: inicio-mobile | inicio-desktop | inicio-mapa */\n\n' + cromaStripped);

// --- 6. Legacy shell: no competir con inicio-stage ---
let shellUi = read('assets/css/play-v3-shell-ui.css');
shellUi = shellUi.replace(
  /\.game-top \{\s*display: grid;[^}]+\}\s*/,
  '/* .game-top layout: autoridad inicio-mobile / inicio-desktop (no shell-ui) */\n\n'
);
write('assets/css/play-v3-shell-ui.css', shellUi);

let app = read('assets/css/play-v3-app.css');
app = app.replace(
  /\.play-v3 \.play-root\.phone \.board-scroll \{ inset: 0; overflow: hidden; \}/,
  '.play-v3 .game-map-wrap:not(.inicio-map-host) .play-root.phone .board-scroll { inset: 0; overflow: hidden; }'
);
if (!/\.play-root \.dock/.test(app)) {
  app = app.replace(
    /\.play-root\.phone \.dock/,
    '.play-root .dock'
  );
}
write('assets/css/play-v3-app.css', app);

let mapCanon = read('assets/css/play-v3-mapa-canonico.css');
mapCanon = mapCanon.replace(
  /\.play-v3 \.board-fit \{/g,
  '.play-v3 .game-map-wrap:not(.inicio-map-host) .board-fit, .play-v3:not(:has(.inicio-map-host)) .board-fit {'
);
mapCanon = mapCanon.replace(
  /\.play-v3 \.play-root\.phone \.board-fit \{/g,
  '.play-v3 .inicio-map-host .play-root.phone .board-fit, .play-v3 .game-map-wrap:not(.inicio-map-host) .play-root.phone .board-fit {'
);
// Revert bad replacement - inicio should NOT get mapa-canonico phone rules
mapCanon = read('assets/css/play-v3-mapa-canonico.css');
mapCanon = mapCanon.replace(
  /\.play-v3 \.board-fit \{/,
  '.play-v3 .game-map-wrap:not(.inicio-map-host) .board-fit {'
);
mapCanon = mapCanon.replace(
  /\.play-v3 \.play-root\.phone \.board-fit \{/,
  '.play-v3 .game-map-wrap:not(.inicio-map-host) .play-root.phone .board-fit {'
);
write('assets/css/play-v3-mapa-canonico.css', mapCanon);

let deskShell = read('assets/css/play-v3-desktop-shell.css');
deskShell = deskShell.replace(/\.play-v3 \.game-map-wrap /g, '.play-v3 .game-map-wrap:not(.inicio-map-host) ');
write('assets/css/play-v3-desktop-shell.css', deskShell);

// --- 7. play.php: add inicio-mapa, drop inicio-responsive ---
let php = read('play.php');
if (!php.includes('inicio/inicio-mapa.css')) {
  php = php.replace(
    /(<link rel="stylesheet" href="assets\/css\/inicio\/inicio-desktop\.css[^"]+")\/>/,
    '$1/>\n  <link rel="stylesheet" href="assets/css/inicio/inicio-mapa.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, \'UTF-8\') ?>"/>'
  );
}
php = php.replace(/\s*<link rel="stylesheet" href="assets\/css\/inicio\/inicio-responsive\.css[^"]+"\/>\n?/g, '\n');
write('play.php', php);

console.log('unify_inicio_authority.cjs — OK');
