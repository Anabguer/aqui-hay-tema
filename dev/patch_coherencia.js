/**
 * Pasada coherencia funcional + composición (sin tocar geometría mapa).
 */
const fs = require('fs');
const path = require('path');
const ROOT = path.join(__dirname, '..');

function read(p) { return fs.readFileSync(path.join(ROOT, p), 'utf8'); }
function write(p, c) { fs.writeFileSync(path.join(ROOT, p), c); }

// === play.php ===
let php = read('play.php');
php = php.replace("$ahtUi = 'v3-20260821f';", "$ahtUi = 'v3-20260821g';");
php = php.replace(
  `        <div class="obj-resumen" data-ui-resumen hidden>
          <div data-resumen-stats></div>
        </div>`,
  `        <button type="button" class="obj-vecinos-resumen" data-open="vecinos" aria-label="Ver vecinos">
          <span class="obj-vecinos-tit">Vecinos</span>
          <div class="obj-vecinos-stats" data-resumen-stats></div>
        </button>`
);
php = php.replace('        <div class="obj-bloques" data-bloques-row></div>\n      </aside>', '      </aside>');
php = php.replace(
  `      <aside class="game-right zona-personas">
        <button type="button" class="obj-pueblo" data-open="vecinos" aria-label="Ver el pueblo">
          <span class="obj-pueblo-faces" data-pueblo-faces aria-hidden="true"></span>
          <span class="obj-pueblo-txt">EL PUEBLO</span>
        </button>
        <div class="obj-parejas">`,
  `      <aside class="game-right zona-personas">
        <div class="obj-bloques-res" data-bloques-row></div>
        <div class="obj-parejas">`
);
write('play.php', php);

// === GameError + motor hora pasada ===
let ge = read('src/Engine/GameError.php');
if (!ge.includes('HORA_PASADA')) {
  ge = ge.replace(
    "    public const MISMA_PERSONA = 'MISMA_PERSONA';",
    "    public const MISMA_PERSONA = 'MISMA_PERSONA';\n    public const HORA_PASADA = 'HORA_PASADA';"
  );
  ge = ge.replace(
    "            case self::MISMA_PERSONA:",
    "            case self::HORA_PASADA:\n                return 'Esa hora ya ha pasado.';\n            case self::MISMA_PERSONA:"
  );
  write('src/Engine/GameError.php', ge);
}

let rel = read('src/Engine/Reloj.php');
if (!rel.includes('function esFuturo')) {
  rel = rel.replace(
    '    public static function avanzarHoras(array &$partida, int $horas): void',
    `    /** Slot estrictamente posterior al reloj actual (misma hora = pasado). */
    public static function esFuturo(array $reloj, int $dia, int $hora): bool
    {
        $nowD = (int) ($reloj['dia_pueblo'] ?? 1);
        $nowH = (int) ($reloj['hora_actual'] ?? 0);
        return ($dia * 24 + $hora) > ($nowD * 24 + $nowH);
    }

    public static function avanzarHoras(array &$partida, int $horas): void`
  );
  write('src/Engine/Reloj.php', rel);
}

let prop = read('src/Engine/PropuestaEncuentroEngine.php');
if (!prop.includes('Reloj::esFuturo')) {
  prop = prop.replace(
    "        $lugarId = $ctx['lugar'];\n        $tipo = PropuestaNivel::aliasTipo($tipo);",
    "        $lugarId = $ctx['lugar'];\n        if (!Reloj::esFuturo($partida['reloj'] ?? [], $dia, $hora)) {\n            return GameError::respuesta(GameError::HORA_PASADA, ['dia' => $dia, 'hora' => $hora]);\n        }\n        $tipo = PropuestaNivel::aliasTipo($tipo);"
  );
  write('src/Engine/PropuestaEncuentroEngine.php', prop);
}

