# Plan de alineación V3 — Aquí Hay Tema

Hoja de ruta viva para alinear el checkpoint visual `cbcb6e6` con el **Documento Maestro V3**.
**Estado:** **listo para ejecución completa** — pendiente autorización explícita de Neni/ChatGPT. **Decisiones técnicas cerradas 21/08; Misiones V3 cerradas 21/08 (curva llegadas + migración viviendas). **Corrección canon lugares aplicada 21/08 (9 lugares disponibles; sin desbloqueo/compra/EN OBRAS funcional).** **Ejecutar solo tras autorización explícita.**

---

## 1. ESTADO DE PARTIDA

| Campo | Valor |
|---|---|
| **SHA base** | `cbcb6e6036e00ed5e214ab8d43f8de1988afdb38` |
| **Rama** | `playtest-01-php74` (sincronizada con `origin/playtest-01-php74`) |
| **Estado Git** | Working tree limpio en HEAD del checkpoint al iniciar este plan |
| **Documento Maestro utilizado** | `DOCUMENTO_MAESTRO_V3_AQUI_HAY_TEMA.md` (adjunto indicado por Neni) |
| **Fecha del plan** | 2026-08-21 |

### Nota sobre la fuente documental

**Actualización 2026-08-21 (mensaje Neni):** contenido completo del Maestro recibido en chat (semilla Cupido Cutre + decisiones cerradas 19–21/08/2026). Pendiente copia formal a `docs/DOCUMENTO_MAESTRO_V3_AQUI_HAY_TEMA.md` (Tarea 0.1).

### Jerarquía documental para la ejecución

Cuando el Maestro se contradice a sí mismo, **las decisiones más recientes del canon V3 sustituyen las antiguas**. No compatibilizar mecánicas expresamente descartadas.

Orden de mandato:

1. **Canon de ejecución V3 acordado con Neni** (mensajes plan + correcciones 21/08): sin economía; máx. **24** vecinos; **sin A/B/C** en UI; **9 lugares** canónicos **todos disponibles desde el inicio**; **sin compra ni desbloqueo de lugares**; personalidad generada por partida; Vecinos como única puerta de población; dev fuera de play.
2. **Decisión mapa 21/08/2026** — *supersede* 6 complejos/ampliaciones visuales: mapa = **una ilustración fija**, **9 zonas** clicables (`mapa_zonas.json`); **no** PNG superpuestos por edificio. Los 9 lugares del mapa son jugables; no hay progresión de desbloqueo en el mapa.
3. **Bloques funcionales del Maestro** (identidad por seed, descubrimiento, marchas, buzón/diario, encuentros/emociones, aforo) — vigentes **solo** en los **9 destinos canónicos** y salvo choque con (1) y (2).
4. **Decisiones 19–20/08** (economía, bloques A/B/C, 6 complejos, ampliaciones Tienda/Arcade/Karaoke/Spa/Picnic/Mirador, compra/desbloqueo de lugares, EN OBRAS como progresión) — **LEGACY descartado** en producto. No reintroducir como destinos, compras ni desbloqueos. Migración interna de saves solo si imprescindible.
5. **Semilla Cupido Cutre** — **histórica**; tono válido; cifras, economía y rol sustituidos por decisiones posteriores.

### Los 9 lugares canónicos (lista cerrada)

1. Cafetería · 2. Biblioteca · 3. Gimnasio · 4. Restaurante · 5. Parque · 6. Bar · 7. Cine · 8. Discoteca · 9. Bingo

**Legacy como lugares (no destinos jugables, no desbloqueables, no comprables, no edificios):** Tienda, Arcade/Recreativo, Karaoke, Spa, Picnic, Mirador. Solo se permite **reutilizar mecánica abstracta** del Maestro (p. ej. familias de eventos) **mapeada a uno de los 9**, nunca como lugar independiente.

**EN OBRAS:** el asset puede existir en repo; **no** forma parte de la mecánica canónica (no overlay de progresión, no lugares bloqueados).

---

## 2. CONTRADICCIONES DETECTADAS

### Producto / UX

| Área | Maestro V3 | Checkpoint `cbcb6e6` | Severidad |
|---|---|---|---|
| Economía | Sin dinero | HUD dinero, `EconomyLedger`, `coste_dinero` en lugares | Alta |
| Viviendas | Sin A/B/C, sin Residencias; **Vecinos** (`N de 24`) | Imagen bloques, capa residencias, tabs A/B/C | Alta |
| Capacidad | Máx. **24** | Hasta **48** (16×3 bloques) | Alta |
| Mapa | **9 lugares** canónicos, **todos disponibles**; sin compra/desbloqueo; sin legacy como destinos | Zonas OK; motor 6 complejos/14 destinos; posible UI EN OBRAS/desbloqueo legacy | Alta |
| Presencia | Tokens en 9 zonas | `placeHabEnZona()` nunca llamada; mapa sin habitantes | **Crítica** |
| Población | 3 + 5 aleatorios | Rosters fijos (`juego_v1`, `playtest_01`) | Alta |
| Llegadas | Espaciado creciente | Motor existe; calibración no V3 | Media |
| Dev | Fuera de UI | Taller visible sin `?lab=1` | Media |
| Vida Pueblo | Sí | SVG roto; HUD shell desincronizado | Alta |

### Personalidad (corrección Neni)

