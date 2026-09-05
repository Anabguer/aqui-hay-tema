#!/usr/bin/env node
'use strict';
/**
 * Falla si archivos NO autorizados definen estructura en zonas Inicio.
 */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const stack = [
  'assets/css/play-v3.css',
  'assets/css/play-v3-app.css',
  'assets/css/design-system/screens/inicio-views.css',
  'assets/css/play-v3-shell-ui.css',
  'assets/css/play-v3-shell-art.css',
  'assets/css/play-v3-mapa-canonico.css',
  'assets/css/play-v3-desktop-shell.css',
  'assets/css/play-v3-responsive.css',
  'assets/css/inicio/tokens-inicio.css',
  'assets/css/inicio/inicio-base.css',
  'assets/css/inicio/inicio-mobile.css',
  'assets/css/inicio/inicio-desktop.css',
  'assets/css/inicio/inicio-mapa.css',
  'assets/css/inicio/inicio-cromatica-desktop.css',
  'assets/css/design-system/legibilidad-global.css',
];

const STRUCT_PROPS = [
  'display', 'grid-template-columns', 'grid-area', 'position', 'inset',
  'width', 'min-width', 'max-width', 'height', 'min-height', 'max-height',
  'padding', 'margin', 'overflow', 'flex', 'flex-direction', 'gap',
];
const STRUCT_RE = new RegExp('(?:^|[;{])\\s*(' + STRUCT_PROPS.join('|') + ')\\s*:', 'm');

const AUTHORITY = {
  mobile: {
    inicioStage: new Set(['assets/css/inicio/inicio-mobile.css']),
    headerMob: new Set(['assets/css/inicio/inicio-mobile.css']),
    mobTiles: new Set(['assets/css/inicio/inicio-mobile.css']),
    mapHost: new Set(['assets/css/inicio/inicio-mapa.css']),
    board: new Set(['assets/css/inicio/inicio-mapa.css']),
    nav: new Set(['assets/css/inicio/inicio-mobile.css']),
    controls: new Set(['assets/css/inicio/inicio-mobile.css']),
    lateralsMob: new Set(['assets/css/inicio/inicio-mobile.css', 'assets/css/inicio/inicio-base.css']),
  },
  desktop: {
    inicioStage: new Set(['assets/css/inicio/inicio-desktop.css']),
    headerDesk: new Set(['assets/css/inicio/inicio-desktop.css']),
    deskCols: new Set(['assets/css/inicio/inicio-desktop.css', 'assets/css/inicio/inicio-base.css']),
    mapHost: new Set(['assets/css/inicio/inicio-mapa.css', 'assets/css/inicio/inicio-desktop.css']),
    board: new Set(['assets/css/inicio/inicio-mapa.css']),
    nav: new Set(['assets/css/inicio/inicio-desktop.css']),
    lateralsDesk: new Set(['assets/css/inicio/inicio-base.css', 'assets/css/inicio/inicio-desktop.css']),
  },
};

