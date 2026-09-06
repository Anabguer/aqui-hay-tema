#!/usr/bin/env node
'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

const mapaPath = path.join(root, 'assets/css/inicio/inicio-mapa.css');
let mapa = fs.readFileSync(mapaPath, 'utf8');

const oldDesk = `@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host {
    width: 100%;
    min-width: 0;
    align-self: start;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 0;
    position: relative;
    overflow: visible;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .play-stage {
    flex: 1 1 auto;
    height: 100%;
    min-height: 0;
    overflow: hidden;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .play-root.pc {
    height: 100%;
    min-height: 0;
    overflow: hidden;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .board-scroll {
    position: absolute;
    inset: 0;
    width: 100%;
    height: auto;
    overflow: hidden;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .board-fit {
    width: 100%;
    max-height: min(76vh, 660px);
    position: relative;
    overflow: hidden;
  }
}`;

const newDesk = `@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host {
    width: 100%;
    min-width: 0;
    align-self: start;
    display: block;
    padding: 0;
    position: relative;
    overflow: visible;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .play-stage {
    position: relative;
    min-height: 0;
    overflow: visible;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .play-root.pc {
    height: auto;
    min-height: 0;
    overflow: visible;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .board-scroll {
    position: static;
    inset: auto;
    width: 100%;
    height: auto;
    overflow: hidden;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .board-fit {
    width: 100%;
    height: auto;
    aspect-ratio: 618 / 404;
    max-height: min(76vh, 660px);
    position: relative;
    overflow: hidden;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .mapa-canonico,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .mapa-canonico-bg {
    width: 100%;
    height: 100%;
    object-fit: fill;
  }
}`;

if (!mapa.includes(oldDesk)) {
  console.error('patch_inicio_mapa_margen_desktop: bloque desktop mapa no encontrado');
  process.exit(1);
}
mapa = mapa.replace(oldDesk, newDesk);

const oldPngDesk = `@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .board-fit {
    min-height: 360px;
    max-height: min(76vh, 660px);
  }
}
`;
if (mapa.includes(oldPngDesk)) {
  mapa = mapa.replace(oldPngDesk, '');
}
fs.writeFileSync(mapaPath, mapa);
console.log('OK', 'assets/css/inicio/inicio-mapa.css');

const deskPath = path.join(root, 'assets/css/inicio/inicio-desktop.css');
let desk = fs.readFileSync(deskPath, 'utf8');
const oldGrid = `    grid-template-columns: minmax(328px, 400px) minmax(0, 1fr) minmax(282px, 362px);
    grid-template-areas:
      "head head head"
      "left map  right";
    column-gap: 14px;
    row-gap: 10px;
    align-items: start;
    padding: 0 14px 14px;
    box-sizing: border-box;`;
const newGrid = `    grid-template-columns: minmax(220px, 250px) minmax(0, 1fr) minmax(220px, 250px);
    grid-template-areas:
      "head head head"
      "left map  right";
    column-gap: 10px;
    row-gap: 10px;
    align-items: start;
    width: 100%;
    max-width: 100%;
    padding: 10px;
    box-sizing: border-box;`;
if (!desk.includes(oldGrid)) {
  console.error('patch_inicio_mapa_margen_desktop: grid inicio-stage no encontrado');
  process.exit(1);
}
desk = desk.replace(oldGrid, newGrid);
fs.writeFileSync(deskPath, desk);
console.log('OK', 'assets/css/inicio/inicio-desktop.css');
