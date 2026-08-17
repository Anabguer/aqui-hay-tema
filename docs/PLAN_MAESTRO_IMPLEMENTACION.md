# Plan Maestro de Implementación — Aquí Hay Tema

**Versión:** 2026-08-18 (revisión ChatGPT incorporada)  
**Punto de partida:** ~8 % (Turno 1) → **~28 %** tras Bloques 0–6 ejecutados en Turno 2

---

## 1. Principios arquitectónicos (revisión ChatGPT)

### Encuentro / Plan = unidad principal
- **`EncuentroEngine`** es la abstracción central; `CitaEngine` es wrapper retrocompatible (`tipo: romantico`).
- Un encuentro puede producir: delta social, delta romance, conflicto, descubrimiento, ninguno, o varios a la vez.
- **`EncuentroResolver`** devuelve canales independientes (`delta_social`, `delta_romance`).

### Intervenciones Celestine vs autonomía NPC
- **`intervenciones_organizadas_max_dia`**: configurable, **null** = sin límite activo; `valor_dev` solo placeholder.
- **NO canonizar «2/día»**. NPC autónomo no consume ese presupuesto.

### Buzón / Diario
- Arquitectura con límites configurables (`max_dia: null`); **cifras 5–10 / 0–5 NO cerradas**.
- Trazabilidad de origen en cada mensaje/entrada.

### RNG centralizado
- **`RngService`**: seed + state en partida; reproducible QA.

### Compatibilidad
- **`CompatibilityEvaluator`**: canales `social`, `romantic`, `contextual` separados.
- Veto romántico (orientación/parentesco) **no** bloquea amistad/conocerse.

### Agenda genérica
- Reservas: trabajo, sueño, recurrente, encuentro, autónomo, temporal.
- La agenda solo sabe ocupación + origen; semántica la define el motor creador.

### Partidas
- **`partida.nueva`**: nuevo `partida_id`.
- **`partida.reiniciar`**: conserva `partida_id`, resetea estado.
- **BLOQUEADO_DECISION producto:** política de IDs en producción multi-dispositivo.

---

## 2. Estado actual del código (post Turno 2)

| Sistema | Archivo(s) | Estado |
|---------|------------|--------|
| Schema v2 + migración | `SchemaMigrator.php` | Funciona |
| RNG | `RngService.php` | Funciona + tests |
| Logging | `GameLogger.php` | Funciona |
| Encuentros | `EncuentroEngine.php`, `EncuentroLifecycle.php`, `EncuentroResolver.php` | Funciona |
| Compatibilidad | `Compatibility/CompatibilityEvaluator.php`, `PlaceholderEvaluator.php` | Contrato + placeholder |
| Agenda genérica | `AgendaEngine.php` | Funciona |
| Mapa/presencia | `PresenciaEngine.php` | Técnico placeholder |
| Buzón/Diario | `BuzonEngine.php`, `DiarioEngine.php` | Estructura vacía |
| NPC autónomo | `AutonomousPlanner.php` | Arquitectura + dev |
| Economía | `EconomyLedger.php` | Ledger sin cifras |
| UI bucle | `play.php`, `play.js` | Bucle Neni end-to-end |
| Dev gate | `dev_gate.php`, `dev.local.php` | Funciona |
| Tests | `tests/*.php`, `run_all.php` | ALL PASS |

---

## 3. Bloques de implementación

### Bloque 0 — Fundamentos ✅ EJECUTADO
- **+%:** ~2 → ~10 %
- SchemaMigrator, RngService, GameLogger, dev gate, tests modulares

### Bloque 1 — Bucle encuentro jugable ✅ EJECUTADO
- **+%:** ~5 → ~15 %
- EncuentroEngine, lifecycle, resolver placeholder, play.php bucle completo

### Bloque 2 — Mapa técnico ✅ EJECUTADO
- **+%:** ~4 → ~19 %
- PresenciaEngine, panel mapa en play.php, iniciales/candados

### Bloque 3 — Buzón/Diario estructura ✅ EJECUTADO
- **+%:** ~3 → ~22 %
- API listar/crear_dev, trazabilidad origen, UI vacía

