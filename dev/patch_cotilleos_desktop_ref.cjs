'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');

const icoSvg =
  "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E" +
  "%3Ccircle cx='12' cy='12' r='11' fill='%236a5890'/%3E" +
  "%3Cpath d='M7 8.5h10a1.5 1.5 0 0 1 1.5 1.5v4.5a1.5 1.5 0 0 1-1.5 1.5h-4.8l-2.2 1.8V16H7a1.5 1.5 0 0 1-1.5-1.5v-4.5A1.5 1.5 0 0 1 7 8.5z' fill='%23ffffff'/%3E" +
  "%3Ccircle cx='9.2' cy='12' r='.65' fill='%236a5890'/%3E" +
  "%3Ccircle cx='12' cy='12' r='.65' fill='%236a5890'/%3E" +
  "%3Ccircle cx='14.8' cy='12' r='.65' fill='%236a5890'/%3E" +
  "%3C/svg%3E";

const heartSvg =
  "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E" +
  "%3Cpath d='M12 20.4s-6.8-4.2-6.8-8.6A3.7 3.7 0 0 1 12 9.8a3.7 3.7 0 0 1 6.8 2c0 4.4-6.8 8.6-6.8 8.6z' fill='none' stroke='%23b8a4d8' stroke-width='1.5'/%3E" +
  "%3C/svg%3E";

const blockOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
    width: 100%;
    min-height: 0;
    padding: 12px 14px 14px;
    border-radius: 16px 14px 18px 15px / 14px 17px 15px 16px;
    border: 1.5px solid rgba(106, 88, 142, .38);
    background: linear-gradient(180deg, #ebe3f8 0%, #e4daf2 100%);
    box-shadow: 0 2px 8px rgba(90, 70, 120, .1);
    text-align: left;
    cursor: pointer;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par .obj-cotilleo-ico {
    flex: 0 0 auto;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background-color: #fff;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M5 5h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-6l-4 3v-3H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z' fill='%235e3e90'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: center;
    background-size: 18px 18px;
    border: 1.5px solid rgba(106, 88, 142, .28);
    box-shadow: 0 1px 3px rgba(70, 50, 90, .12);
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par .obj-cotilleo-cuerpo {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 6px;
    min-width: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par .obj-cotilleo-badges {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par .obj-cotilleo-tit {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .72rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #5e3e90;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par .obj-cotilleo-txt {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 600;
    font-size: .78rem;
    line-height: 1.35;
    color: var(--ds-ink, #2a2218);
    white-space: normal;
    overflow: visible;
    text-overflow: unset;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par .obj-cotilleo-flecha {
    display: none;
  }`;

const blockNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par {
    display: grid;
    grid-template-columns: auto 1fr;
    grid-template-rows: auto auto;
    column-gap: 10px;
    row-gap: 8px;
    align-items: center;
    width: 100%;
    min-height: 0;
    padding: 12px 40px 14px 14px;
    border-radius: 16px;
    border: 1.5px solid rgba(106, 88, 142, .32);
    background: linear-gradient(180deg, #ebe3f8 0%, #e4daf2 100%);
    box-shadow: none;
    filter: none;
    transform: none;
    text-align: left;
    cursor: pointer;
    position: relative;
    overflow: visible;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par::before {
    display: none;
    content: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par::after {
    content: "";
    position: absolute;
    top: 10px;
    right: 12px;
    width: 26px;
    height: 26px;
    background: url("${heartSvg}") center / contain no-repeat;
    opacity: .45;
    pointer-events: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par .obj-cotilleo-ico {
    grid-column: 1;
    grid-row: 1;
    flex: 0 0 auto;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: transparent;
    background-image: url("${icoSvg}");
    background-repeat: no-repeat;
    background-position: center;
    background-size: 32px 32px;
    border: none;
    box-shadow: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par .obj-cotilleo-cuerpo {
    display: contents;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par .obj-cotilleo-badges {
    grid-column: 2;
    grid-row: 1;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 8px;
    min-width: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par .obj-cotilleo-tit {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .9rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #5e3e90;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par .obj-cotilleo-tit::before {
    content: "# ";
    color: #5e3e90;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par .obj-cotilleo-txt {
    grid-column: 1 / -1;
    grid-row: 2;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 600;
    font-size: .82rem;
    line-height: 1.38;
    color: var(--ds-ink, #2a2218);
    white-space: normal;
    overflow: visible;
    text-overflow: unset;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-cotilleo.obj-cotilleo-par .obj-cotilleo-flecha {
    display: none;
  }`;

if (!css.includes(blockOld)) {
  console.error('patch_cotilleos_desktop_ref: bloque cotilleos no encontrado');
  process.exit(1);
}

css = css.replace(blockOld, blockNew);
fs.writeFileSync(file, css, 'utf8');
console.log('patch_cotilleos_desktop_ref OK');
