# Informe Turno 4 — Carlos

**Fecha:** 2026-08-18

## Implementado

1. **Pipeline contenido + validadores** — `PersonajeValidator`, `LugarValidator`, `ConfigPrevalidadaValidator`, `Catalog` con rechazo explícito (`ContentValidationException`). Informe fichas reales sin modificarlas.
2. **Casos de uso** — `PartidaLifecycle`, `ResidenteOperations`, `EncuentroOperations`, `RelojOperations`; `PartidaService` como fachada.
3. **EventBus real** — eventos en español (`partida_creada`, `encuentro_*`, `relacion_modificada`, `tiempo_avanzado`) + `DomainEventDispatcher` + audit correlacionado.
4. **Buzón/diario preparados** — `NarrativeTriggerRegistry`, `ContentReactionSubscriber` (desactivado por feature flag).
5. **Descubrimiento** — `DiscoveryEngine` + tests.
6. **Historial relación** — `RelacionHistorial` integrado en `RelacionEngine`.
7. **Disponibilidad** — `DisponibilidadEngine` + API `agenda.slots_compatibles`.
8. **Calendario dev** — `DevCalendarService` + UI dev.
9. **Inspector eventos** — `EventInspector` + UI dev.
10. **Simulación QA** — `SimulationRunner`, `dev/simulate.php`, tests longevidad 30/100/365.
11. **Export diagnóstico** — `DiagnosticExport` + `dev.diagnostico.export`.

## Fichas reales — REPORTE (no modificadas)

`per_i03.json` (Rocío) incumple contrato enum:
- `vida.hobby_principal`: `bingo` → enum_invalido
- `vida.estilo_social`: `sociable_selectiva` → enum_invalido
- `vida.rasgos_publicos[]`: `observadora`, `practica`, `socarrona` → enum_invalido

**Consecuencia:** `partida.nueva` con `debug_v0` (Rocío) falla validación al incorporar. Tests usan `test_fixtures_v0` + `per_qa_valid`.

## Tests

**68+ OK / 0 FAIL** (`php tests/run_all.php`)

## Simulaciones longevidad

| Días | ms aprox | Save | Eventos dominio | Invariantes |
|------|----------|------|-----------------|-------------|
| 30 | ~1.2s | ~527 KB | 123 | 0 rotas |
| 100 | ~4s | ~1.3 MB | — | 0 rotas |
| 365 | ~15s | ~2.1 MB | — | 0 rotas |

## % funcional

~28–30 % jugable (sin inflar). Mejora técnica significativa en validación, eventos, QA.

## BLOQUEADO_DECISION

- Qué campos empiezan ocultos por personaje (descubrimiento)
- Triggers narrativos buzón/diario (frecuencia, cupos, textos)
- Preferencias horarias subjetivas en disponibilidad
- Corrección enums fichas piloto (decisión diseño, no técnica)

## Neni puede probar

- `dev.php` → Calendario día, Inspector eventos, Simular 30d, Export diagnóstico
- `dev/simulate.php --days=30 --seed=xxx`
- `play.php` con config `test_fixtures_v0` (no `debug_v0` hasta corregir Rocío)

## Turno 5 recomendado

Corregir enums fichas piloto con Neni+ChatGPT; activar descubrimiento por reglas; conectar triggers narrativos; reducir tamaño save (audit cap tuning).
