# Fase de diseño: pool de 16 personajes

> **Estado esqueleto §7:** **APROBADO** (Fase B). Rocío en **I08** (`per_i03`). Detalle: `FASE_B_ESQUELETO_REVISADO.md`. Pilotos Fase C: `FASE_C_ENTREGA_CHATGPT.md`.

Este es el documento para **empezar a crear fichas** con Neni y ChatGPT. El esqueleto de 16 es la **primera tanda** de contenido y encaja con **Bloque A ≈ 16**. No es el pool eterno ni un edificio de 24. Capacidad ≠ pool: `PROPUESTA_VIDA_POOL_Y_MARCHAS.md`.

**Día 1 ≠ 10 iniciales:** el pool tiene 16 slots de catálogo; solo **~3** están activos al inicio (Rocío + B + C). Los slots I01–I10 son plantilla de tanda, no población día 1.

No hay nombres ni biografías aquí. No es código. La V0 jugable está aprobada en `DECISIONES_CERRADAS.md` y detallada en `PROPUESTA_JUGABLE_V0_CURSOR.md`.

**Cómo usar esto**

1. Aprobar o retocar la **sección 7** (reparto sin nombres).
2. Crear **2–3 pilotos** en `CONTRATO_PERSONAJE.md` (A+B, con `rasgos_fisicos`). Cursor valida y genera retrato **borrador**. Neni ve **B + retrato**.
3. Ninguna ficha es final sin `CHECKLIST_AUDITORIA_PERSONAJES.md`.
4. Tras las 16, nivel 2 de auditoría + `VALIDACION_GRAFO_ROMANTICO.md`.

Neni no tiene que revisar los 16 uno a uno. Cursor sí tiene que auditarlos todos igual.

**Formato de entrega:** markdown A+B (`CONTRATO_PERSONAJE.md`). Almacén: `_plantilla.personaje.json` (ya alineada). B se deriva; no se duplica.

---

## A. Plantilla final (qué es un personaje)

Un personaje es un **id estable** + ficha de catálogo + estado de partida.

La ficha de catálogo es lo que rellenáis ahora. El estado de partida (ánimo de hoy, citas, crush concreto) **no se inventa en la biografía**: nace al jugar, salvo semillas opcionales.

### Identidad

| Campo | Tipo | Notas |
| --- | --- | --- |
| `id` | `per_xx` | Nunca el nombre como clave. Al crear: `per_01` … `per_16` o el slot de la §7. |
| `nombre` | texto | Visible. |
| `edad` | entero | Adulto. Bandas recomendadas en §7. |
| `genero` | enum | `mujer` / `hombre` / `no_binarie`. Alimenta el grafo. |
| `pronombres` | lista de texto | p. ej. `["ella"]`, `["él"]`, `["elle"]`. |
| `atraido_por` | lista del mismo enum | Por **quién puede** sentir atracción. Varios = varias. Vacío = prohibido. |
| `etiqueta_orientacion_visible` | texto corto o `""` | Cosmética. **No manda.** Si miente un poco respecto a `atraido_por`, es descubrible. |
| `apertura_descubrimiento` | enum | `cerrada` / `permeable` / `abierta`. Ver §6. No es una ruleta de orientación. |

### Vida mínima (el pueblo se mueve)

| Campo | Tipo | Notas |
| --- | --- | --- |
| `ocupacion` | id | Catálogo de 8: `sanitario`, `oficina`, `camarero`, `comercio`, `docente`, `estudiante_adulto`, `autonomo`, `jubilado`. |
| `franja_disponibilidad` | id | `manana` / `tarde` / `noche` / `flexible`. |
| `hobby_principal` | id | Uno. Candidatos en §7. |
| `hobbies_secundarios` | lista | 0–2. Opcional. |
| `lugares_preferentes` | 1–2 ids | Pueden ser sitios 🔒 o fuera del mapa V0. Si está cerrado, va a abierto o a casa. |
| `estilo_social` | id | `casero` / `fiestero` / `tranquilo` / `intenso` / `espontaneo` / `planificador`. |
| `voz` | enum | `seca` / `dramatica` / `tranquila` / `borde` / `candida`. Motor de frases del diario. |
| `necesidad_contacto_base` | enum | `baja` / `media` / `alta`. Semilla de la variable de pareja. |

