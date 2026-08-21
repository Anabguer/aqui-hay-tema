const fs = require('fs');
const path = require('path');
const root = path.join(__dirname, '..');
eval(fs.readFileSync(path.join(__dirname, '_patch_ui_shell2.js'), 'utf8').replace(/patchPlayPhp\(\);|patchCss\(\);/g, ''));
patchJs();
