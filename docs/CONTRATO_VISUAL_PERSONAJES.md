# Contrato visual de personajes

Cursor genera las **cabezas maestras**. ChatGPT **no** entrega imágenes sueltas. Neni **no** da por buena ninguna imagen hasta decir APROBADO.

Ancla de estilo (a elegir aún entre pruebas): las **cabezas pequeñas** de `docs/referencias_visuales/REFERENCIA_VISUAL_01_AQUI_HAY_TEMA.png` (mapa + tarjetas PAREJAS). No retrato semirrealista / «guapo».

El bloque de personaje del prompt sale **solo** de la ficha B (físico). Cero secretos de A.

---

## Identidad visual (requisito futuro)

```text
IDENTIDAD VISUAL = CABEZA MAESTRA + LOOK/CUERPO
```

La **cabeza maestra** es el archivo canónico de quién es esa persona: cara, pelo, gafas, edad, rasgos. Se genera de frente y se reutiliza.

El **look/cuerpo** (ropa cotidiana, de cita, elegante, fiesta, cómoda, de evento) se estudia **después**. No se programa ni se genera ahora.

Hay que resolver más adelante cómo pegar cabeza + cuerpo sin que parezcan otro personaje (misma paleta, mismo trazo, mismo grado de caricatura). Hasta entonces: solo cabezas.

---

## Qué es (y qué no)

Mismo juego, mismo trazo. Cartoon 2D de tablero / sitcom, sencillo, simpático, ligeramente naïf, imperfecto.

No es realista, semirrealista, anime, Pixar/Disney, avatar corporativo, Instagram ni belleza idealizada.

Guapa no es el default **ni** feo a propósito. El catálogo debe tener **variedad real de atractivo**: personas muy atractivas, normales, peculiares, mayores, jóvenes adultas, distintos cuerpos y estructuras faciales — siempre caricatura editorial, nunca realismo ni personas reales.

Si la ficha pide nariz ancha, papada, canas, gafas gordas: **se dibuja**. Si pide belleza llamativa dentro del estilo cutre: **también**.

---

## Diferenciación estructural (cerrado — estilo 06b)

**Canon desde 2026-08-18.** Obligatoria para todos los habitantes con el lenguaje visual **06b**.

```text
Cada personaje debe poder distinguirse razonablemente de los demás
incluso ignorando pelo, barba, gafas, color de piel y accesorios.
```

Los accesorios **complementan** la identidad; no la crean. 2–4 rasgos visuales **dominantes** por persona, no todos los parámetros al extremo.

El alfabeto facial tiene que poder variar, de verdad:

frente · cráneo/cara (ancho-alto) · pómulos · mejillas · mandíbula · mentón · tamaño/forma/separación/altura de ojos · párpados · cejas · tamaño/ancho/largo/punta de nariz · boca · labios · orejas · asimetrías suaves · marcas de edad cuando toquen

### Auditoría de conjunto (no solo ficha a ficha)

FAIL de roster si varias cabezas son «la misma cara + distinto pelo/barba/gafas».

Prueba mental (y en revisión): tapar pelo, barba, gafas y unificar mentalmente el tono de piel — ¿siguen siendo personas distintas?

Referencia de estilo: `assets/personajes/_revision/_estilo_06/` (**06b** canónico). No crear estilos 07, 08…

**06b = lenguaje gráfico, no caras de maniquí.** El trazo, paleta y encuadre vienen de las pruebas; **cada personaje de producción nace de su ficha**, no de A/B/C.

---

## Maniquíes de prueba — EXCLUIDOS del pool (cerrado 2026-08-18)

Los personajes **A**, **B** y **C** son **maniquíes de prueba**. **EXCLUIDOS del pool.**

```text
REFERENCIA DE ESTILO: SÍ — lámina conjunta COMPARATIVA_MAESTRA_06b_ABC.png
REFERENCIA DE IDENTIDAD: NO — nunca A, B ni C individualmente
```

| Prohibido | Permitido |
| --- | --- |
| Reciclar cara de A, B o C como habitante | Usar **lámina ABC** para trazo, color, acabado |
| `reference_image` de A/B/C sueltos | Identidad **solo** desde ficha JSON |
| Usar personaje N−1 como única referencia | Todos beben de la **misma** lámina maestra |

Ruta maestra: `assets/referencias_visuales/estilo_06b/COMPARATIVA_MAESTRA_06b_ABC.png`  
Pipeline: `docs/PIPELINE_RETRATO_06b.md` · Nombres humanos: `assets/personajes/revision/`

Archivos históricos maniquí: `assets/personajes/_revision/_estilo_06/`.

---

## Variedad de atractivo (cerrado como principio de catálogo)

«Personas normales» **no** significa hacer a todos poco atractivos.

En un catálogo de 80–100+ fichas debe haber:

- personas **muy atractivas** (dentro del estilo cutre, no modelo de pasarela realista)
- personas **normales**
- personas **peculiares** o poco convencionales en belleza
- **mayores**, **jóvenes adultas**, distintos cuerpos y mandíbulas/pómulos

