# Modelo de datos inicial

Propuesta. **No es código.** `0–100` = hipótesis, no canon.

No existe `compatibilidad = X%`. Las 4 variables de pareja **sí** se guardan (la atracción, en dos sentidos).

---

## PERSONAJE

Canónico: `data/personajes/_plantilla.personaje.json`. Entrega humana: `docs/CONTRATO_PERSONAJE.md`. B se deriva; no se duplica.

| Bloque JSON | Qué es |
| --- | --- |
| `id` `slot` `piloto` | Claves de pool / piloto / auditoría |
| `identidad` | Nombre, edad, género, `atraido_por`, apertura. Fijo. |
| `vida` | Oficio, franja, hobbies, rasgos, gustos. Fijo. |
| `romance` | Preferencias `{texto, visible, tipo, eje?}`, dealbreakers `{texto, severidad, eje?}`, necesidad de contacto, `plasticidad_preferencias` |
| `lazos[]` | `{persona, tipo, publico}` — familia, amigos, ex, roce |
| `presencia_v0` | Empieza aquí / orden y función de llegada |
| `visual` | Físico + look + `retrato.estado` |
| `narrativa` | `voz`, frase semilla |
| `auditoria` | `estado_ficha`, `reciprocos_potenciales` |
| `estado_inicial` | Ánimo `neutro`, crush semilla |
| `runtime` | **Vacío en diseño.** Ánimo de hoy, citas, las 4, `atracciones_hacia`, `aprendizaje_preferencias` |

Hambre/higiene/vejiga: **no existen**.

`estado_ficha`: `borrador` \| `revision` \| `aprobado`. Pool: `estado_pool` en `personajes.json`.

---

## RELACIÓN SOCIAL

Semilla en `personaje.lazos`. Runtime extra en `runtime.lazos_nuevos` / archivos de relación.

| Campo | Notas |
| --- | --- |
| `persona` | `per_xx` o slot `I07` |
| `tipo` | catálogo `tipos_relacion_social.json` (madre, hermano, amiga, ex, roce…) |
| `publico` | si B puede nombrarlo |
| veto | `grupo_veto` del tipo; lista cerrada en `parentescos_veto_romance.json` |

---

## RELACIÓN ROMÁNTICA — núcleo

| Campo | Rol |
| --- | --- |
| `atraccion_a_hacia_b` / `atraccion_b_hacia_a` | principal (asimétrica) |
| `vinculo` | principal (compartido) |
| `conflicto` | principal (compartido) |
| `necesidad_contacto_a` / `necesidad_contacto_b` | principal (por persona) |
| `recuerdos_compartidos`, `historial_citas`, `historial_encuentros` | historial |
| `estado_actual`, `fecha_inicio` | runtime |
| `chispa`, `confianza`, `comodidad`, `deseo`, `riesgo_ruptura`, `estado_visual` | **derivados**, no núcleo |

En ficha: reglas de tipo = `identidad.atraido_por`. Semilla unilateral = `estado_inicial.crush`. Números hacia alguien = `runtime.atracciones_hacia` / relación.

---

## CITA

Participantes, lugar desbloqueado, hora, actividad, ropa, snapshot previo, micros, resultado, deltas de las **4**, recuerdos, día (dentro de temporada), coletilla.

Antes de confirmar (cita de Celestine): lugar abierto + **aforo disponible** + **slots libres** en agenda de cada participante + duración. Sin reservas de mesas. UI sugiere primera hora compatible. `PROPUESTA_AGENDA_DISPONIBILIDAD_RUTINAS.md`.

---

## AGENDA (candidato)

Por personaje, por día. Slots **1 h** (índice 07–23). Una actividad por slot.

| Capa | id | Ejemplos |
| --- | --- | --- |
| Estructural | `estructural` | trabajo, sueño, bingo viernes |
| Programado | `programado` | cita confirmada |
| Autónomo | `autonomo` | cafetería, paseo |
| Temporal | `temporal` | turno extra, evento |

`runtime.agenda`, `runtime.estado_laboral`, `runtime.ubicacion_actual`.

Ficha: `compromisos_recurrentes[]` (0–3), plantilla sueño, `susceptibilidad_enemigo_romance` (candidato).

