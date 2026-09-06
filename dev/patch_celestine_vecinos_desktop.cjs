'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');

const cardOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota.obj-vecinos-resumen::before {
    content: "";
    position: absolute;
    top: -7px;
    left: 14px;
    width: 42px;
    height: 16px;
    background: linear-gradient(180deg, #f5e6a8 0%, #e8d48c 100%);
    border: 1px solid rgba(160, 130, 60, .35);
    border-radius: 2px;
    transform: rotate(-4deg);
    box-shadow: 0 1px 2px rgba(80, 60, 20, .12);
    pointer-events: none;
  }`;

const cardNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota.obj-vecinos-resumen::before {
    content: "";
    position: absolute;
    top: 12px;
    right: 14px;
    left: auto;
    width: 24px;
    height: 24px;
    background: url("../play-v3/capas/chincheta.png") center / contain no-repeat;
    border: none;
    border-radius: 0;
    transform: none;
    box-shadow: none;
    pointer-events: none;
  }`;

const kickerOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .libreta-kicker {
    display: block;
    margin-bottom: 6px;
    font-family: var(--ds-font-hand, Caveat, cursive);
    font-size: 1.05rem;
    font-weight: 700;
    color: #c85b78;
    transform: rotate(-1deg);
  }`;

const kickerNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .libreta-kicker {
    display: block;
    margin: 0 0 8px;
    padding-right: 30px;
    text-align: left;
    font-family: var(--ds-font-hand, Caveat, cursive);
    font-size: 1.42rem;
    font-weight: 700;
    line-height: 1.05;
    color: #2a2218;
    transform: none;
  }`;

const previewOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-preview {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin: 4px 0 8px;
  }`;

const previewNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-preview {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin: 6px 0 12px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-preview-cara-wrap,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-preview-cara {
    width: 54px;
    height: 54px;
    flex: 0 0 54px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(55, 45, 38, .22);
    box-sizing: border-box;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-preview-cara-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 0;
    background: #fff;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-preview-ini {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: 1rem;
    color: #5e3e90;
    background: #f3eff7;
  }`;

const titOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-tit {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .72rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #c85b78;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-poblacion {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 700;
    font-size: .68rem;
    color: #7a7164;
  }`;

const titNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-tit {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .9rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #c85b78;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-poblacion {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .86rem;
    color: #2a2218;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-stats .celeste-cuenta-vecinos {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-stats .stat-row,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-stats .vecinos-stat {
    font-size: 1.12rem;
    padding: .38rem 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-stats .vecinos-stat-ico {
    font-size: 1.22rem;
    width: 1.5rem;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-stats .vecinos-stat-k,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-stats .stat-row > span {
    font-size: 1.08rem;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-stats .stat-row strong,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .celestine-nota .obj-vecinos-stats .vecinos-stat-v {
    font-size: 1.16rem;
  }`;

const pairs = [
  [cardOld, cardNew, 'chincheta'],
  [kickerOld, kickerNew, 'kicker'],
  [previewOld, previewNew, 'preview'],
  [titOld, titNew, 'tit/poblacion/stats'],
];

for (const [oldBlock, newBlock, name] of pairs) {
  if (!css.includes(oldBlock)) {
    console.error('patch_celestine_vecinos_desktop: no encontrado', name);
    process.exit(1);
  }
  css = css.replace(oldBlock, newBlock);
}

fs.writeFileSync(file, css, 'utf8');
console.log('patch_celestine_vecinos_desktop OK');
