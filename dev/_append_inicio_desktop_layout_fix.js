'use strict';
const fs = require('fs');
const path = require('path');

const cssPath = path.join(__dirname, '..', 'assets/css/design-system/screens/inicio-desktop.css');
let css = fs.readFileSync(cssPath, 'utf8');

css = css.replace(/\n\/\* INICIO-DESKTOP-LAYOUT-FIX[\s\S]*$/, '');

const block = `
/* INICIO-DESKTOP-LAYOUT-FIX — grid stage + mapa visible en host compartido */
@media (min-width: 769px) {
  .inicio-stage {
    display: grid !important;
    grid-template-columns: minmax(300px, 360px) minmax(320px, 1fr) minmax(260px, 340px) !important;
    grid-template-rows: auto auto !important;
    gap: 14px !important;
    padding: 0 14px 14px !important;
    align-items: start !important;
  }

  .inicio-stage > .inicio-desktop,
  .inicio-stage .inicio-desktop-layout {
    display: contents !important;
  }

  .inicio-stage .inicio-desktop > .game-top {
    grid-column: 1 / -1 !important;
    grid-row: 1 !important;
  }

  .inicio-stage .inicio-desktop-left {
    grid-column: 1 !important;
    grid-row: 2 !important;
  }

  .inicio-stage > .inicio-map-host {
    grid-column: 2 !important;
    grid-row: 2 !important;
    align-self: start !important;
    justify-self: stretch !important;
    width: 100% !important;
    min-width: 0 !important;
    min-height: 320px !important;
    z-index: 1 !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: visible !important;
  }

  /* Host compartido: reglas legacy .inicio-desktop .game-map-wrap no aplican (mapa fuera de .inicio-desktop) */
  .play-v3 .inicio-stage > .inicio-map-host .play-stage {
    flex: 1 1 auto !important;
    width: 100% !important;
    min-height: 280px !important;
    height: 100% !important;
    overflow: hidden !important;
  }

  .play-v3 .inicio-stage > .inicio-map-host .play-root.pc {
    min-height: 260px !important;
    height: 100% !important;
    overflow: hidden !important;
  }

  .play-v3 .inicio-stage > .inicio-map-host .board-scroll {
    position: absolute !important;
    inset: 0 !important;
    width: 100% !important;
    height: 100% !important;
    min-height: 260px !important;
    overflow: hidden !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
  }

  .play-v3 .inicio-stage > .inicio-map-host .board-fit,
  .play-v3 .inicio-stage > .inicio-map-host .play-root.pc .board-fit {
    position: relative !important;
    top: auto !important;
    width: 91% !important;
    max-height: min(76vh, 660px) !important;
    height: auto !important;
    aspect-ratio: 618 / 404 !important;
    margin: 0 auto !important;
  }

  .play-v3 .inicio-stage > .inicio-map-host .mapa-canonico,
  .play-v3 .inicio-stage > .inicio-map-host .mapa-zonas-layer {
    position: absolute !important;
    inset: 0 !important;
    width: 100% !important;
    height: 100% !important;
  }

  .inicio-stage .inicio-desktop-right {
    grid-column: 3 !important;
    grid-row: 2 !important;
  }

  .inicio-desktop-right .plan-seccion-cab {
    display: flex !important;
    align-items: center !important;
    gap: 7px !important;
  }

  .inicio-desktop-right .plan-seccion-ico,
  .inicio-desktop-right .pp-mov-ico,
  .inicio-desktop-right .enc-mov-ico {
    width: 1.125rem !important;
    height: 1.125rem !important;
    flex: 0 0 1.125rem !important;
    max-width: 1.125rem !important;
    max-height: 1.125rem !important;
  }

  .inicio-desktop-right .pp-mov-ico {
    color: var(--ds-lavender-deep, #7c6bae) !important;
  }
}
`;

css = css.trimEnd() + block;
fs.writeFileSync(cssPath, css);
console.log('OK updated INICIO-DESKTOP-LAYOUT-FIX with map-host visibility');
