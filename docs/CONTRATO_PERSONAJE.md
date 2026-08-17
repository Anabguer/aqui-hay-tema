# Contrato de personaje

Contrato entre **ChatGPT** (crea) y **Cursor** (valida). No es código. No hay personajes aquí.

Cada entrega es **exactamente dos bloques**:

- **A. Ficha interna** — diseño, motor, auditoría. Puede tener secretos.
- **B. Ficha pública** — lo único que Neni (y el jugador al conocer a esa persona) ve al inicio.

Neni aprueba **pilotos solo con B**. Si un secreto aparece en B, la entrega es FAIL.

Plantilla JSON (canónica): `data/personajes/_plantilla.personaje.json`.  
Este contrato manda la entrega humana (A+B). El JSON ya representa A **sin pérdida**. B **no se duplica** en JSON: se deriva (§4 y §7).

Auditoría: `CHECKLIST_AUDITORIA_PERSONAJES.md`. Esqueleto de slots: `FASE_DISENO_POOL_16.md`.

---

## Cómo entregar

1. Un personaje = un bloque A + un bloque B, en este orden, con las cabeceras literales de §8.
2. No biografías. No escenas. No IA runtime.
3. En B está **prohibido** todo lo listado en §4 (ocultos).
4. En B **no se nombran** personajes que Neni aún no puede conocer (resto del pool no piloto).
5. En A, los lazos a gente no escrita aún van por **slot** (`I04`, `L01`…), no por nombre inventado.
6. `frase_semilla` es pública: no puede contener crush, dealbreaker, `atraido_por` ni función de llegada.
7. Cursor no corrige en silencio un cambio de identidad. Sí puede corregir formato (enum, campo vacío, longitud).
8. Tras validación **estructural** OK, Cursor genera el retrato (`CONTRATO_VISUAL_PERSONAJES.md`) y lo guarda como **borrador**. Neni ve B + retrato. La imagen no se aprueba sola.

`rasgos_fisicos` es obligatorio en A y B: lista corta de cara/cuello (nariz, pelo, gafas, edad que se lea, imperfectos). Sin eso Cursor no puede generar sin inventar una cara bonita.

---

## 1. Enums y catálogos (cerrados para V0)

