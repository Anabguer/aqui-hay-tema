const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');

function patchCss() {
  const p = path.join(root, 'assets/css/play-v3-app.css');
  let css = fs.readFileSync(p, 'utf8');

  css = css.replace(
    /body\.play-v3 \{\s*background:[^}]+\}/,
    `body.play-v3 {
  background: #fafaf8;
  color: var(--ink);
  font-family: Nunito, "Segoe UI", sans-serif;
  min-height: 100%;
}`
  );

  css = css.replace(
    /\.play-stage \{ height: calc\(100vh - 52px\)[^}]+\}/,
    `.play-stage { flex: 1; min-height: 0; min-width: 0; padding: 0; }`
  );

  const oldOff = `.play-v3 .edificios-layer .edif.is-off {
  opacity: .28;
  filter: grayscale(.35) drop-shadow(0 2px 1px rgba(44,38,31,.12));
}`;

  const newOff = `.play-v3 .edificios-layer .edif.is-off {
  opacity: .88;
  filter: saturate(.62) brightness(1.03) drop-shadow(0 2px 2px rgba(44,38,31,.14));
}
.play-v3 .edificios-layer .edif.is-off::after {
  content: "";
  position: absolute; inset: 0; pointer-events: none;
  background: linear-gradient(180deg, rgba(255,255,255,.18), rgba(255,255,255,.08));
  border: 1.5px dashed rgba(122,113,100,.45);
  border-radius: 2px;
}
.play-v3 .edificios-layer .edif.is-off::before {
  content: "\\1F512";
  position: absolute; top: 4px; right: 4px;
  font-size: 14px; line-height: 1;
  filter: drop-shadow(0 1px 0 rgba(255,255,255,.8));
  opacity: .92;
  pointer-events: none;
}`;

  if (css.includes(oldOff)) css = css.replace(oldOff, newOff);
  else if (!css.includes('edif.is-off::before')) {
    css = css.replace(
      /\.play-v3 \.edificios-layer \.edif\.is-off \{[\s\S]*?\}/,
      newOff
    );
  }

  if (!css.includes('.game-shell')) {
    css += `

/* Shell UI (maqueta: paneles alrededor del mapa calibrado) */
.game-shell {
  display: flex; flex-direction: column;
  height: calc(100vh - 52px); min-height: 520px;
}
.game-top {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  padding: .55rem .85rem;
  background: #fff; border-bottom: 2px solid #e8dfd0;
}
.game-top .brand {
  font-family: Fraunces, Georgia, serif; font-weight: 700; font-size: 1.15rem;
  letter-spacing: .02em; margin: 0;
}
.game-top .brand .heart { color: var(--pink); }
.game-top .top-meta {
  display: flex; flex-wrap: wrap; gap: .45rem; align-items: center;
}
.game-top .pill {
  border: 2px solid #d8cdb8; border-radius: 999px;
  background: #fffdf8; padding: .22rem .65rem;
  font-size: .78rem; font-weight: 800;
}
.game-top .pill.day { background: var(--day); border-color: #c9a84a; }
.game-top .pill.money { background: #fff6c8; border-color: #d4bc5c; }
.game-top .pill.vida { background: #fde8ef; border-color: #e8a0b4; }
.game-main {
  flex: 1; min-height: 0; display: grid;
  grid-template-columns: 220px minmax(0, 1fr) 240px;
  gap: 10px; padding: 10px;
}
.game-left, .game-right { display: flex; flex-direction: column; gap: 10px; min-height: 0; }
.ui-card {
  background: #f7f0de; border: 2px solid #d8cdb8; border-radius: 8px;
  padding: .65rem .7rem; box-shadow: 2px 3px 0 rgba(44,38,31,.06);
}
.ui-card.pink { background: #fde8ef; border-color: #e8a0b4; }
.ui-card.yellow { background: #fff6c8; border-color: #d4bc5c; }
.ui-card h3 {
  margin: 0 0 .45rem; font-size: .72rem; font-weight: 800;
  letter-spacing: .08em; text-transform: uppercase; color: #6a5848;
}
.ui-card .stat-row {
  display: flex; justify-content: space-between; gap: .5rem;
  font-size: .82rem; margin: .15rem 0;
}
.ui-card .muted { color: var(--muted); font-size: .78rem; }
.ui-card .cotilleo-teaser {
  font-family: Fraunces, Georgia, serif; font-style: italic;
  font-size: .88rem; line-height: 1.35; margin: 0 0 .45rem;
}
.ui-card button.linkish {
  border: 0; background: transparent; padding: 0;
  color: var(--pink-deep); font-weight: 800; cursor: pointer; text-decoration: underline;
}
.bloques-row { display: flex; flex-direction: column; gap: .35rem; }
.bloque-chip {
  display: flex; justify-content: space-between; align-items: center;
  border: 2px solid #c9b59a; background: #fffdf6; border-radius: 6px;
  padding: .35rem .45rem; font-size: .78rem; font-weight: 800; cursor: pointer;
}
.bloque-chip[disabled] { opacity: .55; cursor: default; }
.game-map-wrap {
  min-width: 0; min-height: 0; background: #fff;
  border: 2px solid #e8dfd0; border-radius: 10px; overflow: hidden;
  display: flex; flex-direction: column;
}
.game-map-wrap .play-root { height: 100%; }
.game-map-wrap .board-scroll { inset: 0 !important; }
.game-parejas {
  border-top: 2px solid #e8dfd0; background: #fff;
  padding: .45rem .85rem .65rem;
}
.game-parejas h3 {
  margin: 0 0 .35rem; font-size: .72rem; letter-spacing: .08em;
  text-transform: uppercase; color: #6a5848;
}
.parejas-strip {
  display: flex; gap: .55rem; overflow-x: auto; padding-bottom: .2rem;
}
.pareja-card {
  flex: 0 0 auto; min-width: 150px;
  border: 2px solid #d8cdb8; border-radius: 8px; background: #fffdf8;
  padding: .35rem .45rem; cursor: pointer;
}
.pareja-card .faces { display: flex; align-items: center; gap: .25rem; margin-bottom: .2rem; }
.pareja-card .faces img, .pareja-card .faces .cara-ini {
  width: 28px; height: 28px; border-radius: 50%; object-fit: cover;
  border: 2px solid #fff; box-shadow: 0 1px 2px rgba(0,0,0,.12);
}
.pareja-card .estado {
  font-size: .68rem; font-weight: 800; padding: .12rem .35rem; border-radius: 999px;
  display: inline-block;
}
.pareja-card .estado.bien { background: #dfebd8; color: #3d5a32; }
.pareja-card .estado.regular { background: #f3e6cc; color: #6a5848; }
.pareja-card .estado.crisis { background: #fde0e0; color: #8a3030; }
.btn-nuevo-plan {
  width: 100%; margin-top: .35rem;
  border: 2px solid var(--pink); background: var(--pink);
  color: #fff; font-weight: 800; border-radius: 6px; padding: .45rem;
  cursor: pointer;
}
@media (max-width: 1100px) {
  .game-main { grid-template-columns: 1fr; }
  .game-left, .game-right { flex-direction: row; flex-wrap: wrap; }
  .game-left .ui-card, .game-right .ui-card { flex: 1 1 220px; }
}
`;
  }

  fs.writeFileSync(p, css);
  console.log('css ok');
}

