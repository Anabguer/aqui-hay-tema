'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const file = path.join(root, 'assets/css/inicio/inicio-mobile.css');
let css = fs.readFileSync(file, 'utf8');

css = css.replace(
  /\n  \.play-v3 \.inicio-stage \.inicio-mobile \.control-audio \{[\s\S]*?\n  \}/g,
  ''
);

if (!css.includes('INICIO-MOBILE-SIN-CONTROLES-FLOTANTES-20260906')) {
  css += '\n/* INICIO-MOBILE-SIN-CONTROLES-FLOTANTES-20260906 — audio/inventario en nav inferior y Ajustes */\n';
}

fs.writeFileSync(file, css, 'utf8');
console.log('patch_mobile_sin_controles_flotantes OK');