| Campo | Valores permitidos |
| --- | --- |
| `slot` | `I01`…`I10` · `L01`…`L06` |
| `genero` | `mujer` · `hombre` · `no_binarie` |
| `atraido_por[]` | uno o más de: `mujer` · `hombre` · `no_binarie` |
| `apertura_descubrimiento` | `cerrada` · `permeable` · `abierta` |
| `ocupacion` | `sanitario` · `oficina` · `camarero` · `comercio` · `docente` · `estudiante_adulto` · `autonomo` · `jubilado` |
| `franja_disponibilidad` | `manana` · `tarde` · `noche` · `flexible` |
| `hobby_principal` / secundarios | `leer` · `escribir` · `pasear` · `correr` · `cafe_social` · `manualidades` · `cocina` · `musica` · `cine` · `videojuegos` · `copas` · `baile` |
| `lugares_preferentes[]` | ids `lug_*` del catálogo (máx. 2) |
| `estilo_social` | `casero` · `fiestero` · `tranquilo` · `intenso` · `espontaneo` · `planificador` |
| `voz` | `seca` · `dramatica` · `tranquila` · `borde` · `candida` |
| `rasgos_*` | `directo` · `timido` · `ironico` · `leal` · `cabezota` · `empatico` · `vanidoso` · `ansioso` · `bromista` · `reservado` |
| `necesidad_contacto_base` | `baja` · `media` · `alta` |
| `funcion_llegada` | `neutra` · `amistad` · `interes_soltero` · `quimica_emparejado` · `triangulo_potencial` · `vida_del_pueblo` |
| `interes_romantico` | `activo` · `pasivo` · `ninguno` — **candidato.** `ninguno` = no quiere pareja; no es fallo de diseño. Ver `VALIDACION_GRAFO_ROMANTICO.md`. |
| `susceptibilidad_enemigo_romance` | `nula` · `baja` · `media` · `alta` — **candidato.** ¿Puede enemistad→romance con evento causal? `PROPUESTA_AGENDA_DISPONIBILIDAD_RUTINAS.md`. |
| `etiquetas_look_*` | `elegante` · `comodo` · `atrevido` · `alternativo` · `formal` · `deportivo` · `raro` · `vintage` |
| `dealbreaker.severidad` | `leve` · `fuerte` · `absoluto` |
| `preferencia_romantica.tipo` | `ritmo` · `estilo_cita` · `intensidad` · `otro` (opcional; **sin peso** numérico en V0) |
| `preferencia_romantica.eje` / `dealbreaker.eje` | id de `ejes_preferencia` o vacío. Opcional. Motor de aprendizaje; no va en B |
| `plasticidad_preferencias` | `baja` · `media` · `alta`. Fichas nuevas. No va en B. `APRENDIZAJE_PREFERENCIAS.md` |
| `tipo` de lazo | `padre` `madre` `hijo` `hija` `hermano` `hermana` `medio_hermano` `medio_hermana` `abuelo` `abuela` `nieto` `nieta` `tio` `tia` `sobrino` `sobrina` `primo_hermano` `prima_hermana` `amigo` `amiga` `mejor_amigo` `mejor_amiga` `conocido` `conocida` `companero` `companera` `ex` `roce` |
| `reciprocos_potenciales.estado` | `pendiente_pool` · `resuelto` · `fallido` |
| `visual.retrato.estado` | `ninguno` · `borrador` · `aprobado` |
| `estado_ficha` | `borrador` · `revision` · `aprobado` |
| `estado_animo_inicial` | `neutro` (otros ánimos = runtime) |

`pronombres`: texto libre corto (`ella`, `él`, `elle`, `él/elle`…), no enum.

---

## 2. Límites de longitud y cardinalidad

| Campo | Límite |
| --- | --- |
| `id` | `per_` + 2–8 caracteres `[a-z0-9_]` |
| `nombre` | 2–40 caracteres |
| `edad` | entero **22–72** |
| `pronombres` | 1–3 ítems, cada uno ≤ 16 caracteres |
| `etiqueta_orientacion_visible` | `""` o ≤ 24 caracteres |
| `gustos_declarados[]` | 0–3 ítems, cada uno ≤ 40 caracteres |
| `preferencias_romanticas[].texto` | 0–3 ítems, cada uno ≤ 80 caracteres |
| `dealbreakers` | 0–2 |
| `rasgos_publicos` | **exactamente 3** ids |
| `rasgos_ocultos` | 0–2 ids |
| `hobbies_secundarios` | 0–2 ids |
| `lugares_preferentes` | 1–2 ids |
| `familia` / amigos / roce | 0–4 `lazos` en V0 (poco) |
| `etiquetas_look_base` | 1–3 |
| `looks_posibles` | 0–4 (recomendable 2–4) |
| `estilo_visual` | 1 línea, ≤ 120 caracteres |
| `frase_semilla` | 1 frase, ≤ **140** caracteres |
| `rasgos_fisicos[]` | **3–8** ítems, cada uno ≤ 40 caracteres. Solo anatomía/peinado/accesorio visible. |
| `preferencias_esteticas` | 0–3 etiquetas look |

Prohibido: párrafos, infancia, playlist, arco de tres actos.

---

## 3. Obligatorios vs opcionales (ficha interna)

### Obligatorios en A

