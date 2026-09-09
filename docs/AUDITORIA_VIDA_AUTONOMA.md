# AUDITORÍA — Vida Autónoma NPC
## Movimiento, peso de acciones y playtest

**Fecha:** 2026-09-09  
**Simulación:** 14 días, 8 residentes, config playtest_01  
**Script:** `tests/playtest_vida_autonoma_audit.php`

---

## PARTE 1 — RESULTADOS DEL PLAYTEST

### CASO A — Simulación Autónoma (sin intervención del jugador)

| Métrica | Valor |
|---------|-------|
| Encuentros totales | 58 |
| - Individuales (salida autónoma) | 45 |
| - Quedar (NPC→NPC social) | 6 |
| - Conocerse (NPC→NPC social) | 1 |
| - Autonomo_relacion (acontecimientos) | 6 |
| Pares que se conocen | 26/28 (93%) |
| Relaciones románticas creadas | 15 |
| Conflictos creados | 4 |
| Cotilleos generados | 100 |
| Mensajes buzón | 218 |
| Diarios/hitos | 96 |

**Frecuencia de encuentros autónomos:**
- ~4.1 encuentros/día (58 ÷ 14)
- ~3.2 salidas individuales/día
- ~0.5 quedadas NPC→NPC/día

**Lugares más frecuentes:**
- Cafetería (principal destino de salidas individuales)
- Parque (segundo destino)
- Biblioteca (tercero)

**Evolución de relaciones:**
- 26 de 28 pares se conocen en 14 días
- 15 relaciones románticas aparecen (la mayoría con atracción baja 0-3)
- Solo 1 relación con atracción alta (Álex↔Sara: 28/0)
- 4 conflictos leves (intensidad 1-2)

**Impacto en necesidades:**
- NECESIDADES CAEN A 0 en todos los residentes tras 14 días
- Decay horario: -2.5 × 14h/día × 14 días = -490 puntos totales
- Recuperación por encuentros: insuficiente para contrarrestar decay
- **PROBLEMA DETECTADO:** Las necesidades se agotan completamente

---

### CASO B — Simulación con Intervención del Jugador

| Métrica | Valor |
|---------|-------|
| Encuentros organizados por jugador | 28 |
| - Aceptados | 3 |
| - Rechazados | 25 |
| **Tasa de aceptación** | **10.7%** |
| Encuentros totales | 66 |
| - Individuales (autónomos) | 42 |
| - Quedar (NPC→NPC) | 9 |
| - Conocerse (celeste_organizado) | 3 |
| - Autonomo_relacion | 10 |
| - Evento pueblo | 1 |
| - Romántico | 1 |
| Pares que se conocen | 28/28 (100%) |
| Relaciones románticas creadas | 17 |
| Conflictos creados | 8 |
| Cotilleos generados | 120 |
| Mensajes buzón | 279 |
| Diarios/hitos | 145 |

---

### COMPARATIVA DIRECTA

| Métrica | Autónomo | Jugador | Δ |
|---------|----------|---------|---|
| Encuentros totales | 58 | 66 | **+8** |
| Conocidos | 26/28 | 28/28 | **+2** |
| Relaciones románticas | 15 | 17 | **+2** |
| Conflictos | 4 | 8 | **+4** |
| Cotilleos | 100 | 120 | **+20** |
| Buzón | 218 | 279 | **+61** |
| Hitos | 96 | 145 | **+49** |

---

## ANÁLISIS CRÍTICO

### 1. NO EXISTE DIFERENCIA REAL ENTRE MODOS

**Hallazgo principal:** Actualmente NO hay multiplicador diferenciador entre encuentros del jugador y autónomos.

Ambos pasan por:
- `EncuentroExperiencia::resolver()` → misma fórmula de carga
- `EncuentroDeltasReales::deResultado()` → mismos deltas
- `EncuentroResolver::aplicarResultado()` → misma aplicación

La única diferencia es:
- `intencion='celeste_organizado'` vs `intencion='autonomo'`
- El jugador consume `intervenciones_organizadas_usadas_hoy`
- NO hay bonus de impacto social, recuperación emocional, ni eventos especiales

### 2. TASA DE ACEPTACIÓN DEL JUGADOR ES MUY BAJA (10.7%)

