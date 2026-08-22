/**
 * Instrumentacion LAB: imprime eventos lab_audit de la API.
 * Solo se carga con ?lab=1.
 */
(function (global) {
  'use strict';

  function logEvento(ev) {
    if (!ev || typeof ev !== 'object') return;
    var pref = ev.prefijo || '[AHT DEBUG]';
    var tag = ev.tag || 'EVENTO';
    var datos = ev.datos || {};
    var label = pref + ' | ' + tag;
    console.log(label, datos);
    try {
      console.log(label + ' JSON\n' + JSON.stringify(datos, null, 2));
    } catch (e) { /* ignore */ }
    if (typeof console.groupCollapsed === 'function') {
      console.groupCollapsed(label);
      console.log(datos);
      console.groupEnd();
    }
  }

  function logPayload(payload) {
    if (!payload || !payload.lab_audit || !Array.isArray(payload.lab_audit.eventos)) return;
    console.log('[AHT LAB] ' + payload.lab_audit.eventos.length + ' evento(s) de auditoria');
    payload.lab_audit.eventos.forEach(logEvento);
  }

  global.AhtLabAudit = {
    log: logPayload,
    logEvento: logEvento
  };
})(typeof window !== 'undefined' ? window : globalThis);
