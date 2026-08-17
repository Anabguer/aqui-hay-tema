# Informe implementación — Turno 2 (post revisión ChatGPT)

**Fecha:** 2026-08-18

## 1. % implementación

| | |
|---|---|
| **Antes (Turno 1)** | ~8 % |
| **Después (Turno 2)** | **~28 %** |

**Criterio:** mismos ~100 puntos ponderados del informe Turno 1; +20 puntos por Bloques 0–6 ejecutados con tests PASS.

## 2. Commits

Ver `git log --oneline` — baseline + commits por bloque funcional.

## 3. Tests

```
php tests/run_all.php → ALL PASS
- encuentros_test.php (5)
- rng_test.php (5)
- schema_test.php (4)
- smoke.php (10)
```

## 4. Bucle Neni en play.php

**SÍ — de principio a fin:**

1. Abrir `play.php` → Rocío en A01
2. Crear placeholder: usar `dev.php` → «Crear placeholder dev» (o ya hay 2 residentes tras dev)
3. **VER PERSONA** → clic hueco Bloque A
4. **PROPONER ENCUENTRO** → selects A/B, tipo, hora 19:00
5. **PROGRAMAR** → cafetería
6. **AVANZAR TIEMPO** → botón +1h (12 veces hasta pasar 20:00) o dev +12h
7. **RESOLVER** → automático vía `EncuentroLifecycle`
8. **VER CAMBIO** → ficha muestra último encuentro + relaciones actualizadas (placeholder)

Atajo dev: programar 19:00 → `reloj.avanzar` 12h en dev.php → recargar play.php → abrir ficha.

## 5. Placeholders exactos

- `_placeholder_resultado` en encuentros
- `_placeholder` en CompatibilityEvaluator / EncuentroResolver
- `_config_limite_diario.valor_dev` (null por defecto)
- `buzon_config.max_dia` / `diario_config.max_dia` null
- Mapa: `_placeholder_visual` iniciales
- NPC: `_placeholder_dev` en candidatos
- Economía: `_placeholder` en ledger
- Textos: `[PLACEHOLDER] Encuentro … terminado.`

## 6. BLOQUEADO_DECISION

- B/C día 1
- I10 tutorial
- Cifra intervenciones organizadas/día
- Cupos buzón/diario
- Catch-up eventos offline
- Fórmulas compatibilidad definitivas
- Economía balanceada
- Aforos lugares
- Política reiniciar vs nueva en multi-dispositivo (API documentada; reiniciar conserva id)

## 7. Decisiones NO tomadas

- No fijar 2 encuentros/día
- No hardcodear cupos 5–10 / 0–5
- No inventar probabilidades NPC
- No generar contenido buzón/diario
- No catch-up con eventos reales
- No incorporar B/C

## 8. Deuda técnica nueva

- `api/index.php` monolítico (crecerá)
- Reset parcial partida (solo encuentros) no implementado
- HardLayerEvaluator romance pendiente
- Presencia solo en encuentros programados (no autónomo aún)
- `dev.local.php` necesario localmente (gitignored)

## 9. Rutas

- Jugar: `/juegos/Aqui hay tema/play.php`
- Dev: `/juegos/Aqui hay tema/dev.php` (requiere `dev.local.php`)
- API: `/juegos/Aqui hay tema/api/index.php?action=encuentro.programar`

## 10. Restricciones

NO retratos, NO identidades, NO estilo visual, NO narrativa masiva. **Confirmado.**

## 11. Siguiente bloque

Tras cierre B/C → config población día 1. Paralelo: generadores buzón con mínimos auditables cuando existan cifras.
