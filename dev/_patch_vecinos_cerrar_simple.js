const fs = require('fs');
const p = 'assets/css/play-v3-bloques-residencias.css';
let s = fs.readFileSync(p, 'utf8');

// Quitar estilos de pestaña doblada en vecinos
s = s.replace(/\.play-v3 \.capa-vecinos \.capa-cerrar-pesta[^\n]+\{[^}]*\}\n\n/g, '');
s = s.replace(/\.play-v3 \.capa-vecinos \.vecinos-cerrar \{ display: none !important; \}\n\n/g, '');

const cerrarCss = `.play-v3 .capa-vecinos .vecinos-cerrar {
  position: absolute;
  top: .6rem;
  right: .85rem;
  z-index: 6;
  border: 0 !important;
  background: transparent !important;
  padding: 0;
  margin: 0;
  width: auto;
  height: auto;
  min-width: 0;
  min-height: 0;
  display: block;
  font-family: Caveat, cursive;
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: .02em;
  text-transform: lowercase;
  color: #6a5848;
  cursor: pointer;
  box-shadow: none;
  text-shadow: none;
}

.play-v3 .capa-vecinos .vecinos-cerrar::before {
  display: none !important;
  content: none !important;
}

.play-v3 .capa-vecinos .vecinos-cerrar:hover {
  color: #2c261f;
}

.play-v3 .capa-vecinos .capa-cerrar-pestaña {
  display: none !important;
}

`;

if (!s.includes('.play-v3 .capa-vecinos .vecinos-cerrar {')) {
  const anchor = '.play-v3 .capa-vecinos .libreta-kicker,';
  if (!s.includes(anchor)) {
    console.error('anchor not found');
    process.exit(1);
  }
  s = s.replace(anchor, cerrarCss + anchor);
} else {
  s = s.replace(
    /\.play-v3 \.capa-vecinos \.vecinos-cerrar \{[\s\S]*?\.play-v3 \.capa-vecinos \.capa-cerrar-pestaña \{[\s\S]*?\}\n\n/,
    cerrarCss
  );
}

// Cabecera sin hueco de pestaña grande
s = s.replace(
  /\.play-v3 \.capa-vecinos \.vecinos-cab \{\n  padding-top: \.65rem;\n  padding-right: 4\.85rem;/,
  '.play-v3 .capa-vecinos .vecinos-cab {\n  padding-top: .65rem;\n  padding-right: 3.25rem;'
);

fs.writeFileSync(p, s, 'utf8');
console.log('ok');
