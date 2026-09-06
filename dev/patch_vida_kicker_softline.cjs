'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8');
const nl = css.includes('\r\n') ? '\r\n' : '\n';
const norm = (s) => s.replace(/\r\n/g, '\n');

const oldKicker = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-vida-kicker {
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

const newKicker = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-vida-kicker {
    position: relative;
    display: inline-block;
    font-family: var(--ds-font-hand, Caveat, cursive);
    font-size: clamp(1.5rem, 2.4vw, 1.95rem);
    font-weight: 700;
    letter-spacing: .01em;
    text-transform: none;
    color: var(--ds-ink, #2a2218);
    border-bottom: none;
    padding-bottom: 7px;
    text-decoration: none;
    opacity: 1;
    white-space: nowrap;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-vida-kicker::after {
    content: "";
    position: absolute;
    left: 2px;
    right: 2px;
    bottom: 1px;
    height: 2px;
    border-radius: 999px;
    background: linear-gradient(
      90deg,
      rgba(200, 91, 120, 0) 0%,
      rgba(200, 91, 120, .32) 18%,
      rgba(200, 91, 120, .42) 50%,
      rgba(200, 91, 120, .32) 82%,
      rgba(200, 91, 120, 0) 100%
    );
    filter: blur(1.4px);
    pointer-events: none;
  }`;

if (!norm(css).includes(norm(oldKicker))) {
  console.error('patch_vida_kicker_softline: bloque kicker no encontrado');
  process.exit(1);
}

const newBlock = newKicker.split('\n').join(nl);
css = css.replace(oldKicker.split('\n').join(nl), newBlock);
fs.writeFileSync(file, css, 'utf8');
console.log('patch_vida_kicker_softline OK');
