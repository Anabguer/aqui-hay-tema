'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

function read(p) { return fs.readFileSync(path.join(root, p), 'utf8'); }
function write(p, c) { fs.writeFileSync(path.join(root, p), c, 'utf8'); }

function upsertMarker(file, marker, block) {
  let css = read(file);
  const re = new RegExp('/\\* ' + marker.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '[\\s\\S]*?(?=\\n/\\*|$)', 'm');
  const trimmed = block.trim() + '\n';
  if (re.test(css)) css = css.replace(re, trimmed);
  else css = css.trimEnd() + '\n\n' + trimmed;
  write(file, css);
}

/* 03 MENSAJITOS — ref PNG: tabs, cartas rosa, hint lavanda inferior */
upsertMarker('assets/css/play-v3-mensajitos.css', 'CIERRE-VISUAL-03-MENSAJITOS-20260906', `
/* CIERRE-VISUAL-03-MENSAJITOS-20260906 */
.play-v3 .aht-screen[data-aht-screen="buzon"] .mensajitos-tabs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  padding: 0 12px 8px;
}

.play-v3 .aht-screen[data-aht-screen="buzon"] .mensajitos-tab {
  min-height: 40px;
  border-radius: 12px;
  font-weight: 800;
  font-size: .78rem;
  letter-spacing: .04em;
}

.play-v3 .aht-screen[data-aht-screen="buzon"] .mensajitos-tab:not(.is-on) {
  background: #e8e0f4;
  color: #6f5f9a;
  border: 1.5px solid rgba(111, 95, 154, .35);
}

.play-v3 .aht-screen[data-aht-screen="buzon"] .mensajitos-tab.is-on {
  background: #d85a78;
  color: #fff;
  border: 1.5px solid #8a3d52;
}

.play-v3 .aht-screen[data-aht-screen="buzon"] [data-buzon-list] {
  padding: 8px 12px 0;
  gap: 10px;
}

.play-v3 .aht-screen[data-aht-screen="buzon"] .carta-msg.no-leida,
.play-v3 .aht-screen[data-aht-screen="buzon"] .carta-msg.carta-accion.no-leida {
  background: #fde8ef;
  border: 1.5px solid rgba(196, 75, 110, .45);
  border-radius: 14px;
  box-shadow: 0 2px 6px rgba(196, 75, 110, .1);
}

.play-v3 .aht-screen[data-aht-screen="buzon"] .carta-inner {
  gap: 10px;
}

.play-v3 .aht-screen[data-aht-screen="buzon"] .carta-msg .de {
  font-family: var(--ds-font-hand, Caveat, cursive);
  font-size: 1.15rem;
  font-weight: 700;
}

.play-v3 .aht-screen[data-aht-screen="buzon"] .mensajitos-hint {
  margin: 10px 12px 12px;
  padding: 10px 12px;
  border-radius: 12px;
  background: #ece6f8;
  border: 1.5px solid rgba(111, 95, 154, .22);
  font-family: var(--ds-font-ui, Nunito, sans-serif);
  font-size: .76rem;
  font-weight: 600;
  font-style: italic;
  color: #5a4a78;
  text-align: center;
}
`);

/* 04-05 VECINOS — ref PNG: badge 8/16, grid 4 col, tabs */
upsertMarker('assets/css/design-system/vecinos-body.css', 'CIERRE-VISUAL-04-VECINOS-20260906', `
/* CIERRE-VISUAL-04-VECINOS-20260906 */
.play-v3 .aht-screen[data-aht-screen="vecinos"] .vecinos-cuenta-wrap {
  display: flex;
  justify-content: center;
  margin: 4px 0 10px;
}

.play-v3 .aht-screen[data-aht-screen="vecinos"] .vecinos-cuenta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 52px;
  padding: 4px 12px;
  border-radius: 999px;
  background: #e85a78;
  border: 1.5px solid #b84868;
  font-family: var(--ds-font-ui, Nunito, sans-serif);
  font-weight: 800;
  font-size: .72rem;
  color: #fff;
}

.play-v3 .aht-screen[data-aht-screen="vecinos"] .vecinos-tabs {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  padding: 0 12px 10px;
}

.play-v3 .aht-screen[data-aht-screen="vecinos"] .vecinos-tab.is-on {
  background: #e85a78;
  color: #fff;
  border: 1.5px solid #8a3d52;
}

.play-v3 .aht-screen[data-aht-screen="vecinos"] .vecinos-tab:not(.is-on) {
  background: #fff;
  color: #6f5f9a;
  border: 1.5px dashed rgba(111, 95, 154, .55);
}

.play-v3 .aht-screen[data-aht-screen="vecinos"] .vecinos-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  padding: 0 12px 12px;
}

.play-v3 .aht-screen[data-aht-screen="vecinos"] .vecino-card {
  background: #faf6ef;
  border: 1.5px solid rgba(90, 70, 50, .35);
  border-radius: 12px;
  padding: 8px 6px 10px;
}

.play-v3 .aht-screen[data-aht-screen="vecinos"] .vecino-card .cara,
.play-v3 .aht-screen[data-aht-screen="vecinos"] .vecino-card .vecino-cara {
  width: 52px;
  height: 52px;
  margin: 0 auto 6px;
  border: 2px solid #d4a84a;
  border-radius: 50%;
}
`);

