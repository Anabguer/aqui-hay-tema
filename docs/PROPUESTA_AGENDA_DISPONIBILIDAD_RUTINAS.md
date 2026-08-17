# Propuesta: agenda, disponibilidad y evolución de rutinas

**Diseño. No es código.** Ronda 2026-08-18. Complementa `VIDA_DE_HABITANTES.md`, `RELACIONES_Y_CITAS.md`, `RELACIONES_SOCIALES.md`, `MAPA_Y_LUGARES.md`, `PROPUESTA_CELESTINE_BUZON_TIEMPO_PARTIDAS.md`.

---

## Resumen ejecutivo

Cada residente tiene **agenda propia**: suficiente para saber dónde debería estar, bloquear tiempo de citas y diferenciar personajes — **sin** simular minuto a minuto ni convertir el juego en Outlook.

Celestine **propone** planes; los personajes **aceptan o rechazan** con voz propia. El rechazo por agenda puede **descubrir** rutinas.

La complejidad va en **quién coincide con quién**, no en **quién vive en el A07** (vivienda automática, ya cerrada).

---

## Qué compramos (de la propuesta)

| Idea | Por qué encaja |
| --- | --- |
| Agenda por persona | Sustituye el modelo plano de «una franja + trabajo off-mapa». Da personalidad, conflictos y mapa creíble. |
| Bloqueos recurrentes (bingo, madre, entreno) | Diferencian fichas sin inventar 16 calendarios distintos en diseño. |
| Trabajo que **cambia** en partida | Eventos con consecuencias sociales (dejan de coincidir). Encaja con goteo económico y narrativa. |
| Cuatro capas de bloqueo (estructural / programado / autónomo / temporal) | Orden claro; evita dos actividades imposibles. |
| Propuesta + aceptación/rechazo narrativo | Ya cerrado: «Celestine propone, los personajes viven». |
| Rechazo que **descubre** rutina | Conecta agenda + ficha que crece + bingo desbloqueable después. |
| Citas ocupan tiempo real | Imprescindible para dobles reservas y presencia en mapa. |
| UI que **sugiere** horas compatibles | Resuelve el miedo a «16 calendarios» sin burocracia. |
| Presencia visual alimentada por agenda | Cabezas en sitio; fade in/out; no Los Sims caminando. |
| Relaciones **no lineales** | Ya iba el juego; hay que nombrarlo y darle motor. |
| Enemigo → romance **raro y causal** | Memorable; no lotería. |
| Vínculo no binario (confío / me irrita / me atrae…) | Las 4 variables + social no bastan solas para «me cae fatal pero me tira». |

---

## Qué cambiaría (ajustes Cursor)

1. **Granularidad interna: bloques de 1 hora** (ver § Modelo). El jugador ve `17:00`, `18:00`. No minutos.
2. **Sueño como bloqueo estructural**, no barra. Ventana aproximada por personaje (p. ej. 23:00–07:00), no simulación de cansancio Sims.
3. **Enemistad** como estado social derivado (`conflicto_social` alto + lazo), no solo tipo `roce`. Evita inflar el catálogo de parentescos.
4. **Primera prueba:** agenda por **plantillas** + slots 1 h (24 h). **No** «agenda reducida» solo franja. Motor resuelve; ficha aporta excepciones y `compromisos_recurrentes`.
5. **Respuestas a propuestas:** plantillas por `voz` + slots de motivo, no LLM en runtime.
6. **Convivencia:** al mudarse juntos, **fusionar** agendas de «en casa» noche; no nueva mecánica.

---

## Granularidad recomendada

### Interno (motor)

```text
Día = calendario de pueblo (1:1 candidato)
Slot = 1 hora, índices p. ej. 07–23  (17 slots/día)
```

Por qué 1 h y no 30 min: con ~16 residentes × 17 slots = 272 celdas/día — manejable. 30 min duplica ruido sin ganar mucho para «viernes 17:00 bingo».

Por qué no solo `manana`/`tarde`/`noche`: no distingue «viernes 17:00» de «viernes 10:00».

### Visible (jugador)

Horas normales: `17:00`, `19:30` (las medias horas solo en **propuesta de cita**, redondeando al slot libre más cercano).

Opcional en mapa: agrupar «ahora» en **mañana / tarde / noche** para no saturar.

---

## Cuatro capas de agenda

| Capa | id | Ejemplos | Origen | Persistencia |
| --- | --- | --- | --- | --- |
| **A. Estructural** | `estructural` | trabajo, sueño, bingo viernes 17–18, visita madre | Ficha + plantilla de oficio | Recurrente semanal hasta cambio |
| **B. Programado** | `programado` | cita Celestine, médico, viaje | Celestine / evento | Fecha concreta |
| **C. Autónomo** | `autonomo` | ir a cafetería, biblioteca, casa | Motor NPC | Ese día/franja |
| **D. Temporal** | `temporal` | turno extra, boda familiar, resfriado | Evento | Rango fechas; caduca |

