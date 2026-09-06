'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const file = path.join(root, 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8');

/* Quitar ::before "Día " duplicado (JS ya pone "Día N") */
css = css.replace(
  /\n  \.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.obj-dia-num::before \{[\s\S]*?\n  \}/,
  ''
);

/* Misma altura dia/hora */
css = css.replace(
  /(\.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.obj-dia \{[\s\S]*?padding: 10px 14px;)/,
  `$1
    min-height: 52px;
    box-sizing: border-box;`
);

css = css.replace(
  /(\.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.obj-hora \{[\s\S]*?padding: 10px 16px;)/,
  `$1
    min-height: 52px;
    box-sizing: border-box;`
);

/* Rayas legacy shell-art en dia */
const legacyKill = `
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia::after,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-cuerpo::before,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-cuerpo::after {
    display: none;
    content: none;
    background: none;
    border: none;
    box-shadow: none;
  }
`;

if (!css.includes('obj-dia-cuerpo::before')) {
  const insertAfter = '/* INICIO-DESKTOP-TOP-RELOJ-20260906';
  const idx = css.indexOf(insertAfter);
  if (idx >= 0) {
    const mediaEnd = css.indexOf('@media (min-width: 769px)', idx);
    const openBrace = css.indexOf('{', mediaEnd);
    css = css.slice(0, openBrace + 1) + legacyKill + css.slice(openBrace + 1);
  }
} else if (!css.includes('obj-dia::after')) {
  // already has cuerpo rules maybe partial
}

// Ensure legacy kill inside TOP-RELOJ block - simpler: append fix block
const marker = 'INICIO-DESKTOP-TOP-RELOJ-FIX-20260906';
const fixBlock = `
/* ${marker} */
@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora {
    min-height: 52px;
    box-sizing: border-box;
    align-items: center;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-num::before {
    content: none;
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia::after,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-cuerpo::before,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-cuerpo::after {
    display: none;
    content: none;
    background: none;
    border: none;
    box-shadow: none;
    width: 0;
    height: 0;
  }
}
`;

const re = new RegExp('/\\* ' + marker + '[\\s\\S]*?(?=\\n/\\*|$)', 'm');
if (re.test(css)) css = css.replace(re, fixBlock.trim() + '\n');
else css = css.trimEnd() + '\n\n' + fixBlock.trim() + '\n';

fs.writeFileSync(file, css, 'utf8');
console.log('patch_desktop_top_reloj_fix OK');