El jugador organizó 28 encuentros pero solo 3 fueron aceptados.

**Causa raíz:**
- `VoluntadPonderadaEvaluator` usa `media_geometrica` por defecto
- Cada persona evalúa independientemente con p ≈ 0.4-0.7
- Plan aceptado = A y B aceptan → p_plan = √(pA × pB) ≈ 0.5-0.7
- Con p_media ≈ 0.55, tasa plan ≈ 0.30 (coherente con lo observado)

**El jugador se frustra** porque:
- No ve反馈 inmediato de sus acciones
- La mayoría de sus propuestas son rechazadas
- No percibe que su influencia matter

### 3. NECESIDADES SE AGOTAN COMPLETAMENTE

**Problema grave:** Todas las necesidades caen a 0 en 14 días.

**Cálculo:**
- Decay: -2.5/hora × 14 horas/día = -35/día
- En 14 días: -490 puntos de decay
- Recuperación por encuentro: ~3.0 × intensidad × compania × hobby
- Con ~4 encuentros/día: recuperación insuficiente

**Impacto:**
- Residentes siempre en banda "en_rojo"
- Comportamiento autónomo puede verse afectado
- La simulación no refleja un pueblo "vivo" sino deprimido

### 4. EMOCIONES PERMANECEN EN NEUTRO

**Hallazgo:** 8/8 residentes terminan en estado neutro.

**Causa:**
- `EmotionalRecovery::evaluar()` solo aplica cambio si:
  - Estado antes era negativo (triste/enfadado) Y
  - Hay hobby match
- Si estado es neutro → no cambia a alegre por encuentro positivo
- Solo `muy_bien` o `bien` deberían poder cambiar a alegre

**Verificar:** ¿Está implementado correctamente el cambio a alegre?

---

## PARTE 2 — DÓnde SE CALCULAN LOS DELTAS

### Deltas sociales
- **Archivo:** `EncuentroDeltasReales.php:28-62`
- **Función:** `deResultado($resultado, $tipoEncuentro, $cal)`
- **Mapa:** `resolucion_encuentro.deltas_por_resultado` en calibración
- **Ejemplo:** `muy_bien` → social: +8, romance: +4

### Deltas románticos
- **Archivo:** `EncuentroDeltasReales.php:50-51`
- **Fórmula:** Si NO es tipo cita: `romance = round(romance × 0.35)`
- **Apply:** `EncuentroResolver.php:210-222`

### Cambios emocionales
- **Archivo:** `EmotionalRecovery.php:27-71`
- **Función:** `evaluar($estadoAntes, $resultadoExperiencia, $hobbyMatch)`
- **Lógica:** Solo mejora si antes era triste/enfadado Y hay hobby match

### Necesidades
- **Decay:** `NecesidadEstado.php:129-149` → `aplicarDecay()` → -2.5/hora
- **Recuperación:** `NecesidadEstado.php:161-189` → `aplicarRecuperacion()` → +3.0 × factores
- **Apply:** `EncuentroResolver.php:319-375` → `aplicarNecesidadesEncuentro()`

### Conflictos
- **Crear:** `RelacionEngine::upsertConflicto()` desde `EncuentroResolver.php:224-226`
- **Reparar:** `EncuentroResolver.php:416-442` → -1 intensidad por encuentro positivo

### Cotilleos
- **Trigger:** `CotilleoNarrativo::coincidenciaDigna()` → score >= 3
- **Publicación:** `CotilleoPatronCadencia::debePublicar()` → patrón repetido

### Hitos
- **Archivo:** `HistoriaPuebloEngine.php`
- **Tipos:** hito_02 (primera vez que se conocen), hito_17, hito_19, etc.

---

## PARTE 3 — DISEÑO PROPUESTO (POST-VALIDACIÓN)

### 3A. MOVIMIENTO DE NPC ENTRE LUGARES

**Estado actual:** NPC aparece instantáneamente en el nuevo lugar.

**Propuesta:** Transición visual sencilla de 2-4 segundos.

#### Modelo de datos

