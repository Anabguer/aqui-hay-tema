const fs = require('fs');
const p = 'assets/js/play-v3.js';
let j = fs.readFileSync(p, 'utf8');
j = j.replace(
  "$('[data-org-a]').addEventListener('change', refreshTipos);",
  "$('[data-org-a]').addEventListener('change', function () { refreshTipos(); pintarOrgCaras(); });"
);
j = j.replace(
  "$('[data-org-b]').addEventListener('change', refreshTipos);",
  "$('[data-org-b]').addEventListener('change', function () { refreshTipos(); pintarOrgCaras(); });"
);
fs.writeFileSync(p, j);
console.log('org caras listeners OK');
