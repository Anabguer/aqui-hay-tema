#!/usr/bin/env node
'use strict';
/**
 * Autoridad única Inicio móvil — detecta doble stack legacy.
 * Uso: node dev/audit_inicio_authority.cjs
 */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const app = fs.readFileSync(path.join(root, 'assets/css/play-v3-app.css'), 'utf8');
const mob = fs.readFileSync(path.join(root, 'assets/css/inicio/inicio-mobile.css'), 'utf8');
const shell = fs.readFileSync(path.join(root, 'assets/css/play-v3-shell-ui.css'), 'utf8');

const hits = [];

if (/body\.play-v3\s*\{[^}]*margin:\s*1cm\s+2\.5cm/.test(app) &&
  !/@media\s*\(\s*min-width:\s*769px\s*\)[\s\S]*body\.play-v3\s*\{[^}]*margin:\s*1cm\s+2\.5cm/.test(app)) {
  hits.push('body.play-v3 margin desktop sin acotar a min-width 769px');
}

if (/\.play-root\.phone\s+\.dock\s*\{[^}]*display:\s*grid/.test(app)) {
  hits.push('dock legacy activo en .play-root.phone');
}

if (/INICIO-CABECERA-GUIA/.test(mob)) {
  hits.push('bloque INICIO-CABECERA-GUIA competidor en inicio-mobile.css');
}

if (/\.inicio-stage\s+\.inicio-mobile\s+\.control-audio\s*\{[^}]*position:\s*fixed/.test(mob)) {
  hits.push('control-audio flotante legacy en inicio-mobile.css');
}

if (/\.inicio-stage\s+\.inicio-mobile\s+\.game-top\s*\{[^}]*grid-template-areas/.test(mob)) {
  hits.push('game-top grid pre-FIEL en inicio-mobile.css');
}

if (/(^|\n)\.obj-buzon\s*\{/.test(shell)) {
  hits.push('.obj-buzon sin acotar a .inicio-desktop en play-v3-shell-ui.css');
}

if (hits.length) {
  console.error('AUDIT INICIO AUTORIDAD — FALLOS:\n' + hits.map((h) => '  - ' + h).join('\n'));
  process.exit(1);
}

console.log('AUDIT INICIO AUTORIDAD — OK (autoridad única móvil)');