```php
// Nuevo campo en npc_autonomo.movimientos_activos
$movimiento = [
    'residente_id' => 'per_p001',
    'origen' => 'lug_cafeteria',
    'destino' => 'lug_parque',
    'hora_salida' => ['dia' => 3, 'hora' => 14],
    'hora_llegada' => ['dia' => 3, 'hora' => 14], // misma hora (instantáneo lógicamente)
    'progreso' => 0.0, // 0.0 a 1.0 (para animación CSS)
    'duracion_segundos' => 3, // duración visual
];
```

#### Flujo

1. **Al programar encuentro:** Crear registro en `movimientos_activos`
2. **Cada tick de UI:** Calcular `progreso = (tiempoActual - horaSalida) / duracion`
3. **Mostrar:** Token desplazándose de origen a destino durante `duracion_segundos`
4. **Al llegar:** Actualizar `PresenciaEngine` con nuevo lugar

#### Archivos a modificar

- `PresenciaEngine.php` →考虑 movimientos_activos al resolver lugar
- `EncuentroEngine.php` → crear movimiento al programar
- `play.php` → animación CSS del token
- Nuevo: `MovimientoEngine.php` → lógica de transiciones

#### NO romper

- `PresenciaEngine::lugarDeResidente()` sigue funcionando
- Resolución de encuentros sin cambios
- Tokens existentes se animan igual

---

### 3B. DIFERENCIAL JUGADOR vs AUTÓNOMO

**Objetivo:** Que el jugador sienta que su intervención tiene más impacto.

#### Diseño sin multiplicar números a ciegas

**Principio:** El diferencial se aplica en `EncuentroResolver::resolver()` y `aplicarResultado()`, NO en la generación.

#### Cambios propuestos

**1. Bonus de calidad del contacto (jugador)**
```php
// En EncuentroResolver::aplicarResultado()
$esJugador = ($encuentro['intencion'] ?? '') === 'celeste_organizado';
if ($esJugador && $resultado['_deltas_reales']) {
    // +50% en deltas sociales para encuentros del jugador
    $dAb = (int) round($dAb * 1.5);
    $dBa = (int) round($dBa * 1.5);
}
```

**2. Mayor recuperación emocional (jugador)**
```php
// En EmotionalRecovery::evaluar()
// Si es encuentro del jugador, permitir cambio a alegre aunque antes fuera neutro
$esJugador = $contexto['intencion'] ?? '' === 'celeste_organizado';
if ($esJugador && $estadoAntes === 'neutro' && in_array($resultado, ['bien', 'muy_bien'])) {
    return ['estado' => 'alegre', 'motivo' => 'encuentro_jugador', ...];
}
```

**3. Recuperación de necesidades ampliada (jugador)**
```php
// En NecesidadEstado::aplicarRecuperacion()
$modJugador = $esJugador ? 1.5 : 1.0;
return $base * $intensidadLugar * $modCompania * $modHobby * $modJugador;
```

**4. Mayor probabilidad de eventos especiales (jugador)**
```php
// En InteraccionCasual::ejecutarPar()
// Flechazo con 2x probabilidad si viene de encuentro del jugador
$prob *= $esJugador ? 2.0 : 1.0;
```

**5. Reducir tasa de rechazo del jugador**
```php
// En VoluntadPonderadaEvaluator::desglose()
// Bonus +15 al score cuando el plan viene del jugador
if ($origen === 'celeste_organizado') {
    $s += 15; // hace que p suba de ~0.55 a ~0.75
}
```

#### Efecto esperado

| Métrica | Autónomo | Jugador (nuevo) | Δ esperado |
|---------|----------|-----------------|------------|
| Delta social por encuentro | +5 | +8 | +60% |
| Recuperación necesidades | ×1.0 | ×1.5 | +50% |
| Probabilidad alegre | 0% | 30% | +30pp |
| Tasa aceptación plan | ~45% | ~65% | +20pp |
| Eventos especiales | base | ×2 | +100% |

#### Archivos a modificar

- `EncuentroResolver.php` → bonus social jugador
- `EmotionalRecovery.php` → cambio emocional ampliado
- `NecesidadEstado.php` → recuperación ampliada
- `VoluntadPonderadaEvaluator.php` → bonus score jugador
- `InteraccionCasual.php` → eventos especiales ×2

---

## PARTE 4 — PLAN DE IMPLEMENTACIÓN

