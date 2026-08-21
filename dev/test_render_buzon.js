/* eslint-disable */
/** Regresión renderBuzon: remitenteIdDe null no debe lanzar TypeError. */
const fs = require('fs');
const path = require('path');

function extractFn(src, name) {
  const sig = 'function ' + name + '(';
  const start = src.indexOf(sig);
  if (start < 0) throw new Error('missing ' + name);
  let i = src.indexOf('{', start);
  let depth = 0;
  for (; i < src.length; i++) {
    if (src[i] === '{') depth++;
    else if (src[i] === '}') {
      depth--;
      if (depth === 0) return src.slice(start, i + 1);
    }
  }
  throw new Error('unclosed ' + name);
}

const src = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'play-v3.js'), 'utf8');
const fns = [
  'remitenteIdDe', 'remitenteEtiquetaDe', 'inicialDe', 'nombreDe', 'esc',
  'estadoCarta', 'cuerpoCarta', 'tokenDe'
].map(function (n) { return extractFn(src, n); }).join('\n');

const code = fns + `
function simRenderBuzon(msgs) {
  const cartas = (msgs || []).filter(function (m) { return (m.canal || 'buzon') !== 'cotilleo'; });
  return cartas.map(function (m) {
    const rid = remitenteIdDe(m);
    const de = remitenteEtiquetaDe(m);
    const cuerpo = cuerpoCarta(m, de);
    const tok = rid ? tokenDe(rid) : null;
    const avatar = tok ? 'img' : inicialDe(de);
    return { rid: rid, de: de, cuerpo: cuerpo, avatar: avatar };
  });
}
return simRenderBuzon;
`;

const sim = new Function('cacheInsp', 'cachePueblo', code)({ residentes: {} }, null);

const cases = [
  { canal: 'buzon', estado: 'pendiente', texto: 'Llegada nueva', candidato_catalog_id: 'per_x' },
  { canal: 'buzon', estado: 'pendiente', texto: 'Sin remitente' },
  { canal: 'buzon', de_persona: null, texto: 'de_persona null' },
];

let fail = 0;
cases.forEach(function (m, i) {
  try {
    const r = sim([m]);
    console.log('OK caso ' + i + ': ' + JSON.stringify(r[0]));
  } catch (e) {
    console.log('FAIL caso ' + i + ': ' + e.message);
    fail = 1;
  }
});
process.exit(fail);
