# Informe Turno 5 — Carlos

**Fecha:** 2026-08-18  
**Rocío:** ficha `per_i03.json` no modificada.

## Diagnóstico

Catálogo/contrato V0 demasiado estrecho. Los JSON de `data/catalogos/` ya contemplan los valores de Rocío; el validador T4 no.

## Técnico hecho

- Discovery: políticas configurables (`publico|oculto|parcial|por_evento|sin_politica`), sin asignar campos a Rocío.
- Save: caps configurables + archivo resumen; historial de relación conserva última entrada por par.
- QA sigue en `test_fixtures_v0`.
