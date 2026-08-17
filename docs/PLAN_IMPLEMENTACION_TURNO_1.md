# Plan de implementación — Turno 1 (bloque A)

**Fecha:** 2026-08-18 · **Estado:** ejecutado en este turno

## Inventario código previo

| Elemento | Estado |
| --- | --- |
| `index.php` | Landing estática, sin bucle de juego |
| `data/**/*.json` | Plantillas y catálogos; `per_i03` (Rocío) ficha completa |
| Motor PHP / API / UI jugable | **No existía** |
| Tests | **No existían** |
| Git | **No inicializado** en carpeta proyecto |

## Clasificación de trabajo

### A — Seguro para implementar ahora

1. **Estado y persistencia de partida** — seed, crear/guardar/cargar/reiniciar, reloj 1:1, catch-up offline (estructura sin eventos).
2. **Modelo residentes + Bloque A** — 16 viviendas A01–A16, asignación automática primer hueco, Rocío día 1, placeholders explícitos.
3. **Agenda base** — 24×1h, capas estructural/programado, plantillas por `ocupacion`, anti-doble-reserva.
4. **Motor citas** — programar/cancelar/estados, comprobación agenda, lugar operativo (solo cafetería día 1).
5. **UI técnica Bloque A + ficha provisional** — grid 16, abrir residente, datos reales de catálogo.
6. **Base relaciones** — social + romance (4 variables), sin fórmulas ni narrativa.
7. **Harness dev** — avanzar reloj, residente prueba, citas, relaciones, save/load, inspección.

### B — Necesita decisión Neni + ChatGPT

- Residentes B y C del día 1 (slots I06/I10 u otros).
- I10 en tutorial vs llegada normal.
- Contenido buzón/diario, coletillas, rechazos narrativos de agenda.
- Economía (dinero/fama cifras), aforos, desbloqueos adicionales.

### C — Esperar micro-simulación

- Balance compatibilidad, resultados de citas, progresión romance derivada.

### D — Esperar estilo visual

- Retratos, UI final, mapa ilustrado, caras.

## Orden por dependencias

```text
autoload + JsonFile + Catalog
  → PartidaSchema + Reloj + BloqueA
  → ResidenteRuntime + AgendaEngine
  → CitaEngine + RelacionEngine
  → PartidaService + CatchUpOffline (estructura)
  → api/index.php
  → play.php + dev.php + CSS/JS
  → tests/smoke.php
```

## BLOQUEADO_DECISION (no bloquea bloque A)

| Cuestión | Por qué | Alternativas | Afecta |
| --- | --- | --- | --- |
| B/C día 1 | No cerrado | Esperar elección pool | Población inicial >1 |
| Resultado cita | Sin fórmulas | Motor deja `resultado: null` | Narrativa post-cita |
| Plan autónomo NPC | Vida autónoma no implementada | Capa vacía | Rutina fuera de cita |

## RECORTE_V0

Ninguno planificado. Lugares no operativos día 1 quedan fuera de selector de citas (solo `lug_cafeteria`).
