'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const file = path.join(root, 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8');

css = css.replace(
  /\.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.brand-col \{\n    display: flex;\n    flex-direction: column;\n    align-items: flex-start;\n    gap: 2px;\n    min-width: 0;\n  \}/,
  `.play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .brand-col {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    min-width: 0;
    margin-left: 18px;
    padding-left: 4px;
  }`
);

css = css.replace(
  /\.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.brand \{\n    display: flex;\n    align-items: center;\n    gap: \.35rem;\n    margin: 0;\n  \}/,
  `.play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .brand {
    display: flex;
    align-items: center;
    gap: .55rem;
    margin: 0;
  }`
);

css = css.replace(
  /\.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.brand-heart \{\n    width: clamp\(30px, 3\.4vw, 40px\);\n    height: clamp\(27px, 3\.1vw, 36px\);\n    margin-left: \.28rem;\n    flex-shrink: 0;\n  \}/,
  `.play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .brand-heart {
    width: clamp(22px, 2.6vw, 30px);
    height: clamp(20px, 2.4vw, 27px);
    margin-left: .12rem;
    flex-shrink: 0;
    transform: rotate(8deg) translateY(-1px);
  }`
);

const marker = 'INICIO-DESKTOP-BRAND-ALIGN-20260906';
if (!css.includes(marker)) {
  css += `\n/* ${marker} — marca desplazada derecha, corazón más pequeño y separado */\n`;
}

fs.writeFileSync(file, css, 'utf8');
console.log('patch_desktop_brand_align OK');
