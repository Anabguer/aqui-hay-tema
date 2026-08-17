# Buzón de Celestine

**Cerrado para V0** (2026-08-18). Pequeño pero obligatorio en la 1.ª prueba. Análisis ampliado: `PROPUESTA_CELESTINE_BUZON_TIEMPO_PARTIDAS.md`.

## Qué es

Bandeja de **mensajes directos** de habitantes → **Celestine** (el jugador). Complementa — no sustituye — el **Diario del pueblo**.

## Reglas de diseño

1. **Voz en 1.ª persona** del habitante (petición, queja, confesión, rumor personal).
2. El mensaje refleja lo que **cree o dice**, no el estado interno real del motor.
3. Puede **equivocarse, ocultar o interpretar mal**.
4. **No** revela automáticamente las 4 variables románticas ni `atraido_por`.
5. Una **marcha** puede empezar aquí; **nunca** se cierra offline sin Celestine.
6. Mantener posibilidad de un **resumen objetivo mínimo** de hechos del pueblo si la simulación demuestra que hace falta (canal aparte o entrada especial; no sustituye la voz subjetiva).

## Tipos mínimos V0

| tipo | Uso |
| --- | --- |
| `bienvenida` | Tutorial / primer contacto |
| `peticion` | Pedir cita, conocer gente, ayuda |
| `problema` | Conflicto, queja, soledad |
| `respuesta` | Reacción a propuesta de Celestine |
| `descubrimiento` | Información o pista relevante (subjetiva) |
| `aviso_comunidad` | Desbloqueos, movimiento en un local cerrado, etc. |

Ids internos candidatos ampliados (post-V0): `conflicto`, `soledad`, `amistad`, `marcha`, `cuento`, `venteo` — ver tabla histórica abajo.

## Diario vs buzón

| | Diario | Buzón |
| --- | --- | --- |
| Perspectiva | Pueblo / narrador | Habitante → Celestine |
| Verdad | Hechos observables | Subjetivo |
| Cupo | 5–10/día (cerrado) | 0–5/día (pequeño en V0) |
| Uso | «¿Qué ha pasado?» | «¿Quién me necesita?» |

## Offline (tiempo 1:1)

- Los mensajes **pueden acumularse** mientras no juegas.
- **No** se resuelven solos al volver (salvo rutina comprimida en diario).
- Los urgentes (`marcha`, crisis) quedan **pendientes de pulsar**.

Plantilla futura: `data/buzon/_plantilla.mensaje.json` (no crear hasta implementación).

## Tipos conceptuales ampliados (referencia)

| tipo | Ejemplo |
| --- | --- |
| `pedir_cita` | «¿Me organizas algo con…?» |
| `conocer_gente` | «Llevo semanas sin hablar con nadie nuevo.» |
| `conflicto` | «No soporto a X.» |
| `soledad` | «Necesito compañía, no sé con quién.» |
| `amistad` | «Quiero arreglar las cosas con Y.» |
| `marcha` | «Estoy pensando en irme.» → flujo consentido |
| `cuento` | «Anoche pasó una cosa…» |
| `venteo` | Sin acción clara; solo contexto |
