'use strict';
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

function norm(s) {
  return s.replace(/\r\n/g, '\n');
}

const deskPath = path.join(root, 'assets/css/inicio/inicio-desktop.css');
const cromaPath = path.join(root, 'assets/css/inicio/inicio-cromatica-desktop.css');
let desk = norm(fs.readFileSync(deskPath, 'utf8'));
let croma = norm(fs.readFileSync(cromaPath, 'utf8'));

/* --- cromatica: no anular bloque blanco PLANES ni degradados EN CURSO --- */
croma = croma.replace(
  `.play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .shell-grupo,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .encursos-movil,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .proxplanes-movil {
  background: transparent;
  background-color: transparent;
  background-image: none;
  border: none;
  box-shadow: none;
}`,
  `.play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-left .shell-grupo,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .shell-grupo,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .encursos-movil,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .proxplanes-movil {
  background: transparent;
  background-color: transparent;
  background-image: none;
  border: none;
  box-shadow: none;
}`
);

croma = croma.replace(
  /background-image:\s*\n\s*radial-gradient\([^;]+\),\s*\n\s*radial-gradient\([^;]+\),\s*\n\s*linear-gradient\([^;]+\);/g,
  'background-image: none;'
);

croma = croma.replace(
  /\.play-v3 \.inicio-desktop-right \.inicio-planes-libreta \.enc-mov-card--ref-desk \{\s*border: 1\.5px solid rgba\(109, 155, 118, \.42\);\s*border-radius: 14px;\s*background: linear-gradient\(165deg, #f8fcf9 0%, #eef7f0 52%, #e6f2e8 100%\);\s*box-shadow: 0 2px 10px rgba\(109, 155, 118, \.12\);\s*\}/,
  `.play-v3 .inicio-desktop-right .inicio-planes-libreta .enc-mov-card--ref-desk {
  border: 1.5px solid rgba(109, 155, 118, .36);
  border-radius: 14px;
  background: #f3faf4;
  background-color: #f3faf4;
  background-image: none;
  box-shadow: none;
}`
);

croma = croma.replace(
  /\.play-v3 \.inicio-desktop-right \.inicio-planes-libreta \.enc-mov-card--ref-desk \.enc-mov-desk-chevron \{\s*font-size: 1\.3rem;\s*font-weight: 700;\s*color: #4a7352;\s*\}/,
  ''
);

/* --- desktop: bloque blanco, sin flecha, proximos compactos, boton ref --- */
const libretaOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px 14px 12px;
    background: #fff;
    border: 1.5px solid #d9d2e1;
    border-radius: 16px;
    box-shadow: none;
    filter: none;
  }`;

const libretaNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px 14px 12px;
    background: #fff;
    border: 1.5px solid #d9d2e1;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(111, 95, 154, .08);
    filter: none;
  }`;

if (!desk.includes(libretaOld)) {
  console.error('patch_planes_visual_fiel: libreta block no encontrado');
  process.exit(1);
}
desk = desk.replace(libretaOld, libretaNew);

const encOnOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .encursos-movil.is-on {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0 0 8px;
    padding: 10px 10px 12px;
    border: 1px solid rgba(109, 155, 118, .24);
    border-radius: 12px;
    background: rgba(232, 245, 234, .58);
  }`;

const encOnNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .encursos-movil.is-on {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0 0 8px;
    padding: 10px 10px 12px;
    border: 1px solid rgba(109, 155, 118, .22);
    border-radius: 12px;
    background: #edf6ef;
    background-image: none;
  }`;

desk = desk.replace(encOnOld, encOnNew);

if (!desk.includes('.enc-mov-card--ref-desk .enc-mov-estado-pill--fuera')) {
  console.error('patch_planes_visual_fiel: enc estado pill block missing');
  process.exit(1);
}

const chevronRule = `
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-desk-chevron {
    display: none;
  }
`;

if (!desk.includes('.inicio-planes-libreta .enc-mov-desk-chevron')) {
  desk = desk.replace(
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-card--ref-desk .enc-mov-estado-pill--fuera {
    display: none;
  }`,
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .enc-mov-card--ref-desk .enc-mov-estado-pill--fuera {
    display: none;
  }
${chevronRule}`
  );
}

const proxOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .proxplanes-movil .pp-mov-card--desk-duo {
    flex: 0 0 calc(50% - 4px);
    width: calc(50% - 4px);
    min-width: calc(50% - 4px);
    scroll-snap-align: start;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 6px;
    padding: 8px 8px 10px;
    border: 1px solid rgba(111, 95, 154, .14);
    border-radius: 12px;
    background: #f3f0f8;
    box-shadow: none;
    box-sizing: border-box;
    transform: none;
  }`;

const proxNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .proxplanes-movil .pp-mov-card--desk-duo {
    flex: 0 0 calc(50% - 4px);
    width: calc(50% - 4px);
    min-width: calc(50% - 4px);
    scroll-snap-align: start;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 4px;
    padding: 6px 6px 7px;
    border: 1px solid rgba(111, 95, 154, .14);
    border-radius: 10px;
    background: #f3f0f8;
    box-shadow: none;
    box-sizing: border-box;
    transform: none;
  }`;

desk = desk.replace(proxOld, proxNew);

const bodyOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-card--desk-duo .pp-mov-body--desk-duo {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    text-align: center;
  }`;

const bodyNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-card--desk-duo .pp-mov-body--desk-duo {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    text-align: center;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-card--desk-duo .plan-desk-duo-faces .cara-token .cara,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-card--desk-duo .plan-desk-duo-faces img,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-card--desk-duo .plan-desk-duo-faces .cara-ini {
    width: 38px;
    height: 38px;
    min-width: 38px;
    min-height: 38px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-card--desk-duo .plan-desk-duo-nombre {
    font-size: .84rem;
    line-height: 1;
    margin: 0;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .pp-mov-card--desk-duo .pp-mov-lugar {
    margin: 0;
    padding-top: 1px;
    font-size: .68rem;
    line-height: 1.1;
  }`;

desk = desk.replace(bodyOld, bodyNew);

const btnOld = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan.obj-nuevo-plan-horiz {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    min-height: 40px;
    margin: 6px 0 0;
    padding: 9px 16px;
    background: #fff;
    border: 1.5px solid #6f5f9a;
    border-radius: 999px;
    box-shadow: none;
    cursor: pointer;
    font: inherit;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-ico {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0;
    width: 12px;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: .62rem;
    font-weight: 900;
    line-height: .52;
    color: #6f5f9a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-ico::after {
    content: "+";
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-txt {
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-weight: 800;
    font-size: .72rem;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #6f5f9a;
  }`;

const btnNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan.obj-nuevo-plan-horiz {
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
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-txt::before {
    content: "+ ";
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop-right .inicio-planes-libreta .obj-nuevo-plan-txt::after {
    content: " +";
  }`;

if (!desk.includes(btnOld)) {
  console.error('patch_planes_visual_fiel: boton CREAR PLAN no encontrado');
  process.exit(1);
}
desk = desk.replace(btnOld, btnNew);

fs.writeFileSync(deskPath, desk, 'utf8');
fs.writeFileSync(cromaPath, croma, 'utf8');

if (/inicio-planes-libreta,\s*\n\s*\.play-v3:has/.test(croma)) {
  console.error('patch_planes_visual_fiel: libreta sigue en grupo transparente');
  process.exit(1);
}
if (/linear-gradient\(165deg, #f8fcf9/.test(croma)) {
  console.error('patch_planes_visual_fiel: degradado EN CURSO residual');
  process.exit(1);
}
console.log('patch_planes_visual_fiel OK');