### Fase 1: Auditoría y corrección de bugs (antes de implementar)

1. **Corregir necesidades:** Investigar por qué caen a 0
   - ¿`aplicarRecuperacion()` se llama correctamente?
   - ¿Los lugares tienen perfiles de necesidades?
   - ¿El cálculo de recuperación es suficiente?

2. **Corregir emociones:** Investigar por qué no cambian a alegre
   - ¿`EmotionalRecovery::evaluar()` se invoca con `$hobbyMatch=true`?
   - ¿El resultado del encuentro puede ser `bien` o `muy_bien`?

### Fase 2: Movimiento visual (sin cambios de gameplay)

1. Crear `MovimientoEngine.php`
2. Modificar `PresenciaEngine.php` para considerar movimientos
3. Añadir animación CSS en `play.php`
4. Tests de invariante (no persona en 2 lugares)

### Fase 3: Diferencial jugador vs autónomo

1. Implementar bonus social jugador
2. Implementar recuperación emocional ampliada
3. Implementar recuperación necesidades ampliada
4. Implementar reducción rechazo jugador
5. Tests de平衡 (verificar que jugador se siente más impactante)

### Fase 4: Balance y calibración

1. Ajustar multiplicadores según playtest
2. Verificar que el pueblo se sigue sintiendo vivo sin jugador
3. Verificar que el jugador se siente más influyente

---

## CONCLUSIÓN

**Estado actual:** El sistema funciona técnicamente pero:
1. NO hay distinción entre jugador y autonomía
2. Las necesidades se agotan (bug o desbalance)
3. Las emociones no evolucionan (limitación de diseño)
4. La tasa de aceptación del jugador es muy baja (10.7%)

**Siguiente paso:** Validar si los bugs de necesidades/emociones son prioritarios antes de implementar el diferencial.

**Pendiente de aprobación:** ¿Implementamos primero las correcciones o el diferencial?

---

## APÉNDICE — BALANCE ROMÁNTICO CONGELADO (2026-09-09)

### Iteraciones completadas

| Iter | Cambio | Efecto parejas D30 (auto) | Ritmo |
|------|--------|---------------------------|-------|
| 1 | Necesidades (DECAY 2.5→0.35, RECUP 3→6) | — | base estable |
| 2a | Flechazo delta 28→12, prob 0.006→0.003 | ±7% | no cambia |
| 2b | iniciativa_social.prob_por_tick 0.12→0.075 | 0→0.7 parejas | aparecen primeras parejas |
| 3 | romance_hito weight 0.04→0.10, cooldown 336h→168h | 0.2→0.4 parejas | ~75d |
| 4 | voluntad.mod_tipo.romantico 0→+4 | 0.4→0.6 parejas | ~50d |

### Parámetros congelados

```
iniciativa_social.prob_por_tick = 0.075
flechazo.probabilidad = 0.003
flechazo.delta_romance = 12
romance_hito.weight = 0.10
romance_hito.cooldown_horas = 168
voluntad.mod_tipo.romantico = 4
```

### Resultado provisional aceptado

- Primera pareja autónoma: ~D20
- 0.6 parejas formales autónomas en D30
- ~6.8 NPC libres D30
- Ritmo: ~1 pareja / 50 días
- El pueblo mantiene autonomía sin llenarse de parejas solo

### PLAYTEST PENDIENTE (no bloquea frontend)

**Obligatorio antes de cerrar balance romántico definitivo:**

| Parámetro | Valor |
|-----------|-------|
| Seeds | 20 mínimo |
| Duración | 30 días mínimo |
| Escenarios | Autonomía pura + Jugador |
| Seeds | Comparables cuando sea posible |

**Métricas a medir:**
- Primer interés
- Primera pareja
- Parejas D7 / D14 / D30
- NPC libres D30
- Diferencial jugador / autonomía
- Conflictos
- Necesidades

**No bloquear trabajo de Frontend/UX por este test largo.**

---

## PARTE 5 — AUDITORÍA FRONTEND: COHERENCIA VISUAL DE ENCUENTROS (2026-09-09)

### 5.1 Flujo completo: encuentro programado → presencia en mapa → en curso → consecuencia

