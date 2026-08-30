'use strict';
/**
 * Regresión BLOQUE 1 — Eventos del Pueblo: selección de asistentes rota.
 *
 * Causa real: `plazas_disponibles` llega como STRING desde el JSON de estado.
 * El código original hacía `typeof plazas !== 'number'` y caía al fallback
 * ORG_MAX_VECINOS=2, impidiendo seleccionar >2 asistentes. Con min=3 del
 * catálogo, NINGUNA selección (1-2 < min, 3-8 > max=2) validaba → "0/8" y
 * botón nunca se habilitaba.
 *
 * Esta prueba replica la lógica ACTUAL de play-v3.js (orgMaxVecinos con
 * Number() coercion + elegibles async) y verifica el caso string del bug.
 */
const assert = require('assert');

const ORG_MAX_VECINOS = 2;

function orgEsEventoPueblo(org) {
  return org && (org.modo === 'evento' || org.modo === 'evento_pueblo') && !!org.evento_pueblo_id;
}

// --- Lógica EXACTA de assets/js/play-v3.js (post-fix BLOQUE 1) ---
function orgMaxVecinos(org) {
  if (!orgEsEventoPueblo(org)) return ORG_MAX_VECINOS;
  let plazas = org.evento_ctx && org.evento_ctx.plazas_disponibles;
  plazas = Number(plazas);
  if (!isFinite(plazas) || plazas <= 0) {
    if (plazas === 0) return 0;
    plazas = ORG_MAX_VECINOS;
  }
  const nEleg = orgEventoElegiblesCount(org);
  if (nEleg !== null) return Math.min(plazas, nEleg);
  return plazas;
}

function orgEventoElegibles(org) {
  if (!orgEsEventoPueblo(org)) return null;
  const ctx = org.evento_ctx || {};
  const lista = ctx.elegibles || (ctx.preset_organizar && ctx.preset_organizar.elegibles) || null;
  if (!lista || !lista.length) return null;
  const m = {};
  lista.forEach(function (x) { if (x && x.id) m[String(x.id)] = x; });
  return m;
}

function orgEventoElegiblesCount(org) {
  const m = orgEventoElegibles(org);
  if (!m) return null;
  let n = 0;
  for (const k in m) { if (m.hasOwnProperty(k) && m[k].elegible) n++; }
  return n;
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

// 1) BUG ORIGINAL: plazas como STRING "8" debe dar 8, NO el fallback 2.
const orgStr = {
  modo: 'evento_pueblo',
  evento_pueblo_id: 'evt_x',
  evento_ctx: { plazas_disponibles: '8', aforo_total: 8 },
  sel: []
};
assert.strictEqual(orgMaxVecinos(orgStr), 8, 'string "8" no debe caer a fallback 2');

// Con el bug original (sin Number) esto daba 2:
function orgMaxVecinosBuggy(org) {
  if (!orgEsEventoPueblo(org)) return ORG_MAX_VECINOS;
  const plazas = org.evento_ctx && org.evento_ctx.plazas_disponibles;
  if (typeof plazas !== 'number' || plazas <= 0) { // <-- bug
    if (plazas === 0) return 0;
    return ORG_MAX_VECINOS;
  }
  return plazas;
}
assert.strictEqual(orgMaxVecinosBuggy(orgStr), 2, 'sanidad: versión buggy da 2 (documenta el fallo)');

// 2) Selección de 3-8 asistentes debe ser posible (no bloqueada) con aforo 8.
['a','b','c','d','e','f','g','h'].forEach(function (id) {
  assert.strictEqual(toggleOrgPicker(orgStr, id).blocked, false, 'no bloquea con aforo 8: ' + id);
});
assert.strictEqual(toggleOrgPicker(orgStr, 'i').blocked, true, 'noveno asistente bloqueado');

// 3) Plan normal (no evento) sigue limitado a 2.
const orgNormal = { modo: null, evento_pueblo_id: null, evento_ctx: null, sel: [] };
toggleOrgPicker(orgNormal, 'a');
toggleOrgPicker(orgNormal, 'b');
assert.strictEqual(toggleOrgPicker(orgNormal, 'c').blocked, true, 'plan normal limitado a 2');
assert.strictEqual(orgNormal.sel.length, 2);

// 4) Elegibles limitan el máximo real (nEleg < aforo).
const orgEleg = {
  modo: 'evento_pueblo',
  evento_pueblo_id: 'evt_y',
  evento_ctx: {
    plazas_disponibles: 8,
    aforo_total: 8,
    elegibles: [
      { id: 'a', elegible: true },
      { id: 'b', elegible: true },
      { id: 'c', elegible: true },
      { id: 'd', elegible: false } // ocupado
    ]
  },
  sel: []
};
assert.strictEqual(orgMaxVecinos(orgEleg), 3, 'máximo = min(aforo, elegibles)');

// 5) Aforo completo → 0 plazas → bloquea.
const orgLleno = {
  modo: 'evento_pueblo',
  evento_pueblo_id: 'evt_z',
  evento_ctx: { plazas_disponibles: 0, aforo_total: 8 },
  sel: []
};
assert.strictEqual(orgMaxVecinos(orgLleno), 0, 'aforo 0 → 0 plazas');
assert.strictEqual(toggleOrgPicker(orgLleno, 'a').blocked, true, 'bloquea con aforo 0');

console.log('eventos_pueblo_org_aforo_test.js OK (BLOQUE 1 regression)');