### Personalidad y romance (ficha)

| Campo | Tipo | Notas |
| --- | --- | --- |
| `rasgos_publicos` | 3 ids | Del set candidato (§7). Lo que se lee en **El pueblo**. |
| `rasgos_ocultos` | 0–2 ids | Mismo set u otro corto. Se descubren. |
| `gustos_declarados` | 1–3 textos cortos | Visibles. No ensayos. |
| `preferencias_romanticas` | lista corta | Ritmo, estilo de cita, «le va lo intenso»… Parte visible, parte oculta (marcar). |
| `preferencias_esteticas` | 1–3 etiquetas look | Lo que le atrae en *otra* gente. Oculto de entrada. Catálogo: elegante, comodo, atrevido, alternativo, formal, deportivo, raro, vintage. |
| `dealbreakers` | 0–2 | `{texto, severidad: leve\|fuerte\|absoluto}`. Absoluto = capa 1. |

### Lazos iniciales (ids, no novela)

| Campo | Tipo | Notas |
| --- | --- | --- |
| `lazos[]` | `{persona, tipo, publico}` | Familia, amigos, ex, roce. `persona` = id o slot. Recíproco en las dos fichas cuando existan. |

### Presencia en la V0

| Campo | Tipo | Notas |
| --- | --- | --- |
| `empieza_en_pueblo` | bool | Los 10 iniciales: `true`. Los 6: `false`. |
| `orden_llegada` | 1–6 o null | Solo llegadas. El 1 llega antes que el 6. |
| `funcion_llegada` | enum o `""` | `neutra` / `amistad` / `interes_soltero` / `quimica_emparejado` / `triangulo_potencial` / `vida_del_pueblo`. Qué **puede** romper, no un guion obligatorio. |

### Apariencia (modelo sí, tienda no)

Obligatorio en ficha. **Inerte en la V0 jugable** (un retrato / placeholder).

| Campo | Tipo | Notas |
| --- | --- | --- |
| `estilo_visual` | 1 línea | Cómo se lee el personaje. |
| `rasgos_fisicos` | 3–8 rasgos de cara/cuello | Para el retrato. Públicos. No plot. |
| `etiquetas_look_base` | 1–3 | Look con el que «existe» en V0. |
| `looks_posibles` | 2–4 etiquetas o combos | Para no rehacer el modelo cuando haya tienda. |

### Semilla narrativa (poca)

| Campo | Tipo | Notas |
| --- | --- | --- |
| `frase_semilla` | 1 frase | Cómo habla / qué le pasa. No biografía. |
| `tono_coletilla` | opcional | Si este cuerpo suelta coletillas propias; si no, solo el pueblo. |
| `crush_inicial` | id o null | Semilla oculta. Unilateral bienvenida. No emparejar de salida. |
| `estado_ficha` | enum | `borrador` / `revision` / `aprobado`. Nunca `aprobado` sin auditoría nivel 1. |
| `piloto` | bool | `true` solo en los 2–3 que ve Neni. |

---

## B. Campos obligatorios

Sin esto la ficha no entra al pool:

- `id`, `nombre`, `edad`, `genero`, `pronombres`
- `atraido_por` (mínimo un valor)
- `apertura_descubrimiento`
- `ocupacion`, `franja_disponibilidad`, `hobby_principal`
- `lugares_preferentes` (mínimo uno)
- `estilo_social`, `voz`
- `rasgos_publicos` (exactamente 3)
- `necesidad_contacto_base`
- `empieza_en_pueblo`
- si no empieza: `orden_llegada` + `funcion_llegada`
- `estilo_visual`, `rasgos_fisicos` (3–8), `etiquetas_look_base` (mínimo una)
- `frase_semilla`
- `estado_ficha` (arranca en `borrador`)
- `piloto` (`true` / `false`)

