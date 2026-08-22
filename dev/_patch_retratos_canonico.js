/**
 * Elimina fallback CRC32/lote en tokenDe; usa solo URLs del servidor.
 * node dev/_patch_retratos_canonico.js
 */
const fs = require('fs');
const p = require('path').join(__dirname, '..', 'assets', 'js', 'play-v3.js');
let s = fs.readFileSync(p, 'utf8');
const nl = s.includes('\r\n') ? '\r\n' : '\n';

const oldBlock = `  var TOKEN_LOTE_FILES = [
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

const newBlock = `  function tokenDe(rid) {
    if (!rid) return null;
    var tokens = cachePueblo && cachePueblo.tokens;
    if (tokens && tokens[rid] && tokens[rid].url) return tokens[rid].url;
    var res = cacheInsp && cacheInsp.residentes && cacheInsp.residentes[rid];
    if (res && res.retrato_url) return res.retrato_url;
    var hitUrl = null;
    (cachePueblo && cachePueblo.complejos || []).forEach(function (c) {
      (c.personas || []).forEach(function (p) {
        if (p.id === rid) hitUrl = p.token_url || hitUrl;
      });
      if (!hitUrl) {
        (c.visibles || []).forEach(function (p) {
          if (p.id === rid) hitUrl = p.token_url || hitUrl;
        });
      }
    });
    return hitUrl || null;
  }`;

const oldNl = oldBlock.replace(/\n/g, nl);
const newNl = newBlock.replace(/\n/g, nl);
if (!s.includes(oldNl)) {
  console.error('MISSING: tokenDe block');
  process.exit(1);
}
s = s.replace(oldNl, newNl);
fs.writeFileSync(p, s);
console.log('OK: tokenDe canónico sin lote CRC32');
