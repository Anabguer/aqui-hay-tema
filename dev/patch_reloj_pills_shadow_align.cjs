'use strict';
const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, '..', 'assets/css/inicio/inicio-desktop.css');
let css = fs.readFileSync(file, 'utf8');

css = css.replace(
  /(\.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.top-reloj\s*\{[\s\S]*?)align-items:\s*center;/,
  '$1align-items: stretch;'
);

if (!/\.game-top \.obj-hora,\s*\n\s*\.play-v3:has\(\.inicio-desktop\.is-inicio-view-active\) \.inicio-desktop > \.game-top \.obj-dia-cuerpo::before/.test(css)) {
  css = css.replace(
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-cuerpo::before,`,
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-hora,
  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-cuerpo::before,`
  );
}

const fixPillBlock = (sel) => {
  const re = new RegExp(
    `(\\.play-v3:has\\(\\.inicio-desktop\\.is-inicio-view-active\\) \\.inicio-desktop > \\.game-top \\.${sel}\\s*\\{[\\s\\S]*?)box-shadow:\\s*0 2px 8px rgba\\(70,\\s*55,\\s*85,\\s*\\.08\\);`,
    'g'
  );
  css = css.replace(re, (m, pre) => `${pre}box-shadow: none;\n    margin: 0;\n    align-self: center;`);
};

fixPillBlock('obj-dia');
fixPillBlock('obj-hora');

if (!/\.obj-dia-meta\s*\{[\s\S]{0,500}box-shadow:\s*none/.test(css)) {
  css = css.replace(
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-meta {
    display: block;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: .78rem;
    font-weight: 600;
    line-height: 1.1;
    color: #6b5f78;
  }`,
    `  .play-v3:has(.inicio-desktop.is-inicio-view-active) .inicio-desktop > .game-top .obj-dia-meta {
    display: block;
    font-family: var(--ds-font-ui, Nunito, sans-serif);
    font-size: .78rem;
    font-weight: 600;
    line-height: 1.1;
    color: #6b5f78;
    border-bottom: none;
    text-decoration: none;
    box-shadow: none;
  }`
  );
}

const pillFail = (name) => {
  const re = new RegExp(`\\.play-v3:has\\(\\.inicio-desktop\\.is-inicio-view-active\\) \\.inicio-desktop > \\.game-top \\.${name}\\s*\\{[\\s\\S]*?\\n  \\}`);
  const block = re.exec(css);
  if (!block) {
    console.error('patch_reloj_pills_shadow_align: bloque ausente', name);
    process.exit(1);
  }
  if (/box-shadow:\s*0 2px 8px rgba\(70,\s*55,\s*85,\s*\.08\)/.test(block[0])) {
    console.error('patch_reloj_pills_shadow_align: queda sombra en', name);
    process.exit(1);
  }
  if (!/box-shadow:\s*none/.test(block[0])) {
    console.error('patch_reloj_pills_shadow_align: sin box-shadow none en', name);
    process.exit(1);
  }
};

pillFail('obj-dia');
pillFail('obj-hora');

fs.writeFileSync(file, css, 'utf8');
console.log('patch_reloj_pills_shadow_align OK');