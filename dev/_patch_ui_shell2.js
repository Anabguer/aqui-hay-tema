const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

function patchPlayPhp() {
  let php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
  php = php.replace("$ahtUi = 'v3-20260821b';", "$ahtUi = 'v3-20260821c';");

  if (!php.includes('playtest-float')) {
    const playtestFloat = `  <?php if ($ahtLab): ?>
  <div class="playtest-float" data-playtest-float>
    <button type="button" class="playtest-float-toggle" data-playtest-toggle aria-expanded="false" title="Controles de playtest">🧪 Playtest</button>
    <div class="playtest-float-panel" hidden>
      <p class="playtest-float-title">Laboratorio</p>
      <button type="button" id="btn-nueva">Nueva partida</button>
      <button type="button" class="taller-cheat" id="btn-guardar">Guardar</button>
      <button type="button" class="taller-cheat" data-horas="1">+1h</button>
      <button type="button" class="taller-cheat" data-horas="8">+8h</button>
      <button type="button" class="taller-cheat" data-horas="24">+1 día</button>
      <button type="button" class="taller-cheat" id="btn-proximo">Ir al próximo</button>
      <a class="taller-cheat" href="play-provisional.php">UI anterior</a>
      <span class="msg taller-cheat" data-taller-msg></span>
    </div>
  </div>
  <?php endif; ?>
`;
    php = php.replace('<p class="tutorial-pista"', playtestFloat + '  <p class="tutorial-pista"');
  }

  const shellStart = `  <div class="game-shell">
    <header class="game-top">
      <h1 class="brand">AQUÍ HAY TEMA <span class="heart" aria-hidden="true">♥</span></h1>
      <div class="top-meta">
        <div class="nota nota-dia" style="--rot:-1.2deg">
          <span class="nota-kicker">Día</span>
          <p class="nota-body"><span data-dow>—</span><br/><span data-fecha></span> · <span data-hora>—</span></p>
        </div>
        <div class="nota nota-dinero" style="--rot:0.8deg">
          <span class="nota-kicker">Dinero</span>
          <p class="nota-body"><span data-dinero>—</span></p>
        </div>
        <div class="nota nota-vida" style="--rot:-0.5deg">
          <span class="nota-kicker">Vida del pueblo</span>
          <p class="nota-body vida-row">
            <span class="corazon-hud" aria-hidden="true"><span class="corazon-hud-fill"></span></span>
            <span data-vida-pct>0%</span>
          </p>
        </div>
      </div>
    </header>
    <div class="game-main">
      <aside class="game-left">
        <section class="ui-card paper resumen-card" data-ui-resumen hidden>
          <h3>Resumen</h3>
          <div data-resumen-stats></div>
        </section>
        <button type="button" class="ui-card paper cotilleo-card" data-open="diario" aria-label="Abrir diario">
          <h3>Cotilleo</h3>
          <p class="cotilleo-teaser" data-cotilleo-teaser>—</p>
          <span class="card-flecha" aria-hidden="true">📰</span>
        </button>
        <section class="ui-card paper bloques-card">
          <h3>Residencias</h3>
          <div class="bloques-grid" data-bloques-row></div>
        </section>
        <section class="ui-card paper parejas-panel">
          <div class="parejas-head">
            <h3>Parejas</h3>
            <button type="button" class="btn-papel-sm" data-parejas-ver disabled>Ver todas</button>
          </div>
          <div class="parejas-compact" data-parejas-strip></div>
        </section>
      </aside>
      <div class="game-map-wrap">`;

  php = php.replace(/  <div class="game-shell">[\s\S]*?      <div class="game-map-wrap">/, shellStart);

  const shellEnd = `      </div>
      <aside class="game-right">
        <button type="button" class="buzon-compact" data-open="buzon" aria-label="Abrir buzón">
          <span class="buzon-compact-top"><span class="buzon-tit">BUZÓN ✉️</span><span class="buzon-badge" data-buzon-badge>0</span></span>
          <span class="buzon-hint" data-buzon-preview></span>
        </button>
        <section class="ui-card paper proximo-card">
          <h3>Próximo plan</h3>
          <div data-proximo-plan><p class="muted-soft">Nada programado.</p></div>
          <button type="button" class="btn-papel-sm" data-agenda-btn disabled title="Agenda completa pendiente de implementar">Ver agenda</button>
        </section>
        <button type="button" class="btn-nuevo-plan" data-open="organizar">+ NUEVO PLAN</button>
        <button type="button" class="ui-card paper pueblo-card" data-open="vecinos" aria-label="Ver el pueblo">
          <span class="pueblo-icon" aria-hidden="true">👥</span>
          <span class="pueblo-tit">EL PUEBLO</span>
          <span class="pueblo-sub">Residentes y fichas</span>
        </button>
      </aside>
    </div>
  </div>`;

  php = php.replace(
    /      <\/div>\s*<aside class="game-right">[\s\S]*?<footer class="game-parejas">[\s\S]*?<\/footer>\s*<\/div>/,
    shellEnd
  );

  fs.writeFileSync(path.join(root, 'play.php'), php);
  console.log('play.php ok');
}

