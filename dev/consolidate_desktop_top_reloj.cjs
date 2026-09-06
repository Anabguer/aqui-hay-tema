'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

function read(p) { return fs.readFileSync(path.join(root, p), 'utf8'); }
function write(p, c) { fs.writeFileSync(path.join(root, p), c, 'utf8'); }

const S = '.play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top';

const relojCanon = `
  ${S} .top-reloj {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 12px;
    flex-wrap: nowrap;
    background: transparent;
    border: none;
    box-shadow: none;
  }

  ${S} .obj-dia,
  ${S} .obj-dia-cuerpo::before,
  ${S} .obj-dia-cuerpo::after,
  ${S} .obj-dia::after {
    box-shadow: none;
  }

  ${S} .obj-dia-cuerpo::before,
  ${S} .obj-dia-cuerpo::after,
  ${S} .obj-dia::after {
    display: none;
    content: none;
    background: none;
    border: none;
    width: 0;
    height: 0;
  }

  ${S} .obj-dia {
    display: grid;
    grid-template-columns: auto 1fr;
    grid-template-rows: auto auto;
    column-gap: 10px;
    row-gap: 2px;
    align-items: center;
    width: auto;
    min-width: 0;
    min-height: 52px;
    padding: 10px 14px;
    box-sizing: border-box;
    transform: none;
    background: #fff;
    border: 1.5px solid #d9d2e1;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(70, 55, 85, .08);
  }

  ${S} .obj-dia::before {
    content: "";
    grid-row: 1 / 3;
    grid-column: 1;
    width: 28px;
    height: 28px;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='3' y='5' width='18' height='16' rx='3' fill='none' stroke='%23e85a78' stroke-width='1.8'/%3E%3Cpath d='M3 9h18M8 3v4M16 3v4' stroke='%23e85a78' stroke-width='1.8' stroke-linecap='round'/%3E%3Ccircle cx='9' cy='14' r='1' fill='%23e85a78'/%3E%3Ccircle cx='12' cy='14' r='1' fill='%23e85a78'/%3E%3Ccircle cx='15' cy='14' r='1' fill='%23e85a78'/%3E%3C/svg%3E") center / contain no-repeat;
  }

  ${S} .obj-dia-placa {
    grid-column: 2;
    grid-row: 1;
    margin: 0;
    padding: 0;
    background: none;
    border: none;
    box-shadow: none;
    width: auto;
  }

  ${S} .obj-dia-placa::before,
  ${S} .obj-dia-placa::after {
    display: none;
    content: none;
  }

  ${S} .obj-dia-cuerpo {
    grid-column: 2;
    grid-row: 2;
    margin: 0;
    padding: 0;
    background: none;
    border: none;
    width: auto;
  }

  ${S} .obj-dia-num {
    display: block;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: 1rem;
    font-weight: 800;
    font-style: normal;
    line-height: 1.1;
    color: #33261e;
  }

  ${S} .obj-dia-num::before {
    content: none;
    display: none;
  }

  ${S} .obj-dia-meta {
    display: block;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: .78rem;
    font-weight: 600;
    line-height: 1.1;
    color: #6b5f78;
  }

  ${S} .obj-hora {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 10px;
    width: auto;
    min-width: 0;
    min-height: 52px;
    padding: 10px 16px;
    box-sizing: border-box;
    transform: none;
    background: #fff;
    border: 1.5px solid #d9d2e1;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(70, 55, 85, .08);
  }

  ${S} .obj-hora::before,
  ${S} .obj-hora::after {
    display: none;
    content: none;
  }

  ${S} .obj-hora-ico {
    position: static;
    top: auto;
    right: auto;
    width: 28px;
    height: 28px;
    margin: 0;
    flex-shrink: 0;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Ccircle cx='12' cy='12' r='9' fill='none' stroke='%237c6bae' stroke-width='1.8'/%3E%3Cpath d='M12 7v5l3 2' stroke='%237c6bae' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center / contain no-repeat;
  }

  ${S} .obj-hora-ico::after {
    display: none;
    content: none;
  }

  ${S} .obj-hora-val {
    display: block;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: 1rem;
    font-weight: 800;
    font-style: normal;
    line-height: 1.1;
    color: #33261e;
  }

  ${S} .pasar-rato {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 16px;
    margin: 0;
    background: #ebe3f8;
    border: 1.5px solid #b8a4d8;
    border-radius: 999px;
    box-shadow: none;
    color: #5e3e90;
    transform: none;
    cursor: pointer;
    font: inherit;
  }

  ${S} .pasar-rato-txt {
    display: inline;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: .88rem;
    font-weight: 700;
    line-height: 1;
    letter-spacing: .01em;
    white-space: nowrap;
    color: #5e3e90;
  }

  ${S} .pasar-rato-ico {
    font-size: .72rem;
    line-height: 1;
    color: #5e3e90;
  }
`;