function patchPlayPhp() {
  const p = path.join(root, 'play.php');
  let php = fs.readFileSync(p, 'utf8');
  if (php.includes('game-shell')) {
    console.log('play.php already patched');
    return;
  }

  const stageOld = `  <div class="play-stage">
    <div class="play-root pc"`;
  const stageNew = `  <div class="game-shell">
    <header class="game-top">
      <h1 class="brand">AQUÍ HAY TEMA <span class="heart" aria-hidden="true">♥</span></h1>
      <div class="top-meta">
        <span class="pill day"><span data-dow>—</span> · <span data-fecha></span> · <span data-hora>—</span></span>
        <span class="pill money">Dinero: <span data-dinero>—</span></span>
        <span class="pill vida">Vida del pueblo: <span data-vida-pct>0%</span></span>
      </div>
    </header>
    <div class="game-main">
      <aside class="game-left">
        <section class="ui-card yellow" data-ui-resumen>
          <h3>Resumen</h3>
          <div data-resumen-stats></div>
        </section>
        <section class="ui-card pink" data-ui-cotilleo>
          <h3>Último cotilleo</h3>
          <p class="cotilleo-teaser" data-cotilleo-teaser>—</p>
          <button type="button" class="linkish" data-open="diario">Ver diario</button>
        </section>
        <section class="ui-card" data-ui-bloques>
          <h3>Bloques</h3>
          <div class="bloques-row" data-bloques-row></div>
        </section>
      </aside>
      <div class="game-map-wrap">
  <div class="play-stage">
    <div class="play-root pc"`;

  php = php.replace(stageOld, stageNew);

  php = php.replace(
    `    </div>
  </div>
  <script src="assets/js/play-v3.js`,
    `    </div>
  </div>
      </div>
      <aside class="game-right">
        <section class="ui-card" data-ui-buzon>
          <h3>Buzón</h3>
          <p class="muted" data-buzon-preview>Sin mensajes pendientes.</p>
          <button type="button" class="linkish" data-open="buzon">Abrir buzón</button>
        </section>
        <section class="ui-card" data-ui-proximo>
          <h3>Próximo plan</h3>
          <div data-proximo-plan><p class="muted">Nada programado.</p></div>
          <button type="button" class="linkish" data-open="diario">Ver agenda</button>
        </section>
        <button type="button" class="btn-nuevo-plan" data-open="organizar">+ NUEVO PLAN</button>
        <section class="ui-card">
          <h3>El pueblo</h3>
          <p class="muted">Residentes y fichas.</p>
          <button type="button" class="linkish" data-open="vecinos">Ver personas</button>
        </section>
      </aside>
    </div>
    <footer class="game-parejas">
      <h3>Parejas</h3>
      <div class="parejas-strip" data-parejas-strip></div>
    </footer>
  </div>
  <script src="assets/js/play-v3.js`
  );

  fs.writeFileSync(p, php);
  console.log('play.php ok');
}