| Área | Maestro V3 | Checkpoint | Severidad |
|---|---|---|---|
| Modelo | Identidad Pxxx + personalidad generada + runtime | **Intención correcta** en `GeneradorResidente` | — |
| Completitud | Todos los atributos sociales del Maestro | Faltan rasgos ocultos, dealbreakers, prefs románticas, coletilla, lugares preferentes, hobby principal | Alta |
| Catálogos | Producción canónica | `_provisional_catalogos: true` | Media |

### Técnico

UTF-8 roto, SVG inválido, JS mapa legacy convive con mapa canónico, docs pre-V3 contradictorios.

---

## 3. PLAN COMPLETO DE ALINEACIÓN

**10 fases** (0–9). Cada tarea incluye: objetivo, archivos, estado actual → final, dependencias, riesgo, persistencia, comprobación, Neni visual.

---

### Fase 0 — Fuente de verdad y congelación

#### Tarea 0.1 — Ingesta Documento Maestro V3
- [ ] **pendiente**
- **Objetivo:** Canon versionado en repo; validar plan vs maestro.
- **Archivos:** `docs/DOCUMENTO_MAESTRO_V3_AQUI_HAY_TEMA.md` (nuevo).
- **Existe → Final:** Solo adjunto/Drive → copia UTF-8 en repo.
- **Dependencias:** —
- **Riesgo:** Bajo | **Persistencia:** N/A | **Neni visual:** No

#### Tarea 0.2 — Baseline técnico
- [ ] **pendiente**
- **Objetivo:** SHA base + tests verdes antes de codificar.
- **Archivos:** `tests/smoke.php`, suite acordada.
- **Comprobación:** Smoke + vista_pueblo + vida_pueblo tests.

---

### Fase 1 — Saneamiento técnico bloqueante (sin rediseño)

#### Tarea 1.1 — UTF-8 y literales
- [ ] **pendiente** | **Archivos:** `play.php`, `play-v3.js` | **Riesgo:** Bajo | **Neni:** Sí

#### Tarea 1.2 — SVG corazón + cableado Vida del Pueblo
- [ ] **pendiente** | **Archivos:** `play.php`, `play-v3.js` | **Riesgo:** Medio | **Neni:** Sí

#### Tarea 1.3 — Dev fuera de producto
- [ ] **pendiente** | **Archivos:** `play.php`, CSS taller | Ocultar `.taller` sin lab; quitar link provisional | **Neni:** Sí

---

### Fase 2 — Mapa canónico funcional

#### Tarea 2.1 — Tokens en 9 zonas
- [ ] **pendiente** | **Archivos:** `play-v3.js`, `play-v3-mapa-canonico.css`
- **Existe:** Zonas OK; `placeHabEnZona()` sin invocar → mapa vacío.
- **Final:** Habitantes visibles con emoción/hay_tema.
- **Riesgo:** **Crítico** | **Neni:** Sí

#### Tarea 2.2 — Retirar JS mapa 6 complejos
- [ ] **pendiente** | `SLOTS`, `[data-complejo]`, handlers legacy | **Dep:** 2.1 | **Neni:** Sí

#### Tarea 2.3 - Motor alineado a 9 lugares (sin desbloqueo)
- [ ] **pendiente** | `ComplejoCatalog`, `VistaPuebloV3`, `PresenciaEngine`, `lugares.json`
- **Final:** Catálogo jugable = **solo** los 9 destinos canónicos; presencia y planes usan esas zonas. Retirar destinos legacy (Tienda, Arcade, Karaoke, Spa, Picnic, Mirador) del flujo producto. **No** `lugares_desbloqueados`, compra ni EN OBRAS como mecánica.
- **Riesgo:** Alto | **Persistencia:** migrar saves con flags legacy (ignorar en producto) | **Neni:** Sí

---

### Fase 3 — Vecinos (eliminar Residencias, no sustituir)

#### Tarea 3.1 — Quitar UI Residencias/bloques
- [ ] **pendiente** | `play.php`, `play-v3-bloques-residencias.css`, `renderResidencias`, refs `bloques_*.png`
- **Final:** Solo Celestine apunta → Vecinos. **No** nueva pantalla residencial.
- **Persistencia:** No tocar `bloque_*` en JSON aún | **Neni:** Sí

#### Tarea 3.2 — Contador 'N de 24 vecinos'
- [ ] **pendiente** | shell Vecinos, `renderShellPanels` | **Dep:** 3.1, 4.1 | **Neni:** Sí

---

### Fase 4 — Capacidad 24 y vivienda legacy (motor)

> **Decisión técnica cerrada (21/08):** estrategia «pool lógico 24 + espejo legacy». Ver tareas ampliadas.

