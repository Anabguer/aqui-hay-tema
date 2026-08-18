# Propuesta catálogos V2 — NO activa

**Estado:** propuesta técnica. No cambia validadores ni la ficha de Rocío.

## Diagnóstico

Rocío **no está mal**. El validador T4 usa `ContractEnums` copiados del contrato V0 (12 hobbies, 10 rasgos, 6 estilos). Los JSON en `data/catalogos/` **ya incluyen** `bingo`, `sociable_selectiva`, `observadora`, `practica`, `socarrona` (añadidos para piloto_01). El contrato markdown no se actualizó.

Regla permanente: si el contenido es válido creativamente y el contrato no lo contempla, se revisa el contrato.

## bingo → A (hobby válido)

`lug_bingo` existe. `hobbies_lugares.json` ya mapea `bingo → lug_bingo`. No mapear a `cafe_social`. Puede coexistir con un compromiso recurrente, pero el hobby es el lugar correcto.

## sociable_selectiva → ejes, no un enum más

El enum V0 es un conjunto de arquetipos mutuamente excluyentes. No cubre combinaciones. Propuesta: 3 ejes (`energia_social` × `selectividad` × `ritmo`) + etiqueta opcional.

## Rasgos / hobbies / voces

Ver `data/catalogos/_propuesta_v2/`. Ids de rasgos neutros + **alias** para no reescribir Rocío.

## Compatibilidad hacia atrás

- Enums técnicos (género, franja, ocupación, severidad) se quedan cerrados.
- Hobbies, rasgos, estilos, voces → catálogo JSON extensible.
- Validador debería leer el catálogo, no PHP hardcodeado (pendiente de aprobar V2).
- Alias `observadora→observador` etc. sin tocar `per_i03.json`.

## BLOQUEADO_DECISION

Aprobar V2 hobbies (22), rasgos (~28 + alias), ejes de estilo, voces (8).
No asignar políticas Discovery a Rocío todavía.
