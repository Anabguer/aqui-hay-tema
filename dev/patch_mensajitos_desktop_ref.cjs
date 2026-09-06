'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');

const buzonOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    min-height: 64px;
    padding: 10px 14px;
    background: #fff;
    border: 1.5px solid rgba(198, 176, 210, .42);
    border-radius: 16px 14px 18px 15px / 14px 17px 15px 16px;
    box-shadow: 0 2px 8px rgba(70, 55, 85, .08);
    cursor: pointer;
    text-align: left;
  }`;

const buzonNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon {
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

const imgOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-img {
    width: 44px;
    height: auto;
  }`;

const imgNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-img {
    width: 30px;
    height: 24px;
    object-fit: contain;
    filter: brightness(0) saturate(100%) invert(52%) sepia(35%) saturate(1800%) hue-rotate(305deg) brightness(96%) contrast(92%);
  }`;

const txtOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-txt {
    flex: 1 1 auto;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .72rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #7a7164;
  }`;

const txtNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-txt {
    flex: 1 1 auto;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .82rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #2a2218;
  }`;

const badgeOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-badge {
    flex: 0 0 auto;
    min-width: 24px;
    height: 24px;
    padding: 0 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f3c4d0;
    border: 2px solid #fff;
    border-radius: 999px;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .72rem;
    color: #9a3d58;
    box-shadow: 0 1px 3px rgba(196, 43, 74, .15);
  }`;

const badgeNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .obj-buzon-badge {
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

const pairs = [
  [buzonOld, buzonNew, 'obj-buzon'],
  [imgOld, imgNew, 'obj-buzon-img'],
  [txtOld, txtNew, 'obj-buzon-txt'],
  [badgeOld, badgeNew, 'obj-buzon-badge'],
];

for (const [oldBlock, newBlock, name] of pairs) {
  if (!css.includes(oldBlock)) {
    console.error('patch_mensajitos_desktop_ref: no encontrado', name);
    process.exit(1);
  }
  css = css.replace(oldBlock, newBlock);
}

fs.writeFileSync(file, css, 'utf8');
console.log('patch_mensajitos_desktop_ref OK');