#### Tarea 4.1 — Techo 24 residentes
- [ ] **pendiente** | `CapacidadViviendas`, CandidatoLlegadaEngine, ResidenteOperations
- **Objetivo:** Capacidad de producto = **24** fija; llegadas bloqueadas si N >= 24.
- **Existe → Final:** capacidadTotal()` depende de `bloques_abiertos` (legacy; hasta 48) -> **24** via pool(hasta 48) → **24** vía pool lógico; huecos = max(0, 24 - N).
- **Riesgo:** **Crítico** persistencia | **Persistencia:** ver 4.2 | **Neni:** No

##### Decisión técnica — representación interna de capacidad 24

**Elegida:** un **pool lógico de 24 slots** como fuente de verdad para asignación y huecos.

- Nuevo bloque canónico en partida: iviendas: { cap: 24, slots: [{ id, ocupante_id|null, estado }, ...] }.
- IDs de slot estables y opacos: **A01-A16 + B01-B08** (24 total). El jugador **no** ve bloques; solo 'N de 24 vecinos'.
- `CapacidadViviendas`::capacidadTotal() devuelve **24** en producto.
- huecos() = slots libres en pool (equivalente a 24 - residentes activos si invariante 1 residente ↔ 1 slot).
- `asignarAutomatico()`() busca el **primer slot libre** del pool; escribe 
esidentes[id].vivienda_id.

**Motivo:** separa el canon de producto (24 opacos) del almacenamiento legacy (`bloque_a`/b/c) sin reescribir saves a ciegas. Auditable: un array de 24 entradas.

#### Tarea 4.2 — Migración aditiva vivienda (sin pérdida de partidas)
- [ ] **pendiente** | `SchemaMigrator`, `CapacidadViviendas`, `PartidaRepository`
- **Dep:** 4.1
- **Final:** Carga y guardado compatibles con saves antiguos; lógica nueva **no** usa A/B/C como concepto de producto.

##### Decisión técnica — estrategia de migración legacy

**Elegida:** **schema v3 aditivo + compatibilidad de lectura + espejo legacy en escritura** (no migración destructiva).

| Campo / estructura | Acción | Uso en lógica nueva |
|---|---|---|
| 
esidentes[*].vivienda_id | **Conservar** | Enlace canónico residente ↔ slot; se preserva en migración si el slot existe |
| iviendas (pool 24) | **Crear si falta** (v2→v3) | **Fuente de verdad** para huecos y asignación |
| `bloque_a`, `bloque_b` | **Conservar temporalmente** | Espejo sincronizado al guardar; lectura solo si falta pool |
| `bloque_c` | **No materializar** en producto | Ignorar en gates; si un save lab tiene C*, no borrar ocupantes |
| celeste.`bloques_abiertos` | **Dejar de usar** para capacidad | Tras migración: fijar internamente ['a','b'] solo para espejo A01-B08; **no** UI, **no** desbloqueo |
| `CapacidadViviendas`::abrirBloque() | **Retirar del flujo producto** | Solo lab/tests si aún lo necesitan |

**Al cargar (`SchemaMigrator` v2 → v3):**

1. Si ya existe iviendas.slots con 24 entradas → validar invariantes y continuar.
2. Si no existe → **construir pool** desde `bloque_a` + `bloque_b` (máx. 24 slots):
   - Copiar A01-A16 y B01-B08 tal cual (ocupante_id, estado).
   - Residentes con ivienda_id fuera de esos 24 (p. ej. C03 en save lab): **conservar** residente; reasignar al **primer slot libre** del pool; registrar en iviendas.migracion.reasignados[] (meta interna, sin UI).
3. Establecer celeste.vivienda_capacidad_max = 24.
4. **No eliminar** `bloque_* del JSON en v3.

**Al guardar:**

- Escribir pool + espejo `bloque_a`/`bloque_b` coherente con el pool (para herramientas/tests legacy).
- No expandir `bloque_c` en producto.

**Saves con >24 residentes (solo lab):**

- No truncar población.
- Bloquear nuevas llegadas; fuera de producto canonico si N>24.

**¿Schema bump o solo lectura?**

- **Sí, bump a schema_version = 3** la primera vez que se normaliza el pool. Idempotente.

**Retirada definitiva de legacy:**