let enc = read('src/Engine/EncuentroEngine.php');
if (!enc.includes('Reloj::esFuturo')) {
  enc = enc.replace(
    "        $ctx = self::validarContexto($partida, $participantes, $tipo, $lugarId, $logger);\n        if (!($ctx['ok'] ?? false)) {\n            return $ctx;\n        }\n        $participantes = $ctx['participantes'];",
    "        $ctx = self::validarContexto($partida, $participantes, $tipo, $lugarId, $logger);\n        if (!($ctx['ok'] ?? false)) {\n            return $ctx;\n        }\n        if (!Reloj::esFuturo($partida['reloj'] ?? [], $dia, $hora)) {\n            return GameError::respuesta(GameError::HORA_PASADA, ['dia' => $dia, 'hora' => $hora]);\n        }\n        $participantes = $ctx['participantes'];"
  );
  write('src/Engine/EncuentroEngine.php', enc);
}

// === play-v3.js — insert helpers after FASE_DESTINO ===
let js = read('assets/js/play-v3.js');

const HELPERS = `
  const ORGANIZAR_EXCLUIDOS = ['lug_tienda_ropa'];
  const DESTINO_NOMBRE = {
    lug_cafeteria: 'Cafetería', lug_biblioteca: 'Biblioteca', lug_tienda_ropa: 'Tienda',
    lug_restaurante: 'Restaurante', lug_bingo: 'Bingo', lug_cine: 'Cine', lug_arcade: 'Recreativo',
    lug_bar: 'Bar', lug_discoteca: 'Discoteca', lug_karaoke: 'Karaoke', lug_picnic: 'Picnic',
    lug_mirador: 'Mirador', lug_gimnasio: 'Gimnasio', lug_spa: 'Spa', lug_parque: 'Parque'
  };
  /** DEMO UI lab — no persistir, no mezclar con partida real. */
  const LAB_DEMO_PAREJAS = [
    { demo: true, ids: ['per_p002', 'per_p003'] },
    { demo: true, ids: ['per_p004', 'per_p001'] }
  ];
  const LAB_DEMO_PROXIMO = {
    demo: true,
    participantes: ['per_p002', 'per_p003'],
    lugar_nombre: 'Cafetería',
    hora_inicio: 17
  };

  function idsPareja(rel) {
    if (!rel) return [];
    if (Array.isArray(rel.pareja) && rel.pareja.length >= 2) return rel.pareja;
    if (Array.isArray(rel.participantes) && rel.participantes.length >= 2) return rel.participantes;
    if (rel.residente_a && rel.residente_b) return [rel.residente_a, rel.residente_b];
    return [];
  }

  function metricasSociales(partida) {
    const res = partida.residentes || {};
    const ids = Object.keys(res).filter(function (k) { return (res[k].presencia || '') === 'residente'; });
    const emo = { alegre: 0, triste: 0, enfadado: 0 };
    ids.forEach(function (id) {
      const rt = res[id].runtime && res[id].runtime.estado_emocional;
      const eid = rt && rt.id ? String(rt.id) : 'neutro';
      if (eid === 'alegre') emo.alegre++;
      else if (eid === 'triste') emo.triste++;
      else if (eid === 'enfadado') emo.enfadado++;
    });
    let parejas = 0;
    let crisis = 0;
    (partida.relaciones_romanticas || []).forEach(function (r) {
      const est = String(r.estado_pareja || r.estado || '');
      if (est === 'pareja') parejas++;
      if (est === 'crisis') crisis++;
    });
    return { vecinos: ids.length, parejas: parejas, crisis: crisis, emo: emo };
  }

  function posicionarPopover(anchor, panel) {
    const board = $('.board-fit');
    if (!anchor || !panel || !board) return;
    panel.style.right = 'auto';
    const br = board.getBoundingClientRect();
    const ar = anchor.getBoundingClientRect();
    const pw = panel.offsetWidth || 220;
    const ph = panel.offsetHeight || 160;
    let left = ar.left - br.left + (ar.width / 2) - (pw / 2);
    let top = ar.top - br.top - ph - 10;
    if (top < 6) top = ar.bottom - br.top + 10;
    left = Math.max(6, Math.min(left, br.width - pw - 6));
    top = Math.max(6, Math.min(top, br.height - ph - 6));
    panel.style.left = left + 'px';
    panel.style.top = top + 'px';
  }

  function findDestinoMeta(destId) {
    let found = null;
    (cachePueblo && cachePueblo.complejos || []).forEach(function (cx) {
      (cx.destinos || []).forEach(function (d) {
        if (d.id === destId) found = { cx: cx, dest: d };
      });
      if (!found) {
        (cx.destinos_operativos || []).forEach(function (d) {
          if (d.id === destId) found = { cx: cx, dest: d };
        });
      }
    });
    return found;
  }
`;