/* 05 relaciones — filtros y tarjetas */
upsertMarker('assets/css/play-v3-vecinos.css', 'CIERRE-VISUAL-05-RELACIONES-20260906', `
/* CIERRE-VISUAL-05-RELACIONES-20260906 */
.play-v3 .aht-screen[data-aht-screen="vecinos"].is-relaciones .vecinos-rel-filtros {
  display: flex;
  justify-content: space-between;
  gap: 6px;
  padding: 0 12px 10px;
}

.play-v3 .aht-screen[data-aht-screen="vecinos"].is-relaciones .vecinos-rel-filtro.is-on {
  color: #c43b4a;
  border-bottom: 2px solid #c43b4a;
}

.play-v3 .aht-screen[data-aht-screen="vecinos"].is-relaciones .vecinos-rel-card {
  margin: 0 12px 10px;
  padding: 12px;
  border-radius: 14px;
  border: 1.5px solid rgba(232, 160, 180, .45);
  background: #fff9fa;
}

.play-v3 .aht-screen[data-aht-screen="vecinos"].is-relaciones .vecinos-rel-nota {
  margin: 8px 12px 12px;
  padding: 10px 12px;
  border-radius: 12px;
  background: #ece6f8;
  border: 1.5px dashed rgba(111, 95, 154, .4);
  font-size: .76rem;
  font-style: italic;
  color: #5a4a78;
  text-align: center;
}
`);

/* 06 FICHA — bloques rasgos/hobbies/relaciones */
upsertMarker('assets/css/v4/bodies/ficha-relaciones.css', 'CIERRE-VISUAL-06-FICHA-20260906', `
/* CIERRE-VISUAL-06-FICHA-20260906 */
.play-v3 .aht-screen[data-aht-screen="ficha"] .ficha-bloque-rasgos {
  border: 2px solid rgba(232, 120, 152, .5);
  border-radius: 14px;
  background: #fff5f8;
}

.play-v3 .aht-screen[data-aht-screen="ficha"] .ficha-bloque-hobbies {
  border: 2px solid rgba(111, 95, 154, .45);
  border-radius: 14px;
  background: #f5f0fa;
}

.play-v3 .aht-screen[data-aht-screen="ficha"] .ficha-bloque-relaciones {
  border: 2px solid rgba(109, 155, 118, .5);
  border-radius: 14px;
  background: #f2f9f0;
}

.play-v3 .aht-screen[data-aht-screen="ficha"] .ficha-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 32px;
  padding: 6px 10px;
  border-radius: 10px;
  background: #fff;
  border: 1.5px solid rgba(90, 70, 50, .25);
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
}
`);

/* 07 animo + 08 diario en screens.css */
upsertMarker('assets/css/v4/screens.css', 'CIERRE-VISUAL-07-08-FICHA-SUB-20260906', `
/* CIERRE-VISUAL-07-08-FICHA-SUB-20260906 */
.aht-screen[data-aht-screen="ficha_animo"] .animo-estado-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 12px;
  border-radius: 12px;
  background: #e85a78;
  color: #fff;
  font-weight: 800;
  font-size: .82rem;
  text-transform: uppercase;
}

.aht-screen[data-aht-screen="ficha_animo"] .animo-causa-caja {
  margin-top: 10px;
  padding: 14px;
  border-radius: 14px;
  background: #faf6ef;
  border: 1.5px solid rgba(90, 70, 50, .3);
}

.aht-screen[data-aht-screen="ficha_animo"] .animo-consecuencias {
  margin-top: 12px;
  padding: 12px;
  border: 2px dashed rgba(111, 95, 154, .35);
  border-radius: 14px;
}

.aht-screen[data-aht-screen="ficha_diario"] .diario-filtros {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  padding: 0 12px 10px;
}

.aht-screen[data-aht-screen="ficha_diario"] .diario-filtro {
  padding: 6px 12px;
  border-radius: 999px;
  border: 1.5px solid rgba(90, 70, 50, .3);
  background: #fff;
  font-size: .72rem;
  font-weight: 800;
  text-transform: uppercase;
}

.aht-screen[data-aht-screen="ficha_diario"] .diario-entrada {
  margin: 0 12px 10px;
  padding: 12px;
  border-radius: 14px;
  background: #fff;
  border: 2px solid #e8dcc8;
  box-shadow: inset 0 0 0 3px #faf6ef;
}
`);

