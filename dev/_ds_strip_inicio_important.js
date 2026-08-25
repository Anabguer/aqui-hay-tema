'use strict';
/*
 * DS FASE 3 · Piloto Inicio móvil — neutralización quirúrgica del legacy.
 *
 * Elimina de play-v3-responsive.css SOLO las declaraciones `!important` de
 * PROPIEDAD DE PIEL (tipografía, color, fondo, borde decorativo, sombra)
 * dentro de reglas cuyo selector pertenece a módulos de la pantalla INICIO.
 * El skin de esas propiedades pasa a ser propiedad de
 * assets/css/design-system/screens/inicio.css (que carga después).
 *
 * NO se tocan:
 *  - propiedades de layout/estructura (display, margin, padding, width, height,
 *    grid, flex, position, transform, overflow, white-space...);
 *  - reglas dentro de @media (min-width:...) (protección desktop);
 *  - selectores excluidos explícitamente (contratos cubiertos por tests UI
 *    canónicos, p. ej. .es-noche);
 *  - cualquier regla de otros archivos legacy o de otras pantallas.
 *
 * Si una regla queda vacía tras el stripping, se elimina completa.
 * Informe: dev/_ds_strip_report.txt
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const FILE = path.join(ROOT, 'assets', 'css', 'play-v3-responsive.css');
const REPORT = path.join(__dirname, '_ds_strip_report.txt');

const INICIO_SEL = new RegExp(
  'obj-buzon|obj-vecinos|obj-proximo|obj-nuevo-plan|obj-cotilleo|obj-misiones|obj-pareja|' +
  'enc-mov|pp-mov|mision-strip|zona-tit|game-top|game-main|game-left|game-right|shell-grupo|' +
  'brand|top-reloj|top-vida|top-meta|pasar-rato|obj-dia|obj-hora|control-audio|control-musica|' +
  'control-efectos|btn-guia|mensajitos-wrap|celestine|libreta-kicker|top-center|brand-col|brand-text|brand-heart',
  'i'
);

// Selectores que los tests UI canónicos assertionan textualmente: NO TOCAR.
const SKIP_SEL = [/es-noche/i, /top-meta-prim/i, /obj-vecinos-preview-cara/i];

// Subconjunto TILE: módulos de la fila de accesos del inicio cuyo
// dimensionado !important impide el skin DS (min-height/padding/border).
// Se strip-ean además las propiedades de tamaño listadas en SIZE_PROPS.
const TILE_SEL = /obj-buzon|celestine-nota|obj-proximo|obj-nuevo-plan|mensajitos-wrap|game-left-tile-ico|obj-buzon-ico-wrap/;
const SIZE_PROPS = new Set([
  'height', 'min-height', 'max-height',
  'padding', 'padding-top', 'padding-bottom', 'padding-left', 'padding-right',
  'margin', 'margin-top', 'margin-bottom', 'margin-left', 'margin-right',
  'border', 'border-width', 'border-color', 'border-radius'
]);

// Propiedades de piel que el DS propietiza en el piloto.
const SKIN_PROPS = new Set([
  'font-size', 'line-height', 'letter-spacing', 'font-family', 'font-weight',
  'color', 'text-shadow', 'text-transform', 'text-align',
  'background', 'background-color', 'background-image',
  'border-radius', 'box-shadow', 'border-color', 'outline-color',
  '-webkit-text-fill-color', 'filter', 'writing-mode'
]);

// Subconjunto COTI: la tira de Cotilleo del home (grid legacy con
// writing-mode vertical + margin/border/transform/display !important).
const COTI_SEL = /obj-cotilleo/;
const COTI_PROPS = new Set([
  'margin', 'margin-top', 'margin-bottom', 'margin-left', 'margin-right',
  'border', 'transform', 'display', 'overflow', 'writing-mode'
]);

// Subconjunto ITEMS: los tres items de grid de la fila de accesos, cuyo
// height/min-height/max-height !important impide la altura comun de familia.
const ITEM_SEL = /shell-grupo-(buzon|resumen|planes)(\s|,|\$)/;
const ITEM_PROPS = new Set([
  'height', 'min-height', 'max-height'
]);

const src = fs.readFileSync(FILE, 'utf8');
const out = [];
const report = [];
let removedDecls = 0;
let removedRules = 0;
let scannedRules = 0;

// Tokeniza el CSS rastreando la pila de @media activa.
let i = 0;
let buf = '';
const mediaStack = [];

function mediaEsMovil() {
  if (mediaStack.length === 0) return true; // regla de nivel superior
  return mediaStack.some(m => /max-width/i.test(m));
}

function flushRule(selector, body) {
  scannedRules++;
  if (!INICIO_SEL.test(selector) || SKIP_SEL.some(re => re.test(selector))) {
    out.push(selector + '{' + body + '}');
    return;
  }
  if (!/!important/.test(body) || !mediaEsMovil()) {
    out.push(selector + '{' + body + '}');
    return;
  }
  const decls = body.split(';');
  const kept = [];
  const dropped = [];
  for (let d of decls) {
    const t = d.trim();
    if (!t) continue;
    const m = t.match(/^([a-zA-Z-]+)\s*:/);
    const esTile = TILE_SEL.test(selector);
    const esIcono = /obj-buzon-img/.test(selector);
    const esCoti = COTI_SEL.test(selector);
    const esItem = ITEM_SEL.test(selector);
    let propsPiel = esTile ? new Set([...SKIN_PROPS, ...SIZE_PROPS]) : SKIN_PROPS;
    if (esIcono) { propsPiel = new Set([...propsPiel, 'width', 'height']); }
    if (esCoti) propsPiel = new Set([...propsPiel, ...COTI_PROPS]);
    if (esItem) propsPiel = new Set([...propsPiel, ...ITEM_PROPS]);
    if (/^flex:/.test(t) && esTile && /\d(px|rem|%)/.test(t)) { dropped.push(t); continue; }
    if (m && /!important/.test(t) && propsPiel.has(m[1].toLowerCase())) {
      dropped.push(t);
    } else {
      kept.push(t);
    }
  }
  if (dropped.length) {
    removedDecls += dropped.length;
    report.push('REGLA: ' + selector.trim().replace(/\s+/g, ' ').slice(0, 140));
    dropped.forEach(d => { report.push('   - ' + d); });
  }
  if (kept.length) {
    out.push(selector + '{' + kept.join(';\n  ') + ';}');
  } else {
    removedRules++;
    report.push('REGLA ELIMINADA (vacía): ' + selector.trim().replace(/\s+/g, ' ').slice(0, 140));
  }
}

while (i < src.length) {
  const ch = src[i];

  if (ch === '/' && src[i + 1] === '*') { // comentario
    const end = src.indexOf('*/', i + 2);
    const stop = end === -1 ? src.length : end + 2;
    buf += src.slice(i, stop);
    i = stop;
    continue;
  }

  if (ch === '@') { // at-rule
    const brace = src.indexOf('{', i);
    const semi = src.indexOf(';', i);
    if (brace !== -1 && (semi === -1 || brace < semi)) {
      const head = src.slice(i, brace).trim();
      buf += src.slice(i, brace + 1);
      i = brace + 1;
      if (/^@media/i.test(head) || /^@supports/i.test(head)) {
        out.push(buf); buf = '';
        mediaStack.push(head);
      } else { // @keyframes, @font-face...: copiar tal cual hasta su cierre
        let depth = 1;
        while (i < src.length && depth > 0) {
          if (src[i] === '{') depth++;
          else if (src[i] === '}') depth--;
          i++;
        }
        buf += src.slice(brace + 1, i);
      }
      continue;
    }
  }

  if (ch === '}') {
    if (mediaStack.length && buf.trim() === '' && !/[{}]/.test(buf)) {
      out.push(buf + '}');
      buf = '';
      mediaStack.pop();
      i++;
      continue;
    }
    const braceOpen = buf.lastIndexOf('{');
    if (braceOpen !== -1) {
      const selector = buf.slice(0, braceOpen);
      const body = buf.slice(braceOpen + 1);
      flushRule(selector, body);
      buf = '';
    } else {
      buf += ch;
    }
    i++;
    continue;
  }

  if (ch === '{') {
    if (mediaStack.length && buf.trim() === '' ) {
      // llaves de anidación rara: copiar
    }
    buf += ch;
    i++;
    continue;
  }

  buf += ch;
  i++;
}
if (buf) out.push(buf);

fs.writeFileSync(FILE, out.join(''), 'utf8');
const summary = [
  'DS strip inicio — ' + new Date().toISOString(),
  'Reglas escaneadas: ' + scannedRules,
  'Declaraciones !important de piel eliminadas: ' + removedDecls,
  'Reglas eliminadas por quedar vacías: ' + removedRules,
  ''
].join('\n');
fs.writeFileSync(REPORT, summary + report.join('\n') + '\n', 'utf8');
console.log(summary);
console.log('Informe completo: dev/_ds_strip_report.txt');