function patchJs() {
  let js = fs.readFileSync(path.join(root, 'assets/js/play-v3.js'), 'utf8');

  js = js.replace(
    "    if (v === null || v === undefined || v === '') return '—';\n    return String(Math.round(Number(v))) + ' €';",
    "    if (v === null || v === undefined || v === '') return '—';\n    const n = Number(v);\n    if (n === 0) return '0 €';\n    return String(Math.round(n)) + ' €';"
  );

  js = js.replace(
    `    const fill = $('.corazon-fill') || $('.corazon-dibujo');
    if (fill) fill.style.setProperty('--fill', pct + '%');
    const pctN = $('[data-vida-pct]');
    if (pctN) pctN.textContent = Math.round(pct) + '%';`,
    `    const fill = $('.corazon-fill') || $('.corazon-dibujo');
    if (fill) fill.style.setProperty('--fill', pct + '%');
    const hudFill = $('.corazon-hud-fill');
    if (hudFill) hudFill.style.width = pct + '%';
    const pctN = $('[data-vida-pct]');
    if (pctN) pctN.textContent = Math.round(pct) + '%';`
  );

  const newShell = `  function renderShellPanels(estado, buzon, diario) {
    const partida = cacheInsp || {};
    const res = partida.residentes || {};
    const nRes = Object.keys(res).filter(function (k) { return (res[k].presencia || '') === 'residente'; }).length;
    const parejas = (partida.relaciones_romanticas || []).filter(function (r) {
      return r && r.estado === 'pareja';
    });
    const pend = (buzon || []).filter(function (m) {
      return (m.canal || 'buzon') === 'buzon' && (m.estado || '') === 'pendiente';
    });
    const encs = (partida.encuentros || []).filter(function (e) {
      return e && (e.estado === 'programado' || e.estado === 'en_curso');
    });

    const rows = [];
    if (parejas.length) rows.push({ k: 'Parejas', v: String(parejas.length) });
    if (nRes) rows.push({ k: 'Residentes', v: String(nRes) });
    if (encs.length) rows.push({ k: 'Planes activos', v: String(encs.length) });
    if (pend.length) rows.push({ k: 'Buzón pendiente', v: String(pend.length) });

    const resumenCard = $('[data-ui-resumen]');
    const stats = $('[data-resumen-stats]');
    if (resumenCard && stats) {
      if (!rows.length) {
        resumenCard.hidden = true;
        stats.innerHTML = '';
      } else {
        resumenCard.hidden = false;
        stats.innerHTML = rows.map(function (r) {
          return '<div class="stat-row"><span>' + r.k + '</span><strong>' + r.v + '</strong></div>';
        }).join('');
      }
    }

    const teaser = $('[data-cotilleo-teaser]');
    const hoy = (diario && diario.cotilleo && diario.cotilleo.hoy) || diario.entradas || [];
    const ult = (hoy[0] && (hoy[0].texto || hoy[0].cuerpo || hoy[0].titulo)) || '';
    if (teaser) teaser.textContent = ult || 'Todavía no hay cotilleo hoy.';

    const bloques = $('[data-bloques-row]');
    if (bloques) {
      bloques.innerHTML = '';
      [
        { key: 'bloque_a', label: 'A' },
        { key: 'bloque_b', label: 'B' },
        { key: 'bloque_c', label: 'C' },
      ].forEach(function (d) {
        const blk = partida[d.key];
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'bloque-mini' + ((!blk || !blk.viviendas) ? ' is-cerrado' : '');
        if (!blk || !blk.viviendas) {
          btn.disabled = true;
          btn.innerHTML = '<span class="bloque-mini-casa" aria-hidden="true"></span><span class="bloque-mini-txt">BLOQUE ' + d.label + '<br/><em>CERRADO</em></span>';
        } else {
          let occ = 0;
          (blk.viviendas || []).forEach(function (v) { if (v.ocupante_id) occ++; });
          const cap = blk.capacidad || blk.viviendas.length || 16;
          btn.innerHTML = '<span class="bloque-mini-casa" aria-hidden="true"></span><span class="bloque-mini-txt">BLOQUE ' + d.label + '<br/><strong>' + occ + '/' + cap + '</strong></span>';
        }
        bloques.appendChild(btn);
      });
    }

    const badge = $('[data-buzon-badge]');
    if (badge) {
      badge.textContent = String(pend.length);
      badge.classList.toggle('is-on', pend.length > 0);
    }
    const prev = $('[data-buzon-preview]');
    if (prev) {
      if (!pend.length) prev.textContent = 'Sin cartas pendientes';
      else {
        const m = pend[0];
        prev.textContent = (m.remitente_nombre || nombreDe(m.de_persona || m.de) || 'Mensaje') + ': ' +
          (m.preview || m.asunto || m.texto || '').slice(0, 72);
      }
    }

    const proxBox = $('[data-proximo-plan]');
    if (proxBox) {
      encs.sort(function (a, b) {
        const da = (a.dia || 0) * 100 + (a.hora_inicio || a.hora || 0);
        const db = (b.dia || 0) * 100 + (b.hora_inicio || b.hora || 0);
        return da - db;
      });
      const next = encs[0];
      if (!next) proxBox.innerHTML = '<p class="muted-soft">Nada programado.</p>';
      else {
        const ids = next.participantes || [];
        const faces = ids.slice(0, 2).map(function (id) {
          const t = cachePueblo && cachePueblo.tokens && cachePueblo.tokens[id];
          if (t && t.url) return '<img src="' + esc(t.url) + '" alt=""/>';
          return '<span class="cara-ini">' + esc((nombreDe(id)[0] || '?')) + '</span>';
        }).join('');
        proxBox.innerHTML = '<div class="prox-faces">' + faces + '</div>' +
          '<p class="prox-nombres"><strong>' + ids.map(function (id) { return esc(nombreDe(id)); }).join(' · ') + '</strong></p>' +
          '<p class="muted-soft">' + esc(next.lugar_nombre || next.lugar || 'Lugar') +
          ' · Día ' + (next.dia || '?') + ' · ' + String(next.hora_inicio || next.hora || '?').padStart(2, '0') + ':00</p>';
      }
    }

    const strip = $('[data-parejas-strip]');
    const verBtn = $('[data-parejas-ver]');
    if (strip) {
      strip.innerHTML = '';
      parejas.slice(0, 4).forEach(function (rel) {
        const ids = rel.pareja || rel.participantes || [];
        if (!ids || ids.length < 2) return;
        const row = document.createElement('div');
        row.className = 'pareja-row';
        const tok = function (id) {
          const t = cachePueblo && cachePueblo.tokens && cachePueblo.tokens[id];
          if (t && t.url) return '<img src="' + esc(t.url) + '" alt=""/>';
          return '<span class="cara-ini">' + esc((nombreDe(id)[0] || '?')) + '</span>';
        };
        row.innerHTML = '<span class="pareja-faces">' + tok(ids[0]) + tok(ids[1]) + '</span>' +
          '<span class="pareja-nombres">' + esc(nombreDe(ids[0])) + ' · ' + esc(nombreDe(ids[1])) + '</span>';
        strip.appendChild(row);
      });
      if (!parejas.length) strip.innerHTML = '<p class="muted-soft">Sin parejas todavía.</p>';
    }
    if (verBtn) verBtn.disabled = parejas.length <= 4;
  }`;

  js = js.replace(/  function renderShellPanels\([\s\S]*?\n  \}\n\n  function renderHud/, newShell + '\n\n  function renderHud');

  const newBuzon = `  function renderBuzon(msgs) {
    cacheBuzon = msgs || [];
    const box = $('[data-buzon-list]');
    box.innerHTML = '';
    const cartas = cacheBuzon.filter(function (m) { return (m.canal || 'buzon') !== 'cotilleo'; });
    if (!cartas.length) {
      box.innerHTML = '<p class="lista-vacia">Bandeja vacía. Todavía no te ha escrito nadie.</p>';
      return;
    }
    cartas.forEach(function (m) {
      const art = document.createElement('article');
      const st = estadoCarta(m);
      art.className = 'carta-msg' + (m.clasificacion === 'importante' ? ' importante' : '') +
        ((m.estado || '') === 'pendiente' ? ' no-leida' : '') + (st.cls ? ' ' + st.cls : '');
      const de = nombreDe(m.de_persona);
      const cuerpo = cuerpoCarta(m, de);
      const plazo = m.plazo_humano || '';
      const rid = m.de_persona || m.de;
      const tok = tokenDe(rid);
      const avatar = tok
        ? '<img class="carta-avatar" src="' + esc(tok) + '" alt=""/>'
        : '<span class="carta-avatar cara-ini">' + esc((de[0] || '?')) + '</span>';
      art.innerHTML = (m.clasificacion === 'importante' ? '<span class="lacre" aria-hidden="true"></span>' : '') +
        '<div class="carta-inner">' + avatar +
        '<div class="carta-copy">' +
        (st.txt ? '<div class="sello-estado">' + esc(st.txt) + '</div>' : '') +
        '<div class="de">De ' + esc(de) + '</div>' +
        '<p class="cuerpo">' + esc(cuerpo) + '</p>' +
        (plazo ? '<p class="plazo">' + esc(plazo) + '</p>' : '') +
        '</div></div>';
      art.addEventListener('click', async function () {
        let tr = null;
        if (m.id) {
          const lr = await api('buzon.leer', { mensaje_id: m.id });
          tr = lr.tutorial || null;
        }
        await refresh();
        if (tr) pintarTutorialMotor(tr);
      });
      box.appendChild(art);
    });
  }`;

  js = js.replace(/  function renderBuzon\(msgs\) \{[\s\S]*?\n  \}\n\n  function nombreDe\(id\) \{[\s\S]*?\n  \}\n\n  function renderCotilleo/, newBuzon + '\n\n  function renderCotilleo');

  js = js.replace(
    `    if (t && t.closest('.play-root')) {
      setCapa('');
      $('.play-root').removeAttribute('data-consulta');
      return;
    }
    const open = ev.target.closest('[data-open]');
    if (open && open.closest('.play-root')) {
      const name = open.getAttribute('data-open');
      setCapa(name);
      $('.play-root').removeAttribute('data-consulta');
      if (name === 'organizar') fillOrganizar();
      if (name === 'diario') $('[data-diario-tab="hoy"]').click();
      return;
    }`,
    `    if (t && (t.closest('.play-root') || t.closest('.capa'))) {
      setCapa('');
      $('.play-root').removeAttribute('data-consulta');
      return;
    }
    const open = ev.target.closest('[data-open]');
    if (open && (open.closest('.play-root') || open.closest('.game-shell'))) {
      const name = open.getAttribute('data-open');
      setCapa(name);
      $('.play-root').removeAttribute('data-consulta');
      if (name === 'organizar') fillOrganizar();
      if (name === 'diario') {
        const tab = $('[data-diario-tab="hoy"]');
        if (tab) tab.click();
      }
      return;
    }`
  );

  if (!js.includes('playtest-toggle')) {
    js += `
  const ptToggle = document.querySelector('[data-playtest-toggle]');
  const ptPanel = document.querySelector('.playtest-float-panel');
  if (ptToggle && ptPanel) {
    ptToggle.addEventListener('click', function () {
      const open = ptPanel.hidden;
      ptPanel.hidden = !open;
      ptToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }
  const parejasVer = document.querySelector('[data-parejas-ver]');
  if (parejasVer) {
    parejasVer.addEventListener('click', function () {
      if (parejasVer.disabled) return;
      setCapa('vecinos');
    });
  }
`;
  }

  fs.writeFileSync(path.join(root, 'assets/js/play-v3.js'), js);
  console.log('js ok');
}

