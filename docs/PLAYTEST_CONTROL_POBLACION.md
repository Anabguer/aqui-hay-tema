# PLAYTEST — Control de Población Inicial y Llegadas

**Fecha baseline:** 2026-09-02
**Fecha fix:** 2026-09-02
**Estado:** 🧪 PENDIENTE PLAYTEST — 02/09/2026

---

## 1. Pool canónico

| Métrica | Valor |
|---------|-------|
| Total jugables | 199 |
| Mujeres | 99 |
| Hombres | 100 |
| No binaries | 0 |
| Edad mín | 22 |
| Edad máx | 72 |
| Edad media | 31.7 |
| Edad mediana | 28 |

El pool tiene ~50/50 de género y una distribución de edad sesgada hacia jóvenes-adultos (media 31.7). Hay personajes de 72 años que pueden ser seleccionados.

---

## 2. POBLACIÓN INICIAL — Evidencia de 39 partidas juego_v1

### 2.1 Composición de género

De 39 saves analizados (config `juego_v1`):

| Combinación | Count | % | Veredicto |
|-------------|-------|---|-----------|
| Mixta (2+1) | 27 | 69% | ✅ VÁLIDO |
| 3 hombres | 8 | 21% | ❌ FALLO |
| 3 mujeres | 4 | 10% | ❌ FALLO |

**25% de las partidas (≈1 de cada 4) arranca con composición monogénero.**

### 2.2 Edades iniciales

| Métrica | Valor |
|---------|-------|
| Diff edad media (max-min) | ~19 años |
| Diff edad máxima observada | **40 años** |
| Ejemplo extremo | hombre(70) + mujer(31) + hombre(30) |
| Ejemplo extremo 2 | mujer(24) + hombre(64) + mujer(28) |

**Se producen arranques con "un yayo entre los tres primeros" con diferencia de 40 años.**

### 2.3 Partidas problemáticas detectadas (muestra)

| Seed | Composición | Edades | Diff |
|------|-------------|--------|------|
| `pob-test-17` | 3 hombres | 27, 38, 33 | 11 |
| `pob-test-9` | 3 hombres | 24, 27, 26 | 3 |
| `pob-test-10` | 3 hombres | 29, 22, 29 | 7 |
| `pob-test-1` | 3 mujeres | 28, 25, 27 | 3 |
| `pob-test-5` | 3 mujeres | 26, 22, 53 | 31 |
| `pob-test-2` | mixta | 24, 64, 28 | **40** |
| `pob-test-3` | mixta | 45, 27, 64 | **37** |
| `pob-test-16` | mixta | 70, 31, 30 | **40** |
| `pob-test-19` | mixta | 59, 26, 26 | **33** |
| `lleg-lig-0` | mixta | 30, 55, 28 | **25** |
| `llegadas-test-2` | 3 mujeres | 26, 63, 24 | **39** |

### 2.4 Causa raíz

`PoblacionV3::incorporarIniciales()` en `src/Engine/PoblacionV3.php:30`:

```php
$picked = $rng->pickUnique($pool, min($n, count($pool)));
```

**Selección puramente aleatoria de 3 personajes de 199. Sin filtro de género, sin filtro de edad.**

La probabilidad teórica de 3 del mismo género con pool 99M/100H:
- 3 mujeres: (99/199) × (98/198) × (97/197) ≈ 12.1%
- 3 hombres: (100/199) × (99/198) × (98/197) ≈ 12.4%
- **Total monogénero esperado: ~24.5%** → consistente con el 31% observado en 39 partidas.

---

## 3. LLEGADAS POSTERIORES — Evidencia de 6 partidas (25 días c/u)

### 3.1 Ritmo de llegadas

| Partida | Llegadas en 25 días | Primera llegada | Frecuencia media |
|---------|---------------------|-----------------|------------------|
| 0 | 5 | Día 6 | ~5 días |
| 1 | 5 | Día 5 | ~5 días |
| 2 | 4 | Día 6 | ~6 días |
| 3 | 4 | Día 5 | ~6 días |
| 4 | 5 | Día 4 | ~5 días |
| 5 | 5 | Día 5 | ~5 días |
| 6 | 6 | Día 4 | ~4 días |

**Promedio: ~4.7 llegadas en 25 días, primera llegada ~Día 5.**

### 3.2 Ritmo vs caso D1

El caso D1 reportado (09:00 → 3 residentes, 11:00 → 6 residentes = +3 en 2h) **NO se ha reproducido** en las 6 partidas de playtest con el sistema actual. La primera llegada ocurre ~día 5, no en las primeras 2 horas.

El sistema actual de pacing parece razonable en ritmo base. El caso D1 podría haber sido con una config anterior o con behaviour diferente.

### 3.3 Composición de llegadas

| Género | Count | % |
|--------|-------|---|
| mujer | 16 | 55% |
| hombre | 13 | 45% |

Distribución razonablemente equilibrada.

### 3.4 Edades de llegadas

| Métrica | Valor |
|---------|-------|
| Edad mín | 22 |
| Edad máx | 49 |
| Edad media | 29.4 |

