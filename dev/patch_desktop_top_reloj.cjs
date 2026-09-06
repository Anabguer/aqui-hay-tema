'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const file = path.join(root, 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8');

const marker = 'INICIO-DESKTOP-TOP-RELOJ-20260906';
const block = `
/* ${marker} — pastillas día/hora + Pasar el rato (ref cabecera desktop) */
@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .top-reloj {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 12px;
    flex-wrap: nowrap;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia {
    display: grid;
    grid-template-columns: auto 1fr;
    grid-template-rows: auto auto;
    column-gap: 10px;
    row-gap: 2px;
    align-items: center;
    width: auto;
    min-width: 0;
    padding: 10px 14px;
    transform: none;
    background: #fff;
    border: 1.5px solid #d9d2e1;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(70, 55, 85, .08);
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia::before {
    content: "";
    grid-row: 1 / 3;
    grid-column: 1;
    width: 28px;
    height: 28px;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='3' y='5' width='18' height='16' rx='3' fill='none' stroke='%23e85a78' stroke-width='1.8'/%3E%3Cpath d='M3 9h18M8 3v4M16 3v4' stroke='%23e85a78' stroke-width='1.8' stroke-linecap='round'/%3E%3Ccircle cx='9' cy='14' r='1' fill='%23e85a78'/%3E%3Ccircle cx='12' cy='14' r='1' fill='%23e85a78'/%3E%3Ccircle cx='15' cy='14' r='1' fill='%23e85a78'/%3E%3C/svg%3E") center / contain no-repeat;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-placa {
    grid-column: 2;
    grid-row: 1;
    margin: 0;
    padding: 0;
    background: none;
    border: none;
    box-shadow: none;
    width: auto;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-placa::before,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-placa::after {
    display: none;
    content: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-cuerpo {
    grid-column: 2;
    grid-row: 2;
    margin: 0;
    padding: 0;
    background: none;
    border: none;
    box-shadow: none;
    width: auto;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-num {
    display: block;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: 1rem;
    font-weight: 800;
    font-style: normal;
    line-height: 1.1;
    color: #33261e;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-num::before {
    content: "Día ";
    font-weight: 800;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-meta {
    display: block;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: .78rem;
    font-weight: 600;
    line-height: 1.1;
    color: #6b5f78;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 10px;
    width: auto;
    min-width: 0;
    padding: 10px 16px;
    transform: none;
    background: #fff;
    border: 1.5px solid #d9d2e1;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(70, 55, 85, .08);
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora::before,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora::after {
    display: none;
    content: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora-ico {
    position: static;
    top: auto;
    right: auto;
    width: 28px;
    height: 28px;
    margin: 0;
    flex-shrink: 0;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Ccircle cx='12' cy='12' r='9' fill='none' stroke='%237c6bae' stroke-width='1.8'/%3E%3Cpath d='M12 7v5l3 2' stroke='%237c6bae' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center / contain no-repeat;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora-ico::after {
    display: none;
    content: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora-val {
    display: block;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: 1rem;
    font-weight: 800;
    font-style: normal;
    line-height: 1.1;
    color: #33261e;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .pasar-rato {
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

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .pasar-rato-txt {
    display: inline;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: .88rem;
    font-weight: 700;
    line-height: 1;
    letter-spacing: .01em;
    white-space: nowrap;
    color: #5e3e90;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .pasar-rato-ico {
    font-size: .72rem;
    line-height: 1;
    color: #5e3e90;
  }
}
`;

const re = new RegExp('/\\* ' + marker + '[\\s\\S]*?(?=\\n/\\*|$)', 'm');
if (re.test(css)) css = css.replace(re, block.trim() + '\n');
else css = css.trimEnd() + '\n\n' + block.trim() + '\n';

fs.writeFileSync(file, css, 'utf8');
console.log('patch_desktop_top_reloj OK');
