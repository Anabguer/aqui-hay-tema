# PACK FASE C — Piloto I02 (ChatGPT)

**Proyecto:** Aquí Hay Tema  
**Versión pack:** 2026-08-18  
**Uso:** documento **autocontenido**. ChatGPT crea **solo I02** (bloques A + B). Cursor valida; **no** rellenar este pack.

**Entrega:** un markdown con `### A. FICHA INTERNA` y `### B. FICHA PÚBLICA` en ese orden.

---

## 0. Qué debes hacer (y qué no)

| Hacer | No hacer |
| --- | --- |
| Escribir **I02** completo (A + B) | Crear I10, I06, Rocío ni las 16 fichas |
| Respetar **datos cerrados del slot** (§2) | Cambiar slot, género, orientación base, ocupación… |
| Inventar nombre, aspecto, personalidad, secretos **dentro del molde** | Copiar ficha de Rocío ni leer secretos ajenos |
| Usar **slots** para lazos no escritos (`I08`, `I06`…) | Nombrar personajes no públicos en B |
| Incluir `rasgos_fisicos` 3–8 (cara/cuello) | Biografías, escenas, párrafos |
| Dejar `reciprocos_potenciales: pendiente_pool` | Poner `estado_ficha: aprobado` |

**Rocío:** existe como **slot I08** (`per_i03`). Puedes citar **I08** en A para grafo/crush/lazos. **No** copies su ficha interna. En **B** no nombres a Rocío salvo que en el futuro sea público (ahora: **no nombrar** en B).

**Contexto micro-sim α:** I02 forma parte del cast de prueba con Rocío (I08) e I10; piloto I06 (roce) se escribirá **después**. Puedes documentar en A un lazo `roce` con slot **I06** (opcional pero útil); I06 lo completará al escribirse.

---

## 1. Datos cerrados del slot I02 (NO negociables)

| Campo | Valor obligatorio |
| --- | --- |
| `slot` | `I02` |
| `id` | `per_i02` |
| `piloto` | `true` |
| `estado_ficha` | `borrador` |
| Banda `edad` | **22–29** (entero) |
| `genero` | `hombre` |
| `atraido_por` | **`["mujer"]`** (mínimo uno; solo mujeres) |
| `interes_romantico` | **`activo`** |
| `ocupacion` | `camarero` |
| `franja_disponibilidad` | `noche` |
| `estilo_social` | `fiestero` |
| `hobby_principal` | **`copas`** (bar 🔒 — deseo / lugar futuro) |
| `empieza_en_pueblo` | `true` |
| `orden_llegada` | *(no aplica)* |
| `funcion_llegada` | *(no aplica)* |

Creatividad libre **solo** donde el contrato lo permite: nombre, edad exacta en banda, rasgos, voz, secretos, dealbreakers, crush, lazos, compromisos, físico, frase_semilla, etc.

**Tono juego:** gestión social en villa cutre y simpática. Celestine = jugador. No didáctico. No LinkedIn. No drama de premio.

---

## 2. Enums y catálogos (V0 — solo valores permitidos)

