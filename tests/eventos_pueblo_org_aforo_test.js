'use strict';
/**
 * Regresión: modo evento_pueblo usa plazas_disponibles, no ORG_MAX_VECINOS=2.
 */
const assert = require('assert');
const ORG_MAX_VECINOS = 2;

function orgEsEventoPueblo(org) {
  return org && (org.modo === 'evento' || org.modo === 'evento_pueblo') && !!org.evento_pueblo_id;
}

function orgMaxVecinos(org) {
  if (!orgEsEventoPueblo(org)) return ORG_MAX_VECINOS;
  var plazas = org.evento_ctx && org.evento_ctx.plazas_disponibles;
  if (plazas === 0) return 0;
  if (typeof plazas === 'number' && plazas > 0) return plazas;
  return ORG_MAX_VECINOS;
}

function toggleOrgPicker(org, id) {
  const sel = (org.sel || []).filter(Boolean);
  const idx = sel.indexOf(id);
  if (idx >= 0) {
    org.sel = sel.filter(function (x) { return x !== id; });
    return { blocked: false };
  }
  if (sel.length >= orgMaxVecinos(org)) return { blocked: true };
  org.sel = sel.concat([id]);
  return { blocked: false };
}

// Plan normal: max 2
let org = { modo: null, evento_pueblo_id: null, evento_ctx: null, sel: [] };
toggleOrgPicker(org, 'a');
toggleOrgPicker(org, 'b');
const third = toggleOrgPicker(org, 'c');
assert.strictEqual(third.blocked, true);
assert.strictEqual(org.sel.length, 2);

// Evento aforo 5 / 0 asistentes → hasta 5
org = {
  modo: 'evento_pueblo',
  evento_pueblo_id: 'evt_1',
  evento_ctx: { plazas_disponibles: 5, aforo_total: 5 },
  sel: []
};
['a', 'b', 'c', 'd', 'e'].forEach(function (id) {
  assert.strictEqual(toggleOrgPicker(org, id).blocked, false);
});
assert.strictEqual(toggleOrgPicker(org, 'f').blocked, true);

// Evento aforo 5 / 3 asistentes → máximo 2 nuevos
org = {
  modo: 'evento_pueblo',
  evento_pueblo_id: 'evt_2',
  evento_ctx: { plazas_disponibles: 2, aforo_total: 5, participantes_apuntados: ['x', 'y', 'z'] },
  sel: []
};
toggleOrgPicker(org, 'a');
toggleOrgPicker(org, 'b');
assert.strictEqual(toggleOrgPicker(org, 'c').blocked, true);

// Aforo completo
org = {
  modo: 'evento_pueblo',
  evento_pueblo_id: 'evt_3',
  evento_ctx: { plazas_disponibles: 0, aforo_total: 5 },
  sel: []
};
assert.strictEqual(orgMaxVecinos(org), 0);
assert.strictEqual(toggleOrgPicker(org, 'a').blocked, true);

console.log('eventos_pueblo_org_aforo_test.js OK');