---

## C. Campos opcionales

Se pueden dejar vacíos y completar después:

- `etiqueta_orientacion_visible`
- `hobbies_secundarios`
- `rasgos_ocultos`
- `gustos_declarados`
- `preferencias_romanticas`
- `preferencias_esteticas` (recomendable, no bloquea escribir el nombre)
- `dealbreakers`
- `lazos`
- `looks_posibles` (recomendable rellenar al hacer el visual)
- `tono_coletilla`
- `crush_inicial`

No hace falta texto de infancia, playlist ni arco de tres actos.

---

## D. Ocultos para el jugador (y cómo salen)

El jugador **nunca** ve números internos de pareja.

| Dato | ¿En ficha? | Cómo se descubre |
| --- | --- | --- |
| `atraido_por` fino si no coincide con la etiqueta | sí | Citas, 👀, no un menú el día 1 |
| `apertura_descubrimiento` | sí | No se nombra. Se infiere si ocurre |
| `dealbreakers` | sí | Al chocar o con vínculo |
| `rasgos_ocultos` | sí | Diario, cita, convivencia futura |
| `preferencias_esteticas` / ritmo oculto | sí | Citas, cotilleo |
| `necesidad_contacto_base` | sí | Color de pareja + ⚠️, no un icono «alta» |
| `crush_inicial` | opcional | 👀 u informe, no radar |
| Atracción/vínculo/conflicto/contacto **numéricos** | no (runtime) | Color + frase |
| Recuerdos, descubrimientos del jugador | no (runtime) | Diario |

Visible de entrada en **El pueblo**: retrato, nombre, edad, estado sentimental, disponibilidad, si es nuevo, si hay ⚠️, si tiene cita, rasgos públicos, ocupación a groso modo, hobby.

La etiqueta de orientación visible es opcional. Si no está, el pueblo no etiqueta a nadie el día 1.

---

## E. Qué evoluciona en partida (no rellenar ahora)

| Dato | Notas V0 |
| --- | --- |
| Ánimo | Enum corto. Semilla `neutro` basta. |
| Flag `nuevo` | Las llegadas; se apaga a los 3–4 días. |
| Dónde está (franja) | Rutina automática. |
| Cita programada | La pone el jugador. |
| Relaciones románticas (las 4 variables) | Nacen en juego. |
| Atracciones hacia personas concretas | Pueden nacer sin pareja. |
| Amistades y roces nuevos | Además de las semillas. |
| Recuerdos (ids de evento) | Diario. |
| Descubrimientos del jugador sobre la ficha | «Ya sabemos que no aguanta la impuntualidad». |
| `looks` desbloqueados | Campo listo; **sin tienda en V0**. |
| Estado de residencia | En V0 solo `residente` (en pueblo o aún en pool). No hay `ausente`. |
| Descubrimiento de atracción hacia alguien concreto | Posible si `apertura` no es `cerrada`; **evento raro**, no V0 prioritaria. |

Fama del NPC: no. La fama es del casamentero.

---

## F. Validación del grafo romántico (obligatoria)

### Qué significa «recíproco potencial»

Para cada personaje A del pool de **16** debe existir **al menos un** B distinto tal que:

1. A y B no tienen parentesco que vete.
2. No hay dealbreaker **absoluto** de A hacia B ni de B hacia A que anule el romance para siempre (si aún no hay dealbreakers, se ignora este punto).
3. El `genero` de A está en `atraido_por` de B.
4. El `genero` de B está en `atraido_por` de A.

Eso es **posibilidad**. No química alta, no pareja inicial, no final único.

Sí puede haber, además, crushes unilaterales, gente difícil y relaciones que solo suben tras evolución.