Siempre: contrato visual, diferenciación estructural, **cero** personas reales como referencia de personaje.

Auditoría de tanda: comprobar que no todo el roster cae en el mismo bucket de atractivo.

---

## Encuadre — cabeza maestra (cerrado)

Retrato maestro = **persona totalmente de frente**.

- Cabeza centrada, mirada frontal (no 3/4).
- Cabeza **completa** (pelo y barbilla caben; no recortar coronilla).
- Cuello visible.
- Como máximo un **nacimiento mínimo** de hombros.
- **Prácticamente sin ropa visible.** No busto. No camiseta ocupando el cuadro.
- Fondo plano blanco.
- Un personaje. Sin manos, sin pose, sin escenario.

Tiene que funcionar como ficha de cabeza reutilizable y, reducida, como chincheta de mapa.

**Prohibido:** medio cuerpo, cuerpo entero, 3/4 de moda, hombros vestidos a lo retrato de LinkedIn, café/parque, pareja, texto.

El primer borrador de Rocío (jersey verde, 3/4) **no** es el encuadre vigente. No está aprobado. Sirve de contraste, no de canon.

---

## Fondo, luz, detalle

| Pieza | Regla |
| --- | --- |
| Fondo | Blanco plano. |
| Iluminación | Plana de dibujo. Cero beauty light. |
| Detalle | Bajo–medio. Legible a 32 px. |
| Línea | Mismo alfabeto en todo el pueblo (el grosor concreto lo fija la prueba de estilo ganadora). |
| Color | Planos. Poca sombra. |
| Proporciones | Cabeza de caricatura, no fashion. |

Hasta elegir estilo: ~~ver carpeta de pruebas~~ **Estilo 06b cerrado (2026-08-18).** Referencia: `assets/personajes/_revision/_estilo_06/`. Las pruebas 01–05 en `per_i03/pruebas_estilo/` son históricas.

---

## Cara (el mismo alfabeto para los 16)

| Parte | Hacer | No hacer |
| --- | --- | --- |
| Cráneo / cara | Proporción propia (larga, ancha, redonda…). | Misma óvalo para todos. |
| Mandíbula / mentón | Distintos. | Mentón de plantilla. |
| Ojos | Puntos, óvalos o arcos; tamaño y separación distintos. | Anime, shine, pestañas de máscara, el mismo círculo en 16 caras. |
| Cejas | Un trazo; altura/grosor propios. | Microblading. |
| Nariz | Según ficha; personalidad. | Nariz Instagram en todo el roster. |
| Boca | Línea / comisura; dientes solo si la ficha los pide. | Gloss, sonrisa stock. |
| Pelo | Silueta clara. | Que el pelo haga el 90 % de la identidad. |
| Piel | Tono e imperfectos de la ficha. | Porcelana universal. |
| Edad | Se lee en miniatura (estructura + alguna marca). | «Cara de 25» con canas de atrezzo. |
| Accesorios de cabeza | Gafas, pendientes chicos: grandes y simples. | Identidad = solo gafas. |
| Ropa en cabeza maestra | **Casi ninguna.** | Jersey/camiseta protagonizando el plano. |

### Expresión

Una, frontal, de pueblo. No selfie. Sale de `rasgos_fisicos` **y** del tono público (rasgos, frase semilla): un fiestero/bromista no lleva expresión apagada o preocupada por defecto; tampoco hace falta sonrisa exagerada.

---

## Diversidad

Caras distintas, edades adultas, gafas, entradas, arrugas, pecas, narices, papadas, canas. No homogeneizar. No embellecer.

---

## Brief (sin spoilers)

Prompt de cabeza maestra, solo:

- `edad`, `genero`
- `estilo_visual` (sin describir ropa de busto)
- `rasgos_fisicos`

**Nunca:** `atraido_por`, crush, dealbreakers, función de llegada, secretos, `voz` de motor, recíprocos, look de cita.

Archivo por `id` (`per_i03`), no por chiste.

---

## Prompt maestro (bloque fijo)

```text
Board-game / sitcom 2D cartoon MASTER HEAD for an indie game. Same universe as tiny map-head icons: simple, slightly naive, friendly, imperfect. NOT pretty-by-default.

FRAMING (mandatory): fully front-facing. Head centered. Eyes looking at camera. Entire head visible including crown of hair and chin. Neck visible. At most the tiniest hint of shoulder tops. NO clothing, NO shirt, NO bust portrait, NO 3/4 turn. One character. White flat background. Square crop. Head fills most of the frame.

FACE: simple cartoon features readable at map-icon size. Do NOT beautify, rejuvenate, or give a model face. Specific written traits MUST show. Identity must also come from skull/jaw/chin/eyes/nose geometry, not mainly hair, beard, glasses, or accessories.

NOT: photoreal, semireal, anime, Pixar, Disney 3D, corporate avatar, Instagram beauty, watercolor painting, full body, half body, scenery, text.
```

