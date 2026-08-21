/**
 * Fix renderBuzon crash when de_persona is null (valid motor state).
 * Adds remitenteIdDe / remitenteEtiquetaDe helpers.
 */
const fs = require('fs');
const jsPath = 'W:/juegos/aqui-hay-tema/assets/js/play-v3.js';
let js = fs.readFileSync(jsPath, 'utf8');

if (!js.includes('function remitenteIdDe')) {
  js = js.replace(
    `  function nombreDe(id) {
    const r = (cacheInsp && cacheInsp.residentes && cacheInsp.residentes[id]) || {};
    return (r.identidad_publica && r.identidad_publica.nombre) || id;
  }`,
    `  function nombreDe(id) {
    if (id === null || id === undefined || id === '') return '';
    const r = (cacheInsp && cacheInsp.residentes && cacheInsp.residentes[id]) || {};
    return (r.identidad_publica && r.identidad_publica.nombre) || String(id);
  }

  /** ID de remitente si el motor lo expone (de_persona, candidato, actores…). */
  function remitenteIdDe(m) {
    if (!m || typeof m !== 'object') return null;
    const direct = m.de_persona || m.de;
    if (direct) return direct;
    if (m.candidato_catalog_id) return m.candidato_catalog_id;
    const act = m.actores;
    if (Array.isArray(act)) {
      for (let i = 0; i < act.length; i++) {
        if (act[i]) return act[i];
      }
    }
    return null;
  }

  function remitenteEtiquetaDe(m) {
    const id = remitenteIdDe(m);
    if (!id) return '';
    return nombreDe(id);
  }

  function inicialDe(nombre) {
    if (typeof nombre !== 'string' || !nombre.length) return '?';
    return nombre.charAt(0);
  }`
  );
}

const oldRenderBlock = `      const de = nombreDe(m.de_persona);
      const cuerpo = cuerpoCarta(m, de);
      const plazo = m.plazo_humano || '';
      const rid = m.de_persona || m.de;
      const tok = tokenDe(rid);
      const avatar = tok
        ? '<img class="carta-avatar" src="' + esc(tok) + '" alt=""/>'
        : '<span class="carta-avatar cara-ini">' + esc((de[0] || '?')) + '</span>';
      art.innerHTML = (m.clasificacion === 'importante' ? '<span class="lacre" aria-hidden="true"></span>' : '') +
        '<div class="carta-inner">' + avatar +
        '<div class="carta-copy">' +
        (st.txt ? '<div class="sello-estado">' + esc(st.txt) + '</div>' : '') +
        '<div class="de">De ' + esc(de) + '</div>' +
        '<p class="cuerpo">' + esc(cuerpo) + '</p>' +
        (plazo ? '<p class="plazo">' + esc(plazo) + '</p>' : '') +
        '</div></div>';`;

const newRenderBlock = `      const rid = remitenteIdDe(m);
      const de = remitenteEtiquetaDe(m);
      const cuerpo = cuerpoCarta(m, de);
      const plazo = m.plazo_humano || '';
      const tok = rid ? tokenDe(rid) : null;
      const avatar = tok
        ? '<img class="carta-avatar" src="' + esc(tok) + '" alt=""/>'
        : '<span class="carta-avatar cara-ini">' + esc(inicialDe(de)) + '</span>';
      const deLine = de ? '<div class="de">De ' + esc(de) + '</div>' : '';
      art.innerHTML = (m.clasificacion === 'importante' ? '<span class="lacre" aria-hidden="true"></span>' : '') +
        '<div class="carta-inner">' + avatar +
        '<div class="carta-copy">' +
        (st.txt ? '<div class="sello-estado">' + esc(st.txt) + '</div>' : '') +
        deLine +
        '<p class="cuerpo">' + esc(cuerpo) + '</p>' +
        (plazo ? '<p class="plazo">' + esc(plazo) + '</p>' : '') +
        '</div></div>';`;

if (js.includes('de[0]')) {
  js = js.replace(oldRenderBlock, newRenderBlock);
  fs.writeFileSync(jsPath, js);
  console.log('renderBuzon patched');
} else if (js.includes('remitenteIdDe(m)')) {
  console.log('already patched');
} else {
  console.error('Could not find renderBuzon block — manual patch needed');
  process.exit(1);
}
