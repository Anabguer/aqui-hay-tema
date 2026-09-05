#!/usr/bin/env node
'use strict';
const fs = require('fs');
const path = require('path');
const p = path.join(__dirname, '..', 'assets/css/inicio/inicio-base.css');
let b = fs.readFileSync(p, 'utf8');
const block = `
/* INICIO-LATERALES-DESKTOP — estructura columnas derecha (autoridad inicio-base) */
@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .encursos-movil .enc-mov-escena {
    grid-template-columns: auto minmax(0, 1fr);
    align-items: start;
    gap: 8px 10px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .encursos-movil .enc-mov-lugar-stamp {
    flex: 0 0 auto;
    width: 58px;
    min-width: 58px;
    max-width: 58px;
    padding: 4px 4px 9px;
    margin-top: 2px;
    box-sizing: border-box;
    align-self: start;
    grid-row: 1 / span 2;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .encursos-movil .enc-mov-lugar-stamp img {
    display: block;
    width: 100%;
    height: 40px;
    max-width: 50px;
    max-height: 40px;
    object-fit: cover;
    object-position: center bottom;
    border-radius: 2px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .encursos-movil .enc-mov-escena-core {
    align-items: stretch;
    text-align: center;
    gap: 5px;
  }
}
`;
if (!b.includes('INICIO-LATERALES-DESKTOP')) {
  fs.writeFileSync(p, b.trimEnd() + block + '\n');
}
console.log('patch_inicio_base_laterals OK');
