# Diario del pueblo

Nombre técnico (cerrado): **Diario del pueblo**.

Nombre visible más humorístico (`El cotilleo`, etc.): se puede estudiar después. No cambiar el id interno.

**Complemento candidato:** **Buzón de Celestine** — mensajes directos, subjetivos, en 1.ª persona. Ver `BUZON_DE_CELESTINE.md` y `PROPUESTA_CELESTINE_BUZON_TIEMPO_PARTIDAS.md`.

## Diario vs buzón

| | Diario | Buzón |
| --- | --- | --- |
| Voz | Narrador / cotilleo del barrio | Habitante → Celestine |
| Verdad | Hechos **observables** (objetivo/resumido) | Lo que **cree o dice** (puede mentir o equivocarse) |
| Pregunta | «¿Qué ha pasado en el pueblo?» | «¿Quién me necesita?» |

**Recomendación:** mantener **ambos**. El diario no desaparece.

## Cupo (cerrado)

Unas **5–10 entradas relevantes por día** visibles al jugador cuando hay vecindario de verdad. Cortas: una o dos líneas. No un log de 200.

Con **~3** habitantes el cupo debe ser **más bajo** (si no, se inventa ruido). Con A lleno (~16) o A+B, 5–10 es un techo: el diario elige, no resume a todos. Varios bloques **no** bajan el ruido de la cafetería. `PROPUESTA_BLOQUE_3_A_24.md`.

## Tipos internos (cerrados)

| tipo | Qué es | Indicador |
| --- | --- | --- |
| `ruido_humor` | Cotilleo tonto, vida | ninguno |
| `info_util` | Dato de contexto (dónde ha estado, ánimo) | ninguno |
| `pista` | Señal jugable | `👀` |
| `problema` | Aviso que conviene no ignorar | `⚠️` |

No todos los mensajes parecen importantes. La mayoría van sin icono.

## Ejemplos de tono (no son el roster)

- Marta ha dormido fatal.
- Joel ha pasado la tarde jugando.
- 👀 Eva y Lucía han coincidido otra vez en la cafetería.
- ⚠️ Nora lleva varios días evitando a Aina.

Las voces de las fichas alimentan el diario; no 16 veces la misma. Auditoría narrativa de pool: `CHECKLIST_AUDITORIA_PERSONAJES.md`.

Una línea de **coincidencia** («están otra vez en la cafetería») **no** afirma que hablaran. `ENCUENTROS_ESPONTANEOS.md`.

## Uso

Informa; no prohíbe la cita. Quien ignore ⚠️ y 👀 juega peor. Ver `ENCUENTROS_ESPONTANEOS.md`.

Un cambio **consolidado** (o un moretón fuerte) de gustos puede dejar una línea 👀, sin números: `APRENDIZAJE_PREFERENCIAS.md`. El cupo 5–10 manda.

Plantilla: `data/diario/_plantilla.entrada.json`.
