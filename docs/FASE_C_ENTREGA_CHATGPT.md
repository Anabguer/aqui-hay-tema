# Fase C — Entrega para ChatGPT (pilotos micro-sim)

**Objetivo:** validar el **molde** de personaje antes de las 16 fichas. **No** es producción masiva.

**Estado Fase B:** esqueleto **aprobado** · config micro-sim **α** · agenda base aprobada.

---

## Qué debe entregar ChatGPT

**Formato:** `CONTRATO_PERSONAJE.md` — bloques **A + B** por personaje, en archivos markdown separados o un solo archivo con cabeceras claras.

**Orden de entrega:**

1. **I02** (camarero noche) — ficha completa A+B
2. **I10** (jubilada sin romance) — ficha completa A+B
3. **I06** (oficina, roce con I02) — ficha completa A+B — *piloto 4 recomendado por Cursor*

**Rocío (`per_i03`):** **NO reescribir.** Cursor actualiza solo `slot: I08`. ChatGPT no entrega ficha Rocío salvo que Neni pida revisión puntual.

---

## Brief I02 — slot cerrado (creatividad dentro del molde)

| Campo | Valor obligatorio |
| --- | --- |
| `slot` | `I02` |
| `id` | `per_i02` (o proponer; Cursor confirma) |
| Banda edad | 22–29 |
| `genero` | `hombre` |
| `atraido_por` | `["mujer"]` |
| `interes_romantico` | `activo` |
| `ocupacion` | `camarero` |
| `franja_disponibilidad` | `noche` |
| `estilo_social` | `fiestero` |
| `hobby_principal` | `copas` (bar 🔒 — deseo futuro) |
| `empieza_en_pueblo` | `true` |
| `piloto` | `true` |

**Agenda:** plantilla `camarero` (`PLANTILLAS_AGENDA_BORRADOR.md`). **0–3 `compromisos_recurrentes`** — solo si aportan carácter; puede ser **0**.

**Grafo (A):** recíproco potencial con al menos una mujer del pool futuro (slots, no nombres). Rocío (I08) puede ser crush/recíproco **potencial** documentado por slot.

**Visual:** estilo **06b**, diferenciación estructural obligatoria. `rasgos_fisicos` 3–8 para cabeza maestra.

**Tono:** cutre/simpático; no didáctico. **B** sin secretos de A.

---

## Brief I10 — slot cerrado

| Campo | Valor obligatorio |
| --- | --- |
| `slot` | `I10` |
| `id` | `per_i10` |
| Banda edad | 60–72 |
| `genero` | `mujer` |
| `interes_romantico` | **`ninguno`** |
| `atraido_por` | `[]` o omitir según contrato — Cursor validará |
| `ocupacion` | `jubilado` |
| `franja_disponibilidad` | `manana` |
| `estilo_social` | `casero` |
| `hobby_principal` | `cocina` o `manualidades` o `pasear` |
| `empieza_en_pueblo` | `true` |
| `piloto` | `true` |

**Crítico:** arco de **amistad / vínculo / bienestar** con valor propio. **No** personaje incompleto ni puzzle a arreglar. **No** diseñar como lección didáctica «no quiere romance».

**Agenda:** sin bloque laboral; **0–2** compromisos recurrentes máximo si encajan (no rellenar por rellenar).

**Grafo:** excluida de recíproco romántico; sí lazos amistad (slots).

---

## Brief I06 — piloto 4 (roce / tensión)

| Campo | Valor obligatorio |
| --- | --- |
| `slot` | `I06` |
| `id` | `per_i06` |
| Banda edad | 30–44 |
| `genero` | `hombre` |
| `atraido_por` | `["hombre", "mujer"]` |
| `interes_romantico` | `activo` |
| `ocupacion` | `oficina` |
| `franja_disponibilidad` | `manana` |
| `estilo_social` | `tranquilo` |
| `hobby_principal` | `cine` (🔒) |
| `empieza_en_pueblo` | `true` |
| `piloto` | `true` |

**Lazo obligatorio en A:** `roce` con slot **I02** (recíproco en ambas fichas cuando existan). Tensión social, no villain de cartón.

**Función micro-sim:** relación negativa inicial, horario **mañana** vs I02 **noche**, propuesta de encuentro cruzando franjas.

---

## Reglas de entrega (recordatorio)

1. Un personaje = **A** luego **B** (`CONTRATO_PERSONAJE.md` §8).
2. Lazos a no escritos → **slot**, no nombre.
3. En **B**: cero crush, dealbreakers, `atraido_por` fino, secretos.
4. `frase_semilla` pública, sin spoilers.
5. `plasticidad_preferencias` obligatoria en A (fichas nuevas).
6. Al menos un matiz oculto (§3 regla matiz): crush, dealbreaker, rasgo oculto, roce, pref oculta…
7. **`compromisos_recurrentes`:** 0–3; variedad real (ver aprobación agenda Fase B).
8. No biografías largas. No escenas.

---

## Qué hace Cursor después de cada entrega

1. Validación estructural (`CONTRATO_PERSONAJE.md`).
2. Auditoría nivel 1 (`CHECKLIST_AUDITORIA_PERSONAJES.md`).
3. Mapeo a JSON (`_plantilla.personaje.json`).
4. Registro en `REGISTRO_AUDITORIA_PERSONAJES.md`.
5. Retrato borrador **06b** cuando toque — **después** de validación A; Neni ve **solo B + imagen**.

**No** generar las 16 · **no** programar · **no** regenerar Rocío todavía.

---

## Micro-sim α (Fase D) — cast

| Personaje | Slot | Rol en sim |
| --- | --- | --- |
| Rocío | I08 | Hetero tarde, cafetería, día 1 real fijo |
| I02 | I02 | Romance posible, noche, config α |
| I10 | I10 | Sin romance, amistad — **solo micro-sim**, no día 1 cerrado |
| I06 | I06 | Roce, mañana, tensión extra |

**Día 1 real del juego:** Rocío sí · B y C **pendientes** (I10 no cerrado como habitante inicial).

---

## Checklist antes de enviar a Cursor

- [ ] Tres entregas A+B (I02, I10, I06)
- [ ] Enums del contrato respetados
- [ ] I10 con `interes_romantico: ninguno` bien jugado
- [ ] Roce I02↔I06 en lazos A
- [ ] Compromisos 0–3 sin relleno artificial
- [ ] B limpio para Neni

*Documento de trabajo Fase C. Referencias: `FASE_B_ESQUELETO_REVISADO.md`, `REGLAS_NO_NEGOCIABLES.md`.*
