'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

function stripImportant(css) {
  return css.replace(/\s*!important\b/gi, '');
}

function normalizePrefixes(css) {
  return css
    .replace(/\.play-v3:has\(\.game-shell\)\s+\.play-root\[data-capa\]/g, '.play-v3')
    .replace(/\.play-v3:has\(\.game-shell\)/g, '.play-v3');
}

function countImportant(css) {
  return (css.match(/!important/gi) || []).length;
}

function splitTopLevel(css) {
  const out = [];
  let i = 0;
  const n = css.length;
  while (i < n) {
    while (i < n && /\s/.test(css[i])) i++;
    if (i >= n) break;
    if (css[i] === '/' && css[i + 1] === '*') {
      const end = css.indexOf('*/', i + 2);
      const block = css.slice(i, end + 2);
      out.push({ type: 'comment', text: block });
      i = end + 2;
      continue;
    }
    if (css.slice(i, i + 6) === '@media') {
      const brace = css.indexOf('{', i);
      let depth = 0;
      let j = brace;
      for (; j < n; j++) {
        if (css[j] === '{') depth++;
        else if (css[j] === '}') { depth--; if (depth === 0) { j++; break; } }
      }
      out.push({ type: 'media', text: css.slice(i, j) });
      i = j;
      continue;
    }
    if (css[i] === '@') {
      const semi = css.indexOf(';', i);
      if (semi !== -1 && css.indexOf('{', i) === -1) {
        out.push({ type: 'at', text: css.slice(i, semi + 1) });
        i = semi + 1;
        continue;
      }
    }
    const brace = css.indexOf('{', i);
    if (brace === -1) { out.push({ type: 'raw', text: css.slice(i) }); break; }
    const sel = css.slice(i, brace).trim();
    let depth = 0;
    let j = brace;
    for (; j < n; j++) {
      if (css[j] === '{') depth++;
      else if (css[j] === '}') { depth--; if (depth === 0) { j++; break; } }
    }
    out.push({ type: 'rule', selector: sel, text: css.slice(i, j) });
    i = j;
  }
  return out;
}

function dedupeRules(css) {
  const parts = splitTopLevel(css);
  const map = new Map();
  const order = [];
  const passthrough = [];
  for (const p of parts) {
    if (p.type === 'rule') {
      const key = p.selector.replace(/\s+/g, ' ').trim();
      if (!map.has(key)) order.push(key);
      map.set(key, p.text);
    } else if (p.type === 'comment') {
      passthrough.push(p.text);
    } else if (p.type === 'media') {
      passthrough.push(p.text);
    } else {
      passthrough.push(p.text);
    }
  }
  const header = passthrough.filter(t => t.startsWith('/*')).join('\n');
  const rules = order.map(k => map.get(k)).join('\n\n');
  return (header ? header + '\n\n' : '') + rules;
}

function extractMedia(css) {
  const parts = splitTopLevel(css);
  const media = [];
  const rest = [];
  for (const p of parts) {
    if (p.type === 'media') media.push(p.text);
    else rest.push(p.text || '');
  }
  return { media: media.join('\n\n'), rest: rest.join('\n') };
}

function extractRootVars(css) {
  const parts = splitTopLevel(css);
  const tokens = [];
  const rest = [];
  for (const p of parts) {
    if (p.type === 'rule' && /^:root\b/.test(p.selector)) {
      tokens.push(p.text);
    } else {
      rest.push(p.text || (p.type === 'comment' ? p.text : ''));
    }
  }
  return { tokens: tokens.join('\n\n'), rest: rest.filter(Boolean).join('\n') };
}

function processFile(rel, opts = {}) {
  const fp = path.join(root, rel);
  if (!fs.existsSync(fp)) return;
  let css = fs.readFileSync(fp, 'utf8');
  const before = countImportant(css);
  if (opts.stripCapaModal && rel.includes('play-v3-responsive')) {
    css = css.split('\n').filter(line => {
      if (/\.capa-[a-z]/.test(line) && !/capa-scroll/.test(line)) return false;
      if (/modal-core|modals-shell-lavanda|play-v3-capas/.test(line)) return false;
      return true;
    }).join('\n');
  }
  css = normalizePrefixes(css);
  if (opts.dedupe) css = dedupeRules(css);
  if (opts.stripImportant !== false) css = stripImportant(css);
  fs.writeFileSync(fp, css, 'utf8');
  const after = countImportant(css);
  console.log(`${rel}: !important ${before} -> ${after}, lines ${css.split('\n').length}`);
}

