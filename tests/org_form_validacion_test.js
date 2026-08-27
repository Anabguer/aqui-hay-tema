'use strict';
/**
 * Pruebas de lógica del formulario Nuevo plan (selección 1–2 vecinos).
 * Ejecutar: node tests/org_form_validacion_test.js
 */
const assert = require('assert');

const ORG_MAX_VECINOS = 2;

let org = { sel: [], tipo: '', lugar: '', dia: null, hora: 17 };
const dom = { lugar: '', dia: '', hora: '' };

function orgSeleccionados() {
  return (org.sel || []).filter(Boolean);
}

function orgModo() {
  return orgSeleccionados().length <= 1 ? 'solo' : 'pareja';
}

function toggleOrgPicker(id) {
  if (!id) return;
  const sel = orgSeleccionados();
  const idx = sel.indexOf(id);
  if (idx >= 0) {
    org.sel = sel.filter(function (x) { return x !== id; });
  } else if (sel.length >= ORG_MAX_VECINOS) {
    return { blocked: true };
  } else {
    org.sel = sel.concat([id]);
  }
  if (orgModo() === 'solo') org.tipo = 'individual';
  else if (org.tipo === 'individual') org.tipo = '';
  return { blocked: false };
}

function orgParticipantesListos() {
  const parts = orgSeleccionados();
  if (orgModo() === 'solo') return parts.length >= 1;
  return parts.length >= 2;
}

function mensajeOrgParticipantesPendientes() {
  if (orgModo() === 'solo') {
    return orgSeleccionados().length ? '' : 'Elige a quién va el plan.';
  }
  const n = orgSeleccionados().length;
  if (n === 0) return 'Elige al menos dos vecinos.';
  if (n === 1) return 'Elige un acompañante para continuar.';
  return '';
}

function validarOrgForm() {
  org.lugar = dom.lugar;
  org.dia = parseInt(dom.dia, 10);
  org.hora = parseInt(dom.hora, 10);
  const parts = orgSeleccionados();
  if (orgModo() === 'solo') {
    if (!parts.length) return { ok: false, msg: 'Elige a quién va el plan.' };
    if (!org.lugar) return { ok: false, msg: 'Elige un lugar.' };
    if (!org.dia || !org.hora) return { ok: false, msg: 'Elige cuándo quedar.' };
    return { ok: true };
  }
  if (parts.length < 2) return { ok: false, msg: 'Elige al menos dos vecinos.' };
  if (new Set(parts).size !== parts.length) return { ok: false, msg: 'Elige a personas distintas.' };
  if (!org.tipo) return { ok: false, msg: 'Elige qué buscáis.' };
  if (!org.lugar) return { ok: false, msg: 'Elige un lugar.' };
  if (!org.dia || !org.hora) return { ok: false, msg: 'Elige cuándo quedar.' };
  return { ok: true };
}

function buildPayload() {
  const parts = orgSeleccionados();
  return {
    participantes: parts,
    dia: org.dia,
    hora: org.hora,
    tipo: orgModo() === 'solo' ? 'individual' : (org.tipo || ''),
    lugar: org.lugar,
    modo: orgModo(),
  };
}

function reset() {
  org = { sel: [], tipo: '', lugar: '', dia: null, hora: 17 };
  dom.lugar = '';
  dom.dia = '';
  dom.hora = '';
}

// 1. Plan con 1 residente → válido
reset();
toggleOrgPicker('res_sara');
dom.lugar = 'lug_cine';
dom.dia = '1';
dom.hora = '19';
assert.strictEqual(orgParticipantesListos(), true);
assert.strictEqual(validarOrgForm().ok, true);
assert.deepStrictEqual(buildPayload().participantes, ['res_sara']);

// 2. Plan con 2 residentes → válido
reset();
toggleOrgPicker('res_sara');
toggleOrgPicker('res_luis');
org.tipo = 'conocerse';
dom.lugar = 'lug_cine';
dom.dia = '1';
dom.hora = '18';
assert.strictEqual(orgParticipantesListos(), true);
assert.strictEqual(validarOrgForm().ok, true);
assert.deepStrictEqual(buildPayload().participantes, ['res_sara', 'res_luis']);

// 3. Plan con 3 residentes → bloqueado en UI
reset();
toggleOrgPicker('res_sara');
toggleOrgPicker('res_luis');
const third = toggleOrgPicker('res_maria');
assert.strictEqual(third.blocked, true);
assert.strictEqual(orgSeleccionados().length, 2);

// 4. Con 2 residentes pero sin tipo → inválido en modo acompañado
reset();
toggleOrgPicker('res_sara');
toggleOrgPicker('res_luis');
dom.lugar = 'lug_cine';
dom.dia = '1';
dom.hora = '18';
assert.strictEqual(orgModo(), 'pareja');
assert.strictEqual(validarOrgForm().ok, false);
assert.strictEqual(validarOrgForm().msg, 'Elige qué buscáis.');

// 5. Cambio 2 → 1 recalcula modo solo
reset();
toggleOrgPicker('res_sara');
toggleOrgPicker('res_luis');
toggleOrgPicker('res_luis');
assert.strictEqual(orgModo(), 'solo');
assert.strictEqual(org.tipo, 'individual');

console.log('org_form_validacion_test.js OK (5 casos)');
