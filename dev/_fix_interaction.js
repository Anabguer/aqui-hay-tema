/**
 * Repara apertura de capas desde shell lateral y popups del mapa.
 */
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');

function patchJs() {
  const p = path.join(root, 'assets/js/play-v3.js');
  let js = fs.readFileSync(p, 'utf8');

  if (!js.includes('function uiRootFrom(el)')) {
    js = js.replace(
      '  function setCapa(name) {',
      `  function uiRootFrom(el) {
    return el && (el.closest('.play-root') || el.closest('.game-shell'));
  }

  function setCapa(name) {`
    );
  }

  js = js.replace(
    "if (t && t.closest('.play-root')) {",
    'if (t && uiRootFrom(t)) {'
  );
  js = js.replace(
    "if (open && open.closest('.play-root')) {",
    'if (open && uiRootFrom(open)) {'
  );

  fs.writeFileSync(p, js);
  console.log('js click scope ok', js.includes('uiRootFrom(t)'));
}

function patchCss() {
  const p = path.join(root, 'assets/css/play-v3.css');
  let css = fs.readFileSync(p, 'utf8');
  css = css.replace(
    '.play-root[data-consulta="sel"] .selector,\n.play-root[data-consulta="quien"] .quien { display: block; }\n.selector { display: none !important; }',
    '.play-root[data-consulta="sel"] .selector,\n.play-root[data-consulta="quien"] .quien { display: block !important; }'
  );
  fs.writeFileSync(p, css);
  console.log('css selector ok', !css.includes('.selector { display: none !important; }'));
}

function bumpUi() {
  const p = path.join(root, 'play.php');
  let php = fs.readFileSync(p, 'utf8');
  php = php.replace(/\$ahtUi = 'v3-20260821[a-z]';/, "$ahtUi = 'v3-20260821aa';");
  fs.writeFileSync(p, php, 'utf8');
  console.log('cache', php.match(/\$ahtUi = '([^']+)'/)[1]);
}

patchJs();
patchCss();
bumpUi();