### Prioridad al resolver conflicto (mayor gana)

1. **Cita programada confirmada** (B) — compromiso con Celestine o pareja ya aceptada.
2. **Bloqueo estructural duro** — trabajo activo, sueño (salvo evento script «turno de noche»).
3. **Temporal** (D) — sustituye rutina autónoma ese día.
4. **Estructural recurrente personal** (A) — bingo, familia.
5. **Autónomo** (C) — el más débil; se replanifica si choca.

Regla: **nunca dos actividades incompatibles en el mismo slot.** Si chocan A y C, gana A; C se elige otro slot u otro sitio.

### Incompatibilidades (ejemplos)

| Actividad A | Actividad B | Resultado |
| --- | --- | --- |
| Trabajo | Cita propuesta | Rechazo o contraoferta de hora |
| Bingo | Cita 17:30 | Rechazo con motivo |
| Cita 19–20 | Cita 19:30 | Imposible; segunda rechazada |
| Sueño | Cualquier plan | No disponible |
| En cafetería (C) | En biblioteca (C) | Imposible; una sola ubicación por slot |

---

## Trabajo dinámico

Estado laboral en **runtime**, semilla en ficha:

| Estado | Agenda |
| --- | --- |
| Sin trabajo | Sin bloqueo laboral (puede tener otros) |
| Empleado | Bloques semanales según `ocupacion` + plantilla |
| Turno excepcional | Bloque temporal (D) ese día |
| Desempleado / fin contrato | Quita bloques futuros |

Evento ejemplo: «Le ofrecieron trabajo a X». Si acepta (NPC o tras Celestine): inserta bloques A de trabajo. **Coincidencias con otros cambian** sin aviso mágico — el diario puede 👀 «Ya no se cruzan en la cafetería».

Plantillas por `ocupacion` (catálogo, no por ficha):

- `oficina` → lun–vie 09–17
- `camarero` → tarde/noche variado
- `jubilado` → sin bloque laboral
- etc.

La ficha guarda `ocupacion` + `franja_disponibilidad` como **sesgo**, no como horario exacto. El horario exacto sale de plantilla + variación leve (±1 h, un día libre).

---

## Rutinas recurrentes (ficha)

En **A (interna)**, no en B salvo pistas:

```text
compromisos_recurrentes[]:
  - id: bingo_viernes
    dia_semana: viernes
    hora_inicio: 17
    hora_fin: 18
    lugar: lug_bingo          # puede estar 🔒 al inicio
    etiqueta: "bingo"
    descubrible: true          # Celestine puede aprenderlo al rechazar
    publico_en_ficha: false    # hasta descubrir
```

Cardinalidad recomendada por ficha: **0–3** compromisos. No todos iguales.

Al rechazar una cita:

- Si `descubrible` y choca → respuesta con motivo → `runtime.descubrimientos_jugador.rutinas += bingo_viernes`
- Ficha muestra: «Viernes por la tarde: bingo» (o `…` si no descubierto)

---

## Proponer un plan (Celestine)

Contrato de propuesta:

```text
persona_a
persona_b?          # opcional (amistad / cita / mediación)
tipo_encuentro      # romantico | amistoso | mediacion | otro
lugar
fecha + hora_inicio + duracion   # p. ej. 60 min → ocupa slots
```

### Comprobaciones (orden)

1. **Agenda** — slots libres para A (y B si hay).
2. **Lugar** — desbloqueado + **aforo** esa hora.
3. **Capa social** — relación, personalidad, ánimo, conocimiento mutuo.
4. **Legalidad romance** — si aplica (parentesco, dealbreaker, `atraido_por`…).

### Respuesta

Narrativa, **voz** (`seca`, `borde`, `candida`…). Motivos posibles:

- no te conozco
- no me apetece
- tengo bingo
- estoy enfadada con él
- me da curiosidad
- sí, venga

Misma situación, distinta voz → distinto texto. **No** una sola frase genérica.

Si acepta → crea bloque **B (programado)** + reserva aforo + aparecerá en mapa a la hora.

---

## Presencia en mapa

| Estado agenda | Mapa |
| --- | --- |
| Trabajo (off-site) | **No** en sitios sociales. Opcional: icono «trabajando» en ficha. |
| Sueño / en casa | No en sitios sociales. |
| En `lug_cafeteria` | Cabeza en cafetería (si aforo lo permite) |
| Cita confirmada | Ambas cabezas en el lugar a la hora |
| Bloqueo bingo en `lug_bingo` 🔒 | Si cerrado: replanificar o «en casa»; si abierto: cabeza ahí |