### Lo que **no** cuenta para aprobar el roster

- `apertura_descubrimiento`. Si alguien solo se «salva» porque es `abierta`, el grafo miente. La apertura es narrativa extra, no parche.
- «En el pueblo grande del juego final habrá más gente». El pool es estos 16.
- Una llegada futura **fuera** de los 16.

### Los 10 del día 1 también tienen que valer — OBSOLETO (2026-08-18)

~~Cada uno de los **10 iniciales** debe tener ≥1 recíproco potencial **dentro de esos 10**.~~

**Sustituido por:** grafo en pool 16; trío día 1 validado aparte; 2 personajes `interes_romantico: ninguno`; inicio por configs prevalidadas.

El párrafo siguiente sobre llegadas sigue vigente con matices:

### Tras cada llegada (cuando existan las fichas)

Al simular el pueblo con 10, 11, 12… nadie de los que ya viven allí debe quedar en 0 recíprocos **por un veto nuevo** (p. ej. si la llegada fuera familia y anulara el único cruce legal: no diseñar eso).

La llegada puede crear unilaterales nuevos. No puede dejar a alguien sin ningún recíproco legal en el pueblo presente.

### Cómo comprobarlo (checklist, no código)

Cuando estén las 16 fichas:

1. Tabla: id, género, `atraido_por`, familia vetada.
2. Para cada A, listar Bs que cumplen los 4 puntos.
3. Si algún A tiene lista vacía en el pool 16 → reescribir ficha o lazo familiar.
4. Repetir **solo con los 10** `empieza_en_pueblo: true`.
5. Por cada llegada en orden 1…6, repetir con «los que ya están + esta llegada».
6. No optimizar hacia un matching perfecto de 8 parejas.

Detalle en `VALIDACION_GRAFO_ROMANTICO.md`.

### Género y `atraido_por` (taxonomía V0)

Cerrada para poder escribir fichas:

- `genero`: `mujer` | `hombre` | `no_binarie`
- `atraido_por`: una o más de esas tres claves

«Le gustan varios géneros» = lista de dos o tres, no un cuarto enum mágico.

Si hay personajes `no_binarie`, **alguien** del pool (no solo ellxs) debe incluir `no_binarie` en `atraido_por`. Si no, ellxs no tienen recíproco.

---

## G. Propuesta de distribución (sin nombres) — PENDIENTE DE APROBACIÓN

> **No aprobado.** Falta: 2× `interes_romantico: ninguno`, alinear slot I03 con Rocío, amistad con valor propio, trío día 1, agenda por plantillas. Ver revisión en `ESTADO_PROYECTO.md`.

### Edades (recomendadas)

Rango V0: **22–72**. Nadie menor de 22.

| Banda | Cupo ~ | Para qué |
| --- | --- | --- |
| 22–29 | 4 | Energía, estudiante, turnos de noche |
| 30–44 | 6 | Centro de gravedad del pueblo |
| 45–59 | 4 | Oficio asentado, otro ritmo de pareja |
| 60–72 | 2 | Jubilación, otro tipo de cita |

Evitar un único salto enorme como *única* opción recíproca de alguien joven (legal sí, aburrido y raro como único cruce).

### Géneros

**7 mujeres, 7 hombres, 2 no_binarie.**

No 8/8 rígido. Dos `no_binarie` para que puedan ser recíproco entre sí *y* no dependan de un único aliado.

### Patrones de `atraido_por` (cupos)

Pensados para que el grafo cierre sin ser un sudoku de una sola pareja gay.

| Patrón (lista) | Cupo | Notas |
| --- | --- | --- |
| solo `mujeres` | 5 | Mezcla de mujeres y hombres (lésbico estable + hetero) |
| solo `hombres` | 5 | Mezcla de hombres y mujeres (gay estable + hetero) |
| `mujeres` + `hombres` | 3 | Sin NB de entrada |
| incluye `no_binarie` (y al menos otro género, o los tres) | 3 | Los 2 NB + **como mínimo 1** persona más. Mejor 3–4 en total |

