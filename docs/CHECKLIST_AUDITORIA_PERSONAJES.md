# Checklist de auditoría de personajes

**Ninguna ficha es FINAL** hasta superar esta auditoría. No es código. No sustituye a `FASE_DISENO_POOL_16.md` ni a `VALIDACION_GRAFO_ROMANTICO.md`: los usa.

## Para qué existe

1. Conservar que **Neni se encuentre gente inesperada al jugar**. Ve y aprueba el tono de **2–3 pilotos**. El resto del pool de 16 lo diseña ChatGPT; no hace falta que lo lea uno a uno.
2. Evitar el fallo habitual: *los primeros muy trabajados y los últimos deprisa o fuera de reglas*.
3. Que Cursor pueda comprobar que **las 16** siguen el mismo estándar.

El «sí, este tono» de Neni **no** convierte una ficha en `aprobado`. Esa palabra es de la auditoría.

| Quién | Qué hace |
| --- | --- |
| Neni | 2–3 pilotos (tono). El resto, en partida. |
| ChatGPT | Diseña el pool con el mismo estándar que los pilotos. Revisión narrativa. |
| Cursor | Auditoría estructural, reglas, clonado, grafo. **Genera retratos** (borrador). **No** reescribe identidad en silencio. No auto-aprueba imágenes. |

---

## Estados

### Ficha (`estado_ficha` en cada JSON)

| Estado | Significa |
| --- | --- |
| `borrador` | Escrita. Aún no auditada, o con fallos abiertos. |
| `revision` | Nivel 1 pasado *o* tono de piloto OK. Puede faltar el recíproco real del pool. |
| `aprobado` | Nivel 1 **completo**, incluido recíproco comprobable en el pool. |

Un piloto al que Neni ha dicho «sí» queda en `revision` hasta que existan destinos recíprocos. Entonces Cursor cierra esa línea y puede pasar a `aprobado`.

### Pool (`estado_pool` en `data/personajes/personajes.json`)

| Estado | Significa |
| --- | --- |
| `borrador` | Faltan fichas o hay fallos de nivel 1. |
| `auditoria_global` | Las 16 existen y están al menos en `revision`. Se está haciendo el nivel 2. |
| `aprobado` | Nivel 2 pasado. El roster V0 se puede usar para programar / jugar prueba. |

`aprobado` de pool **exige** las 16 fichas en `aprobado`.

---

## Flujo (no 16 de golpe para Neni)

1. Aprobar o retocar el esqueleto de slots (`FASE_DISENO_POOL_16.md` §7).
2. ChatGPT escribe **2–3 pilotos** distintos entre sí, en formato `CONTRATO_PERSONAJE.md` (A+B, con `rasgos_fisicos`).
3. Cursor: nivel 1 estructural (contra el contrato). Si FAIL, no hay retrato.
4. Si la estructura pasa: Cursor genera retrato **borrador** (`CONTRATO_VISUAL_PERSONAJES.md`).
5. Neni ve **solo B + retrato**. Aprueba tono de ficha y/o imagen por separado (`APROBADO` / `REGENERAR` / cambios).
6. ChatGPT escribe el resto (recomendado: primero los 10 iniciales, luego L01–L06), siempre A+B.
7. Por **cada** ficha, incluidos los últimos: Cursor nivel 1 → retrato borrador → ChatGPT narrativa si hace falta → `revision`.
8. Con 16: nivel 2 (grafo, diversidad, red, jugabilidad, voces).
9. Solo entonces `estado_pool: aprobado`.

El retrato `aprobado` no aprueba el grafo. El grafo OK no aprueba la cara.

**Pasada anti-prisas:** las fichas **L03–L06** (o las 4 últimas que se escriban) reciben el mismo checklist que I03, no un «ya vale». Si el nivel 1 de las últimas es más corto que el de los pilotos, FAIL de proceso.

Neni no tiene que leer las 13–14 restantes. Cursor sí tiene que pasarlas **por el mismo checklist**.

No volcar nombres ni spoilers del roster no-piloto en docs que Neni use para «conocer el pueblo» antes de jugar.

### Pilotos sugeridos (slots, no nombres)

Para que el tono no se apruebe solo con gente joven hetero:

- `I03` (mismo género, intenso)
- `I05` (mismo género, otro registro)
- `I10` (otra banda de edad)

Si preferís 2: `I03` + `I10`. No tres clones del mismo chiste.

Campo `piloto: true` **solo** en esos.

---

## Revisión en tres pasos

Cuando ChatGPT entregue personajes:

1. **Cursor** — validación estructural y de reglas (enums, catálogos, vetos, campos, clónicos, longitud, grafo cuando haya destinos). Escribe PASS/FAIL en el registro.
2. **ChatGPT** — revisión narrativa y de diseño (voz, matiz, «¿se juega?», tono Aquí Hay Tema, no estereotipo plano).
3. **Auditoría global del pool** — cuando existan las 16, **antes** de `estado_pool: aprobado`.

### Si algo falla