Transición: **fade** breve in/out. Sin pathfinding.

La agenda es la **fuente de verdad** de `runtime.ubicacion_actual` por slot.

---

## Citas y tiempo

Cita 19:00–20:00 = slots 19 y 20 ocupados (o 19:00–20:00 mapeado a slots según duración).

Al terminar:

1. Resolver encuentro (micros, informe).
2. Actualizar relaciones / ánimo.
3. Diario / buzón si toca.
4. Liberar slots **posteriores**; el slot de la cita ya pasó.

**Doble reserva:** imposible por construcción si el motor marca slots `ocupado`.

---

## UI: sin burocracia

Celestine **no** abre 16 calendarios.

Flujo propuesto:

```text
Rocío + José · Cafetería · HOY
17:00 ❌  (bingo)
18:00 ❌
19:00 ✓
20:00 ✓

[ Primera hora compatible: 19:00 ]   ← botón
```

Motivo de ❌ opcional al pulsar (si descubierto). La UI **calcula** intersección de agendas + aforo.

---

## Tiempo real 1:1 y offline

| Situación | Comportamiento |
| --- | --- |
| Cita programada mientras AFK | **Se resuelve** offline (ya cerrado). Informe al volver. |
| Slots intermedios | Comprimidos; no simular hora a hora. |
| Marcha / «quédate» | Pendiente; nunca offline. |
| Trabajo nuevo mid-AFK | Puede aplicarse al catch-up del día con resumen. |

El reloj del pueblo avanza **días**; dentro del día la agenda usa slots solo cuando Celestine **mira** o cuando toca **resolver** una cita.

---

## Relaciones no lineales

### No imponer escalera única

Rutas válidas (ejemplos):

- desconocido → conocido → amigo → romance
- desconocido → atracción → citas → romance
- conocido → roce → **tensión alta** → enemistad
- enemistad → (evento causal) → curiosidad → atracción → romance (**raro**)

### Registro social (par A–B)

Además de `tipo` de lazo y las **4 variables románticas** (si hay pareja o atracción activa):

| Eje social | Rango | Notas |
| --- | --- | --- |
| `afecto` | −2…+2 | me cae fatal ↔ me cae muy bien |
| `confianza` | −2…+2 | no confío ↔ confío |
| `tension` | 0…+2 | roce, enemistad activa |
| `atraccion_a_hacia_b` | (ya existe) | puede existir **sin** pareja y **con** enemistad |

Estados **derivados** para UI (no barras):

- «me cae bien pero no confío»
- «me atrae pero me irrita»
- «amigos con tensión»
- «ex que se llevan bien»
- «enemistad con química rara»

### Enemigo → romance

**Prohibido:** tirada «ahora te gusta tu enemigo».

**Permitido:** cadena de **eventos** con `causas[]` (misma filosofía que `APRENDIZAJE_PREFERENCIAS.md`):

- te defiende cuando nadie lo hace
- conversación sorprendentemente buena
- colaboración obligatoria
- reconciliación parcial + afinidad oculta

Campo candidato en ficha: `susceptibilidad_enemigo_romance`: `nula` | `baja` | `media` | `alta`.

- `nula`: jamás por este pipeline
- `alta`: posible, **sigue siendo raro** (probabilidad baja + hitos)

Resultado memorable: «Espera… ¿por qué me está empezando a gustar este imbécil?» — evento ⚠️ / entrada diario, no ruido diario.

---

## Comparación con el modelo actual

| Hoy | Propuesta |
| --- | --- |
| `franja_disponibilidad` (manana/tarde/noche) | **Semilla** + plantilla; la agenda real es runtime |
| `ocupacion` estática | + estado laboral dinámico |
| «Una franja de trabajo + sueño» (`PROPUESTA_VIDA_POOL`) | Bloques A con slots concretos |
| Cita: lugar + franja + aforo | + **duración** + ocupación de slots |
| `lazos[]` tipo amigo/ex/roce | + ejes sociales A–B + estados derivados |
| `descubrimientos_jugador` previsto | + rutinas, horarios, trabajo |
| Calor amistad `conocido→amigo` | Compatible; no es la única ruta |
| Encuentros: coincidir ≠ interactuar | Igual; agenda pone gente en sitio; motor decide interacción |

### Qué añadir al contrato / modelo

**Ficha (A):**

- `compromisos_recurrentes[]` (0–3)
- `plantilla_sueno` o ventana sueño derivada de edad/ocupación
- `susceptibilidad_enemigo_romance` (candidato)
- `estado_laboral_inicial` (opcional: empleado / sin trabajo)

