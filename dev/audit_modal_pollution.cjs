#!/usr/bin/env node
'use strict';
/**
 * Auditoría de contaminación shell modal.
 * Uso: node dev/audit_modal_pollution.cjs
 * Exit 0 = sin hallazgos críticos; exit 1 = hay conflictos shell fuera de v4/
 */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const cssRoot = path.join(root, 'assets/css');
const allowShell = new Set([
  'assets/css/v4/screens.css',
  'assets/css/v4/screen-frame.css',
  'assets/css/v4/tokens-v4.css',
]);

const checks = [
  {
    id: 'shell-position-absolute',
    severity: 'CRITICO',
    pattern: /\.aht-screen\s*\{[^}]*position:\s*absolute/g,
    hint: 'Shell .aht-screen con position:absolute (pre-V4)',
  },
  {
    id: 'phone-bottom-sheet',
    severity: 'CRITICO',
    pattern: /\.play-root\.phone\s+\.aht-screen\s*\{[^}]*bottom:/g,
    hint: 'Modal móvil anclada abajo (bottom:)',
  },
  {
    id: 'capa-transform-none',
    severity: 'CRITICO',
    pattern: /\[data-capa=[^\]]+\][^{]*\.aht-screen[^{]*\{[^}]*transform:\s*none/g,
    hint: 'transform:none en modal abierta — rompe centrado V4',
  },
  {
    id: 'capas-shell-unificado',
    severity: 'CRITICO',
    pattern: /play-root\.(?:phone|pc)\[data-capa=[^\]]+\]\s+\.aht-screen\[data-aht-screen=[^\]]+\][^{]*\{[^}]*position:\s*fixed[^}]*visibility:\s*visible/g,
    hint: 'Shell duplicado CAPAS (position:fixed por capa fuera de v4)',
  },
  {
    id: 'velo-capa-opacity',
    severity: 'ALTO',
    pattern: /\[data-capa=[^\]]+\]\s+\.velo\s*\{[^}]*opacity:\s*1/g,
    hint: 'Velo legacy activo por data-capa (doble con .aht-velo)',
  },
  {
    id: 'screen-width-override',
    severity: 'ALTO',
    pattern: /\.play-root\.(?:pc|phone)\s+\.aht-screen\[data-aht-screen="[^"]+"\]\s*\{[^}]*\bwidth:/g,
    hint: 'Ancho shell por pantalla (debe venir de tokens v4)',
  },
];

function listCssFiles(dir, out) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) listCssFiles(p, out);
    else if (e.name.endsWith('.css')) out.push(p);
  }
}

const files = [];
listCssFiles(cssRoot, files);
const findings = [];

for (const abs of files) {
  const rel = path.relative(root, abs).replace(/\\/g, '/');
  if (allowShell.has(rel)) continue;
  const content = fs.readFileSync(abs, 'utf8');
  for (const check of checks) {
    const m = content.match(check.pattern);
    if (m) {
      findings.push({ rel, ...check, count: m.length });
    }
  }
}

console.log('=== AUDITORIA SHELL MODAL ===');
console.log('Autoridad permitida:', [...allowShell].join(', '));
console.log('Ficheros escaneados:', files.length);
console.log('');

if (!findings.length) {
  console.log('Sin hallazgos fuera de v4/.');
  process.exit(0);
}

const bySeverity = { CRITICO: [], ALTO: [], MEDIO: [] };
for (const f of findings) (bySeverity[f.severity] || bySeverity.MEDIO).push(f);

for (const sev of ['CRITICO', 'ALTO', 'MEDIO']) {
  const list = bySeverity[sev];
  if (!list.length) continue;
  console.log(`-- ${sev} (${list.length}) --`);
  for (const f of list) {
    console.log(`  ${f.rel}  [${f.id}] x${f.count}`);
    console.log(`    ${f.hint}`);
  }
  console.log('');
}

const critical = findings.filter((f) => f.severity === 'CRITICO').length;
const high = findings.filter((f) => f.severity === 'ALTO').length;
console.log(`Resumen: ${critical} críticos, ${high} altos, ${findings.length} total`);
process.exit(critical > 0 ? 1 : 0);