Los 2 `no_binarie` deben poder corresponderse entre sí **o** con gente que les incluye.

`apertura`: mayoría `cerrada` (orientación estable). Unas **4 `permeable`** y **1–2 `abierta`**. El descubrimiento no es el motor de la V0.

### Horarios

| `franja_disponibilidad` | Cupo ~ |
| --- | --- |
| `manana` | 4 |
| `tarde` | 4 |
| `noche` | 3 |
| `flexible` | 5 |

Que no coincidan todos los «citables» a la misma hora. Los dos camareros no tienen por qué ser los únicos nocturnos (un sanitario de turno también).

### Ocupaciones

Usar las **8** del catálogo. Repetir está bien (dos docentes, dos jubilados). Evitar 10 oficinistas.

Orden de magnitud: **2 por ocupación**, ±1.

### Tipos sociales

Del catálogo existente:

| `estilo_social` | Cupo ~ |
| --- | --- |
| `casero` | 3 |
| `fiestero` | 2 |
| `tranquilo` | 3 |
| `intenso` | 3 |
| `espontaneo` | 2 |
| `planificador` | 3 |

Pocos fiesteros: la discoteca no está en el mapa V0.

### Hobbies (candidatos para esta fase)

Lista corta para poder rellenar `hobby_principal`. No es el catálogo eterno del juego.

| id | Dónde pisa sobre todo | En V0 |
| --- | --- | --- |
| `leer` | biblioteca | abierto |
| `escribir` | biblioteca | abierto |
| `pasear` | parque | abierto |
| `correr` | parque | abierto |
| `cafe_social` | cafetería | abierto |
| `manualidades` | biblioteca / casa | abierto |
| `cocina` | casa (off-mapa) | off-mapa |
| `musica` | casa / más adelante bar | off-mapa |
| `cine` | cine | 🔒 visible |
| `videojuegos` | arcade | 🔒 visible |
| `copas` | bar | 🔒 visible |
| `baile` | discoteca | fuera del mapa V0 |

**Cupo:** cada uno de los 16 un principal distinto no hace falta; sí hace falta:

- ≥2 hobbies que usen cafetería
- ≥2 parque
- ≥2 biblioteca
- ≥3 cuyo hobby «de verdad» sea un sitio 🔒 o fuera (deseo de desbloquear, diario ocasional)

### Vínculos familiares y sociales iniciales

Poco. El veto familiar es un hacha.

| Tipo | Cantidad recomendada en los 16 |
| --- | --- |
| Núcleo familiar con veto | **1** (p. ej. madre/padre + hija/o, ambos en los 10 **o** uno inicial y uno llegada) |
| Hermanos con veto | **0 o 1** par (si es llegada, no rompe el grafo del día 1) |
| Primos hermanos | **0** en V0 salvo que el grafo sobrado lo aguante |
| Abuelos/nietos, tíos | **0** en V0 (edades + vetos comen opciones) |
| Amistades iniciales | **6–10** lazos (no todos amigos de todos) |
| Roce (`lazos` tipo `roce`) | **1–2** pares |
| Ex | **0 o 1** en todo el pool |

Las llegadas: **al menos 3 de 6** traen un lazo débil (amigo, compañero, familia lejana no vetada, «conocido de»). Que no parezcan generados por la niebla.

### Los 10 vs los 6 vs el trío día 1

**Día 1 (~3):** Rocío confirmada + B + C por decidir tras revisar esqueleto. Nadie empieza en pareja oficial. Un crush unilateral oculto sí.

**Pool I01–I10:** plantilla de catálogo para la tanda; **no** implica que los 10 estén vivos el día 1.

