'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const file = path.join(root, 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8');

css = css.replace(
  /\.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.top-vida \{[\s\S]*?gap: 4px;\n  \}/,
  `.play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .top-vida {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: flex-end;
    justify-self: end;
    width: auto;
    max-width: none;
    grid-template-columns: none;
    padding: 0;
    margin: 0;
    overflow: visible;
    gap: 10px;
    border: none;
    background: transparent;
    box-shadow: none;
    cursor: pointer;
  }`
);

css = css.replace(
  /\.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.obj-vida-kicker \{[\s\S]*?white-space: nowrap;\n  \}/,
  `.play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-vida-kicker {
    font-family: var(--ds-font-hand, Caveat, cursive);
    font-size: 1.15rem;
    font-weight: 700;
    letter-spacing: .01em;
    text-transform: none;
    color: var(--ds-ink, #2a2218);
    text-decoration: underline;
    text-decoration-color: var(--ds-pink-deep, #c85b78);
    text-decoration-thickness: 2px;
    text-underline-offset: 4px;
    opacity: 1;
    white-space: nowrap;
  }`
);

css = css.replace(
  /\n  \.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.control-audio \{[\s\S]*?gap: 6px;\n  \}/,
  ''
);

if (!css.includes('INICIO-DESKTOP-VIDA-HEADER-20260906')) {
  css += '\n/* INICIO-DESKTOP-VIDA-HEADER-20260906 — sin controles flotantes; vida+kicker ref PNG 02 */\n';
}

fs.writeFileSync(file, css, 'utf8');
console.log('patch_desktop_vida_header OK');