Negativos fijos:

```text
photorealistic, hyperrealistic, semirealistic, Instagram face, beauty filter, airbrushed skin, model, perfect symmetry, long eyelashes, cinematic lighting, rim light, 3D render, Unreal, anime, manga, Pixar, Disney, corporate avatar, watercolor, oil painting, full body, half body, 3/4 view, turned head, clothing, shirt, sweater, bust portrait, shoulders filling the frame, sitting, walking, background scene, couple, text, watermark
```

Frase de personaje (físico, sin ropa):

```text
Master head, same person: [edad] year old [genero], [rasgos_fisicos as comma list]. Front face, neck, no clothes.
```

Referencia: PNG 01, **cabecitas** del mapa/tarjetas. Salida **1:1**.

---

## Estados de la imagen

| Estado | Dónde | Quién |
| --- | --- | --- |
| pruebas de estilo | `assets/personajes/_revision/<id>/pruebas_estilo/` | comparación; **ninguna aprobada** |
| `borrador` | `assets/personajes/_revision/<id>/borrador.png` | generación habitual de un piloto |
| `aprobado` | `assets/personajes/<id>/retrato.png` | **solo Neni** |

Las pruebas de estilo de Rocío **no** sustituyen al borrador hasta que Neni elija dirección y luego una cabeza.

Máximo un `borrador.png` de trabajo por id (se pisa). Las cinco pruebas de dirección son una excepción puntual, no un historial infinito.

---

## Archivos

```text
assets/personajes/<id>/retrato.png
assets/personajes/<id>/retrato.meta.json
assets/personajes/_revision/<id>/borrador.png
assets/personajes/_revision/<id>/pruebas_estilo/   ← comparación de trazo
```

Meta: `data/personajes/_plantilla.retrato.json`. Sin ficha A.

---

## Flujo (estilo 06b cerrado)

1. ChatGPT entrega A+B.
2. Cursor valida A.
3. Genera **cabeza maestra** borrador en **06b** (frente, sin ropa) desde **ficha** + **`reference_image`: lámina ABC** (`assets/referencias_visuales/estilo_06b/COMPARATIVA_MAESTRA_06b_ABC.png`). **No** A/B/C sueltos. **No** personaje N−1 como única referencia.
4. **Gate anti-reciclaje + gate estilo** (PNG real): identidad ficha + misma familia gráfica ABC + no clon maniquí.
5. **Comparativa ABC + personaje** para revisión conjunta.
6. APROBADO / REGENERAR / cambios.
7. Solo APROBADO → `retrato.png`.

El borrador antiguo de Rocío **no** está aprobado; regenerar en 06b cuando toque.

---

## Auditoría visual rápida

FAIL estructural: ropa de busto, 3/4, escenario, foto, dos personas, texto.

FAIL de roster (cuando la regla candidata de diferenciación estructural esté en vigor): varias cabezas = misma cara + distinto pelo/barba/gafas.

**FAIL anti-reciclaje:** borrador = maniquí A/B/C de `_estilo_06/` (misma estructura de cráneo/mandíbula/ojos/nariz aunque cambie pelo) **o** clon de otro habitante del pool.

**FAIL contenido ajeno (incidente I02 v2):** ficha de identificación, UI, texto, armas, temática militar/espionaje, fotorealismo — aunque el prompt fuera correcto.

Pipeline completo: `docs/PIPELINE_RETRATO_06b.md`.

### Gate posterior (sobre PNG real — obligatorio)

Antes de entregar `borrador.png` a Neni, Cursor **abre el archivo generado** y comprueba:

| # | Check |
| --- | --- |
| 1 | Género/presentación = ficha |
| 2 | Edad aparente coherente |
| 3 | Caricatura 06b, no foto ni UI |
| 4 | Encuadre frente, cuello, sin ropa |
| 5 | Sin texto, armas, ficha ID, stats, lemas |
| 6 | ≥3 rasgos de `visual.rasgos_fisicos` reconocibles |
| 7 | No maniquí A/B/C |
| 8 | No clon de otro `per_*` |
| 9 | Expresión coherente con `rasgos_publicos` |

FAIL → archivar en `evidencia/`; **no** rellenar meta `gate: pass` sin mirar el PNG.

**Prohibido en generador:** slot `I02` en filename/description (dispara alucinaciones «agente»); `reference_image` de maniquíes; prompts no construidos desde JSON.

Constructor: `data/personajes/_build_retrato_prompt.mjs`.

Checklist gate (Cursor, antes de entregar borrador):

1. ¿La cara nace de `rasgos_fisicos` de la ficha?
2. ¿Se diferencia de A, B y C si tapas pelo/accesorios?
3. ¿Se diferencia de otros `per_*` ya generados?
4. ¿Expresión coherente con rasgos públicos (sin exagerar)?
5. ¿Encuadre 06b (frente, cuello, sin ropa)?

Si está «demasiado guapa» respecto a la ficha: enseñar y proponer REGENERAR. No callar.
