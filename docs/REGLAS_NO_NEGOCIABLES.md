# Reglas no negociables — Aquí Hay Tema

**Contrato permanente de calidad.** Aplica a diseño, contenido, implementación y producción.

**Objetivo:** impedir que un sistema **técnicamente funcional** salga como **terminado** cuando el contenido es mínimo, repetitivo o recortado respecto al diseño.

Referencias: `DECISIONES_CERRADAS.md` · `ESTADO_PROYECTO.md` · `CHECKLIST_PRE_IMPLEMENTACION.md`

---

## 1. Dos gates obligatorios

Ningún sistema se declara **terminado** sin pasar **ambos** gates.

### Gate técnico

- Funciona según las reglas documentadas.
- No corrompe estado de partida.
- Es reproducible / testeable.
- Respeta límites (aforo, agenda, conocimiento progresivo, etc.).

### Gate de contenido / experiencia

- Variedad **suficiente** (ver §3 y §5).
- No repite de forma evidente en sesiones normales.
- Tono cutre/simpático del juego, no genérico corporativo.
- Cubre las situaciones previstas en diseño.
- **No depende** de placeholders sin marcar.
- Produce la experiencia que el documento de diseño describe.

**Regla:** un sistema puede estar `técnicamente_funcional` y seguir **INCOMPLETO** por contenido hasta pasar el gate de experiencia.

---

## 2. Prohibido recortar contenido en silencio

Si el diseño habla de «muchas variantes», «varios motivos», «variedad de…», **Cursor no puede** sustituirlo silenciosamente por 2–5 ejemplos genéricos para cerrar la implementación.

### Si hay que reducir alcance (V0 u otro)

Marcar explícitamente **`RECORTE_V0`** y documentar:

| Campo | Qué incluir |
| --- | --- |
| Diseño previsto | Qué decía el doc original |
| Implementado | Qué hay de verdad |
| Pendiente | Qué falta |
| Motivo | Por qué se recorta |
| Impacto en experiencia | Bajo / medio / alto |

- **Impacto bajo:** puede decidir Cursor + anotar.
- **Impacto medio o alto:** requiere **Neni + ChatGPT**.

Sin registro `RECORTE_V0` = recorte **prohibido**.

---

## 3. «Mucho / varios / bastantes» → cifra antes de cerrar

Durante diseño está bien hablar en cualitativo. **Antes de cerrar un catálogo de producción** debe existir un **mínimo verificable y auditable**.

Ejemplos de lo que habrá que cifrar **por sistema** (no ahora):

- variantes narrativas por tipo de evento
- respuestas de rechazo por `voz`
- mensajes de buzón por tipo
- hobbies / rasgos en pool
- microeventos de cita
- motivos de conflicto
- coletillas
- acontecimientos autónomos NPC

### Regla central

> **NINGÚN CATÁLOGO DE PRODUCCIÓN SE CIERRA SIN UN MÍNIMO DE CONTENIDO DEFINIDO Y AUDITABLE.**

Las cifras se fijan al diseñar o implementar **cada** catálogo, en su doc o en una fila de `RECORTE_V0` / ficha de sistema. No inventar números globales únicos para todo el juego en este documento.

Plantilla mínima por catálogo:

```text
catálogo: [id]
doc_fuente: [ruta]
mínimo_producción: [n]  ← definir antes de cerrar
mínimo_V0: [n] o RECORTE_V0
criterio_variedad: [1 línea]
estado_contenido: [ver §12]
```

---

## 4. Placeholders no cuentan como contenido final

Durante desarrollo puede existir:

- «Mensaje 1»
- «Hola, quiero una cita»
- «Evento genérico»
- «Texto pendiente»

Debe marcarse **`placeholder: true`** (o estado equivalente en datos).

**Ningún placeholder entra en auditoría de mínimos** ni en el gate de contenido.

---

## 5. Variedad real, no solo cantidad

100 frases casi iguales **no** cumplen el gate.

La auditoría debe detectar:

- duplicados literales o near-duplicates
- misma estructura cambiando solo el nombre
- misma reacción para todas las `voz`
- eventos con un solo resultado narrativo
- hobbies/rasgos redundantes en el pool
- personajes con misma voz / mismo arco

La variedad es **semántica**, no solo numérica.

---

## 6. Sistemas narrativos — contrato mínimo

Todo sistema narrativo futuro separa al menos:

| Pieza | Descripción |
| --- | --- |
| Condición de entrada | Cuándo puede dispararse |
| Datos requeridos | Qué debe existir en ficha/runtime |
| Variantes posibles | Qué textos/resultados hay |
| Voz / personaje | Cómo cambia el tono |
| Consecuencia | Qué cambia en estado |
| Cooldown / repetición | Si aplica |
| Revela | Qué puede mostrar al jugador |
| Oculta | Qué no puede afirmar |