| Tipo de fallo | Qué hacer |
| --- | --- |
| Estructura / formato (campo vacío, enum ilegal, id mal escrito, hobby fuera de catálogo) | Cursor **corrige** y lo anota en una línea. |
| Identidad (nombre, género, `atraido_por`, personalidad, familia, función de llegada, dealbreaker absoluto, voz) | **No** corregir en silencio. Fallo + propuesta. Decide quien diseñó (ChatGPT) o Neni (si es piloto). |

---

## Nivel 1 — auditoría individual

Marcar cada bloque PASS / FAIL. Un FAIL de identidad bloquea `aprobado`. Un FAIL estructural se puede arreglar en el acto.

Registro compacto: una letra por bloque en `REGISTRO_AUDITORIA_PERSONAJES.md` (`I P V R N J Vis`).

### Identidad

- [ ] Todos los **obligatorios** de `FASE_DISENO_POOL_16.md` §B existen y no están vacíos
- [ ] `id` estable (`per_xx` / slot), nunca el nombre como clave
- [ ] Edad adulta válida: **22–72**
- [ ] `genero` ∈ `mujer` / `hombre` / `no_binarie`
- [ ] Pronombres coherentes con el modelo (salvo decisión explícita y anotada)
- [ ] `atraido_por` ⊆ esos tres ids, **mínimo uno**
- [ ] `apertura_descubrimiento` ∈ `cerrada` / `permeable` / `abierta`
- [ ] Etiqueta visible, si hay, **no manda**; si discrepa de `atraido_por`, es descubrible, no un error
- [ ] Sin contradicciones internas (jubilado de 24; `atraido_por: []`; progenitor más joven que el hijo; etc.)

### Personalidad

- [ ] Personalidad reconocible en una frase (no hace falta biografía)
- [ ] No es una lista genérica de adjetivos intercambiables
- [ ] Los 3 `rasgos_publicos` **pueden** producir conducta (cita, diario, rechazo)
- [ ] Al menos un matiz o contradicción humana cuando proceda (oculto, roce, crush, dealbreaker leve/fuerte, voz vs estilo)
- [ ] No es clon evidente de otra ficha ya escrita (misma voz + mismo estilo social + ≥2 rasgos iguales + banda de edad cercana → FAIL salvo justificación)

### Vida

- [ ] `ocupacion` del catálogo de 8, coherente con edad
- [ ] `franja_disponibilidad` usable; no absurda con el oficio (docente-noche = justificado o FAIL)
- [ ] `hobby_principal` del catálogo candidato; secundarios válidos si hay
- [ ] `lugares_preferentes` ligados al hobby o a su vida; ids reales (`MAPA_Y_LUGARES.md`)
- [ ] Vínculos: `lazos[]` con `persona` + `tipo` legales; parentesco **recíproco** en las dos fichas cuando existan
- [ ] `presencia_v0` coherente (I empiezan; L no; `orden_llegada` 1–6 único)
- [ ] Estado inicial compatible (`estado_animo_inicial` simple; nadie empieza ya emparejado en V0)

### Romance

- [ ] Preferencias románticas **estructuradas** (cortas); no un párrafo
- [ ] Fichas **nuevas**: `plasticidad_preferencias` ∈ `baja` / `media` / `alta`. Las ya escritas pueden no tenerlo (`media` en simulación). No reescribir identidad solo para meter el campo
- [ ] Si hay `eje` en preferencia/dealbreaker, existe en `ejes_preferencia.json`. El aprendizaje no se rellena en diseño
- [ ] Dealbreakers definidos cuando correspondan; un absoluto no puede anular *todas* las vías
- [ ] Atracción posible según las reglas (`atraido_por` + vetos)
- [ ] ≥1 recíproco **potencial** en el pool de 16 — si el pool aún no existe: `pendiente_pool`, ficha máximo `revision`
- [ ] Unilaterales adicionales: permitidos (no obligatorios en nivel 1)
- [ ] Ningún parentesco vetado aparece como crush / destino romántico

### Narrativa

- [ ] Voz reconocible; `voz` rellena y se nota en `frase_semilla`
- [ ] Tono compatible con Aquí Hay Tema (pueblo, cutrez, no drama de premio ni ficha de LinkedIn)
- [ ] Coletilla o estilo verbal usable (`tono_coletilla` o semilla que el diario pueda citar); una línea, no escena (`COLETILLAS.md`)
- [ ] Visible vs oculto claramente separado (dealbreakers, crush, apertura, estética: no van en la tarjeta pública de **El pueblo**)
- [ ] Frases no excesivamente largas: `frase_semilla` ≤ ~140 caracteres; gustos de una línea
- [ ] Cero dependencia de IA en runtime (catálogo cerrado, no prompt)

### Juego

- [ ] Genera decisiones o historias (no es decorado)
- [ ] No tiene «pareja perfecta obvia»: horario, trato y preferencias no apuntan a una sola persona del pool como puzzle resuelto
- [ ] Al menos un elemento de conflicto, sorpresa o evolución
- [ ] Sus datos sirven a citas, diario y encuentros espontáneos (ocupación, hobby, voz, lugares, lazos)

