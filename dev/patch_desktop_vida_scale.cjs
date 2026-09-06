'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const file = path.join(root, 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8');

css = css.replace(
  /grid-template-columns: auto 1fr minmax\(240px, 300px\);/,
  'grid-template-columns: auto 1fr minmax(280px, 380px);'
);

css = css.replace(
  /font-size: 1\.15rem;\n    font-weight: 700;\n    letter-spacing: \.01em;\n    text-transform: none;\n    color: var\(--ds-ink, #2a2218\);\n    text-decoration: underline;\n    text-decoration-color: var\(--ds-pink-deep, #c85b78\);\n    text-decoration-thickness: 2px;\n    text-underline-offset: 4px;/,
  'font-size: clamp(1.5rem, 2.4vw, 1.95rem);\n    font-weight: 700;\n    letter-spacing: .01em;\n    text-transform: none;\n    color: var(--ds-ink, #2a2218);\n    text-decoration: underline;\n    text-decoration-color: var(--ds-pink-deep, #c85b78);\n    text-decoration-thickness: 2.5px;\n    text-underline-offset: 5px;'
);

css = css.replace(
  /\.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.corazon-svg \{\n    width: 56px;/,
  '.play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .corazon-svg {\n    width: clamp(72px, 5.8vw, 92px);'
);

css = css.replace(
  /justify-content: flex-end;\n    justify-self: end;\n    width: auto;\n    max-width: none;\n    grid-template-columns: none;\n    padding: 0;\n    margin: 0;\n    overflow: visible;\n    gap: 10px;/,
  'justify-content: flex-end;\n    justify-self: end;\n    width: auto;\n    max-width: none;\n    grid-template-columns: none;\n    padding: 0;\n    margin: 0;\n    overflow: visible;\n    gap: 14px;'
);

const marker = 'INICIO-DESKTOP-VIDA-SCALE-20260906';
if (!css.includes(marker)) {
  css += `\n/* ${marker} — vida del pueblo + corazón ref PNG 02 */\n`;
}

fs.writeFileSync(file, css, 'utf8');
console.log('patch_desktop_vida_scale OK');
