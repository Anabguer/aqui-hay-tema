'use strict';
const fs = require('fs');
const path = require('path');

const p = path.join(__dirname, '..', 'assets/css/design-system/screens/inicio-desktop.css');
let css = fs.readFileSync(p, 'utf8');

css = css.replace(
  /(\.inicio-stage > \.inicio-map-host \{[\s\S]*?grid-row: 2;\s*\}\s*)\}\s*\n\s*\}\s*\n\s*\/\* PORTED-DESKTOP-SHELL \*\//,
  '$1}\n\n/* PORTED-DESKTOP-SHELL */'
);

let depth = 0;
for (const ch of css) {
  if (ch === '{') depth++;
  if (ch === '}') depth--;
}
if (depth !== 0) {
  console.error('brace imbalance:', depth);
  process.exit(1);
}

fs.writeFileSync(p, css);
console.log('OK fixed inicio-desktop.css parse (depth=0)');