| Campo | Valores |
| --- | --- |
| `apertura_descubrimiento` | `cerrada` · `permeable` · `abierta` |
| `ocupacion` | `sanitario` · `oficina` · `camarero` · `comercio` · `docente` · `estudiante_adulto` · `autonomo` · `jubilado` |
| `franja_disponibilidad` | `manana` · `tarde` · `noche` · `flexible` |
| `hobby_principal` / secundarios | `leer` · `escribir` · `pasear` · `correr` · `cafe_social` · `manualidades` · `cocina` · `musica` · `cine` · `videojuegos` · `copas` · `baile` |
| `estilo_social` | `casero` · `fiestero` · `tranquilo` · `intenso` · `espontaneo` · `planificador` |
| `voz` | `seca` · `dramatica` · `tranquila` · `borde` · `candida` |
| `rasgos_publicos` / `rasgos_ocultos` | `directo` · `timido` · `ironico` · `leal` · `cabezota` · `empatico` · `vanidoso` · `ansioso` · `bromista` · `reservado` |
| `necesidad_contacto_base` | `baja` · `media` · `alta` |
| `plasticidad_preferencias` | `baja` · `media` · `alta` — **obligatorio en A** |
| `interes_romantico` | `activo` · `pasivo` · `ninguno` — I02 = **`activo`** |
| `susceptibilidad_enemigo_romance` | `nula` · `baja` · `media` · `alta` — opcional; default `nula` o `baja` |
| `dealbreaker.severidad` | `leve` · `fuerte` · `absoluto` |
| `preferencia_romantica.tipo` | `ritmo` · `estilo_cita` · `intensidad` · `otro` |
| `tipo` de lazo | `padre` `madre` `hijo` `hija` `hermano` `hermana` `medio_hermano` `medio_hermana` `abuelo` `abuela` `nieto` `nieta` `tio` `tia` `sobrino` `sobrina` `primo_hermano` `prima_hermana` `amigo` `amiga` `mejor_amigo` `mejor_amiga` `conocido` `conocida` `companero` `companera` `ex` · **`roce`** |
| `etiquetas_look_base` / estéticas | `elegante` · `comodo` · `atrevido` · `alternativo` · `formal` · `deportivo` · `raro` · `vintage` |
| `estado_animo_inicial` | `neutro` |
| `reciprocos_potenciales` | `pendiente_pool` · `resuelto` · `fallido` — usar **`pendiente_pool`** |

### Lugares (`lugares_preferentes`) — ids válidos

Mínimo **1**, máximo **2**:

| id | Nombre | Nota V0 |
| --- | --- | --- |
| `lug_cafeteria` | Cafetería | **Operativa** día 1 |
| `lug_parque` | Parque | En mapa; cerrado al inicio |
| `lug_biblioteca` | Biblioteca | En mapa; cerrado al inicio |
| `lug_bar` | Bar | Candado visible — coherente con hobby `copas` |
| `lug_cine` | Cine | Candado |
| `lug_arcade` | Arcade | Candado |

*(Otros lugares existen en catálogo pero no hace falta usarlos en I02.)*

---

## 3. Límites de longitud y cardinalidad

| Campo | Límite |
| --- | --- |
| `id` | `per_` + 2–8 caracteres `[a-z0-9_]` → **`per_i02`** |
| `nombre` | 2–40 caracteres |
| `edad` | entero **22–29** |
| `pronombres` | 1–3 ítems, ≤ 16 caracteres c/u |
| `etiqueta_orientacion_visible` | `""` o ≤ 24 caracteres |
| `gustos_declarados` | 0–3 ítems, ≤ 40 caracteres c/u |
| `preferencias_romanticas` | 0–3 ítems; `texto` ≤ 80 caracteres |
| `dealbreakers` | 0–2 |
| `rasgos_publicos` | **exactamente 3** ids |
| `rasgos_ocultos` | 0–2 ids |
| `hobbies_secundarios` | 0–2 ids |
| `lugares_preferentes` | 1–2 ids |
| `lazos` | 0–4 en V0 |
| `etiquetas_look_base` | 1–3 |
| `looks_posibles` | 0–4 (recomendable 2–4) |
| `estilo_visual` | 1 línea, ≤ 120 caracteres |
| `frase_semilla` | 1 frase, ≤ **140** caracteres; **sin spoilers** |
| `rasgos_fisicos` | **3–8** ítems, ≤ 40 caracteres c/u; **solo cara/cuello** |
| `preferencias_esteticas` | 0–3 etiquetas look |

**Prohibido:** párrafos, infancia, playlist, arco de tres actos, escenas.

---

## 4. Obligatorios vs opcionales en A (I02)

### Obligatorios en A

`slot`, `id`, `nombre`, `edad`, `genero`, `pronombres`, `atraido_por`, `apertura_descubrimiento`, `ocupacion`, `franja_disponibilidad`, `hobby_principal`, `lugares_preferentes` (≥1), `estilo_social`, `voz`, `rasgos_publicos` (3), `necesidad_contacto_base`, `empieza_en_pueblo`, `estilo_visual`, `rasgos_fisicos` (3–8), `etiquetas_look_base` (≥1), `frase_semilla`, `estado_ficha` (`borrador`), `piloto` (`true`), **`plasticidad_preferencias`**, **`interes_romantico`** (`activo`).

