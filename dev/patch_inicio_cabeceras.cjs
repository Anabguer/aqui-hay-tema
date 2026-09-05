'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

const MOB_MARKER = '/* INICIO-CABECERA-MOVIL-20260906 */';
const DESK_MARKER = '/* INICIO-DESKTOP-CABECERA-20260906 */';

const mobCabecera = `
${MOB_MARKER}
@media (max-width: 768px) {
  .play-v3 .inicio-stage .inicio-mobile .inicio-header-card {
    width: 100%;
    box-sizing: border-box;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-header-brand-row .brand {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    min-width: 0;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-header-brand-row .brand-text {
    font-family: var(--ds-font-hand, Caveat, cursive);
    font-weight: 700;
    font-size: 1.0625rem;
    line-height: 1;
    letter-spacing: .02em;
    color: var(--ds-ink, #2a2218);
    white-space: nowrap;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-header-brand-row .brand-heart {
    width: 14px;
    height: 13px;
    margin: 0;
    flex-shrink: 0;
    transform: rotate(8deg) translateY(-1px);
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-header-brand-row .brand-heart:not(.brand-heart--lead) {
    display: none;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-header-brand-row .top-vida {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    width: auto;
    max-width: none;
    grid-template-columns: none;
    padding: 0;
    overflow: visible;
    border: none;
    background: transparent;
    gap: 0;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-header-brand-row .top-vida-num,
  .play-v3 .inicio-stage .inicio-mobile .inicio-header-brand-row .obj-vida-kicker {
    display: none;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-header-brand-row .corazon-svg {
    width: 52px;
    height: auto;
    margin: 0;
    justify-self: auto;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-temporal-pill {
    flex-wrap: nowrap;
    justify-content: space-between;
    gap: 6px;
    padding: 6px 10px;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-temporal-dia,
  .play-v3 .inicio-stage .inicio-mobile .inicio-temporal-hora {
    white-space: nowrap;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-temporal-pill .pasar-rato-txt,
  .play-v3 .inicio-stage .inicio-mobile .inicio-temporal-pill .es-noche-txt {
    display: none;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-temporal-pill .es-noche-luna {
    width: 18px;
    height: 18px;
    fill: #6b5a8a;
  }

  .play-v3 .inicio-stage .inicio-mobile .control-audio {
    position: fixed;
    right: 12px;
    bottom: calc(72px + env(safe-area-inset-bottom, 0px));
    z-index: 40;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
}
`;

const deskCabecera = `
${DESK_MARKER}
@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top {
    background: #fff;
    border: 1px solid #d9d2e1;
    border-radius: 16px 14px 18px 15px / 14px 17px 15px 16px;
    box-shadow: 0 4px 12px rgba(70, 55, 85, .1);
    padding: 12px 16px 14px;
    padding-right: 16px;
    margin-bottom: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .brand-col {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    min-width: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .brand {
    display: flex;
    align-items: center;
    gap: .35rem;
    margin: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .brand-text {
    font-family: var(--ds-font-hand, Caveat, cursive);
    font-size: clamp(1.85rem, 2.4vw, 2.35rem);
    font-weight: 700;
    letter-spacing: .01em;
    line-height: 1;
    color: var(--ds-ink, #2a2218);
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .btn-guia:not([hidden]) {
    display: inline-flex;
    align-items: center;
    padding: 0 2px;
    margin: 0;
    background: transparent;
    border: none;
    box-shadow: none;
    font-family: var(--ds-font-hand, Caveat, cursive);
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--ds-pink-deep, #c85b78);
    text-decoration: underline;
    text-underline-offset: 3px;
    transform: rotate(-1deg);
    cursor: pointer;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .top-center {
    display: flex;
    justify-content: center;
    align-items: flex-end;
    gap: 1.25rem;
    min-width: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .top-reloj {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 1.25rem;
    flex-wrap: nowrap;
    background: transparent;
    border: none;
    box-shadow: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .top-vida {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    width: auto;
    max-width: none;
    grid-template-columns: none;
    padding: 0;
    margin: 0;
    overflow: visible;
    gap: 4px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-vida-kicker {
    font-family: var(--ds-font-hand, Caveat, cursive);
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--ds-ink, #2a2218);
    opacity: 1;
    white-space: nowrap;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .corazon-svg {
    width: 56px;
    height: auto;
    margin: 0;
    justify-self: auto;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .control-audio {
    position: absolute;
    top: 10px;
    right: 10px;
    display: flex;
    gap: 6px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-estacion {
    display: none;
  }
}
`;

function upsertMarker(file, marker, block) {
  let css = fs.readFileSync(file, 'utf8');
  const start = css.indexOf(marker);
  if (start >= 0) {
    const next = css.indexOf('\n/* INICIO-', start + marker.length);
    const end = next >= 0 ? next : css.length;
    css = css.slice(0, start) + block.trim() + '\n\n' + css.slice(end).replace(/^\n+/, '');
  } else {
    css = css.trimEnd() + '\n\n' + block.trim() + '\n';
  }
  fs.writeFileSync(file, css, 'utf8');
  console.log('patched', file);
}

function scopeShellArt() {
  const file = path.join(root, 'assets/css/play-v3-shell-art.css');
  let art = fs.readFileSync(file, 'utf8');
  art = art.replace(/\.play-v3:not\(:has\(\.inicio-stage\)\) \.game-top \{\s*padding-right:\s*1cm;\s*box-sizing:\s*border-box;\s*\}\s*/g, '');
  art = art.replace(/\.play-v3:not\(:has\(\.inicio-stage\)\) \.game-top \.top-vida \{\s*display:\s*grid;\s*grid-template-columns:\s*minmax\(0,\s*1fr\) auto;\s*width:\s*100%;\s*max-width:\s*100%;\s*box-sizing:\s*border-box;\s*padding-right:\s*0;\s*overflow:\s*hidden;\s*\}\s*/g, '');
  art = art.replace(/\.play-v3:not\(:has\(\.inicio-stage\)\) \.game-top \.corazon-svg \{\s*margin-right:\s*0;\s*justify-self:\s*end;\s*\}\s*/g, '');
  art = art.replace(/\.game-top \{\s*padding-right:\s*1cm;\s*box-sizing:\s*border-box;\s*\}\s*/g, '');
  art = art.replace(/\.game-top \.top-vida \{\s*display:\s*grid;\s*grid-template-columns:\s*minmax\(0,\s*1fr\) auto;\s*width:\s*100%;\s*max-width:\s*100%;\s*box-sizing:\s*border-box;\s*padding-right:\s*0;\s*overflow:\s*hidden;\s*\}\s*/g, '');
  art = art.replace(/\.game-top \.corazon-svg \{\s*margin-right:\s*0;\s*justify-self:\s*end;\s*\}\s*/g, '');
  fs.writeFileSync(file, art, 'utf8');
  console.log('stripped shell-art game-top legacy');
}

upsertMarker(path.join(root, 'assets/css/inicio/inicio-mobile.css'), MOB_MARKER, mobCabecera);
upsertMarker(path.join(root, 'assets/css/inicio/inicio-desktop.css'), DESK_MARKER, deskCabecera);
scopeShellArt();
console.log('cabeceras OK');
