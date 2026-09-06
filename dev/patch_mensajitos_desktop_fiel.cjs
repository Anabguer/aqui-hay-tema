'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');

const sobreSvg =
  "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 36 28' fill='none'%3E" +
  "%3Crect x='2' y='6' width='32' height='20' rx='3' stroke='%23d4637f' stroke-width='2.2'/%3E" +
  "%3Cpath d='M2 8.5 L18 18.5 L34 8.5' stroke='%23d4637f' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'/%3E" +
  "%3C/svg%3E";

const buzonOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    min-height: 56px;
    padding: 11px 16px;
    background: #fff;
    border: 1.5px solid rgba(232, 90, 120, .55);
    border-radius: 999px;
    box-shadow:
      0 0 0 1px rgba(232, 90, 120, .12),
      0 0 10px rgba(232, 90, 120, .22),
      0 0 22px rgba(232, 90, 120, .14);
    filter: none;
    cursor: pointer;
    text-align: left;
  }`;

const buzonNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon {
    display: flex;
    align-items: center;
    gap: 14px;
    width: 100%;
    min-height: 68px;
    padding: 14px 18px;
    background: #fff;
    border: 1.5px solid rgba(232, 90, 120, .55);
    border-radius: 14px;
    box-shadow:
      0 0 0 1px rgba(232, 90, 120, .10),
      0 4px 14px rgba(232, 90, 120, .20);
    filter: none;
    cursor: pointer;
    text-align: left;
  }`;

const wrapOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-ico-wrap {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
  }`;

const wrapNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-ico-wrap {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 30px;
    background: url("${sobreSvg}") center / contain no-repeat;
  }`;

const imgOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-img {
    width: 30px;
    height: 24px;
    object-fit: contain;
    filter: brightness(0) saturate(100%) invert(52%) sepia(35%) saturate(1800%) hue-rotate(305deg) brightness(96%) contrast(92%);
  }`;

const imgNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-img {
    display: none;
    width: 0;
    height: 0;
    object-fit: contain;
    filter: none;
  }`;

const txtOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-txt {
    flex: 1 1 auto;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .82rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #2a2218;
  }`;

const txtNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-txt {
    flex: 1 1 auto;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .98rem;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: #2a2218;
  }`;

const badgeOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-badge {
    flex: 0 0 auto;
    min-width: 26px;
    height: 26px;
    padding: 0 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #e85a78;
    border: none;
    border-radius: 999px;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .78rem;
    line-height: 1;
    color: #fff;
    box-shadow: none;
  }`;

const badgeNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-badge {
    flex: 0 0 auto;
    min-width: 28px;
    height: 28px;
    padding: 0 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #e85a78;
    border: none;
    border-radius: 999px;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .82rem;
    line-height: 1;
    color: #fff;
    box-shadow: none;
  }`;

const pairs = [
  [buzonOld, buzonNew, 'obj-buzon'],
  [wrapOld, wrapNew, 'obj-buzon-ico-wrap'],
  [imgOld, imgNew, 'obj-buzon-img'],
  [txtOld, txtNew, 'obj-buzon-txt'],
  [badgeOld, badgeNew, 'obj-buzon-badge'],
];

for (const [oldBlock, newBlock, name] of pairs) {
  if (!css.includes(oldBlock)) {
    console.error('patch_mensajitos_desktop_fiel: no encontrado', name);
    process.exit(1);
  }
  css = css.replace(oldBlock, newBlock);
}

fs.writeFileSync(file, css, 'utf8');
console.log('patch_mensajitos_desktop_fiel OK');