function rebuildInicio() {
  const mobilePath = 'assets/css/design-system/screens/inicio-mobile.css';
  const desktopPath = 'assets/css/design-system/screens/inicio-desktop.css';
  const cromPath = 'assets/css/design-system/screens/inicio-desktop-cromatica.css';
  const evMob = 'assets/css/design-system/screens/inicio-evento-pueblo-mobile.css';
  const evDesk = 'assets/css/design-system/screens/inicio-evento-pueblo-desktop.css';
  const outDir = 'assets/css/inicio';
  fs.mkdirSync(path.join(root, outDir), { recursive: true });

  let mob = fs.readFileSync(path.join(root, mobilePath), 'utf8');
  let desk = fs.readFileSync(path.join(root, desktopPath), 'utf8');
  let crom = fs.readFileSync(path.join(root, cromPath), 'utf8');
  let evM = fs.readFileSync(path.join(root, evMob), 'utf8');
  let evD = fs.readFileSync(path.join(root, evDesk), 'utf8');

  [mob, desk, crom, evM, evD] = [mob, desk, crom, evM, evD].map(s => normalizePrefixes(stripImportant(s)));

  const mobNoMedia = extractMedia(mob);
  const deskNoMedia = extractMedia(desk);
  const mobVars = extractRootVars(mobNoMedia.rest);
  const deskVars = extractRootVars(deskNoMedia.rest);
  const cromVars = extractRootVars(crom);

  const tokens = ['/* tokens-inicio */', mobVars.tokens, deskVars.tokens, cromVars.tokens].filter(Boolean).join('\n\n');
  const responsive = ['/* inicio-responsive */', mobNoMedia.media, deskNoMedia.media, evM, evD].filter(Boolean).join('\n\n');

  const mobRules = splitTopLevel(mobVars.rest).filter(p => p.type === 'rule');
  const deskRules = splitTopLevel(deskVars.rest).filter(p => p.type === 'rule');
  const deskMap = new Map(deskRules.map(r => [r.selector.replace(/\s+/g, ' ').trim(), r.text]));
  const shared = [];
  const mobOnly = [];
  for (const r of mobRules) {
    const k = r.selector.replace(/\s+/g, ' ').trim();
    const d = deskMap.get(k);
    if (d && d.replace(/\s+/g, '') === r.text.replace(/\s+/g, '')) shared.push(r.text);
    else mobOnly.push(r.text);
  }
  const mobKeys = new Set(mobRules.map(r => r.selector.replace(/\s+/g, ' ').trim()));
  const deskOnly = deskRules.filter(r => {
    const k = r.selector.replace(/\s+/g, ' ').trim();
    if (!mobKeys.has(k)) return true;
    const m = mobRules.find(x => x.selector.replace(/\s+/g, ' ').trim() === k);
    return m && m.text.replace(/\s+/g, '') !== r.text.replace(/\s+/g, '');
  }).map(r => r.text);

  const base = dedupeRules(['/* inicio-base */', ...shared].join('\n\n'));
  const mobileOut = dedupeRules(['/* inicio-mobile */', ...mobOnly].join('\n\n'));
  const desktopOut = dedupeRules(['/* inicio-desktop */', ...deskOnly].join('\n\n'));

  fs.writeFileSync(path.join(root, outDir, 'tokens-inicio.css'), dedupeRules(tokens));
  fs.writeFileSync(path.join(root, outDir, 'inicio-base.css'), base);
  fs.writeFileSync(path.join(root, outDir, 'inicio-mobile.css'), mobileOut);
  fs.writeFileSync(path.join(root, outDir, 'inicio-desktop.css'), desktopOut);
  fs.writeFileSync(path.join(root, outDir, 'inicio-responsive.css'), dedupeRules(responsive));

  const totalLines = ['tokens-inicio.css','inicio-base.css','inicio-mobile.css','inicio-desktop.css','inicio-responsive.css']
    .map(f => fs.readFileSync(path.join(root, outDir, f), 'utf8').split('\n').length)
    .reduce((a,b)=>a+b,0);
  const totalImp = ['tokens-inicio.css','inicio-base.css','inicio-mobile.css','inicio-desktop.css','inicio-responsive.css']
    .map(f => countImportant(fs.readFileSync(path.join(root, outDir, f), 'utf8')))
    .reduce((a,b)=>a+b,0);
  console.log(`INICIO rebuilt: ${totalLines} lines, ${totalImp} !important`);
}