/* 09 COTILLEOS — tarjetas coloridas + footer */
upsertMarker('assets/css/play-v3-cotilleos.css', 'CIERRE-VISUAL-09-COTILLEOS-20260906', `
/* CIERRE-VISUAL-09-COTILLEOS-20260906 — body cotilleos; sin shell duplicado */
.play-v3 .aht-screen[data-aht-screen="cotilleos"] .coti-lista {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 8px 12px 0;
}

.play-v3 .aht-screen[data-aht-screen="cotilleos"] .coti-item {
  position: relative;
  padding: 12px 12px 12px 14px;
  border-radius: 14px;
  border: 1.5px solid rgba(90, 70, 50, .22);
}

.play-v3 .aht-screen[data-aht-screen="cotilleos"] .coti-item:nth-child(5n+1) { background: #fff6d6; }
.play-v3 .aht-screen[data-aht-screen="cotilleos"] .coti-item:nth-child(5n+2) { background: #ece6f8; }
.play-v3 .aht-screen[data-aht-screen="cotilleos"] .coti-item:nth-child(5n+3) { background: #e8f5e4; }
.play-v3 .aht-screen[data-aht-screen="cotilleos"] .coti-item:nth-child(5n+4) { background: #fdeef3; }
.play-v3 .aht-screen[data-aht-screen="cotilleos"] .coti-item:nth-child(5n+5) { background: #fff0e0; }

.play-v3 .aht-screen[data-aht-screen="cotilleos"] .coti-footer-hint {
  margin: 10px 12px 12px;
  padding: 10px 12px;
  border-radius: 12px;
  background: #ece6f8;
  font-size: .76rem;
  font-style: italic;
  color: #5a4a78;
  text-align: center;
}

/* diario submodal: quitar scrapbook shell legacy */
.play-v3 .aht-screen[data-aht-screen="diario"].coti-modal-papel::before,
.play-v3 .aht-screen[data-aht-screen="diario"].coti-modal-papel::after {
  display: none;
  content: none;
}
`);

/* 11 lugar + 12 nuevo plan */
upsertMarker('assets/css/play-v3-organizar.css', 'CIERRE-VISUAL-11-12-ORGANIZAR-20260906', `
/* CIERRE-VISUAL-11-12-ORGANIZAR-20260906 */
.play-v3 .aht-screen[data-aht-screen="organizar"].is-lugar .org-lugar-cita {
  margin: 12px;
  padding: 14px;
  border-radius: 14px;
  background: #faf6ef;
  border: 1.5px solid rgba(90, 70, 50, .25);
  font-family: var(--ds-font-hand, Caveat, cursive);
  font-size: 1.2rem;
  font-style: italic;
  text-align: center;
  color: #5a4838;
}

.play-v3 .aht-screen[data-aht-screen="organizar"].is-lugar .org-lugar-cta {
  margin: 0 12px 12px;
  min-height: 48px;
  border-radius: 14px;
  background: #e85a78;
  color: #fff;
  font-weight: 800;
  letter-spacing: .04em;
  text-transform: uppercase;
}

.play-v3 .aht-screen[data-aht-screen="organizar"]:not(.is-lugar) .org-paso-num {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #e85a78;
  color: #fff;
  font-weight: 800;
  font-size: .82rem;
}

.play-v3 .aht-screen[data-aht-screen="organizar"]:not(.is-lugar) .org-crear-plan {
  margin: 12px;
  padding: 12px 16px;
  border-radius: 999px;
  background: #e85a78;
  border: 2px dashed rgba(255, 255, 255, .85);
  color: #fff;
  font-weight: 800;
  text-transform: uppercase;
}
`);

/* 13-15 encuentros */
upsertMarker('assets/css/play-v3-enc-int.css', 'CIERRE-VISUAL-13-15-ENCUENTRO-20260906', `
/* CIERRE-VISUAL-13-15-ENCUENTRO-20260906 */
.play-v3 .aht-screen[data-aht-screen="mentes"] .enc-elegir-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  padding: 12px;
}

.play-v3 .aht-screen[data-aht-screen="mentes"] .enc-elegir-card {
  padding: 14px 10px;
  border-radius: 14px;
  background: #fff;
  border: 1.5px solid rgba(90, 70, 50, .25);
  text-align: center;
}

.play-v3 .aht-screen[data-aht-screen="mentes"] .enc-hobby-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  padding: 12px;
}

.play-v3 .aht-screen[data-aht-screen="mentes"] .enc-hobby-btn {
  min-height: 44px;
  padding: 10px 12px;
  border-radius: 12px;
  background: #fff;
  border: 1.5px solid rgba(90, 70, 50, .25);
  font-weight: 700;
  text-align: left;
}

.play-v3 .aht-screen[data-aht-screen="mentes"] .enc-detalle-caja {
  margin: 12px;
  padding: 14px;
  border-radius: 14px;
  background: #f4f2f6;
  border: 1.5px solid rgba(90, 70, 50, .2);
}
`);