- **Fase 9.3:** cuando ningún handler producto lea `bloque_a`/b ni `bloques_abiertos`.
- Posible v4 posterior: dejar de escribir espejo `bloque_*.

**Comprobación migración:**

- [ ] Cargar ≥3 saves de data/partidas/ → mismos residentes; reasignaciones documentadas si aplica.
- [ ] tests/viviendas_test.php + nuevo tests/viviendas_pool_v3_test.php
- [ ] Round-trip save/load sin pérdida de residentes ni relaciones.

### Fase 5 — Economía fuera del producto

#### Tarea 5.1 — Quitar dinero de UI
- [ ] **pendiente** | `play.php`, `play-v3.js` | Mantener `economia` en save | **Neni:** Sí

#### Tarea 5.2 - Lugares siempre disponibles (sin economía ni desbloqueo)
- [ ] **pendiente** | `features.json`, `lugares.json`, motores que lean `coste_dinero` / desbloqueo
- **Final:** Los 9 lugares canónicos accesibles desde el inicio; sin compra, sin flags de desbloqueo en producto; sin EN OBRAS funcional
- **Dep:** 5.1

---

### Fase 6 — Personalidad generada (completar, NO fijar en Pxxx)

> Identidad Pxxx = retrato/token/género/edad/base visual. Personalidad = generada al entrar en partida desde catálogos.

#### Tarea 6.1 — Completar `GeneradorResidente`
- [ ] **pendiente** | `GeneradorResidente.php`, `PerfilPartida.php`, calibración
- **Existe:** hobbies, rasgos, prefs parciales, estilo; falta ocultos, románticas, dealbreakers, coletilla, lugares preferentes, hobby principal.
- **Final:** Tres capas: identidad / personalidad partida / runtime. No regenerar al cargar save.
- **Riesgo:** Alto | **Dep:** 0.1 (cardinalidades Maestro)

#### Tarea 6.2 — Catálogos producción
- [ ] **pendiente** | `data/catalogos/`, quitar `_provisional_catalogos`

#### Tarea 6.3 — Ficha vecino
- [ ] **pendiente** | `FichaPlayVista.php`, capa ficha | **Neni:** Sí

---

### Fase 7 — Nueva partida y llegadas

#### Tarea 7.1 — Config producto V3 (3 aleatorios)
- [ ] **pendiente** | prevalidadas, `configNueva()` | Sustituir roster fijo `juego_v1`

#### Tarea 7.2 — 5 incorporaciones aleatorias (→ 8)
- [ ] **pendiente** | `TutorialIncorporaciones`, configs | RNG acotado, no cola fija

#### Tarea 7.3 — Llegadas posteriores espaciadas (curva cerrada)
- [ ] **pendiente** | CandidatoLlegadaEngine, CalibracionConfig, MENSAJITOS | **Dep:** 7.2, 8.2, 4.1

> **Decisión técnica cerrada (21/08):** curva «gap mínimo por población + lotería diaria suave por huecos».

##### Regla exacta propuesta

**Variables:**

- N = residentes activos; H = max(0, 24 - N).
- Modo **normal** activo (tutorial completado, 8 residentes).

**Precondiciones** (cada evaluación diaria):

1. N < 24.
2. Sin candidato_activo ni en_camino.
3. dia_pueblo >= llegadas.cooldown_hasta_dia.

**A) Cooldown mínimo** tras llegada efectiva, rechazo o expiración:

`
gap_min(N) = 2 + floor((clamp(N, 8, 23) - 8) * 1.25)
gap_jitter = rng_int(0, 2)
cooldown_hasta_dia = dia_actual + gap_min(N) + gap_jitter
`

**B) Lotería diaria** (solo si ya pasó el cooldown):

`
p_dia = min(0.30, 0.04 + 0.015 * H)
`

- Si falla la tirada, reintentar al día siguiente sin mover el cooldown.
- Si acierta → candidato en buzón (flujo actual).

**Se mantienen:** plazo oferta, espera 1–10 min en camino, cooldown de cara (14 d), un candidato activo, RNG reproducible.

**Motivo:** gap creciente evita avalancha; p_dia(H) conserva presión suave con muchos huecos; fórmulas cortas en calibracion.json.

##### Ejemplos (tras cerrar ciclo anterior)

| N | H | gap_min | p_dia | E[T] (días pueblo) |
|---:|---:|---:|---:|---:|
| 8 | 16 | 2 | 0.280 | **6.6** |
| 12 | 12 | 7 | 0.220 | **12.5** |
| 16 | 8 | 12 | 0.160 | **19.2** |
| 20 | 4 | 17 | 0.100 | **28.0** |
| 23 | 1 | 20 | 0.055 | **39.2** |

**Tiempo esperado entre ofertas** (desde cierre de ciclo hasta candidato en buzón):

```
E[T] = gap_min(N) + E[jitter] + E[días hasta acierto de lotería]
     = gap_min(N) + 1 + (1 / p_dia)
