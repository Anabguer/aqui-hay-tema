const fs = require('fs');
const p = 'assets/css/play-v3-bloques-residencias.css';
let s = fs.readFileSync(p, 'utf8');

const re = /\.play-v3 \.capa-vecinos \.vecinos-cerrar[\s\S]*?\.play-v3 \.capa-vecinos \.capa-cerrar-pesta[^\n]+\n\n/;
const neu = `.play-v3 .capa-vecinos .capa-cerrar-pestaña {
  font-family: Caveat, cursive;
  font-size: .98rem !important;
  letter-spacing: .03em;
}

`;

if (!re.test(s)) {
  console.error('pattern not found');
  process.exit(1);
}
s = s.replace(re, neu);
fs.writeFileSync(p, s, 'utf8');
console.log('ok');