`slot`, `id`, `nombre`, `edad`, `genero`, `pronombres`, `atraido_por` (≥1), `apertura_descubrimiento`, `ocupacion`, `franja_disponibilidad`, `hobby_principal`, `lugares_preferentes` (≥1), `estilo_social`, `voz`, `rasgos_publicos` (3), `necesidad_contacto_base`, `empieza_en_pueblo`, `estilo_visual`, `rasgos_fisicos` (3–8), `etiquetas_look_base` (≥1), `frase_semilla`, `estado_ficha` (arranca `borrador`), `piloto`.

Si `empieza_en_pueblo` es `false`: también `orden_llegada` (1–6, único en el pool) y `funcion_llegada`.

### Opcionales en A

`interes_romantico` (default `activo` o `pasivo`; `ninguno` = no quiere pareja — ver grafo).

`compromisos_recurrentes[]` (0–3): `{ id, dia_semana, hora_inicio, hora_fin, lugar?, etiqueta, descubrible }`. Solo en A; descubrimiento en runtime.

`susceptibilidad_enemigo_romance` (default `nula` o `baja`).

`etiqueta_orientacion_visible`, `hobbies_secundarios`, `rasgos_ocultos`, `gustos_declarados`, `preferencias_romanticas`, `preferencias_esteticas`, `dealbreakers`, `lazos`, `looks_posibles`, `tono_coletilla`, `estado_inicial.crush`.

`plasticidad_preferencias`: **obligatoria en fichas nuevas**. Las ya escritas se pueden dejar sin el campo (el simulador asume `media`). No reescribir identidad para meterla.

### Regla de matiz (sin campo extra)

Debe existir **al menos uno** de: `rasgos_ocultos` no vacío, `estado_inicial.crush.persona`, `dealbreakers`, un lazo `roce`, o una `preferencia_romantica` con `visible: false`.  
Si no hay ninguno → FAIL de personalidad (clon / adjetivo plano).

---

## 4. Visibles (B) vs ocultos (solo A)

### Van en B (y también en A)

| Campo | Notas |
| --- | --- |
| `nombre` | |
| `edad` | |
| `genero` | |
| `pronombres` | |
| `etiqueta_orientacion_visible` | Solo si no es `""`. Si va vacía, B no menciona orientación. |
| `ocupacion` | A groso modo; el id vale |
| `franja_disponibilidad` | Disponibilidad percibida, no reloj Sims |
| `hobby_principal` | Aunque el lugar esté 🔒 |
| `hobbies_secundarios` | Si hay |
| `lugares_preferentes` | |
| `estilo_social` | |
| `rasgos_publicos` | Los 3 |
| `gustos_declarados` | |
| `preferencias_romanticas` con `visible: true` | Si las hay |
| `estilo_visual` | Dirección de caricatura; una línea |
| `rasgos_fisicos` | Cara y cuello para el retrato. Neni lo ve (es aspecto, no plot) |
| `etiquetas_look_base` | Cómo se lee ahora |
| `frase_semilla` | Tono. Sin spoilers |
| `empieza_en_pueblo` | El jugador ve si es vecino o recién llegado **cuando aparece** |

Familia / amigos en B **solo** si el lazo tiene `publico: true` **y** la otra persona es ya pública para Neni. Si `persona` es un slot no publicado: el lazo queda en A (`publico` da igual: B no nombra slots).

### Nunca en B

| Campo | Por qué |
| --- | --- |
| `slot`, `id` interno | Meta de diseño |
| `atraido_por` | Grafo; sorpresa |
| `apertura_descubrimiento` | No se nombra |
| `voz` | Etiqueta de motor; Neni oye `frase_semilla` |
| `rasgos_ocultos` | Se descubren |
| `preferencias_romanticas` con `visible: false` | |
| `preferencias_esteticas` | Lo que le atrae en *otra* gente |
| `plasticidad_preferencias` | Motor; no es cotilleo inicial |
| `*.eje` | Id interno de catálogo |
| `dealbreakers` | |
| `necesidad_contacto_base` | Cifra; luego color / ⚠️ |
| `estado_inicial.crush` | |
| lazos con `tipo: roce` o `publico: false` | Sale por diario |
| `funcion_llegada` | Guion de diseño, no cotilleo inicial |
| `orden_llegada` | Meta |
| `looks_posibles` | Tienda futura |
| `tono_coletilla` | Motor |
| `reciprocos_potenciales` | Grafo |
| `estado_ficha`, `piloto` | Meta (Cursor/Neni los saben por el flujo, no van en la tarjeta de pueblo) |
| `estado_animo_inicial` | Runtime |
| Las 4 variables de pareja, recuerdos, citas | No existen en catálogo |

