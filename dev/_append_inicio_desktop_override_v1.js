'use strict';
const fs = require('fs');
const path = require('path');

const cssPath = path.join(__dirname, '..', 'assets/css/design-system/screens/inicio-desktop.css');
let css = fs.readFileSync(cssPath, 'utf8');

const marker = '/* INICIO-DESKTOP-OVERRIDE-v1';
if (css.includes(marker)) {
  css = css.replace(/\n\/\* INICIO-DESKTOP-OVERRIDE-v1[\s\S]*$/, '');
}

const block = `
/* INICIO-DESKTOP-OVERRIDE-v1 — gana a shell-art/legibilidad; ref fa92ff6 + PORTED block */
@media (min-width: 769px) {
  .play-v3 .inicio-desktop > .game-top {
    background: transparent !important;
    box-shadow: none !important;
  }

  .play-v3 .inicio-desktop > .game-top .btn-guia:not([hidden]) {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    font-family: var(--ds-font-hand, "Caveat Brush", cursive) !important;
    font-size: 1.05rem !important;
    font-weight: 700 !important;
    color: var(--ds-pink-deep, #c85b78) !important;
    text-decoration: underline !important;
    text-underline-offset: 3px !important;
  }

  .play-v3 .inicio-desktop-left .obj-buzon {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    width: 100% !important;
    border: 2px solid #2c261f !important;
    background: #fffdf8 !important;
  }

  .play-v3 .inicio-desktop-left .obj-nuevo-plan.obj-proximo-cta.obj-nuevo-plan-horiz {
    display: flex !important;
    flex-direction: row !important;
    width: 100% !important;
    min-height: 46px !important;
    margin-top: .15rem !important;
    padding: .62rem 1rem .65rem !important;
    border: 2.5px solid #2c261f !important;
    border-radius: 16px 20px 14px 18px / 18px 14px 20px 16px !important;
    background: linear-gradient(165deg, #f8a8b8 0%, #e87a90 48%, #d95f78 100%) !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .35), inset 0 -2px 0 rgba(160, 50, 70, .2), 2px 4px 0 rgba(44, 38, 31, .14) !important;
    transform: rotate(-0.5deg) !important;
  }

  .play-v3 .inicio-desktop-right .obj-cotilleo.obj-cotilleo-par {
    display: grid !important;
    grid-template-columns: auto minmax(0, 1fr) auto !important;
    grid-template-rows: auto auto !important;
    grid-template-areas:
      "tit txt flecha"
      "badge txt flecha" !important;
    align-items: start !important;
    gap: 5px 11px !important;
    min-height: 62px !important;
    padding: 9px 11px 9px 9px !important;
    border: 1px solid rgba(122, 106, 154, .35) !important;
    border-radius: 10px !important;
    background: linear-gradient(180deg, #ebe3f8 0%, #e8dff5 100%) !important;
    box-shadow: 0 1px 3px rgba(44, 38, 31, .06) !important;
    transform: none !important;
    clip-path: none !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
  }

  .play-v3 .inicio-desktop-right .obj-cotilleo.obj-cotilleo-par::before,
  .play-v3 .inicio-desktop-right .obj-cotilleo.obj-cotilleo-par::after {
    content: none !important;
    display: none !important;
  }

  .play-v3 .inicio-desktop-right .obj-cotilleo-cuerpo {
    display: contents !important;
  }

  .play-v3 .inicio-desktop-right .obj-cotilleo-tit {
    grid-area: tit !important;
    flex: none !important;
    margin: 0 !important;
    padding: 6px 9px !important;
    border-radius: 6px !important;
    background: #4f4378 !important;
    font-family: Nunito, "Segoe UI", sans-serif !important;
    font-size: .62rem !important;
    font-weight: 800 !important;
    letter-spacing: .11em !important;
    text-transform: uppercase !important;
    color: #fff !important;
    line-height: 1 !important;
    transform: none !important;
  }

  .play-v3 .inicio-desktop-right .obj-cotilleo-txt {
    grid-area: txt !important;
    align-self: center !important;
    font-family: var(--ds-font-hand, Caveat, cursive) !important;
    font-size: 1.05rem !important;
    line-height: 1.3 !important;
    color: var(--ds-ink, #3b3028) !important;
  }

  .play-v3 .inicio-desktop-right .obj-cotilleo-badge {
    grid-area: badge !important;
    position: static !important;
    margin: 0 !important;
    transform: rotate(3deg) !important;
  }

  .play-v3 .inicio-desktop-right .obj-cotilleo-flecha {
    grid-area: flecha !important;
    align-self: center !important;
    color: var(--ds-lavender-deep, #7c6bae) !important;
    font-size: 1.35rem !important;
  }

  .inicio-stage {
    grid-template-columns: minmax(300px, 360px) minmax(320px, 1fr) minmax(260px, 340px) !important;
  }
}
`;

css = css.trimEnd() + block;
fs.writeFileSync(cssPath, css);
console.log('OK appended INICIO-DESKTOP-OVERRIDE-v1 to', cssPath);
