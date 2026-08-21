(function () {
  'use strict';

  const API = 'api/index.php';
  const qs = new URLSearchParams(location.search);
  const CONFIG_JUEGO = { config_id: 'juego_v1' };
  const CONFIG_LAB = { config_id: 'playtest_01', seed: 'playtest-01' };
  const IS_LAB = qs.get('lab') === '1';
  function configNueva(forceFreshSeed) {
    const c = qs.get('config');
    if (c) {
      const o = { config_id: c };
      if (forceFreshSeed || IS_LAB) {
        o.seed = 'playtest-' + Date.now().toString(36);
      } else if (qs.get('seed')) {
        o.seed = qs.get('seed');
      }
      return o;
    }
    if (IS_LAB) {
      return { config_id: 'playtest_01', seed: 'playtest-' + Date.now().toString(36) };
    }
    return CONFIG_JUEGO;
  }
  let partidaId = localStorage.getItem(IS_LAB ? 'aht_partida_id' : 'aht_partida_id_juego');
  let cacheEstado = null;
  let cacheInsp = null;
  let cachePueblo = null;
  let cacheBuzon = [];
  let org = { tipo: '', a: '', b: '', lugar: '', dia: null, hora: 17 };
  const playtestLogClient = { entries: [] };
  playtestLogClient.push = function (e) {
    this.entries.push(e);
    if (this.entries.length > 300) this.entries = this.entries.slice(-300);
  };
  function storageKey() { return IS_LAB ? 'aht_partida_id' : 'aht_partida_id_juego'; }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }
  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));
  const ptToggle = $('[data-playtest-toggle]');
  const ptPanel = document.querySelector('[data-playtest-float] .playtest-float-panel');
  if (ptToggle && ptPanel) {
    ptToggle.addEventListener('click', function () {
      var open = ptPanel.hasAttribute('hidden');
      if (open) ptPanel.removeAttribute('hidden');
      else ptPanel.setAttribute('hidden', 'hidden');
      ptToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  function pintarPlaytestDiag(fromServer) {
    const pre = $('[data-playtest-diag-log]');
    if (!pre) return;
    const serverText = fromServer && fromServer.texto ? String(fromServer.texto) : '';
    const clientBits = playtestLogClient.entries.map(function (e) {
      if (e.tipo === 'API_ERROR') {
        return e.ts + ' | API_ERROR\n' + e.method + ' ' + e.action + ' → HTTP ' + e.status
          + '\nreq: ' + JSON.stringify(e.payload)
          + '\nresp: ' + JSON.stringify(e.respuesta).slice(0, 500)
          + '\ncausa: ' + e.causa;
      }
      return e.ts + ' | ' + (e.tipo || 'CLIENT') + '\n' + JSON.stringify(e);
    });
    const all = [];
    if (serverText) all.push(serverText);
    if (clientBits.length) all.push('--- CLIENT / API ---\n' + clientBits.join('\n\n'));
    pre.textContent = all.length ? all.join('\n\n') : '(aún no hay eventos)';
    try { if (fromServer && fromServer.texto) console.log('[AHT playtest_diag]\n' + fromServer.texto); } catch (e) {}
  }


  async function api(action, body, method) {
    body = body || {};
    method = method || 'POST';
    const opts = { method: method };
    let url;
    if (IS_LAB) body.lab = 1;
    if (method === 'GET') {
      const q = new URLSearchParams();
      q.set('action', action);
      if (IS_LAB) q.set('lab', '1');
      if (partidaId) q.set('partida_id', partidaId);
      Object.keys(body).forEach(function (k) {
        const v = body[k];
        if (v === undefined || v === null) return;
        if (Array.isArray(v)) {
          v.forEach(function (item) { q.append(k + '[]', String(item)); });
        } else if (typeof v !== 'object') {
          q.set(k, String(v));
        }
      });
      url = API + '?' + q.toString();
    } else {
      opts.headers = { 'Content-Type': 'application/json' };
      opts.body = JSON.stringify(Object.assign({ partida_id: partidaId }, body));
      url = API + '?action=' + encodeURIComponent(action);
    }
    let resp;
    let raw = '';
    try {
      resp = await fetch(url, opts);
      raw = await resp.text();
    } catch (err) {
      const fail = { ok: false, error: 'network_error', mensaje_ui: 'No se pudo contactar con la API.', detalle: String(err && err.message || err) };
      logApiError(action, method, body, 0, fail, 'network');
      return fail;
    }
    let data;
    try {
      data = raw ? JSON.parse(raw) : {};
    } catch (err) {
      const fail = { ok: false, error: 'respuesta_no_json', status: resp.status, mensaje_ui: 'La API no devolvió JSON.', raw: raw.slice(0, 400) };
      logApiError(action, method, body, resp.status, fail, 'json_parse');
      return fail;
    }
    if (!resp.ok || data.ok === false) {
      logApiError(action, method, body, resp.status, data, data.error || ('http_' + resp.status));
    }
    if (IS_LAB && typeof AhtLabAudit !== 'undefined' && AhtLabAudit.log) {
      try { AhtLabAudit.log(data); } catch (e) {}
    }
    return data;
  }

  function logApiError(action, method, payload, status, data, causa) {
    const entry = {
      ts: new Date().toISOString().slice(11, 19),
      tipo: 'API_ERROR',
      method: method,
      action: action,
      status: status,
      payload: payload,
      respuesta: data,
      causa: causa
    };
    playtestLogClient.push(entry);
    try { console.warn('[AHT playtest API]', method, action, status, causa, data); } catch (e) {}
    pintarPlaytestDiag();
  }


    const TUT_INTRO_KEY_PREFIX = 'aht_intro_v1_';
  const TUT_PASOS = [
    {
      tit: 'Bienvenida al pueblo',
      txt: 'Este es tu mapa: un pueblo con hasta 24 vecinos, nueve lugares y un reloj que sigue aunque tú no hagas nada. Observas, lees y propones; no mueves piezas.'
    },
    {
      tit: 'El tiempo pasa',
      txt: 'Arriba ves el día y la hora. El pueblo vive solo: la gente va y viene, llegan recados y cambian los planes. Tú decides cuándo mirar más de cerca.'
    },
    {
      tit: 'Los lugares',
      txt: 'Toca una zona del mapa — cafetería, parque, cine… — para ver quién está y, si encaja, proponer un plan allí.'
    },
    {
      tit: 'Organizar',
      txt: 'No mandas a nadie. Propones un plan entre dos vecinas: quién, dónde y cuándo. Ellas deciden si les cuadra.'
    },
    {
      tit: 'Mensajitos',
      txt: 'En el sobre llegan recados, peticiones y cotilleos. Los urgentes llevan lacre. Léelos cuando quieras; algunos piden prisa.'
    },
    {
      tit: 'Vecinos y diario',
      txt: 'Desde Celestine abres Vecinos: la libreta con quien vive en el pueblo. En Diario, El Cotilleo cuenta lo que se comenta. Todo encaja con el mismo reloj.'
    },
    {
      tit: 'A jugar',
      txt: 'Empieza por Mensajitos si hay un recado, o echa un ojo al mapa. Si te pierdes, el botón «¿Cómo va esto?» vuelve a abrir esta guía.'
    }
  ];
  let tutIntroIdx = 0;

  function tutIntroKey() {
    return TUT_INTRO_KEY_PREFIX + (partidaId || 'sin_partida');
  }
  function tutIntroHecho() {
    try { return localStorage.getItem(tutIntroKey()) === '1'; } catch (e) { return false; }
  }
  function marcarTutIntroHecho() {
    try { localStorage.setItem(tutIntroKey(), '1'); } catch (e) {}
  }
  function pintarTutIntro() {
    const box = $('[data-tut-intro]');
    if (!box) return;
    const paso = TUT_PASOS[tutIntroIdx];
    $('[data-tut-tit]').textContent = paso.tit;
    $('[data-tut-texto]').textContent = paso.txt;
    const dots = $('[data-tut-pasos]');
    dots.innerHTML = '';
    TUT_PASOS.forEach(function (_, i) {
      const s = document.createElement('span');
      if (i <= tutIntroIdx) s.className = 'is-on';
      dots.appendChild(s);
    });
    const btnAtras = $('[data-tut-atras]');
    const btnSig = $('[data-tut-siguiente]');
    if (btnAtras) btnAtras.hidden = tutIntroIdx === 0;
    if (btnSig) btnSig.textContent = tutIntroIdx >= TUT_PASOS.length - 1 ? 'Empezar' : 'Siguiente';
  }
  function abrirTutIntro(desdeCero) {
    if (desdeCero) tutIntroIdx = 0;
    const box = $('[data-tut-intro]');
    if (!box) return;
    box.hidden = false;
    document.body.setAttribute('data-tut-activo', '1');
    pintarTutIntro();
  }
  function cerrarTutIntro(marcar) {
    const box = $('[data-tut-intro]');
    if (box) box.hidden = true;
    document.body.removeAttribute('data-tut-activo');
    if (marcar) marcarTutIntroHecho();
    const reopen = $('[data-tut-reopen]');
    if (reopen) reopen.hidden = false;
  }
  function quizaMostrarTutIntro() {
    if (IS_LAB || tutIntroHecho()) {
      const reopen = $('[data-tut-reopen]');
      if (reopen && tutIntroHecho()) reopen.hidden = false;
      return;
    }
    abrirTutIntro(true);
  }
  function pintarTutorialMotor(tut) {
    const pista = $('[data-tutorial-pista]');
    if (!pista) return;
    if (!tut || !tut.activo || !tut.pista) {
      pista.hidden = true;
      pista.textContent = '';
      document.body.removeAttribute('data-tutorial-zona');
      return;
    }
    pista.hidden = false;
    pista.textContent = tut.pista;
    if (tut.zona) document.body.setAttribute('data-tutorial-zona', tut.zona);
    else document.body.removeAttribute('data-tutorial-zona');
  }

  function layout() {
    const root = $('.play-root');
    const phone = window.innerWidth < 720;
    root.classList.toggle('phone', phone);
    root.classList.toggle('pc', !phone);
  }

  function toast(txt) {
    const n = $('[data-toast]');
    n.textContent = txt;
    n.classList.add('is-on');
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { n.classList.remove('is-on'); }, 2800);
  }

  function uiRootFrom(el) {
    return el && (el.closest('.play-root') || el.closest('.game-shell'));
  }

  function setCapa(name) {
    const root = $('.play-root');
    if (!name) root.removeAttribute('data-capa');
    else root.setAttribute('data-capa', name);
    $$('.dock button').forEach(function (b) {
      const open = b.getAttribute('data-open');
      b.classList.toggle('is-on', name ? open === name : !open);
    });
  }

  function dineroTxt(insp, estado) {
    const eco = (insp && insp.economia && insp.economia.dinero) ? insp.economia.dinero.balance : null;
    const cel = estado && estado.celeste ? estado.celeste.dinero : null;
    const v = eco !== null && eco !== undefined ? eco : cel;
    if (v === null || v === undefined || v === '') return '—';
    return String(Math.round(Number(v))) + ' €';
  }


  function nombreDe(id) {
    const r = (cacheInsp && cacheInsp.residentes && cacheInsp.residentes[id]) || {};
    return (r.identidad_publica && r.identidad_publica.nombre) || id;
  }

  function renderMisiones(misiones) {
    var teaser = $('[data-misiones-teaser]');
    var list = $('[data-misiones-list]');
    var items = [];
    if (misiones && Array.isArray(misiones.misiones)) items = misiones.misiones;
    else if (misiones && Array.isArray(misiones.items)) items = misiones.items;
    else if (Array.isArray(misiones)) items = misiones;
    var dia = (misiones && misiones.dia) ? misiones.dia : (cacheEstado && cacheEstado.reloj ? cacheEstado.reloj.dia_pueblo : 0);
    if (!dia && cacheEstado && cacheEstado.reloj) dia = cacheEstado.reloj.dia_pueblo;
    var hoy = items.filter(function (m) { return !m.dia || (m.dia || 0) === dia; });
    if (teaser) {
      var pend = hoy.filter(function (m) { return (m.estado || '') === 'pendiente'; });
      teaser.textContent = pend.length
        ? (pend.length + ' objetivo' + (pend.length === 1 ? '' : 's') + ' pendiente' + (pend.length === 1 ? '' : 's'))
        : (hoy.length ? 'Nada pendiente hoy.' : 'Sin misiones hoy.');
    }
    if (!list) return;
    list.innerHTML = '';
    if (!hoy.length) {
      list.innerHTML = '<p class="muted">No hay misiones para hoy.</p>';
      return;
    }
    hoy.forEach(function (m) {
      var row = document.createElement('div');
      row.className = 'mision-row mision-' + (m.estado || 'pendiente');
      row.innerHTML = '<p>' + esc(m.texto || m.hecho || 'Objetivo') + '</p>' +
        '<span class="mision-estado">' + esc(m.estado || '') + '</span>';
      list.appendChild(row);
    });
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
        '<div class="stat-row"><span>Vecinos</span><strong>' + nRes + ' de 24</strong></div>' +
        '<div class="stat-row"><span>Parejas</span><strong>' + parejas.length + '</strong></div>' +
        '<div class="stat-row"><span>Buzón pendiente</span><strong>' +
        (buzon || []).filter(function (m) { return (m.estado || '') === 'pendiente'; }).length +
        '</strong></div>';
    }

    const teaser = $('[data-cotilleo-teaser]');
    const hoy = (diario && diario.cotilleo && diario.cotilleo.hoy) || diario.entradas || [];
    const ult = (hoy[0] && (hoy[0].texto || hoy[0].cuerpo || hoy[0].titulo)) || '';
    if (teaser) teaser.textContent = ult || 'Todavía no hay cotilleo hoy.';

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


  var cacheMapaZonas = null;
  const LUGAR_NOMBRE_UI = {
    lug_cafeteria: 'la cafetería', lug_biblioteca: 'la biblioteca', lug_gimnasio: 'el gimnasio',
    lug_restaurante: 'el restaurante', lug_parque: 'el parque', lug_bar: 'el bar',
    lug_cine: 'el cine', lug_discoteca: 'la discoteca', lug_bingo: 'el bingo'
  };
  function nombreLugarUi(id, fallback) {
    if (!id) return fallback || 'ese sitio';
    if (LUGAR_NOMBRE_UI[id]) return LUGAR_NOMBRE_UI[id];
    var fb = fallback || id;
    if (typeof fb === 'string' && fb.indexOf('lug_') === 0) return fb.replace('lug_', '').replace(/_/g, ' ');
    return fb;
  }
  var LUG_TO_ZONA = {
    lug_cafeteria: 'cafeteria', lug_biblioteca: 'biblioteca', lug_gimnasio: 'gimnasio',
    lug_restaurante: 'restaurante', lug_parque: 'parque', lug_bar: 'bar',
    lug_cine: 'cine', lug_discoteca: 'discoteca', lug_bingo: 'bingo'
  };
  var ZONA_TO_LUGS = {
    cafeteria: ['lug_cafeteria'], biblioteca: ['lug_biblioteca'], gimnasio: ['lug_gimnasio'],
    restaurante: ['lug_restaurante'], parque: ['lug_parque'], bar: ['lug_bar'],
    cine: ['lug_cine'], discoteca: ['lug_discoteca'], bingo: ['lug_bingo']
  };

  function initMapaCanonico() {
    var layer = $('[data-mapa-zonas]');
    if (!layer) return Promise.resolve(null);
    var v = (document.querySelector('meta[name="aht-ui"]') && document.querySelector('script[src*="play-v3.js"]')) ?
      (document.querySelector('script[src*="play-v3.js"]').src.split('v=')[1] || '') : '';
    return fetch('assets/play-v3/mapa_zonas.json?v=' + encodeURIComponent(v)).then(function (r) { return r.json(); }).then(function (cfg) {
      cacheMapaZonas = cfg;
      layer.innerHTML = '';
      var zonas = cfg.zonas || {};
      Object.keys(zonas).forEach(function (id) {
        var z = zonas[id];
        if (!z || !z.w || !z.h) return;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'mapa-zona-hit';
        btn.setAttribute('data-zona', id);
        btn.setAttribute('aria-label', z.label || id);
        btn.style.left = z.x + '%';
        btn.style.top = z.y + '%';
        btn.style.width = z.w + '%';
        btn.style.height = z.h + '%';
        btn.innerHTML = '<span class="habs"></span>';
        layer.appendChild(btn);
      });
      return cfg;
    }).catch(function () { return null; });
  }

  function personasEnZona(zonaId) {
    var lugs = ZONA_TO_LUGS[zonaId] || [];
    var out = [];
    (cachePueblo && cachePueblo.complejos || []).forEach(function (cx) {
      (cx.personas || []).forEach(function (p) {
        if (lugs.indexOf(p.destino_id) >= 0) out.push(p);
      });
    });
    return out;
  }

  function destinosOperativosZona(zonaId) {
    var lugs = ZONA_TO_LUGS[zonaId] || [];
    var out = [];
    (cachePueblo && cachePueblo.complejos || []).forEach(function (cx) {
      (cx.destinos_operativos || []).forEach(function (d) {
        if (lugs.indexOf(d.id) >= 0) out.push(d);
      });
    });
    return out;
  }

  function placeHabEnZona(box, p, i) {
    var el = document.createElement('span');
    el.className = 'hab';
    el.setAttribute('data-residente', p.id);
    el.setAttribute('data-destino', p.destino_id);
    el.setAttribute('data-fase', p.fase || 'en_destino');
    el.setAttribute('data-emocion', p.emocion || 'neutro');
    if (p.hay_tema) el.setAttribute('data-hay-tema', '1');
    var cols = 3;
    var col = i % cols;
    var row = Math.floor(i / cols);
    el.style.left = (14 + col * 26) + '%';
    el.style.top = (18 + row * 24) + '%';
    if (p.token_url) {
      el.innerHTML = '<span class="cara"><img src="' + p.token_url + '" alt=""/></span>';
    } else {
      el.innerHTML = '<span class="cara cara-ini">' + (p.iniciales || '?') + '</span>';
    }
    if (p.hay_tema) {
      el.insertAdjacentHTML('beforeend', '<img class="sello-tema" src="assets/play-v3/marcas/sello_hay_tema.png" alt=""/>');
    }
    box.appendChild(el);
  }

  function posicionarNotaMapa(el, zonaBtn) {
    if (!el || !zonaBtn) return;
    var board = $('.board-fit');
    if (!board) return;
    var boardRect = board.getBoundingClientRect();
    var zonaRect = zonaBtn.getBoundingClientRect();
    var margen = 10;
    var pw = el.offsetWidth || 240;
    var ph = el.offsetHeight || 160;
    var relX = zonaRect.left - boardRect.left;
    var relY = zonaRect.top - boardRect.top;
    var bw = boardRect.width;
    var bh = boardRect.height;
    var left = relX + zonaRect.width + margen;
    if (left + pw > bw - margen) {
      left = relX - pw - margen;
    }
    if (left < margen) left = margen;
    if (left + pw > bw - margen) left = Math.max(margen, bw - pw - margen);
    var top = relY;
    if (top + ph > bh - margen) top = Math.max(margen, bh - ph - margen);
    if (top < margen) top = margen;
    el.style.right = 'auto';
    el.style.left = left + 'px';
    el.style.top = top + 'px';
  }

  function abrirConsultaZona(zonaId, zonaBtn) {
    var meta = cacheMapaZonas && cacheMapaZonas.zonas && cacheMapaZonas.zonas[zonaId];
    var ops = destinosOperativosZona(zonaId);
    if (ops.length > 1) {
      $('.play-root').setAttribute('data-consulta', 'sel');
      $('[data-s-tit]').textContent = meta ? meta.label : zonaId;
      $('[data-s-coti]').textContent = ops.map(function (d) { return d.nombre; }).join(' · ');
      var box = $('[data-s-btns]');
      box.innerHTML = '';
      ops.forEach(function (d) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = 'Ver ' + nombreLugarUi(d.id, d.nombre);
        b.addEventListener('click', function () { abrirQuienZona(zonaId, d.id, zonaBtn); });
        box.appendChild(b);
      });
      var all = document.createElement('button');
      all.type = 'button';
      all.textContent = 'Quién hay aquí';
      all.addEventListener('click', function () { abrirQuienZona(zonaId, null, zonaBtn); });
      box.appendChild(all);
      posicionarNotaMapa($('.selector'), zonaBtn);
      return;
    }
    abrirQuienZona(zonaId, ops[0] ? ops[0].id : null, zonaBtn);
  }

  function abrirQuienZona(zonaId, destId, zonaBtn) {
    var meta = cacheMapaZonas && cacheMapaZonas.zonas && cacheMapaZonas.zonas[zonaId];
    var lugs = ZONA_TO_LUGS[zonaId] || [];
    var gente = personasEnZona(zonaId).filter(function (p) {
      return !destId || p.destino_id === destId;
    });
    $('.play-root').setAttribute('data-consulta', 'quien');
    $('[data-q-tit]').textContent = meta ? meta.label : zonaId;
    $('[data-q-sum]').textContent = gente.length
      ? (gente.length === 1 ? 'Hay alguien.' : ('Hay ' + gente.length + '.'))
      : 'No hay ni un alma.';
    var list = $('[data-q-list]');
    list.innerHTML = '';
    var groups = {};
    gente.forEach(function (p) {
      if (!groups[p.destino_nombre]) groups[p.destino_nombre] = [];
      groups[p.destino_nombre].push(p);
    });
    Object.keys(groups).forEach(function (dest) {
      var h = document.createElement('p');
      h.className = 'quien-dest';
      h.textContent = dest;
      list.appendChild(h);
      var ul = document.createElement('ul');
      groups[dest].forEach(function (p) {
        var li = document.createElement('li');
        li.textContent = p.nombre;
        ul.appendChild(li);
      });
      list.appendChild(ul);
    });
    var box = $('[data-q-btns]');
    box.innerHTML = '';
    destinosOperativosZona(zonaId).forEach(function (d) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = 'Organizar en ' + nombreLugarUi(d.id, d.nombre);
      b.addEventListener('click', function () {
        org.lugar = d.id;
        setCapa('organizar');
        $('.play-root').removeAttribute('data-consulta');
        fillOrganizar();
      });
      box.appendChild(b);
    });
    posicionarNotaMapa($('.quien'), zonaBtn);
  }

  function renderHud(estado, buzon) {
    const rv = estado.reloj_vista || {};
    const reloj = estado.reloj || {};
    $('[data-dow]').textContent = rv.dia_semana_ui || ('Día ' + (reloj.dia_pueblo || '—'));
    $('[data-fecha]').textContent = rv.fecha_corta || '';
    const h = rv.hora !== undefined ? rv.hora : reloj.hora_actual;
    $('[data-hora]').textContent = h === undefined ? '—' : (String(h).padStart(2, '0') + ':00');
    const vida = estado.vida_pueblo || null;
    const pct = vida && typeof vida.corazon_pct === 'number' ? vida.corazon_pct : 0;
    const fillRect = $('[data-corazon-fill]');
    if (fillRect) {
      var fillH = 52 * (pct / 100);
      fillRect.setAttribute('y', String(52 - fillH));
      fillRect.setAttribute('height', String(fillH));
    }
    const fill = $('.corazon-fill') || $('.corazon-dibujo');
    if (fill) fill.style.setProperty('--fill', pct + '%');
    const pctN = $('[data-vida-pct]');
    if (pctN) pctN.textContent = Math.round(pct) + '%';
    const cartas = (buzon || []).filter(function (m) {
      return (m.canal || 'buzon') === 'buzon' && (m.estado || '') === 'pendiente';
    });
    const badge = $('.buzon .badge');
    badge.textContent = String(cartas.length);
    badge.classList.toggle('is-on', cartas.length > 0);
    const imp = cartas.some(function (m) { return m.clasificacion === 'importante'; });
    if (imp) $('.play-root').setAttribute('data-importante', '1');
    else $('.play-root').removeAttribute('data-importante');
  }

  function placeHab(box, p, i, cid) {
    const el = document.createElement('span');
    el.className = 'hab';
    el.setAttribute('data-residente', p.id);
    el.setAttribute('data-complejo', cid);
    el.setAttribute('data-destino', p.destino_id);
    el.setAttribute('data-fase', p.fase || 'en_destino');
    el.setAttribute('data-emocion', p.emocion || 'neutro');
    if (p.hay_tema) el.setAttribute('data-hay-tema', '1');
    const slots = (SLOTS[cid] && SLOTS[cid][p.destino_id]) || [[22 + i * 12, 50]];
    const xy = slots[Math.min(i, slots.length - 1)];
    el.style.left = xy[0] + '%';
    el.style.top = xy[1] + '%';
    if (p.token_url) {
      el.innerHTML = '<span class="cara"><img src="' + p.token_url + '" alt=""/></span>';
    } else {
      el.innerHTML = '<span class="cara cara-ini">' + (p.iniciales || '?') + '</span>';
    }
    if (p.hay_tema) {
      el.insertAdjacentHTML('beforeend', '<img class="sello-tema" src="assets/play-v3/marcas/sello_hay_tema.png" alt=""/>');
    }
    box.appendChild(el);
  }

  function renderPueblo(pueblo) {
    cachePueblo = pueblo;
    var layer = $('[data-mapa-zonas]');
    if (!layer) return;
    $$('.mapa-zona-hit .habs').forEach(function (b) { b.innerHTML = ''; });
    var porZona = {};
    (pueblo.complejos || []).forEach(function (cx) {
      (cx.visibles || cx.personas || []).forEach(function (p) {
        var zid = LUG_TO_ZONA[p.destino_id];
        if (!zid) return;
        if (!porZona[zid]) porZona[zid] = [];
        if (porZona[zid].length >= 5) return;
        porZona[zid].push(p);
      });
    });
    Object.keys(porZona).forEach(function (zid) {
      var btn = layer.querySelector('[data-zona="' + zid + '"]');
      if (!btn) return;
      var box = btn.querySelector('.habs');
      if (!box) return;
      porZona[zid].forEach(function (p, i) { placeHabEnZona(box, p, i); });
    });
  }

  function applyFases(pueblo) {
    $$('.complejo').forEach(function (el) {
      const id = el.getAttribute('data-complejo');
      const cx = (pueblo.complejos || []).filter(function (c) { return c.id === id; })[0];
      el.classList.toggle('is-pleno', !!(cx && cx.fase === 'pleno'));
    });
  }

  function pintarEdificios(btn, cx) {
    const ops = {};
    (cx.destinos || []).forEach(function (d, i) {
      if (i === 0 || d.operativo) ops[d.id] = true;
    });
    const edifs = btn.querySelectorAll('.edif');
    let alguna = false;
    for (let j = 0; j < edifs.length; j++) {
      const img = edifs[j];
      const show = !!ops[img.getAttribute('data-destino')];
      img.classList.toggle('is-on', show);
      if (show) alguna = true;
    }
    btn.classList.toggle('tiene-edifs', alguna);
  }

  function cxById(id) {
    return (cachePueblo && cachePueblo.complejos || []).filter(function (c) { return c.id === id; })[0];
  }

  function abrirConsulta(id) {
    const cx = cxById(id);
    if (!cx) return;
    const ops = cx.destinos_operativos || [];
    if (ops.length > 1) {
      $('.play-root').setAttribute('data-consulta', 'sel');
      $('[data-s-tit]').textContent = cx.nombre;
      $('[data-s-coti]').textContent = ops.map(function (d) { return d.nombre; }).join(' · ');
      const box = $('[data-s-btns]');
      box.innerHTML = '';
      ops.forEach(function (d) {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = 'Ver ' + nombreLugarUi(d.id, d.nombre);
        b.addEventListener('click', function () { abrirQuien(id, d.id); });
        box.appendChild(b);
      });
      const all = document.createElement('button');
      all.type = 'button';
      all.textContent = 'Quién hay en el complejo';
      all.addEventListener('click', function () { abrirQuien(id, null); });
      box.appendChild(all);
      return;
    }
    abrirQuien(id, ops[0] ? ops[0].id : null);
  }

  function abrirQuien(id, destId) {
    const cx = cxById(id);
    $('.play-root').setAttribute('data-consulta', 'quien');
    $('[data-q-tit]').textContent = cx.nombre;
    const gente = (cx.personas || []).filter(function (p) {
      return !destId || p.destino_id === destId;
    });
    $('[data-q-sum]').textContent = gente.length
      ? (gente.length === 1 ? 'Hay alguien.' : ('Hay ' + gente.length + '.'))
      : copyVacio(id);
    const list = $('[data-q-list]');
    list.innerHTML = '';
    const groups = {};
    gente.forEach(function (p) {
      if (!groups[p.destino_nombre]) groups[p.destino_nombre] = [];
      groups[p.destino_nombre].push(p);
    });
    Object.keys(groups).forEach(function (dest) {
      const h = document.createElement('p');
      h.className = 'quien-dest';
      h.textContent = dest;
      list.appendChild(h);
      const ul = document.createElement('ul');
      groups[dest].forEach(function (p) {
        const li = document.createElement('li');
        li.textContent = p.nombre;
        ul.appendChild(li);
      });
      list.appendChild(ul);
    });
    const box = $('[data-q-btns]');
    box.innerHTML = '';
    (cx.destinos_operativos || []).forEach(function (d) {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = 'Organizar en ' + nombreLugarUi(d.id, d.nombre);
      b.addEventListener('click', function () {
        org.lugar = d.id;
        setCapa('organizar');
        $('.play-root').removeAttribute('data-consulta');
        fillOrganizar();
      });
      box.appendChild(b);
    });
  }

  function copyVacio(cid) {
    const t = {
      cafe_libros: 'Ni el café humea. No hay ni un alma.',
      rincon_lola: 'Hoy Lola no tendría a quién servir.',
      cine_game: 'Pantalla en negro. No hay ni un alma.',
      mala_idea: 'Hasta el bar está en silencio.',
      parque: 'Solo el banco, esperando.',
      gimnasio_spa: 'Máquinas quietas. No hay ni un alma.'
    };
    return t[cid] || 'No hay ni un alma.';
  }

  function tokenDe(rid) {
    const lote = cachePueblo && cachePueblo.tokens ? cachePueblo.tokens[rid] : null;
    if (lote && lote.url) return lote.url;
    const all = [];
    (cachePueblo && cachePueblo.complejos || []).forEach(function (c) {
      (c.personas || []).forEach(function (p) { all.push(p); });
    });
    const hit = all.filter(function (p) { return p.id === rid; })[0];
    if (hit && hit.token_url) return hit.token_url;
    return null;
  }

  let vecBuscaTxt = '';
  let resBloqueActivo = 'a';
  let resBuscaTxt = '';

  function renderVecinos() {
    const box = $('[data-vecinos-list]');
    box.innerHTML = '';
    const res = (cacheInsp && cacheInsp.residentes) || {};
    const filtro = (vecBuscaTxt || '').trim().toLowerCase();
    const ids = Object.keys(res).filter(function (id) {
      const r = res[id];
      const nom = ((r.identidad_publica && r.identidad_publica.nombre) || id).toLowerCase();
      return !filtro || nom.indexOf(filtro) >= 0;
    });
    ids.sort(function (a, b) {
      const na = (res[a].identidad_publica && res[a].identidad_publica.nombre) || a;
      const nb = (res[b].identidad_publica && res[b].identidad_publica.nombre) || b;
      return String(na).localeCompare(String(nb), 'es');
    });
    if (!ids.length) {
      box.innerHTML = '<p class="lista-vacia vecinos-vacio">' +
        (filtro ? 'Nadie con ese nombre.' : 'Todavía no hay vecinos en esta partida.') + '</p>';
      return;
    }
    ids.forEach(function (id) {
      const r = res[id];
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'vecino-celda';
      const img = tokenDe(id);
      const nom = (r.identidad_publica && r.identidad_publica.nombre) || id;
      const ini = nom.charAt(0) || '?';
      b.innerHTML = '<div class="vecino-cara">' +
        (img ? '<img src="' + esc(img) + '" alt=""/>' : '<span class="vecino-ini">' + esc(ini) + '</span>') +
        '</div><p class="vecino-nom">' + esc(nom) + '</p>';
      b.addEventListener('click', function () { abrirFicha(id); });
      box.appendChild(b);
    });
  }

  var RES_BLOQUES = [
    { letra: 'a', key: 'bloque_a' },
    { letra: 'b', key: 'bloque_b' },
    { letra: 'c', key: 'bloque_c' }
  ];

  function bloqueInfo(letra) {
    var partida = cacheInsp || {};
    var def = RES_BLOQUES.filter(function (b) { return b.letra === letra; })[0];
    var blk = def ? partida[def.key] : null;
    var viviendas = (blk && Array.isArray(blk.viviendas)) ? blk.viviendas : [];
    return { viviendas: viviendas, abierto: viviendas.length > 0 };
  }

  async function abrirFicha(id) {
    const r = await api('residente.ficha', { residente_id: id }, 'GET');
    if (!r.ok) return;
    if (r.tutorial) pintarTutorialMotor(r.tutorial);
    const f = r.ficha || {};
    const vista = f.vista_play || f;
    setCapa('ficha');
    $('[data-ficha-nombre]').textContent = vista.nombre || (f.identidad && f.identidad.nombre) || id;
    const emo = vista.estado_animo || 'neutro';
    $('[data-ficha-animo]').textContent = emo === 'neutro' ? 'Hoy no se le nota gran cosa.' : ('Está ' + emo + '.');
    const img = tokenDe(id);
    $('[data-ficha-img]').innerHTML = img ? '<img src="' + img + '" alt=""/>' : '';
    const ul = $('[data-ficha-pistas]');
    ul.innerHTML = '';
    (vista.manera_de_ser || []).forEach(function (t) {
      const li = document.createElement('li');
      li.textContent = t;
      ul.appendChild(li);
    });
    (vista.pistas || []).forEach(function (t) {
      const li = document.createElement('li');
      li.textContent = t;
      ul.appendChild(li);
    });
    if (!ul.children.length) {
      const li = document.createElement('li');
      li.textContent = 'Página casi en blanco.';
      ul.appendChild(li);
    }
    org.a = id;
    $('[data-ficha-org]').onclick = function () {
      org.a = id;
      setCapa('organizar');
      fillOrganizar();
    };
  }

  function estadoCarta(m) {
    const pueblo = m.estado_pueblo || '';
    if (pueblo === 'cumplida') return { cls: 'estado-cumplida', txt: 'Hecho' };
    if (pueblo === 'caducada') return { cls: 'estado-caducada', txt: 'Se le pasó' };
    if ((m.estado || '') === 'pendiente') return { cls: 'estado-pendiente', txt: '' };
    if ((m.estado || '') === 'resuelto') return { cls: 'estado-cumplida', txt: 'Ya está' };
    return { cls: '', txt: '' };
  }

  function cuerpoCarta(m, de) {
    let t = String(m.texto || '').trim();
    if (de && t.indexOf(de + ':') === 0) t = t.slice(de.length + 1).trim();
    if (de && t.indexOf(de + ' ') === 0) {
      /* deja el resto */
    }
    return t;
  }

  function renderBuzon(msgs) {
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
      art.innerHTML = (m.clasificacion === 'importante' ? '<span class="lacre" aria-hidden="true"></span>' : '') +
        (st.txt ? '<div class="sello-estado">' + esc(st.txt) + '</div>' : '') +
        '<div class="de">De ' + esc(de) + '</div>' +
        '<p class="cuerpo">' + esc(cuerpo) + '</p>' +
        (plazo ? '<p class="plazo">' + esc(plazo) + '</p>' : '');
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
  }

  function nombreDe(id) {
    if (!id) return 'Alguien';
    const r = cacheInsp && cacheInsp.residentes && cacheInsp.residentes[id];
    return (r && r.identidad_publica && r.identidad_publica.nombre) || id;
  }

  function renderCotilleo(coti) {
    function fill(sel, items) {
      const box = $(sel);
      box.innerHTML = '';
      if (!items || !items.length) {
        box.innerHTML = '<p class="lista-vacia">Hoy el pueblo no ha dado titular.</p>';
        return;
      }
      items.forEach(function (e) {
        const art = document.createElement('article');
        art.className = 'recorte chisme';
        art.innerHTML = '<p>' + (e.texto || '') + '</p><p class="firma">' + (e.fecha_corta || ('día ' + e.dia)) + '</p>';
        box.appendChild(art);
      });
    }
    fill('[data-coti-hoy]', coti && coti.hoy);
    fill('[data-coti-ayer]', coti && coti.ayer);
    fill('[data-coti-viejos]', coti && coti.viejos);
  }

  function idsResidentes() {
    return Object.keys((cacheInsp && cacheInsp.residentes) || {});
  }

  function fillSelect(sel, value, excludeId) {
    sel.innerHTML = '<option value="">—</option>';
    idsResidentes().forEach(function (id) {
      if (excludeId && id === excludeId) return;
      const o = document.createElement('option');
      o.value = id;
      o.textContent = nombreDe(id);
      sel.appendChild(o);
    });
    if (value && value !== excludeId) sel.value = value;
    else if (value && value === excludeId) sel.value = '';
  }

  function destinosOperativos() {
    const out = [];
    (cachePueblo && cachePueblo.complejos || []).forEach(function (c) {
      (c.destinos_operativos || []).forEach(function (d) { out.push(d); });
    });
    return out;
  }

  function pintarOrgCaras() {
    var box = $('[data-org-caras]');
    if (!box) return;
    var selA = $('[data-org-a]');
    var selB = $('[data-org-b]');
    var a = (selA && selA.value) || org.a;
    var b = (selB && selB.value) || org.b;
    if (!a || !b || a === b) {
      box.hidden = true;
      box.innerHTML = '';
      return;
    }
    box.hidden = false;
    box.innerHTML = '';
    [a, b].forEach(function (id) {
      var img = tokenDe(id);
      var nom = nombreDe(id);
      var ini = (nom.charAt(0) || '?');
      var span = document.createElement('span');
      span.className = 'cara';
      span.innerHTML = img
        ? '<img src="' + esc(img) + '" alt=""/>'
        : '<span class="cara-ini">' + esc(ini) + '</span>';
      box.appendChild(span);
    });
  }

  function fillOrganizar() {
    fillSelect($('[data-org-a]'), org.a, org.b);
    fillSelect($('[data-org-b]'), org.b, org.a);
    const lug = $('[data-org-lugar]');
    lug.innerHTML = '<option value="">—</option>';
    destinosOperativos().forEach(function (d) {
      const o = document.createElement('option');
      o.value = d.id;
      o.textContent = d.nombre;
      lug.appendChild(o);
    });
    if (org.lugar) lug.value = org.lugar;
    const rv = (cacheEstado && cacheEstado.reloj_vista) || {};
    const dias = rv.proximos_dias || [];
    const fd = $('[data-org-dia]');
    fd.innerHTML = '';
    dias.forEach(function (d) {
      const o = document.createElement('option');
      o.value = String(d.dia_pueblo);
      o.textContent = d.etiqueta || ('día ' + d.dia_pueblo);
      fd.appendChild(o);
    });
    org.dia = org.dia || (cacheEstado && cacheEstado.reloj && cacheEstado.reloj.dia_pueblo);
    if (org.dia) fd.value = String(org.dia);
    const hora = $('[data-org-hora]');
    hora.innerHTML = '';
    for (let h = 8; h <= 23; h++) {
      const o = document.createElement('option');
      o.value = String(h);
      o.textContent = String(h).padStart(2, '0') + ':00';
      hora.appendChild(o);
    }
    hora.value = String(org.hora || 17);
    refreshTipos();
  }

  async function refreshTipos() {
    org.a = $('[data-org-a]').value;
    org.b = $('[data-org-b]').value;
    const box = $('[data-org-tipos]');
    if (!org.a || !org.b || org.a === org.b) {
      box.innerHTML = '<p class="mini">Elige a dos personas distintas. Luego vemos qué plan encaja.</p>';
      return;
    }
    const r = await api('encuentro.tipos_permitidos', { residente_a: org.a, residente_b: org.b }, 'GET');
    box.innerHTML = '';
    const ops = r.opciones || [];
    const ids = ops.map(function (op) { return op.id; });
    if (!org.tipo || ids.indexOf(org.tipo) < 0) {
      org.tipo = r.tipo_sugerido || (ops[0] && ops[0].id) || '';
    }
    ops.forEach(function (op) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'chip' + (org.tipo === op.id ? ' is-on' : '');
      b.textContent = op.label;
      b.addEventListener('click', function () {
        org.tipo = op.id;
        $$('.chip', box).forEach(function (c) { c.classList.remove('is-on'); });
        b.classList.add('is-on');
      });
      box.appendChild(b);
    });
    if (!ops.length) {
      box.innerHTML = '<p class="mini">' + (r.mensaje_ui || 'Entre estas dos, ahora no sale un plan.') + '</p>';
    }
  }

  async function proponer() {
    org.a = $('[data-org-a]').value;
    org.b = $('[data-org-b]').value;
    org.lugar = $('[data-org-lugar]').value;
    org.dia = parseInt($('[data-org-dia]').value, 10);
    org.hora = parseInt($('[data-org-hora]').value, 10);
    if (!org.a || !org.b || org.a === org.b || !org.lugar || !org.dia) {
      toast(org.a && org.a === org.b ? 'Elige a dos personas distintas.' : 'Falta quién, dónde o cuándo.');
      return;
    }
    const payload = {
      participantes: [org.a, org.b],
      dia: org.dia,
      hora: org.hora,
      tipo: org.tipo || '',
      lugar: org.lugar
    };
    const r = await api('encuentro.proponer', payload);
    if (r.playtest_diag) pintarPlaytestDiag(r.playtest_diag);
    try {
      console.log('[AHT plan]', payload, {
        ok: r.ok,
        rechazada: r.rechazada,
        rechazo_clase: r.rechazo_clase,
        error: r.error,
        mensaje_ui: r.mensaje_ui,
        reacciones: r.propuesta && r.propuesta.reacciones
      });
    } catch (e) {}
    if (r.ok) {
      toast(r.rechazada
        ? (r.mensaje_ui || 'No han quedado. Mira el registro tecnico.')
        : (r.mensaje_ui || 'Propuesto. Ellas siguen a lo suyo.'));
      setCapa('');
      await refresh();
      if (r.tutorial) pintarTutorialMotor(r.tutorial);
    } else {
      toast(r.mensaje_ui || 'Asi no se ha podido organizar.');
      await refresh();
    }
  }
  async function ensurePartida() {
    if (partidaId) {
      const r = await api('partida.cargar', { partida_id: partidaId });
      if (r.ok) return;
    }
    const r = await api('partida.nueva', configNueva(true));
    if (r.ok) {
      partidaId = r.partida_id;
      localStorage.setItem(storageKey(), partidaId);
    }
  }
  async function refresh() {
    const estadoResp = await api('partida.estado', {}, 'GET');
    if (!estadoResp.ok) return;
    cacheEstado = estadoResp.estado;
    const insp = await api('partida.inspeccionar', {}, 'GET');
    cacheInsp = insp.ok ? insp.partida : null;
    const mapa = await api('mapa.presencia', {}, 'GET');
    const buzon = await api('buzon.listar', {}, 'GET');
    const diario = await api('diario.listar', {}, 'GET');
    renderHud(cacheEstado, buzon.mensajes || []);
    renderShellPanels(cacheEstado, buzon.mensajes || [], diario);
      renderMisiones(cacheEstado.misiones_hoy || (cacheInsp && cacheInsp.misiones_diarias));
    renderPueblo(mapa.pueblo || { complejos: [] });
    renderBuzon(buzon.mensajes || []);
    renderCotilleo(diario.cotilleo || { hoy: diario.entradas || [], ayer: [], viejos: [] });
    renderVecinos();
    if (IS_LAB) {
      const tm = $('[data-taller-msg]');
      if (tm) tm.textContent = cacheEstado.reloj_texto || '';
    }
    pintarTutorialMotor(cacheEstado.tutorial);
  }

  async function nuevaPartidaLimpia() {
    localStorage.removeItem(storageKey());
    partidaId = null;
    cacheEstado = null;
    cacheInsp = null;
    cachePueblo = null;
    cacheBuzon = [];
    org = { tipo: '', a: '', b: '', lugar: '', dia: null, hora: 17 };
    playtestLogClient.entries = [];
    setCapa('');
    const r = await api('partida.nueva', configNueva(true));
    if (r.ok) {
      partidaId = r.partida_id;
      localStorage.setItem(storageKey(), partidaId);
      playtestLogClient.push({
        ts: new Date().toISOString().slice(11, 19),
        tipo: 'NUEVA_PARTIDA',
        partida_id: partidaId,
        seed: (r.partida && r.partida.meta && r.partida.meta.seed) || null
      });
      toast('Partida nueva (seed limpia).');
    } else {
      toast(r.mensaje_ui || 'No se pudo crear la partida.');
    }
    await refresh();
    quizaMostrarTutIntro();
  }

  if (IS_LAB) {
    const btnGuardar = $('#btn-guardar');
    if (btnGuardar) {
      btnGuardar.addEventListener('click', async function () {
        await api('partida.guardar', {});
        toast('Guardado.');
      });
    }
    const btnNueva = $('#btn-nueva');
    if (btnNueva) {
      btnNueva.addEventListener('click', async function () {
        await nuevaPartidaLimpia();
      });
    }
  }

  function pintarPlaytestGuia(guia, evento) {
    const box = $('[data-playtest-guia]');
    if (!box) return;
    if (!guia || !guia.activo) {
      box.hidden = true;
      return;
    }
    box.hidden = false;
    const tit = $('[data-pg-titulo]');
    if (tit) tit.textContent = guia.titulo || 'PRUEBA DEL PUEBLO';
    const rel = $('[data-pg-reloj]');
    if (rel) rel.textContent = guia.reloj_humano || '';
    const ahora = $('[data-pg-ahora]');
    if (ahora) {
      ahora.innerHTML = '';
      (guia.ahora_mismo || []).forEach(function (l) {
        const li = document.createElement('li');
        li.textContent = l;
        ahora.appendChild(li);
      });
    }
    const hacer = $('[data-pg-hacer]');
    if (hacer) {
      hacer.innerHTML = '';
      (guia.que_hacer_ahora || []).forEach(function (l) {
        const li = document.createElement('li');
        li.textContent = l;
        hacer.appendChild(li);
      });
    }
    const ev = $('[data-pg-evento]');
    const ultimo = evento || guia.ultimo;
    if (ev) {
      if (ultimo && (ultimo.titulo || (ultimo.lineas && ultimo.lineas.length))) {
        ev.hidden = false;
        const lines = (ultimo.lineas || []).map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('');
        ev.innerHTML = '<strong>' + esc(ultimo.titulo || 'HA PASADO ALGO') + '</strong><ul>' + lines + '</ul>';
      } else {
        ev.hidden = true;
        ev.innerHTML = '';
      }
    }
    const pist = $('[data-pg-pistas]');
    if (pist) {
      pist.innerHTML = '';
      (guia.pistas || []).forEach(function (p) {
        const d = document.createElement('div');
        d.className = 'pista ' + (p.tipo === 'ojo' ? 'ojo' : 'puedes');
        d.innerHTML = '<strong>' + esc(p.titulo || '') + '</strong><div>' + esc(p.texto || '') + '</div>';
        pist.appendChild(d);
      });
    }
    const objs = $('[data-pg-objs]');
    if (objs) {
      objs.innerHTML = '';
      (guia.objetivos || []).forEach(function (o) {
        const li = document.createElement('li');
        if (o.hecho) li.className = 'hecho';
        li.textContent = o.label || o.id;
        objs.appendChild(li);
      });
    }
  }

  function pintarResumenAvance(resumen) {
    const el = $('[data-taller-debug]');
    if (!el) return;
    const lineas = (resumen && resumen.lineas) || [];
    if (!lineas.length) {
      el.hidden = true;
      el.classList.remove('is-on');
      el.textContent = '';
      return;
    }
    el.hidden = false;
    el.classList.add('is-on');
    el.textContent = lineas.map(function (l) { return '· ' + (l.texto || l.tipo || ''); }).join('\n');
  }

  async function avanzarHoras(horas) {
    const r = await api('reloj.avanzar', { horas: horas, paso_a_paso: true });
    await refresh();
    if (r.playtest_guia) pintarPlaytestGuia(r.playtest_guia, r.playtest_guia_evento);
    if (r.playtest_diag) pintarPlaytestDiag(r.playtest_diag);
    pintarResumenAvance(r.resumen_avance);
  }
  async function irProximo() {
    const r = await api('reloj.proximo_encuentro', {});
    if (!r.ok) toast(r.mensaje_ui || 'No hay proximo encuentro.');
    await refresh();
    if (r.playtest_guia) pintarPlaytestGuia(r.playtest_guia, r.playtest_guia_evento);
    if (r.playtest_diag) pintarPlaytestDiag(r.playtest_diag);
    pintarResumenAvance(r.resumen_avance);
  }
  function bindLabHoras() {
    var scope = IS_LAB ? document.querySelector('[data-playtest-float]') : null;
    if (!scope) return;
    $$('[data-horas]', scope).forEach(function (btn) {
      if (btn._ahtHorasBound) return;
      btn._ahtHorasBound = true;
      btn.addEventListener('click', function () {
        avanzarHoras(parseInt(btn.getAttribute('data-horas'), 10));
      });
    });
  }
  bindLabHoras();
  const btnProx = $('#btn-proximo');
  if (btnProx) btnProx.addEventListener('click', irProximo);
  const btnProxLab = $('#btn-proximo-lab');
  if (btnProxLab) btnProxLab.addEventListener('click', irProximo);
  const btnCopy = $('[data-diag-copy]');
  if (btnCopy) {
    btnCopy.addEventListener('click', async function () {
      const pre = $('[data-playtest-diag-log]');
      const txt = pre ? pre.textContent : '';
      try {
        await navigator.clipboard.writeText(txt);
        toast('Registro copiado.');
      } catch (e) {
        toast('No se pudo copiar. Selecciona el texto a mano.');
      }
    });
  }
  const btnClear = $('[data-diag-clear-ui]');
  if (btnClear) {
    btnClear.addEventListener('click', function () {
      playtestLogClient.entries = [];
      pintarPlaytestDiag(cacheEstado && cacheEstado.playtest_diag);
      toast('Vista de cliente limpiada (el log del servidor sigue en la partida).');
    });
  }

  document.body.addEventListener('click', function (ev) {
    const t = ev.target.closest('[data-close], .velo');
    if (t && uiRootFrom(t)) {
      setCapa('');
      $('.play-root').removeAttribute('data-consulta');
      return;
    }
    const open = ev.target.closest('[data-open]');
    if (open && uiRootFrom(open)) {
      const name = open.getAttribute('data-open');
      setCapa(name);
      $('.play-root').removeAttribute('data-consulta');
      if (name === 'organizar') fillOrganizar();
      if (name === 'diario') $('[data-diario-tab="hoy"]').click();
      return;
    }
    const tab = ev.target.closest('[data-diario-tab]');
    if (tab) {
      $('.play-root').setAttribute('data-diario', tab.getAttribute('data-diario-tab'));
      $$('[data-diario-tab]').forEach(function (b) {
        b.classList.toggle('is-on', b === tab);
      });
      return;
    }
    const zona = ev.target.closest('.mapa-zona-hit[data-zona]');
    if (zona) {
      abrirConsultaZona(zona.getAttribute('data-zona'), zona);
      return;
    }
    const cx = ev.target.closest('.complejo[data-complejo]');
    if (cx) {
      abrirConsulta(cx.getAttribute('data-complejo'));
    }
  });

  const vecBuscaInp = $('[data-vec-busca]');
  if (vecBuscaInp) {
    vecBuscaInp.addEventListener('input', function () {
      vecBuscaTxt = vecBuscaInp.value;
      renderVecinos();
    });
  }

  const resBuscaInp = $('[data-res-busca]');
  if (resBuscaInp) {
    resBuscaInp.addEventListener('input', function () {
      resBuscaTxt = resBuscaInp.value;
    });
  }

  $('[data-org-a]').addEventListener('change', refreshTipos);
  $('[data-org-b]').addEventListener('change', refreshTipos);
  $('[data-org-go]').addEventListener('click', proponer);

  window.addEventListener('resize', layout);
  layout();
  const btnNuevaMesa = $('#btn-nueva-mesa');
  if (btnNuevaMesa) btnNuevaMesa.addEventListener('click', nuevaPartidaLimpia);
  const tutSkip = $('[data-tut-skip]');
  if (tutSkip) tutSkip.addEventListener('click', function () { cerrarTutIntro(true); });
  const tutSig = $('[data-tut-siguiente]');
  if (tutSig) tutSig.addEventListener('click', function () {
    if (tutIntroIdx >= TUT_PASOS.length - 1) cerrarTutIntro(true);
    else { tutIntroIdx++; pintarTutIntro(); }
  });
  const tutAtras = $('[data-tut-atras]');
  if (tutAtras) tutAtras.addEventListener('click', function () {
    if (tutIntroIdx > 0) { tutIntroIdx--; pintarTutIntro(); }
  });
  const tutReopen = $('[data-tut-reopen]');
  if (tutReopen) tutReopen.addEventListener('click', function () { abrirTutIntro(true); });

  initMapaCanonico().then(function () {
    return ensurePartida().then(function () {
      return refresh().then(function () { quizaMostrarTutIntro(); });
    });
  });
})();
