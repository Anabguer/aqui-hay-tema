const fs = require('fs');
const jsPath = 'W:/juegos/aqui-hay-tema/assets/js/play-v3.js';
let js = fs.readFileSync(jsPath, 'utf8');

js = js.replace(
  `    if (v === null || v === undefined || v === '') return '—';
    const n = Number(v);
    if (n === 0) return '0 €';
    return String(Math.round(n)) + ' €';`,
  `    if (v === null || v === undefined || v === '') {
      if (insp && insp.meta && insp.meta.partida_id) return '0 €';
      return '…';
    }
    const n = Number(v);
    if (n === 0) return '0 €';
    return String(Math.round(n)) + ' €';`
);

js = js.replace(
  `    const fill = $('.corazon-fill') || $('.corazon-dibujo');
    if (fill) fill.style.setProperty('--fill', pct + '%');
    const hudFill = $('.corazon-hud-fill');
    if (hudFill) hudFill.style.width = pct + '%';
    const pctN = $('[data-vida-pct]');
    if (pctN) pctN.textContent = Math.round(pct) + '%';`,
  `    const fill = $('.corazon-fill') || $('.corazon-dibujo');
    if (fill) fill.style.setProperty('--fill', pct + '%');
    const shellHeart = $('.corazon-shell-fill');
    if (shellHeart) shellHeart.style.setProperty('--fill', pct + '%');
    const pctN = $('[data-vida-pct]');
    if (pctN) pctN.textContent = Math.round(pct) + '%';`
);

js = js.replace(
  `      [
        { key: 'bloque_a', label: 'A' },
        { key: 'bloque_b', label: 'B' },
        { key: 'bloque_c', label: 'C' },
      ].forEach(function (d) {
        const blk = partida[d.key];
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'bloque-mini' + ((!blk || !blk.viviendas) ? ' is-cerrado' : '');`,
  `      [
        { key: 'bloque_a', label: 'A' },
        { key: 'bloque_b', label: 'B' },
        { key: 'bloque_c', label: 'C' },
      ].forEach(function (d) {
        const blk = partida[d.key];
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('data-bloque', d.label.toLowerCase());
        btn.className = 'bloque-mini bloque-' + d.label.toLowerCase() + ((!blk || !blk.viviendas) ? ' is-cerrado' : '');`
);

js = js.replace(
  `      if (!parejas.length) strip.innerHTML = '<p class="muted-soft">Sin parejas todavía.</p>';`,
  `      if (!parejas.length) strip.innerHTML = '<p class="muted-soft parejas-vacio">Todavía no hay parejas. El pueblo está en fase de mirarse de reojo.</p>';`
);

fs.writeFileSync(jsPath, js);
console.log('js patched');