`⚠️`, cita programada, flag `nuevo`, estado sentimental de color: **runtime**, no se rellenan en la entrega.

---

## 5. Fijos vs evolutivos

### Fijos (`identidad`, `vida`, `romance`, `lazos` iniciales, `presencia_v0`, `visual` salvo retrato, `narrativa`)

ChatGPT los escribe. Incluye `atraido_por`, apertura, dealbreakers, hobbies, voz, `plasticidad_preferencias`.

Aprendizaje de gustos en partida (`runtime.aprendizaje_preferencias`): **no** se rellena en diseño. No pisa orientación. `APRENDIZAJE_PREFERENCIAS.md`.

### Semillas (`estado_inicial`)

`animo: neutro`. `crush.persona` = id o slot, o `null`. El juego puede mover el crush; la ficha no guarda «hoy está de mal humor».

### Evolutivos (`runtime`)

**Vacío en diseño.** Ánimo del día, dónde está, flag `nuevo`, citas, las 4 variables, `atracciones_hacia`, recuerdos, lazos nuevos, huellas de aprendizaje, `descubrimientos_jugador`.

La ficha **en partida** crece (público / descubierto / `…`). No se vuelca A. `PROPUESTA_BLOQUE_3_A_24.md`.

---

## 6. Qué puede quedar `pendiente_pool`

Hasta que existan destinos reales (las 16, o al menos el otro extremo del lazo):

| Dato | Cómo se marca en A |
| --- | --- |
| Recíproco potencial | `auditoria.reciprocos_potenciales.estado: pendiente_pool` y/o `slots: ["I04"]` |
| Crush | `estado_inicial.crush.persona`: slot si esa ficha no existe |
| Familia / amigos / roce | `lazos[].persona`: slot (`I07`) |
| Validación de grafo | `auditoria.estado_ficha` máximo `revision` mientras `pendiente_pool` |

`atraido_por`, `genero`, `apertura` y dealbreakers **no** se dejan pendientes: salen en el piloto ya.

Un piloto puede aprobarse de **tono** con B mientras el recíproco sigue `pendiente_pool`.

---

## 7. JSON canónico (ya alineado)

`data/personajes/_plantilla.personaje.json` representa el contrato **sin pérdida**.

ChatGPT sigue entregando **markdown A+B** (esto es lo que lee Neni en B). Cursor copia A al JSON. **No se guarda un segundo objeto B.**

### B se deriva de A

| En B (markdown para Neni) | Sale de JSON |
| --- | --- |
| nombre, edad, género, pronombres | `identidad.*` |
| etiqueta orientación | `identidad.etiqueta_orientacion_visible` si no es `""` |
| ocupación, franja, hobbies, lugares, estilo social, rasgos públicos, gustos | `vida.*` (no `rasgos_ocultos`) |
| preferencias románticas visibles | `romance.preferencias_romanticas` con `visible: true` → solo `texto` |
| estilo visual, rasgos físicos, look base | `visual.*` |
| frase semilla | `narrativa.frase_semilla` |
| empieza en pueblo | `presencia_v0.empieza_en_pueblo` |
| lazos públicos | `lazos` con `publico: true` **y** la otra persona ya visible para Neni; texto tipo `hermana de [nombre]` |

