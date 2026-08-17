(function () {
  'use strict';

  const API = 'api/index.php';
  let partidaId = localStorage.getItem('aht_partida_id');
  let selectedResidente = null;

  async function api(action, body = {}, method = 'POST') {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (method !== 'GET') {
      opts.body = JSON.stringify({ partida_id: partidaId, ...body });
    }
    const url = method === 'GET'
      ? `${API}?action=${encodeURIComponent(action)}&partida_id=${encodeURIComponent(partidaId || '')}`
      : `${API}?action=${encodeURIComponent(action)}`;
    return (await fetch(url, opts)).json();
  }

  function $(sel) { return document.querySelector(sel); }
  function el(tag, cls, text) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text !== undefined) e.textContent = text;
    return e;
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

  async function loadResidentesSelects() {
    const insp = await api('partida.inspeccionar', {}, 'GET');
    if (!insp.ok) return;
    window._residentesMap = {};
    const selA = $('#enc-a');
    const selB = $('#enc-b');
    selA.innerHTML = '';
    selB.innerHTML = '';
    Object.entries(insp.partida.residentes || {}).forEach(([id, r]) => {
      window._residentesMap[id] = r.identidad_publica?.nombre || id;
      const label = `${r.identidad_publica?.nombre || id} (${id})`;
      selA.appendChild(new Option(label, id));
      selB.appendChild(new Option(label, id));
    });
  }

  async function refreshEstado() {
    const r = await api('partida.estado', {}, 'GET');
    if (!r.ok) return null;
    const e = r.estado;
    $('#status-reloj').textContent = e.reloj_texto;
    $('#status-meta').textContent =
      `${e.residentes_count} residentes · ${e.encuentros_activos ?? 0} encuentros activos · schema v${e.meta.schema_version}`;
    renderBloqueA(e.bloque_a);
    await renderMapa();
    await renderBuzon();
    if (selectedResidente) await abrirFicha(selectedResidente);
    return e;
  }

  function renderBloqueA(bloque) {
    const grid = $('#grid-bloque-a');
    grid.innerHTML = '';
    bloque.viviendas.forEach(v => {
      const slot = el('div', 'slot ' + (v.ocupante_id ? 'ocupado' : 'libre'));
      slot.appendChild(el('div', 'slot-id', v.id));
      slot.appendChild(el('div', 'slot-nombre', v.ocupante_id
        ? (window._residentesMap?.[v.ocupante_id] || v.ocupante_id) : '— libre —'));
      if (v.ocupante_id) {
        slot.addEventListener('click', () => {
          selectedResidente = v.ocupante_id;
          document.querySelectorAll('.slot.selected').forEach(s => s.classList.remove('selected'));
          slot.classList.add('selected');
          abrirFicha(v.ocupante_id);
        });
      }
      grid.appendChild(slot);
    });
  }

  async function renderMapa() {
    const r = await api('mapa.presencia', {}, 'GET');
    const panel = $('#mapa-panel');
    if (!r.ok) {
      panel.textContent = 'Error mapa';
      return;
    }
    panel.innerHTML = '';
    r.mapa.lugares.forEach(lug => {
      const row = el('div', 'campo');
      const tag = lug.operativo ? 'ABIERTO' : (lug.candado ? 'CANDADO' : 'cerrado');
      row.appendChild(el('div', 'label', `${lug.nombre} [${tag}]`));
      const pres = lug.residentes_presentes.map(p => p.iniciales).join(' ') || '—';
      row.appendChild(document.createTextNode(` ${pres}`));
      panel.appendChild(row);
    });
  }

  async function renderBuzon() {
    const r = await api('buzon.listar', {}, 'GET');
    const n = r.ok ? r.mensajes.length : 0;
    $('#buzon-panel').textContent = n
      ? `${n} mensaje(s) en buzón`
      : 'Bandeja vacía (estructura lista, sin catálogo narrativo).';
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
    panel.appendChild(el('h3', null, f.identidad.nombre));
    panel.appendChild(el('p', null, `Trabajo: ${f.trabajo?.ocupacion || '—'}`));
    const relKeys = Object.keys(f.relaciones || {});
    panel.appendChild(el('p', null, `Relaciones: ${relKeys.length || 'ninguna'}`));
    if (f.ultimo_encuentro?.resultado) {
      const res = f.ultimo_encuentro.resultado;
      panel.appendChild(el('p', 'badge-provisional', 'Último encuentro (PLACEHOLDER)'));
      panel.appendChild(el('p', null, res.texto_resumen || JSON.stringify(res.delta_social || {})));
    }
  }

  async function init() {
    const horaSel = $('#enc-hora');
    for (let h = 8; h <= 22; h++) {
      horaSel.appendChild(new Option(`${String(h).padStart(2, '0')}:00`, String(h)));
    }
    horaSel.value = '19';

    await ensurePartida();
    await loadResidentesSelects();
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
        ? `OK: ${r.encuentro.id} (${r.encuentro.tipo}) — avanza el reloj para resolver`
        : `Error: ${r.error}${r.residente ? ' (' + r.residente + ')' : ''}`;
      await refreshEstado();
    });

    $('#btn-avanzar-1h').addEventListener('click', async () => {
      await api('reloj.avanzar', { horas: 1 });
      await refreshEstado();
    });

    $('#btn-guardar').addEventListener('click', () => api('partida.guardar', {}));
    $('#btn-nueva').addEventListener('click', async () => {
      if (!confirm('¿Nueva partida (nuevo id)?')) return;
      localStorage.removeItem('aht_partida_id');
      partidaId = null;
      await ensurePartida();
      await loadResidentesSelects();
      await refreshEstado();
    });
  }

  document.addEventListener('DOMContentLoaded', init);
})();
