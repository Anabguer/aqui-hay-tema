const fs = require('fs');
const j = fs.readFileSync('assets/js/play-v3.js', 'utf8');

function assert(cond, msg) {
  if (!cond) throw new Error(msg);
}

assert(j.includes('function tokenDe(rid)'), 'falta tokenDe canónico');
assert(!j.includes('TOKEN_LOTE_FILES'), 'tokenDe no debe usar lote CRC32 en cliente');
assert(!j.includes('tokenLoteUrl'), 'tokenDe no debe tener tokenLoteUrl');
assert(j.includes('res.retrato_url'), 'tokenDe debe leer retrato_url del servidor');
assert(/function carasPlanHtml[\s\S]*?const img = tokenDe\(id\)/.test(j), 'carasPlanHtml debe usar tokenDe');
assert(/const tok = function \(id\) \{[\s\S]*?const img = tokenDe\(id\)/.test(j), 'parejas tok debe usar tokenDe');
assert(!/carasPlanHtml[\s\S]{0,200}cachePueblo\.tokens/.test(j), 'carasPlanHtml no debe leer cachePueblo.tokens directo');
assert(!/const tok = function \(id\)[\s\S]{0,200}cachePueblo\.tokens/.test(j), 'parejas tok no debe leer cachePueblo.tokens directo');

const refresh = j.match(/async function refresh\(\) \{[\s\S]*?\n  \}/);
assert(refresh, 'refresh no encontrado');
const body = refresh[0];
const puebloAt = body.indexOf('renderPueblo(');
const shellAt = body.indexOf('renderShellPanels(');
assert(puebloAt >= 0 && shellAt >= 0 && puebloAt < shellAt, 'renderPueblo debe ir antes de renderShellPanels');
assert(/cache:\s*'no-store'/.test(j), 'fetch API debe usar cache no-store');
assert(/\(cx\.visibles && cx\.visibles\.length\)/.test(j), 'renderPueblo debe caer a personas si visibles está vacío');

console.log('retratos_tokens_static_test OK');