/* 16 parejas + 17 vida pueblo en misc */
upsertMarker('assets/css/v4/bodies/misc-screens.css', 'CIERRE-VISUAL-16-17-MISC-20260906', `
/* CIERRE-VISUAL-16-17-MISC-20260906 */
.play-v3 .aht-screen[data-aht-screen="parejas"] .aht-frame-body,
.play-v3 .aht-screen[data-aht-screen="parejas"] .parejas-body {
  background: #f4f2f6;
  border-radius: 12px;
  margin: 0 12px 12px;
  padding: 16px;
}

.play-v3 .aht-screen[data-aht-screen="parejas"] .parejas-vacio,
.play-v3 .aht-screen[data-aht-screen="parejas"] .parejas-body > .muted {
  font-family: var(--ds-font-ui, Nunito, sans-serif);
  font-size: .88rem;
  font-weight: 600;
  color: #6a5848;
}

.play-v3 .aht-screen[data-aht-screen="vida_pueblo"] .vida-pueblo-score {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 14px;
  border-radius: 999px;
  background: #e8f3e6;
  border: 1.5px solid rgba(74, 122, 82, .45);
  font-weight: 800;
  color: #8a3038;
}

.play-v3 .aht-screen[data-aht-screen="vida_pueblo"] .vida-pueblo-caja {
  margin: 12px;
  padding: 14px;
  border-radius: 14px;
  background: #ece6f8;
  border: 1.5px solid rgba(111, 95, 154, .35);
  line-height: 1.45;
}
`);

/* 18 ajustes */
upsertMarker('assets/css/v4/bodies/ajustes.css', 'CIERRE-VISUAL-18-AJUSTES-20260906', `
/* CIERRE-VISUAL-18-AJUSTES-20260906 */
.play-v3 .aht-screen[data-aht-screen="ajustes"] .ajust-bloque {
  margin: 0 12px 10px;
  padding: 12px 14px;
  border-radius: 14px;
  border: 1.5px solid rgba(90, 70, 50, .3);
  background: #fff;
}

.play-v3 .aht-screen[data-aht-screen="ajustes"] .ajust-bloque--como {
  background: #fdeef3;
  text-align: center;
  font-family: var(--ds-font-hand, Caveat, cursive);
  font-size: 1.1rem;
  font-weight: 700;
}

.play-v3 .aht-screen[data-aht-screen="ajustes"] .ajust-toggle.is-on {
  background: #e85a78;
}

.play-v3 .aht-screen[data-aht-screen="ajustes"] .ajust-bloque--reiniciar {
  border-color: rgba(196, 75, 110, .55);
  text-align: center;
  font-family: var(--ds-font-hand, Caveat, cursive);
  font-size: 1.05rem;
  font-weight: 700;
}
`);

/* INICIO remate pass4 */
upsertMarker('assets/css/inicio/inicio-desktop.css', 'INICIO-VISUAL-PASS4-20260906', `
/* INICIO-VISUAL-PASS4-20260906 — remate desktop ref 02 */
@media (min-width: 769px) {
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-grid {
    gap: 14px;
    align-items: start;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-center {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-center .inicio-nav-mapa {
    margin-top: 4px;
  }
}
`);

let mob = read('assets/css/inicio/inicio-mobile.css');
if (!mob.includes('INICIO-VISUAL-PASS4-20260906')) {
  const pass4 = `
  /* INICIO-VISUAL-PASS4-20260906 — remate movil ref 01 */
  .play-v3 .inicio-stage .inicio-mobile.inicio-mobile-feed {
    gap: 10px;
    padding-bottom: 8px;
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-cotilleo-compacto {
    border-radius: 14px;
    padding: 10px 12px;
    background: #ece6f8;
    border: 1.5px solid rgba(111, 95, 154, .22);
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-planes-unif {
    border-radius: 14px;
    overflow: hidden;
    border: 1.5px solid rgba(111, 95, 154, .22);
  }

  .play-v3 .inicio-stage .inicio-mobile .inicio-nav-inferior {
    margin-top: 4px;
    border-radius: 14px;
    border: 1.5px solid rgba(90, 70, 50, .18);
    box-shadow: 0 2px 8px rgba(70, 55, 40, .08);
  }
`;
  mob = mob.replace(/\n\}\s*$/, pass4 + '\n}\n');
}
write('assets/css/inicio/inicio-mobile.css', mob);

console.log('patch_cierre_visual OK');
