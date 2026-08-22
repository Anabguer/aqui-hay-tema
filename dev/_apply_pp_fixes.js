/**
 * Aplica fixes tutorial/misiones/plan en play-v3.js
 * node dev/_apply_pp_fixes.js
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

rep(
  `  let org = { modo: 'pareja', tipo: '', a: '', b: '', lugar: '', dia: null, hora: 17 };`,
  `  let org = { modo: 'pareja', tipo: '', a: '', b: '', lugar: '', dia: null, hora: 17 };
  let orgPresetNuevo = false;`,
  'org preset flag'
);

rep(
  `    if (irMisiones !== false && marcar) {
      setCapa('diario');
      var tab = $('[data-diario-tab="hoy"]');
      if (tab) tab.click();
    }`,
  `    if (irMisiones !== false && marcar) {
      setCapa('misiones');
    }`,
  'cerrarTutIntro misiones'
);

rep(
  `  async function cerrarTutFinale() {
    var box = $('[data-tut-finale]');
    if (box) box.hidden = true;
    document.body.removeAttribute('data-tut-finale');
    await api('partida.tutorial_finale', {});
    await refresh();
  }`,
  `  async function cerrarTutFinale() {
    var box = $('[data-tut-finale]');
    if (box) box.hidden = true;
    document.body.removeAttribute('data-tut-finale');
    await api('partida.tutorial_finale', {});
    await refresh();
    setCapa('misiones');
  }`,
  'cerrarTutFinale misiones'
);

rep(
  `    if (acc === 'organizar_pareja') {
      org.modo = 'pareja';
      if (params.a) org.a = params.a;
      if (params.b) org.b = params.b;
      setCapa('organizar');
      fillOrganizar();
      return;
    }
    if (acc === 'organizar_solo') {
      org.modo = 'solo';
      if (params.a) org.a = params.a;
      if (params.lugar) org.lugar = params.lugar;
      setCapa('organizar');
      fillOrganizar();
      return;
    }`,
  `    if (acc === 'organizar_pareja') {
      resetOrgForm({ modo: 'pareja', a: params.a, b: params.b });
      orgPresetNuevo = true;
      setCapa('organizar');
      fillOrganizar();
      return;
    }
    if (acc === 'organizar_solo') {
      resetOrgForm({ modo: 'solo', a: params.a, lugar: params.lugar });
      orgPresetNuevo = true;
      setCapa('organizar');
      fillOrganizar();
      return;
    }`,
  'ejecutarAccionMision reset'
);

rep(
  `  function setOrgModo(modo) {
    org.modo = modo === 'solo' ? 'solo' : 'pareja';
    var rowB = $('[data-org-row-b]');
    if (rowB) rowB.hidden = org.modo === 'solo';
    $('[data-org-modo]').forEach(function (btn) {
      btn.classList.toggle('is-on', btn.getAttribute('data-org-modo') === org.modo);
    });
    fillOrganizar();
  }`,
  `  function resetOrgForm(preset) {
    var p = preset || {};
    org.modo = p.modo === 'solo' ? 'solo' : 'pareja';
    org.tipo = org.modo === 'solo' ? 'individual' : '';
    org.a = p.a || '';
    org.b = org.modo === 'solo' ? '' : (p.b || '');
    org.lugar = p.lugar || '';
    org.dia = null;
    org.hora = 17;
  }

  function setOrgModo(modo) {
    org.modo = modo === 'solo' ? 'solo' : 'pareja';
    if (org.modo === 'solo') {
      org.b = '';
      org.tipo = 'individual';
    } else {
      org.tipo = '';
      if (org.a && org.b && org.a === org.b) org.b = '';
    }
    var rowB = $('[data-org-row-b]');
    if (rowB) rowB.hidden = org.modo === 'solo';
    $('[data-org-modo]').forEach(function (btn) {
      btn.classList.toggle('is-on', btn.getAttribute('data-org-modo') === org.modo);
    });
    fillOrganizar();
  }`,
  'resetOrgForm setOrgModo'
);

rep(
  `        toast(msg);
      }
      setCapa('');
      await refresh();
      if (r.tutorial) pintarTutorialMotor(r.tutorial);`,
  `        toast(msg);
      }
      if (r.nuevo_mensajito) {
        toast(r.mensajito_aviso_ui || 'Tienes un nuevo Mensajito.');
        $('.play-root').setAttribute('data-importante', '1');
      }
      setCapa('');
      await refresh();
      if (r.tutorial) pintarTutorialMotor(r.tutorial);`,
  'nuevo mensajito toast'
);

rep(
  `      if (name === 'organizar') fillOrganizar();`,
  `      if (name === 'organizar') {
        if (!orgPresetNuevo) resetOrgForm();
        orgPresetNuevo = false;
        fillOrganizar();
      }`,
  'open organizar reset'
);

if (!js.includes('btn.addEventListener(\'click\', function () { setOrgModo')) {
  rep(
    `  if (orgGo) orgGo.addEventListener('click', proponer);`,
    `  $$('[data-org-modo]').forEach(function (btn) {
    btn.addEventListener('click', function () { setOrgModo(btn.getAttribute('data-org-modo')); });
  });
  var finOk = $('[data-tut-fin-ok]');
  if (finOk) finOk.addEventListener('click', cerrarTutFinale);
  if (orgGo) orgGo.addEventListener('click', proponer);`,
    'org modo listeners'
  );
} else if (!js.includes('finOk.addEventListener')) {
  rep(
    `  if (orgGo) orgGo.addEventListener('click', proponer);`,
    `  var finOk = $('[data-tut-fin-ok]');
  if (finOk) finOk.addEventListener('click', cerrarTutFinale);
  if (orgGo) orgGo.addEventListener('click', proponer);`,
    'finale listener only'
  );
}

fs.writeFileSync(p, js);
console.log('play-v3.js fixes applied');