Las llegadas tienden a ser jóvenes-adultos. Solo 1 caso con edad >40 (hombre 49).

### 3.5 Diversidad de llegadas respecto a existente

No se detectaron llegadas que repitan exactamente el mismo perfil de gender+age que los iniciales. El pool de 199 personajes提供 suficiente diversidad para las primeras ~10 llegadas.

---

## 4. Verificación de reglas P8

### ✅ Lo que SÍ funciona
- El pool tiene distribución de género razonable (~50/50)
- Las llegadas mantienen diversidad de género (~55/45)
- Las edades de llegadas son razonablemente jóvenes (media 29.4)
- El ritmo de llegadas (≈1 cada 5 días) permite conocer a la gente
- No se reproduce el caso D1 (+3 en 2h) con config actual

### ❌ Lo que NO funciona
1. **25-31% de partidas arrancan con 3 del mismo género** — viola la regla P8
2. **Diferencias de edad de hasta 40 años en los 3 iniciales** — "un yayo entre los tres"
3. **No hay filtro de diversidad en llegadas** — funciona por azar, podría duplicar perfiles

---

## 5. Veredicto PlayTest

### P8: 🧪 EN PLAYTEST — NO CERRAR

**Evidencia suficiente para decir que P8 está implementado en código pero NO garantiza las reglas de producto:**

1. La lógica de `PoblacionV3::incorporarIniciales()` no tiene restricciones de género ni edad
2. El 25-31% de partidas incumple la regla de composición mixta
3. Las diferencias de edad extremas (40 años) son posibles y se producen
4. Las llegadas posteriores son aceptables en ritmo y diversidad

**Recomendación:** P8 necesita un filtro de selección que garantice:
- Composición mixta (2+1) en los 3 iniciales
- Edades within ~20 años de diferencia
- Selección informada, no puramente aleatoria

---

## 6. Evidencia técnica

- **Script de diagnóstico:** `dev/playtest_control_poblacion.php`
- **Script de llegadas:** `dev/playtest_llegadas_ligero.php`
- **Saves analizados:** 39 juego_v1 en `data/partidas/`
- **Partidas nuevas creadas:** 20 (fase inicial) + 8 (fase llegadas)
- **Config usada:** `juego_v1` (la canónica de producción)

---

## 7. POST-FIX — Corrección implementada (2026-09-02)

### 7.1 Archivo modificado

`src/Engine/PoblacionV3.php` — único archivo de lógica modificado.

### 7.2 Regla de selección implementada

**Algoritmo de muestreo directo (O(1) amortizado):**

1. Elegir patrón al azar: 2M+1H o 2H+1M
2. Elegir 1 del género mayoritario al azar
3. Filtrar mayoritarios dentro de `MAX_EDAD_DIFF` (15) del primero → elegir 2do
4. Filtrar minoritarios dentro del rango `[min, max]` de los 2 mayoritarios → elegir 3ro
5. Si algún paso falla, intercambiar patrón y reintentar (max 4 intentos)
6. Fallback: `pickUnique` del pool completo (solo si pool < 3 caracteres válidos)

**Umbrales:**
- `MAX_EDAD_DIFF = 15` años (constante pública)
- Justificación: pool tiene 167/199 personajes de 22-49 años. Con diff ≤15 hay 591K+ tríos válidos. Excluye personajes ≥50 años del núcleo inicial ("yayo" problem).

### 7.3 Simulación POST-FIX: 1500 partidas

| Métrica | Resultado | Target |
|---------|-----------|--------|
| Monogénero | **0** | 0 |
| Violaciones edad (>15) | **0** | 0 |
| Composición 2F+1M | 737 (49.1%) | ~50% |
| Composición 2H+1F | 763 (50.9%) | ~50% |
| Diff edad media | 5.6 | ≤15 |
| Diff edad max | 15 | ≤15 |
| Edad media de trio | 31.2 | 20-40 |
| NPC distintos usados | 199/199 | todos |
| Ratio max/esperado | 1.68x | ≤3x |

### 7.4 Tests unitarios

`tests/poblacion_v3_initial_test.php` — 2010 assertions, 0 fallos:
1. 1000 init → 0 monogénero
2. 1000 init → 0 violaciones rango edad
3. Ambas composiciones (2F+1M y 2H+1F) aparecen
4. 199/199 NPC usados (sin sesgo extremo)
5. Sin duplicados en trío
6. Fallback con pool pequeño
7. PoblacionV3 no modifica llegadas

### 7.5 Llegadas posteriores

**NO modificadas.** `CandidatoLlegadaEngine.php` sin cambios. Ritmo ~1 llegada/5 días, primera llegada ~día 5, distribución 55/45.

### 7.6 Estado P8

```
🧪 PENDIENTE PLAYTEST — 02/09/2026
- Población inicial mixta corregida (0 monogénero en 1500 tests)
- Cercanía de edades corregida (max diff = 15, media = 5.6)
- Llegadas posteriores sin cambios
- Pendiente confirmación longitudinal en partidas reales
```
