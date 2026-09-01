/* ============================================================
   MENSAJITOS LAB — Piloto DS Modal + Body Mensajitos
   Datos realistas basados en la estructura real de AHT.
   Trigger: ?modal_catalog=1
   ============================================================ */

(function () {
  'use strict';

  /* === DATOS DE EJEMPLO (basados en estructura real de BuzonEngine) === */
  var AVATARS = {
    celestine: '👩‍🦰', marc: '🧑‍🍳', nora: '👩‍🎨', hugo: '🧔',
    lina: '👩‍⚕️', teo: '🧑‍🌾', ariadna: '👩‍💼', dario: '🧑‍🏫',
    valentina: '👩‍🔧', rafa: '🧔‍♀️', carlita: '👩‍🍳', luna: '🧑‍🎨'
  };

  var CASOS = {
    /* A. MENSAJE SIMPLE — NPC escribe al jugador */
    simple: {
      titulo: 'Mensajitos',
      cards: [
        {
          id: 'msg_simple_1',
          de: 'Nora',
          avatar: AVATARS.nora,
          texto: 'He visto que has quedado con Hugo el otro día. Me alegro, de verdad. A veces necesita alguien que le saque una sonrisa.',
          dia: 3,
          fecha: 'Día 3 — Tarde',
          leido: false,
          tipo: 'info',
          clasificacion: 'cotilleo'
        },
        {
          id: 'msg_simple_2',
          de: 'Teo',
          avatar: AVATARS.teo,
          texto: 'Oye, si vas por el parque hoy, he dejado unas flores en la entrada para que las recojas. Son para el jardín de Lina.',
          dia: 2,
          fecha: 'Día 2 — Mañana',
          leido: true,
          tipo: 'info',
          clasificacion: 'oportunidad'
        }
      ]
    },

    /* B. MENSAJE CON DECISIÓN — NPC pide opinión o elección */
    decision: {
      titulo: 'Mensajitos',
      cards: [
        {
          id: 'msg_decision_1',
          de: 'Hugo',
          avatar: AVATARS.hugo,
          texto: 'Estoy pensando en apuntarme al torneo de cartas del viernes, pero no sé si será un poco tonto. ¿Tú qué crees?',
          dia: 4,
          fecha: 'Día 4 — Mañana',
          leido: false,
          tipo: 'accion',
          clasificacion: 'importante',
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

    /* C. MENSAJE DE REGALO — NPC entrega algo */
    regalo: {
      titulo: 'Mensajitos',
      cards: [
        {
          id: 'msg_regalo_1',
          de: 'Carlita',
          avatar: AVATARS.carlita,
          texto: 'He hecho una tarta de manzana extra y he pensado en ti. Aquí la tienes, que la disfrutes.',
          dia: 5,
          fecha: 'Día 5 — Tarde',
          leido: false,
          tipo: 'accion',
          clasificacion: 'oportunidad',
          es_objeto_recibido: true,
          regalo: {
            icono: '🍰',
            nombre: 'Tarta de manzana',
            desc: 'Receta de la abuela Carlita'
          }
        }
      ]
    },

    /* D. MENSAJE CON PETICIÓN — NPC necesita algo del jugador */
    peticion: {
      titulo: 'Mensajitos',
      cards: [
        {
          id: 'msg_peticion_1',
          de: 'Lina',
          avatar: AVATARS.lina,
          texto: 'Necesito que alguien me presente a Dario. No me atrevo a hablarle directamente, ¡me pongo nerviosa! ¿Podrías organizar algo?',
          dia: 6,
          fecha: 'Día 6 — Mañana',
          leido: false,
          tipo: 'accion',
          clasificacion: 'peticion',
          requiere_decision: true,
          plazo: 'Antes del día 10',
          selector_titulo: '¿Quién puede presentarle?',
          selector_opciones: [
            { personaje_id: 'per_i01', nombre: 'Marc', pista: 'Conoce a ambos desde hace tiempo' },
            { personaje_id: 'per_i02', nombre: 'Nora', pista: 'Siempre está metida en todo' },
            { personaje_id: 'per_i03', nombre: 'Teo', pista: 'Tranquilo, no genera presión' }
          ]
        }
      ]
    },

    /* E. MENSAJE EN HILO — respuesta a algo que el jugador hizo */
    hilo: {
      titulo: 'Mensajitos',
      cards: [
        {
          id: 'msg_hilo_1',
          de: 'Ariadna',
          avatar: AVATARS.ariadna,
          texto: '¡Gracias por lo de ayer! Dario se lo pasó genial. Dice que quiere repetir la próxima semana.',
          dia: 7,
          fecha: 'Día 7 — Tarde',
          leido: false,
          tipo: 'info',
          clasificacion: 'importante',
          hilo: {
            titulo: 'Sobre lo que me contaste',
            texto: '¿Podrías presentarme a Dario? Creo que nos llevaríamos bien...',
            fecha: 'Día 5 — Mañana'
          }
        }
      ]
    },

    /* F. LEÍDO / NO LEÍDO — diferencia de estado */
    leido: {
      titulo: 'Mensajitos',
      cards: [
        {
          id: 'msg_noleido',
          de: 'Valentina',
          avatar: AVATARS.valentina,
          texto: '¿Has visto el partido de ayer? Estaba todo el pueblo animando. Fue épico.',
          dia: 8,
          fecha: 'Día 8 — Noche',
          leido: false,
          tipo: 'info',
          clasificacion: 'cotilleo'
        },
        {
          id: 'msg_leido',
          de: 'Rafa',
          avatar: AVATARS.rafa,
          texto: 'Mañana organizo algo en el parque si te apetece. Avísame.',
          dia: 7,
          fecha: 'Día 7 — Tarde',
          leido: true,
          tipo: 'info',
          clasificacion: 'oportunidad'
        },
        {
          id: 'msg_resuelto',
          de: 'Dario',
          avatar: AVATARS.dario,
          texto: 'La clase de ayer salió genial. Los alumnos estuvieron muy atentos.',
          dia: 6,
          fecha: 'Día 6 — Tarde',
          leido: true,
          tipo: 'info',
          clasificacion: 'cotilleo',
          estado: 'resuelto'
        }
      ]
    }
  };

  /* === RENDER DE CARTAS === */
  function renderCard(c) {
    var attrs = [];
    attrs.push('data-unread="' + (!c.leido) + '"');
    if (c.tipo === 'accion') attrs.push('data-action="true"');
    if (c.tipo === 'info') attrs.push('data-info="true"');
    if (c.hilo) attrs.push('data-thread="true"');
    if (c.es_objeto_recibido) attrs.push('data-gift="true"');

    var html = '<article class="aht-msg-card" ' + attrs.join(' ') + '>';

    /* Badge estado */
    html += '<div class="aht-msg-badge">';
    if (!c.leido) {
      html += '<span class="aht-msg-badge--new">Nuevo</span>';
    } else if (c.estado === 'resuelto') {
      html += '<span class="aht-msg-badge--state">Resuelto</span>';
    } else {
      html += '<span class="aht-msg-badge--read">Visto</span>';
    }
    html += '</div>';

    /* Avatar */
    html += '<div class="aht-msg-avatar">' + c.avatar + '</div>';

    /* Contenido */
    html += '<div class="aht-msg-content">';
    html += '<p class="aht-msg-from">' + c.de + '</p>';
    html += '<p class="aht-msg-date">' + c.fecha + '</p>';

    /* Hilo */
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

    /* Cuerpo */
    html += '<p class="aht-msg-body">' + c.texto + '</p>';

    /* Regalo */
    if (c.regalo) {
      html += '<div class="aht-msg-gift">';
      html += '<span class="aht-msg-gift-icon">' + c.regalo.icono + '</span>';
      html += '<div>';
      html += '<div class="aht-msg-gift-name">' + c.regalo.nombre + '</div>';
      html += '<div class="aht-msg-gift-desc">' + c.regalo.desc + '</div>';
      html += '</div>';
      html += '</div>';
    }

    /* Plazo */
    if (c.plazo) {
      html += '<div class="aht-msg-deadline">⏰ ' + c.plazo + '</div>';
    }

    /* Decision: consejo */
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
      html += '</div>';
      html += '</div>';
    }

    /* Decision: selector de persona */
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
      html += '</div>';
      html += '</div>';
    }

    /* Acciones simples */
    if (c.requiere_decision && !c.opciones_consejo && !c.selector_opciones) {
      html += '<div class="aht-msg-actions">';
      html += '<button type="button" class="aht-msg-btn aht-msg-btn--primary">Aceptar</button>';
      html += '<button type="button" class="aht-msg-btn aht-msg-btn--soft">Dejarlo</button>';
      html += '</div>';
    }

    /* Toggle leído */
    html += '<div style="margin-top:8px">';
    html += '<button type="button" class="aht-msg-read-toggle" data-read="' + c.leido + '">';
    html += c.leido ? '✓ Leído' : '○ No leído';
    html += '</button>';
    html += '</div>';

    html += '</div>'; /* /aht-msg-content */
    html += '</article>';
    return html;
  }

  function renderVariant(key) {
    var caso = CASOS[key];
    if (!caso) return;
    var list = document.getElementById('msgLabList');
    if (!list) return;

    var html = '<div class="aht-msg-section">';
    html += '<h3 class="aht-msg-section-title">Mensajes</h3>';
    for (var i = 0; i < caso.cards.length; i++) {
      html += renderCard(caso.cards[i]);
    }
    html += '</div>';
    list.innerHTML = html;
  }

  /* === APERTURA / CIERRE === */
  var overlay = document.getElementById('msgLabOverlay');
  var closeBtn = document.getElementById('msgLabClose');
  var labPanel = document.getElementById('msgLabPanel');

  if (!overlay) return;

  function openLab() {
    overlay.setAttribute('aria-hidden', 'false');
    if (labPanel) labPanel.hidden = false;
    renderVariant('simple');
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

  /* === LAB TABS === */
  if (labPanel) {
    labPanel.addEventListener('click', function (e) {
      var tab = e.target.closest('[data-msg-lab]');
      if (!tab) return;
      var key = tab.getAttribute('data-msg-lab');
      if (!key) return;

      /* Activar tab */
      labPanel.querySelectorAll('.aht-modal-lab-tab').forEach(function (t) {
        t.classList.remove('is-active');
      });
      tab.classList.add('is-active');

      /* Render variante */
      renderVariant(key);
    });
  }

  /* === TOGGLE LEÍDO (delegación) === */
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('.aht-msg-read-toggle');
    if (!toggle) return;
    var current = toggle.getAttribute('data-read') === 'true';
    toggle.setAttribute('data-read', String(!current));
    toggle.textContent = !current ? '✓ Leído' : '○ No leído';

    /* Actualizar badge */
    var card = toggle.closest('.aht-msg-card');
    if (card) {
      card.setAttribute('data-unread', String(current));
      var badge = card.querySelector('.aht-msg-badge');
      if (badge) {
        badge.innerHTML = !current
          ? '<span class="aht-msg-badge--new">Nuevo</span>'
          : '<span class="aht-msg-badge--read">Visto</span>';
      }
    }
  });

  /* === TRIGGER: ?modal_catalog=1 === */
  var params = new URLSearchParams(window.location.search);
  if (params.get('modal_catalog') === '1') {
    setTimeout(openLab, 80);
  }
})();
