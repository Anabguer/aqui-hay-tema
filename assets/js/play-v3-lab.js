(function () {
  'use strict';
  var state = { lab: null, vecino: null, parA: '', parB: '', periodo: null, busy: false, filtroCrono: 'TODOS' };

  function bridge() { return window.AHT_PLAY || null; }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

  async function api(action, body) {
    var b = bridge();
    if (!b || !b.api) return { ok: false, error: 'no_bridge' };
    return b.api(action, body || {});
  }

  function card(num, lbl) {
    return '<div class="lab-card"><div class="lab-num">' + esc(num) + '</div><div class="lab-lbl">' + esc(lbl) + '</div></div>';
  }

  function pintarResumen(lab) {
    var el = $('[data-lab-resumen]');
    if (!el || !lab) return;
    var r = lab.reloj || {};
    var p = lab.poblacion || {};
    var rel = lab.relaciones || {};
    var nar = lab.narrativa || {};
    var html = '';
    html += '<div class="lab-sim-bar"><span>Simular periodo</span>';
    [1, 3, 7, 30].forEach(function (d) {
      html += '<button type="button" data-lab-sim="' + d + '">+' + d + ' d\u00eda' + (d > 1 ? 's' : '') + '</button>';
    });
    html += '<span class="lab-sim-status" data-lab-sim-status></span></div>';
    html += '<div class="lab-cards">';
    html += card(r.dia_pueblo, 'D\u00eda');
    html += card(p.residentes, 'Residentes');
    html += card(p.viviendas_ocupadas + '/' + (p.viviendas_ocupadas + p.viviendas_libres), 'Viviendas');
    html += card((lab.parejas || []).length, 'Parejas');
    html += card(rel.pares_conocidos, 'Pares conocidos');
    html += card(rel.unilaterales, 'Inter\u00e9s unilateral');
    html += card(rel.reciprocos, 'Inter\u00e9s rec\u00edproco');
    html += card(nar.cotilleos_buzon, 'Cotilleos');
    html += card(nar.mensajitos_pendientes_decision, 'Mensajitos decisi\u00f3n');
    html += card((lab.drama || {}).crisis_activas || 0, 'Crisis');
    html += card((lab.marchas || {}).activas || 0, 'Marchas');
    html += '</div>';
    html += '<div class="lab-periodo" data-lab-periodo-box hidden><h3>Resumen del periodo</h3><div data-lab-periodo-content></div><div class="lab-export-bar"><button type="button" data-lab-copy-periodo>Copiar resumen</button><button type="button" data-lab-export-periodo>Exportar JSON periodo</button></div></div>';
    html += '<p style="font-size:.75rem;color:#8a7a66;margin:0">' + esc(r.texto || '') + ' \u00b7 ' + esc(r.fecha_corta || '') + '</p>';
    el.innerHTML = html;
    bindSimButtons();
    bindExportButtons();
  }

  function pintarPeriodo(data) {
    state.periodo = data;
    var box = $('[data-lab-periodo-box]');
    var content = $('[data-lab-periodo-content]');
    if (!box || !content || !data) return;
    box.hidden = false;
    var sim = data.simulacion || {};
    var per = data.periodo || {};
    var html = '<p><strong>D\u00eda ' + sim.dia_inicio + ' \u2192 ' + sim.dia_fin + '</strong> (' + sim.dias + ' d\u00edas, ' + sim.elapsed_ms + ' ms)</p><ul>';
    var act = per.actividad_periodo || {};
    Object.keys(act).forEach(function (k) { html += '<li>' + esc(k) + ': ' + esc(act[k]) + '</li>'; });
    html += '</ul><h3 style="margin-top:.5rem">Cambios importantes</h3><ul>';
    (per.cambios_importantes || []).slice(0, 25).forEach(function (c) {
      html += '<li>' + esc(c.texto || '') + '</li>';
    });
    html += '</ul>';
    content.innerHTML = html;
    pintarCronologia(per.cronologia || []);
  }

  function pintarVecinos(list) {
    var el = $('[data-lab-vecinos-chips]');
    if (!el) return;
    el.innerHTML = (list || []).map(function (v) {
      var img = v.retrato_url ? '<img src="' + esc(v.retrato_url) + '" alt="">' : '';
      return '<button type="button" class="lab-vecino-chip' + (state.vecino === v.id ? ' is-on' : '') + '" data-lab-vecino="' + esc(v.id) + '">' + img + esc(v.nombre) + '</button>';
    }).join('');
    $$('[data-lab-vecino]', el).forEach(function (btn) {
      btn.addEventListener('click', function () { cargarVecino(btn.getAttribute('data-lab-vecino')); });
    });
    var sel = $('[data-lab-vecino-select]');
    if (sel) {
      sel.innerHTML = '<option value="">— Elegir vecino —</option>' + (list || []).map(function (v) {
        return '<option value="' + esc(v.id) + '">' + esc(v.nombre) + '</option>';
      }).join('');
    }
    ['[data-lab-par-a]', '[data-lab-par-b]'].forEach(function (q) {
      var s = $(q);
      if (!s) return;
      var cur = s.value;
      s.innerHTML = '<option value="">—</option>' + (list || []).map(function (v) {
        return '<option value="' + esc(v.id) + '">' + esc(v.nombre) + '</option>';
      }).join('');
      if (cur) s.value = cur;
    });
  }

  function pintarVecinoDetalle(d) {
    var el = $('[data-lab-vecino-detalle]');
    if (!el || !d || !d.ok) { if (el) el.textContent = d && d.error ? d.error : ''; return; }
    var m = d.motor || {};
    var j = d.jugador || {};
    var id = m.identidad || {};
    var html = '<div class="lab-grid-2">';
    html += '<div class="lab-block motor"><h4>Motor</h4>';
    html += '<p><strong>' + esc(d.nombre) + '</strong> \u00b7 edad ' + esc(id.edad) + ' \u00b7 ' + esc(id.presencia) + '</p>';
    html += '<p>Vivienda: ' + esc(id.vivienda_id) + '</p>';
    html += '<p>Emoci\u00f3n: ' + esc((id.estado_emocional || {}).id || '—') + '</p>';
    html += '<p>Conocidos: ' + ((m.vida_social || {}).conocidos || []).length + '</p>';
    html += '<p>Intereses romanticos: ' + ((((m.vida_social || {}).intereses_romanticos || []).map(function (x) { return x.nombre + ' (' + x.valor + ')'; }).join(', ')) || '—') + '</p>';
    html += '</div>';
    html += '<div class="lab-block jugador"><h4>Jugador (descubierto)</h4>';
    var fj = j.ficha || {};
    html += '<p>' + esc(fj.nombre || d.nombre) + '</p>';
    html += '<p>Hobbies visibles: ' + esc((fj.hobbies || []).map(function (h) { return h.etiqueta || h; }).join(', ')) + '</p>';
    html += '<p>Descubrimientos: ' + (j.descubrimientos || []).length + '</p>';
    html += '</div></div>';
    el.innerHTML = html;
  }

  function pintarPar(d) {
    var el = $('[data-lab-par-detalle]');
    if (!el) return;
    if (!d || !d.ok) { el.textContent = d && d.error ? d.error : 'Elige dos vecinos distintos.'; return; }
    var ab = d.a_hacia_b || {};
    var ba = d.b_hacia_a || {};
    var html = '<p><strong>' + esc(d.par.a.nombre) + '</strong> + <strong>' + esc(d.par.b.nombre) + '</strong> \u00b7 pareja: ' + esc(d.estado_pareja) + ' \u00b7 se conocen: ' + (d.se_conocen ? 's\u00ed' : 'no') + '</p>';
    html += '<div class="lab-dir-pair">';
    html += dirCard(ab);
    html += dirCard(ba);
    html += '</div><h4 style="margin:.6rem 0 .3rem;font-size:.72rem;text-transform:uppercase;color:#8a7a66">Timeline del par</h4><ul class="lab-timeline">';
    (d.timeline || []).forEach(function (t) {
      html += '<li><span class="tl-dia">D\u00eda ' + esc(t.dia) + (t.hora != null ? ' \u00b7 ' + t.hora + ':00' : '') + '</span> ' + esc(t.texto) + '</li>';
    });
    html += '</ul>';
    el.innerHTML = html;
  }

  function dirCard(dir) {
    var soc = dir.social || {};
    return '<div class="lab-dir"><strong>' + esc(dir.desde_nombre) + ' \u2192 ' + esc(dir.hacia_nombre) + '</strong>' +
      'Social: ' + esc(soc.valor) + ' (' + esc(soc.banda) + ')<br>' +
      'Romance: ' + esc(dir.romance) + '<br>' +
      'Qu\u00edmica: ' + esc(dir.quimica) + '<br>' +
      'Compat. oculta: ' + esc((dir.compatibilidad_oculta || {}).valor) + '<br>' +
      'Flechazo hito: ' + (dir.tiene_flechazo_hito ? 's\u00ed' : 'no') +
      '</div>';
  }

  function pintarCronologia(items) {
    var el = $('[data-lab-cronologia]');
    if (!el) return;
    var cats = ['TODOS', 'SOCIAL', 'ROMANCE', 'DRAMA', 'ENCUENTROS', 'MARCHAS'];
    var fh = '<div class="lab-crono-filtros">';
    cats.forEach(function (c) {
      fh += '<button type="button" data-lab-filtro="' + c + '"' + (state.filtroCrono === c ? ' class="is-on"' : '') + '>' + c + '</button>';
    });
    fh += '</div><ul class="lab-timeline">';
    var list = items || [];
    if (state.filtroCrono !== 'TODOS') list = list.filter(function (x) { return x.categoria === state.filtroCrono; });
    list.slice(-60).forEach(function (t) {
      fh += '<li><span class="tl-dia">D\u00eda ' + esc(t.dia) + ' \u00b7 ' + esc(t.categoria) + '</span> ' + esc(t.texto) + '</li>';
    });
    fh += '</ul>';
    el.innerHTML = fh;
    $$('[data-lab-filtro]', el).forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.filtroCrono = btn.getAttribute('data-lab-filtro');
        pintarCronologia(items);
      });
    });
  }

  async function cargarResumen() {
    var r = await api('debug.lab.resumen', {});
    if (!r.ok) return;
    state.lab = r.lab;
    pintarResumen(r.lab);
    pintarVecinos((r.lab || {}).vecinos || []);
    var rel = $('[data-lab-reloj]');
    if (rel && r.lab && r.lab.reloj) rel.textContent = r.lab.reloj.texto || '';
  }

  async function cargarVecino(id) {
    state.vecino = id;
    pintarVecinos((state.lab || {}).vecinos || []);
    var r = await api('debug.lab.vecino', { residente_id: id });
    pintarVecinoDetalle(r);
  }

  async function cargarPar() {
    var a = ($('[data-lab-par-a]') || {}).value || state.parA;
    var b = ($('[data-lab-par-b]') || {}).value || state.parB;
    state.parA = a; state.parB = b;
    if (!a || !b || a === b) return;
    var r = await api('debug.lab.par', { a: a, b: b });
    pintarPar(r);
  }

  async function simular(dias) {
    if (state.busy) return;
    state.busy = true;
    var st = $('[data-lab-sim-status]');
    if (st) st.textContent = 'Simulando ' + dias + ' d\u00edas\u2026';
    $$('[data-lab-sim]').forEach(function (b) { b.classList.add('is-busy'); });
    var r = await api('debug.lab.simular', { dias: dias });
    state.busy = false;
    $$('[data-lab-sim]').forEach(function (b) { b.classList.remove('is-busy'); });
    if (!r.ok) {
      if (st) st.textContent = r.mensaje_ui || r.error || 'Error';
      return;
    }
    if (st) st.textContent = 'Listo (' + ((r.simulacion || {}).elapsed_ms || '?') + ' ms)';
    pintarPeriodo(r);
    state.lab = r.despues;
    pintarResumen(r.despues);
    var b = bridge();
    if (b && b.refresh) await b.refresh();
  }

  function bindSimButtons() {
    $$('[data-lab-sim]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        simular(parseInt(btn.getAttribute('data-lab-sim'), 10));
      });
    });
  }

  function bindExportButtons() {
    var cp = $('[data-lab-copy-periodo]');
    if (cp) cp.addEventListener('click', function () {
      var txt = state.periodo && state.periodo.export && state.periodo.export.texto;
      if (!txt) return;
      navigator.clipboard.writeText(txt).catch(function () {});
    });
    var ex = $('[data-lab-export-periodo]');
    if (ex) ex.addEventListener('click', function () {
      var json = state.periodo && state.periodo.export && state.periodo.export.json;
      if (!json) return;
      var blob = new Blob([JSON.stringify(json, null, 2)], { type: 'application/json' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'play-lab-periodo.json';
      a.click();
    });
  }

  function setTab(name) {
    $$('[data-lab-tab]').forEach(function (btn) {
      btn.setAttribute('aria-selected', btn.getAttribute('data-lab-tab') === name ? 'true' : 'false');
    });
    $$('[data-lab-panel]').forEach(function (p) {
      p.hidden = p.getAttribute('data-lab-panel') !== name;
    });
  }

  function openLab() {
    var ov = $('[data-play-lab]');
    if (!ov) return;
    ov.hidden = false;
    cargarResumen();
    setTab('resumen');
  }

  function closeLab() {
    var ov = $('[data-play-lab]');
    if (ov) ov.hidden = true;
  }

  function init() {
    var ov = $('[data-play-lab]');
    if (!ov) return;
    $$('[data-lab-tab]').forEach(function (btn) {
      btn.addEventListener('click', function () { setTab(btn.getAttribute('data-lab-tab')); });
    });
    var close = $('[data-lab-close]');
    if (close) close.addEventListener('click', closeLab);
    ov.addEventListener('click', function (e) { if (e.target === ov) closeLab(); });
    var btnPar = $('[data-lab-inspeccionar-par]');
    if (btnPar) btnPar.addEventListener('click', cargarPar);
    var selV = $('[data-lab-vecino-select]');
    if (selV) selV.addEventListener('change', function () { if (selV.value) cargarVecino(selV.value); });
    window.AHT_PLAY_LAB = { open: openLab, close: closeLab, reload: cargarResumen };
    document.addEventListener('aht-play-lab-open', openLab);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