if (!js.includes('metricasSociales')) {
  js = js.replace(
    "  picnic: 'lug_picnic', mirador: 'lug_mirador'\n};",
    "  picnic: 'lug_picnic', mirador: 'lug_mirador'\n};" + HELPERS
  );
}

// destinosParaOrganizar
if (!js.includes('destinosParaOrganizar')) {
  js = js.replace(
    `  function destinosOperativos() {
    const out = [];
    (cachePueblo && cachePueblo.complejos || []).forEach(function (c) {
      (c.destinos_operativos || []).forEach(function (d) { out.push(d); });
    });
    return out;
  }`,
    `  function destinosOperativos() {
    const out = [];
    (cachePueblo && cachePueblo.complejos || []).forEach(function (c) {
      (c.destinos_operativos || []).forEach(function (d) { out.push(d); });
    });
    return out;
  }

  function destinosParaOrganizar() {
    return destinosOperativos().filter(function (d) {
      return ORGANIZAR_EXCLUIDOS.indexOf(d.id) < 0;
    });
  }

  function refreshHorasOrganizar() {
    const fd = $('[data-org-dia]');
    const hora = $('[data-org-hora]');
    if (!fd || !hora) return;
    const diaSel = parseInt(fd.value, 10);
    const hoy = cacheEstado && cacheEstado.reloj && cacheEstado.reloj.dia_pueblo;
    const horaAhora = cacheEstado && cacheEstado.reloj ? cacheEstado.reloj.hora_actual : 0;
    const minH = (diaSel === hoy) ? Math.max(8, horaAhora + 1) : 8;
    const prev = hora.value;
    hora.innerHTML = '';
    for (let h = minH; h <= 23; h++) {
      const o = document.createElement('option');
      o.value = String(h);
      o.textContent = String(h).padStart(2, '0') + ':00';
      hora.appendChild(o);
    }
    if (prev && parseInt(prev, 10) >= minH) hora.value = prev;
    else hora.value = String(minH);
    org.hora = parseInt(hora.value, 10);
  }`
  );
  js = js.replace('destinosOperativos().forEach(function (d) {', 'destinosParaOrganizar().forEach(function (d) {');
  js = js.replace(
    '    hora.value = String(org.hora || 17);\n    refreshTipos();',
    '    refreshHorasOrganizar();\n    refreshTipos();'
  );
}

// abrirDestino + replace abrirConsulta usage
const ABRIR_DESTINO = `
  function abrirDestino(destId, anchorEl) {
    if (!destId) return;
    if (destId === 'lug_tienda_ropa') {
      $('.play-root').setAttribute('data-consulta', 'quien');
      $('[data-q-tit]').textContent = 'Tienda';
      $('[data-q-sum]').textContent = 'Compras y regalos para el pueblo. La función de tienda llegará pronto.';
      $('[data-q-list]').innerHTML = '';
      const box = $('[data-q-btns]');
      if (box) box.innerHTML = '';
      const panel = $('.quien');
      if (panel) posicionarPopover(anchorEl, panel);
      return;
    }
    const meta = findDestinoMeta(destId);
    const cx = meta && meta.cx;
    if (!cx) return;
    $('.play-root').setAttribute('data-consulta', 'quien');
    const titulo = (meta.dest && meta.dest.nombre) || DESTINO_NOMBRE[destId] || destId.replace('lug_', '');
    $('[data-q-tit]').textContent = titulo;
    const gente = (cx.personas || []).filter(function (p) { return p.destino_id === destId; });
    $('[data-q-sum]').textContent = gente.length
      ? (gente.length === 1 ? 'Hay alguien.' : ('Hay ' + gente.length + '.'))
      : 'Nadie ahora mismo.';
    const list = $('[data-q-list]');
    list.innerHTML = '';
    if (gente.length) {
      const ul = document.createElement('ul');
      gente.forEach(function (p) {
        const li = document.createElement('li');
        li.textContent = p.nombre || p.id;
        ul.appendChild(li);
      });
      list.appendChild(ul);
    }
    const box = $('[data-q-btns]');
    box.innerHTML = '';
    const bOrg = document.createElement('button');
    bOrg.type = 'button';
    bOrg.textContent = 'Organizar aquí';
    bOrg.addEventListener('click', function () {
      org.lugar = destId;
      setCapa('organizar');
      $('.play-root').removeAttribute('data-consulta');
      fillOrganizar();
    });
    box.appendChild(bOrg);
    posicionarPopover(anchorEl, $('.quien'));
  }
`;