**Runtime:**

- `agenda_slots[]` por día o generación bajo demanda
- `estado_laboral` + historial
- `descubrimientos_jugador.rutinas[]`
- `relacion_social.ejes` por par
- `ubicacion_actual`, `actividad_actual`

**Catálogos:**

- `plantillas_horario_ocupacion.json`
- tipos de `compromiso_recurrente`

**UI (futuro):**

- selector de plan con horas ✓/❌
- primera hora compatible

---

## Riesgos

| Riesgo | Mitigación |
| --- | --- |
| 16 × 17 slots = complejidad | Generar bajo demanda; autónomos ligeros; 1.ª prueba recortada |
| Rechazos repetitivos frustran | UI sugiere hora buena; motivos con voz, no solo «no» |
| Rutinas 🔒 (bingo sin lugar) | Replanificar o compromiso «en casa» abstracto hasta desbloqueo |
| Trabajo cambia y rompe historias | Feature; diario 👀 «ya no coinciden» |
| Enemigo→romance parece random | Solo con evento + causas + susceptibilidad |
| Tiempo 1:1 + agenda horaria | Simular slots solo en sesión activa + citas; resto comprimido |
| Contenido: 16 fichas × 3 rutinas | Auditar en diseño; no obligatorio 3 para todos |
| Confundir agenda con vivienda | Docs separados; vivienda sigue automática |

---

## Contradicciones

| Antes | ¿Choca? | Resolución |
| --- | --- | --- |
| Vida NPC **mínima** cerrada (5 campos) | No | El mínimo sigue; la **agenda es runtime** + 0–3 compromisos en ficha. No convierte en Sims. |
| «Sin calendario de fichar» (`PROPUESTA_VIDA_POOL`) | No | El **jugador** no ficha horas a mano; la UI **sugiere**. |
| «El jugador no edita rutina» (V0) | No | Sigue. Celestine **propone** citas, no arrastra bloques en un planner. |
| `franja_disponibilidad` en ficha | No | Pasa a ser **orientación**, no el horario entero. |
| Calor amistad lineal (BLOQUE_3_A_24) | No | Convive con rutas múltiples; el calor es una vía, no la única. |
| Catálogo lazos sin `enemigo` | **Suave** | Usar `tension` + afecto bajo; `enemistad` como **estado derivado**, no tipo de parentesco. |
| Coincidir ≠ interactuar | No | Refuerzo: agenda = estar; motor = hablar o no. |
| Catch-up 12–24 h obsoleto con 1:1 | No | Citas programadas se resuelven; slots AFK se comprimen. |
| Prohibido simulación laboral compleja | No | Plantilla + bloques; sin productividad ni ascensos Excel. |

**Ninguna contradicción grave.** Evoluciona el modelo de «franja + off-mapa» hacia agenda con slots.

---

## Decisiones a cerrar AHORA

1. **Granularidad interna = 1 hora** (07–23). ¿OK?
2. **1.ª prueba jugable:** ¿agenda **mínima** (trabajo + sueño + citas + 0–1 rutina) o sistema completo?
3. **Enemistad:** ¿estado derivado (`tension` + afecto) o nuevo `tipo` en catálogo?
4. **Duración default de cita:** ¿60 min / 90 min / según lugar?
5. **¿Semilla `susceptibilidad_enemigo_romance` en ficha** ya en tanda 1, o solo en simulador?

### Puede esperar

- Plantillas exactas por ocupación
- Medias horas en UI
- Planes autónomos hora a hora en 1.ª prueba
- Animación más allá de fade
- Eventos de cambio de trabajo (contenido)
- Fórmulas de aceptación/rechazo

---

## Relación con otros docs

- Vivienda automática: `PROPUESTA_VIDA_POOL_Y_MARCHAS.md` (no mezclar)
- Aforo: `MAPA_Y_LUGARES.md`
- Coincidencia: `ENCUENTROS_ESPONTANEOS.md`
- Aprendizaje causal: `APRENDIZAJE_PREFERENCIAS.md`
- Descubrimiento: `PROPUESTA_BLOQUE_3_A_24.md` §5
- Modelo datos: `MODELO_DATOS_INICIAL.md`
- Contrato: `CONTRATO_PERSONAJE.md`

---

## Preguntas para Neni y ChatGPT

1. ¿0–3 compromisos recurrentes por ficha os suena bien?
2. ¿Ejemplos de rutina preferís que apunten a lugares 🔒 (bingo) o solo a sitios del día 1?
3. ¿La UI de «primera hora compatible» os encaja?
4. ¿Enemistad→romance: cuántos personajes `susceptibilidad` > nula en 16?
5. ¿Cita estándar = 1 hora?