Si el B de ChatGPT **añade** un secreto de A → FAIL. Si omite un campo público, Cursor lo completa desde A.

### Preferencias: tipo, no peso

`{ "texto", "visible", "tipo", "eje"? }`. `tipo` opcional: `ritmo` / `estilo_cita` / `intensidad` / `otro`. **No hay peso 0–100** en la ficha (sería un % disfrazado). El motor sesga con las 4 variables y, más adelante, con ejes de aprendizaje en runtime — no con un número escrito por ChatGPT.

`eje` opcional: enganche al catálogo `ejes_preferencia`. Si falta, esa prosa no entra en el pipeline de aprendizaje.

`plasticidad_preferencias`: `baja` / `media` / `alta`. Cómo de fácil reinterpreta gustos de pareja. Distinto de `apertura_descubrimiento`.

### Dealbreakers

`{ "texto", "severidad": "leve"|"fuerte"|"absoluto", "eje"? }`. `absoluto` = capa 1. Un absoluto **no** puede ser lo único que deja a alguien sin recíproco (auditoría de grafo). El aprendizaje **no** crea ni borra `absoluto`.

### Lazos

Un array `lazos`: `{ "persona": "per_xx"|"I07", "tipo": "<catálogo>", "publico": false }`. Familia, amistad, ex y roce conviven aquí. Recíproco en las dos fichas cuando ambas existan.

### Recíprocos

```text
auditoria.reciprocos_potenciales.estado: pendiente_pool | resuelto | fallido
auditoria.reciprocos_potenciales.slots: []   # mientras el pool no está
auditoria.reciprocos_potenciales.ids: []     # cuando hay per_xx
```

Piloto: `pendiente_pool`. Tras las 16: Cursor rellena `ids` y pone `resuelto` o `fallido`.

### Mapa markdown A → JSON

| Markdown A | JSON |
| --- | --- |
| slot, id, piloto | raíz |
| nombre…apertura | `identidad` |
| ocupacion…gustos | `vida` |
| necesidad_contacto, plasticidad_preferencias, preferencias, dealbreakers | `romance` |
| familia / amigos / roce | `lazos[]` |
| empieza_en_pueblo, orden, funcion | `presencia_v0` |
| estilo_visual, rasgos_fisicos, looks | `visual` |
| voz, frase_semilla, tono_coletilla | `narrativa` |
| estado_ficha, recíprocos | `auditoria` |
| crush, ánimo neutro | `estado_inicial` |
| (nada de «hoy») | `runtime` vacío |

### Atracción asimétrica

Reglas de tipo: `identidad.atraido_por` (capa 1). Persona concreta: `estado_inicial.crush` (semilla) y `runtime.atracciones_hacia` (partida, dos sentidos en la relación). No hace falta un segundo campo de orientación.

---

## A. Ficha interna (exportar / auditar)

Campos en este orden. Valores vacíos = aún no relleno, no «inventa tú».

```text
### A. FICHA INTERNA
slot:
id:
piloto:                 false
estado_ficha:           borrador

nombre:
edad:
genero:
pronombres:             []
atraido_por:            []
etiqueta_orientacion_visible:
apertura_descubrimiento:

ocupacion:
franja_disponibilidad:
hobby_principal:
hobbies_secundarios:    []
lugares_preferentes:    []
estilo_social:
voz:
rasgos_publicos:        []
rasgos_ocultos:         []
gustos_declarados:      []
necesidad_contacto_base:
plasticidad_preferencias:

preferencias_romanticas:
  - texto:
    visible:
    eje:

preferencias_esteticas: []
dealbreakers:
  - texto:
    severidad:
    eje:

familia / amigos / roce (→ lazos):
  - persona:            (id o slot)
    tipo:
    publico:            false

empieza_en_pueblo:
orden_llegada:
funcion_llegada:

estilo_visual:
rasgos_fisicos:         []
etiquetas_look_base:    []
looks_posibles:         []
tono_coletilla:
frase_semilla:
crush_inicial:          (persona: id o slot)
reciprocos_potenciales: pendiente_pool
estado_animo_inicial:   neutro
```

