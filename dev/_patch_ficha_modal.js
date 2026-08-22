const fs = require('fs');

const jsPath = 'assets/js/play-v3.js';
let js = fs.readFileSync(jsPath, 'utf8');

const helpers = `
  let fichaActualId = '';

  function diaLlegadaVecino(id) {
    const insp = cacheInsp || {};
    const llegadas = insp.llegadas || {};
    const hist = llegadas.historial || [];
    for (let i = hist.length - 1; i >= 0; i--) {
      const h = hist[i];
      if (h && h.catalog_id === id && h.resultado === 'llegado') return Number(h.dia || 1);
    }
    const tut = llegadas.tutorial_hechas || [];
    for (let j = 0; j < tut.length; j++) {
      if (tut[j].catalog_id === id) return Number(llegadas.tutorial_completado_dia || 1);
    }
    return 1;
  }

  function etiquetaRelText(rel) {
    if (!rel) return '—';
    if (rel.etiqueta_vinculo === 'crisis') return 'En crisis';
    if (rel.etiqueta_vinculo === 'pareja') return 'Pareja';
    if (rel.etiqueta_vinculo === 'ex_pareja') return 'Ex pareja';
    const map = {
      desconocido: 'Desconocido',
      conocido: 'Conocido',
      amigo: 'Amigo',
      buena_amistad: 'Buena amistad',
      muy_buena_amistad: 'Buena amistad',
      cae_mal: 'Cae mal'
    };
    return map[rel.etiqueta_social] || String(rel.etiqueta_social || '—').replace(/_/g, ' ');
  }

  function barRelPct(rel) {
    if (rel.etiqueta_vinculo === 'crisis') return 42;
    if (rel.etiqueta_vinculo === 'pareja') return 92;
    const s = rel.etiqueta_social || '';
    if (s === 'buena_amistad' || s === 'muy_buena_amistad' || s === 'amigo') return 78;
    if (s === 'conocido') return 55;
    if (s === 'cae_mal') return 22;
    return 40;
  }

  function animoLinea(emo) {
    const id = String(emo || 'neutro');
    const map = {
      neutro: 'Hoy no se le nota gran cosa.',
      alegre: 'Está alegre.',
      triste: 'Está triste.',
      enfadado: 'Está enfadado.',
      pensativa: 'Está pensativa.',
      pensativo: 'Está pensativo.'
    };
    return map[id] || ('Está ' + id + '.');
  }

  function planesDeVecino(id) {
    return encuentrosFuturos(cacheInsp, cacheEstado).filter(function (e) {
      return (e.participantes || []).indexOf(id) >= 0;
    }).slice(0, 4);
  }

  function pintarFicha(id, f, vista) {
    fichaActualId = id;
    const nom = vista.nombre || (f.identidad && f.identidad.nombre) || id;
    const img = tokenDe(id);
    const caraBox = $('[data-ficha-img]');
    if (caraBox) {
      caraBox.innerHTML = img
        ? '<img src="' + esc(img) + '" alt=""/>'
        : '<span class="ficha-ini">' + esc((nom.charAt(0) || '?')) + '</span>';
    }
    const nomEl = $('[data-ficha-nombre]');
    if (nomEl) nomEl.textContent = nom;
    const desdeEl = $('[data-ficha-desde]');
    if (desdeEl) desdeEl.textContent = 'En el pueblo desde el día ' + diaLlegadaVecino(id);
    const animoEl = $('[data-ficha-animo]');
    if (animoEl) {
      const emo = vista.estado_animo || 'neutro';
      animoEl.textContent = 'Ánimo: ' + animoLinea(emo).replace(/^Está /, '').replace(/^Hoy /, 'hoy ');
    }
    const rasgosBox = $('[data-ficha-rasgos]');
    if (rasgosBox) {
      rasgosBox.innerHTML = '';
      const rasgos = (vista.manera_de_ser || []).slice(0, 4);
      if (!rasgos.length) {
        rasgosBox.innerHTML = '<span class="ficha-vacio">Aún no sabes cómo es.</span>';
      } else {
        rasgos.forEach(function (t) {
          const sp = document.createElement('span');
          sp.className = 'ficha-rasgo-tag';
          sp.textContent = String(t).toUpperCase();
          rasgosBox.appendChild(sp);
        });
      }
    }
    const gustaBox = $('[data-ficha-gusta]');
    if (gustaBox) {
      gustaBox.innerHTML = '';
      const gusta = (vista.gusta || []).slice(0, 6);
      if (!gusta.length) {
        gustaBox.innerHTML = '<p class="ficha-vacio">Todavía no sabes qué le gusta.</p>';
      } else {
        gusta.forEach(function (t) {
          const sp = document.createElement('span');
          sp.className = 'ficha-chip';
          sp.textContent = t;
          gustaBox.appendChild(sp);
        });
      }
    }
    const noBox = $('[data-ficha-nogusta]');
    if (noBox) {
      noBox.innerHTML = '';
      const pistas = (vista.pistas || []).filter(function (t) {
        return /no soporta|echa para atrás|cara rara|Evítalo|no le va/i.test(String(t));
      });
      if (!pistas.length) {
        noBox.innerHTML = '<p class="ficha-vacio">Todavía no sabes qué no le gusta.</p>';
      } else {
        pistas.slice(0, 3).forEach(function (t) {
          const p = document.createElement('p');
          p.className = 'ficha-nogusta-item';
          p.textContent = t.replace(/^Has descubierto que /, '').replace(/^A /, '');
          noBox.appendChild(p);
        });
      }
    }
    const relBox = $('[data-ficha-relaciones]');
    if (relBox) {
      relBox.innerHTML = '';
      const rels = [];
      const raw = f.relaciones || {};
      Object.keys(raw).forEach(function (oid) {
        const r = raw[oid];
        if (!r || !r.conocidos) return;
        rels.push(r);
      });
      rels.sort(function (a, b) {
        if (a.etiqueta_vinculo === 'crisis') return -1;
        if (b.etiqueta_vinculo === 'crisis') return 1;
        return String(a.nombre).localeCompare(String(b.nombre), 'es');
      });
      if (!rels.length) {
        relBox.innerHTML = '<p class="ficha-vacio">Aún no tiene relaciones conocidas.</p>';
      } else {
        rels.slice(0, 5).forEach(function (rel) {
          const row = document.createElement('div');
          row.className = 'ficha-rel-row' + (rel.etiqueta_vinculo === 'crisis' ? ' is-crisis' : '');
          const cara = tokenDe(rel.id);
          const ini = (rel.nombre || '?').charAt(0);
          const pct = barRelPct(rel);
          const lbl = etiquetaRelText(rel);
          row.innerHTML =
            '<div class="ficha-rel-cara">' +
            (cara ? '<img src="' + esc(cara) + '" alt=""/>' : '<span>' + esc(ini) + '</span>') +
            '</div>' +
            '<div class="ficha-rel-main">' +
            '<div class="ficha-rel-nom">' + esc(rel.nombre || rel.id) + '</div>' +
            '<div class="ficha-rel-bar"><span style="width:' + pct + '%"></span></div>' +
            '</div>' +
            '<span class="ficha-rel-etiq">' + esc(lbl) + '</span>';
          relBox.appendChild(row);
        });
      }
    }
    const planBox = $('[data-ficha-planes]');
    if (planBox) {
      planBox.innerHTML = '';
      const planes = planesDeVecino(id);
      if (!planes.length) {
        const ult = f.ultimo_encuentro_vista || f.ultimo_encuentro;
        if (ult && (ult.texto || ult.lugar || ult.lugar_nombre)) {
          const p = document.createElement('p');
          p.className = 'ficha-plan-item';
          p.textContent = (ult.texto || formatPlanMeta(ult, cacheEstado));
          planBox.appendChild(p);
        } else {
          planBox.innerHTML = '<p class="ficha-vacio">Sin planes recientes.</p>';
        }
      } else {
        planes.forEach(function (enc) {
          const p = document.createElement('p');
          p.className = 'ficha-plan-item';
          p.textContent = formatPlanMeta(enc, cacheEstado);
          planBox.appendChild(p);
        });
      }
    }
    const orgBtn = $('[data-ficha-org]');
    if (orgBtn) {
      orgBtn.onclick = function () {
        org.a = id;
        setCapa('organizar');
        fillOrganizar();
      };
    }
    const msgBtn = $('[data-ficha-msg]');
    if (msgBtn) {
      msgBtn.disabled = true;
      msgBtn.title = 'Pronto podrás escribirles directamente.';
    }
  }
`;