function splitScreensSecondary() {
  const src = path.join(root, 'assets/css/v4/screens-secondary.css');
  let css = fs.readFileSync(src, 'utf8');
  css = normalizePrefixes(stripImportant(css));
  const bodiesDir = path.join(root, 'assets/css/v4/bodies');
  fs.mkdirSync(bodiesDir, { recursive: true });

  const markers = [
    { name: 'parejas.css', start: 'Parejas' },
    { name: 'ajustes.css', start: 'Ajustes: cinta' },
    { name: 'vecinos-relaciones.css', start: 'Vecinos' },
    { name: 'ficha-relaciones.css', start: 'Ficha' },
    { name: 'vida-pueblo-icon.css', start: 'Vida del pueblo' },
    { name: 'mobile-submodales.css', start: 'vil' },
    { name: 'cintas-scrapbook.css', start: 'Cintas' },
    { name: 'ajustes-layout.css', start: 'Ajustes: layout' },
    { name: 'inventario.css', start: 'Inventario' },
    { name: 'relaciones-filtros.css', start: 'Relaciones: filtros' },
  ];

  const lines = css.split('\n');
  const sections = {};
  let current = '_header';
  sections[current] = [];
  for (const line of lines) {
    const m = line.match(/^\/\* --- (.+?) ---/);
    if (m) {
      const hit = markers.find(x => m[1].includes(x.start) || m[1].startsWith(x.start.split(':')[0]));
      current = hit ? hit.name : ('misc-' + m[1].slice(0, 20).replace(/\W/g, '_'));
      if (!sections[current]) sections[current] = [];
    }
    sections[current].push(line);
  }

  for (const [file, chunk] of Object.entries(sections)) {
    if (file === '_header' || chunk.length < 3) continue;
    const body = chunk.join('\n').trim();
    if (!body) continue;
    const target = file.endsWith('.css') ? path.join(bodiesDir, file) : path.join(bodiesDir, file + '.css');
    fs.writeFileSync(target, `/* v4/bodies/${path.basename(target)} */\n` + dedupeRules(body));
  }

  const inv = path.join(bodiesDir, 'inventario.css');
  if (fs.existsSync(inv)) {
    const screens = path.join(root, 'assets/css/v4/screens.css');
    let sc = fs.readFileSync(screens, 'utf8');
    if (!sc.includes('INVENTARIO-V4-BODY')) {
      sc += '\n\n/* INVENTARIO-V4-BODY */\n' + fs.readFileSync(inv, 'utf8');
      fs.writeFileSync(screens, sc);
    }
    fs.unlinkSync(inv);
  }

  const vr = path.join(bodiesDir, 'vecinos-relaciones.css');
  if (fs.existsSync(vr)) {
    const vb = path.join(root, 'assets/css/design-system/vecinos-body.css');
    fs.appendFileSync(vb, '\n\n/* V4 secondary migrado */\n' + fs.readFileSync(vr, 'utf8'));
    fs.unlinkSync(vr);
  }

  fs.writeFileSync(src, '/* screens-secondary retired — see v4/bodies */\n');
  console.log('screens-secondary split -> v4/bodies');
}

function mergeMiscPlayV3() {
  const files = ['assets/css/play-v3-misiones.css','assets/css/play-v3-vida.css','assets/css/play-v3-agenda.css','assets/css/play-v3-notas-mapa.css'];
  const out = path.join(root, 'assets/css/v4/bodies/misc-screens.css');
  let bundle = '/* misc screens */\n';
  for (const f of files) {
    const fp = path.join(root, f);
    if (!fs.existsSync(fp)) continue;
    let css = stripImportant(normalizePrefixes(fs.readFileSync(fp, 'utf8')));
    bundle += `\n/* from ${f} */\n` + css + '\n';
    fs.unlinkSync(fp);
  }
  fs.writeFileSync(out, dedupeRules(bundle));
  console.log('merged misc play-v3 small files');
}

const playBodies = [
  'assets/css/play-v3-app.css','assets/css/play-v3-avisos.css',
  'assets/css/play-v3-bloques-residencias.css','assets/css/play-v3-consulta-edificio-v2.css',
  'assets/css/play-v3-cotilleos.css','assets/css/play-v3-desktop-shell.css','assets/css/play-v3-enc-int.css',
  'assets/css/play-v3-lab.css','assets/css/play-v3-mapa-canonico.css','assets/css/play-v3-mensajitos.css',
  'assets/css/play-v3-organizar.css','assets/css/play-v3-regalos.css','assets/css/play-v3-responsive.css',
  'assets/css/play-v3-shell-art.css','assets/css/play-v3-shell-ui.css','assets/css/play-v3-tutorial-ds.css',
  'assets/css/play-v3-tutorial-lavanda.css','assets/css/play-v3-vecinos.css','assets/css/play-v3.css',
  'assets/css/design-system/components.css','assets/css/design-system/legibilidad-global.css',
  'assets/css/design-system/typography-reading.css','assets/css/design-system/vecinos-body.css',
  'assets/css/v4/screen-frame.css','assets/css/v4/screens.css','assets/css/v4/tokens-v4.css',
];

console.log('=== PHASE 2 CSS SURGERY ===');
splitScreensSecondary();
mergeMiscPlayV3();
for (const f of playBodies) processFile(f, { dedupe: true, stripCapaModal: f.includes('responsive') });
rebuildInicio();

for (const orphan of [
  'assets/css/app.css',
  'assets/css/design-system/screens/inicio.css',
  'assets/css/play-v3-inicio-override.css',
  'assets/css/design-system/screens/inicio-mobile.css',
  'assets/css/design-system/screens/inicio-desktop.css',
  'assets/css/design-system/screens/inicio-desktop-cromatica.css',
  'assets/css/design-system/screens/inicio-evento-pueblo-mobile.css',
  'assets/css/design-system/screens/inicio-evento-pueblo-desktop.css',
]) {
  const fp = path.join(root, orphan);
  if (fs.existsSync(fp)) { fs.unlinkSync(fp); console.log('deleted', orphan); }
}

let total = 0, files = 0;
function walk(d) {
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const p = path.join(d, e.name);
    if (e.isDirectory()) walk(p);
    else if (e.name.endsWith('.css')) {
      const c = fs.readFileSync(p, 'utf8');
      total += countImportant(c);
      files++;
    }
  }
}
walk(path.join(root, 'assets/css'));
console.log(`METRICS: ${files} css files, ${total} !important total`);