---

## B. Ficha pública (Neni / jugador al conocerle)

Solo esto. Si hace falta un campo de A que no está aquí: **no se pone**.

```text
### B. FICHA PÚBLICA
nombre:
edad:
genero:
pronombres:             []
etiqueta_orientacion_visible:

ocupacion:
franja_disponibilidad:
hobby_principal:
hobbies_secundarios:    []
lugares_preferentes:    []
estilo_social:
rasgos_publicos:        []
gustos_declarados:      []
preferencias_romanticas_visibles: []

estilo_visual:
rasgos_fisicos:         []
etiquetas_look_base:    []
frase_semilla:

empieza_en_pueblo:
lazos_publicos:         []
```

`lazos_publicos`: lista corta tipo `hermano de [nombre ya público]` o vacía. Nunca un slot (`I07`) ni un nombre no publicado.

`preferencias_romanticas_visibles`: solo los `texto` de A con `visible: true`. Si no hay, `[]`.

---

## 8. Ejemplo vacío (cero personaje)

Copiar tal cual y rellenar. No hay nombres, ni edad, ni chiste.

```text
### A. FICHA INTERNA
slot:
id:
piloto:                 false
estado_ficha:           borrador

nombre:
edad:
genero:
pronombres:             []
atraido_por:            []
etiqueta_orientacion_visible:
apertura_descubrimiento:

ocupacion:
franja_disponibilidad:
hobby_principal:
hobbies_secundarios:    []
lugares_preferentes:    []
estilo_social:
voz:
rasgos_publicos:        []
rasgos_ocultos:         []
gustos_declarados:      []
necesidad_contacto_base:
plasticidad_preferencias:

preferencias_romanticas:
  - texto:
    visible:
    eje:

preferencias_esteticas: []
dealbreakers:
  - texto:
    severidad:
    eje:

familia / amigos / roce (→ lazos):
  - persona:
    tipo:
    publico:            false

empieza_en_pueblo:
orden_llegada:
funcion_llegada:

estilo_visual:
rasgos_fisicos:         []
etiquetas_look_base:    []
looks_posibles:         []
tono_coletilla:
frase_semilla:
crush_inicial:          (persona: id o slot)
reciprocos_potenciales: pendiente_pool
estado_animo_inicial:   neutro

### B. FICHA PÚBLICA
nombre:
edad:
genero:
pronombres:             []
etiqueta_orientacion_visible:

ocupacion:
franja_disponibilidad:
hobby_principal:
hobbies_secundarios:    []
lugares_preferentes:    []
estilo_social:
rasgos_publicos:        []
gustos_declarados:      []
preferencias_romanticas_visibles: []

estilo_visual:
rasgos_fisicos:         []
etiquetas_look_base:    []
frase_semilla:

empieza_en_pueblo:
lazos_publicos:         []
```

Listas opcionales vacías se pueden omitir o dejar `[]`. Campos obligatorios no.

---

## 9. Validación rápida de Cursor (además del checklist)

FAIL inmediato si:

- Falta A o falta B
- B contiene cualquier clave de §4 «nunca en B»
- `frase_semilla` > 140 o delata un secreto
- B nombra a alguien que no es público
- Enum ilegal
- `rasgos_publicos` ≠ 3
- Edad fuera de 22–72
- `atraido_por` vacío
- `rasgos_fisicos` con menos de 3 ítems o con plot (crush, orientación, función de llegada)
- Llegada sin `orden_llegada` + `funcion_llegada`
- `estado_ficha: aprobado` con `reciprocos_potenciales.estado: pendiente_pool`
- dealbreaker `absoluto` que deja a alguien con 0 recíprocos (cuando el pool exista)
