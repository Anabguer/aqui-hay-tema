#!/usr/bin/env node
'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

const deskPath = path.join(root, 'assets/css/inicio/inicio-desktop.css');
let desk = fs.readFileSync(deskPath, 'utf8');
const oldBtn = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan.obj-nuevo-plan-horiz {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    width: 100%;
    min-height: 38px;
    margin: 6px 0 0;
    padding: 9px 14px;
    background: #fff;
    border: 1.5px solid #6f5f9a;
    border-radius: 12px;
    box-shadow: none;
    cursor: pointer;
    font: inherit;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-ico {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-txt {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .72rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #6f5f9a;
  }`;
const newBtn = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan.obj-nuevo-plan-horiz {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    width: 100%;
    min-height: 54px;
    margin: 8px 0 0;
    padding: 14px 18px;
    background: #fff;
    border: 1.5px solid rgba(111, 95, 154, .58);
    border-radius: 14px;
    box-shadow:
      0 0 0 1px rgba(111, 95, 154, .10),
      0 4px 14px rgba(111, 95, 154, .22);
    clip-path: none;
    filter: none;
    transform: none;
    cursor: pointer;
    font: inherit;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-ico {
    display: none;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-txt {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: 1rem;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: #6f5f9a;
  }`;
if (!desk.includes(oldBtn)) {
  console.error('patch_crear_plan_btn_prominente: bloque boton no encontrado');
  process.exit(1);
}
desk = desk.replace(oldBtn, newBtn);
fs.writeFileSync(deskPath, desk);
console.log('OK', 'assets/css/inicio/inicio-desktop.css');

const cromaPath = path.join(root, 'assets/css/inicio/inicio-cromatica-desktop.css');
let croma = fs.readFileSync(cromaPath, 'utf8');
const oldCroma = `.play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo-planes .obj-nuevo-plan.obj-nuevo-plan-horiz {
  border: 1.5px solid #6f5f9a;
  border-radius: 12px;
  background: #fff;
  background-color: #fff;
  background-image: none;
  box-shadow: none;
}

.play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo-planes .obj-nuevo-plan-ico,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo-planes .obj-nuevo-plan-txt {
  color: #6f5f9a;
}

`;
if (!croma.includes(oldCroma)) {
  console.error('patch_crear_plan_btn_prominente: bloque cromatica no encontrado');
  process.exit(1);
}
croma = croma.replace(oldCroma, '');
const oldCroma2 = `.play-v3 .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan.obj-nuevo-plan-horiz {
  border-color: #6f5f9a;
}

.play-v3 .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-ico,
  .play-v3 .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-txt {
  color: #6f5f9a;
}

`;
if (croma.includes(oldCroma2)) {
  croma = croma.replace(oldCroma2, '');
}
fs.writeFileSync(cromaPath, croma);
console.log('OK', 'assets/css/inicio/inicio-cromatica-desktop.css');