**OBSOLETO para diseño actual:** la regla «cada uno de los 10 iniciales debe tener recíproco **dentro de los 10**» (§F abajo). Sustituida por: recíproco en pool 16 para quien tenga romance; trío tutorial validado por **configs prevalidadas + seed**; 2× `interes_romantico: ninguno` en la tanda.

**Llegadas (funciones, no fichas):**

| Orden | Función | Qué debe añadir |
| --- | --- | --- |
| 1 | `vida_del_pueblo` o `amistad` | Casi no toca parejas. El pueblo crece. |
| 2 | `interes_soltero` | Química clara con alguien libre |
| 3 | `quimica_emparejado` | 👀 hacia alguien que **ya** podría estar con otra persona; no ruptura el día de la maleta |
| 4 | `amistad` o `neutra` | Amplía horarios/sitios, poco romance |
| 5 | `triangulo_potencial` | Solo tiene diente si ya hay pareja amarilla o un crush |
| 6 | `interes_soltero` o `vida_del_pueblo` | Cierra huecos de edad/grafo, no un jefe final |

Nunca dos llegadas el mismo día. Nunca llegada = divorcio el mismo día.

### Slots (esqueleto para asignar al crear)

No son personajes. Son huecos. Podéis permutar ±1 año y nombres cuando toque.

**Iniciales (10)** — grafo pensado para cerrar entre ellos:

| Slot | Banda edad | Género | `atraido_por` | Franja | Ocupación | Estilo | Hobby tipo | Nota de grafo |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `I01` | 22–29 | mujer | hombres | flexible | estudiante_adulto | espontaneo | café / abierto | Hetero joven; recíproco con I02, I06 |
| `I02` | 22–29 | hombre | mujeres | noche | camarero | fiestero | copas (🔒) | Hetero joven |
| `I03` | 30–44 | mujer | mujeres | manana | docente | intenso | leer | Lésbico estable; recíproco con I04 | **⚠️ Rocío real:** hetero, 47, oficina, bingo — **requiere re-slot o revisión esqueleto** |
| `I04` | 30–44 | mujer | mujeres, hombres, no_binarie | flexible | autonomo | planificador | escribir | Puente; también recíproco con I09 |
| `I05` | 30–44 | hombre | hombres | noche | sanitario | casero | videojuegos (🔒) | Gay estable; recíproco con I06 |
| `I06` | 30–44 | hombre | hombres, mujeres | manana | oficina | tranquilo | cine (🔒) | Puente gay/hetero |
| `I07` | 45–59 | mujer | hombres | tarde | comercio | casero | pasear | Hetero; posible núcleo familiar con I01 |
| `I08` | 45–59 | hombre | mujeres | tarde | sanitario | tranquilo | correr | Hetero; recíproco con I07 / I10 |
| `I09` | 30–44 | no_binarie | mujeres, hombres, no_binarie | flexible | autonomo | intenso | musica | Recíproco con I04; A05 refuerza |
| `I10` | 60–72 | mujer | hombres | manana | jubilado | casero | cocina | Recíproco con I08 (edad cercana-ish) |

Familia sugerida (no obligatoria): **I07 progenitorx de I01**. Compruéese que I01 sigue teniendo recíprocos (I02, I06).

**Llegadas (6)**

| Slot | Banda | Género | `atraido_por` | Franja | Ocupación | Función | Qué mueve |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `L01` | 22–29 | hombre | hombres | noche | camarero | amistad / triángulo gay | Amigo o hermano de I05; tensión con I05–I06 |
| `L02` | 22–29 | mujer | mujeres | flexible | estudiante_adulto | interes_soltero / triángulo | Química con I03 o I04 |
| `L03` | 30–44 | hombre | mujeres | manana | oficina | quimica_emparejado | Compañero de I06; 👀 hacia alguien ya ligado |
| `L04` | 45–59 | mujer | hombres, mujeres | tarde | docente | vida_del_pueblo | Poco drama; otra franja de citas |
| `L05` | 30–44 | no_binarie | mujeres, no_binarie | tarde | comercio | interes_soltero | Recíproco fuerte con I09 |
| `L06` | 60–72 | hombre | mujeres | flexible | jubilado | vida_del_pueblo | Opción de I10; vida del pueblo |

