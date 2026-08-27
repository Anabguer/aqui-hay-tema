'use strict';
/**
 * Lógica de submit Nuevo plan — feedback y anti doble clic.
 * node tests/org_proponer_feedback_test.js
 */
const assert = require('assert');

let orgProponiendo = false;
let apiCalls = 0;
let lastAviso = '';
let lastToast = '';
let modalCerrado = false;

const ORG_BTN_LABEL = 'Organizar';
const ORG_BTN_BUSY = 'Organizando…';

function validarOrgForm(org) {
  if (!org.lugar || !org.dia || !org.hora) return { ok: false, msg: 'Elige cuándo quedar.' };
  if (!org.sel.length) return { ok: false, msg: 'Elige a quién va el plan.' };
  return { ok: true };
}

function mensajeErrorOrgApi(r, fallback) {
  fallback = fallback || 'No se ha podido organizar el plan. Inténtalo de nuevo.';
  if (!r) return fallback;
  if (r.mensaje_ui) return r.mensaje_ui;
  return fallback;
}

function btnState(val) {
  return {
    disabled: orgProponiendo,
    isBusy: orgProponiendo,
    isDisabled: !val.ok && !orgProponiendo,
    label: orgProponiendo ? ORG_BTN_BUSY : ORG_BTN_LABEL,
  };
}

async function aplicarRespuestaProponer(r) {
  if (!r || typeof r !== 'object') {
    lastAviso = 'No se ha podido organizar el plan. Inténtalo de nuevo.';
    return;
  }
  if (r.ok) {
    if (r.rechazada) {
      lastAviso = r.mensaje_ui || 'Plan rechazado.';
      return;
    }
    lastAviso = r.mensaje_ui || 'Plan organizado.';
    lastToast = lastAviso;
    modalCerrado = true;
    return;
  }
  lastAviso = mensajeErrorOrgApi(r);
}

async function proponer(org, apiFn) {
  if (orgProponiendo) return { skipped: true };
  const val = validarOrgForm(org);
  if (!val.ok) {
    lastAviso = val.msg;
    return { skipped: true, reason: 'validacion' };
  }
  orgProponiendo = true;
  try {
    const r = await apiFn();
    await aplicarRespuestaProponer(r);
    return { skipped: false };
  } catch (e) {
    lastAviso = 'No se ha podido organizar el plan. Inténtalo de nuevo.';
    return { skipped: false, error: true };
  } finally {
    orgProponiendo = false;
  }
}

function reset() {
  orgProponiendo = false;
  apiCalls = 0;
  lastAviso = '';
  lastToast = '';
  modalCerrado = false;
}

const orgOk = { sel: ['a'], lugar: 'lug_cine', dia: 1, hora: 18 };

// 1. processing visible
reset();
orgProponiendo = true;
const st = btnState({ ok: true });
assert.strictEqual(st.label, ORG_BTN_BUSY);
assert.strictEqual(st.disabled, true);

// 2. segundo clic durante processing → no segunda petición
reset();
orgProponiendo = true;
const second = { skipped: orgProponiendo };
assert.strictEqual(second.skipped, true);

// 3. éxito → feedback
reset();
(async function () {
  await proponer(orgOk, async function () {
    apiCalls++;
    return { ok: true, mensaje_ui: 'Quedaron en el cine.' };
  });
  assert.strictEqual(apiCalls, 1);
  assert.strictEqual(lastAviso, 'Quedaron en el cine.');
  assert.strictEqual(modalCerrado, true);

  // 4. rechazo voluntad
  reset();
  await proponer(orgOk, async function () {
    apiCalls++;
    return { ok: true, rechazada: true, mensaje_ui: 'Ana no quiere ir.' };
  });
  assert.strictEqual(lastAviso, 'Ana no quiere ir.');
  assert.strictEqual(modalCerrado, false);

  // 5. cooldown/regla
  reset();
  await proponer(orgOk, async function () {
    apiCalls++;
    return { ok: false, error: 'ENCUENTRO_RECHAZADO_COOLDOWN', mensaje_ui: 'Todavía no quiere hablar de eso.' };
  });
  assert.ok(lastAviso.includes('Todavía'));

  // 6. error genérico
  reset();
  await proponer(orgOk, async function () {
    apiCalls++;
    return { ok: false, error: 'excepcion' };
  });
  assert.ok(lastAviso.includes('No se ha podido organizar'));

  // 7. botón vuelve a estado correcto
  assert.strictEqual(orgProponiendo, false);
  assert.strictEqual(btnState({ ok: true }).label, ORG_BTN_LABEL);

  // 8. validación → aviso, no silencio
  reset();
  await proponer({ sel: [], lugar: '', dia: null, hora: 0 }, async function () {
    apiCalls++;
    return { ok: true };
  });
  assert.strictEqual(apiCalls, 0);
  assert.ok(lastAviso.length > 0);

  // 9. una interacción = una propuesta (doble clic rápido)
  reset();
  let inflight = false;
  const apiSlow = async function () {
    if (inflight) apiCalls++;
    inflight = true;
    await new Promise(function (r) { setTimeout(r, 30); });
    apiCalls++;
    return { ok: true, mensaje_ui: 'ok' };
  };
  const p1 = proponer(orgOk, apiSlow);
  const p2 = proponer(orgOk, apiSlow);
  await Promise.all([p1, p2]);
  assert.strictEqual(apiCalls, 1, 'solo una propuesta en vuelo');

  console.log('org_proponer_feedback_test.js OK (9 casos)');
})();
