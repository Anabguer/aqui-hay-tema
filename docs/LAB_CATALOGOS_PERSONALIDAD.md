# Lab de catálogos de personalidad y gustos — Aquí Hay Tema

**Estado:** PROPUESTA. **No canon.** No se han tocado P001–P200 ni los JSON de producción. `CatalogStore` de PLAY **no** carga esta carpeta.

**JSON candidato:** `data/catalogos/_candidatos_personalidad/`  
**Volcado:** `php dev/export_catalogos_candidatos.php`  
**Lab 20 muestras:** `php dev/simulador_catalogos_personalidad.php` → `dev/MUESTRA_20_CATALOGOS.md`  
**Tests:** `php tests/catalogos_candidatos_test.php`

Copia de lectura: `docs/LAB_CATALOGOS_PERSONALIDAD.md`

---

## 1. Taxonomía recomendada

La producción actual mezcla poco y genérico: 17 aficiones tipo «música / cine / deporte», 16 rasgos, `preferencias.json` vacío, y un puente `hobbies_lugares.json` que ya es una segunda fuente.

**No hace falta un catálogo nuevo por cada columna de Excel.** Hace falta separar *qué hace*, *qué le tira*, *qué no traga*, *cómo es*, *cómo está con la gente* y *cómo se vincula*.

| Capa | Qué es | Qué no es |
|---|---|---|
| **Aficiones** | Prácticas. Ir al cine, correr, karaoke, plantas. | Géneros, sitios, “ser gamer”. |
| **Gustos** | Matices. Terror, café de verdad, terrazas. Pesan **más** que el estereotipo. | Un clon de aficiones (`cine` otra vez). |
| **Rechazos** | Polaridad negativa: un gusto, un destino, una **actividad** o un **contexto**. | Un segundo listado de hobbies. |
| **Rasgos** | Cómo es. Modulan planes y tono. | `romántico → mirador`. |
| **Preferencias sociales** | Ejes: energía, selectividad, ritmo (ya V0) + **ruido** + **tamaño de grupo**. Label opcional. | Un enum único intro/extro. |
| **Afecto estilo** | Espacio, ritmo de vínculo, cómo se nota. | Relacional V1, orientación, dealbreakers, `ejes_preferencia` (lo que busco en *otra* persona). |
| **Manías** | Peculiaridades. `mecanico: false`. 0–1 por persona. | Rasgos con bonus. |

**Categorías que sobran si se copian tal cual**

- «Gustos» como sinónimo de aficiones.
- «Preferencias sociales» paralelas a `estilos_sociales.json`: se **amplían** los ejes, no se duplican.
- Rasgo `romántico` / `fiestero` como destino disfrazado.
- Afición genérica `deporte`, `música`, `cine`, `viajar`.

---

## 2. Números

| Catálogo | Elementos |
|---|---:|
| Aficiones (11 familias) | **42** |
| Gustos | **28** |
| Rechazos (actividad/contexto; los 15 destinos también son rechazables por id) | **17** |
| Rasgos (7 ejes) | **32** |
| Ejes sociales | **5** (3 ya existían) |
| Ejes de afecto | **3** |
| Manías | **22** |

Combos de 3 aficiones: **11.480**. Combos de 3 rasgos: **4.960**. 200 residentes no agotan el espacio. El riesgo de clones es un generador estereotipado, no el tamaño.

---

## 3. Dónde vive (decisión técnica cerrada)

**JSON versionable** en `data/catalogos/_candidatos_personalidad/`.

- El proyecto **ya** usa JSON + `CatalogStore`. Encaja.
- Se puede revisar en Git, auditar y ampliar sin SQL.
- PLAY no lo carga: cero divergencia operativa hasta que Neni + ChatGPT aprueben.
- El PHP de `dev/datos_catalogos_candidatos_*.php` es andamiaje de volcado. **La pieza a revisar es el JSON.**
- Cuando se apruebe: **sustituir** `aficiones.json` / `rasgos.json` / ampliar `estilos_sociales.json`, y **retirar** `hobbies_lugares.json` (los `lugar_ids` van en la afición). Una sola fuente.
- No hay BD de catálogo creativo y no hace falta inventarla.

IDs estables, cortos, en la línea de `leer` / `bingo`. Alias de producción (`observadora` → `observador`) se conservan al migrar; no se reescribe Rocío.

---

## 4. Relación con los 15 destinos

Cada destino tiene ≥2 afinidades (afición o gusto) y ≥1 rechazo específico, **más** el veto directo del lugar.