if (!js.includes('function abrirDestino')) {
  js = js.replace('  function abrirConsulta(id) {', ABRIR_DESTINO + '\n  function abrirConsulta(id) {');
  js = js.replace(
    `    const cx = ev.target.closest('.complejo[data-complejo]');
    if (cx) {
      abrirConsulta(cx.getAttribute('data-complejo'));
    }`,
    `    const edif = ev.target.closest('.edif[data-destino]');
    if (edif) {
      ev.stopPropagation();
      abrirDestino(edif.getAttribute('data-destino'), edif);
      return;
    }`
  );
}

// Tienda visual class in layoutComposicionDefinitiva
if (!js.includes('is-funcional')) {
  js = js.replace(
    `      img.classList.toggle('is-on', habilitado);
      img.classList.toggle('is-off', !habilitado);`,
    `      img.classList.toggle('is-on', habilitado);
      img.classList.toggle('is-off', !habilitado);
      img.classList.toggle('is-funcional', id === 'tienda');`
  );
}

// Replace renderShellPanels resumen + bloques + parejas + proximo demo
const OLD_RESUMEN = `    const rows = [];
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
    }`;

const NEW_RESUMEN = `    const met = metricasSociales(partida);
    const stats = $('[data-resumen-stats]');
    if (stats) {
      const lines = [];
      lines.push({ icon: '👥', k: 'Vecinos', v: String(met.vecinos) });
      if (met.parejas) lines.push({ icon: '♥', k: 'Parejas', v: String(met.parejas) });
      if (met.crisis) lines.push({ icon: '⚡', k: 'En crisis', v: String(met.crisis) });
      if (met.emo.alegre) lines.push({ icon: '☀', k: 'Alegres', v: String(met.emo.alegre) });
      if (met.emo.triste) lines.push({ icon: '☁', k: 'Tristes', v: String(met.emo.triste) });
      if (met.emo.enfadado) lines.push({ icon: '✦', k: 'Enfadados', v: String(met.emo.enfadado) });
      stats.innerHTML = lines.map(function (r) {
        return '<div class="vecinos-stat"><span class="vecinos-stat-ico" aria-hidden="true">' + r.icon + '</span><span class="vecinos-stat-k">' + r.k + '</span><strong class="vecinos-stat-v">' + r.v + '</strong></div>';
      }).join('');
    }`;

if (js.includes('Buzón pendiente')) {
  js = js.replace(OLD_RESUMEN, NEW_RESUMEN);
}

// Bloques rendering
const OLD_BLOQUES = `    const bloques = $('[data-bloques-row]');
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
        btn.setAttribute('data-bloque', d.label.toLowerCase());
        btn.className = 'obj-bloque bloque-' + d.label.toLowerCase() + ((!blk || !blk.viviendas) ? ' is-cerrado' : '');
        if (!blk || !blk.viviendas) {
          btn.disabled = true;
          btn.innerHTML = '<span class="bloque-fachada" aria-hidden="true"><span class="bloque-letra">' + d.label + '</span></span><span class="bloque-info"><strong>BLOQUE ' + d.label + '</strong><em>CERRADO</em></span>';
        } else {
          let occ = 0;
          (blk.viviendas || []).forEach(function (v) { if (v.ocupante_id) occ++; });
          const cap = blk.capacidad || blk.viviendas.length || 16;
          btn.innerHTML = '<span class="bloque-fachada" aria-hidden="true"><span class="bloque-letra">' + d.label + '</span></span><span class="bloque-info"><strong>BLOQUE ' + d.label + '</strong><span>' + occ + '/' + cap + '</span></span>';
        }
        bloques.appendChild(btn);
      });
    }`;