### Visual (ficha + retrato)

- [ ] `estilo_visual` + `rasgos_fisicos` (3–8, solo cara/cuello) + `etiquetas_look_base`
- [ ] `rasgos_fisicos` no son «cara bonita»; hay al menos un rasgo reconocible (nariz, pelo, gafas, edad, etc.)
- [ ] Retrato, si ya existe: contrato `CONTRATO_VISUAL_PERSONAJES.md`; estado `borrador` hasta que Neni diga APROBADO
- [ ] El prompt del retrato no contiene secretos de A
- [ ] `looks_posibles` preparados aunque la ropa no esté en V0

---

## Nivel 2 — auditoría del pool completo

Solo con `estado_pool: auditoria_global`. Cursor rellena **un bloque** al final del registro (no 16 novelas).

### Grafo romántico

Usar `VALIDACION_GRAFO_ROMANTICO.md`. Además:

- [ ] Nadie aislado (0 destinos legales)
- [ ] Cada uno tiene ≥1 recíproco potencial
- [ ] Los **10 iniciales** también entre ellos
- [ ] Hay **alguna** atracción unilateral en el pool
- [ ] Varias combinaciones posibles; no un matching único obvio de 8 parejas
- [ ] Nadie es hub de todo el pueblo salvo que esté escrito a propósito (anotarlo)
- [ ] Nadie es prácticamente imposible: difícil sí; 0 vías no. Si es «difícil a propósito», sigue habiendo **al menos una vía válida**
- [ ] Prefijos 10, 10+L01, … +L06 no rompen el grafo (`apertura_descubrimiento` no salva)

### Diversidad

No cuotas rígidas si perjudican el diseño. SÍ flag si hay concentración accidental.

Comprobar variedad de: edades adultas, géneros, orientaciones / patrones de `atraido_por`, cuerpos / estética, trabajos, horarios, hobbies, personalidades, estilos sociales, maneras de hablar (`voz`).

- [ ] Edades: no todo 28–35
- [ ] Géneros: no olvidar `no_binarie` si el esqueleto los trae
- [ ] Patrones de atracción variados (no 14 heteros + 2 de adorno)
- [ ] Looks / cuerpos distintos en las etiquetas
- [ ] Las 8 ocupaciones usadas; no 10 oficinistas
- [ ] Las 4 franjas existen
- [ ] Hobbies abiertos y 🔒 mezclados
- [ ] Las 6 voces no se reducen a 12 `seca`

### Red social

- [ ] Parentescos recíprocos y con edades posibles
- [ ] Amistades iniciales: pocas, no un clan
- [ ] Ex: 0–1 en el pool, no un culebrón previo
- [ ] Compañeros / choques: usables en diario
- [ ] Tensiones posibles sin vetar romance por error
- [ ] Ningún parentesco prohibido aparece como opción romántica

### Jugabilidad

- [ ] Los 10 residentes iniciales funcionan como grupo (citas, diario) sin esperar a L06
- [ ] Los 6 que llegan aportan cambios reales (funciones distintas)
- [ ] Las llegadas no rompen el grafo
- [ ] Cada alta vuelve a validar el grafo romántico del pueblo presente
- [ ] Hobbies con lugar abierto, 🔒 visible o futuro coherente
- [ ] Horarios variados: citas posibles en mañana, tarde y noche

### Narrativa de pueblo

- [ ] No 16 personajes con la misma voz
- [ ] No la misma biografía cambiando el nombre
- [ ] Mezcla de fáciles de leer y menos predecibles
- [ ] Potencial para comedia, ternura, conflicto y sorpresas
- [ ] Evitar estereotipos planos (el docente no tiene que ser el aburrido; la jubilada no tiene que ser solo abuela)

---

## Cómo se registra (sin burocracia)

Un solo archivo: `REGISTRO_AUDITORIA_PERSONAJES.md`.

- **Una línea por ficha.** No un acta. No 16 documentos.
- Columna compacta de nivel 1: `I P V R N J Vis` con `✓` / `✗` / `~` (`~` = `pendiente_pool`).
- Una nota corta **solo** si hay FAIL o corrección estructural.
- Al final: un bloque nivel 2 (fecha + PASS/FAIL por sección + 3–5 notas).

Ejemplo:

```text
I03 | per_03 | sí | revision | I✓ P✓ V✓ R~ N✓ J✓ Vis✓ | recíproco pendiente_pool
L03 | per_13 | no | borrador | I✗ P✓ V✓ R✓ N✓ J✓ Vis✓ | atraido_por vacío — no tocar nombre
```

El histórico vive aquí, no duplicado en la ficha.

Neni no tiene que leer el registro. ChatGPT y Cursor sí.

---

## Campos en ficha

Ver `data/personajes/_plantilla.personaje.json`. Mínimo:

```text
estado_ficha: borrador | revision | aprobado
piloto: true | false
```

Pool:

```text
estado_pool: borrador | auditoria_global | aprobado
```