function patchJs() {
  const p = path.join(root, 'assets/js/play-v3.js');
  let js = fs.readFileSync(p, 'utf8');

  if (!js.includes('function renderShellPanels')) {
    const insert = `
  function nombreDe(id) {
    const r = (cacheInsp && cacheInsp.residentes && cacheInsp.residentes[id]) || {};
    return (r.identidad_publica && r.identidad_publica.nombre) || id;
  }

  function renderShellPanels(estado, buzon, diario) {
    const partida = cacheInsp || {};
    const res = partida.residentes || {};
    const nRes = Object.keys(res).filter(function (k) { return (res[k].presencia || '') === 'residente'; }).length;
    const parejas = (partida.relaciones_romanticas || []).filter(function (r) {
      return r && r.estado === 'pareja';
    });
    const stats = $('[data-resumen-stats]');
    if (stats) {
      stats.innerHTML =
        '<div class="stat-row"><span>Parejas</span><strong>' + parejas.length + '</strong></div>' +
        '<div class="stat-row"><span>Residentes</span><strong>' + nRes + '</strong></div>' +
        '<div class="stat-row"><span>Buzón pendiente</span><strong>' +
        (buzon || []).filter(function (m) { return (m.estado || '') === 'pendiente'; }).length +
        '</strong></div>';
    }

    const teaser = $('[data-cotilleo-teaser]');
    const hoy = (diario && diario.cotilleo && diario.cotilleo.hoy) || diario.entradas || [];
    const ult = (hoy[0] && (hoy[0].texto || hoy[0].cuerpo || hoy[0].titulo)) || '';
    if (teaser) teaser.textContent = ult || 'Todavía no hay cotilleo hoy.';

    const bloques = $('[data-bloques-row]');
    if (bloques) {
      bloques.innerHTML = '';
      const defs = [
        { id: 'a', key: 'bloque_a', label: 'BLOQUE A' },
        { id: 'b', key: 'bloque_b', label: 'BLOQUE B' },
        { id: 'c', key: 'bloque_c', label: 'BLOQUE C' },
      ];
      defs.forEach(function (d) {
        const blk = partida[d.key];
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'bloque-chip';
        if (!blk || !blk.viviendas) {
          btn.disabled = true;
          btn.textContent = d.label + ' · cerrado';
        } else {
          let occ = 0;
          (blk.viviendas || []).forEach(function (v) { if (v.ocupante_id) occ++; });
          const cap = blk.capacidad || blk.viviendas.length || 16;
          btn.textContent = d.label + ' · ' + occ + '/' + cap;
        }
        bloques.appendChild(btn);
      });
    }

    const prev = $('[data-buzon-preview]');
    const pend = (buzon || []).filter(function (m) {
      return (m.canal || 'buzon') === 'buzon' && (m.estado || '') === 'pendiente';
    });
    if (prev) {
      if (!pend.length) prev.textContent = 'Sin mensajes pendientes.';
      else {
        const m = pend[0];
        prev.textContent = (m.remitente_nombre || m.de || 'Mensaje') + ': ' + (m.preview || m.asunto || m.texto || '').slice(0, 80);
      }
    }

    const proxBox = $('[data-proximo-plan]');
    if (proxBox) {
      const encs = (partida.encuentros || []).filter(function (e) {
        return e && (e.estado === 'programado' || e.estado === 'en_curso');
      });
      encs.sort(function (a, b) {
        const da = (a.dia || 0) * 100 + (a.hora_inicio || a.hora || 0);
        const db = (b.dia || 0) * 100 + (b.hora_inicio || b.hora || 0);
        return da - db;
      });
      const next = encs[0];
      if (!next) proxBox.innerHTML = '<p class="muted">Nada programado.</p>';
      else {
        const parts = (next.participantes || []).map(function (id) { return nombreDe(id); }).join(' · ');
        proxBox.innerHTML = '<p><strong>' + parts + '</strong></p>' +
          '<p class="muted">' + (next.lugar_nombre || next.lugar || 'Lugar') +
          ' · Día ' + (next.dia || '?') + ' ' + String(next.hora_inicio || next.hora || '?').padStart(2, '0') + ':00</p>';
      }
    }

    const strip = $('[data-parejas-strip]');
    if (strip) {
      strip.innerHTML = '';
      parejas.forEach(function (rel) {
        const ids = rel.pareja || rel.participantes || [];
        if (!ids || ids.length < 2) return;
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'pareja-card';
        const tok = function (id) {
          const t = cachePueblo && cachePueblo.tokens && cachePueblo.tokens[id];
          if (t && t.url) return '<img src="' + esc(t.url) + '" alt=""/>';
          return '<span class="cara-ini">' + esc((nombreDe(id)[0] || '?')) + '</span>';
        };
        const est = (rel.estabilidad_pareja && rel.estabilidad_pareja.activa) ? 'bien' : 'regular';
        card.innerHTML = '<div class="faces">' + tok(ids[0]) + '<span>♥</span>' + tok(ids[1]) + '</div>' +
          '<div>' + esc(nombreDe(ids[0])) + ' · ' + esc(nombreDe(ids[1])) + '</div>' +
          '<span class="estado ' + est + '">' + (est === 'bien' ? 'Bien' : 'Regular') + '</span>';
        strip.appendChild(card);
      });
      if (!parejas.length) {
        strip.innerHTML = '<p class="muted">Aún no hay parejas registradas.</p>';
      }
    }
  }

`;
    js = js.replace('  function renderHud(estado, buzon) {', insert + '  function renderHud(estado, buzon) {');
  }

  if (!js.includes('renderShellPanels(estado')) {
    js = js.replace(
      '    renderHud(cacheEstado, buzon.mensajes || []);\n    renderPueblo(mapa.pueblo || { complejos: [] });',
      '    renderHud(cacheEstado, buzon.mensajes || []);\n    renderShellPanels(cacheEstado, buzon.mensajes || [], diario);\n    renderPueblo(mapa.pueblo || { complejos: [] });'
    );
  }

  fs.writeFileSync(p, js);
  console.log('js ok');
}

patchCss();
patchPlayPhp();
patchJs();
