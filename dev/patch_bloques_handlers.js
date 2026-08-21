const fs = require('fs');
const path = require('path');
const p = path.join(__dirname, '..', 'assets', 'js', 'play-v3.js');
let js = fs.readFileSync(p, 'utf8');
if (!js.includes('resBuscaInp')) {
  js = js.replace(
    "  $('[data-org-dia]').addEventListener('change', refreshHorasOrganizar);",
    "  const resBuscaInp = $('[data-res-busca]');\n  if (resBuscaInp) {\n    resBuscaInp.addEventListener('input', function () {\n      resBuscaTxt = resBuscaInp.value;\n      renderResidenciasCapa();\n    });\n  }\n  $('[data-org-dia]').addEventListener('change', refreshHorasOrganizar);"
  );
  fs.writeFileSync(p, js);
  console.log('added resBuscaInp');
} else {
  console.log('already there');
}
