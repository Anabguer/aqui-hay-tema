(function () {
  'use strict';

  const API = 'api/index.php';
  const PLAYTEST = { config_id: 'playtest_01', seed: 'playtest-01' };
  const SLOTS = {
    cafe_libros: { lug_cafeteria: [[22, 52], [34, 66], [16, 70]], lug_biblioteca: [[60, 38], [70, 52]], lug_tienda_ropa: [[86, 46], [80, 62]] },
    rincon_lola: { lug_restaurante: [[30, 40], [50, 48]], lug_bingo: [[40, 78], [58, 82]] },
    cine_game: { lug_cine: [[22, 58], [34, 72], [18, 78]], lug_arcade: [[76, 42], [68, 58], [82, 68]] },
    mala_idea: { lug_bar: [[22, 40], [34, 54], [18, 62]], lug_discoteca: [[76, 26], [68, 40], [82, 48]], lug_karaoke: [[30, 80], [46, 84]] },
    parque: { lug_parque: [[48, 48], [36, 58]], lug_picnic: [[22, 78], [34, 84]], lug_mirador: [[78, 22], [70, 32]] },
    gimnasio_spa: { lug_gimnasio: [[28, 48], [40, 62]], lug_spa: [[76, 42], [70, 58]] }
  };

  let partidaId = localStorage.getItem('aht_partida_id');
  let cacheEstado = null;
  let cacheInsp = null;
  let cachePueblo = null;
  let cacheBuzon = [];
  let org = { tipo: 'quedar', a: '', b: '', lugar: '', dia: null, hora: 17 };

  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

  async function api(action, body, method) {
    body = body || {};
    method = method || 'POST';
    const opts = { method: method, headers: { 'Content-Type': 'application/json' } };
    if (method !== 'GET') {
      opts.body = JSON.stringify(Object.assign({ partida_id: partidaId }, body));
    }
    const qs = new URLSearchParams(Object.assign({ action: action, partida_id: partidaId || '' }, body));
    const url = method === 'GET' ? (API + '?' + qs.toString()) : (API + '?action=' + encodeURIComponent(action));
    return (await fetch(url, opts)).json();
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

  function renderHud(estado, buzon) {
    const rv = estado.reloj_vista || {};
    const reloj = estado.reloj || {};
    $('[data-dow]').textContent = ((rv.dia_semana_ui || '') + ' ' + (rv.fecha_corta || '')).trim() || ('Día ' + (reloj.dia_pueblo || '—'));
    const h = rv.hora !== undefined ? rv.hora : reloj.hora_actual;
    $('[data-hora]').textContent = h === undefined ? '—' : (String(h).padStart(2, '0') + ':00');
    $('[data-dinero]').textContent = dineroTxt(cacheInsp, estado);
    const vida = estado.vida_pueblo || null;
    const pct = vida && typeof vida.corazon_pct === 'number' ? vida.corazon_pct : 0;
    $('.corazon-dibujo').style.setProperty('--fill', pct + '%');
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
    const root = $('.play-root');
    (pueblo.complejos || []).forEach(function (cx) {
      const btn = $('[data-complejo="' + cx.id + '"]');
      if (!btn) return;
      btn.setAttribute('data-fase', cx.fase);
      const etiP = btn.querySelector('.eti-pleno');
      const etiT = btn.querySelector('.eti-temp');
      if (etiP && etiT) {
        etiP.style.display = cx.fase === 'pleno' ? '' : 'none';
        etiT.style.display = cx.fase === 'pleno' ? 'none' : '';
      }
      if (cx.fase === 'pleno') root.setAttribute('data-pueblo-' + cx.id, 'pleno');
      else root.removeAttribute('data-pueblo-' + cx.id);
      const box = btn.querySelector('.habs');
      box.innerHTML = '';
      const used = {};
      (cx.visibles || []).forEach(function (p) {
        const i = used[p.destino_id] || 0;
        used[p.destino_id] = i + 1;
        placeHab(box, p, i, cx.id);
      });
      if (cx.extra > 0) {
        const mas = document.createElement('button');
        mas.type = 'button';
        mas.className = 'aforo-mas';
        mas.textContent = '+' + cx.extra;
        mas.setAttribute('aria-label', 'Ver las ' + cx.total + ' personas de ' + cx.nombre);
        mas.addEventListener('click', function (ev) {
          ev.stopPropagation();
          abrirQuien(cx.id, null);
        });
        box.appendChild(mas);
      }
    });
    applyFases(pueblo);
  }

  function applyFases(pueblo) {
    $$('.complejo').forEach(function (el) {
      const id = el.getAttribute('data-complejo');
      const cx = (pueblo.complejos || []).filter(function (c) { return c.id === id; })[0];
      el.classList.toggle('is-pleno', !!(cx && cx.fase === 'pleno'));
    });
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
        b.textContent = 'Ver ' + d.nombre.toLowerCase();
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
      ? (gente.length + ' aquí · en el mapa se ven ' + Math.min(cx.total, 5) + (cx.extra ? ' y +' + cx.extra : ''))
      : 'Nadie ahora mismo.';
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
        li.textContent = p.nombre + (p.hay_tema ? ' · hay tema' : '') + (p.emocion && p.emocion !== 'neutro' ? ' · ' + p.emocion : '');
        ul.appendChild(li);
      });
      list.appendChild(ul);
    });
    const box = $('[data-q-btns]');
    box.innerHTML = '';
    (cx.destinos_operativos || []).forEach(function (d) {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = 'Organizar en ' + d.nombre.toLowerCase();
      b.addEventListener('click', function () {
        org.lugar = d.id;
        setCapa('organizar');
        $('.play-root').removeAttribute('data-consulta');
        fillOrganizar();
      });
      box.appendChild(b);
    });
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

  function renderVecinos() {
    const box = $('[data-vecinos-list]');
    box.innerHTML = '';
    const res = (cacheInsp && cacheInsp.residentes) || {};
    Object.keys(res).forEach(function (id) {
      const r = res[id];
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'vecino';
      const img = tokenDe(id);
      b.innerHTML = (img ? '<img src="' + img + '" alt=""/>' : '<span class="cara cara-ini"></span>') +
        '<span>' + ((r.identidad_publica && r.identidad_publica.nombre) || id) + '</span>';
      b.addEventListener('click', function () { abrirFicha(id); });
      box.appendChild(b);
    });
    if (!Object.keys(res).length) {
      box.innerHTML = '<p class="lista-vacia">Todavía no hay vecinos en esta partida.</p>';
    }
  }

  async function abrirFicha(id) {
    const r = await api('residente.ficha', { residente_id: id }, 'GET');
    if (!r.ok) return;
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

  function renderBuzon(msgs) {
    cacheBuzon = msgs || [];
    const box = $('[data-buzon-list]');
    box.innerHTML = '';
    const cartas = cacheBuzon.filter(function (m) { return (m.canal || 'buzon') !== 'cotilleo'; });
    if (!cartas.length) {
      box.innerHTML = '<p class="lista-vacia">Bandeja vacía. Te escribirán a ti.</p>';
      return;
    }
    cartas.forEach(function (m) {
      const art = document.createElement('article');
      art.className = 'carta-msg' + (m.clasificacion === 'importante' ? ' importante' : '') + ((m.estado || '') === 'pendiente' ? ' no-leida' : '');
      const de = nombreDe(m.de_persona);
      art.innerHTML = (m.clasificacion === 'importante' ? '<span class="lacre">OJO</span>' : '') +
        '<div><div class="sello">' + (m.estado || '') + '</div><div class="de">' + de + '</div>' +
        '<p class="cuerpo">' + (m.texto || '') + '</p></div>';
      art.addEventListener('click', async function () {
        if (m.id) await api('buzon.leer', { mensaje_id: m.id });
        await refresh();
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

  function fillSelect(sel, value) {
    sel.innerHTML = '<option value="">—</option>';
    idsResidentes().forEach(function (id) {
      const o = document.createElement('option');
      o.value = id;
      o.textContent = nombreDe(id);
      sel.appendChild(o);
    });
    if (value) sel.value = value;
  }

  function destinosOperativos() {
    const out = [];
    (cachePueblo && cachePueblo.complejos || []).forEach(function (c) {
      (c.destinos_operativos || []).forEach(function (d) { out.push(d); });
    });
    return out;
  }

  function fillOrganizar() {
    fillSelect($('[data-org-a]'), org.a);
    fillSelect($('[data-org-b]'), org.b);
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
      box.innerHTML = '<p class="mini">Elige dos vecinos. El motor dice qué planes existen entre ellos.</p>';
      return;
    }
    const r = await api('encuentro.tipos_permitidos', { participantes: [org.a, org.b] }, 'GET');
    box.innerHTML = '';
    (r.opciones || []).forEach(function (op) {
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
    if (!(r.opciones || []).length) {
      box.innerHTML = '<p class="mini">El motor no ofrece un plan entre estas dos personas ahora.</p>';
    }
  }

  async function proponer() {
    org.a = $('[data-org-a]').value;
    org.b = $('[data-org-b]').value;
    org.lugar = $('[data-org-lugar]').value;
    org.dia = parseInt($('[data-org-dia]').value, 10);
    org.hora = parseInt($('[data-org-hora]').value, 10);
    if (!org.a || !org.b || !org.lugar || !org.dia) {
      toast('Falta quién, dónde o cuándo.');
      return;
    }
    const r = await api('encuentro.proponer', {
      participantes: [org.a, org.b],
      dia: org.dia,
      hora: org.hora,
      tipo: org.tipo || 'quedar',
      lugar: org.lugar
    });
    if (r.ok) {
      toast('Propuesto. Ellas viven.');
      setCapa('');
      await refresh();
    } else {
      toast(r.mensaje_ui || r.error || 'El motor no ha aceptado el plan.');
    }
  }

  async function ensurePartida() {
    if (partidaId) {
      const r = await api('partida.cargar', { partida_id: partidaId });
      if (r.ok) return;
    }
    const r = await api('partida.nueva', PLAYTEST);
    if (r.ok) {
      partidaId = r.partida_id;
      localStorage.setItem('aht_partida_id', partidaId);
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
    renderPueblo(mapa.pueblo || { complejos: [] });
    renderBuzon(buzon.mensajes || []);
    renderCotilleo(diario.cotilleo || { hoy: diario.entradas || [], ayer: [], viejos: [] });
    renderVecinos();
    $('[data-taller-msg]').textContent = cacheEstado.reloj_texto || '';
  }

  document.body.addEventListener('click', function (ev) {
    const t = ev.target.closest('[data-close], .velo');
    if (t && t.closest('.play-root')) {
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
    }
    const tab = ev.target.closest('[data-diario-tab]');
    if (tab) {
      $('.play-root').setAttribute('data-diario', tab.getAttribute('data-diario-tab'));
      $$('[data-diario-tab]').forEach(function (b) {
        b.classList.toggle('is-on', b === tab);
      });
      return;
    }
    const cx = ev.target.closest('.complejo[data-complejo]');
    if (cx) {
      abrirConsulta(cx.getAttribute('data-complejo'));
    }
  });

  $('[data-org-a]').addEventListener('change', refreshTipos);
  $('[data-org-b]').addEventListener('change', refreshTipos);
  $('[data-org-go]').addEventListener('click', proponer);

  $('#btn-guardar').addEventListener('click', async function () {
    await api('partida.guardar', {});
    toast('Guardado.');
  });
  $('#btn-nueva').addEventListener('click', async function () {
    localStorage.removeItem('aht_partida_id');
    partidaId = null;
    await ensurePartida();
    await refresh();
  });
  $$('[data-horas]').forEach(function (b) {
    b.addEventListener('click', async function () {
      await api('reloj.avanzar', { horas: parseInt(b.getAttribute('data-horas'), 10), paso_a_paso: true });
      await refresh();
    });
  });
  $('#btn-proximo').addEventListener('click', async function () {
    const r = await api('reloj.proximo_encuentro', {});
    if (!r.ok) toast(r.mensaje_ui || 'No hay próximo encuentro.');
    await refresh();
  });

  window.addEventListener('resize', layout);
  layout();
  ensurePartida().then(refresh);
})();
