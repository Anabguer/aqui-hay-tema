'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8');

const oldBlock = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-vida-kicker {
    font-family: var(--ds-font-hand, Caveat, cursive);
    font-size: clamp(1.5rem, 2.4vw, 1.95rem);
    font-weight: 700;
    letter-spacing: .01em;
    text-transform: none;
    color: var(--ds-ink, #2a2218);
    text-decoration: underline;
    text-decoration-color: var(--ds-pink-deep, #c85b78);
    text-decoration-thickness: 2.5px;
    text-underline-offset: 5px;
    opacity: 1;
    white-space: nowrap;
  }`;

const newBlock = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-vida-kicker {
    font-family: var(--ds-font-hand, Caveat, cursive);
    font-size: clamp(1.5rem, 2.4vw, 1.95rem);
    font-weight: 700;
    letter-spacing: .01em;
    text-transform: none;
    color: var(--ds-ink, #2a2218);
    border-bottom: none;
    padding-bottom: 0;
    text-decoration: underline;
    text-decoration-color: rgba(200, 91, 120, .45);
    text-decoration-thickness: 1px;
    text-underline-offset: 3px;
    opacity: 1;
    white-space: nowrap;
  }`;

if (!css.includes(oldBlock)) {
  console.error('patch_vida_kicker_underline: bloque no encontrado');
  process.exit(1);
}
css = css.replace(oldBlock, newBlock);
fs.writeFileSync(file, css, 'utf8');
console.log('patch_vida_kicker_underline OK');
