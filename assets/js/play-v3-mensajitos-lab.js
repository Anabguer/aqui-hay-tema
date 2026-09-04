/* ============================================================
   MENSAJITOS LAB — Piloto DS Modal + Body Mensajitos
   ITERACIÓN 6: color por VECINO (autoridad = remitente)
   Trigger: ?modal_catalog=1

   Reglas:
   - Color por VECINO (de_id determinista, fallback a nombre normalizado).
     Mismo remitente = mismo color SIEMPRE.
   - El color NO representa: leído/no, emoción, tipo, hilo, estado.
   - Hilos: la autoridad es el REMITENTE, no el hilo_id.
   - Avatares y nombres son interactivos → abrir perfil
     (regla DS: CARA DE VECINO → FICHA DEL VECINO)
   - Tabs NUEVOS/TODOS con semántica real (estado === 'pendiente')
   - Marcar todos visible dentro de NUEVOS con pendientes
   ============================================================ */

(function () {
  'use strict';

  function inicialDe(nombre) {
    if (typeof nombre !== 'string' || !nombre.length) return '?';
    return nombre.charAt(0).toUpperCase();
  }

  /* 5 papeles pastel planos (sin gradient). */
  var PAPELES = ['rose', 'blue', 'green', 'cream', 'lilac'];

  /* Mapeo explícito de de_id → papel.
     Estabilidad TOTAL: mismo vecino = mismo papel, en cualquier
     bandeja, en cualquier orden, en cualquier variante del lab.
     Si el de_id no está en el mapa, fallback a hash FNV-1a. */
  var PAPER_POR_VECINO = {
    'per_i01': 'lilac',  /* Marc   */
    'per_i02': 'rose',   /* (pendiente) */
    'per_i03': 'rose',   /* Nora   */
    'per_i04': 'cream',  /* Hugo   */
    'per_i05': 'blue',   /* Lina   */
    'per_i06': 'green',  /* Teo    */
    'per_i07': 'cream',  /* Ariadna */
    'per_i08': 'green',  /* Dario  */
    'per_i09': 'blue',   /* Valentina */
    'per_i10': 'lilac',  /* Rafa   */
    'per_i11': 'rose'    /* Carlita */
  };

  /* Hash determinista FNV-1a para IDs no mapeados.
     Estable, mismo id → mismo papel siempre. */
  function fnv1a(s) {
    var x = 2166136261;
    for (var i = 0; i < s.length; i++) {
      x ^= s.charCodeAt(i);
      x = (x * 16777619) >>> 0;
    }
    return x;
  }

  /* Color por vecino: mapa explícito + hash fallback.
     El nombre se usa como fallback secundario si no hay de_id. */
  function paperParaRemitente(deId, nombre) {
    if (deId && PAPER_POR_VECINO[deId]) {
      return PAPER_POR_VECINO[deId];
    }
    var key = deId || ('name:' + (nombre || '').toLowerCase().trim());
    return PAPELES[fnv1a(String(key)) % PAPELES.length];
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
          fecha: 'Día 3 — Tarde',
          estado: 'pendiente', tipo: 'info'
        },
        {
          id: 'msg_simple_2',
          de: 'Teo', de_id: 'per_i06', emocion: 'neutro',
          texto: 'Oye, si vas por el parque hoy, he dejado unas flores en la entrada para que las recojas. Son para el jardín de Lina.',
          fecha: 'Día 2 — Mañana',
          estado: 'leido', tipo: 'info'
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
          fecha: 'Día 4 — Mañana',
          estado: 'pendiente', tipo: 'accion',
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
          fecha: 'Día 5 — Tarde',
          estado: 'pendiente', tipo: 'accion',
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
          fecha: 'Día 6 — Mañana',
          estado: 'pendiente', tipo: 'accion',
          requiere_decision: true, plazo: 'Antes del día 10',
          persona_mencionada: { id: 'per_i08', nombre: 'Dario' },
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
        /* Tres mensajes del mismo hilo (hilo_id='h_presentacion_dario').
           Deben compartir el mismo papel para mostrar la regla visual. */
        {
          id: 'msg_hilo_1',
          de: 'Ariadna', de_id: 'per_i07', emocion: 'alegre',
          texto: '¡Gracias por lo de ayer! Dario se lo pasó genial. Dice que quiere repetir la próxima semana.',
          fecha: 'Día 7 — Tarde',
          estado: 'pendiente', tipo: 'info',
          hilo_id: 'h_presentacion_dario',
          hilo: {
            titulo: 'Sobre lo que me contaste',
            texto: '¿Podrías presentarme a Dario? Creo que nos llevaríamos bien...',
            fecha: 'Día 5 — Mañana'
          }
        },
        {
          id: 'msg_hilo_2',
          de: 'Dario', de_id: 'per_i08', emocion: 'alegre',
          texto: 'Oye, ¿al final conocí a Ariadna gracias a ti? Me ha caído genial, sí.',
          fecha: 'Día 8 — Tarde',
          estado: 'leido', tipo: 'info',
          hilo_id: 'h_presentacion_dario'
        },
        {
          id: 'msg_hilo_3',
          de: 'Ariadna', de_id: 'per_i07', emocion: 'alegre',
          texto: '¡Confirmado! El jueves quedamos para merendar. Esto gracias a ti.',
          fecha: 'Día 9 — Mañana',
          estado: 'pendiente', tipo: 'info',
          hilo_id: 'h_presentacion_dario'
        }
      ]
    },
    leido: {
      titulo: 'Mensajitos',
      cards: [
        {
          id: 'msg_noleido', de: 'Valentina', de_id: 'per_i09', emocion: 'alegre',
          texto: '¿Has visto el partido de ayer? Estaba todo el pueblo animando. Fue épico.',
          fecha: 'Día 8 — Noche',
          estado: 'pendiente', tipo: 'info'
        },
        {
          id: 'msg_leido', de: 'Rafa', de_id: 'per_i10', emocion: 'neutro',
          texto: 'Mañana organizo algo en el parque si te apetece. Avísame.',
          fecha: 'Día 7 — Tarde',
          estado: 'leido', tipo: 'info'
        },
        {
          id: 'msg_resuelto', de: 'Dario', de_id: 'per_i08', emocion: 'alegre',
          texto: 'La clase de ayer salió genial. Los alumnos estuvieron muy atentos.',
          fecha: 'Día 6 — Tarde',
          estado: 'resuelto', tipo: 'info'
        }
      ]
    },
    varios: {
      titulo: 'Mensajitos',
      cards: [
        {
          id: 'msg_v1', de: 'Marc', de_id: 'per_i01', emocion: 'alegre',
          texto: 'Oye, ¿te apuntas a cenar esta noche en el bar? Vamos unos cuantos.',
          fecha: 'Día 9 — Tarde',
          estado: 'pendiente', tipo: 'accion'
        },
        {
          id: 'msg_v2', de: 'Lina', de_id: 'per_i05', emocion: 'triste',
          texto: 'He visto que Dario no ha venido hoy. ¿Sabes si está bien?',
          fecha: 'Día 9 — Tarde',
          estado: 'pendiente', tipo: 'info',
          persona_mencionada: { id: 'per_i08', nombre: 'Dario' }
        },
        {
          id: 'msg_v3', de: 'Nora', de_id: 'per_i03', emocion: 'neutro',
          texto: 'Si pasas por la biblioteca, devuélveme el libro que te presté. ¡Gracias!',
          fecha: 'Día 9 — Mañana',
          estado: 'pendiente', tipo: 'info'
        },
        {
          id: 'msg_v4', de: 'Hugo', de_id: 'per_i04', emocion: 'alegre',
          texto: '¡Al final me apunté al torneo! Te aviso de cómo va.',
          fecha: 'Día 8 — Noche',
          estado: 'leido', tipo: 'info'
        },
        {
          id: 'msg_v5', de: 'Teo', de_id: 'per_i06', emocion: 'neutro',
          texto: 'Las flores para Lina ya están en el jarrón. Quedaron preciosas.',
          fecha: 'Día 7 — Mañana',
          estado: 'resuelto', tipo: 'info',
          persona_mencionada: { id: 'per_i05', nombre: 'Lina' }
        },
        /* Iter 6: 2+ mensajes del mismo vecino para validar la regla
           "mismo de_id = mismo papel" visualmente. */
        {
          id: 'msg_v6', de: 'Marc', de_id: 'per_i01', emocion: 'neutro',
          texto: 'Recuérdame lo del libro de Nora. Se lo devolveré mañana.',
          fecha: 'Día 8 — Tarde',
          estado: 'leido', tipo: 'info'
        },
        {
          id: 'msg_v7', de: 'Marc', de_id: 'per_i01', emocion: 'alegre',
          texto: '¡Mira qué buena combinación de cafés! Te paso la lista.',
          fecha: 'Día 7 — Noche',
          estado: 'pendiente', tipo: 'oportunidad', clasificacion: 'oportunidad'
        },
        {
          id: 'msg_v8', de: 'Lina', de_id: 'per_i05', emocion: 'alegre',
          texto: 'Ya he hablado con Dario. Gracias por presentarme.',
          fecha: 'Día 6 — Tarde',
          estado: 'resuelto', tipo: 'info'
        },
        /* Iter 6: SEGUNDO mensaje de Nora en esta variante para
           validar visualmente "mismo de_id = mismo papel" en una
           sola vista con 5 vecinos distintos. */
        {
          id: 'msg_v9', de: 'Nora', de_id: 'per_i03', emocion: 'alegre',
          texto: 'Cuando pases por la plaza, te dejo un detalle en la fuente.',
          fecha: 'Día 9 — Tarde',
          estado: 'pendiente', tipo: 'oportunidad'
        }
      ]
    },
    /* === Iter 6: PRUEBA VISUAL de identidad cromática ===
       2 mensajes de Nora + 2 de Marc + 1 de Lina + 1 de Teo
       → 4 vecinos con colores estables, 2 con mismo color que otro. */
    identidad: {
      titulo: 'Mensajitos',
      cards: [
        { id: 'id_1', de: 'Nora',  de_id: 'per_i03', emocion: 'alegre',
          texto: '¿Te has enterado de lo de ayer en el Café?',
          fecha: 'Día 9 — Tarde', estado: 'pendiente', tipo: 'info' },
        { id: 'id_2', de: 'Marc',  de_id: 'per_i01', emocion: 'neutro',
          texto: 'Voy a necesitar tu ayuda con la mudanza del sábado.',
          fecha: 'Día 9 — Tarde', estado: 'pendiente', tipo: 'accion' },
        { id: 'id_3', de: 'Lina',  de_id: 'per_i05', emocion: 'triste',
          texto: 'No he visto a Dario en todo el día. ¿Está bien?',
          fecha: 'Día 9 — Mañana', estado: 'pendiente', tipo: 'info' },
        { id: 'id_4', de: 'Nora',  de_id: 'per_i03', emocion: 'alegre',
          texto: 'El libro que me prestaste está en mi mesa, gracias.',
          fecha: 'Día 8 — Tarde', estado: 'leido', tipo: 'info' },
        { id: 'id_5', de: 'Teo',   de_id: 'per_i06', emocion: 'neutro',
          texto: '¿Pasas por el huerto? Hay verdura de sobra.',
          fecha: 'Día 8 — Mañana', estado: 'pendiente', tipo: 'oportunidad' },
        { id: 'id_6', de: 'Marc',  de_id: 'per_i01', emocion: 'alegre',
          texto: 'Confirmado el jueves a las 8. Te paso la lista.',
          fecha: 'Día 7 — Noche', estado: 'leido', tipo: 'accion' }
      ]
    }
  };

  /* === AVATAR HTML === */
  function avatarHTML(nombre, id, emocion, mini) {
    var emoEmoji = { alegre: '😊', neutro: '😐', triste: '😢', enfadado: '😠' }[emocion] || '😐';
    var cls = 'aht-msg-avatar cara-ini' + (mini ? ' aht-msg-avatar--mini' : '');
    return '<div class="' + cls + '" ' +
           'data-persona-id="' + (id || '') + '" ' +
           'data-persona-nombre="' + nombre + '" ' +
           'role="button" tabindex="0" ' +
           'aria-label="Abrir perfil de ' + nombre + '">' +
           inicialDe(nombre) +
           '<span class="emo-face emo-face--' + (emocion || 'neutro') + '">' + emoEmoji + '</span>' +
           '</div>';
  }

  /* === NAVEGACIÓN A PERFIL — regla DS === */
  function abrirPerfil(personaId, nombre) {
    /* En el lab: log + intento de integración con sistema real.
       En producción: window.AHT.residenteOpen(personaId) o equivalente. */
    if (window.AHT && typeof window.AHT.residenteOpen === 'function') {
      window.AHT.residenteOpen(personaId);
    } else {
      console.log('[AHT-LAB] Abrir perfil de vecino:', personaId || nombre);
    }
  }

  /* === RENDER DE CARTA === */
  function renderCard(c, idx) {
    var leido = (c.estado || 'pendiente') !== 'pendiente';
    /* Iter 6: el color pertenece al VECINO.
       Mismo de_id (o nombre fallback) → mismo papel SIEMPRE. */
    var paper = paperParaRemitente(c.de_id, c.de);
    var attrs = [];
    attrs.push('data-paper="' + paper + '"');
    attrs.push('data-unread="' + (!leido) + '"');
    if (c.tipo === 'accion') attrs.push('data-action="true"');
    if (c.tipo === 'info') attrs.push('data-info="true"');
    if (c.hilo) attrs.push('data-thread="true"');
    if (c.es_objeto_recibido) attrs.push('data-gift="true"');
    if (c.estado === 'resuelto') attrs.push('data-resolved="true"');

    var html = '<article class="aht-msg-card" ' + attrs.join(' ') + '>';

    /* AVATAR (grid-area: avatar) */
    html += avatarHTML(c.de, c.de_id, c.emocion, false);

    /* HEADER (grid-area: header) — nombre + badge */
    var flagClass = 'aht-msg-flag--new';
    var flagText = 'Nuevo';
    if (leido && c.estado !== 'resuelto') { flagClass = 'aht-msg-flag--read'; flagText = 'Visto'; }
    if (c.estado === 'resuelto') { flagClass = 'aht-msg-flag--resolved'; flagText = 'Resuelto'; }

    html += '<div class="aht-msg-header-row">';
    html += '<p class="aht-msg-from" data-persona-id="' + (c.de_id || '') + '" data-persona-nombre="' + c.de + '" role="button" tabindex="0">' + c.de + '</p>';
    html += '<span class="aht-msg-flag ' + flagClass + '">' + flagText + '</span>';
    html += '</div>';

    /* STATUS ROW (grid-area: status) — fecha + leído */
    html += '<div class="aht-msg-status-row">';
    html += '<span class="aht-msg-date">' + c.fecha + '</span>';
    html += '<span class="aht-msg-status">';
    html += '<button type="button" class="aht-msg-read-toggle" data-read="' + leido + '">';
    html += leido ? '✓ Leído' : '○ No leído';
    html += '</button>';
    html += '</span>';
    html += '</div>';

    /* CONTENT (grid-area: content) */
    html += '<div class="aht-msg-content">';

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

    /* Persona mencionada en peticiones (avatar + nombre) */
    if (c.persona_mencionada) {
      var pm = c.persona_mencionada;
      html += '<div class="aht-msg-person">';
      html += avatarHTML(pm.nombre, pm.id, 'neutro', true);
      html += '<span>Sobre <span class="aht-msg-person-name" data-persona-id="' + (pm.id || '') + '" data-persona-nombre="' + pm.nombre + '" role="button" tabindex="0">' + pm.nombre + '</span></span>';
      html += '</div>';
    }

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

    html += '</div></article>'; /* /aht-msg-content /article */
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
        html += renderCard(cartas[i], i);
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

      /* Reset del mapa de hilos al cambiar de variante:
         asegura que la asignación hilo→color sea estable por variante */
      hiloColorMap = {};

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

  /* === TOGGLE LEÍDO === */
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
      if (current) {
        flag.className = 'aht-msg-flag aht-msg-flag--read';
        flag.textContent = 'Visto';
      } else {
        flag.className = 'aht-msg-flag aht-msg-flag--new';
        flag.textContent = 'Nuevo';
      }
    }

    updateCount();
    updateMarkAll();
  });

  /* === AVATARES Y NOMBRES → PERFIL ===
     Regla DS: CARA DE VECINO → FICHA DEL VECINO */
  document.addEventListener('click', function (e) {
    var target = e.target.closest('[data-persona-id], .aht-msg-avatar, .aht-msg-from, .aht-msg-person-name');
    if (!target) return;
    var pid = target.getAttribute('data-persona-id');
    var pnom = target.getAttribute('data-persona-nombre') || '';
    if (!pid && !pnom) return;
    /* No interceptar clicks en opciones/botones dentro de cards */
    if (e.target.closest('.aht-msg-choice-opt, .aht-msg-btn, .aht-msg-read-toggle')) return;
    abrirPerfil(pid, pnom);
  });

  /* Teclado: Enter/Space en avatares/nombres */
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    var target = e.target.closest('.aht-msg-avatar, .aht-msg-from, .aht-msg-person-name');
    if (!target) return;
    e.preventDefault();
    var pid = target.getAttribute('data-persona-id');
    var pnom = target.getAttribute('data-persona-nombre') || '';
    abrirPerfil(pid, pnom);
  });

  var params = new URLSearchParams(window.location.search);
  if (params.get('modal_catalog') === '1') {
    setTimeout(openLab, 80);
  }
})();
