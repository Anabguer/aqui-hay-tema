const fs = require('fs');
const patches = [
  {
    file: 'assets/css/play-v3-app.css',
    from: '.play-root[data-capa="organizar"] .capa-organizar,',
    to: '.play-root[data-capa="organizar"] .capa-organizar,\n.play-root[data-capa="misiones"] .capa-misiones,',
  },
  {
    file: 'assets/css/play-v3-capas-shell.css',
    from: '.play-v3 .play-root.pc[data-capa="organizar"] .capa-organizar,',
    to: '.play-v3 .play-root.pc[data-capa="organizar"] .capa-organizar,\n.play-v3 .play-root.pc[data-capa="misiones"] .capa-misiones,',
  },
];
for (const p of patches) {
  let s = fs.readFileSync(p.file, 'utf8');
  if (s.includes('capa-misiones')) {
    console.log('skip', p.file);
    continue;
  }
  if (!s.includes(p.from)) {
    console.error('missing in', p.file);
    process.exit(1);
  }
  s = s.replace(p.from, p.to);
  fs.writeFileSync(p.file, s);
  console.log('ok', p.file);
}
