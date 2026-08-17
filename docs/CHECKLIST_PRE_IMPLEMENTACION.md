# Checklist pre-implementación

Impedir empezar una **feature importante** sin criterios mínimos claros.

Usar junto con `REGLAS_NO_NEGOCIABLES.md`. **No sustituye** content gate post-implementación.

---

## Cuándo usar este checklist

**Obligatorio** antes de programar:

- buzón, diario, citas, agenda, autonomía NPC
- compatibilidad, relaciones, marchas, llegadas
- mapa/lugares operativos, desbloqueos
- economía/fama jugables
- persistencia partida / seed
- cualquier pantalla de partida (El pueblo, mapa, etc.)

**Opcional** para cambios triviales (texto landing, typos en docs).

---

## A. Checklist general (copiar por feature)

Marcar **SÍ / NO / N/A**. Si algún **SÍ obligatorio** falta → **no implementar** todavía.

### 1. Definición

| # | Pregunta | OK |
| --- | --- | --- |
| A1 | ¿Objetivo jugable en 1–2 frases? | ☐ |
| A2 | ¿Documento fuente citado (`DECISIONES`, propuesta, contrato)? | ☐ |
| A3 | ¿Qué ve el jugador vs qué es interno? | ☐ |
| A4 | ¿Interacción con otros sistemas listada? | ☐ |
| A5 | ¿Qué podría romper (edge cases)? | ☐ |

### 2. Reglas y datos

| # | Pregunta | OK |
| --- | --- | --- |
| B1 | ¿Reglas de entrada/salida escritas? | ☐ |
| B2 | ¿Campos de ficha/runtime identificados? | ☐ |
| B3 | ¿Invariantes (lo que nunca debe pasar)? | ☐ |
| B4 | ¿Separación motor / datos / estado / narrativa clara? | ☐ |

### 3. Contenido (antes de codear)

| # | Pregunta | OK |
| --- | --- | --- |
| C1 | ¿Catálogos afectados listados? | ☐ |
| C2 | ¿Mínimo de contenido **definido** o `RECORTE_V0` documentado? | ☐ |
| C3 | ¿Placeholders identificados y marcados como tales? | ☐ |
| C4 | ¿Criterio de variedad semántica (no solo N frases)? | ☐ |

### 4. Alcance V0

| # | Pregunta | OK |
| --- | --- | --- |
| D1 | ¿Qué entra en V0 vs post-V0? | ☐ |
| D2 | Si es recorte, ¿`RECORTE_V0` con impacto y aprobación si aplica? | ☐ |
| D3 | ¿V0 sigue siendo **probable** (no raquítico)? | ☐ |

### 5. Prueba

| # | Pregunta | OK |
| --- | --- | --- |
| E1 | ¿Cómo probar gate **técnico**? | ☐ |
| E2 | ¿Cómo probar gate **contenido** (sesión de prueba concreta)? | ☐ |
| E3 | ¿Semilla/config de debug definida si aplica? | ☐ |

### 6. Estados

| # | Pregunta | OK |
| --- | --- | --- |
| F1 | ¿Estado de contenido inicial asignado (`diseñado`, etc.)? | ☐ |
| F2 | ¿Criterio para pasar a `listo_para_auditoría`? | ☐ |

**Firma propuesta:** Cursor rellena · **Neni+ChatGPT** validan antes de Fase H.

---

## B. Content gate (post-implementación, pre-producción)

Usar cuando el gate técnico ya pasa pero antes de `aprobado`.

| # | Criterio | ☐ |
| --- | --- | --- |
| G1 | Mínimos de catálogo cumplidos **o** `RECORTE_V0` aprobado | |
| G2 | Sin placeholders sin marcar en flujos jugables | |
| G3 | Variedad semántica spot-check (no clones obvios) | |
| G4 | Tono Aquí Hay Tema (cutre, no corporativo) | |
| G5 | Texto no afirma hechos que el motor no sostiene | |
| G6 | No duplica otro canal (diario vs buzón vs ficha) sin regla | |
| G7 | Sesión test 30–60 min sin repetición **evidente** | |

---

## C. Anexos por sistema (rellenar al implementar)

Mínimos **a definir** en diseño; no inventar aquí cifras globales.

### Buzón (`BUZON_DE_CELESTINE.md`)

| Campo | Valor |
| --- | --- |
| Doc fuente | |
| Tipos V0 | bienvenida, petición, problema, respuesta, descubrimiento, aviso_comunidad |
| mínimo_producción | _pendiente definir_ |
| mínimo_V0 | _pendiente definir_ |
| RECORTE_V0 | _si aplica_ |
| Prueba contenido | _ej.: 3 días simulados, 6 tipos, 3 voces_ |

### Citas / encuentros

| Campo | Valor |
| --- | --- |
| Doc fuente | `RELACIONES_Y_CITAS.md`, `ENCUENTROS_ESPONTANEOS.md` |
| mínimo_producción | _referencia diseño: banco 25–40 microeventos_ |
| mínimo_V0 | _pendiente definir_ |
| Motivos rechazo por `voz` | _pendiente definir_ |

### Diario

| Campo | Valor |
| --- | --- |
| Doc fuente | `DIARIO_DEL_PUEBLO.md` |
| Cupo | 5–10/día (cerrado) |
| mínimo variantes por tipo | _pendiente definir_ |

### Agenda

| Campo | Valor |
| --- | --- |
| Doc fuente | `PLANTILLAS_AGENDA_BORRADOR.md`, `PROPUESTA_AGENDA_*` |
| Plantillas ocupación | _ver borrador_ |
| mínimo excepciones por ficha | 0–3 `compromisos_recurrentes` |

### Personajes (pool)

| Campo | Valor |
| --- | --- |
| Doc fuente | `CONTRATO_PERSONAJE.md`, `CHECKLIST_AUDITORIA_PERSONAJES.md` |
| Tanda 1 | 16 fichas auditadas |
| Anti-clon visual | 06b + diferenciación estructural |
| `interes_romantico: ninguno` | 2 en tanda |

### Coletillas

| Campo | Valor |
| --- | --- |
| Doc fuente | `COLETILLAS.md` |
| mínimo_producción | _pendiente definir_ |

---

## D. Plantilla de ficha de feature

```markdown
# Feature: [nombre]

## Objetivo jugable
…

## Documentos
- …

## Reglas
…

## Datos
- ficha: …
- runtime: …
- catálogos: …

## Contenido
- catálogos afectados: …
- mínimo_V0: … (o RECORTE_V0 enlace)
- placeholders permitidos: …

## V0 / post-V0
…

## Prueba
- técnica: …
- contenido: …

## Riesgos
…

## Estado contenido
concepto | diseñado | contenido_parcial | …
```

---

## E. Regla rápida

> Si no puedes rellenar **C1–C4** y **D1–D3**, la feature sigue en **diseño**, no en **implementación**.

*Complemento de `REGLAS_NO_NEGOCIABLES.md`. Actualizar anexos §C cuando se cifren mínimos por sistema.*
