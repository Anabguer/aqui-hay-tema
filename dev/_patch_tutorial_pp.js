/**
 * Tutorial Primeros Pasos + planes en solitario.
 * node dev/_patch_tutorial_pp.js
 */
const fs = require('fs');
const p = 'assets/js/play-v3.js';
let js = fs.readFileSync(p, 'utf8').replace(/\r\n/g, '\n');

function rep(from, to, label) {
  if (!js.includes(from)) {
    console.error('MISSING:', label);
    process.exit(1);
  }
  js = js.replace(from, to);
}

// Intro desde servidor
rep(
  `  function pintarTutIntro() {
    const box = $('[data-tut-intro]');
    if (!box) return;
    const paso = TUT_PASOS[tutIntroIdx];`,
  `  function tutPasosActuales() {
    if (cacheEstado && cacheEstado.tutorial && cacheEstado.tutorial.intro && cacheEstado.tutorial.intro.pasos) {
      return cacheEstado.tutorial.intro.pasos;
    }
    return TUT_PASOS;
  }
  function pintarTutIntro() {
    const box = $('[data-tut-intro]');
    if (!box) return;
    const pasos = tutPasosActuales();
    const paso = pasos[tutIntroIdx];`,
  'pintarTutIntro'
);
rep(
  `    TUT_PASOS.forEach(function (_, i) {`,
  `    tutPasosActuales().forEach(function (_, i) {`,
  'tut dots'
);
rep(
  `    if (btnSig) btnSig.textContent = tutIntroIdx >= TUT_PASOS.length - 1 ? 'Empezar' : 'Siguiente';`,
  `    const pasosN = tutPasosActuales();
    const ult = pasosN[tutIntroIdx];
    if (btnSig) {
      btnSig.textContent = tutIntroIdx >= pasosN.length - 1
        ? (ult && ult.boton_final ? ult.boton_final : 'A ver qué se cuece')
        : 'Siguiente';
    }
    var carasBox = $('[data-tut-caras]');
    if (carasBox) {
      carasBox.innerHTML = '';
      if (ult && ult.caras && ult.caras.length) {
        carasBox.hidden = false;
        ult.caras.forEach(function (c) {
          var sp = document.createElement('span');
          sp.className = 'cara';
          sp.innerHTML = c.token_url
            ? '<img src="' + esc(c.token_url) + '" alt=""/>'
            : '<span class="cara-ini">' + esc((c.nombre || '?')[0]) + '</span>';
          carasBox.appendChild(sp);
        });
      } else {
        carasBox.hidden = true;
      }
    }`,
  'tut btn caras'
);

if (!js.includes('function quizaMostrarTutFinale')) {
  rep(
    `  function quizaMostrarTutIntro() {`,
    `  function quizaMostrarTutFinale() {
    var tut = cacheEstado && cacheEstado.tutorial;
    if (!tut || !tut.finale_pendiente || !tut.finale) return;
    var box = $('[data-tut-finale]');
    if (!box) return;
    $('[data-tut-fin-tit]').textContent = tut.finale.tit || '';
    $('[data-tut-fin-texto]').textContent = tut.finale.txt || '';
    var btn = $('[data-tut-fin-ok]');
    if (btn) btn.textContent = tut.finale.boton || 'Que empiece el tema';
    box.hidden = false;
    document.body.setAttribute('data-tut-finale', '1');
  }
  async function cerrarTutFinale() {
    var box = $('[data-tut-finale]');
    if (box) box.hidden = true;
    document.body.removeAttribute('data-tut-finale');
    await api('partida.tutorial_finale', {});
    await refresh();
  }
  function quizaMostrarTutIntro() {`,
    'finale helpers'
  );
}

rep(
  `    if (tutIntroIdx >= TUT_PASOS.length - 1) cerrarTutIntro(true, true);`,
  `    const pasosN2 = tutPasosActuales();
    if (tutIntroIdx >= pasosN2.length - 1) cerrarTutIntro(true, true);`,
  'tut fin click'
);

if (!js.includes('org.modo')) {
  rep(
    `  let org = { tipo: '', a: '', b: '', lugar: '', dia: null, hora: 17 };`,
    `  let org = { modo: 'pareja', tipo: '', a: '', b: '', lugar: '', dia: null, hora: 17 };`,
    'org modo'
  );
}