### Bloque 4 — Compatibilidad contrato ✅ EJECUTADO
- **+%:** ~3 → ~25 %
- Interface + PlaceholderEvaluator, harness en encuentros_test

### Bloque 5 — NPC autonomía arquitectura ✅ EJECUTADO
- **+%:** ~2 → ~27 %
- AutonomousPlanner + log candidatos/elegido

### Bloque 6 — Economía arquitectura ✅ EJECUTADO
- **+%:** ~1 → ~28 %
- EconomyLedger dinero/fama separados

### Bloque 7 — Catch-up offline eventos ⏸ NO EJECUTADO
- Requiere diseño eventos (B)

### Bloque 8 — Población B/C ⏸ BLOQUEADO
- BLOQUEADO_DECISION: residentes día 1

### Bloque 9 — Contenido producción ⏸ NO EJECUTADO
- Catálogos narrativos, 15 fichas, micros

---

## 4. Clasificación A / B / C

### A — Carlos solo
Bloques 0–6 (hecho), tests, logging, migraciones, UI técnica

### B — Neni + ChatGPT
- B/C día 1, I10 tutorial
- Cifras intervenciones/día, buzón/diario cupos
- Fórmulas compatibilidad, micro-sim
- Catálogos narrativos, economía balanceada
- Aforos mapa

### C — Arquitectura sí, contenido no
- Generadores buzón/diario
- Resultados encuentro narrativos
- NPC autónomo rico
- Catch-up eventos

---

## 5. Estrategia de tests

```
tests/run_all.php       # ejecutor
tests/smoke.php         # integración
tests/rng_test.php
tests/schema_test.php
tests/encuentros_test.php
```

**Invariantes cubiertos:**
- Doble reserva
- Transiciones encuentro válidas
- Social ≠ romance independientes
- RNG reproducible save/load
- Schema migration v1→v2
- Encuentro resuelto al avanzar reloj

**Pendientes:** veto romántico no bloquea amistad (cuando exista HardLayerEvaluator), catch-up, NPC autónomo integrado.

---

## 6. Modo dev (roadmap)

| Herramienta | Estado |
|-------------|--------|
| Avanzar reloj | ✅ |
| Placeholder residente | ✅ |
| Programar encuentro | ✅ |
| Inspeccionar JSON | ✅ |
| Forzar mensaje buzón | ✅ API buzon.crear_dev |
| Plan NPC | ✅ npc.planificar_dev |
| Economía dev | ✅ economia.registrar_dev |
| Reset parcial | Pendiente |
| Reproducir seed | ✅ via partida seed + rng state |

---

## 7. Logging

Tipos: `encuentro_programado`, `agenda_rechazo`, `encuentro_terminado`, `encuentro_resuelto`, `relacion_delta_social`, `relacion_delta_romance`, `npc_autonomo_plan`

Almacenamiento: `logs/partida_*.jsonl` + `event_log[]` en partida (últimas 200).

---

## 8. Estimación % honesta

| Hito | % |
|------|---|
| Turno 1 | ~8 % |
| Tras Bloques 0–6 (Turno 2) | **~28 %** |
| Con contenido mínimo auditado | ~45–55 % |
| V0 completo REGLAS_NO_NEGOCIABLES | ~70 %+ |

---

## 9. BLOQUEADO_DECISION

| Cuestión | Afecta |
|----------|--------|
| B/C día 1 | Población inicial |
| I10 tutorial | Onboarding |
| Cifra intervenciones/día | Balance Celestine |
| Cupos buzón/diario | Ruido vs silencio |
| Reiniciar vs nueva en UX multi-dispositivo | Persistencia |
| Catch-up offline eventos | Bloque 7 |

---

## 10. Siguiente bloque recomendado

**Cuando se cierre B/C:** incorporar residentes + ajustar config `debug_v0`.  
**Paralelo visual (Neni):** Bloque 7 estructura catch-up sin eventos, o generadores buzón con mínimos auditables tras cifras.

---

## 11. RECORTE_V0

Ninguno registrado.

---

## 12. Confirmación restricciones

Sin retratos, sin identidades tocadas, sin fórmulas definitivas, sin economía balanceada, sin narrativa masiva.