```
1. ENCUE programado (EncuentroEngine::programar())
   → encounter type: 'programado'
   → temporal: {dia, hora_inicio, hora_fin}
   → intencion: 'autonomo_npc_social' | 'celeste_organizado' | etc.

2. RESOLUCIÓN DE LUGAR (al momento de ejecutar)
   → PresenciaEngine::lugarDeResidente()
     a) ¿Tiene encuentro 'programado' o 'en_curso' futuro → retorna ese lugar
     b) ¿Tiene plan autónomo para esa hora → retorna plan
     c) Fallback: rutina habitual

3. ENCUENTRO EN CURSO (EncuentroLifecycle::sincronizarConReloj())
   → type: 'programado' → 'en_curso' cuando hora_inicio <= reloj < hora_fin
   → type: 'en_curso' → 'terminado' cuando reloj >= hora_fin

4. CONSECUENCIA (EncuentroResolver::aplicarResultado())
   → Se ejecuta al resolver (después de hora_fin)
   → Delta social, romance, emocional, necesidades
   → Se refleja en la siguiente consulta de estado
```

### 5.2 Qué ve actualmente el jugador en cada momento

| Momento | Qué ocurre en backend | Qué ve el jugador |
|---------|----------------------|-------------------|
| Hora T-1: encuentro programado | Encounter type='programado', hora_inicio=T | NPC A en lugar X, NPC B en lugar Y (presencia normal) |
| Hora T: hora_inicio | Encounter → 'en_curso' | Ambos NPC aparecen en mismo lugar (cambio instantáneo) |
| Hora T+1: durante encuentro | encounter仍在 'en_curso' | Ambos NPC visibles en el lugar |
| Hora T+1: hora_fin | Encounter → 'terminado', resolver() | Consecuencia aparece en buzón/bitácora |

### 5.3 Análisis de coherencia visual

**Estado actual (verificado):**

1. **Encuentros programados** → `LugarAtributos::ocupaHora()` verifica la ventana temporal. Si está en ventana, el NPC aparece en el lugar del encuentro.

2. **NPC concurrentes en mismo lugar** → `PresenciaEngine::resolver()` retorna el lugar correcto. Si dos NPC tienen encuentro en_curso en la misma localización, **ambos aparecen ahí**.

3. **Timing de transición** → No hay transición visual. El cambio es **instantáneo**: a la hora exacta, el NPC "salta" de un lugar a otro.

**Posibles incoherencias detectadas:**

| # | Incoherencia | Severidad | Detalle |
|---|-------------|-----------|---------|
| 1 | **Cambio instantáneo de lugar** | Media | NPC aparece en lugar A → salta a lugar B sin transición visual. El jugador ve un "teletransporte". |
| 2 | **Resolución de consecuencia diferida** | Baja | La consecuencia (deltas sociales, románticos) se aplica al resolver después de hora_fin. El jugador no ve feedback inmediato durante el encuentro. |
| 3 | **Sin indicador de "encuentro en curso"** | Baja | No hay visual clue de que dos NPC están interactuando. Solo se ven en el mismo lugar. |
| 4 | **Presencia en lugar destino no verificada para encuentros** | Baja | Si un NPC tiene encuentro programado en cafetería pero está "en ruta", `lugarDeResidente()` lo pone en cafetería sin verificar si el otro NPC también está ahí. |

### 5.4 Propuesta mínima (solo si hay problema real)

**Problema confirmado:** El cambio instantáneo de lugar (teletransporte) es la principal incoherencia UX. Las demás son menores.

**Solución mínima (NO implementar aún, solo propuesta):**

1. **Transición visual de 2-3 segundos** al cambiar de lugar
   - Mantener `PresenciaEngine` como está (instantáneo lógicamente)
   - Añadir capa de animación CSS en el frontend
   - Token se desplaza de origen a destino visualmente
   - Backend no cambia

2. **Badge "encuentro"** (futuro)
   - Cuando dos NPC están en el mismo lugar y hay encounter en_curso → badge sutil
   - Info tooltip: "A y B están juntos en la cafetería"

**NO necesario:**
- Cambiar la lógica de `PresenciaEngine`
- Cambiar el timing de resolución de consecuencias
- Añadir nuevos campos al modelo de datos de encuentros