function patchCss() {
  const p = path.join(root, 'assets/css/play-v3-app.css');
  let css = fs.readFileSync(p, 'utf8');

  css = css.replace(/body\.play-v3 \{\s*background:[^}]+\}/, `body.play-v3 {
  background: #fafaf8;
  color: var(--ink);
  font-family: Nunito, "Segoe UI", sans-serif;
  min-height: 100%;
}`);

  if (!css.includes('playtest-float')) {
    css += `

/* === Playtest flotante (lab) === */
body.play-v3[data-lab="1"] .taller,
body.play-v3[data-lab="1"] .playtest-cheats { display: none !important; }
body.play-v3[data-lab="1"] .game-shell { height: 100vh; min-height: 0; }
.playtest-float {
  position: fixed; z-index: 90; right: 12px; bottom: 12px;
  font-family: Nunito, "Segoe UI", sans-serif;
}
.playtest-float-toggle {
  border: 2px solid #2c261f; background: #fff6c8; color: #2c261f;
  font-weight: 800; font-size: .78rem; padding: .45rem .65rem;
  border-radius: 999px; cursor: pointer; box-shadow: 3px 4px 0 rgba(44,38,31,.15);
  transform: rotate(-1deg);
}
.playtest-float-panel {
  position: absolute; right: 0; bottom: calc(100% + 8px);
  width: min(280px, 88vw); padding: .65rem;
  background: #fffdf6; border: 2px solid #2c261f;
  box-shadow: 6px 8px 0 rgba(44,38,31,.12); border-radius: 8px;
  display: flex; flex-wrap: wrap; gap: .35rem;
}
.playtest-float-panel[hidden] { display: none !important; }
.playtest-float-title {
  width: 100%; margin: 0 0 .2rem; font-size: .68rem; font-weight: 800;
  letter-spacing: .08em; text-transform: uppercase; color: #7a7164;
}
.playtest-float-panel button, .playtest-float-panel a {
  border: 2px solid #8a7a66; background: #fff; font: inherit; font-weight: 800;
  font-size: .72rem; padding: .25rem .45rem; cursor: pointer; text-decoration: none; color: inherit;
}

/* === Shell ilustrada === */
.game-shell { background: #fafaf8; }
.game-main { background: #fafaf8; gap: 8px; padding: 8px; }
.game-left, .game-right { background: transparent; }
.ui-card.paper, .buzon-compact, .pueblo-card, .cotilleo-card {
  border: 2px solid #2c261f; border-radius: 10px;
  box-shadow: 3px 4px 0 rgba(44,38,31,.08);
  text-align: left; cursor: default;
}
.ui-card.paper {
  background: #f7f0de; padding: .65rem .7rem;
  transform: rotate(var(--rot, -0.3deg));
}
.cotilleo-card, .pueblo-card, .buzon-compact {
  width: 100%; font: inherit; color: inherit;
  transform: rotate(var(--rot, 0.4deg));
}
.cotilleo-card { position: relative; padding-right: 2rem; cursor: pointer; }
.cotilleo-card:hover, .pueblo-card:hover, .buzon-compact:hover { filter: brightness(1.02); }
.card-flecha { position: absolute; right: .55rem; bottom: .45rem; font-size: 1.1rem; opacity: .85; }
.ui-card h3, .cotilleo-card h3 {
  margin: 0 0 .35rem; font-size: .68rem; font-weight: 800;
  letter-spacing: .08em; text-transform: uppercase; color: #6a5848;
}
.cotilleo-teaser {
  font-family: Fraunces, Georgia, serif; font-style: italic;
  font-size: .86rem; line-height: 1.35; margin: 0; color: #2c261f;
}
.ui-card.pink { background: #fde8ef; border-color: #e8a0b4; }
.ui-card.yellow { background: #fff6c8; border-color: #d4bc5c; }
.muted-soft { color: #7a7164; font-size: .78rem; margin: 0; line-height: 1.35; }

/* Cabecera notas */
.game-top {
  background: #fafaf8; border-bottom: 2px solid #ebe6dc; padding: .5rem .85rem;
}
.top-meta { margin-left: auto; }
.nota {
  display: inline-block; vertical-align: top;
  min-width: 108px; padding: .35rem .55rem .4rem;
  border: 2px solid #2c261f; border-radius: 6px;
  box-shadow: 2px 3px 0 rgba(44,38,31,.1);
  transform: rotate(var(--rot, 0deg));
}
.nota-dia, .nota-dinero { background: #fff6c8; }
.nota-vida { background: #fde8ef; border-color: #d48aa0; }
.nota-kicker {
  display: block; font-size: .58rem; font-weight: 800; letter-spacing: .1em;
  text-transform: uppercase; color: #6a5848; margin-bottom: .12rem;
}
.nota-body { margin: 0; font-family: Fraunces, Georgia, serif; font-weight: 700; font-size: .82rem; line-height: 1.25; }
.vida-row { display: flex; align-items: center; gap: .35rem; }
.corazon-hud {
  display: inline-block; width: 52px; height: 12px; border: 2px solid #2c261f;
  background: #fff; border-radius: 999px; overflow: hidden; vertical-align: middle;
}
.corazon-hud-fill { display: block; height: 100%; width: 0%; background: linear-gradient(90deg, #e56b8a, #c42b4a); }

/* Bloques mini */
.bloques-grid { display: flex; flex-direction: column; gap: .35rem; }
.bloque-mini {
  display: flex; align-items: center; gap: .45rem;
  border: 2px solid #2c261f; background: #fffdf6; border-radius: 8px;
  padding: .35rem .45rem; cursor: default; text-align: left; width: 100%;
  box-shadow: 2px 2px 0 rgba(44,38,31,.06);
}
.bloque-mini.is-cerrado { opacity: .72; filter: saturate(.7); }
.bloque-mini-casa {
  width: 28px; height: 24px; flex: 0 0 28px; position: relative;
  background: #e8d5b3; border: 2px solid #2c261f; border-radius: 2px 2px 0 0;
}
.bloque-mini-casa::before {
  content: ""; position: absolute; left: -3px; right: -3px; top: -10px; height: 12px;
  background: #c45; clip-path: polygon(50% 0, 100% 100%, 0 100%);
  border: 2px solid #2c261f; border-bottom: 0;
}
.bloque-mini-txt { font-size: .72rem; font-weight: 800; line-height: 1.25; }
.bloque-mini-txt em { font-style: normal; color: #7a7164; font-weight: 700; }

/* Buzón compacto */
.buzon-compact {
  display: block; padding: .55rem .65rem; background: #f7f0de;
  border: 2px solid #2c261f; border-radius: 10px; cursor: pointer;
  box-shadow: 3px 4px 0 rgba(44,38,31,.08); transform: rotate(-0.6deg);
}
.buzon-compact-top { display: flex; justify-content: space-between; align-items: center; }
.buzon-tit { font-weight: 800; font-size: .82rem; letter-spacing: .04em; }
.buzon-badge {
  min-width: 1.4rem; text-align: center; font-weight: 800; font-size: .75rem;
  background: #fff; border: 2px solid #2c261f; border-radius: 999px; padding: 0 .35rem;
}
.buzon-badge.is-on { background: #fde8ef; border-color: #c42b4a; }
.buzon-hint { display: block; margin-top: .3rem; font-size: .72rem; color: #6a5848; line-height: 1.3; }

/* Pueblo + botones papel */
.pueblo-card {
  display: flex; flex-direction: column; align-items: flex-start; gap: .15rem;
  padding: .65rem .7rem; background: #fffdf8; cursor: pointer;
}
.pueblo-icon { font-size: 1.35rem; }
.pueblo-tit { font-weight: 800; font-size: .88rem; letter-spacing: .06em; }
.pueblo-sub { font-size: .72rem; color: #7a7164; }
.btn-papel-sm {
  margin-top: .4rem; border: 2px solid #8a7a66; background: #fffdf6;
  font: inherit; font-weight: 800; font-size: .68rem; padding: .22rem .45rem;
  cursor: pointer; border-radius: 4px; box-shadow: 2px 2px 0 rgba(44,38,31,.08);
  text-decoration: none; color: inherit;
}
.btn-papel-sm:disabled { opacity: .45; cursor: not-allowed; }
.btn-nuevo-plan {
  border: 2px solid #c42b4a; background: #e56b8a; color: #fff;
  font-weight: 800; font-size: .78rem; padding: .5rem .65rem; border-radius: 8px;
  cursor: pointer; box-shadow: 3px 3px 0 rgba(44,38,31,.12); transform: rotate(-0.5deg);
  width: 100%;
}
.btn-nuevo-plan:hover { filter: brightness(1.05); }

/* Parejas compactas (columna izq) */
.parejas-panel { transform: rotate(0.2deg); }
.parejas-head { display: flex; justify-content: space-between; align-items: center; gap: .35rem; }
.parejas-head h3 { margin: 0; }
.parejas-compact { display: flex; flex-direction: column; gap: .35rem; margin-top: .35rem; max-height: 140px; overflow-y: auto; }
.pareja-row { display: flex; align-items: center; gap: .4rem; font-size: .72rem; }
.pareja-faces img, .pareja-faces .cara-ini {
  width: 22px; height: 22px; border-radius: 50%; object-fit: cover;
  border: 1.5px solid #fff; box-shadow: 0 1px 1px rgba(0,0,0,.12); margin-right: -6px;
}
.pareja-nombres { line-height: 1.2; }

/* Próximo plan */
.prox-faces img, .prox-faces .cara-ini {
  width: 32px; height: 32px; border-radius: 50%; object-fit: cover;
  border: 2px solid #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1); margin-right: .25rem;
}
.prox-nombres { margin: .25rem 0 .1rem; font-size: .82rem; }

/* Edificios EN OBRAS */
.play-v3 .edificios-layer .edif.is-off {
  opacity: 1;
  filter: saturate(.45) brightness(.95) contrast(.95) drop-shadow(0 2px 2px rgba(44,38,31,.12));
}
.play-v3 .edificios-layer .edif.is-off::before {
  content: "EN OBRAS";
  position: absolute; left: 50%; top: 8px; transform: translateX(-50%) rotate(-2deg);
  font: 800 9px/1 Nunito, "Segoe UI", sans-serif; letter-spacing: .06em;
  color: #2c261f; background: #fff6c8; border: 2px solid #2c261f;
  padding: 2px 6px; border-radius: 3px; box-shadow: 2px 2px 0 rgba(44,38,31,.15);
  pointer-events: none; z-index: 2;
}
.play-v3 .edificios-layer .edif.is-off::after {
  content: "";
  position: absolute; inset: 0; pointer-events: none;
  background: repeating-linear-gradient(
    -45deg,
    rgba(255,255,255,0) 0 8px,
    rgba(196,43,74,.12) 8px 16px
  );
  border: 2px dashed rgba(122,113,100,.55);
  box-sizing: border-box;
}

/* Buzón modal */
.play-v3 .capa-buzon {
  width: min(520px, 94vw) !important;
  max-width: 520px !important;
  max-height: min(88vh, 720px) !important;
  padding: 1rem 1rem 1.1rem !important;
}
.play-v3 .capa-buzon [data-buzon-list] {
  max-height: calc(88vh - 120px);
  overflow-y: auto;
  padding-right: .25rem;
  scrollbar-width: thin;
  scrollbar-color: #c9b59a transparent;
}
.play-v3 .capa-buzon [data-buzon-list]::-webkit-scrollbar { width: 6px; }
.play-v3 .capa-buzon [data-buzon-list]::-webkit-scrollbar-thumb {
  background: #c9b59a; border-radius: 999px;
}
.play-v3 .carta-msg {
  display: block !important;
  padding: .85rem .65rem !important;
  border-bottom: 2px dashed #d5c9b4;
}
.play-v3 .carta-inner {
  display: flex; gap: .75rem; align-items: flex-start;
  min-width: 0; width: 100%;
}
.play-v3 .carta-avatar {
  flex: 0 0 48px; width: 48px; height: 48px; border-radius: 50%;
  border: 2px solid #2c261f; object-fit: cover; background: #fff;
}
.play-v3 .carta-copy { flex: 1; min-width: 0; }
.play-v3 .carta-msg .cuerpo {
  white-space: normal; word-break: normal; overflow-wrap: anywhere;
  max-width: none;
}
.game-map-wrap {
  background: #fff; border: 2px solid #ebe6dc; border-radius: 12px;
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.8);
}
`;
  }

  css = css.replace(/\/\* Shell UI \(maqueta[\s\S]*$/, '');

  fs.writeFileSync(p, css);
  console.log('css ok');
}

patchPlayPhp();
patchJs();
patchCss();
