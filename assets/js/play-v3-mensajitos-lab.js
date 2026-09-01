/* ============================================================
   MENSAJITOS LAB — Piloto DS Modal + Body Mensajitos
   ITERACIÓN 2: identidad AHT + funciones reales
   Trigger: ?modal_catalog=1
   
   Lógica real reutilizada:
   - Tabs NUEVOS/TODOS (estado === 'pendiente')
   - Marcar todos (buzon.leer_todos)
   - Tint por hash de remitente (mensajitoTintDeRemitente)
   - Sistema de avatar (cara-ini con inicial + emoción)
   ============================================================ */

(function () {
  'use strict';

  /* === ALGORITMO REAL DE TINT (de play-v3.js) === */
  function tintDeRemitente(id) {
    var s = String(id || '');
    if (!s) return 0;
    var h = 0;
    for (var i = 0; i < s.length; i++) {
      h = ((h << 5) - h + s.charCodeAt(i)) | 0;
    }
    return (Math.abs(h) % 4);
  }

  function inicialDe(nombre) {
    if (typeof nombre !== 'string' || !nombre.length) return '?';
    return nombre.charAt(0).toUpperCase();
  }

  /* === DATOS REALISTAS === */
  var CASOS = {
    simple: {
      titulo: 'Mensajitos',
      cards: [
        {
          id: 'msg_simple_1',
          de: 'Nora', de_id: 'per_i03', emocion: 'alegre',
          texto: 'He visto que has quedado con Hugo el otro día. Me alegro, de verdad. A veces necesita alguien que le saque una sonrisa.',
          dia: 3, fecha: 'Día 3 — Tarde',
          estado: 'pendiente', tipo: 'info', clasificacion: 'cotilleo'
        },
        {
          id: 'msg_simple_2',
          de: 'Teo', de_id: 'per_i06', emocion: 'neutro',
          texto: 'Oye, si vas por el parque hoy, he dejado unas flores en la entrada para que las recojas. Son para el jardín de Lina.',
          dia: 2, fecha: 'Día 2 — Mañana',
          estado: 'leido', tipo: 'info', clasificacion: 'oportunidad'
        }
      ]
    },
    decision: {
      titulo: 'Mensajitos',
      cards: [
        {
          id: 'msg_decision_1',
          de: 'Hugo', de_id: 'per_i04', emocion: 'triste',
          texto: 'Estoy pensando en apuntarme al torneo de cartas del viernes, pero no sé si será un poco tonto. ¿Tú qué crees?',
          dia: 4, fecha: 'Día 4 — Mañana',
          estado: 'pendiente', tipo: 'accion', clasificacion: 'importante',
          requiere_decision: true,
          consejo_titulo: '¿Qué le sugieres?',
          opciones_consejo: [
            { id: 'animar', etiqueta: '¡Apúntate! Será divertido.' },
            { id: 'suavizar', etiqueta: 'Piénsalo un poco más.' },
            { id: 'ignorar', etiqueta: 'No responder por ahora.' }
          ]
        }
      ]
    },
    regalo: {
      titulo: 'Mensajitos',
      cards: [
        {
          id: 'msg_regalo_1',
          de: 'Carlita', de_id: 'per_i11', emocion: 'alegre',
          texto: 'He hecho una tarta de manzana extra y he pensado en ti. Aquí la tienes, que la disfrutes.',
          dia: 5, fecha: 'Día 5 — Tarde',
          estado: 'pendiente', tipo: 'accion', clasificacion: 'oportunidad',
          es_objeto_recibido: true,
          regalo: { icono: '🍰', nombre: 'Tarta de manzana', desc: 'Receta de la abuela Carlita' }
        }
      ]
    },
    peticion: {
      titulo: 'Mensajitos',
      cards: [
        {
          id: 'msg_peticion_1',
          de: 'Lina', de_id: 'per_i05', emocion: 'triste',
          texto: 'Necesito que alguien me presente a Dario. No me atrevo a hablarle directamente, ¡me pongo nerviosa! ¿Podrías organizar algo?',
          dia: 6, fecha: 'Día 6 — Mañana',
          estado: 'pendiente', tipo: 'accion', clasificacion: 'peticion',
          requiere_decision: true, plazo: 'Antes del día 10',
          selector_titulo: '¿Quién puede presentarle?',
          selector_opciones: [
            { personaje_id: 'per_i01', nombre: 'Marc', pista: 'Conoce a ambos desde hace tiempo' },
            { personaje_id: 'per_i02', nombre: 'Nora', pista: 'Siempre está metida en todo' },
            { personaje_id: 'per_i03', nombre: 'Teo', pista: 'Tranquilo, no genera presión' }
          ]
        }
      ]
    },
    hilo: {
      titulo: 'Mensajitos',
      cards: [
        {
          id: 'msg_hilo_1',
          de: 'Ariadna', de_id: 'per_i07', emocion: 'alegre',
          texto: '¡Gracias por lo de ayer! Dario se lo pasó genial. Dice que quiere repetir la próxima semana.',
          dia: 7, fecha: 'Día 7 — Tarde',
          estado: 'pendiente', tipo: 'info', clasificacion: 'importante',
          hilo: {
            titulo: 'Sobre lo que me contaste',
            texto: '¿Podrías presentarme a Dario? Creo que nos llevaríamos bien...',
            fecha: 'Día 5 — Mañana'
          }
        }
      ]
    },
    leido: {
      titulo: 'Mensajitos',
      cards: [
        {
          id: 'msg_noleido', de: 'Valentina', de_id: 'per_i09', emocion: 'alegre',
          texto: '¿Has visto el partido de ayer? Estaba todo el pueblo animando. Fue épico.',
          dia: 8, fecha: 'Día 8 — Noche',
          estado: 'pendiente', tipo: 'info', clasificacion: 'cotilleo'
        },
        {
          id: 'msg_leido', de: 'Rafa', de_id: 'per_i10', emocion: 'neutro',
          texto: 'Mañana organizo algo en el parque si te apetece. Avísame.',
          dia: 7, fecha: 'Día 7 — Tarde',
          estado: 'leido', tipo: 'info', clasificacion: 'oportunidad'
        },
        {
          id: 'msg_resuelto', de: 'Dario', de_id: 'per_i08', emocion: 'alegre',
          texto: 'La clase de ayer salió genial. Los alumnos estuvieron muy atentos.',
          dia: 6, fecha: 'Día 6 — Tarde',
          estado: 'resuelto', tipo: 'info', clasificacion: 'cotilleo'
        }
      ]
    },
    varios: {
      titulo: 'Mensajitos',
      cards: [
        {
          id: 'msg_v1', de: 'Marc', de_id: 'per_i01', emocion: 'alegre',
          texto: 'Oye, ¿te apuntas a cenar esta noche en el bar? Vamos unos cuantos.',
          dia: 9, fecha: 'Día 9 — Tarde',
          estado: 'pendiente', tipo: 'accion', clasificacion: 'oportunidad'
        },
        {
          id: 'msg_v2', de: 'Lina', de_id: 'per_i05', emocion: 'triste',
          texto: 'He visto que Dario no ha venido hoy. ¿Sabes si está bien?',
          dia: 9, fecha: 'Día 9 — Tarde',
          estado: 'pendiente', tipo: 'info', clasificacion: 'cotilleo'
        },
        {
          id: 'msg_v3', de: 'Nora', de_id: 'per_i03', emocion: 'neutro',
          texto: 'Si pasas por la biblioteca, devuélveme el libro que te presté. ¡Gracias!',
          dia: 9, fecha: 'Día 9 — Mañana',
          estado: 'pendiente', tipo: 'info', clasificacion: 'peticion'
        },
        {
          id: 'msg_v4', de: 'Hugo', de_id: 'per_i04', emocion: 'alegre',
          texto: '¡Al final me apunté al torneo! Te aviso de cómo va.',
          dia: 8, fecha: 'Día 8 — Noche',
          estado: 'leido', tipo: 'info', clasificacion: 'cotilleo'
        },
        {
          id: 'msg_v5', de: 'Teo', de_id: 'per_i06', emocion: 'neutro',
          texto: 'Las flores para Lina ya están en el jarrón. Quedaron preciosas.',
          dia: 7, fecha: 'Día 7 — Mañana',
          estado: 'resuelto', tipo: 'info', clasificacion: 'oportunidad'
        }
      ]
    }
  };

  /* === RENDER DE CARTA === */
  function renderCard(c) {
    var tint = tintDeRemitente(c.de_id || c.de);
    var leido = (c.estado || 'pendiente') !== 'pendiente';
    var attrs = [];
    attrs.push('data-tint="' + tint + '"');
    attrs.push('data-unread="' + (!leido) + '"');
    if (c.tipo === 'accion') attrs.push('data-action="true"');
    if (c.tipo === 'info') attrs.push('data-info="true"');
    if (c.hilo) attrs.push('data-thread="true"');
    if (c.es_objeto_recibido) attrs.push('data-gift="true"');
    if (c.estado === 'resuelto') attrs.push('data-resolved="true"');

    var html = '<article class="aht-msg-card" ' + attrs.join(' ') + '>';

    var flagClass = 'aht-msg-flag--new';
    var flagText = 'Nuevo';
    if (leido && c.estado !== 'resuelto') { flagClass = 'aht-msg-flag--read'; flagText = 'Visto'; }
    if (c.estado === 'resuelto') { flagClass = 'aht-msg-flag--resolved'; flagText = 'Resuelto'; }
    html += '<span class="aht-msg-flag ' + flagClass + '">' + flagText + '</span>';

    var emoEmoji = { alegre: '😊', neutro: '😐', triste: '😢', enfadado: '😠' }[c.emocion] || '😐';
    html += '<div class="aht-msg-avatar cara-ini" aria-hidden="true">';
    html += inicialDe(c.de);
    html += '<span class="emo-face emo-face--' + (c.emocion || 'neutro') + '">' + emoEmoji + '</span>';
    html += '</div>';

    html += '<div class="aht-msg-content">';
    html += '<div class="aht-msg-head">';
    html += '<p class="aht-msg-from">' + c.de + '</p>';
    html += '<span class="aht-msg-date">' + c.fecha + '</span>';
    html += '</div>';

    if (c.hilo) {
      html += '<div class="aht-msg-thread">';
      html += '<div class="aht-msg-thread-origin">';
      html += '<span class="aht-msg-thread-label">' + c.hilo.titulo + '</span>';
      html += '<p class="aht-msg-thread-text">' + c.hilo.texto + '</p>';
      html += '<span class="aht-msg-thread-date">' + c.hilo.fecha + '</span>';
      html += '</div>';
      html += '<div class="aht-msg-thread-arrow"></div>';
      html += '</div>';
    }

    html += '<p class="aht-msg-body">' + c.texto + '</p>';

    if (c.regalo) {
      html += '<div class="aht-msg-gift">';
      html += '<span class="aht-msg-gift-icon">' + c.regalo.icono + '</span>';
      html += '<div>';
      html += '<div class="aht-msg-gift-name">' + c.regalo.nombre + '</div>';
      html += '<div class="aht-msg-gift-desc">' + c.regalo.desc + '</div>';
      html += '</div>';
      html += '</div>';
    }

    if (c.plazo) {
      html += '<div class="aht-msg-deadline">⏰ ' + c.plazo + '</div>';
    }

    if (c.opciones_consejo) {
      html += '<div class="aht-msg-choice">';
      html += '<span class="aht-msg-choice-title">' + (c.consejo_titulo || '¿Qué opinas?') + '</span>';
      html += '<div class="aht-msg-choice-list">';
      for (var i = 0; i < c.opciones_consejo.length; i++) {
        var op = c.opciones_consejo[i];
        html += '<button type="button" class="aht-msg-choice-opt" data-action-id="' + op.id + '">';
        html += '<div class="aht-msg-choice-opt-copy">';
        html += '<span class="aht-msg-choice-opt-name">' + op.etiqueta + '</span>';
        html += '</div>';
        html += '<span class="aht-msg-choice-opt-arrow">›</span>';
        html += '</button>';
      }
      html += '</div></div>';
    }

    if (c.selector_opciones) {
      html += '<div class="aht-msg-choice">';
      html += '<span class="aht-msg-choice-title">' + (c.selector_titulo || '¿Quién?') + '</span>';
      html += '<div class="aht-msg-choice-list">';
      for (var j = 0; j < c.selector_opciones.length; j++) {
        var sel = c.selector_opciones[j];
        html += '<button type="button" class="aht-msg-choice-opt" data-action-id="elegir_persona" data-persona="' + sel.personaje_id + '">';
        html += '<div class="aht-msg-choice-opt-copy">';
        html += '<span class="aht-msg-choice-opt-name">' + sel.nombre + '</span>';
        html += '<span class="aht-msg-choice-opt-hint">' + sel.pista + '</span>';
        html += '</div>';
        html += '<span class="aht-msg-choice-opt-arrow">›</span>';
        html += '</button>';
      }
      html += '</div></div>';
    }

    if (c.requiere_decision && !c.opciones_consejo && !c.selector_opciones) {
      html += '<div class="aht-msg-actions">';
      html += '<button type="button" class="aht-msg-btn aht-msg-btn--primary">Aceptar</button>';
      html += '<button type="button" class="aht-msg-btn aht-msg-btn--soft">Dejarlo</button>';
      html += '</div>';
    }

    html += '<div style="margin-top:8px">';
    html += '<button type="button" class="aht-msg-read-toggle" data-read="' + leido + '">';
    html += leido ? '✓ Leído' : '○ No leído';
    html += '</button>';
    html += '</div>';

    html += '</div></article>';
    return html;
  }

  var currentFiltro = 'nuevos';
  var currentVariant = 'simple';

  function renderList() {
    var caso = CASOS[currentVariant];
    if (!caso) return;
    var list = document.getElementById('msgLabList');
    if (!list) return;

    var cartas = caso.cards.filter(function (c) {
      if (currentFiltro === 'nuevos') {
        return (c.estado || '') === 'pendiente';
      }
      return true;
    });

    if (!cartas.length) {
      list.innerHTML = '<div class="aht-msg-empty">' +
        '<div class="aht-msg-empty-icon">📭</div>' +
        '<p class="aht-msg-empty-text">No hay mensajes ' + (currentFiltro === 'nuevos' ? 'pendientes' : 'todavía') + '.</p>' +
        '</div>';
    } else {
      var html = '<div class="aht-msg-section">';
      for (var i = 0; i < cartas.length; i++) {
        html += renderCard(cartas[i]);
      }
      html += '</div>';
      list.innerHTML = html;
    }

    updateCount();
    updateMarkAll();
  }

  function updateCount() {
    var caso = CASOS[currentVariant];
    if (!caso) return;
    var countEl = document.getElementById('msgLabCount');
    if (!countEl) return;
    var n = caso.cards.filter(function (c) { return (c.estado || '') === 'pendiente'; }).length;
    countEl.textContent = String(n);
    countEl.hidden = n === 0;
  }

  function updateMarkAll() {
    var caso = CASOS[currentVariant];
    var btn = document.getElementById('msgLabMarkAll');
    if (!btn) return;
    if (currentFiltro !== 'nuevos') {
      btn.hidden = true;
      return;
    }
    var n = caso ? caso.cards.filter(function (c) { return (c.estado || '') === 'pendiente'; }).length : 0;
    btn.hidden = n === 0;
  }

  var overlay = document.getElementById('msgLabOverlay');
  var closeBtn = document.getElementById('msgLabClose');
  var labPanel = document.getElementById('msgLabPanel');
  var navZone = document.querySelector('.aht-msg-nav');

  if (!overlay) return;

  function openLab() {
    overlay.setAttribute('aria-hidden', 'false');
    if (labPanel) labPanel.hidden = false;
    renderList();
  }

  function closeLab() {
    overlay.setAttribute('aria-hidden', 'true');
  }

  window.ahtMsgLabOpen = openLab;
  window.ahtMsgLabClose = closeLab;

  if (closeBtn) closeBtn.addEventListener('click', closeLab);
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) closeLab();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay.getAttribute('aria-hidden') === 'false') {
      closeLab();
    }
  });

  if (navZone) {
    navZone.addEventListener('click', function (e) {
      var tab = e.target.closest('[data-msg-filtro]');
      if (tab) {
        var filtro = tab.getAttribute('data-msg-filtro');
        if (!filtro || filtro === currentFiltro) return;
        currentFiltro = filtro;
        navZone.querySelectorAll('.aht-msg-tab').forEach(function (t) {
          var on = t === tab;
          t.classList.toggle('is-on', on);
          t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        renderList();
        return;
      }
      var markBtn = e.target.closest('#msgLabMarkAll');
      if (markBtn) {
        marcarTodosLeidos();
      }
    });
  }

  function marcarTodosLeidos() {
    var caso = CASOS[currentVariant];
    if (!caso) return;
    caso.cards.forEach(function (c) {
      if ((c.estado || '') === 'pendiente') {
        c.estado = 'leido';
      }
    });
    renderList();
  }

  if (labPanel) {
    labPanel.addEventListener('click', function (e) {
      var tab = e.target.closest('[data-msg-lab]');
      if (!tab) return;
      var key = tab.getAttribute('data-msg-lab');
      if (!key || !CASOS[key]) return;
      currentVariant = key;
      currentFiltro = 'nuevos';

      labPanel.querySelectorAll('.aht-modal-lab-tab').forEach(function (t) {
        t.classList.remove('is-active');
      });
      tab.classList.add('is-active');

      if (navZone) {
        navZone.querySelectorAll('.aht-msg-tab').forEach(function (t) {
          var on = t.getAttribute('data-msg-filtro') === 'nuevos';
          t.classList.toggle('is-on', on);
          t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
      }

      renderList();
    });
  }

  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('.aht-msg-read-toggle');
    if (!toggle) return;
    var card = toggle.closest('.aht-msg-card');
    if (!card) return;
    var current = toggle.getAttribute('data-read') === 'true';
    toggle.setAttribute('data-read', String(!current));
    toggle.textContent = !current ? '✓ Leído' : '○ No leído';

    var from = card.querySelector('.aht-msg-from');
    if (from) {
      var caso = CASOS[currentVariant];
      if (caso) {
        caso.cards.forEach(function (c) {
          if (c.de === from.textContent && ((c.estado === 'pendiente') === current)) {
            c.estado = !current ? 'leido' : 'pendiente';
          }
        });
      }
    }

    card.setAttribute('data-unread', String(current));
    var flag = card.querySelector('.aht-msg-flag');
    if (flag) {
      flag.className = 'aht-msg-flag ' + (current ? 'aht-msg-flag--read' : 'aht-msg-flag--new');
      flag.textContent = current ? 'Visto' : 'Nuevo';
    }

    updateCount();
    updateMarkAll();
  });

  var params = new URLSearchParams(window.location.search);
  if (params.get('modal_catalog') === '1') {
    setTimeout(openLab, 80);
  }
})();