---

## RELACIÓN SOCIAL — ejes (candidato)

Por par A–B (además de `tipo` de lazo):

| Eje | Rango |
| --- | --- |
| `afecto` | −2…+2 |
| `confianza` | −2…+2 |
| `tension` | 0…+2 |

Estados derivados para UI. Enemistad = derivado, no tipo de parentesco.

---

## LUGAR

`desbloqueado_inicial`: true solo cafetería, parque, biblioteca (1.ª prueba: café primero).

Mapa V0: además, candados visibles cine, bar, arcade. El resto del catálogo no necesita estar en el tablero de la prueba.

`tipo`: `casa` distinta de parcela comercial (`comprable: false`).

`aforo`: **null hasta prueba de mapa.** Principio cerrado: cada sitio social tiene cupo de cabezas simultáneas. `MAPA_Y_LUGARES.md`.

`coste_dinero` / `requisito_fama`: null.

Catálogo: `data/lugares/lugares.json`.

Coincidencia en un lugar = oportunidad, no interacción automática.

---

## VIVIENDA (runtime / partida)

| Campo | Notas |
| --- | --- |
| `apartamento_id` | p. ej. `A01`…`A16`, `B01`…`B16` |
| `bloque_id` | derivado del id (`a` / `b`) |
| asignación | **automática:** primer hueco libre. Celestine no elige. |

No hay preferencias de portal ni stats por piso. Convivir: un apto, dos residentes, el otro hueco se libera.

---

## DIARIO DEL PUEBLO

| Campo | Notas |
| --- | --- |
| `tipo` | `ruido_humor` / `info_util` / `pista` / `problema` |
| `texto` | 1–2 líneas |
| `indicador` | `""` / `"eyes"` / `"warn"` → 👀 / ⚠️ |
| `momento` | día + franja + id de temporada |

Cupo visible: 5–10/día **cuando el pueblo es mediano**. Con ~3 vecinos el cupo alto es relleno. Con ~16 el cupo es un filtro (la rutina NPC no entra). `PROPUESTA_VIDA_POOL_Y_MARCHAS.md`.

---

## JUGADOR / PARTIDA

| Campo | Notas |
| --- | --- |
| `partida_id` | Candidato: ranura persistente |
| `dinero`, `fama` | null hasta economía |
| `temporada_actual` | id |
| `dia_en_temporada` | int |
| `dia_pueblo` | Candidato tiempo 1:1: días transcurridos desde inicio |
| `ultima_sesion` | Candidato: timestamp para catch-up |
| `lugares_desbloqueados` | ids |
| `bloques_abiertos` | Candidato: `['a']` en 1.ª prueba; `'b'` futuro |
| `capacidad_por_bloque` | `a` ≈ 16; `b` ≈ 16 cuando exista |
| presencia de habitantes | `nunca_ha_vivido` / `residente` / `nuevo` / `pensando_en_irse` / `ausente_temporal` / `antiguo_residente` |

**Por partida (candidato):** `estado_en_partida` por ficha = `no_presentado` · `residente` · `archivado`. Archivado **excluido** de llegadas aleatorias en esta partida.

Marchas: `pensando_en_irse` espera a Celestine. Offline **no** lo resuelve. «Quédate» no tira dado.

**Partidas (concepto):** persistente · nueva · reiniciar · pool independiente por partida. `PROPUESTA_CELESTINE_BUZON_TIEMPO_PARTIDAS.md`.

## BUZÓN (candidato)

| Campo | Notas |
| --- | --- |
| `mensaje_id` | |
| `de_persona` | id habitante |
| `tipo` | `pedir_cita` · `conflicto` · `marcha` · … |
| `texto` | Lo que **cree/dice**, no variables internas |
| `dia` | |
| `estado` | `pendiente` · `leido` · `resuelto` |

Ver `BUZON_DE_CELESTINE.md`.

Duración de temporada: no cifrar.

---

## Compatibilidad

Evaluador futuro: capa 1 (atracción-por-tipo + parentesco + dealbreaker `absoluto`) → luego sesgos de capas 3–9 sobre las 4 variables. Sin % persistido.
