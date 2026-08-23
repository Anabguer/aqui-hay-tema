'use strict';
/**
 * Pruebas de lógica del formulario Nuevo plan (SOLO vs ACOMPAÑADO).
 * Ejecutar: node tests/org_form_validacion_test.js
 */
const assert = require('assert');

let org = { modo: 'pareja', tipo: '', a: '', b: '', lugar: '', dia: null, hora: 17 };
const dom = { lugar: '', dia: '', hora: '' };

function orgSeleccionados() {
  if (org.modo === 'solo') return org.a ? [org.a] : [];
  const out = [];
  if (org.a) out.push(org.a);
  if (org.b && org.b !== org.a) out.push(org.b);
  return out;
}

function orgParticipantesListos() {
  const parts = orgSeleccionados();
  if (org.modo === 'solo') return parts.length >= 1;
  return parts.length >= 2;
}

function mensajeOrgParticipantesPendientes() {
  if (org.modo === 'solo') {
    return org.a ? '' : 'Elige a quién va el plan.';
  }
  const n = orgSeleccionados().length;
  if (n === 0) return 'Elige al menos dos vecinos.';
  if (n === 1) return 'Elige un acompañante para continuar.';
  return '';
}

function mensajeErrorOrgApi(r) {
  if (!r) return '';
  if (r.mensaje_ui) return r.mensaje_ui;
  const err = String(r.error || '');
  if (err === 'participantes_requeridos' || err === 'participantes_insuficientes') {
    if (org.modo === 'solo') return 'Elige a quién va el plan.';
    const pend = mensajeOrgParticipantesPendientes();
    return pend || 'Elige al menos dos vecinos.';
  }
  return '';
}

function validarOrgForm() {
  org.lugar = dom.lugar;
  org.dia = parseInt(dom.dia, 10);
  org.hora = parseInt(dom.hora, 10);
  if (org.modo === 'solo') {
    if (!org.a) return { ok: false, msg: 'Elige a quién va el plan.' };
    if (!org.lugar) return { ok: false, msg: 'Elige un lugar.' };
    if (!org.dia || !org.hora) return { ok: false, msg: 'Elige cuándo quedar.' };
    return { ok: true };
  }
  if (!org.a || !org.b) return { ok: false, msg: 'Elige al menos dos vecinos.' };
  if (org.a === org.b) return { ok: false, msg: 'Elige a dos personas distintas.' };
  if (!org.tipo) return { ok: false, msg: 'Elige qué buscáis.' };
  if (!org.lugar) return { ok: false, msg: 'Elige un lugar.' };
  if (!org.dia || !org.hora) return { ok: false, msg: 'Elige cuándo quedar.' };
  return { ok: true };
}

function buildPayload() {
  return {
    participantes: org.modo === 'solo' ? [org.a] : [org.a, org.b],
    dia: org.dia,
    hora: org.hora,
    tipo: org.modo === 'solo' ? 'individual' : (org.tipo || ''),
    lugar: org.lugar,
    modo: org.modo,
  };
}

function setOrgModo(modo) {
  org.modo = modo === 'solo' ? 'solo' : 'pareja';
  if (org.modo === 'solo') {
    org.b = '';
    org.tipo = 'individual';
  } else {
    org.tipo = '';
    if (org.a && org.b && org.a === org.b) org.b = '';
  }
}

function reset() {
  org = { modo: 'pareja', tipo: '', a: '', b: '', lugar: '', dia: null, hora: 17 };
  dom.lugar = '';
  dom.dia = '';
  dom.hora = '';
}

// 1. Acompañado + 1 persona → inválido con mensaje claro
reset();
org.modo = 'pareja';
org.a = 'res_sara';
assert.strictEqual(orgParticipantesListos(), false);
assert.strictEqual(mensajeOrgParticipantesPendientes(), 'Elige un acompañante para continuar.');
dom.lugar = 'lug_cine';
dom.dia = '1';
dom.hora = '18';
assert.strictEqual(validarOrgForm().ok, false);
assert.strictEqual(validarOrgForm().msg, 'Elige al menos dos vecinos.');

// 2. Acompañado + 2 personas → fecha/hora habilitada (validación pasa con datos)
reset();
org.modo = 'pareja';
org.a = 'res_sara';
org.b = 'res_luis';
org.tipo = 'conocerse';
dom.lugar = 'lug_cine';
dom.dia = '1';
dom.hora = '18';
assert.strictEqual(orgParticipantesListos(), true);
assert.strictEqual(validarOrgForm().ok, true);

// 3. Solo + 1 persona → válido
reset();
setOrgModo('solo');
org.a = 'res_sara';
dom.lugar = 'lug_cine';
dom.dia = '1';
dom.hora = '19';
assert.strictEqual(orgParticipantesListos(), true);
assert.strictEqual(validarOrgForm().ok, true);

// 4. Solo + 2 personas → no requerido / no debe provocar error de mínimo 2
reset();
setOrgModo('solo');
org.a = 'res_sara';
org.b = 'res_luis';
dom.lugar = 'lug_cine';
dom.dia = '1';
dom.hora = '19';
assert.strictEqual(orgParticipantesListos(), true);
const payloadSolo = buildPayload();
assert.deepStrictEqual(payloadSolo.participantes, ['res_sara']);
assert.strictEqual(payloadSolo.tipo, 'individual');
assert.strictEqual(validarOrgForm().ok, true);

// 5. Cambio Acompañado → Solo recalcula validación correctamente
reset();
org.modo = 'pareja';
org.a = 'res_sara';
org.b = 'res_luis';
org.tipo = 'conocerse';
dom.lugar = 'lug_cine';
dom.dia = '1';
dom.hora = '18';
assert.strictEqual(validarOrgForm().ok, true);
setOrgModo('solo');
assert.strictEqual(org.b, '');
assert.strictEqual(org.tipo, 'individual');
assert.strictEqual(mensajeErrorOrgApi({ error: 'participantes_requeridos' }), 'Elige a quién va el plan.');
assert.strictEqual(validarOrgForm().ok, true);

// 6. Cambio Solo → Acompañado vuelve a exigir 2
reset();
setOrgModo('solo');
org.a = 'res_sara';
dom.lugar = 'lug_cine';
dom.dia = '1';
dom.hora = '18';
assert.strictEqual(validarOrgForm().ok, true);
setOrgModo('pareja');
assert.strictEqual(orgParticipantesListos(), false);
assert.strictEqual(validarOrgForm().ok, false);
assert.strictEqual(validarOrgForm().msg, 'Elige al menos dos vecinos.');

// 7. Payload de plan individual contiene solo 1 participante
reset();
setOrgModo('solo');
org.a = 'res_sara';
dom.lugar = 'lug_cine';
dom.dia = '2';
dom.hora = '20';
const p = buildPayload();
assert.deepStrictEqual(p.participantes, ['res_sara']);
assert.strictEqual(p.modo, 'solo');
assert.strictEqual(p.tipo, 'individual');

console.log('org_form_validacion_test.js OK (8 casos)');