### Opcionales en A (puedes usar)

`compromisos_recurrentes` (0–3), `susceptibilidad_enemigo_romance`, `etiqueta_orientacion_visible`, `hobbies_secundarios`, `rasgos_ocultos`, `gustos_declarados`, `preferencias_romanticas`, `preferencias_esteticas`, `dealbreakers`, `lazos`, `looks_posibles`, `tono_coletilla`, `crush_inicial` / `estado_inicial.crush`.

### Regla de matiz (FAIL si falta)

Debe existir **al menos uno** de:

- `rasgos_ocultos` no vacío  
- `crush_inicial` / crush hacia slot o id  
- `dealbreakers` (≥1)  
- lazo tipo **`roce`**  
- `preferencia_romantica` con `visible: false`  

Si no hay ninguno → personalidad plana (FAIL).

---

## 5. Visibilidad A vs B

### Van en **A y B**

`nombre`, `edad`, `genero`, `pronombres`, `etiqueta_orientacion_visible` (si no es vacía), `ocupacion`, `franja_disponibilidad`, `hobby_principal`, `hobbies_secundarios`, `lugares_preferentes`, `estilo_social`, `rasgos_publicos`, `gustos_declarados`, `preferencias_romanticas` solo las de `visible: true`, `estilo_visual`, `rasgos_fisicos`, `etiquetas_look_base`, `frase_semilla`, `empieza_en_pueblo`.

Lazos en B **solo** si `publico: true` **y** la otra persona ya es pública para Neni. **Ahora:** no nombres slots ni vecinos no escritos en B → `lazos_publicos: []` salvo excepción acordada.

### **Nunca en B**

`slot`, `id`, `piloto`, `estado_ficha`, `atraido_por`, `apertura_descubrimiento`, `interes_romantico`, `voz`, `rasgos_ocultos`, preferencias con `visible: false`, `preferencias_esteticas`, `plasticidad_preferencias`, `dealbreakers`, `necesidad_contacto_base`, crush, lazos `roce` o `publico: false`, `compromisos_recurrentes`, `funcion_llegada`, `orden_llegada`, `looks_posibles`, `tono_coletilla`, `reciprocos_potenciales`, campos `eje`, variables de pareja, runtime.

---

## 6. Grafo y recíprocos (I02)

### Regla (pool 16)

Para I02 (`interes_romantico: activo`), en **A** debe quedar documentado que existe **≥1 recíproco potencial** entre mujeres del pool futuro:

1. Sin parentesco vetado entre A y B.  
2. Sin dealbreaker **absoluto** mutuo que anule romance para siempre.  
3. `genero` de I02 (`hombre`) ∈ `atraido_por` de B.  
4. `genero` de B (`mujer`) ∈ `atraido_por` de I02 (`mujer`).

**Mientras no existan todas las fichas:**

```text
reciprocos_potenciales: pendiente_pool
```

Y en A, lista de **slots** candidatos, p. ej. `["I08", "I01", "I07"]` — **sin inventar nombres**.

**Slot I08** (Rocío): mujer, hetero, puede ser recíproco **potencial** legal con I02. Puedes usar `I08` en crush o lista de slots. **No** copies dealbreakers ni secretos de Rocío.

**Trío día 1 real:** Rocío sí; B y C del juego **pendientes**. La regla de recíproco es de **tanda 16**, no exige romance entre los 3 del tutorial.

### Parentesco que **veta** romance

Padre/madre↔hijo/a, hermanos, medio hermanos, abuelo/a↔nieto/a, tío/a↔sobrino/a, **primos hermanos**.

### Dealbreakers

- Máximo **2**.  
- `severidad`: `leve` | `fuerte` | `absoluto`.  
- Un **absoluto** no debe ser lo único que deja a I02 sin ningún recíproco posible en el pool (cuando exista el pool).