const zones = {
  inicioStage: [/\.inicio-stage\b/],
  headerMob: [/\.inicio-mobile[^\{]*\.game-top|\.inicio-stage[^\{]*\.inicio-mobile[^\{]*\.game-top/],
  headerDesk: [/\.inicio-desktop[^\{]*\.game-top|\.inicio-stage[^\{]*\.inicio-desktop[^\{]*\.game-top/],
  deskCols: [/\.inicio-desktop-left|\.inicio-desktop-right|\.inicio-desktop-layout/],
  mobTiles: [/\.inicio-mobile-tiles|\.inicio-mobile-layout/],
  mapHost: [/\.inicio-map-host/],
  board: [/\.inicio-map-host[^\{]*\.board-scroll|\.inicio-map-host[^\{]*\.board-fit|\.inicio-stage[^\{]*\.board-scroll|\.inicio-stage[^\{]*\.board-fit/],
  lateralsMob: [/\.inicio-mobile-feed|\.encursos-movil|\.proxplanes-movil/],
  lateralsDesk: [/\.inicio-desktop-right/],
  nav: [/\.play-bottom-nav/],
  controls: [/\.control-audio/],
};

function parseRules(css, file) {
  const rules = [];
  let i = 0;
  const mediaStack = [];
  while (i < css.length) {
    if (css[i] === '/' && css[i + 1] === '*') {
      i = css.indexOf('*/', i + 2) + 2;
      continue;
    }
    const media = css.slice(i).match(/^@media\s*\([^)]+\)\s*\{/);
    if (media) {
      mediaStack.push(media[0]);
      i += media[0].length;
      continue;
    }
    if (css[i] === '}') {
      if (mediaStack.length) mediaStack.pop();
      i++;
      continue;
    }
    const brace = css.indexOf('{', i);
    if (brace === -1) break;
    const sel = css.slice(i, brace).trim();
    const end = css.indexOf('}', brace + 1);
    if (end === -1) break;
    const body = css.slice(brace + 1, end);
    if (sel && !sel.startsWith('@')) {
      rules.push({ file, sel, body, media: mediaStack.join(' ') || 'global' });
    }
    i = end + 1;
  }
  return rules;
}

const allRules = [];
stack.forEach((f) => {
  const p = path.join(root, f);
  if (!fs.existsSync(p)) return;
  parseRules(fs.readFileSync(p, 'utf8'), f).forEach((r) => allRules.push(r));
});

function viewportOf(rule) {
  const m = rule.media + ' ' + rule.sel;
  if (rule.file === 'assets/css/inicio/inicio-mobile.css' && !/min-width:\s*769/.test(m)) return 'mobile';
  if (rule.file === 'assets/css/inicio/inicio-desktop.css' || rule.file === 'assets/css/inicio/inicio-mapa.css') {
    if (/min-width:\s*769|is-inicio-view-active|inicio-desktop/.test(m)) return 'desktop';
  }
  if (/max-width:\s*768/.test(m)) return 'mobile';
  if (/min-width:\s*769/.test(m) || /inicio-desktop|is-inicio-view-active/.test(rule.sel)) return 'desktop';
  if (/inicio-mobile/.test(rule.sel) && !/inicio-desktop/.test(rule.sel)) return 'mobile';
  return 'both';
}

const failures = [];

for (const [view, zoneMap] of Object.entries(AUTHORITY)) {
  for (const [zone, allowed] of Object.entries(zoneMap)) {
    const patterns = zones[zone];
    const offenders = new Set();
    for (const r of allRules) {
      if (!patterns.some((re) => re.test(r.sel))) continue;
      if (/inicio-map-host\)/.test(r.sel) && /:not\(\.inicio-map-host\)/.test(r.sel)) continue;
      if (zone === 'inicioStage' && /encursos-movil|proxplanes-movil|shell-grupo|enc-int/.test(r.sel)) continue;
      const vp = viewportOf(r);
      if (vp !== view && vp !== 'both') continue;
      if (!STRUCT_RE.test(r.body)) continue;
      if (r.file === 'assets/css/inicio/inicio-cromatica-desktop.css') {
        failures.push(`${view}/${zone}: cromatica con estructura prohibida :: ${r.sel.slice(0, 72)}`);
        continue;
      }
      if (r.file === 'assets/css/inicio/inicio-responsive.css') continue;
      if (r.file === 'assets/css/design-system/legibilidad-global.css' &&
        /data-capa|data-consulta/.test(r.sel)) continue;
      if (r.file === 'assets/css/design-system/screens/inicio-views.css') continue;
      if (r.file === 'assets/css/inicio/inicio-mobile.css' && zone === 'mapHost' &&
        /^[\s\S]*grid-area\s*:[\s\S]*$/.test(r.body) && !/\b(width|height|padding|margin|position|inset|overflow|flex)\s*:/.test(r.body)) continue;
      if (r.file === 'assets/css/inicio/inicio-mapa.css' && zone === 'inicioStage') continue;
      if (allowed.has(r.file)) continue;
      offenders.add(r.file);
    }
    offenders.forEach((f) => failures.push(`${view}/${zone}: competidor no autorizado ${f}`));
  }
}

if (failures.length) {
  console.error('AUDIT INICIO CASCADE AUTHORITY — FALLOS:\n' + failures.map((f) => '  - ' + f).join('\n'));
  process.exit(1);
}
console.log('AUDIT INICIO CASCADE AUTHORITY — OK (una autoridad por zona)');
