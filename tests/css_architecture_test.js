'use strict';
const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
const php = fs.readFileSync(path.join(root, 'play.php'), 'utf8');
let failures = 0;
function ok(cond, msg) { console.log((cond ? 'OK' : 'FAIL') + ': ' + msg); if (!cond) failures++; }

function countImpInDir(dir) {
  let n = 0;
  const offenders = [];
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) {
      const sub = countImpInDir(p);
      n += sub.total;
      offenders.push(...sub.offenders);
    } else if (e.name.endsWith('.css')) {
      const m = fs.readFileSync(p, 'utf8').match(/!important/gi);
      if (m && m.length) {
        n += m.length;
        offenders.push(path.relative(root, p).replace(/\\/g, '/') + ':' + m.length);
      }
    }
  }
  return { total: n, offenders };
}

const legacy = [
  'play-v3-capas.css', 'modal-core.css', 'modals-shell-lavanda-mobile.css', 'modals-secondary-unified.css',
  'screens-secondary.css', 'play-v3-ficha.css', 'play-v3-visual-review.css', 'play-v3-inicio-override.css',
  'design-system/screens/inicio-mobile.css', 'design-system/screens/inicio-desktop-cromatica.css',
  'mensajitos-cartas-persona-v1.css', 'mensajitos-carta-regalo-v1.css'
];
legacy.forEach(f => ok(!php.includes(f), 'play.php sin ' + f));
['inicio/inicio-base.css', 'inicio/inicio-mobile.css', 'v4/bodies/misc-screens.css', 'design-system/mensajitos-body.css'].forEach(f => ok(php.includes(f), 'play enlaza ' + f));

const cssImp = countImpInDir(path.join(root, 'assets/css'));
ok(cssImp.total === 0, 'assets/css !important total = 0 (actual ' + cssImp.total + (cssImp.offenders.length ? ': ' + cssImp.offenders.join(', ') : '') + ')');

const styleBlocks = php.match(/<style>[\s\S]*?<\/style>/g) || [];
let inlineImp = 0;
styleBlocks.forEach(block => {
  inlineImp += (block.match(/!important/gi) || []).length;
});
ok(inlineImp === 0, 'play.php inline !important = 0 (actual ' + inlineImp + ')');

const frame = fs.readFileSync(path.join(root, 'assets/css/v4/screen-frame.css'), 'utf8');
ok(/AHT-FRAME-CANON-v4/.test(frame), 'marcador frame');
ok((php.match(/class="aht-screen"/g) || []).length >= 19, '>=19 screens');

const screensIdx = php.indexOf('assets/css/v4/screens.css');
const legIdx = php.indexOf('design-system/legibilidad-global.css');
ok(screensIdx > legIdx, 'screens.css despues de legibilidad-global (ultima autoridad shell)');

const forbiddenShell = [
  { file: 'assets/css/play-v3-app.css', pattern: /\.play-root\.phone \.aht-screen\s*\{/, msg: 'sin shell legacy phone .aht-screen en play-v3-app' },
  { file: 'assets/css/play-v3-app.css', pattern: /\.aht-screen\s*\{\s*position:\s*absolute/, msg: 'sin position:absolute en .aht-screen en play-v3-app' },
  { file: 'assets/css/play-v3-avisos.css', pattern: /play-root\.phone\[data-capa=.*\.aht-screen\[data-aht-screen=.*position:\s*fixed/, msg: 'sin shell duplicado CAPAS en play-v3-avisos' },
  { file: 'assets/css/play-v3-shell-ui.css', pattern: /\.play-root\.pc \.aht-screen\[data-aht-screen="buzon"\]\s*\{[^}]*\bwidth:/, msg: 'sin ancho shell buzon en play-v3-shell-ui' },
  { file: 'assets/css/play-v3.css', pattern: /\[data-capa="organizar"\][^{]*\.aht-screen[^{]*\{[^}]*transform:\s*none/, msg: 'sin transform:none organizar en play-v3.css' },
  { file: 'assets/css/play-v3-regalos.css', pattern: /\[data-capa="inventario"\][^{]*\.aht-screen[^{]*\{[^}]*transform:\s*none/, msg: 'sin transform:none inventario en play-v3-regalos' },
  { file: 'assets/css/play-v3-cotilleos.css', pattern: /\[data-capa="diario"\][^{]*\.aht-screen[^{]*coti-modal-papel[^{]*\{[^}]*position:\s*fixed[^}]*visibility:\s*visible/, msg: 'sin shell coti-modal-papel en play-v3-cotilleos' },
  { file: 'assets/css/play-v3-bloques-residencias.css', pattern: /\[data-capa="vecinos"\]\s+\.velo\s*\{[^}]*opacity:\s*1/, msg: 'sin velo legacy vecinos en bloques-residencias' },
  { file: 'assets/css/v4/screens.css', pattern: /V4 SHELL AUTHORITY/, msg: 'sin bloque parche AUTHORITY en screens.css' },
];
forbiddenShell.forEach(({ file, pattern, msg }) => {
  const content = fs.readFileSync(path.join(root, file), 'utf8');
  ok(!pattern.test(content), msg);
});

// Auditoría reproducible (dev/audit_modal_pollution.cjs)
const { execFileSync } = require('child_process');
try {
  execFileSync(process.execPath, [path.join(root, 'dev/audit_modal_pollution.cjs')], { stdio: 'pipe' });
  ok(true, 'audit_modal_pollution sin criticos');
} catch (e) {
  ok(false, 'audit_modal_pollution sin criticos');
  if (e.stdout) process.stdout.write(e.stdout);
}

try {
  execFileSync(process.execPath, [path.join(root, 'dev/audit_inicio_cascade_authority.cjs')], { stdio: 'pipe' });
  ok(true, 'audit_inicio_cascade_authority una autoridad por zona');
} catch (e) {
  ok(false, 'audit_inicio_cascade_authority una autoridad por zona');
  if (e.stdout) process.stdout.write(e.stdout);
  if (e.stderr) process.stderr.write(e.stderr);
}

try {
  execFileSync(process.execPath, [path.join(root, 'dev/audit_inicio_authority.cjs')], { stdio: 'pipe' });
  ok(true, 'audit_inicio_authority sin doble stack legacy');
} catch (e) {
  ok(false, 'audit_inicio_authority sin doble stack legacy');
  if (e.stdout) process.stdout.write(e.stdout);
  if (e.stderr) process.stderr.write(e.stderr);
}

console.log(failures ? '\n' + failures + ' FAIL' : '\nTODO OK');
process.exit(failures ? 1 : 0);
