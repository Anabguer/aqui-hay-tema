# Sistema de personajes

Fichas: se diseñan ahora con `FASE_DISENO_POOL_16.md`. Aquí el sistema, no los nombres.

## Escala

- **Partida:** ~3 al día 1. **Bloque A ≈ 16** simultáneos (hipótesis). Ampliar = **Bloque B ≈ 16** (candidato; no un 24 único). **Pool** = tandas (ahora 16 fichas). No son la misma cifra.
- Marchas: última palabra del jugador. «Quédate» = se queda; la causa vira a arco.
- Detalle: `PROPUESTA_BLOQUE_3_A_24.md` + `PROPUESTA_VIDA_POOL_Y_MARCHAS.md`

Nadie es un muñeco apagado. Ver `VIDA_DE_HABITANTES.md`.

## Identidad y atracción (capa 1)

- `genero`: `mujer` / `hombre` / `no_binarie`
- `pronombres`
- `atraido_por`: lista de esos mismos valores
- `etiqueta_orientacion_visible`: opcional, no manda
- `apertura_descubrimiento`: `cerrada` / `permeable` / `abierta` (narrativa; no salva el grafo)
- Parentesco incompatible = veto (lista en `RELACIONES_SOCIALES.md`)

Edad adulta V0 recomendada: **22–72**.

Al escribir fichas: `VALIDACION_GRAFO_ROMANTICO.md` + auditoría nivel 1/2. Recíproco potencial obligatorio. Una ficha piloto puede quedar en `revision` hasta que existan destinos en el pool.

## Visible vs oculto

El jugador no ve números internos.

Visible en **El pueblo**: nombre, edad, pronombres si procede, retrato, 3 rasgos públicos, hobby, lugares preferentes, estilo social, ocupación a groso modo, estado sentimental, disponibilidad, flag nuevo, ⚠️, cita.

Oculto o descubrible: dealbreakers, `atraido_por` fino, apertura, preferencias estéticas, necesidad de contacto como cifra, crush, rasgos ocultos.

## Vida NPC mínima (cerrada)

- ocupación (catálogo de 8)
- franja habitual de disponibilidad
- hobby principal
- estado de ánimo simple
- lugar o lugares preferentes

Prohibido: hambre, higiene, vejiga, casa-Sims, trabajo simulado.

## Ropa

Campos en ficha (estilo visual, `rasgos_fisicos`, etiquetas, looks posibles, preferencias estéticas). **Ni tienda ni selección de look en V0.** Retrato: `CONTRATO_VISUAL_PERSONAJES.md`. Ver `ROPA_Y_LOOKS.md`.

## Plantilla

`data/personajes/_plantilla.personaje.json` (canónica, anidada) y `docs/CONTRATO_PERSONAJE.md` (entrega A+B; B se deriva).

## Auditoría y estados

Nadie es final sin `CHECKLIST_AUDITORIA_PERSONAJES.md`.

- Ficha: `borrador` → `revision` → `aprobado`
- Pool: `borrador` → `auditoria_global` → `aprobado`
- Neni aprueba tono de 2–3 `piloto: true`. El resto no hace falta que lo lea uno a uno.

Registro (una línea por persona, letras I P V R N J Vis): `REGISTRO_AUDITORIA_PERSONAJES.md`.
