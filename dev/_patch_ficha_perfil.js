const fs = require('fs');
const jsPath = 'assets/js/play-v3.js';
let js = fs.readFileSync(jsPath, 'utf8');

const helpers = `
  function canonEmoId(id) {
    const e = String(id || 'neutro').toLowerCase();
    if (e === 'neutral' || e === 'neutro') return 'neutro';
    if (e === 'alegre' || e === 'triste' || e === 'enfadado') return e;
    return 'neutro';
  }

  function etiquetaVecinoDesde(vista, dia) {
    const g = vista && vista.genero;
    const rol = g === 'mujer' ? 'Vecina' : (g === 'hombre' ? 'Vecino' : 'Vecino');
    return rol + ' desde el día ' + dia;
  }

  function textoAnimoDisplay(emo) {
    const map = {
      neutro: 'neutral',
      alegre: 'alegre',
      triste: 'triste',
      enfadado: 'enfadada'
    };
    return map[emo] || emo.replace(/_/g, ' ');
  }

  function svgAnimoBubble(emo) {
    const faces = {
      neutro: '<circle cx="18" cy="17" r="1.2" fill="#2c261f"/><circle cx="24" cy="17" r="1.2" fill="#2c261f"/><path d="M17 22.5 Q21 24.5 25 22.5" stroke="#2c261f" stroke-width="1.2" fill="none" stroke-linecap="round"/>',
      alegre: '<circle cx="18" cy="17" r="1.2" fill="#2c261f"/><circle cx="24" cy="17" r="1.2" fill="#2c261f"/><path d="M17 21.5 Q21 25.5 25 21.5" stroke="#2c261f" stroke-width="1.2" fill="none" stroke-linecap="round"/>',
      triste: '<circle cx="18" cy="17" r="1.2" fill="#2c261f"/><circle cx="24" cy="17" r="1.2" fill="#2c261f"/><path d="M17 24 Q21 21 25 24" stroke="#2c261f" stroke-width="1.2" fill="none" stroke-linecap="round"/>',
      enfadado: '<path d="M16.5 15.5 L19 17" stroke="#2c261f" stroke-width="1.2" stroke-linecap="round"/><path d="M25.5 15.5 L23 17" stroke="#2c261f" stroke-width="1.2" stroke-linecap="round"/><circle cx="18" cy="18.5" r="1.2" fill="#2c261f"/><circle cx="24" cy="18.5" r="1.2" fill="#2c261f"/><path d="M17.5 23 Q21 21.5 24.5 23" stroke="#2c261f" stroke-width="1.2" fill="none" stroke-linecap="round"/>'
    };
    const face = faces[emo] || faces.neutro;
    return '<svg class="ficha-animo-svg" viewBox="0 0 42 36" width="42" height="36" aria-hidden="true">' +
      '<path d="M8 14 C8 8 14 4 21 4 C28 4 34 8 34 14 C34 20 30 24 26 26 L24 32 L18 32 L16 26 C12 24 8 20 8 14 Z" fill="#fffdf8" stroke="#2c261f" stroke-width="1.4" stroke-linejoin="round"/>' +
      '<ellipse cx="12" cy="12" rx="5" ry="4" fill="#fffdf8" stroke="#2c261f" stroke-width="1.2"/>' +
      face +
      '</svg>';
  }

  function pintarAnimoFicha(vista) {
    const emo = canonEmoId(vista.estado_animo);
    const txtEl = $('[data-ficha-animo-text]');
    const icoEl = $('[data-ficha-animo-ico]');
    if (txtEl) txtEl.textContent = textoAnimoDisplay(emo);
    if (icoEl) {
      icoEl.setAttribute('data-emo', emo);
      icoEl.innerHTML = svgAnimoBubble(emo);
    }
  }
`;

if (!js.includes('function canonEmoId(')) {
  js = js.replace('  function textoPlanesVacios(id) {', helpers.trim() + '\n\n  function textoPlanesVacios(id) {');
}

const oldProfile = `    const desdeEl = $('[data-ficha-desde]');
    if (desdeEl) desdeEl.textContent = 'En el pueblo desde el día ' + diaLlegadaVecino(id);
    const animoEl = $('[data-ficha-animo]');
    if (animoEl) {
      const emo = String(vista.estado_animo || 'neutro');
      const emoMap = {
        neutro: 'neutro',
        alegre: 'alegre',
        triste: 'triste',
        enfadado: 'enfadado',
        pensativa: 'pensativa',
        pensativo: 'pensativo'
      };
      animoEl.textContent = 'Ánimo: ' + (emoMap[emo] || emo.replace(/_/g, ' '));
    }`;

const newProfile = `    const desdeEl = $('[data-ficha-desde]');
    if (desdeEl) desdeEl.textContent = etiquetaVecinoDesde(vista, diaLlegadaVecino(id));
    pintarAnimoFicha(vista);`;

if (!js.includes(oldProfile)) {
  // try without accented chars issues
  const re = /const desdeEl = \$\('\[data-ficha-desde\]'\);[\s\S]*?animoEl\.textContent = 'Ánimo:[\s\S]*?\n    \}/;
  if (re.test(js)) {
    js = js.replace(re, newProfile.trim());
  } else {
    console.error('profile block not found');
    process.exit(1);
  }
} else {
  js = js.replace(oldProfile, newProfile);
}

fs.writeFileSync(jsPath, js, 'utf8');
console.log('profile JS ok');
