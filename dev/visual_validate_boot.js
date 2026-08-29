/**
 * DEV — intercepta partida.refresh para el arnés visual (sin API HTTP).
 */
(function () {
  var el = document.getElementById('aht-refresh-payload');
  if (!el) return;
  var payload;
  try {
    payload = JSON.parse(el.textContent || '{}');
  } catch (e) {
    return;
  }
  payload.ok = true;
  var partidaId = document.body.getAttribute('data-partida-id') || '';
  var capa = document.body.getAttribute('data-capa-target') || '';
  var visualCapa = document.body.getAttribute('data-visual-capa') || '';
  var fichaId = document.body.getAttribute('data-ficha-id') || '';
  var notasLugar = document.body.getAttribute('data-notas-lugar') || 'parque';

  try {
    localStorage.setItem('aht_partida_id_juego', partidaId);
  } catch (e) {}

  var origFetch = window.fetch;
  window.fetch = function (url, opts) {
    var u = String(url || '');
    if (u.indexOf('partida.refresh') !== -1) {
      return Promise.resolve(new Response(JSON.stringify(payload), {
        status: 200,
        headers: { 'Content-Type': 'application/json' }
      }));
    }
    if (u.indexOf('partida.nueva') !== -1 || u.indexOf('partida.adopt') !== -1) {
      return Promise.resolve(new Response(JSON.stringify({ ok: false }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' }
      }));
    }
    if (u.indexOf('residente.ficha') !== -1 || u.indexOf('residentes.ficha') !== -1) {
      var res = (payload.partida && payload.partida.residentes && payload.partida.residentes[fichaId]) || null;
      var vista = res ? Object.assign({}, res) : null;
      if (vista && window.__VIS_ANIMO_FIXTURE__) {
        vista.estado_animo = vista.estado_animo || 'enfadado';
        vista.animo_explicacion = window.__VIS_ANIMO_FIXTURE__;
      }
      return Promise.resolve(new Response(JSON.stringify({
        ok: !!vista,
        ficha: vista ? { vista_play: vista, catalog_id: vista.catalog_id || fichaId } : null
      }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' }
      }));
    }
    if (u.indexOf('residentes.diario') !== -1 || u.indexOf('residente.diario') !== -1) {
      var entradas = Array.isArray(payload.diario_fixture) ? payload.diario_fixture : [];
      return Promise.resolve(new Response(JSON.stringify({ ok: true, entradas: entradas }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' }
      }));
    }
    return origFetch.apply(this, arguments);
  };

  function abrirNotasMapa() {
    function tryOpen() {
      var want = (notasLugar || 'parque').toLowerCase();
      var btn = Array.prototype.find.call(document.querySelectorAll('button'), function (b) {
        var t = (b.textContent || '').trim().toLowerCase();
        var a = (b.getAttribute('aria-label') || '').trim().toLowerCase();
        return t === want || a === want;
      });
      if (btn) {
        btn.click();
        return true;
      }
      var hit = document.querySelector('.mapa-zona-hit[data-zona="' + want + '"]')
        || document.querySelector('.mapa-zona-hit');
      if (typeof abrirConsultaZona === 'function' && hit) {
        var zid = hit.getAttribute('data-zona') || hit.getAttribute('data-zona-id') || want;
        abrirConsultaZona(zid, hit, true);
        return true;
      }
      if (typeof abrirConsulta === 'function') {
        abrirConsulta(want, true);
        return true;
      }
      return false;
    }
    [1500, 3000, 4500, 6000].forEach(function (ms) { setTimeout(tryOpen, ms); });
  }

  function abrirIntervencionPanel() {
    var card = document.querySelector('[data-enc-mov-card]');
    if (!card) return;
    card.scrollIntoView({ block: 'center', behavior: 'instant' });
    var toggle = card.querySelector('[data-enc-mov-toggle]');
    var panel = card.querySelector('[data-enc-mov-panel]');
    if (toggle && panel && panel.hidden) toggle.click();
  }

  function abrirAnimoHarness() {
    if (typeof abrirFicha !== 'function' || !fichaId) return;
    abrirFicha(fichaId);
    function forceAnimoModal() {
      var overlay = document.querySelector('[data-animo-overlay]');
      var body = document.querySelector('[data-animo-body]');
      var fix = window.__VIS_ANIMO_FIXTURE__;
      if (!overlay || !body || !fix) return false;
      var nom = (document.querySelector('[data-ficha-nombre]') || {}).textContent || 'Vecino';
      body.innerHTML =
        '<div class="animo-modal-top">' +
          '<div class="animo-modal-avatar"></div>' +
          '<h3 class="animo-modal-tit">¿Qué le pasa a ' + nom + '?</h3>' +
        '</div>' +
        '<div class="animo-modal-pills">' +
          '<span class="animo-modal-estado animo-modal-estado--enfadado">ENFADADO</span>' +
          '<span class="animo-modal-desde">Desde hoy</span>' +
        '</div>' +
        '<span class="animo-modal-ribbon animo-modal-ribbon--lav">¿Qué ha pasado?</span>' +
        '<div class="animo-modal-causa animo-modal-causa--illus"><p>' + (fix.explicacion || '') + '</p></div>' +
        '<button type="button" class="animo-modal-cta" data-animo-org>Organizar un plan</button>' +
        '<button type="button" class="animo-modal-ghost" data-animo-diario>Ver en su diario</button>';
      var img = document.querySelector('[data-ficha-img]');
      var av = body.querySelector('.animo-modal-avatar');
      if (img && av) av.innerHTML = img.innerHTML;
      overlay.hidden = false;
      if (typeof setCapa === 'function') setCapa('ficha');
      return true;
    }
    function tryAnimo() {
      var q = document.querySelector('[data-ficha-animo-q]');
      var pill = document.querySelector('[data-ficha-animo-pill]');
      if (q && !q.hidden) { q.click(); return true; }
      if (pill && pill.classList.contains('is-clickable')) { pill.click(); return true; }
      return forceAnimoModal();
    }
    [1400, 2400, 3400, 4400].forEach(function (ms) { setTimeout(tryAnimo, ms); });
  }

  function abrirTutorialHarness() {
    var step = parseInt(document.body.getAttribute('data-tut-step') || '1', 10);
    if (typeof mostrarTutorial === 'function') {
      mostrarTutorial(step);
      return;
    }
    var intro = document.querySelector('.tut-intro, [data-tut-intro], aside[data-tut-finale]');
    if (intro) intro.hidden = false;
  }

  document.addEventListener('DOMContentLoaded', function () {
    function postBoot() {
      if (capa && typeof setCapa === 'function') setCapa(capa);
      if (visualCapa === 'ficha_animo' || capa === 'ficha') {
        if (visualCapa === 'ficha_animo') abrirAnimoHarness();
        else if (fichaId && typeof abrirFicha === 'function') abrirFicha(fichaId);
      }
      if (capa === 'ficha_diario' && fichaId && typeof abrirDiarioVecino === 'function') {
        abrirDiarioVecino(fichaId);
      }
      if (document.body.getAttribute('data-vecinos-rel') === '1') {
        var tab = document.querySelector('[data-vec-tab="relaciones"]');
        if (tab) tab.click();
      }
      if (visualCapa === 'intervencion') abrirIntervencionPanel();
      if (visualCapa === 'notas_mapa') abrirNotasMapa();
      if (visualCapa === 'tutorial') abrirTutorialHarness();
    }
    setTimeout(postBoot, 900);
    setTimeout(postBoot, 1800);
    setTimeout(postBoot, 2800);
  });
})();