### Preferencias románticas

Formato en A:

```text
preferencias_romanticas:
  - texto: "..."
    visible: true | false
    tipo: ritmo | estilo_cita | intensidad | otro   # opcional
    eje:                                              # opcional; vacío si no hay catálogo
```

En B: solo textos con `visible: true` → `preferencias_romanticas_visibles: []`.

**Sin pesos numéricos.** Sin porcentajes.

---

## 7. Agenda (solo lo que afecta a I02)

### Modelo cerrado

- **24 slots** de **1 hora** (00–23).  
- Capas (prioridad): **estructural > programado > temporal > autónomo**.  
- Un slot = una actividad; **anti-doble-reserva**.  
- Celestine propone hora; personaje acepta/rechaza con voz.

### Plantilla `camarero` (estructural — no escribir 24 h a mano)

| Pieza | Orientación |
| --- | --- |
| Trabajo | mar–dom o rotativo; bloque **16–24** (noche) |
| Sueño | ventana **01–09** (turno nocturno) |
| Autónomo | motor rellena huecos (cafetería, etc.) |

La ficha lleva `franja_disponibilidad: noche` como **sesgo**; el horario exacto lo resuelve plantilla + motor.

### `compromisos_recurrentes` (opcional 0–3)

**Solo en A.** Formato:

```text
compromisos_recurrentes:
  - id: identificador_snake
    dia_semana: lunes | martes | ... | domingo
    hora_inicio: 0-23
    hora_fin: 0-23
    lugar: lug_*   # opcional
    etiqueta: "texto corto"
    descubrible: true | false
    publico_en_ficha: false
```

**Regla Fase B:** **0 compromisos es válido.** No rellenar solo porque el campo exista. Si pones 1–3, que sean **característicos** (p. ej. noche en bar cuando abra, no calendario genérico).

---

## 8. `rasgos_fisicos` y visual (06b)

### Estilo canónico

- **06b** — caricatura 2D tablero/sitcom, simpática, imperfecta.  
- **Diferenciación estructural obligatoria:** debe distinguirse de otros habitantes **sin** depender solo de pelo/barba/gafas. Variar cráneo, mandíbula, ojos, nariz, boca…  
- **No** belleza Instagram por defecto; **no** feo a propósito; **no** personas reales.

### Encuadre cabeza maestra (para Cursor después)

- Totalmente **de frente**, cabeza + cuello, **casi sin ropa**.  
- **Prohibido** en prompt: busto, jersey protagonista, 3/4, escenario.

### `rasgos_fisicos` (A y B)

- **3–8** ítems.  
- Solo **cara y cuello**: nariz, mandíbula, ojos, cejas, pelo, barba, gafas, orejas, edad visible, asimetrías…  
- **Prohibido** en rasgos_fisicos: plot, orientación, crush, función de llegada.  
- `estilo_visual`: 1 línea dirección caricatura (≤ 120 car.).

I02 es hombre 22–29: la edad debe **leerse** en la cara (no «cara de 20» genérica).

---

## 9. Esquema exacto A — copiar y rellenar

```text
### A. FICHA INTERNA
slot: I02
id: per_i02
piloto: true
estado_ficha: borrador

nombre:
edad:
genero: hombre
pronombres: []
atraido_por: [mujer]
etiqueta_orientacion_visible:
apertura_descubrimiento:
interes_romantico: activo

ocupacion: camarero
franja_disponibilidad: noche
hobby_principal: copas
hobbies_secundarios: []
lugares_preferentes: []
estilo_social: fiestero
voz:
rasgos_publicos: []
rasgos_ocultos: []
gustos_declarados: []
necesidad_contacto_base:
plasticidad_preferencias:

preferencias_romanticas:
  - texto:
    visible:
    tipo:

preferencias_esteticas: []
dealbreakers:
  - texto:
    severidad:
    eje:

compromisos_recurrentes: []
# 0–3 ítems; ver §7. Vacío [] permitido.

susceptibilidad_enemigo_romance:

familia / amigos / roce (→ lazos):
  - persona:            # id o slot (I06, I08…)
    tipo:
    publico: false

empieza_en_pueblo: true

estilo_visual:
rasgos_fisicos: []
etiquetas_look_base: []
looks_posibles: []
tono_coletilla:
frase_semilla:
crush_inicial:          # persona: slot o null
reciprocos_potenciales: pendiente_pool
# slots candidatos: []   ← recomendado listar en comentario o línea extra
estado_animo_inicial: neutro
```

