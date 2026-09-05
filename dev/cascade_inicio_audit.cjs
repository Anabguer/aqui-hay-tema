#!/usr/bin/env node
'use strict';
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
  'assets/css/design-system/typography-reading.css',
];

const STRUCT = [
  'width', 'max-width', 'min-width', 'margin', 'margin-left', 'margin-right', 'padding',
  'display', 'grid-template-columns', 'grid-template-areas', 'grid-area', 'flex', 'flex-direction',
  'position', 'inset', 'top', 'bottom', 'left', 'right', 'transform',
  'height', 'min-height', 'max-height', 'overflow', 'overflow-x', 'overflow-y', 'background', 'background-color',
];

const zones = {
  body: [/body\.play-v3\b/, /html:has\(body\.play-v3/],
  gameShell: [/\.game-shell\b/],
  inicioStage: [/\.inicio-stage\b/],
  headerMob: [/\.inicio-mobile[^\{]*\.game-top|\.inicio-stage[^\{]*\.inicio-mobile[^\{]*\.game-top|\.inicio-header/],
  headerDesk: [/\.inicio-desktop[^\{]*\.game-top|\.inicio-stage[^\{]*\.inicio-desktop[^\{]*\.game-top/],
  deskCols: [/\.inicio-desktop-left|\.inicio-desktop-right|\.inicio-desktop-layout/],
  mobTiles: [/\.inicio-mobile-tiles|\.inicio-mobile-layout/],
  mapHost: [/\.inicio-map-host|\.game-map-wrap/],
  board: [/\.board-scroll|\.board-fit|\.play-stage/],
  lateralsMob: [/\.inicio-mobile-feed|\.inicio-chrome-right|\.encursos-movil|\.proxplanes-movil/],
  lateralsDesk: [/\.inicio-desktop-right|\.inicio-chrome-right/],
  nav: [/\.play-bottom-nav|\.dock\b/],
  controls: [/\.control-audio|\.control-musica|\.control-efectos/],
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
      mediaStack.push(media[0].replace(/\s*\{$/, ''));
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
      rules.push({ file, sel: sel.replace(/\s+/g, ' '), body, media: mediaStack.join(' | ') || 'global' });
    }
    i = end + 1;
  }
  return rules;
}

function spec(sel) {
  const s = sel.split(',').map((x) => x.trim());
  return Math.max(...s.map((one) => {
    let n = 0;
    n += (one.match(/#/g) || []).length * 100;
    n += (one.match(/\./g) || []).length * 10;
    n += (one.match(/:/g) || []).length * 5;
    n += (one.match(/\[/g) || []).length * 5;
    if (one.includes(':has')) n += 25;
    return n;
  }));
}

function classify(rule, zoneKey) {
  const m = rule.media;
  const deskSel = /inicio-desktop|is-inicio-view-active|min-width:\s*769/.test(rule.sel + ' ' + m);
  const mobSel = /inicio-mobile|max-width:\s*768/.test(rule.sel + ' ' + m);
  if (/max-width:\s*768/.test(m) || (mobSel && !deskSel)) return 'mobile';
  if (/min-width:\s*769/.test(m) || (deskSel && !mobSel)) return 'desktop';
  return 'both';
}

const allRules = [];
stack.forEach((f, order) => {
  const p = path.join(root, f);
  if (!fs.existsSync(p)) return;
  parseRules(fs.readFileSync(p, 'utf8'), f).forEach((r) => {
    r.order = order;
    r.spec = spec(r.sel);
    allRules.push(r);
  });
});

function analyze(zoneKey, patterns) {
  const hits = allRules.filter((r) => patterns.some((re) => re.test(r.sel)));
  const byProp = {};
  for (const prop of STRUCT) {
    const competing = hits.filter((r) => new RegExp('\\b' + prop + '\\s*:').test(r.body));
    if (!competing.length) continue;
    const winner = competing[competing.length - 1];
    const overridden = competing.length > 1 ? competing.slice(0, -1).map((r) => r.file + ' :: ' + r.sel.slice(0, 80)) : [];
    byProp[prop] = {
      count: competing.length,
      winner: winner.file + ' | ' + winner.media + ' | ' + winner.sel.slice(0, 100),
      winnerVal: (winner.body.match(new RegExp('\\b' + prop + '\\s*:[^;]+')) || [''])[0],
      overridden: overridden.slice(-3),
    };
  }
  const files = [...new Set(hits.map((h) => h.file))];
  const gens = {
    legacyShell: hits.filter((h) => /play-v3-(app|shell|mapa|desktop-shell)|play-v3\.css/.test(h.file)).length,
    inicioStack: hits.filter((h) => /inicio\//.test(h.file)).length,
    views: hits.filter((h) => /inicio-views/.test(h.file)).length,
    cromatica: hits.filter((h) => /cromatica/.test(h.file)).length,
  };
  return { zoneKey, rules: hits.length, files, gens, byProp };
}

const mobileZones = ['body', 'gameShell', 'inicioStage', 'headerMob', 'mobTiles', 'mapHost', 'board', 'lateralsMob', 'nav', 'controls'];
const desktopZones = ['body', 'gameShell', 'inicioStage', 'headerDesk', 'deskCols', 'mapHost', 'board', 'lateralsDesk', 'nav'];

const mob = mobileZones.map((z) => analyze(z, zones[z]));
const desk = desktopZones.map((z) => analyze(z, zones[z]));

function countAuthorities(zoneAnalyses) {
  const fileSets = zoneAnalyses.map((z) => z.files.filter((f) => /inicio\/|inicio-views|play-v3-(app|shell|mapa|desktop-shell)/.test(f)));
  const unique = new Set();
  fileSets.flat().forEach((f) => unique.add(f));
  return { perZone: zoneAnalyses.map((z) => ({ zone: z.zoneKey, files: z.files.length, rules: z.rules, gens: z.gens })), uniqueFiles: [...unique] };
}

const mobAuth = countAuthorities(mob);
const deskAuth = countAuthorities(desk);

console.log('=== MOBILE AUTHORITIES (unique inicio-related files):', mobAuth.uniqueFiles.length);
console.log(mobAuth.uniqueFiles.join('\n'));
console.log('\n=== DESKTOP AUTHORITIES (unique inicio-related files):', deskAuth.uniqueFiles.length);
console.log(deskAuth.uniqueFiles.join('\n'));

console.log('\n=== COMPETING PROPS (mobile) ===');
for (const z of mob) {
  const hot = Object.entries(z.byProp).filter(([, v]) => v.count > 1);
  if (!hot.length) continue;
  console.log('\n[' + z.zoneKey + '] rules=' + z.rules + ' files=' + z.files.join(', '));
  hot.forEach(([p, v]) => {
    console.log(' ', p, 'x' + v.count, 'WINS:', v.winnerVal, '<=', v.winner.split(' | ')[0]);
    if (v.overridden.length) console.log('   overridden by later:', v.overridden.join(' || '));
  });
}

console.log('\n=== COMPETING PROPS (desktop) ===');
for (const z of desk) {
  const hot = Object.entries(z.byProp).filter(([, v]) => v.count > 1);
  if (!hot.length) continue;
  console.log('\n[' + z.zoneKey + '] rules=' + z.rules + ' files=' + z.files.join(', '));
  hot.forEach(([p, v]) => {
    console.log(' ', p, 'x' + v.count, 'WINS:', v.winnerVal, '<=', v.winner.split(' | ')[0]);
    if (v.overridden.length) console.log('   overridden by later:', v.overridden.join(' || '));
  });
}
