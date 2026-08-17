# Validación del grafo romántico

Esto es el **núcleo del grafo** del nivel 2 de `CHECKLIST_AUDITORIA_PERSONAJES.md` (y del bloque Romance del nivel 1). Una ficha no es `aprobado` si falla esta regla (en pilotos, el recíproco puede quedar `pendiente_pool`).

## Regla (cerrada, matizada 2026-08-17)

Nadie del pool que **puede tener romance** está **condenado por diseño** a no poder tener nunca una relación recíproca.

**No buscar pareja no es un fallo.** Un habitante con `interes_romantico: ninguno` (campo candidato en `CONTRATO_PERSONAJE.md`) **no** entra en esta regla como «personaje roto».

Para quien **sí** puede tener romance (`activo` o `pasivo`):

Cada personaje A tiene **al menos un** B tal que el romance es *legal en los dos sentidos*:

1. No hay parentesco vetado entre A y B.
2. No hay dealbreaker **absoluto** de A hacia B ni de B hacia A que anule el romance para siempre. Un `absoluto` **no** puede ser lo único que deja a A (o a B) en 0 recíprocos: si pasa, se retoca el dealbreaker o el grafo.
3. `genero` de A ∈ `atraido_por` de B.
4. `genero` de B ∈ `atraido_por` de A.

Eso **no** implica pareja, química alta, que empiecen juntos ni un final único.

El **trío del día 1 no tiene por qué cumplir** esta regla *entre los tres*. La regla es de la **tanda** y de quien **puede coexistir** y **puede querer romance**, no de un catálogo futuro de 100. `PROPUESTA_VIDA_POOL_Y_MARCHAS.md`, `PROPUESTA_CELESTINE_BUZON_TIEMPO_PARTIDAS.md`.

Sí puede haber, **además**:

- atracciones unilaterales
- crushes imposibles (veto, dealbreaker, o el otro no corresponde)
- personas muy difíciles
- relaciones que solo suben tras evolución

## Qué no vale como salvavidas

- `apertura_descubrimiento` (`permeable` / `abierta`). Es narrativa. **No** cuenta para aprobar el roster.
- Gente que «llegará en el juego final» fuera de estos 16.
- Un recíproco que **solo** existe en una llegada, para alguien de los **10 iniciales** del esqueleto (`I01`–`I10`). La partida empieza con **~3**, no 10.

Los **10 del esqueleto** deben cumplir la regla **entre ellos** al validar la tanda — no confundir con el trío tutorial del día 1.

Tras cada llegada (orden 1…6), nadie que ya viva en el pueblo queda a 0 recíprocos legales por un veto nuevo.

## Cómo comprobarlo (cuando haya ids)

1. Listar los 16: `genero`, `atraido_por`, familia vetada, dealbreakers absolutos.
2. Para cada A, listar Bs que pasen los 4 puntos.
3. Si algún recuento es 0 → reescribir ficha o lazo familiar.
4. Repetir el recuento **solo con los 10** iniciales.
5. Ir añadiendo L01…L06 y repetir.
6. No optimizar hacia un matching perfecto.

No ejecutar esta comprobación hasta que existan las 16 fichas (o, en el paso intermedio, hasta que existan los 10).
