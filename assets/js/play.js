(function () {
  'use strict';

  const API = 'api/index.php';
  let partidaId = localStorage.getItem('aht_partida_id');

  async function api(action, body = {}, method = 'POST') {
    const opts = {
      method,
      headers: { 'Content-Type': 'application/json' },
    };
    if (method !== 'GET') {
      opts.body = JSON.stringify({ partida_id: partidaId, ...body });
    }
    const url = method === 'GET'
      ? `${API}?action=${encodeURIComponent(action)}&partida_id=${encodeURIComponent(partidaId || '')}`
      : `${API}?action=${encodeURIComponent(action)}`;
    const res = await fetch(url, opts);
    return res.json();
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

  async function refreshEstado() {
    const r = await api('partida.estado', {}, 'GET');
    if (!r.ok) {
      $('#status-reloj').textContent = 'Error: ' + (r.error || '?');
      return null;
    }
    const e = r.estado;
    $('#status-reloj').textContent = e.reloj_texto;
    $('#status-meta').textContent = `Partida ${e.meta.partida_id.slice(0, 12)}… · ${e.residentes_count} residentes · ${e.citas_activas} citas activas`;
    renderBloqueA(e.bloque_a);
    return e;
  }

  function renderBloqueA(bloque) {
    const grid = $('#grid-bloque-a');
    grid.innerHTML = '';
    bloque.viviendas.forEach(v => {
      const slot = el('div', 'slot ' + (v.ocupante_id ? 'ocupado' : 'libre'));
      slot.dataset.vivienda = v.id;
      slot.appendChild(el('div', 'slot-id', v.id));
      const nombre = v.ocupante_id
        ? (window._residentesMap?.[v.ocupante_id] || v.ocupante_id)
        : '— libre —';
      slot.appendChild(el('div', 'slot-nombre', nombre));
      if (v.ocupante_id) {
        slot.addEventListener('click', () => abrirFicha(v.ocupante_id, slot));
      }
      grid.appendChild(slot);
    });
  }

  async function abrirFicha(residenteId, slotEl) {
    document.querySelectorAll('.slot.selected').forEach(s => s.classList.remove('selected'));
    if (slotEl) slotEl.classList.add('selected');

    const r = await api('residente.ficha', { residente_id: residenteId }, 'GET');
    const panel = $('#ficha-panel');
    if (!r.ok) {
      panel.innerHTML = '<p class="error">No se pudo cargar ficha</p>';
      return;
    }
    const f = r.ficha;
    panel.innerHTML = '';

    panel.appendChild(el('h2', null, f.identidad.nombre));
    if (f.placeholder) {
      panel.appendChild(el('p', 'badge-provisional', 'PLACEHOLDER DEV'));
    }

    const campos = [
      ['Vivienda', f.vivienda_id || '—'],
      ['Slot catálogo', f.identidad.slot_catalogo || '—'],
      ['Trabajo', f.trabajo.ocupacion || '—'],
      ['Presencia', f.presencia],
    ];
    campos.forEach(([label, val]) => {
      const c = el('div', 'campo');
      c.appendChild(el('div', 'label', label));
      c.appendChild(document.createTextNode(String(val)));
      panel.appendChild(c);
    });

    const hobbies = el('div', 'campo');
    hobbies.appendChild(el('div', 'label', 'Hobbies conocidos'));
    hobbies.appendChild(document.createTextNode((f.hobbies.conocidos || []).join(', ') || '—'));
    panel.appendChild(hobbies);

    const rel = el('div', 'campo');
    rel.appendChild(el('div', 'label', 'Relaciones'));
    const relKeys = Object.keys(f.relaciones || {});
    rel.appendChild(document.createTextNode(relKeys.length ? relKeys.join(', ') : 'Ninguna registrada'));
    panel.appendChild(rel);

    const ag = el('div', 'campo agenda-mini');
    ag.appendChild(el('div', 'label', 'Agenda hoy (24h)'));
    (f.agenda_hoy?.slots || []).forEach(s => {
      const line = el('div', s.ocupado ? 'ocupado' : 'libre');
      line.textContent = `${String(s.hora).padStart(2, '0')}:00 ${s.ocupado ? `[${s.capa}/${s.tipo}]` : 'libre'}`;
      ag.appendChild(line);
    });
    panel.appendChild(ag);
  }

  async function loadResidentesMap(estado) {
    window._residentesMap = {};
    const insp = await api('partida.inspeccionar', {}, 'GET');
    if (insp.ok) {
      Object.entries(insp.partida.residentes || {}).forEach(([id, r]) => {
        window._residentesMap[id] = r.identidad_publica?.nombre || id;
      });
    }
  }

  async function init() {
    await ensurePartida();
    const e = await refreshEstado();
    if (e) await loadResidentesMap(e);
    await refreshEstado();

    $('#btn-guardar').addEventListener('click', async () => {
      await api('partida.guardar', {});
      $('#status-meta').textContent += ' · guardado';
    });

    $('#btn-nueva').addEventListener('click', async () => {
      if (!confirm('¿Crear partida nueva? (La actual queda en disco)')) return;
      localStorage.removeItem('aht_partida_id');
      partidaId = null;
      await ensurePartida();
      await refreshEstado();
    });
  }

  document.addEventListener('DOMContentLoaded', init);
})();
