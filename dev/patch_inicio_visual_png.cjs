'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

const MARKER_MOB = '/* INICIO-VISUAL-PNG-20260906 — movil */';
const MARKER_DESK = '/* INICIO-VISUAL-PNG-20260906 — desktop */';
const MARKER_MAP = '/* INICIO-VISUAL-PNG-20260906 — mapa */';

function upsert(file, marker, block) {
  let css = fs.readFileSync(file, 'utf8');
  const start = css.indexOf(marker);
  if (start >= 0) {
    const next = css.indexOf('\n/* INICIO-', start + marker.length);
    const end = next >= 0 ? next : css.length;
    css = css.slice(0, start) + block.trim() + '\n\n' + css.slice(end).replace(/^\n+/, '');
  } else {
    css = css.trimEnd() + '\n\n' + block.trim() + '\n';
  }
  fs.writeFileSync(file, css, 'utf8');
  console.log('patched', path.relative(root, file));
}

const mobBlock = `
${MARKER_MOB}
@media (max-width: 768px) {
  .play-v3 .inicio-stage {
    background: #f3eef8;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mobile-feed-inner {
    padding: 0 13px 12px;
    gap: 10px;
    background: transparent;
  }

  body.play-v3 .inicio-mobile.inicio-mobile-feed {
    background: transparent;
  }

  /* Cabecera — píldora blanca con borde rosa (ref 01) */
  .play-v3 .inicio-stage .inicio-mobile .inicio-temporal-pill {
    background: #fff;
    border: 1.5px solid rgba(200, 91, 120, .55);
    box-shadow: 0 1px 0 rgba(200, 91, 120, .12);
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-header-brand-row {
    margin-bottom: 8px;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-header-card {
    padding: 9px 11px 11px;
  }

  /* Tiles MENSAJITOS / VECINOS / PLAN */
  .play-v3 .inicio-stage .inicio-mobile .inicio-mobile-tiles .obj-buzon {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    background: #fff;
    border: 1.5px solid rgba(55, 45, 38, .22);
    box-shadow: 0 2px 8px rgba(70, 50, 30, .07);
    padding: 8px 6px 7px;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-mobile-tiles .obj-buzon-flecha {
    display: none;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-mobile-tiles .celestine-nota.obj-vecinos-resumen {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    background: linear-gradient(180deg, #fff8fa 0%, #fdeef3 100%);
    border: 1.5px solid rgba(232, 120, 152, .45);
    box-shadow: 0 2px 8px rgba(196, 43, 74, .08);
    padding: 8px 6px 7px;
    text-align: center;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-mobile-tiles .obj-vecinos-poblacion {
    display: none;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-mobile-tiles .obj-vecinos-total-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 22px;
    padding: 0 5px;
    background: #f3c4d0;
    border: 2px solid #fff;
    border-radius: 999px;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .7rem;
    color: #9a3d58;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-mobile-tiles .obj-vecinos-total-badge[hidden] {
    display: none;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-mobile-tiles .celestine-nota.obj-vecinos-resumen {
    position: relative;
  }

  /* Cotilleos — barra horizontal lavanda */
  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .obj-cotilleo.obj-cotilleo-compact {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    min-height: 52px;
    padding: 8px 10px 8px 8px;
    margin: 0;
    background: linear-gradient(180deg, #e8dcf5 0%, #ddd0f0 100%);
    border: 1.5px solid rgba(106, 88, 142, .38);
    border-radius: 999px;
    box-shadow: 0 2px 6px rgba(90, 70, 120, .1);
    text-align: left;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .obj-cotilleo.obj-cotilleo-compact .obj-cotilleo-ico {
    flex: 0 0 32px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #fff;
    border: 1.5px solid rgba(106, 88, 142, .25);
    box-shadow: 0 1px 3px rgba(70, 50, 90, .12);
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .obj-cotilleo.obj-cotilleo-compact .obj-cotilleo-cuerpo {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    flex: 1 1 auto;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .obj-cotilleo.obj-cotilleo-compact .obj-cotilleo-badges {
    flex: 0 0 auto;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .obj-cotilleo.obj-cotilleo-compact .obj-cotilleo-tit {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    background: #5e3e90;
    border-radius: 999px;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .62rem;
    letter-spacing: .06em;
    color: #fff;
    line-height: 1.2;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .obj-cotilleo.obj-cotilleo-compact .obj-cotilleo-badge {
    display: none;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .obj-cotilleo.obj-cotilleo-compact .obj-cotilleo-txt {
    flex: 1 1 auto;
    min-width: 0;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 700;
    font-size: .72rem;
    line-height: 1.25;
    color: var(--ds-ink, #2a2218);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .obj-cotilleo.obj-cotilleo-compact .obj-cotilleo-flecha {
    flex: 0 0 auto;
    font-size: 1.1rem;
    line-height: 1;
    color: rgba(90, 70, 120, .65);
  }

  /* Planes — bloque con spine vertical */
  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .planes-movil-unif {
    margin: 0;
    padding: 0;
    background: transparent;
    border: none;
    box-shadow: none;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .planes-unif-body {
    display: flex;
    align-items: stretch;
    gap: 0;
    min-height: 118px;
    background: #f4f8fc;
    border: 1.5px solid rgba(120, 160, 200, .35);
    border-radius: 16px 14px 18px 15px / 14px 17px 15px 16px;
    box-shadow: 0 2px 8px rgba(70, 90, 120, .08);
    overflow: hidden;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .planes-unif-spine {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    gap: 6px;
    width: 38px;
    min-width: 38px;
    padding: 10px 4px 8px;
    background: linear-gradient(180deg, #dceaf8 0%, #cfe0f2 100%);
    border-right: 1.5px solid rgba(120, 160, 200, .28);
    box-sizing: border-box;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .planes-unif-spine-ico {
    width: 16px;
    height: 16px;
    color: #5a7a9a;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .planes-unif-spine-tit {
    margin: 0;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .58rem;
    letter-spacing: .08em;
    color: #4a6888;
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    line-height: 1;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .planes-unif-spine-badges {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: auto;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .planes-unif-spine-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #fff;
    border: 1.5px solid rgba(90, 130, 90, .4);
    color: #4a7a48;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .planes-unif-spine-badge-ico {
    width: 12px;
    height: 12px;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .planes-unif-spine-badge-num {
    font-size: .58rem;
    font-weight: 800;
    line-height: 1;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .planes-unif-track-wrap {
    position: relative;
    flex: 1 1 auto;
    min-width: 0;
    padding: 8px 8px 8px 6px;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .planes-unif-track {
    display: flex;
    align-items: stretch;
    gap: 8px;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .planes-unif-track::-webkit-scrollbar {
    display: none;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .plan-unif-card {
    flex: 0 0 min(78%, 240px);
    scroll-snap-align: start;
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 8px 10px 10px;
    background: #fff;
    border: 1.5px solid rgba(218, 200, 140, .55);
    border-radius: 12px;
    box-shadow: 0 2px 6px rgba(70, 50, 30, .08);
    position: relative;
    box-sizing: border-box;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .plan-unif-badge {
    align-self: flex-start;
    padding: 2px 7px;
    border-radius: 999px;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .58rem;
    letter-spacing: .04em;
    line-height: 1.2;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .plan-unif-badge--curso {
    background: #d8ead4;
    border: 1px solid rgba(72, 128, 62, .35);
    color: #3d6b38;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .plan-unif-menu {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 18px;
    height: 18px;
    color: rgba(90, 70, 50, .55);
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .plan-unif-menu svg {
    width: 100%;
    height: 100%;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .plan-unif-faces {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .plan-unif-nombres {
    margin: 0;
    font-family: var(--ds-font-hand, Caveat, cursive);
    font-weight: 700;
    font-size: 1.05rem;
    line-height: 1.1;
    color: var(--ds-ink, #2a2218);
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .plan-unif-lugar {
    margin: 0;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 700;
    font-size: .68rem;
    color: rgba(90, 70, 50, .75);
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .planes-unif-more {
    position: absolute;
    right: 4px;
    top: 50%;
    transform: translateY(-50%);
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 50%;
    background: rgba(255, 255, 255, .92);
    box-shadow: 0 2px 6px rgba(70, 50, 90, .15);
    color: #5e3e90;
    cursor: pointer;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .planes-movil-unif.is-empty .planes-unif-body {
    min-height: 72px;
    align-items: center;
    justify-content: center;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .planes-movil-unif.is-empty .planes-unif-track-wrap::after {
    content: "Sin planes por ahora";
    display: block;
    padding: 12px;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: .78rem;
    font-weight: 700;
    color: rgba(90, 70, 50, .65);
    text-align: center;
  }

  /* Misiones + Parejas */
  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-duo {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin: 0;
    padding: 0;
    background: transparent;
    border: none;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    min-height: 78px;
    padding: 8px 8px 8px 10px;
    border: 1.5px solid transparent;
    border-radius: 14px 12px 16px 13px / 12px 16px 13px 14px;
    box-shadow: 0 2px 6px rgba(70, 50, 30, .08);
    cursor: pointer;
    text-align: left;
    font: inherit;
    color: inherit;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-card--mis {
    background: linear-gradient(180deg, #eef7ea 0%, #e3f0dc 100%);
    border-color: rgba(90, 140, 75, .35);
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-card--par {
    background: linear-gradient(180deg, #fff3f6 0%, #fde8ef 100%);
    border-color: rgba(232, 120, 152, .35);
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-cuerpo {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    min-width: 0;
    flex: 1 1 auto;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-ico {
    flex: 0 0 28px;
    width: 28px;
    height: 28px;
    background-repeat: no-repeat;
    background-position: center;
    background-size: contain;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-ico--mis {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect x='5' y='4' width='14' height='17' rx='2' fill='%23fff' stroke='%234a7a48' stroke-width='1.8'/%3E%3Cpath d='M8 9h8M8 13h5' stroke='%234a7a48' stroke-width='1.6' stroke-linecap='round'/%3E%3C/svg%3E");
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-ico--par {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M8.5 14.5c-2.2 0-4 1.4-4 3.2V19h8v-1.3c0-1.8-1.8-3.2-4-3.2Z' fill='%23f3a8bc'/%3E%3Cpath d='M15.5 14.5c2.2 0 4 1.4 4 3.2V19h-8v-1.3c0-1.8 1.8-3.2 4-3.2Z' fill='%23f3a8bc'/%3E%3Cpath d='M12 12a3 3 0 1 0-3-3 3 3 0 0 0 3 3Zm4.5-1.5a2.5 2.5 0 1 0-2.5-2.5 2.5 2.5 0 0 0 2.5 2.5ZM7.5 8a2.5 2.5 0 1 0-2.5-2.5A2.5 2.5 0 0 0 7.5 8Z' fill='%23e85b78'/%3E%3C/svg%3E");
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-main {
    min-width: 0;
    flex: 1 1 auto;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-head {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-tit {
    font-family: var(--ds-font-hand, Caveat, cursive);
    font-weight: 700;
    font-size: 1rem;
    letter-spacing: .04em;
    text-transform: uppercase;
    line-height: 1.1;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-card--mis .inicio-mp-tit {
    color: #3d6b38;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-card--par .inicio-mp-tit {
    color: #b84a68;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    border-radius: 999px;
    background: #4a7a48;
    color: #fff;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .65rem;
    line-height: 1;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-badge[hidden] {
    display: none;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-sub {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-top: 2px;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-resumen {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 700;
    font-size: .62rem;
    line-height: 1.2;
    color: rgba(55, 45, 38, .78);
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-pill {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .58rem;
    color: #4a7a48;
  }

  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed .inicio-mp-chevron {
    flex: 0 0 auto;
    font-size: 1.1rem;
    line-height: 1;
    color: rgba(90, 70, 50, .5);
  }

  /* Navegación inferior */
  .play-v3 .inicio-stage > .play-bottom-nav {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    align-items: stretch;
    gap: 0;
    margin: 0;
    padding: 6px 0 calc(6px + env(safe-area-inset-bottom, 0px));
    background: #fff;
    border-top: 1px solid rgba(198, 176, 210, .35);
    border-radius: 16px 16px 0 0;
    box-shadow: 0 -2px 10px rgba(70, 55, 85, .08);
  }

  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    min-height: 52px;
    padding: 4px 2px;
    margin: 0;
    border: none;
    border-right: 1px solid rgba(198, 176, 210, .28);
    background: transparent;
    cursor: pointer;
    font: inherit;
    color: var(--ds-ink, #2a2218);
  }

  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-btn:last-child {
    border-right: none;
  }

  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-ico {
    display: grid;
    place-items: center;
    width: 26px;
    height: 26px;
    color: #c85b78;
  }

  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-btn:nth-child(2) .play-bottom-nav-ico {
    color: #5a5a62;
  }

  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-btn:nth-child(3) .play-bottom-nav-ico {
    color: #6b5a8a;
  }

  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-btn:nth-child(4) .play-bottom-nav-ico {
    color: #d95f78;
  }

  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-ico svg {
    width: 22px;
    height: 22px;
  }

  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-txt {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .58rem;
    letter-spacing: .04em;
    text-transform: uppercase;
    line-height: 1.1;
    color: var(--ds-ink, #2a2218);
  }

  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-badge {
    position: absolute;
    top: -2px;
    right: -4px;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    border-radius: 999px;
    background: #c85b78;
    color: #fff;
    font-size: .55rem;
    font-weight: 800;
    line-height: 16px;
  }

  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-ico--inv {
    position: relative;
  }
}
`;

