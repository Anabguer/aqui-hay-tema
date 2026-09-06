'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const file = path.join(root, 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8');

css = css.replace(
  /font-size: clamp\(1\.85rem, 2\.4vw, 2\.35rem\);/,
  'font-size: clamp(2.75rem, 4.6vw, 3.65rem);'
);

css = css.replace(
  /letter-spacing: \.01em;\n    line-height: 1;\n    color: var\(--ds-ink, #2a2218\);\n  \}\n\n  \.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.btn-guia/,
  'letter-spacing: .01em;\n    line-height: 0.92;\n    color: var(--ds-ink, #2a2218);\n  }\n\n  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .brand-heart {\n    width: clamp(30px, 3.4vw, 40px);\n    height: clamp(27px, 3.1vw, 36px);\n    margin-left: .28rem;\n    flex-shrink: 0;\n  }\n\n  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .btn-guia'
);

if (!css.includes('INICIO-DESKTOP-BRAND-SIZE-20260906')) {
  css += '\n/* INICIO-DESKTOP-BRAND-SIZE-20260906 — marca cabecera ref PNG 02 */\n';
}

fs.writeFileSync(file, css, 'utf8');
console.log('patch_desktop_brand_size OK');
