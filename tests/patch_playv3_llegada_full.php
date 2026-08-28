<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$jsPath = $root . '/assets/js/play-v3.js';
$cssPath = $root . '/assets/css/play-v3-organizar.css';
$busterPath = $root . '/assets/aht-cache-buster.txt';

$js = file_get_contents($jsPath);
if (strpos($js, 'data-llegada-acomp') !== false) {
    echo "play-v3.js already patched\n";
} else {
    $perfilFn = <<<'JS'

  function htmlPerfilCandidato(m) {
    const p = m && m.perfil_candidato;
    if (!p || typeof p !== 'object' || !p.nombre) return '';
    const retrato = p.retrato_url
      ? '<img class="llegada-perfil-foto" src="' + esc(p.retrato_url) + '" alt=""/>'
      : '<span class="llegada-perfil-foto llegada-perfil-foto--ini">' + esc(inicialDe(p.nombre)) + '</span>';
    const chips = [];
    if (p.edad) chips.push('<span class="llegada-perfil-chip">' + esc(String(p.edad)) + ' a\u00f1os</span>');
    if (p.hobby_visible) chips.push('<span class="llegada-perfil-chip">' + esc(p.hobby_visible) + '</span>');
    (p.rasgos_visibles || []).forEach(function (r) {
      if (r) chips.push('<span class="llegada-perfil-chip">' + esc(r) + '</span>');
    });
    return '<div class="llegada-perfil" data-llegada-perfil="1">' + retrato +
      '<div class="llegada-perfil-body">' +
      '<div class="llegada-perfil-nom">' + esc(p.nombre) + ' <span class="llegada-perfil-tag">Nuevo candidato</span></div>' +
      (p.presentacion ? '<p class="llegada-perfil-txt">' + esc(p.presentacion) + '</p>' : '') +
      (chips.length ? '<div class="llegada-perfil-chips">' + chips.join('') + '</div>' : '') +
      '</div></div>';
  }

  let llegadaCelebradaKey = null;

  function pintarLlegadaCelebracionSiToca() {
    const insp = cacheInsp || {};
    const llegadas = insp.llegadas || {};
    const pres = llegadas.presentacion && llegadas.presentacion.ultima;
    if (!pres || !pres.residente_id) return;
    const key = String(pres.residente_id) + '|' + String(pres.dia || 0) + '|' + String(pres.hora || 0);
    if (llegadaCelebradaKey === key) return;
    try {
      if (sessionStorage.getItem('aht_llegada_celebra') === key) {
        llegadaCelebradaKey = key;
        return;
      }
    } catch (e) {}
    llegadaCelebradaKey = key;
    try { sessionStorage.setItem('aht_llegada_celebra', key); } catch (e) {}
    const nom = pres.nombre || nombreDe(pres.residente_id) || 'Un nuevo vecino';
    let txt = nom + ' se ha incorporado al pueblo.';
    if (pres.acompanante_id) {
      const nomA = nombreDe(pres.acompanante_id);
      if (nomA) txt += ' ' + nomA + ' le ense\u00f1a Villaborde.';
    }
    toast(txt);
  }

JS;

    $needle = '  function htmlAccionesMensajito(m) {';
    if (strpos($js, $needle) === false) {
        fwrite(STDERR, "htmlAccionesMensajito anchor missing\n");
        exit(1);
    }
    $js = str_replace($needle, $perfilFn . $needle, $js);

    $llegadaAcciones = <<<'JS'
    if (m.tipo === 'candidato_llegada' &&
        (m.estado || '') === 'pendiente' &&
        (m.estado_decision || '') !== 'resuelto') {
      const opts = Array.isArray(m.acompanantes_opciones) ? m.acompanantes_opciones : [];
      const titA = m.selector_titulo_acompanante || '\u00bfQui\u00e9n le ense\u00f1a Villaborde?';
      const optsHtml = opts.map(function (o) {
        if (!o || !o.personaje_id) return '';
        const pista = o.pista ? '<span class="msg-opcion-pista">' + esc(o.pista) + '</span>' : '';
        return '<button type="button" class="carta-cta carta-cta--suave msg-opcion llegada-acomp-opt" data-llegada-acomp="' + esc(o.personaje_id) + '">' +
          '<span class="msg-opcion-nom">' + esc(o.nombre || '') + '</span>' + pista + '</button>';
      }).join('');
      return '<div class="acciones-msg msg-opciones llegada-acciones">' +
        '<span class="msg-opciones-tit">' + esc(titA) + '</span>' +
        (optsHtml || '<p class="llegada-sin-acomp">Nadie libre ahora para la bienvenida.</p>') +
        '<div class="llegada-acciones-pie">' +
        '<button type="button" class="carta-cta carta-cta--suave" data-llegada-rechazar="1">Ahora no</button>' +
        '</div></div>';
    }

JS;

    $anchor2 = '    const acciones = Array.isArray(m.acciones_ui) ? m.acciones_ui : [];';
    if (strpos($js, $anchor2) === false) {
        fwrite(STDERR, "acciones_ui anchor missing\n");
        exit(1);
    }
    $js = str_replace($anchor2, $llegadaAcciones . $anchor2, $js);

    $soloOld = "const soloLectura = ['respuesta_plan', 'peticion_resultado', 'marcha_publica'];";
    $soloNew = "const soloLectura = ['respuesta_plan', 'peticion_resultado', 'marcha_publica', 'marcha_despedida', 'legado_despedida'];";
  if (strpos($js, $soloOld) !== false) {
        $js = str_replace($soloOld, $soloNew, $js);
    }

    $wireAnchor = '    art.querySelectorAll(\'[data-consejo-opcion]\').forEach(function (btn) {';
    $wireInsert = <<<'JS'
    art.querySelectorAll('[data-llegada-acomp]').forEach(function (btn) {
      btn.addEventListener('click', async function (ev) {
        ev.stopPropagation();
        if (btn.disabled) return;
        const acomp = btn.getAttribute('data-llegada-acomp');
        if (!acomp) return;
        art.querySelectorAll('[data-llegada-acomp]').forEach(function (b) { b.disabled = true; });
        const ok = await resolverAccionMensajito(m, 'aceptar_candidato', { acompanante_id: acomp });
        if (ok) await refresh();
        else art.querySelectorAll('[data-llegada-acomp]').forEach(function (b) { b.disabled = false; });
      });
    });
    art.querySelectorAll('[data-llegada-rechazar]').forEach(function (btn) {
      btn.addEventListener('click', async function (ev) {
        ev.stopPropagation();
        if (btn.disabled) return;
        btn.disabled = true;
        const ok = await resolverAccionMensajito(m, 'rechazar_candidato');
        if (ok) await refresh();
        else btn.disabled = false;
      });
    });

JS;
    if (strpos($js, $wireAnchor) === false) {
        fwrite(STDERR, "wire consejo anchor missing\n");
        exit(1);
    }
    $js = str_replace($wireAnchor, $wireInsert . $wireAnchor, $js);

    $cartaAnchor = '      const cuerpo = cuerpoMensajito(m, nombre);';
    $cartaInsert = '      const cuerpo = cuerpoMensajito(m, nombre);
      const perfilLlegadaHtml = (m.tipo === \'candidato_llegada\') ? htmlPerfilCandidato(m) : \'\';';
    if (strpos($js, 'perfilLlegadaHtml') === false) {
        $js = str_replace($cartaAnchor, $cartaInsert, $js);
        $cartaOld = "'<p class=\"cuerpo\">' + esc(cuerpo) + '</p>' +\n" .
            "        '</div></div>' +\n" .
            "        '<div class=\"carta-pie\">'";
        $cartaNew = "'<p class=\"cuerpo\">' + esc(cuerpo) + '</p>' +\n" .
            "        perfilLlegadaHtml +\n" .
            "        '</div></div>' +\n" .
            "        '<div class=\"carta-pie\">'";
        $js = str_replace($cartaOld, $cartaNew, $js);
    }

    $refreshAnchor = '    renderBuzon(buzon.mensajes || []);';
    $refreshInsert = '    renderBuzon(buzon.mensajes || []);
    pintarLlegadaCelebracionSiToca();';
    if (strpos($js, 'pintarLlegadaCelebracionSiToca') === false || strpos($js, $refreshInsert) === false) {
        $js = str_replace($refreshAnchor, $refreshInsert, $js, $cnt);
        if ($cnt === 0 && strpos($js, 'pintarLlegadaCelebracionSiToca();') === false) {
            fwrite(STDERR, "refresh anchor missing\n");
            exit(1);
        }
    }

    file_put_contents($jsPath, $js);
    echo "play-v3.js patched\n";
}

$css = file_get_contents($cssPath);
if (strpos($css, '.llegada-perfil') !== false) {
    echo "CSS already patched\n";
} else {
    $cssBlock = <<<'CSS'

/* Llegada candidato — perfil previo en Mensajito */
.llegada-perfil {
  display: flex;
  gap: 12px;
  margin-top: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(255, 248, 230, 0.65);
  border: 1px solid rgba(180, 140, 60, 0.25);
}
.llegada-perfil-foto {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}
.llegada-perfil-foto--ini {
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 1.25rem;
  background: #e8dcc8;
  color: #5a4a32;
}
.llegada-perfil-nom {
  font-weight: 600;
  margin-bottom: 4px;
}
.llegada-perfil-tag {
  font-size: 0.75rem;
  font-weight: 500;
  color: #7a5c20;
  background: rgba(210, 170, 80, 0.2);
  padding: 2px 6px;
  border-radius: 4px;
  margin-left: 4px;
}
.llegada-perfil-txt {
  font-size: 0.9rem;
  margin: 0 0 6px;
  line-height: 1.35;
}
.llegada-perfil-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.llegada-perfil-chip {
  font-size: 0.75rem;
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.06);
}
.llegada-acciones .llegada-acomp-opt.is-selected {
  outline: 2px solid #c9a227;
}
.llegada-acciones-pie {
  margin-top: 8px;
}
.llegada-sin-acomp {
  font-size: 0.85rem;
  margin: 4px 0;
  color: #666;
}

CSS;
    file_put_contents($cssPath, $css . $cssBlock);
    echo "CSS patched\n";
}

file_put_contents($busterPath, (string) time());
echo "cache buster updated\n";