Patrones resultantes ~ 5 solo mujeres / 5 solo hombres / resto varios, con NB cubierto.

### Contradicción 10+6 vs máximo 14

Está cerrada la estructura 10 + 6 y un máximo *aproximado* de 14. Sin marchas que liberen hueco solas, 10+6 = 16 = **A lleno**.

**Recomendación:** el 14 es alarma de test («se siente lleno»), no un cajón para 2 fichas. Se diseñan y validan **16**. En partida, las 6 llegadas pueden entrar en A. Si el jugador no deja ir a nadie, **B** es la válvula (más adelante; no ahora). No se hincha A a 24.

---

## Rasgos candidatos (para los 3 públicos)

Aprobar o sustituir en bloque. 10 ids, 3 por ficha, repetibles.

`directo`, `timido`, `ironico`, `leal`, `cabezota`, `empatico`, `vanidoso`, `ansioso`, `bromista`, `reservado`

No duplicar aquí `voz` ni `estilo_social`.

---

## 8. Hoja para copiar (una por personaje)

```text
id:
slot (I01–I10 / L01–L06):
nombre:
edad:
genero:          mujer | hombre | no_binarie
pronombres:
atraido_por:     [mujer/hombre/no_binarie, ...]
etiqueta_visible (opcional):
apertura:        cerrada | permeable | abierta

ocupacion:
franja:          manana | tarde | noche | flexible
hobby_principal:
lugares_preferentes:
estilo_social:
voz:             seca | dramatica | tranquila | borde | candida
rasgos_publicos: (3)
necesidad_contacto: baja | media | alta

empieza_en_pueblo: si | no
orden_llegada:     (si no)
funcion_llegada:   (si no)

estilo_visual:
rasgos_fisicos:    (3–8, cara/cuello; no plot)
etiquetas_look_base:
frase_semilla:
piloto:            si | no
estado_ficha:      borrador

— opcional —
lazos:
dealbreakers:
rasgos_ocultos:
preferencias_esteticas:
preferencias_romanticas:
crush:
looks_posibles:
```

Orden de creación: **2–3 pilotos** (I03, I05, I10 sugeridos) → tono de Neni → resto de los 10 → validar grafo de 10 → L01…L06 → auditoría nivel 2. Neni no revisa los 16.

Ninguna ficha `aprobado` sin `CHECKLIST_AUDITORIA_PERSONAJES.md`. Resultado: una línea en `REGISTRO_AUDITORIA_PERSONAJES.md`.

---

## Crítica (tercer diseñador)

- No empecéis por la anécdota más graciosa. Los pilotos I03 e I05 cubren los dos ejes de mismo género; I10 evita que el tono se apruebe solo con gente joven. El par I03–I04 / I05–I06 se cierra al escribir el resto.
- I07 padre/madre de I01 es oro de diario y peligro de grafo: I01 no puede tener a I07 como «opción». Dejad a I01 con dos destinos hetero claros.
- L03 (`quimica_emparejado`) no debe ser irresistible el día que llega. Si no, cada maleta es un capítulo de culebrón y habéis roto la regla de «no toda llegada es crisis».
- No rellenéis dealbreakers absolutos tipo «nada de hombres» si `atraido_por` ya dice hombres: es el mismo veto dos veces.
- La etiqueta visible tipo «hetero» en alguien `permeable` es un spoiler invertido: o es honesta a día 1, o se omite.

Cuando el esqueleto §7 esté aprobado (aunque sea con un «sí, ±1»), cread los **pilotos**, no los 16. Entrega: `CONTRATO_PERSONAJE.md`. Auditoría: `CHECKLIST_AUDITORIA_PERSONAJES.md`.