**Regla de verdad:** el texto **nunca** crea un hecho que el motor no sostiene.

Ejemplo: `pedir_encuentro` no dispara si el objetivo no existe, no es conocido cuando la regla lo exige, o está bloqueado por condición dura.

---

## 7. Trazabilidad

Antes de implementar una feature importante debe poder responderse:

1. ¿Qué documento la define?
2. ¿Qué datos necesita?
3. ¿Qué evento la dispara?
4. ¿Qué cambia en el estado?
5. ¿Cómo se prueba?
6. ¿Qué contenido necesita (mínimos)?
7. ¿Qué queda pendiente?

Si no hay respuesta → es **IDEA**, no feature lista. Usar `CHECKLIST_PRE_IMPLEMENTACION.md`.

---

## 8. Content gate antes de producción

Antes de declarar **lista para producción** cualquier parte del juego, revisión específica de contenido (además del gate técnico).

| Área | Qué mirar |
| --- | --- |
| **Buzón** | variedad por tipo, voz, repetición, condiciones lógicas |
| **Citas** | resultados distintos, microeventos, respuestas, consecuencias |
| **Personajes** | auditoría individual + global, anti-clon (visual y narrativo) |
| **Autonomía NPC** | variedad de acciones, frecuencia, anti-loop |
| **Lugares** | cada sitio operativo aporta jugabilidad real |
| **Agenda** | plantillas distintas, no 16 calendarios clone |
| **Diario** | mezcla pista/ruido/aviso, no spam duplicado con buzón |

Checklist detallada: `CHECKLIST_PRE_IMPLEMENTACION.md` § Content gate.

---

## 9. Motor ≠ datos ≠ narrativa

Contenido ampliable (mensajes, eventos, hobbies, coletillas, personajes, lugares, planes…) debe vivir en **datos/catálogos** cuando sea razonable, no hardcodeado masivo en código.

Separación arquitectónica obligatoria:

```text
MOTOR      → reglas, resolución, invariantes
DATOS      → catálogos, plantillas, pesos
ESTADO     → partida, relaciones, agenda runtime
NARRATIVA  → textos, variantes, eventos concretos
```

No fijar aquí SQL/JSON exacto; sí exigir esta separación al diseñar implementación.

---

## 10. V0 ≠ raquítico

V0 puede tener **menos sistemas**.

Los sistemas que **sí entran** deben llevar contenido suficiente para probar si son divertidos.

Preferir: **3 sistemas con variedad** antes que **12 sistemas con 2 ejemplos cada uno**.

Alineado con `MVP.md` y decisiones Fase 1 (buzón pequeño pero real, no stub único).

---

## 11. Estados de contenido (reutilizables)

Estados simples por **sistema** o **catálogo** (no burocracia por frase suelta):

| Estado | Significado |
| --- | --- |
| `concepto` | Idea; sin reglas ni datos |
| `diseñado` | Reglas y doc; sin contenido escrito |
| `contenido_parcial` | Hay material pero no alcanza mínimo |
| `técnicamente_funcional` | Gate técnico OK; contenido puede faltar |
| `listo_para_auditoría` | Cree cumplir mínimos; pendiente revisar |
| `aprobado` | Pasó gate técnico **y** gate contenido |

**Prohibido** saltar de `técnicamente_funcional` a `aprobado` sin content gate.

Estados de **ficha personaje** (`borrador` / `revision` / `aprobado`) siguen en `CONTRATO_PERSONAJE.md` — capa distinta.

---

## 12. RECORTE_V0 — plantilla

```markdown
## RECORTE_V0 — [sistema/catálogo]

- **Diseño previsto:** …
- **Implementado en V0:** …
- **Pendiente post-V0:** …
- **Motivo:** …
- **Impacto experiencia:** bajo | medio | alto
- **Aprobado por:** Cursor | Neni+ChatGPT (obligatorio si medio/alto)
- **Fecha:** …
```

Guardar en el doc del sistema o en `PENDIENTES_DISENO.md`.

---

## 13. Quién hace qué

| Acción | Quién |
| --- | --- |
| Definir mínimos de catálogo | Diseño (Neni + ChatGPT + Cursor doc) |
| Marcar RECORTE_V0 | Cursor propone; Neni+ChatGPT si impacto ≥ medio |
| Content gate final | Neni (experiencia) + checklist Cursor |
| Declarar `aprobado` contenido | Neni |
| Implementar sin checklist | **Prohibido** para features importantes |

---

## 14. Relación con otras fases

- **Antes de Fase C (pilotos):** contrato personaje + auditoría personajes.
- **Antes de implementación (Fase H):** `CHECKLIST_PRE_IMPLEMENTACION.md` por feature.
- **Antes de producción pública:** content gate §8 + mínimos §3 cumplidos o `RECORTE_V0` aprobado.

*Documento vivo. No sustituye decisiones de diseño en `DECISIONES_CERRADAS.md`.*