```

- `E[jitter] = 1` (media de `rng_int(0,2)`).
- La lotería diaria geométrica aporta `1/p_dia` días de media tras el cooldown.
- Rango orientativo (solo jitter): **gap_min + 1/p_dia** a **gap_min + 2 + 1/p_dia** días.

**Horizonte 8→24:** 16 llegadas post-8; suma de E[T] para N=8..23 = **312 días pueblo** (~19.5 días/llegada de media). Progresión creciente confirmada (6.6 d -> 39.2 d); **curva sin reajuste**.

##### Cómo se testeará

- [ ] tests/llegadas_curva_v3_test.php: fórmulas en N=8,12,16,20,23.
- [ ] Simulación determinista 200 días desde N=8: intervalos crecientes; N≤24.
- [ ] Regresión: cierre de ciclo respeta cooldown_hasta_dia >= dia + gap_min(N).
- [ ] Smoke: ≤3–4 candidatos extra en 60 días simulados (orden de magnitud).

---

---

### Fase 8 — Sistemas jugables

#### Tarea 8.1 — Vida del Pueblo ON en producto | **Dep:** 1.2 | **Neni:** Sí
#### Tarea 8.2 — MENSAJITOS bucle completo | **Neni:** Sí
#### Tarea 8.3 — Organizar/planes/relaciones con mapa 9 | **Neni:** Sí
#### Tarea 8.4 — Cotilleo/Diario según Maestro | **Neni:** Sí


#### Tarea 8.5 — Misiones diarias V3 (canon social, sin economía)
- [ ] **pendiente** | motor misiones + UI play | **Dep:** 8.1, 6.3, 2.3
- **Neni:** Sí (ubicación panel en shell)

> **Decisión V3 cerrada (21/08):** las misiones **permanecen**; ya no dan dinero. Son objetivos sociales opcionales + pequeño empujón de Vida + información ocasional.

##### Auditoría focal — qué existe

| Pieza | Estado |
|---|---|
| `MisionDiariaEngine.php` | Motor usable: ≤3/día, generación al comenzar día, cumplimiento por encuentro Celestine |
| `MisionPlantillas.php` | 10 plantillas state-driven (pares, aislamiento, discovery, lugar, relación floja…) |
| `MisionPlayBridge.php` | Encuentro iniciado/terminado → `onEncuentroCelestine()` |
| `RelojOperations.php` | Cierra caducadas + genera nuevas |
| Flag `misiones_diarias_enabled` | OFF en producto; ON en playtest |
| UI | Solo `play-provisional.php`; **ausente en `play.php`** |
| Encargos extra Maestro | **Sin código** → LEGACY documental |
| `PeticionPuebloEngine` (B4) | **Distinto**: peticiones multi-día por buzón; no es misión diaria |

**Conflicto actual con canon:** `vida_caducada = -2` al caducar → **eliminar** (sin castigo).

##### Qué se reutiliza / legacy

- **Reutilizar:** motor, plantillas, puente encuentros, generación por estado real, regla «hecho = organizar encuentro celebrado».
- **Legacy:** encargos extra económicos; penalización al caducar; `lugares_desbloqueados` en `sitio_del_dia`/`cambio_de_sitio`; nombres lugar arcade/mirador/tienda en plantillas.
- **No mezclar:** misiones diarias (≤1 día, opcionales) vs peticiones/crisis (multi-día, buzón/diario).

##### Generación y completación

1. `alComenzarDia()`: hasta 3 misiones, una por familia, solo si hay candidatos reales.
2. Cumplir: encuentro Celestine celebrado que encaja → `cumplirIndice()`; 1 encuentro → 1 misión max/día.
3. `alCerrarDia()`: pendientes → caducada, **Δ Vida = 0**.

##### Recompensa V3 (decisión técnica)

**Vida del Pueblo (principal):**

```
delta_vida = min(3, 1 + floor(exigencia / 50))
```

| Exigencia | Δ Vida |
|---:|---:|
| 10–49 | +1 |
| 50–99 | +2 |
| 100 | +3 |

- **Techo diario misiones:** **+4 Vida/día** (suma cumplidas; exceso narrativo sin más Vida).
- **Caducada / ignorada:** **0**.
- Mantener `cuenta_latido`: solo la misión de mayor exigencia del día puede ser positivo válido Latido.

**Información (secundaria, opcional):**

- Tras cumplir, **~30%** en familias elegibles (`discovery`, `aislamiento`, `por_descubrir`) → un descubrimiento ponderado vía motor discovery; **no** en todas; sin Pokédex.

##### Archivos afectados

`MisionDiariaEngine.php`, `MisionPlantillas.php`, `calibracion_vida.json`, `features.json`, config V3, `play.php`, `play-v3.js`, `tests/misiones_diarias_test.php`. **No tocar** economía/encargos (inexistentes).

##### Tests

- Caducada = 0 Vida; delta por exigencia; cap +4/día; discovery no siempre; lugares 9 sin desbloqueo.

##### UI en `play.php` (acceso visual propio — decisión cerrada)

La implementación **debe incluir un acceso visual propio** en `play.php`, integrado con la estética actual (papel/dibujo/capas), **no** una página paralela ni un botón web genérico.

**Requisitos de implementación (sin diseño final ahora):**

- Botón o acceso visible y coherente con el lenguaje visual del juego (textura/recurso de la shell, no `<button>` HTML genérico).
- Etiqueta provisional aceptable: **«Hoy en el pueblo»** o **«Misiones»** (elegir una en implementación; Neni puede renombrar en validación visual).
- Al pulsar: abrir panel/listado en el **mismo sistema de capas/ventanas** que Organizar, Vecinos, MENSAJITOS, etc. (reutilizar `vistaHoy()` como fuente de datos).
- **No** crear ruta, iframe ni pantalla externa.
- **No** fijar placement definitivo en código/documento si depende del espacio tras retirar dinero, bloques y Residencias; dejar hook/acceso preparado y ajustar posición en validación visual de Neni.

**Referencia técnica existente:** `play-provisional.php` (`misiones-hoy-*`) solo como lab; migrar lógica a `play.php` canónico.

##### Producto pendiente (solo validación Neni)

- **Placement exacto** del acceso y acabado visual fino en la shell sin dinero ni bloques (validación en producción).


---

### Fase 9 — Limpieza (sin rediseño)

#### Tarea 9.1 — Consolidar CSS servidos (9 hojas → merge opcional sin cambiar look) | **Neni:** Sí
#### Tarea 9.2 — Marcar docs superseded por V3
#### Tarea 9.3 — Retirar legacy motor cuando migración verificada

---

## 4. DECISIONES DE PRODUCTO QUE NO SE PUEDEN CAMBIAR

1. Sin economía/dinero en producto.
2. Vida del Pueblo sí (corazón + motor en partida canónica).
3. 9 lugares definitivos del mapa canónico (lista cerrada: Cafetería, Biblioteca, Gimnasio, Restaurante, Parque, Bar, Cine, Discoteca, Bingo).
4. Sin tienda ni lugares fuera de esos 9.
5. **Los 9 lugares canónicos están disponibles desde el inicio** — sin compra, sin desbloqueo, sin progresión EN OBRAS.
6. Legacy **Tienda, Arcade/Recreativo, Karaoke, Spa, Picnic, Mirador** — no destinos jugables, no desbloqueables, no comprables, no edificios en mapa.
7. Sin lugares antiguos fuera de la lista de 9 en flujo jugador.
8. Mapa canónico actual - no volver a 6 complejos.
9. Máximo 24 vecinos.
10. Sin A/B/C en producto — ni copy, imagen ni pantalla Residencias.
11. 3 personajes aleatorios al iniciar.
12. 5 incorporaciones aleatorias posteriores (oleada inicial → 8 total).
13. Personalidad social generada desde catálogos al entrar en partida; persistente por save.
14. Identidad Pxxx separada (solo base visual/demográfica); **no** hobbies/carácter fijos en ficha Pxxx.
15. Runtime evolutivo separado (emoción, relaciones, discovery).
16. Llegadas posteriores cada vez más espaciadas.
17. MENSAJITOS.
18. Celestine apunta → Vecinos (`N de 24`, listado, ficha).
19. `play.php` pantalla canónica.
20. Herramientas dev fuera de la interfaz del jugador.
21. No rediseñar aprovechando refactors.
22. **Misiones diarias** (hasta 3/día): objetivos sociales opcionales; recompensa principal = aporte pequeño a Vida del Pueblo; sin dinero; sin penalización por no completar.
23. **Encargos extra económicos** del Maestro antiguo: **no** existen en producto V3 (legacy documental).
24. **Peticiones/historias** (B4, buzón, varios días) — distintas de misiones diarias; no mezclar.

25. PHP 7.4; sin deps nuevas sin autorización.

---

## 5. LEGACY Y MIGRACIONES

| Elemento | Acción | Notas |
|---|---|---|
| `bloque_a/b/c` | **MANTENER → MIGRAR** | Cap 24; sin UI |
| `vivienda_id` | **MANTENER → MIGRAR** | Slot interno opaco |
| `celeste.`bloques_abiertos`` | **MIGRAR** | Ignorar B/C producto |
| Saves `data/partidas/` | **MANTENER** | Probar tras cada migración |
| `ComplejoCatalog` / 14 destinos | **MANTENER → RETIRAR CUANDO SEGURO** | Sustituir por catálogo 9 destinos; tras Fase 2 |
| Destinos legacy (Tienda, Arcade, Karaoke, Spa, Picnic, Mirador) | **RETIRAR CUANDO SEGURO** | No destinos; no desbloqueo; mecánica abstracta solo remapeada a los 9 |
| `economia` / `EconomyLedger` | **MANTENER TEMPORALMENTE** | Sin UI |
| `playtest_01`, `debug_v0` | **MANTENER** | Lab interno |
| `juego_v1` | **MIGRAR →** config V3 | Sin roster fijo |
| `GeneradorResidente` | **MIGRAR** | Completar; quitar provisional |
| `PerfilPartida::deOLegacy` | **MANTENER TEMPORALMENTE** | Saves antiguos |
| `play-provisional.php` | **MANTENER** dev | Sin link producto |
| Docs pre-V3 | **MANTENER** histórico | Marcar superseded |
| Assets `bloques_*.png` | **MANTENER** repo | Dejar de referenciar |
| Asset EN OBRAS | **MANTENER** repo | **No** mecánica canónica; no overlay de progresión |
| `lugares_desbloqueados` / compra lugares | **MIGRAR → IGNORAR** | Producto: 9 lugares siempre abiertos |
| Encargos extra (Maestro 19/08) | **LEGACY — NO IMPLEMENTAR** | Sin código; no sustituto económico |
| `viviendas` (pool 24) | **CREAR v3** | Fuente de verdad capacidad/asignación |
| `bloque_a/b/c`, `bloques_abiertos` | **ESPEJO / IGNORAR** | Solo compat; retirar lógica en Fase 9.3 |
| JS mapa 6 complejos | **RETIRAR CUANDO SEGURO** | Tras Fase 2 |

---

## 6. SANEAMIENTO TÉCNICO

### CSS cargados por `play.php` (checkpoint)

1. `play-v3.css` 2. `play-v3-capas.css` 3. `play-v3-app.css` 4. `play-v3-shell-ui.css` 5. `play-v3-shell-art.css` 6. `play-v3-capas-shell.css` 7. `play-v3-mensajitos.css` 8. `play-v3-bloques-residencias.css` *(retirar tras Fase 3)* 9. `play-v3-mapa-canonico.css`

Duplicados no servidos: `design/integracion-v3/`, `dev/_ro/`.

**Regla:** consolidar sin rediseñar — copiar reglas, no cambiar valores visuales.

Incluido en fases: UTF-8 (1.1), SVG (1.2), taller (1.3), JS mapa muerto (2.2/9.3), docs obsoletos (9.2).

---

## 7. VALIDACIÓN FINAL PREVISTA

### Técnicas (sin validación visual automatizada)

- [ ] Tests PHP acordados — ALL PASS
- [ ] Smoke: nueva partida V3 → 3 residentes; día 1 → 8; techo 24
- [ ] Carga ≥3 saves antiguos de `data/partidas/`
- [ ] APIs: `partida.estado`, `mapa.presencia`, `buzón.listar`, `encuentro.proponer`, `partida.inspeccionar`
- [ ] Misiones V3: caducada sin penalización; cap +4 Vida/día; sin encargos extra
- [ ] Grep producto: sin `dinero`, `residencias`, `bloque_a`, desbloqueo/compra de lugares, EN OBRAS funcional, destinos legacy en UI
- [ ] Personalidad: misma seed → mismo perfil; otra partida → otra personalidad; Pxxx identidad estable
- [ ] Simulación 30+ días: llegadas espaciadas; no >24 residentes
- [ ] `tests/llegadas_curva_v3_test.php` + simulacion 200 dias N>=8
- [ ] Migracion v3: round-trip >=3 saves en data/partidas/
- [ ] Migración v3: round-trip ≥3 saves en `data/partidas/`

### Visual (solo Neni, manual)

Mapa con tokens; shell sin dev/dinero; corazón OK; Vecinos `N de 24` sin Residencias; MENSAJITOS/Organizar/Cotilleo/ficha; aspecto conservado vs checkpoint.

**No** Browser / Playwright / screenshots para estética.

---

## 8. REGISTRO DE EJECUCIÓN

### Plantilla por tarea

`
### [ID] Título
- [x] realizada
- **Archivos modificados:** …
- **Resultado:** …
- **Comprobación ejecutada:** …
- **Incidencias/desviaciones:** …
- **Resolución:** …
- **SHA checkpoint:** …
`

### Log

_(vacío — pendiente autorización de ejecución)_


---

## 10. REVISIÓN FINAL PRE-EJECUCIÓN (21/08/2026)

Checklist de coherencia del plan (sin re-auditar repo). **Resultado: alineado** salvo ítems marcados «validación Neni».

| Canon / decisión | Estado en plan |
|---|---|
| Sin economía funcional en producto | ✅ Fase 5; flags off; sin HUD dinero; misiones sin dinero; encargos extra NO |
| Sin desbloqueo/compra de lugares | ✅ 9 lugares disponibles; Fase 2.3 + 5.2; legacy ignorado |
| EN OBRAS no es mecánica | ✅ Solo asset repo; sin overlay progresión |
| Sin UI Residencias / A/B/C | ✅ Fase 3 eliminar; Vecinos única puerta |
| Solo 9 lugares canónicos | ✅ §4, §Jerarquía, Apéndice B |
| Máximo 24 vecinos | ✅ Fase 4 pool 24 |
| 3 + 5 iniciales aleatorios → 8 | ✅ Fase 7.1–7.2 |
| Personalidad generada (no fija Pxxx) | ✅ Fase 6 |
| Llegadas post-8 con curva cerrada | ✅ Tarea 7.3 (gap + p_dia; E[T] documentado; ~312 d) |
| Migración viviendas no destructiva | ✅ Schema v3 aditivo; espejo legacy; §4.2 |
| Misiones V3 sin dinero | ✅ Tarea 8.5 + Apéndice D |
| Vida del Pueblo sí | ✅ Tarea 8.1 |
| MENSAJITOS sí | ✅ Tarea 8.2 |
| Cotilleo/Diario sí | ✅ Tarea 8.4 |
| Planes/relaciones + mapa 9 | ✅ Tarea 8.3 |
| Dev fuera del producto | ✅ Tarea 1.3 |
| `play.php` pantalla canónica | ✅ §4 + Fase 1–9 |
| Decisiones técnicas documentadas | ✅ Apéndices A–D; Tareas 4.2, 7.3, 8.5 |
| Migraciones peligrosas con estrategia segura | ✅ Pool viviendas v3; saves lab >24 no truncar |

### Contradicciones restantes dentro del plan

**Ninguna funcional.** Las menciones a economía, desbloqueo, EN OBRAS, 6 complejos/14 destinos, bloques A/B/C y encargos aparecen **solo** como: checkpoint actual, legacy a retirar, o Maestro supersedido — nunca como objetivo de implementación V3.

### Decisiones DE PRODUCTO abiertas (escalar a Neni en validación visual)

1. **Placement y acabado** del acceso a misiones en shell `play.php` (contenido y acceso definidos; posición final tras quitar dinero/bloques).
2. Ninguna otra decisión de producto bloqueante identificada en esta revisión.

### Autorización

El plan cubre fases **0–9** con dependencias, tests y criterios Neni. **Listo para ejecución completa** cuando Neni/ChatGPT autoricen explícitamente.


---

## Apéndice A — `GeneradorResidente` (corrección auditoría)

**Canon:** identidad Pxxx (visual/género/edad) + personalidad generada en partida + runtime. **No** cargar hobbies/carácter fijos desde Pxxx.

| Requisito | Estado actual | Fase |
|---|---|---|
| No reescribe catálogo Pxxx | OK | — |
| `runtime.perfil_partida` persistido | OK | — |
| Seed reproducible (`RngService`) | OK | — |
| Hobbies (lista N) | Parcial; sin principal/secundarios | 6.1 |
| Rasgos públicos | OK (N rasgos) | 6.1 cardinalidades |
| Rasgos ocultos | No generado | 6.1 |
| Estilo social | OK | — |
| Prefs pos/neg (personalidad, visual, hobbies) | Parcial | 6.1 |
| Prefs románticas / dealbreakers | No | 6.1 |
| Coletilla / voz | No | 6.1 |
| Lugares preferentes | No | 6.1 |
| Catálogos `data/catalogos/` | OK vía `CatalogStore`; flag `_provisional` | 6.2 |
| Saves sin perfil | `PerfilPartida::deOLegacy` | Mantener |

---

*Fin del plan — pendiente aprobación Neni/ChatGPT. No ejecutar hasta autorización.*
---

## Apéndice B — Lectura Maestro V3 (contenido recibido 21/08/2026)

### Corrección canon lugares (21/08, mensaje Neni — previo a aprobación)

- **NO** convertir Tienda, Arcade, Karaoke, Spa, Picnic ni Mirador en lugares desbloqueables, comprables ni destinos independientes.
- **SÍ:** solo los **9 lugares canónicos**, **todos disponibles**, **sin economía**, **sin compra de lugares**, **sin desbloqueo de edificios**.
- Asset EN OBRAS: puede existir en repo; **no** es mecánica de producto.

### Confirmado y alineado con el plan

| Tema | Maestro / canon V3 | Plan / checkpoint |
|---|---|---|
| Identidad Pxxx vs personalidad | Cara/asset/género/edad fijos; rasgos/hobbies/prefs **nuevos por seed** | Fase 6: completar `GeneradorResidente`, no fijar en ficha |
| Descubrimiento progresivo | Celestine no ve ficha completa; sin contador Pokédex | Fase 6.3 + discovery existente |
| Arranque población | 3 al inicio → **8 el día 1** tras tutorial; luego candidatos | Fase 7: 3+5 aleatorios → 8 |
| Candidatos llegada | Un candidato activo; buzón; ventana real; aceptar/rechazar | Fase 7.3 + 8.2 |
| Buzón vs Diario | Buzón = decisión/importante; Diario = cotilleo | MENSAJITOS + Cotilleo (Fase 8) |
| Mapa | **9 zonas** fijas, hotspots, calibrador; **9 lugares siempre jugables** | Fase 2; imagen + JSON OK |
| Encuentros | Resultado **individual**; emociones ALEGRE/NEUTRAL/TRISTE/ENFADADO | Fase 8.3 |
| Aforo / horarios | Reglas por destino en Maestro 20/08 | **Solo 9 destinos**; retirar modelo 6 complejos/14 destinos del producto |

### Supersedido explícitamente (no implementar ni compatibilizar)

- **6 complejos** y **14 destinos** (Tienda, Arcade, Karaoke, Spa, Picnic, Mirador incluidos como lugares).
- **Compra y desbloqueo de lugares** y **EN OBRAS** como progresión funcional.
- **Economía** visible y compra de bloques A/B/C (48 plazas).
- **Ampliaciones** como edificios o destinos separados — sustituido por **9 lugares planos** en mapa único.

### Reutilización permitida del Maestro legacy

Familias de eventos, copy o reglas de encuentro escritas para Cine, Arcade, Restaurante, etc. pueden **inspirar** fichas de los **9 destinos canónicos** (p. ej. eventos de arcade integrados en Cine o Bar si encaja en diseño), pero **sin** reintroducir el destino legacy como entidad jugable.

### Gaps del Maestro que el plan ya cubre

- Tokens en mapa → **Fase 2.1 crítica**.
- `GeneradorResidente` incompleto → **Fase 6.1**.
- Residencias UI A/B/C → **Fase 3.1 eliminar**.
- Motor `ComplejoCatalog` → **Fase 2.3**: catálogo 9, retirar legacy.

### Tarea 0.1 refinada

Copiar Maestro a `docs/DOCUMENTO_MAESTRO_V3_AQUI_HAY_TEMA.md` y anexar **addendum canon V3** (9 lugares, sin economía/desbloqueo) donde el texto antiguo contradiga.

---

## Apéndice C — Decisiones técnicas cerradas antes de ejecución (21/08/2026)

| Tema | Decisión | Motivo breve |
|---|---|---|
| Curva de llegadas post-8 | Gap mínimo + jitter medio + 1/p_dia; fórmulas sin cambio; tabla E[T] corregida matemáticamente | Espaciado progresivo auditable; evita avalancha; reutiliza motor |
| Migración viviendas | Pool lógico 24 slots (A01–A16, B01–B08); schema v3 aditivo; espejo `bloque_a/b` al guardar | No perder saves; sin A/B/C en producto; retirada legacy en Fase 9.3 |
| Regla general futura | Decisiones técnicas no fijadas en Maestro → agente las cierra en este plan | Solo escalar a Neni si es producto o riesgo de pérdida de datos |

---

## Apéndice D — Misiones diarias V3 (21/08/2026)

| Tema | Decisión |
|---|---|
| ¿Desaparecen? | **No** — cambian de función |
| Economía | **Eliminada** — sin dinero ni encargos extra |
| Encargos extra Maestro | **LEGACY** — no hay código; no reintroducir |
| Peticiones B4 | **Distinto** — historias/problemas; buzón; multi-día |
| Recompensa principal | Vida: `min(3, 1+floor(exigencia/50))`, cap **+4/día** |
| No completar | **Sin castigo** (caducada = 0) |
| Recompensa secundaria | Discovery opcional ~30% en familias elegibles |
| Motor base | `MisionDiariaEngine` + `MisionPlantillas` |
| UI | Acceso visual propio en `play.php` (capas shell); etiqueta provisional «Hoy en el pueblo»/«Misiones»; placement fino → Neni |
| Pendiente producto | Placement visual exacto en shell (Neni) |

