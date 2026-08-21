/* eslint-disable */
const fs = require('fs');
const path = require('path');

const phpPath = path.join(__dirname, '..', 'play.php');
let s = fs.readFileSync(phpPath, 'utf8');

s = s.replace("$ahtUi = 'v3-20260821j';", "$ahtUi = 'v3-20260821k';");

if (!s.includes('play-v3-shell-art.css')) {
  s = s.replace(
    '  <link rel="stylesheet" href="assets/css/play-v3-shell-ui.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, \'UTF-8\') ?>"/>\n',
    '  <link rel="stylesheet" href="assets/css/play-v3-shell-ui.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, \'UTF-8\') ?>"/>\n' +
      '  <link rel="stylesheet" href="assets/css/play-v3-shell-art.css?v=<?= htmlspecialchars($ahtUi, ENT_QUOTES, \'UTF-8\') ?>"/>\n'
  );
}

const leftOld = `<aside class="game-left zona-actividad">
        <button type="button" class="obj-vecinos-resumen" data-open="vecinos" aria-label="Ver vecinos">
          <span class="obj-vecinos-tit">Vecinos</span>
          <div class="obj-vecinos-stats" data-resumen-stats></div>
        </button>
        <button type="button" class="obj-buzon" data-open="buzon" aria-label="Abrir buzón">
          <span class="obj-buzon-badge" data-buzon-badge hidden>0</span>
          <img class="obj-buzon-img" src="assets/play-v3/hud/sobre.png" alt="" width="52" height="42"/>
          <span class="obj-buzon-txt">BUZÓN</span>
        </button>
        <button type="button" class="obj-cotilleo" data-open="diario" aria-label="Abrir diario">
          <span class="obj-cotilleo-tit">Cotilleo</span>
          <p class="obj-cotilleo-txt" data-cotilleo-teaser>—</p>
        </button>
        <div class="obj-proximo">
          <span class="obj-proximo-tit">Próximo plan</span>
          <div class="obj-proximo-body" data-proximo-plan><p class="obj-proximo-vacio">Nada en agenda. Sospechoso.</p></div>
          <button type="button" class="obj-planes-pend" data-planes-pend hidden>
            <span data-planes-pend-txt></span>
            <span class="obj-planes-pend-flecha" aria-hidden="true">→</span>
          </button>
        </div>
        <button type="button" class="obj-nuevo-plan" data-open="organizar" aria-label="Nuevo plan">
          <span class="obj-nuevo-plan-ico" aria-hidden="true">+</span>
          <span class="obj-nuevo-plan-txt">NUEVO PLAN</span>
        </button>
      </aside>`;

const leftNew = `<aside class="game-left zona-actividad">
        <section class="shell-grupo shell-grupo-resumen">
          <button type="button" class="obj-vecinos-resumen celestine-nota" data-open="vecinos" aria-label="Ver vecinos">
            <span class="libreta-kicker">Celestine apunta</span>
            <span class="obj-vecinos-tit">Vecinos</span>
            <div class="obj-vecinos-stats" data-resumen-stats></div>
          </button>
        </section>
        <section class="shell-grupo shell-grupo-paso">
          <span class="shell-grupo-kicker">Lo que está pasando</span>
          <div class="shell-par-buzon-coti">
            <button type="button" class="obj-buzon" data-open="buzon" aria-label="Abrir buzón">
              <span class="obj-buzon-badge" data-buzon-badge hidden>0</span>
              <img class="obj-buzon-img" src="assets/play-v3/hud/sobre.png" alt="" width="52" height="42"/>
              <span class="obj-buzon-txt">BUZÓN</span>
            </button>
            <button type="button" class="obj-cotilleo" data-open="diario" aria-label="Abrir diario">
              <span class="obj-cotilleo-tit">Cotilleo</span>
              <p class="obj-cotilleo-txt" data-cotilleo-teaser>—</p>
            </button>
          </div>
        </section>
        <section class="shell-grupo shell-grupo-planes">
          <span class="shell-grupo-kicker">Planes</span>
          <div class="obj-proximo">
            <span class="obj-proximo-tit">Próximo plan</span>
            <div class="obj-proximo-body" data-proximo-plan><p class="obj-proximo-vacio">Nada en agenda. Sospechoso.</p></div>
            <button type="button" class="obj-planes-pend" data-planes-pend hidden>
              <span data-planes-pend-txt></span>
            </button>
          </div>
          <button type="button" class="obj-nuevo-plan obj-nota-rosa" data-open="organizar" aria-label="Nuevo plan">
            <span class="obj-nuevo-plan-ico" aria-hidden="true">+</span>
            <span class="obj-nuevo-plan-txt">NUEVO PLAN</span>
          </button>
        </section>
      </aside>`;

if (s.includes(leftOld)) {
  s = s.replace(leftOld, leftNew);
} else {
  console.warn('left panel block not found exact — trying regex');
}

const rightOld = `<aside class="game-right zona-personas">
        <div class="obj-bloques-res" data-bloques-row></div>
        <div class="obj-parejas">
          <div class="obj-parejas-head">
            <span class="obj-parejas-tab">Parejas</span>
            <button type="button" class="obj-pestaña" data-parejas-ver disabled>Ver todas</button>
          </div>
          <div class="obj-parejas-list" data-parejas-strip></div>
        </div>
      </aside>`;

const rightNew = `<aside class="game-right zona-personas">
        <section class="shell-grupo shell-grupo-residencias">
          <span class="zona-tit">Residencias</span>
          <div class="obj-residencias-row obj-bloques-res" data-bloques-row></div>
        </section>
        <section class="shell-grupo shell-grupo-parejas">
          <span class="zona-tit">Parejas</span>
          <div class="obj-parejas-list" data-parejas-strip></div>
        </section>
      </aside>`;

if (s.includes(rightOld)) {
  s = s.replace(rightOld, rightNew);
} else {
  console.warn('right panel block not found exact');
}

fs.writeFileSync(phpPath, s);
console.log('play.php shell HTML patched');
