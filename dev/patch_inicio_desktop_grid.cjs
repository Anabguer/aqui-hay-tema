#!/usr/bin/env node
'use strict';
const fs = require('fs');
const path = require('path');
const p = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
let d = fs.readFileSync(p, 'utf8');

const gridBlock = `/* INICIO-DESKTOP-LAYOUT — autoridad única layout desktop */

@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage {
    display: grid;
    grid-template-columns: minmax(328px, 400px) minmax(0, 1fr) minmax(282px, 362px);
    grid-template-areas:
      "head head head"
      "left map  right";
    column-gap: 14px;
    row-gap: 10px;
    align-items: start;
    padding: 0 14px 14px;
    box-sizing: border-box;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-desktop {
    display: contents;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage .inicio-desktop > .game-top {
    grid-area: head;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage .inicio-desktop-layout {
    display: contents;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage .inicio-desktop-left {
    grid-area: left;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host {
    grid-area: map;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage .inicio-desktop-right {
    grid-area: right;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-mobile.inicio-mobile-feed,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-mobile:not(.inicio-mobile-feed) {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop .inicio-desktop-layout {
    padding: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top {
    display: grid;
    grid-template-columns: auto 1fr minmax(240px, 300px);
    align-items: center;
    gap: 0.75rem 1.25rem;
    padding: 7px 0 9px;
    box-sizing: border-box;
    position: relative;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav {
    display: flex;
    position: static;
    width: 100%;
    max-width: 100%;
    margin-top: 2px;
    align-self: end;
  }
}
`;

d = d.replace(/\.inicio-desktop \.inicio-desktop-left\s*\{[^}]+\}\s*/g, '');
d = d.replace(/\.play-v3 \.encursos-movil \.enc-mov-escena\s*\{[^}]+\}\s*/g, '');
d = d.replace(/\.play-v3 \.encursos-movil \.enc-mov-lugar-stamp\s*\{[^}]+\}\s*/g, '');
d = d.replace(/\.play-v3 \.encursos-movil \.enc-mov-lugar-stamp img\s*\{[^}]+\}\s*/g, '');
d = d.replace(/\.play-v3 \.encursos-movil \.enc-mov-escena-core\s*\{[^}]+\}\s*/g, '');

const marker = '/* INICIO-DESKTOP-LAYOUT';
const idx = d.indexOf(marker);
if (idx >= 0) {
  d = d.slice(0, idx).trimEnd() + '\n\n' + gridBlock + '\n';
} else {
  d = d.trimEnd() + '\n\n' + gridBlock + '\n';
}

fs.writeFileSync(p, d);
console.log('patch_inicio_desktop_grid OK');
