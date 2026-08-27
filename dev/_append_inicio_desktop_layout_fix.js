'use strict';
const fs = require('fs');
const path = require('path');

const cssPath = path.join(__dirname, '..', 'assets/css/design-system/screens/inicio-desktop.css');
let css = fs.readFileSync(cssPath, 'utf8');

css = css.replace(/\n\/\* INICIO-DESKTOP-LAYOUT-FIX[\s\S]*$/, '');

const block = `
/* INICIO-DESKTOP-LAYOUT-FIX — mapa centro + icono prox planes (display:contents) */
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
    z-index: 1 !important;
  }

  .inicio-stage .inicio-desktop-right {
    grid-column: 3 !important;
    grid-row: 2 !important;
  }

  /* .pp-mov-ico (SVG calendario) heredaba el ancho de columna (~320px) */
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
console.log('OK replaced INICIO-DESKTOP-LAYOUT-FIX');