const NEW_BLOQUES = `    const bloques = $('[data-bloques-row]');
    if (bloques) {
      bloques.innerHTML = '';
      const abiertos = (partida.celeste && partida.celeste.bloques_abiertos) || ['a'];
      [
        { key: 'bloque_a', label: 'A' },
        { key: 'bloque_b', label: 'B' },
        { key: 'bloque_c', label: 'C' },
      ].forEach(function (d) {
        const letra = d.label.toLowerCase();
        const abierto = abiertos.indexOf(letra) >= 0;
        const blk = partida[d.key];
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('data-bloque', letra);
        btn.className = 'obj-bloque-mini bloque-' + letra + (abierto ? ' is-activo' : ' is-cerrado');
        if (!abierto) {
          btn.disabled = true;
          btn.title = 'Próximamente';
          btn.innerHTML = '<span class="bloque-mini-fachada" aria-hidden="true"><span class="bloque-mini-letra">' + d.label + '</span></span><span class="bloque-mini-info"><strong>BLOQUE ' + d.label + '</strong><em>Próximamente</em></span>';
        } else {
          let occ = 0;
          (blk && blk.viviendas || []).forEach(function (v) { if (v.ocupante_id) occ++; });
          const cap = (blk && blk.capacidad) || 16;
          btn.innerHTML = '<span class="bloque-mini-fachada" aria-hidden="true"><span class="bloque-mini-letra">' + d.label + '</span></span><span class="bloque-mini-info"><strong>BLOQUE ' + d.label + '</strong><span>' + occ + '/' + cap + '</span></span>';
        }
        bloques.appendChild(btn);
      });
    }`;

if (js.includes("btn.className = 'obj-bloque bloque-'")) {
  js = js.replace(OLD_BLOQUES, NEW_BLOQUES);
}

// Remove pueblo faces block
js = js.replace(/\n\n    const puebloFaces = \$[\s\S]*?\n    \}\n\n    const badge = \$/, '\n\n    const badge = $');

// Parejas + proximo lab demo
js = js.replace(
  `    const parejas = (partida.relaciones_romanticas || []).filter(function (r) {
      return r && r.estado === 'pareja';
    });`,
  `    const parejasReales = (partida.relaciones_romanticas || []).filter(function (r) {
      return r && String(r.estado_pareja || r.estado || '') === 'pareja';
    });
    const parejas = parejasReales.length ? parejasReales : (IS_LAB ? LAB_DEMO_PAREJAS : []);`
);

js = js.replace(
  `      const next = encs[0];
      if (!next) proxBox.innerHTML = '<p class="obj-proximo-vacio">Nada en agenda. Sospechoso.</p>';`,
  `      let next = encs[0];
      if (!next && IS_LAB) next = LAB_DEMO_PROXIMO;
      if (!next) proxBox.innerHTML = '<p class="obj-proximo-vacio">Nada en agenda. Sospechoso.</p>';`
);

js = js.replace(
  `      parejas.slice(0, 4).forEach(function (rel) {
        const ids = rel.pareja || rel.participantes || [];
        if (!ids || ids.length < 2) return;`,
  `      parejas.slice(0, 4).forEach(function (rel) {
        const ids = rel.demo ? rel.ids : idsPareja(rel);
        if (!ids || ids.length < 2) return;`
);

js = js.replace(
  `      if (!parejas.length) strip.innerHTML = '<p class="obj-parejas-vacio">Todavía no hay parejas. El pueblo está en fase de mirarse de reojo.</p>';`,
  `      if (!parejas.length) strip.innerHTML = '<p class="obj-parejas-vacio">Todavía no hay parejas.</p>';
      else if (IS_LAB && !parejasReales.length) {
        const note = document.createElement('p');
        note.className = 'obj-lab-note';
        note.textContent = 'Vista demo (lab)';
        strip.insertBefore(note, strip.firstChild);
      }`
);

// org dia change listener