let desk = read('assets/css/inicio/inicio-desktop.css');

// Remove corrective blocks
desk = desk.replace(
  /\n\/\* INICIO-DESKTOP-TOP-RELOJ-20260906[\s\S]*?\n\}\n(?=\n\/\* INICIO-DESKTOP-VIDA-SCALE|\n\/\* INICIO-DESKTOP-BRAND-ALIGN|\n\/\* INICIO-DESKTOP-TOP-RELOJ-FIX|$)/,
  '\n'
);
desk = desk.replace(
  /\n\/\* INICIO-DESKTOP-TOP-RELOJ-FIX-20260906 \*\/[\s\S]*?\n\}\n?$/,
  '\n'
);

// Replace old top-reloj in CABECERA
desk = desk.replace(
  /  \.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.top-reloj \{[\s\S]*?box-shadow: none;\n  \}/,
  relojCanon.trim()
);

// FIEL legacy: btn-guia y obj-dia-meta ya viven en CABECERA-RELOJ-CANON
desk = desk.replace(
  /\/\* INICIO-FIEL-PANTALLAZOS-20260906 \*\/\n@media \(min-width: 769px\) \{[\s\S]*?\.obj-dia-cuerpo \.obj-dia-meta \{[\s\S]*?\}\n\}\n/,
  '/* INICIO-FIEL-PANTALLAZOS-20260906 — migrado a CABECERA-RELOJ-CANON */\n'
);
desk = desk.replace(
  /@media \(min-width: 769px\) \{\n  \.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.obj-dia-cuerpo \.obj-dia-meta \{[\s\S]*?\}\n\}\n/,
  ''
);

if (!desk.includes('INICIO-DESKTOP-CABECERA-RELOJ-CANON-20260906')) {
  desk = desk.replace(
    '/* INICIO-DESKTOP-CABECERA-20260906 */',
    '/* INICIO-DESKTOP-CABECERA-20260906 + INICIO-DESKTOP-CABECERA-RELOJ-CANON-20260906 */'
  );
}

write('assets/css/inicio/inicio-desktop.css', desk);

// Cromatica: quitar duplicados estructurales; solo hover suave pasar-rato
let croma = read('assets/css/inicio/inicio-cromatica-desktop.css');
croma = croma.replace(
  /\/\* INICIO-TOP-RELOJ-CROMATICA-DESKTOP-v108[\s\S]*?@media \(min-width: 769px\) \{[\s\S]*?\n\}\n/,
  '/* INICIO-TOP-RELOJ-CROMATICA-DESKTOP-v108 — migrado a inicio-desktop.css CABECERA-RELOJ-CANON */\n'
);

croma = croma.replace(
  /  \.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.pasar-rato,\n  \.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.pasar-rato\.pasar-rato--noche \{[\s\S]*?transform: rotate\(-1deg\);\n  \}/,
  `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .pasar-rato:hover {
    background: #f0e8fa;
  }`
);

write('assets/css/inicio/inicio-cromatica-desktop.css', croma);

console.log('consolidate_desktop_top_reloj OK');