rep(
  `  function fillOrganizar() {
    fillSelect($('[data-org-a]'), org.a, org.b);
    fillSelect($('[data-org-b]'), org.b, org.a);`,
  `  function setOrgModo(modo) {
    org.modo = modo === 'solo' ? 'solo' : 'pareja';
    var rowB = $('[data-org-row-b]');
    if (rowB) rowB.hidden = org.modo === 'solo';
    $$('[data-org-modo]').forEach(function (btn) {
      btn.classList.toggle('is-on', btn.getAttribute('data-org-modo') === org.modo);
    });
    fillOrganizar();
  }
  function fillOrganizar() {
    fillSelect($('[data-org-a]'), org.a, org.b);
    var rowB = $('[data-org-row-b]');
    if (rowB) rowB.hidden = org.modo === 'solo';
    if (org.modo !== 'solo') fillSelect($('[data-org-b]'), org.b, org.a);`,
  'setOrgModo'
);

rep(
  `    if (!org.a || !org.b || org.a === org.b) {
      box.innerHTML = '<p class="mini">Elige a dos personas distintas. Luego vemos qué plan encaja.</p>';
      return;
    }
    const r = await api('encuentro.tipos_permitidos', { residente_a: org.a, residente_b: org.b }, 'GET');`,
  `    if (org.modo === 'solo') {
      if (!org.a) {
        box.innerHTML = '<p class="mini">Elige a quién organizar el plan.</p>';
        return;
      }
      const rSolo = await api('encuentro.tipos_permitidos', { participantes: [org.a], modo: 'solo' }, 'GET');
      box.innerHTML = '';
      org.tipo = 'individual';
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'chip is-on';
      chip.textContent = 'Por su cuenta';
      box.appendChild(chip);
      return;
    }
    if (!org.a || !org.b || org.a === org.b) {
      box.innerHTML = '<p class="mini">Elige a dos personas distintas. Luego vemos qué plan encaja.</p>';
      return;
    }
    const r = await api('encuentro.tipos_permitidos', { residente_a: org.a, residente_b: org.b }, 'GET');`,
  'refreshTipos solo'
);

rep(
  `    if (!org.a || !org.b || org.a === org.b || !org.lugar || !org.dia) {
      toast(org.a && org.a === org.b ? 'Elige a dos personas distintas.' : 'Falta quién, dónde o cuándo.');
      return;
    }
    const payload = {
      participantes: [org.a, org.b],
      dia: org.dia,
      hora: org.hora,
      tipo: org.tipo || '',
      lugar: org.lugar
    };`,
  `    if (org.modo === 'solo') {
      if (!org.a || !org.lugar || !org.dia) {
        toast('Falta quién, dónde o cuándo.');
        return;
      }
    } else if (!org.a || !org.b || org.a === org.b || !org.lugar || !org.dia) {
      toast(org.a && org.a === org.b ? 'Elige a dos personas distintas.' : 'Falta quién, dónde o cuándo.');
      return;
    }
    const payload = {
      participantes: org.modo === 'solo' ? [org.a] : [org.a, org.b],
      dia: org.dia,
      hora: org.hora,
      tipo: org.modo === 'solo' ? 'individual' : (org.tipo || ''),
      lugar: org.lugar,
      modo: org.modo
    };`,
  'proponer solo'
);

if (!js.includes('data-org-modo')) {
  rep(
    `  $('[data-org-go]').addEventListener('click', proponer);`,
    `  $$('[data-org-modo]').forEach(function (btn) {
    btn.addEventListener('click', function () { setOrgModo(btn.getAttribute('data-org-modo')); });
  });
  var finOk = $('[data-tut-fin-ok]');
  if (finOk) finOk.addEventListener('click', cerrarTutFinale);
  $('[data-org-go]').addEventListener('click', proponer);`,
    'org modo listeners'
  );
}

rep(
  `    pintarTutorialMotor(cacheEstado.tutorial);
  }`,
  `    pintarTutorialMotor(cacheEstado.tutorial);
    quizaMostrarTutFinale();
  }`,
  'refresh finale'
);

rep(
  `      row.innerHTML = '<p>' + esc(m.texto || m.hecho || 'Objetivo') + '</p>' +`,
  `      var tit = m.titulo ? '<strong>' + esc(m.titulo) + '</strong><br/>' : '';
      row.innerHTML = tit + '<p>' + esc(m.texto || m.hecho || 'Objetivo') + '</p>' +`,
  'mision titulo'
);

fs.writeFileSync(p, js);
console.log('tutorial_pp patched');