if (!js.includes('function pintarFicha(')) {
  js = js.replace(
    '  async function abrirFicha(id) {',
    helpers + '\n  async function abrirFicha(id) {'
  );
}

const newAbrir = `  async function abrirFicha(id) {
    const r = await api('residente.ficha', { residente_id: id }, 'GET');
    if (!r.ok) return;
    if (r.tutorial) pintarTutorialMotor(r.tutorial);
    const f = r.ficha || {};
    const vista = f.vista_play || f;
    pintarFicha(id, f, vista);
    setCapa('ficha');
  }`;

const abrirRe = /  async function abrirFicha\(id\) \{[\s\S]*?\n  \}\n\n  function estadoCarta/;
if (!abrirRe.test(js)) {
  console.error('abrirFicha pattern fail');
  process.exit(1);
}
js = js.replace(abrirRe, newAbrir + '\n\n  function estadoCarta');

if (!js.includes('data-ficha-volver')) {
  js = js.replace(
    '  const vecBuscaInp = $(\'[data-vec-busca]\');',
    '  const fichaVolver = $(\'[data-ficha-volver]\');\n  if (fichaVolver) {\n    fichaVolver.addEventListener(\'click\', function () { setCapa(\'vecinos\'); renderVecinos(); });\n  }\n\n  const vecBuscaInp = $(\'[data-vec-busca]\');'
  );
}

fs.writeFileSync(jsPath, js, 'utf8');
console.log('JS ficha ok', js.includes('pintarFicha'));