const mapBlock = `
${MARKER_MAP}
@media (max-width: 768px) {
  .play-v3 .inicio-stage > .inicio-map-host {
    padding: 0 13px 10px;
  }

  .play-v3 .inicio-stage > .inicio-map-host .board-fit {
    aspect-ratio: 618 / 404;
    min-height: 200px;
    max-height: min(52vw, 280px);
  }

  .play-v3 .inicio-stage > .inicio-map-host .mapa-canonico,
  .play-v3 .inicio-stage > .inicio-map-host .mapa-canonico-bg {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }

  .play-v3 .inicio-stage > .inicio-map-host .mapa-canonico {
    display: block;
    border-radius: 20px 18px 22px 16px / 18px 22px 16px 20px;
    border: 2px solid rgba(107, 81, 56, .28);
    box-shadow: 0 3px 10px rgba(70, 50, 30, .1);
    overflow: hidden;
    background: #fff;
  }
}

@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .inicio-map-host .board-fit {
    min-height: 360px;
    max-height: min(76vh, 660px);
  }
}
`;

const deskBlock = `
${MARKER_DESK}
@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage {
    background: #f3eef8;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage {
    grid-template-areas:
      "head head head"
      "left map  right"
      ".    nav  .";
    row-gap: 10px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    grid-area: nav;
    width: 100%;
    max-width: 100%;
    margin: 0;
    padding: 8px 0;
    background: #fff;
    border: 1px solid rgba(198, 176, 210, .35);
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(70, 55, 85, .08);
    position: static;
    align-self: stretch;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav .play-bottom-nav-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    min-height: 56px;
    padding: 6px 4px;
    margin: 0;
    border: none;
    border-right: 1px solid rgba(198, 176, 210, .28);
    background: transparent;
    cursor: pointer;
    font: inherit;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav .play-bottom-nav-btn:last-child {
    border-right: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav .play-bottom-nav-ico {
    width: 28px;
    height: 28px;
    color: #c85b78;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav .play-bottom-nav-btn:nth-child(3) .play-bottom-nav-ico {
    color: #6b5a8a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav .play-bottom-nav-txt {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .62rem;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--ds-ink, #2a2218);
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right {
    gap: 12px;
  }
}
`;

upsert(path.join(root, 'assets/css/inicio/inicio-mobile.css'), MARKER_MOB, mobBlock);
upsert(path.join(root, 'assets/css/inicio/inicio-mapa.css'), MARKER_MAP, mapBlock);
upsert(path.join(root, 'assets/css/inicio/inicio-desktop.css'), MARKER_DESK, deskBlock);

console.log('visual PNG patch OK');
