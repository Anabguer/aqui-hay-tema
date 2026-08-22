const fs = require('fs');

const jsPath = 'assets/js/play-v3.js';
let js = fs.readFileSync(jsPath, 'utf8');

const tokenDeBlock = `  var TOKEN_LOTE_FILES = [
    'P001.png', 'P008.png', 'P009.png', 'P010.png', 'P016.png',
    'P018.png', 'P028.png', 'P031.png', 'P082.png', 'P109.png',
    'P117.png', 'P121.png', 'P138.png', 'P173.png'
  ];

  function crc32Unsigned(str) {
    var crc = 0xFFFFFFFF;
    for (var i = 0; i < str.length; i++) {
      crc ^= str.charCodeAt(i);
      for (var j = 0; j < 8; j++) {
        crc = (crc >>> 1) ^ (0xEDB88320 & -(crc & 1));
      }
    }
    return (crc ^ 0xFFFFFFFF) >>> 0;
  }

  function tokenLoteUrl(rid) {
    if (!rid) return null;
    var n = TOKEN_LOTE_FILES.length;
    if (!n) return null;
    var idx = crc32Unsigned(String(rid)) % n;
    return 'assets/personajes/tokens-m/' + TOKEN_LOTE_FILES[idx];
  }

  function tokenDe(rid) {
    if (!rid) return null;
    var tokens = cachePueblo && cachePueblo.tokens;
    if (tokens && tokens[rid] && tokens[rid].url) return tokens[rid].url;
    var res = cacheInsp && cacheInsp.residentes && cacheInsp.residentes[rid];
    var cid = (res && res.catalog_id) || rid;
    if (tokens && tokens[cid] && tokens[cid].url) return tokens[cid].url;
    var hitUrl = null;
    (cachePueblo && cachePueblo.complejos || []).forEach(function (c) {
      (c.personas || []).forEach(function (p) {
        if (p.id === rid || p.id === cid) hitUrl = p.token_url || hitUrl;
      });
      if (!hitUrl) {
        (c.visibles || []).forEach(function (p) {
          if (p.id === rid || p.id === cid) hitUrl = p.token_url || hitUrl;
        });
      }
    });
    if (hitUrl) return hitUrl;
    return tokenLoteUrl(cid) || tokenLoteUrl(rid);
  }`;

const tokenRe = /  function tokenDe\(rid\) \{[\s\S]*?\n  \}\n\n  let vecBuscaTxt/;
if (!tokenRe.test(js)) {
  console.error('tokenDe pattern not found');
  process.exit(1);
}
js = js.replace(tokenRe, tokenDeBlock + '\n\n  let vecBuscaTxt');

if (!js.includes("if (name === 'vecinos') renderVecinos();")) {
  js = js.replace(
    "      if (name === 'diario') $('[data-diario-tab=\"hoy\"]').click();\n      return;",
    "      if (name === 'diario') $('[data-diario-tab=\"hoy\"]').click();\n      if (name === 'vecinos') renderVecinos();\n      return;"
  );
}

fs.writeFileSync(jsPath, js, 'utf8');
console.log('JS ok');

const cssAppend = `
/* === Vecinos modal: refinado v3 (chinchetas, caras, tipografía) === */
.play-v3 .play-root.pc[data-capa="vecinos"] .capa-vecinos {
  overflow: visible !important;
}

.play-v3 .capa-vecinos .vecinos-pin {
  top: 10px;
  width: 22px;
  height: 22px;
  z-index: 6;
}

.play-v3 .capa-vecinos .vecinos-pin-l { left: 18px; }
.play-v3 .capa-vecinos .vecinos-pin-r { right: 18px; }

.play-v3 .capa-vecinos .vecinos-cab {
  padding-top: .35rem;
  padding-right: 1.85rem;
}

.play-v3 .capa-vecinos .vecinos-cab h2 {
  font-size: 1.22rem;
  letter-spacing: .08em;
}

.play-v3 .capa-vecinos .vecinos-cuenta {
  font-size: 1.15rem;
  letter-spacing: .02em;
}

.play-v3 .play-root.pc[data-capa="vecinos"] .capa-vecinos > [data-vecinos-list] {
  overflow-x: clip;
  overflow-y: auto;
  border-radius: 4px;
}

.play-v3 .capa-vecinos .vecino-cara {
  width: 3.75rem;
  height: 3.75rem;
  border-width: 2.5px;
  background: #fffdf6;
}

.play-v3 .capa-vecinos .vecino-cara img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: 50% 12%;
  display: block;
}

.play-v3 .capa-vecinos .vecino-nom {
  font-family: Nunito, "Segoe UI", sans-serif;
  font-size: .92rem;
  font-weight: 800;
  line-height: 1.2;
  color: #2a2218;
}

.play-v3 .capa-vecinos .vecinos-pie {
  font-size: .86rem;
}
`;

const cssPath = 'assets/css/play-v3-bloques-residencias.css';
let css = fs.readFileSync(cssPath, 'utf8');
const marker = '/* === Vecinos modal: refinado v3';
if (!css.includes(marker)) {
  fs.appendFileSync(cssPath, cssAppend, 'utf8');
  console.log('CSS appended');
} else {
  console.log('CSS already patched');
}
