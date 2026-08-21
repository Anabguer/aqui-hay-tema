const fs = require('fs');
const path = 'assets/js/play-v3.js';
let s = fs.readFileSync(path, 'utf8');
const nl = s.includes('\r\n') ? '\r\n' : '\n';

const broken = `    const ptToggle = $('[data-playtest-toggle]');${nl}  const ptPanel = document.querySelector('[data-playtest-float] .playtest-float-panel');${nl}  if (ptToggle && ptPanel) {${nl}    ptToggle.addEventListener('click', function () {${nl}      var open = ptPanel.hasAttribute('hidden');${nl}      if (open) ptPanel.removeAttribute('hidden');${nl}      else ptPanel.setAttribute('hidden', 'hidden');${nl}      ptToggle.setAttribute('aria-expanded', open ? 'true' : 'false');${nl}    });${nl}  }${nl}${nl}  if (IS_LAB) {`;

const fixedConfig = `    if (IS_LAB) {`;

if (!s.includes(broken)) {
  console.error('broken block not found');
  process.exit(1);
}

s = s.replace(broken, fixedConfig);

const ptBlock = `${nl}  const ptToggle = $('[data-playtest-toggle]');${nl}  const ptPanel = document.querySelector('[data-playtest-float] .playtest-float-panel');${nl}  if (ptToggle && ptPanel) {${nl}    ptToggle.addEventListener('click', function () {${nl}      var open = ptPanel.hasAttribute('hidden');${nl}      if (open) ptPanel.removeAttribute('hidden');${nl}      else ptPanel.setAttribute('hidden', 'hidden');${nl}      ptToggle.setAttribute('aria-expanded', open ? 'true' : 'false');${nl}    });${nl}  }${nl}`;

const anchor = `  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));`;
if (!s.includes(anchor)) {
  console.error('anchor not found');
  process.exit(1);
}
if (!s.includes("const ptToggle = $('[data-playtest-toggle]')")) {
  s = s.replace(anchor, anchor + ptBlock);
}

// Fix pintarPlaytestGuia indentation (cosmetic but clearer)
s = s.replace(`${nl}    function pintarPlaytestGuia(guia, evento) {`, `${nl}  function pintarPlaytestGuia(guia, evento) {`);

fs.writeFileSync(path, s);
console.log('fixed configNueva + ptToggle placement');