if (js.indexOf('refreshHorasOrganizar') >= 0 && js.indexOf("addEventListener('change', refreshHorasOrganizar)") < 0) {
  js = js.replace(
    "$('[data-org-a]').addEventListener('change', refreshTipos);",
    "$('[data-org-dia]').addEventListener('change', refreshHorasOrganizar);\n  $('[data-org-a]').addEventListener('change', refreshTipos);"
  );
}

write('assets/js/play-v3.js', js);

// === CSS shell ===
const shellCss = read('assets/css/play-v3-shell-ui.css');
const EXTRA_SHELL = `

/* === Coherencia: aire, overflow, vecinos, bloques, buzón === */
body.play-v3, .game-shell, .game-main { overflow-x: hidden; max-width: 100vw; }
.game-left, .game-right { overflow-x: hidden; max-width: 100%; }
.zona-actividad { gap: 1.1rem; padding-bottom: .5rem; }
.zona-actividad .obj-vecinos-resumen { margin-bottom: .35rem; }
.zona-actividad .obj-buzon { margin-top: .15rem; }
.zona-actividad .obj-cotilleo { margin-top: .1rem; max-width: 100%; box-sizing: border-box; }
.zona-actividad .obj-proximo { margin-top: .45rem; }
.zona-actividad .obj-nuevo-plan { margin-top: .25rem; }
.zona-personas { gap: 1.25rem; }
.zona-personas .obj-bloques-res { margin-bottom: .35rem; }
.zona-personas .obj-parejas { margin-top: .5rem; }

.obj-vecinos-resumen {
  display: block; width: 100%; padding: .35rem 0 .2rem; border: 0; background: transparent;
  cursor: pointer; text-align: left; font: inherit; color: inherit;
}
.obj-vecinos-tit { display: block; font-size: .58rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: #7a7164; margin-bottom: .35rem; }
.vecinos-stat { display: flex; align-items: center; gap: .35rem; padding: .12rem 0; font-size: .68rem; line-height: 1.25; }
.vecinos-stat-ico { width: 1rem; text-align: center; opacity: .85; font-size: .72rem; }
.vecinos-stat-k { flex: 1; color: #5a5048; }
.vecinos-stat-v { font-weight: 800; }

.obj-bloques-res { display: flex; flex-direction: column; gap: .55rem; width: 100%; }
.obj-bloque-mini {
  display: flex; align-items: center; gap: .45rem; width: 100%; padding: .2rem 0;
  border: 0; background: transparent; font: inherit; text-align: left; color: inherit; cursor: pointer;
}
.obj-bloque-mini.is-activo .bloque-mini-fachada { background: linear-gradient(180deg, #dff3df, #c5e6c5); border-color: #3d6b3d; }
.obj-bloque-mini.is-cerrado { cursor: default; opacity: .72; }
.obj-bloque-mini.is-cerrado .bloque-mini-fachada { background: #ece8e0; border-color: #b8b0a4; filter: grayscale(.15); }
.bloque-mini-fachada {
  width: 30px; height: 26px; flex: 0 0 30px; border: 2px solid #2c261f; position: relative;
  clip-path: polygon(8% 100%, 8% 38%, 50% 8%, 92% 38%, 92% 100%);
}
.bloque-mini-letra { position: absolute; bottom: -5px; left: 50%; transform: translateX(-50%); font-size: .52rem; font-weight: 800; background: #fff; border: 1px solid #2c261f; padding: 0 3px; }
.bloque-mini-info { font-size: .64rem; line-height: 1.25; }
.bloque-mini-info strong { display: block; font-weight: 800; letter-spacing: .04em; }
.bloque-mini-info em { font-style: normal; color: #8a7a66; font-size: .58rem; }

.obj-lab-note { font-size: .55rem; font-style: italic; color: #8a7a66; margin: 0 0 .25rem; }

/* Tienda = instalación funcional */
.play-v3 .edificios-layer .edif.is-funcional.is-on {
  filter: drop-shadow(0 0 4px rgba(120, 90, 200, .35));
  outline: 2px solid rgba(120, 90, 200, .45);
  outline-offset: 1px;
}

/* Buzón modal — menos ventana, más papel */
.play-v3 .capa-buzon {
  background: #fff9ee !important;
  border: 2px solid #2c261f !important;
  box-shadow: 6px 8px 0 rgba(44,38,31,.08), inset 0 0 0 1px rgba(255,255,255,.6) !important;
  border-radius: 2px !important;
  max-width: 540px !important;
}
.play-v3 .capa-buzon > .cerrar {
  position: absolute; top: -12px; right: 12px; border: 2px solid #2c261f; background: #fde8ef;
  font-size: .65rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
  padding: .2rem .45rem; cursor: pointer; transform: rotate(2deg); border-radius: 2px;
  box-shadow: 2px 2px 0 rgba(44,38,31,.1);
}
.play-v3 .capa-buzon h2 { font-family: Fraunces, Georgia, serif; font-size: 1.35rem; margin: .2rem 0 .65rem; }
.play-v3 .capa-buzon [data-buzon-list] { display: flex; flex-direction: column; gap: .85rem; padding-top: .25rem; }
.play-v3 .capa-buzon .carta-msg {
  background: #fffdf8; border: 0 !important; box-shadow: 0 1px 0 rgba(44,38,31,.08);
  padding: .65rem .5rem !important; border-radius: 0 !important;
}
.play-v3 .capa-buzon .carta-msg.importante {
  background: linear-gradient(135deg, #fff5f8, #fffdf8);
  box-shadow: inset 3px 0 0 #c42b4a, 0 1px 0 rgba(44,38,31,.06);
}
.play-v3 .capa-buzon .carta-msg + .carta-msg { border-top: none; }
`;

