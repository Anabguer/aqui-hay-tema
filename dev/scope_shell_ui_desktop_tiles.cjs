#!/usr/bin/env node
'use strict';
/**
 * Acota reglas sidebar .obj-* de play-v3-shell-ui.css a .inicio-desktop
 * (no deben gobernar tiles móvil en .inicio-mobile-tiles)
 */
const fs = require('fs');
const path = require('path');

const file = path.join(__dirname, '..', 'assets/css/play-v3-shell-ui.css');
let css = fs.readFileSync(file, 'utf8');

const prefixes = [
  '.obj-buzon',
  '.obj-buzon-badge',
  '.obj-buzon-img',
  '.obj-buzon-txt',
  '.obj-cotilleo',
  '.obj-cotilleo-tit',
  '.obj-cotilleo-txt',
  '.obj-proximo',
  '.obj-proximo-tit',
  '.obj-proximo-body',
  '.obj-proximo-vacio',
  '.obj-nuevo-plan',
  '.obj-nuevo-plan-ico',
  '.obj-nuevo-plan-txt',
  '.obj-vecinos-resumen:not(.celestine-nota)',
  '.obj-vecinos-resumen.celestine-nota',
  '.obj-vecinos-stats',
  '.obj-vecinos-tit',
  '.celestine-nota .obj-vecinos-tit',
];

let n = 0;
for (const sel of prefixes) {
  const re = new RegExp('(^|\\n)(' + sel.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')(\\s*\\{)', 'g');
  const next = css.replace(re, (m, before, selector, brace) => {
    if (selector.startsWith('.inicio-desktop ')) return m;
    n++;
    return before + '.inicio-desktop ' + selector + brace;
  });
  css = next;
}

css = css.replace(
  /\.obj-vecinos-resumen, \.obj-cotilleo, \.obj-proximo, \.obj-buzon \{ max-width:100%; box-sizing:border-box; \}/,
  '.inicio-desktop .obj-vecinos-resumen, .inicio-desktop .obj-cotilleo, .inicio-desktop .obj-proximo, .inicio-desktop .obj-buzon { max-width:100%; box-sizing:border-box; }'
);

// Dock legacy: retirado del flujo móvil (play-bottom-nav es la nav actual)
css = css.replace(
  /\.play-v3 \.mesa, \.play-v3 \.mesa \*, \.play-v3 \.play-root\.pc \.dock \{/,
  '.play-v3 .mesa, .play-v3 .mesa *, .play-v3 .play-root .dock {'
);

fs.writeFileSync(file, css);
console.log('play-v3-shell-ui.css: ' + n + ' selectores acotados a .inicio-desktop; dock oculto global');
