'use strict';
const fs = require('fs');
const path = require('path');

const phpFile = path.join(__dirname, '..', 'play.php');
const deskFile = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
const mobFile = path.join(__dirname, '..', 'assets/css/inicio/inicio-mobile.css');

const historiaAlbumSvg =
  '<svg viewBox="0 0 24 24" focusable="false">' +
  '<path d="M6 3h11a2 2 0 0 1 2 2v16H8a2 2 0 0 1-2-2V3Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>' +
  '<path d="M8 3v18" stroke="currentColor" stroke-width="1.8"/>' +
  '<rect x="11" y="7" width="5.5" height="4.5" rx=".5" fill="none" stroke="currentColor" stroke-width="1.4"/>' +
  '<circle cx="12.3" cy="8.8" r=".65" fill="currentColor"/>' +
  '<path d="M11 10.7l1.2-1 1 .8 1.5-1.6" fill="none" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"/>' +
  '</svg>';

const navButtons = `      <button type="button" class="play-bottom-nav-btn" data-open="necesidades_global">
        <span class="play-bottom-nav-ico" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v4M12 16h.01" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </span>
        <span class="play-bottom-nav-txt">Necesidades</span>
      </button>
      <button type="button" class="play-bottom-nav-btn" data-open="historia">
        <span class="play-bottom-nav-ico" aria-hidden="true">
          ${historiaAlbumSvg}
        </span>
        <span class="play-bottom-nav-txt">Historia</span>
      </button>
`;

let php = fs.readFileSync(phpFile, 'utf8');
if (php.includes('data-open="necesidades_global"') && php.includes('class="play-bottom-nav-btn" data-open="historia"')) {
  console.log('patch_bottom_nav_6: play.php ya tiene 6 botones');
} else {
  const anchor = `        <span class="play-bottom-nav-txt">Relaciones</span>
      </button>
    </nav>`;
  if (!php.includes(anchor)) {
    console.error('patch_bottom_nav_6: ancla nav no encontrada');
    process.exit(1);
  }
  php = php.replace(anchor, `        <span class="play-bottom-nav-txt">Relaciones</span>
      </button>
${navButtons}    </nav>`);
  fs.writeFileSync(phpFile, php, 'utf8');
  console.log('patch_bottom_nav_6: play.php OK');
}

function patchCss(file, label) {
  let css = fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n');
  if (!css.includes('repeat(4, minmax(0, 1fr))')) {
    if (css.includes('repeat(6, minmax(0, 1fr))')) {
      console.log('patch_bottom_nav_6:', label, 'grid ya en 6');
      return;
    }
    console.error('patch_bottom_nav_6:', label, 'grid 4 no encontrado');
    process.exit(1);
  }
  css = css.replace(/repeat\(4, minmax\(0, 1fr\)\)/g, 'repeat(6, minmax(0, 1fr))');
  fs.writeFileSync(file, css, 'utf8');
  console.log('patch_bottom_nav_6:', label, 'grid OK');
}

patchCss(deskFile, 'desktop');
patchCss(mobFile, 'mobile');

let desk = fs.readFileSync(deskFile, 'utf8').replace(/\r\n/g, '\n');
const deskNavBlock = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav .play-bottom-nav-btn:nth-child(3) .play-bottom-nav-ico {
    color: #6b5a8a;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav .play-bottom-nav-txt {`;

const deskNavNew = `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav .play-bottom-nav-btn[data-open="ajustes"] .play-bottom-nav-ico {
    color: #c42b4a;
    background: transparent;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav .play-bottom-nav-btn[data-open="inventario"] .play-bottom-nav-ico,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav .play-bottom-nav-btn[data-open="necesidades_global"] .play-bottom-nav-ico,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav .play-bottom-nav-btn[data-open="historia"] .play-bottom-nav-ico {
    color: #33261e;
    background: transparent;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav .play-bottom-nav-btn[data-open="vecinos"] .play-bottom-nav-ico {
    color: #7c6bae;
    background: #e4dcf4;
    border-radius: 8px;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav .play-bottom-nav-btn[data-open="relaciones"] .play-bottom-nav-ico {
    color: #d95f78;
    background: transparent;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav .play-bottom-nav-btn[data-open="relaciones"] .play-bottom-nav-ico svg path {
    fill: #d95f78;
    stroke: #d95f78;
  }

  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-stage > .play-bottom-nav .play-bottom-nav-txt {`;

if (!desk.includes(deskNavBlock)) {
  if (desk.includes('[data-open="necesidades_global"] .play-bottom-nav-ico')) {
    console.log('patch_bottom_nav_6: desktop icon rules ya presentes');
  } else {
    console.error('patch_bottom_nav_6: bloque desktop nav no encontrado');
    process.exit(1);
  }
} else {
  desk = desk.replace(deskNavBlock, deskNavNew);
  fs.writeFileSync(deskFile, desk, 'utf8');
  console.log('patch_bottom_nav_6: desktop icon rules OK');
}

let mob = fs.readFileSync(mobFile, 'utf8').replace(/\r\n/g, '\n');
const mobBlock = `  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-btn:nth-child(4) .play-bottom-nav-ico {
    color: #d95f78;
  }

  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-ico svg {`;

const mobNew = `  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-btn:nth-child(4) .play-bottom-nav-ico {
    color: #d95f78;
  }

  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-btn:nth-child(5) .play-bottom-nav-ico,
  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-btn:nth-child(6) .play-bottom-nav-ico {
    color: #33261e;
  }

  .play-v3 .inicio-stage > .play-bottom-nav .play-bottom-nav-ico svg {`;

if (!mob.includes(mobBlock)) {
  if (mob.includes('nth-child(6)')) {
    console.log('patch_bottom_nav_6: mobile icon rules ya presentes');
  } else {
    console.error('patch_bottom_nav_6: bloque mobile nav no encontrado');
    process.exit(1);
  }
} else {
  mob = mob.replace(mobBlock, mobNew);
  fs.writeFileSync(mobFile, mob, 'utf8');
  console.log('patch_bottom_nav_6: mobile icon rules OK');
}

console.log('patch_bottom_nav_6 OK');
