(function () {
  'use strict';

  const API = 'api/index.php';
  let partidaId = localStorage.getItem('aht_partida_id');
  let selectedResidente = null;
  let cacheInspeccion = null;

  async function api(action, body = {}, method = 'POST') {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (method !== 'GET') {
      opts.body = JSON.stringify({ partida_id: partidaId, ...body });
    }
    const qs = new URLSearchParams({ action, partida_id: partidaId || '', ...body });
    const url = method === 'GET' ? `${API}?${qs.toString()}` : `${API}?action=${encodeURIComponent(action)}`;
    return (await fetch(url, opts)).json();
  }

  function $(sel) { return document.querySelector(sel); }
  function el(tag, cls, text) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined) e.textContent = text;
    return e;
  }

  function emptyState(panel, text) {
    panel.innerHTML = '';
    panel.appendChild(el('p', 'empty', text));
  }

  function nombreResidente(id) {
    return cacheInspeccion?.residentes?.[id]?.identidad_publica?.nombre || id;
  }

  function formatHora(dia, hora) {
    return `D${dia} · ${String(hora).padStart(2, '0')}:00`;
  }

  async function ensurePartida() {
    if (partidaId) {
      const r = await api('partida.cargar', { partida_id: partidaId });
      if (r.ok) return r;
    }
    const r = await api('partida.nueva', {});
    if (r.ok) {
      partidaId = r.partida_id;
      localStorage.setItem('aht_partida_id', partidaId);
    }
    return r;
  }

  async function cargarInspeccion() {
    const r = await api('partida.inspeccionar', {}, 'GET');
    if (!r.ok) return null;
    cacheInspeccion = r.partida;
    return cacheInspeccion;
  }

  function renderResidentesList(residentes) {
    const panel = $('#residentes-panel');
    panel.innerHTML = '';
    const entries = Object.entries(residentes || {});
    if (!entries.length) {
      return emptyState(panel, 'No hay residentes.');
    }
    entries.forEach(([id, r]) => {
      const card = el('button', 'resident-card' + (selectedResidente === id ? ' active' : ''), undefined);
      card.type = 'button';
      card.appendChild(el('div', null, r.identidad_publica?.nombre || id));
      card.appendChild(el('div', 'resident-meta', `${id} · ${r.vivienda_id || 'sin vivienda'} · ${r.runtime?.ocupacion || '—'}`));
      card.addEventListener('click', () => seleccionarResidente(id));
      panel.appendChild(card);
    });
  }

  function renderBloqueA(bloque) {
    const grid = $('#grid-bloque-a');
    grid.innerHTML = '';
    (bloque?.viviendas || []).forEach(v => {
      const slot = el('div', 'slot ' + (v.ocupante_id ? 'ocupado' : 'libre'));
      slot.appendChild(el('div', 'slot-id', v.id));
      slot.appendChild(el('div', 'slot-nombre', v.ocupante_id ? nombreResidente(v.ocupante_id) : '— libre —'));
      if (v.ocupante_id) {
        if (selectedResidente === v.ocupante_id) slot.classList.add('selected');
        slot.addEventListener('click', () => seleccionarResidente(v.ocupante_id));
      }
      grid.appendChild(slot);
    });
  }

  async function loadResidentesSelects() {
    const insp = cacheInspeccion || await cargarInspeccion();
    if (!insp) return;
    const selA = $('#enc-a');
    const selB = $('#enc-b');
    selA.innerHTML = '';
    selB.innerHTML = '';
    Object.entries(insp.residentes || {}).forEach(([id, r]) => {
      const label = `${r.identidad_publica?.nombre || id} (${id})`;
      selA.appendChild(new Option(label, id));
      selB.appendChild(new Option(label, id));
    });
    if (selectedResidente && insp.residentes?.[selectedResidente]) {
      selA.value = selectedResidente;
    }
  }

  function renderSummary(e, buzonCount) {
    $('#status-reloj').textContent = e.reloj_texto;
    $('#status-meta').textContent =
      `${e.residentes_count} residentes · ${e.encuentros_activos ?? 0} encuentros activos · schema v${e.meta.schema_version}`;
    $('#sum-reloj').textContent = e.reloj_texto;
    $('#sum-residentes').textContent = String(e.residentes_count || 0);
    $('#sum-encuentros').textContent = String(e.encuentros_activos || 0);
    $('#sum-buzon').textContent = String(buzonCount || 0);
  }

  async function renderMapa() {
    const r = await api('mapa.presencia', {}, 'GET');
    const panel = $('#mapa-panel');
    if (!r.ok) {
      panel.textContent = 'Error mapa';
      return;
    }
    panel.innerHTML = '';
    (r.mapa?.lugares || []).forEach(lug => {
      const row = el('div', 'lugar-card');
      const tag = lug.operativo ? 'abierto' : (lug.candado ? 'candado' : 'cerrado');
      row.appendChild(el('div', null, `${lug.nombre} · ${tag}`));
      const pres = (lug.residentes_presentes || []).map(p => nombreResidente(p.id)).join(', ') || 'Sin residentes';
      row.appendChild(el('div', 'mini-meta', pres));
      panel.appendChild(row);
    });
  }

  async function renderEncuentros() {
    const r = await api('encuentro.listar', {}, 'GET');
    const activos = $('#encuentros-activos');
    const hist = $('#encuentros-historial');
    activos.innerHTML = '';
    hist.innerHTML = '';
    if (!r.ok) {
      activos.textContent = 'Error encuentros';
      return;
    }
    const list = r.encuentros || [];
    const abiertos = list.filter(e => e.estado === 'programado' || e.estado === 'en_curso');
    const cerrados = list.filter(e => e.estado === 'terminado' || e.estado === 'cancelado').slice(-8).reverse();

    const renderList = (target, items, empty) => {
      if (!items.length) return emptyState(target, empty);
      items.forEach(enc => {
        const row = el('div', 'enc-row');
        row.appendChild(el('div', null, `${enc.tipo} · ${formatHora(enc.dia, enc.hora)}`));
        row.appendChild(el('div', 'mini-meta', `${(enc.participantes || []).map(nombreResidente).join(' · ')} · ${enc.estado}`));
        if (enc.resultado?.texto_resumen) {
          row.appendChild(el('div', 'mini-meta', enc.resultado.texto_resumen));
        }
        target.appendChild(row);
      });
    };

    renderList(activos, abiertos, 'No hay encuentros activos.');
    renderList(hist, cerrados, 'Sin encuentros finalizados todavía.');
  }

  async function renderRelaciones(residenteId) {
    const panel = $('#relaciones-panel');
    if (!residenteId) return emptyState(panel, 'Selecciona un residente.');
    const r = await api('residente.ficha', { residente_id: residenteId }, 'GET');
    if (!r.ok) return emptyState(panel, 'Error relaciones.');
    const rels = Object.entries(r.ficha?.relaciones || {});
    panel.innerHTML = '';
    if (!rels.length) return emptyState(panel, 'Sin relaciones visibles todavía.');
    rels.forEach(([id, rel]) => {
      const row = el('div', 'rel-row');
      row.appendChild(el('div', null, nombreResidente(id)));
      row.appendChild(el('div', 'mini-meta', `Social: ${rel.social?.tipo || '—'} · Romance: ${rel.romance ? 'sí' : '—'}`));
      panel.appendChild(row);
    });
  }

  async function renderBuzon() {
    const r = await api('buzon.listar', {}, 'GET');
    const panel = $('#buzon-panel');
    panel.innerHTML = '';
    if (!r.ok) {
      panel.textContent = 'Error buzón';
      return 0;
    }
    const mensajes = r.mensajes || [];
    if (!mensajes.length) {
      emptyState(panel, 'Bandeja vacía.');
      return 0;
    }
    mensajes.slice(-6).reverse().forEach(m => {
      const row = el('div', 'msg-row');
      row.appendChild(el('div', null, `${nombreResidente(m.de_persona || 'sistema')} · ${m.tipo || 'mensaje'}`));
      row.appendChild(el('div', 'mini-meta', `D${m.dia || '—'} · ${m.estado || 'pendiente'}`));
      row.appendChild(el('div', null, m.texto || '(sin texto)'));
      panel.appendChild(row);
    });
    return mensajes.length;
  }

  async function renderDiario() {
    const r = await api('diario.listar', {}, 'GET');
    const panel = $('#diario-panel');
    panel.innerHTML = '';
    if (!r.ok) {
      panel.textContent = 'Error diario';
      return;
    }
    const entradas = r.entradas || [];
    if (!entradas.length) return emptyState(panel, 'Sin entradas hoy.');
    entradas.slice(-6).reverse().forEach(d => {
      const row = el('div', 'dia-row');
      row.appendChild(el('div', null, d.tipo || 'entrada'));
      row.appendChild(el('div', 'mini-meta', `D${d.dia || '—'}`));
      row.appendChild(el('div', null, d.texto || '(sin texto)'));
      panel.appendChild(row);
    });
  }

  function renderPortrait(target, visual, nombre) {
    const frame = el('div', 'portrait-frame');
    const asset = visual?.asset;
    if (asset?.url_relativa && asset?.existe) {
      const img = document.createElement('img');
      img.src = asset.url_relativa;
      img.alt = `${nombre} — ${visual.expression_id || 'neutral'}`;
      frame.appendChild(img);
    } else {
      frame.appendChild(el('div', 'portrait-placeholder', 'Retrato pendiente\npack / neutral'));
    }
    target.appendChild(frame);
  }

  async function abrirFicha(residenteId) {
    const r = await api('residente.ficha', { residente_id: residenteId }, 'GET');
    const panel = $('#ficha-panel');
    if (!r.ok) {
      panel.innerHTML = '<p class="error">Error ficha</p>';
      return;
    }
    const f = r.ficha;
    panel.innerHTML = '';

    const hero = el('div', 'ficha-hero');
    renderPortrait(hero, f.presentacion_visual, f.identidad.nombre);
    const meta = el('div');
    meta.appendChild(el('h3', null, f.identidad.nombre));
    meta.appendChild(el('div', 'mini-meta', `${f.id} · ${f.vivienda_id || 'sin vivienda'} · ${f.placeholder ? 'placeholder' : 'catálogo'}`));
    meta.appendChild(el('div', null, `Trabajo: ${f.trabajo?.ocupacion || '—'}`));
    meta.appendChild(el('div', null, `Edad: ${f.identidad?.edad ?? '—'}`));
    hero.appendChild(meta);
    panel.appendChild(hero);

    const pills = el('div', 'pill-row');
    pills.appendChild(el('span', 'pill', `Estado: ${f.estado_emocional?.id || 'neutro'}`));
    pills.appendChild(el('span', 'pill', `Expresión: ${f.presentacion_visual?.expression_id || 'neutral'}`));
    pills.appendChild(el('span', 'pill', `Fallback: ${f.presentacion_visual?.fallback ? 'sí' : 'no'}`));
    panel.appendChild(pills);

    const hobbies = (f.hobbies?.conocidos || []).length ? f.hobbies.conocidos.join(', ') : 'Sin hobbies visibles todavía';
    panel.appendChild(el('p', null, `Hobbies visibles: ${hobbies}`));
    panel.appendChild(el('p', 'mini-meta', `Descubrimientos: ${(f.descubrimientos || []).length} · Relaciones: ${Object.keys(f.relaciones || {}).length}`));

    if (f.ultimo_encuentro?.resultado) {
      const row = el('div', 'enc-row');
      row.appendChild(el('div', 'label', 'Último encuentro'));
      row.appendChild(el('div', null, f.ultimo_encuentro.resultado.texto_resumen || JSON.stringify(f.ultimo_encuentro.resultado.delta_social || {})));
      panel.appendChild(row);
    }

    const agenda = el('div', 'agenda-mini');
    const slots = (f.agenda_hoy?.slots || []).filter(s => s.hora >= 8 && s.hora <= 22);
    slots.forEach(s => {
      agenda.appendChild(el('div', s.ocupado ? 'ocupado' : 'libre', `${String(s.hora).padStart(2, '0')}:00 · ${s.ocupado ? (s.tipo || s.detalle || 'ocupado') : 'libre'}`));
    });
    panel.appendChild(el('div', 'label', 'Agenda hoy'));
    panel.appendChild(agenda);
  }

  async function seleccionarResidente(id) {
    selectedResidente = id;
    renderResidentesList(cacheInspeccion?.residentes || {});
    if ($('#enc-a')) $('#enc-a').value = id;
    await abrirFicha(id);
    await renderRelaciones(id);
  }

  async function refreshEstado() {
    const [estadoResp, insp] = await Promise.all([
      api('partida.estado', {}, 'GET'),
      cargarInspeccion(),
    ]);
    if (!estadoResp.ok || !insp) return null;
    const e = estadoResp.estado;
    const buzonCount = await renderBuzon();
    renderSummary(e, buzonCount);
    renderResidentesList(insp.residentes || {});
    renderBloqueA(e.bloque_a);
    await Promise.all([
      loadResidentesSelects(),
      renderMapa(),
      renderEncuentros(),
      renderDiario(),
    ]);
    if (!selectedResidente) {
      const first = Object.keys(insp.residentes || {})[0] || null;
      if (first) selectedResidente = first;
    }
    if (selectedResidente) {
      await abrirFicha(selectedResidente);
      await renderRelaciones(selectedResidente);
    }
    return e;
  }

  async function init() {
    const horaSel = $('#enc-hora');
    for (let h = 8; h <= 22; h++) {
      horaSel.appendChild(new Option(`${String(h).padStart(2, '0')}:00`, String(h)));
    }
    horaSel.value = '19';

    await ensurePartida();
    await refreshEstado();

    $('#btn-programar').addEventListener('click', async () => {
      const fb = $('#enc-feedback');
      fb.textContent = 'Programando…';
      const r = await api('encuentro.programar', {
        participantes: [$('#enc-a').value, $('#enc-b').value],
        tipo: $('#enc-tipo').value,
        hora: parseInt($('#enc-hora').value, 10),
        lugar: 'lug_cafeteria',
      });
      fb.textContent = r.ok
        ? `Encuentro programado para ${formatHora(r.encuentro.dia, r.encuentro.hora)}`
        : `${r.mensaje_ui || r.error}${r.residente ? ' · ' + nombreResidente(r.residente) : ''}`;
      await refreshEstado();
    });

    $('#btn-avanzar-1h').addEventListener('click', async () => {
      const r = await api('reloj.avanzar', { horas: 1 });
      $('#enc-feedback').textContent = r.ok ? 'Tiempo avanzado 1h.' : 'No se pudo avanzar el reloj.';
      await refreshEstado();
    });

    $('#btn-guardar').addEventListener('click', async () => {
      const r = await api('partida.guardar', {});
      $('#enc-feedback').textContent = r.ok ? 'Partida guardada.' : 'Error al guardar.';
    });

    $('#btn-nueva').addEventListener('click', async () => {
      if (!confirm('¿Nueva partida (nuevo id)?')) return;
      localStorage.removeItem('aht_partida_id');
      partidaId = null;
      selectedResidente = null;
      cacheInspeccion = null;
      await ensurePartida();
      await refreshEstado();
      $('#enc-feedback').textContent = 'Nueva partida creada.';
    });
  }

  document.addEventListener('DOMContentLoaded', init);
})();