if (!shellCss.includes('obj-vecinos-resumen')) {
  write('assets/css/play-v3-shell-ui.css', shellCss + EXTRA_SHELL);
}

// === play-v3.css popover ===
let pv3 = read('assets/css/play-v3.css');
if (!pv3.includes('popover-mapa')) {
  pv3 = pv3.replace(
    `.selector { right: 3%; top: 12%; }
.quien { right: 3%; top: 10%; max-height: 68%; overflow: auto; }`,
    `.selector { display: none !important; }
.quien {
  position: absolute; left: 0; top: 0; right: auto;
  max-width: min(240px, 46%); max-height: 55%; overflow: auto;
  z-index: 45; box-shadow: 4px 6px 0 rgba(44,38,31,.12);
}
.play-root[data-consulta="quien"] .quien { display: block; }`
  );
  write('assets/css/play-v3.css', pv3);
}

// === Test hora pasada ===
const testPath = 'tests/hora_pasada_test.php';
if (!fs.existsSync(path.join(ROOT, testPath))) {
  write(testPath, `<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/autoload.php';
use AquiHayTema\\Engine\\PartidaService;
use AquiHayTema\\Engine\\PropuestaEncuentroEngine;
use AquiHayTema\\Engine\\GameError;

$root = dirname(__DIR__);
$svc = new PartidaService($root);
$partida = $svc->nuevaPartida('playtest_01', 'hora-pasada-test');
$failures = 0;
function ok(bool $c, string $m): void { global $failures; echo ($c ? 'OK' : 'FAIL') . ": $m\\n"; if (!$c) $failures++; }

$partida['reloj']['dia_pueblo'] = 23;
$partida['reloj']['hora_actual'] = 14;
$ids = [];
foreach ($partida['residentes'] ?? [] as $id => $r) {
    if (($r['presencia'] ?? '') === 'residente') $ids[] = $id;
}
$ids = array_slice($ids, 0, 2);
ok(count($ids) >= 2, 'al menos 2 residentes');
$lug = $partida['celeste']['lugares_desbloqueados'][0] ?? 'lug_cafeteria';
$rPast = PropuestaEncuentroEngine::proponer($partida, $ids, 23, 10, 'conocerse', $lug);
ok(($rPast['error'] ?? '') === GameError::HORA_PASADA, 'dia 23 10:00 rechazado');
$rOk = PropuestaEncuentroEngine::proponer($partida, $ids, 23, 16, 'conocerse', $lug);
ok($rOk['ok'] ?? false, 'dia 23 16:00 procesado');
exit($failures ? 1 : 0);
`);
}

console.log('patch_coherencia ok');
