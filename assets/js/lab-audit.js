/**
 * Instrumentación LAB: imprime en consola los eventos lab_audit de la API.
 * Solo se carga con ?lab=1.
 */
(function (global) {
  'use strict';

  function logEvento(ev) {
    if (!ev || typeof ev !== 'object') return;
    var pref = ev.prefijo || '[AHT DEBUG]';
    var tag = ev.tag || 'EVENTO';
    var datos = ev.datos || {};
    var label = pref + ' · ' + tag + (ev.ts ? ' · ' + ev.ts : '');
    if (typeof console.groupCollapsed === 'function') {
      console.groupCollapsed(label);
      console.log(datos);
      try {
        console.log(JSON.stringify(datos, null, 2));
      } catch (e) { /* ignore */ }
      console.groupEnd();
    } else {
      console.log(label, datos);
    }
  }

  function logPayload(payload) {
    if (!payload || !payload.lab_audit || !Array.isArray(payload.lab_audit.eventos)) return;
    payload.lab_audit.eventos.forEach(logEvento);
  }

  global.AhtLabAudit = {
    log: logPayload,
    logEvento: logEvento,
  };
})(typeof window !== 'undefined' ? window : globalThis);