Los gustos concretos ganan al estereotipo:

- Cine de sala + rechazo terror.
- Escuchar música + rechazo bailar.
- Correr + rechazo Gimnasio.
- Leer + rechazo «vivir en la biblioteca».
- Sociable + umbral de ruido bajo + rechazo Discoteca.
- Tímida + karaoke.

La personalidad **modula** (grupo, ruido, energía). No asigna el edificio.

---

## 5. Descubrimiento progresivo

Contrato ya existente: al incorporar, 1 afición + 1 rasgo. El resto sale por planes, conversaciones, eventos, peticiones, coincidencias, regalos, cumpleaños, Cotilleo, buzón, terceros.

Cada ítem lleva frases de Celestine (humanas, no «Hobby: Leer») y hooks de evento/regalo. Nada obliga a volcar la ficha el día 1. Una persona puede llevar diez días en el pueblo sin que sepamos que juega.

---

## 6. Incompatibilidades

**Duras (generador / validador):** no afición y rechazo de la misma afición; no gusto y rechazo del mismo gusto; un solo valor por eje social.

**No son incompatibles** (y el lab fuerza 10 ejemplos): tímida+karaoke, sociable+odia discotecas, deporte+odia gimnasio, lectora que no se vive en la biblioteca, cine≠terror, música≠bailar, competitiva que lleva fatal perder, cálida+espacio, lectora+fiestera, juegos+plantas.

---

## 7. Auditoría

- Cobertura 15 destinos: **sin huecos** de afinidad ni de rechazo.
- 20 muestras, seed `lab-catalogos-20`: similitud máxima **0.33**. Ningún clon ≥0.55.
- Ningún ítem sin `usos`.
- Producción intacta (`karaoke` no existe en `aficiones.json` de PLAY).
- Relacional V1, orientación, dealbreakers, UI, assets: no tocados.

**Duplicados semánticos vigilados**

- `tertulia` ≈ `cafe_con_gente`: se mantienen (una es hilo, la otra es el sitio). Si estorban, fusionar después.
- `cabezota` vs un hipotético `obstinado`: no se ha colado el duplicado de `_propuesta_v2`.
- `cotilla` se etiqueta «Enterado/a» para no sonar a insulto de Excel.

**Qué eliminaría o cambiaría yo (recomendación, no bloqueo)**

1. Vigilar que `tertulia` no salga demasiado en generaciones reales (tope de familia).
2. `ver_en_casa` no tiene destino: está bien (regalos/rutina). No forzarla al cine.
3. No crear catálogo de regalos todavía: hay `hooks` y `regalo` sueltos en manías. Suficiente para aprobar *esta* capa.
4. Al migrar, relajar `PersonajeValidator` de «exactamente 3 rasgos» si queremos 2–4. Eso es ficha canónica: más adelante.

---

## 8. Decisiones técnicas cerradas

- Fuente = JSON candidato, no SQL, no PHP de PLAY.
- Rechazo de destino = id `lug_*`, no un edificio duplicado.
- Estilo social = ejes, no un adjetivo único.
- Afecto estilo ≠ Relacional V1.
- Copy visible = `descubrimientos[]`, nunca el id.
- Autonomía futura: aficiones + rechazos de destino ya encajan en `LugarAutonomo` / `PlanAfinidad` cuando se migre. **Bonus numéricos no se canonizan ahora.**

---

## Cardinalidad (dirección de Producto 20/08)

Cerrado como dirección, **no como JSON de producción**:

- 3 aficiones canónicas + 3 rasgos canónicos.
- Al llegar: se conoce 1 afición y 1 rasgo. El resto, progresivo.
- Gustos, rechazos, ejes sociales, `afecto_estilo` y manías según la taxonomía. No volcar 40 atributos el día 1.
- `afecto_estilo` es identidad individual, **no** Relacional V1.

Las **entradas concretas** (42 aficiones, 28 gustos, 17 rechazos, 32 rasgos, ejes, 22 manías, muestra 20) **siguen sin canon**. No sustituyen producción. No se tocan P001–P200.

---

## BLOQUEADO_DECISION

Taxonomía, `afecto_estilo` y cardinalidad 3+3: **resueltos** por Producto.

Pendiente de Neni + ChatGPT (revisión Drive): **aprobar, recortar o reescribir las entradas concretas** de los JSON candidatos. Hasta entonces no hay migración a catálogos de PLAY.