---

## 10. Esquema exacto B — copiar y rellenar

```text
### B. FICHA PÚBLICA
nombre:
edad:
genero: hombre
pronombres: []
etiqueta_orientacion_visible:

ocupacion: camarero
franja_disponibilidad: noche
hobby_principal: copas
hobbies_secundarios: []
lugares_preferentes: []
estilo_social: fiestero
rasgos_publicos: []
gustos_declarados: []
preferencias_romanticas_visibles: []

estilo_visual:
rasgos_fisicos: []
etiquetas_look_base: []
frase_semilla:

empieza_en_pueblo: true
lazos_publicos: []
```

---

## 11. FAIL inmediato (Cursor)

- Falta A o B.  
- B contiene cualquier campo de §5 «nunca en B».  
- `frase_semilla` > 140 o delata secreto.  
- B nombra personaje no público.  
- Enum ilegal.  
- `rasgos_publicos` ≠ 3.  
- `edad` fuera de 22–29.  
- `atraido_por` vacío o sin `mujer`.  
- `hobby_principal` ≠ `copas` · `estilo_social` ≠ `fiestero` · `ocupacion` ≠ `camarero` · `franja` ≠ `noche`.  
- `rasgos_fisicos` < 3 o con plot.  
- Sin matiz (§4 regla de matiz).  
- `estado_ficha: aprobado` con `pendiente_pool`.  
- Biografía larga o escena.

---

## 12. Auto-checklist antes de enviar (nivel 1 resumido)

### Identidad
- [ ] Obligatorios §4 presentes  
- [ ] `id` = `per_i02`, `slot` = `I02`  
- [ ] Edad 22–29, coherente con camarero  

### Personalidad
- [ ] 3 rasgos públicos con conducta posible  
- [ ] ≥1 matiz oculto (§4)  
- [ ] No clon genérico «fiestero random»  

### Vida
- [ ] `lug_bar` o `lug_cafeteria` razonable en preferentes  
- [ ] `empieza_en_pueblo: true`  

### Romance
- [ ] `plasticidad_preferencias` definida  
- [ ] Slots recíprocos listados; `pendiente_pool`  
- [ ] Dealbreaker absoluto no bloquea todo el grafo  

### Narrativa
- [ ] `voz` coherente con `frase_semilla`  
- [ ] Tono Aquí Hay Tema  

### Visual
- [ ] 3–8 rasgos físicos estructurales (06b)  
- [ ] No «cara bonita genérica»  

### B
- [ ] Cero secretos  
- [ ] `lazos_publicos` vacío o solo públicos reales  

---

## 13. Después de tu entrega

1. Cursor valida estructura.  
2. Auditoría checklist.  
3. JSON en `data/personajes/per_i02.json`.  
4. Retrato borrador **06b** (Cursor; Neni ve **B + imagen**).

---

## 14. Decisiones canónicas recientes (solo las que afectan I02)

- **Celestine** = jugador; visible narrativamente; sin avatar.  
- **Sin pareja ≠ fallo** (no aplica a I02 activo).  
- **Estilo visual 06b** + diferenciación estructural.  
- **Día 1 real:** solo **cafetería operativa**; bar 🔒 pero hobby `copas` válido.  
- **Marchas consentidas** (I02 puede querer irse; jugador decide).  
- **Coincidir ≠ interactuar** (NPC autónomos).  
- **Calidad:** no placeholders disfrazados; ver reglas de contenido en producción (no bloquean escribir ficha).

---

*Fin del pack I02. Un solo personaje por entrega.*
