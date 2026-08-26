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
  var fichaId = document.body.getAttribute('data-ficha-id') || '';

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
    if (u.indexOf('residentes.ficha') !== -1 && fichaId) {
      var res = (payload.partida && payload.partida.residentes && payload.partida.residentes[fichaId]) || null;
      return Promise.resolve(new Response(JSON.stringify({ ok: !!res, ficha: res ? { vista_play: res } : null }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' }
      }));
    }
    if (u.indexOf('residentes.diario') !== -1) {
      return Promise.resolve(new Response(JSON.stringify({ ok: true, entradas: [] }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' }
      }));
    }
    return origFetch.apply(this, arguments);
  };

  document.addEventListener('DOMContentLoaded', function () {
    function postBoot() {
      if (capa && typeof setCapa === 'function') setCapa(capa);
      if (capa === 'ficha' && fichaId && typeof abrirFicha === 'function') abrirFicha(fichaId);
      if (capa === 'ficha_diario' && fichaId && typeof abrirDiarioVecino === 'function') abrirDiarioVecino(fichaId);
      if (document.body.getAttribute('data-vecinos-rel') === '1') {
        var tab = document.querySelector('[data-vec-tab="relaciones"]');
        if (tab) tab.click();
      }
    }
    setTimeout(postBoot, 900);
    setTimeout(postBoot, 1800);
  });
})();
