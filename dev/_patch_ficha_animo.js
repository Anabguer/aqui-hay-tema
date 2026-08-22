const fs = require('fs');
const jsPath = 'assets/js/play-v3.js';
let js = fs.readFileSync(jsPath, 'utf8');

const oldAnimo = `    const animoEl = $('[data-ficha-animo]');
    if (animoEl) {
      const emo = vista.estado_animo || 'neutro';
      animoEl.textContent = 'Ánimo: ' + animoLinea(emo).replace(/^Está /, '').replace(/^Hoy /, 'hoy ');
    }`;

const newAnimo = `    const animoEl = $('[data-ficha-animo]');
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

if (!js.includes(oldAnimo)) {
  console.error('animo block not found');
  process.exit(1);
}
js = js.replace(oldAnimo, newAnimo);

// Remove unused animoLinea if only used for animo - check first
if (!js.includes('animoLinea(')) {
  js = js.replace(/\n  function animoLinea\(emo\) \{[\s\S]*?\n  \}\n/, '\n');
}

fs.writeFileSync(jsPath, js, 'utf8');
console.log('animo fix ok');
