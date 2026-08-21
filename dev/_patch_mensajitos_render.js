  function renderBuzon(msgs) {
    cacheBuzon = msgs || [];
    const box = $('[data-buzon-list]');
    box.innerHTML = '';
    const cartas = cacheBuzon.filter(function (m) { return (m.canal || 'buzon') !== 'cotilleo'; });
    if (!cartas.length) {
      box.innerHTML = '<p class="lista-vacia">Nada por aquí. Celestine respira.</p>';
      return;
    }
    const accion = cartas.filter(mensajitoRequiereAccion);
    const info = cartas.filter(function (m) { return !mensajitoRequiereAccion(m); });

    function pintarCarta(m, esAccion) {
      const art = document.createElement('article');
      const st = estadoCarta(m);
      art.className = 'carta-msg' +
        (esAccion ? ' carta-accion' : ' carta-info') +
        ((m.estado || '') === 'pendiente' ? ' no-leida' : '') +
        (st.cls ? ' ' + st.cls : '');
      const rid = remitenteIdDe(m);
      const nombre = nombrePublicoDe(m);
      const cuerpo = cuerpoMensajito(m, nombre);
      const plazo = m.plazo_humano || '';
      const tok = rid && !m.candidato_catalog_id ? tokenDe(rid) : null;
      const avatar = tok
        ? '<img class="carta-avatar" src="' + esc(tok) + '" alt=""/>'
        : '<span class="carta-avatar cara-ini">' + esc(inicialDe(nombre)) + '</span>';
      const deLine = nombre ? '<div class="de">' + esc(nombre) + '</div>' : '';
      let accionesHtml = '';
      if (m.tipo === 'candidato_llegada' && (m.estado || '') === 'pendiente') {
        accionesHtml = '<div class="acciones-msg">' +
          '<button type="button" data-accion="aceptar">Dejarle hueco</button>' +
          '<button type="button" class="btn-suave" data-accion="rechazar">Ahora no</button>' +
          '</div>';
      }
      art.innerHTML = '<div class="carta-inner">' + avatar +
        '<div class="carta-copy">' +
        (st.txt ? '<div class="sello-estado">' + esc(st.txt) + '</div>' : '') +
        deLine +
        '<p class="cuerpo">' + esc(cuerpo) + '</p>' +
        (plazo ? '<p class="plazo">' + esc(plazo) + '</p>' : '') +
        accionesHtml +
        '</div></div>';

      art.querySelectorAll('[data-accion]').forEach(function (btn) {
        btn.addEventListener('click', async function (ev) {
          ev.stopPropagation();
          const acc = btn.getAttribute('data-accion');
          if (acc === 'aceptar') await api('llegada.aceptar', { mensaje_id: m.id });
          else if (acc === 'rechazar') await api('llegada.rechazar', { mensaje_id: m.id });
          await refresh();
        });
      });

      if (!accionesHtml && (m.estado || '') === 'pendiente') {
        art.addEventListener('click', async function () {
          const tr = await marcarMensajitoLeido(m);
          await refresh();
          if (tr) pintarTutorialMotor(tr);
        });
      }
      return art;
    }

    function pintarSeccion(titulo, items, esAccion) {
      if (!items.length) return;
      const sec = document.createElement('section');
      sec.className = 'mensajitos-seccion';
      sec.innerHTML = '<h3 class="mensajitos-seccion-tit">' + titulo + '</h3>';
      items.forEach(function (m) { sec.appendChild(pintarCarta(m, esAccion)); });
      box.appendChild(sec);
    }

    pintarSeccion('Piden algo', accion, true);
    pintarSeccion('Lo que circula', info, false);
  }