# Plantillas de agenda — borrador (Fase B)

**Estado:** `diseñado` — no implementado.  
**Fuente:** `DECISIONES_CERRADAS.md` (agenda 1 h / 24 h) · `PROPUESTA_AGENDA_DISPONIBILIDAD_RUTINAS.md`

El motor resuelve agenda; la ficha aporta **ocupación**, **sesgo de franja**, **0–3 `compromisos_recurrentes`** y excepciones. **No** diseñar 24×16 manualmente.

---

## Modelo interno

```text
Día = 24 slots de 1 hora (00–23)
Capas: estructural > programado > temporal > autónomo
```

Visible al jugador: hora concreta (`17:00`); UI sugiere primer slot libre compatible.

---

## Plantillas por `ocupacion` (estructural A)

Bloques recurrentes semanales. Variación leve permitida (±1 h, 1 día libre/mes) vía excepción temporal.

| Ocupación | Bloque trabajo (días) | Horas típicas | Sueño orientativo | Notas |
| --- | --- | --- | --- | --- |
| `oficina` | lun–vie | 09–17 | 23–07 | Rocío (I08): encaja |
| `camarero` | mar–dom o rotativo | 16–24 | 01–09 | Turnos noche |
| `sanitario` | lun–vie + 1 sáb | 07–15 **o** 14–22 | según turno | Plantilla `turno_manana` / `turno_tarde` |
| `comercio` | lun–sáb | 10–14 + 17–21 | 23–07 | Partido (siesta comercial) |
| `docente` | lun–vie | 08–15 | 22–06 | I03, L04 |
| `estudiante_adulto` | lun–vie | 10–14 + estudio 17–19 | 00–08 | Flexible fines de semana |
| `autonomo` | lun–vie | 10–18 (bloque blando) | 00–08 | Más huecos autónomos |
| `jubilado` | — | sin bloque laboral | 22–06 | I10 |

### Sueño

Capa **estructural dura**. Ventana por plantilla de vida (`sueño_estandar`, `sueño_nocturno`). No simular cansancio Sims.

---

## Excepciones personales (ficha)

Campo **`compromisos_recurrentes`** (0–3 por personaje). **Aprobado Fase B:** 0 es válido; no rellenar artificialmente. Distribución natural: 0 / 1 muy característico / 2 / excepcionalmente 3.

```yaml
- id: bingo_viernes
  dia_semana: viernes
  hora_inicio: 17
  hora_fin: 18
  lugar: lug_bingo          # puede estar cerrado en mapa
  descubrible: true
  publico_en_ficha: false

- id: pesca_madrugada
  dia_semana: sabado
  hora_inicio: 5
  hora_fin: 7
  lugar: lug_parque         # o off-map
  descubrible: true
```

Ejemplo Rocío (propuesta, no canon hasta ficha): `bingo_viernes` — coherente con hobby.

---

## Resolución (orden)

1. Cita confirmada (Celestine)
2. Trabajo / sueño estructural
3. Temporal (evento)
4. `compromisos_recurrentes`
5. Plan autónomo (cafetería, paseo…)

**Regla:** un slot = una ubicación/actividad. Propuesta en hora ocupada → rechazo con motivo (`voz` + plantilla).

---

## UI mínima (diseño)

- Mostrar horas **incompatibles** (gris/bloqueado)
- Impedir doble reserva
- Botón «siguiente hora compatible» para par A–B
- No editar agenda NPC manualmente (solo proponer)

---

## Contenido pendiente (mínimos — definir antes de implementar)

| Catálogo | Estado | Nota |
| --- | --- | --- |
| Plantillas ocupación (8) | diseñado | Este doc |
| Motivos rechazo cita por `voz` (5 voces) | pendiente cifra | `REGLAS_NO_NEGOCIABLES` §3 |
| Respuestas contraoferta hora | pendiente cifra | |
| Eventos cambio turno | post-V0 candidato | |

**RECORTE_V0:** no aplicado — plantillas base entran en V0; biblioteca grande de rechazos puede recortarse **solo** con registro explícito.

---

## Prueba micro-sim (Fase D)

Con pilotos Rocío + B + C:

1. Día tipo: proponer cita 17:00 vs bingo / trabajo / sueño
2. Madrugada: slot 05:00 solo si ficha tiene excepción
3. Autonomía: 2 NPC coinciden cafetería sin cita Celestine

*No programar hasta checklist pre-implementación agenda cumplida.*
