# AHT — Arquitectura de modales (post-saneamiento FASE 1)

> Estado tras el saneamiento controlado de **FASE 1 — Piloto Mensajitos LAB**.
> Criterio: **UNA autoridad clara por concern**. Si mañana Neni dice "cámbiame este color",
> debe existir un único sitio donde tocarlo.

---

## TL;DR — Mapa de autoridades

| Concern | Archivo autoritativo | Por qué |
|---|---|---|
| **Tokens globales** (colores, tipografía, espaciados, papel, marca, motion, breakpoints) | `assets/css/design-system/tokens.css` | Única fuente de variables `--ds-*` y `--modal-*` (líneas 80-89). |
| **Shell del nuevo DS (`.aht-modal`)** — geometría, posición, tamaño, header, título, ✦, X, body slot, responsive mobile | `assets/css/design-system/modal-ds.css` | Único archivo que toca `.aht-modal`, `.aht-modal-overlay`, `.aht-modal-header`, `.aht-modal-title`, `.aht-modal-close`, `.aht-modal-body`. **188 líneas.** |
| **Body Mensajitos LAB** (tabs, papers, cards, avatares, flags, choices, gift, deadline, hint, LAB panel, mobile) | `assets/css/design-system/mensajitos-body.css` | Único archivo que toca `.aht-msg-*` y `.aht-modal-lab-panel`. **831 líneas.** |
| **Body Modal Catálogo** (futuro, ya preparado) | `assets/css/design-system/modal-catalog.css` | Pre-creado; sin montar todavía. |
| **Shell legacy `.capa` (vecinos, ficha, agenda, misiones, …)** — geometría de la familia `.capa.ds-migrada` | `assets/css/design-system/modals-shell-lavanda-mobile.css` (CANON) + `modal-core.css` + `modal-skin.css` + `modal-header.css` + `modal-responsive.css` | El CANON es `modals-shell-lavanda-mobile.css` (marcador `MODALS-SHELL-LAVANDA-CANON-v3`); los otros 4 son sub-authorities por capa (core/skin/header/responsive) — no se mezclan entre sí ni con `.aht-modal`. |
| **Tutorial (excepción — NO tocar)** | `play-v3-tutorial-ds.css`, `play-v3-tutorial-lavanda.css`, `screens/inicio-views.css` (parte tutorial) | Excluido explícitamente del saneamiento. |

---

## 1. Para cambiar X del shell (`.aht-modal`)

### Quiero cambiar el tamaño / posición / max-height del shell

→ **`assets/css/design-system/modal-ds.css`** sección `=== B) SHELL (frame) ===` (líneas ~36-57).

Variables del shell que viven en `tokens.css` (`--modal-shell-bg`, `--modal-shell-radius`, `--modal-shadow`) controlan la piel del shell; el resto (width, max-width, max-height, transform, flex, overflow, z-index) está en `modal-ds.css` directamente.

### Quiero cambiar el header (cabecera, padding, ✦, marca detrás del título)

→ **`assets/css/design-system/modal-ds.css`** sección `=== C) HEADER ===` (líneas ~59-156).

Cambiar `.aht-modal-header`, `.aht-modal-header::before` (marca), `.aht-modal-title`, `.aht-modal-title::before/::after` (✦), `.aht-modal-close` (X).

### Quiero cambiar la X (botón cerrar)

→ **`assets/css/design-system/modal-ds.css`** líneas ~122-156.

### Quiero cambiar el body slot (padding, scroll, fondo del body)

→ **`assets/css/design-system/modal-ds.css`** sección `=== D) BODY (slot libre) ===` (líneas ~159-165).

> El **fondo** del body viene de `var(--modal-content-bg)` definido en `tokens.css` línea 83.
> Cambiar el color del fondo del body de TODOS los `.aht-modal` → **`tokens.css` línea 83**.

### Quiero cambiar el responsive común (mobile)

→ **`assets/css/design-system/modal-ds.css`** sección `=== E) RESPONSIVE: MOBILE ===` (líneas ~167-191).

Único `@media (max-width: 768px)` para todo el shell. Las reglas vienen DESPUÉS de la base y dentro del `@media`, así que ganan la cascada sin `!important`.

---

## 2. Para cambiar el body de Mensajitos LAB

→ **`assets/css/design-system/mensajitos-body.css`** (831 líneas).

Áreas dentro del archivo:
- **Líneas 14-61**: tokens locales (`--msg-*`) declarados en `:root, .aht-modal`. Tocan la paleta del piloto.
- **Líneas 63-184**: zona de navegación (tabs NUEVOS/TODOS, marcar todos, contador).
- **Líneas 186-198**: contenedor del body y secciones.
- **Líneas 200-275**: carta (`.aht-msg-card`) con sus 5 variantes `data-paper="rose|blue|green|cream|lilac"`.
- **Líneas 277-343**: avatar circular + emoción + variantes mini.
- **Líneas 345-456**: header row, from, status row, date, flag NUEVO, read-toggle.
- **Líneas 458-541**: content, body, hilo, regalo, deadline.
- **Líneas 543-579**: persona mencionada, acciones.
- **Líneas 581-693**: decisión/choices con `.aht-msg-choice-opt`, botones CTA, hint footer.
- **Líneas 705-770**: estado vacío.
- **Líneas 712-767**: LAB panel (selector de variantes, solo visible con `?modal_catalog=1`).
- **Líneas 776-844**: responsive mobile del body.

> Si Neni dice "quiero el tab TODOS en verde" → buscar `.aht-msg-tab` en `mensajitos-body.css`. **Un único sitio**.

---

## 3. Para cambiar un token / color

→ **`assets/css/design-system/tokens.css`**.

Categorías dentro del archivo:
- **Líneas 11-49**: papel, superficies, tinta, marca (`--ds-paper-*`, `--ds-ink*`, `--ds-coral/pink/lavender/mustard*`).
- **Líneas 51-55**: emocionales canónicos.
- **Líneas 56-68**: tipografía (roles + escala AHT).
- **Líneas 70-78**: espaciado.
- **Líneas 80-89**: **piel modal común** (CANON) — `--modal-shell-bg`, `--modal-header-bg`, `--modal-content-bg`, `--modal-title-mark`, `--modal-footer-bg`, `--modal-border`, `--modal-shadow`, `--modal-overlay`.
- **Líneas 92-97**: radios.
- **Líneas 99-108**: bordes y sombras.
- **Líneas 110-119**: táctil y tamaños.
- **Líneas 121-128**: z-index.
- **Líneas 130-133**: motion.

> **Regla**: si necesitas un color que no está, **añádelo a `tokens.css`**, no en otro archivo. Es la única fuente de variables.

---

## 4. Aislamiento confirmado por grep

```
$ grep -rln '\.aht-modal\|\.aht-msg' assets/css/ assets/js/
assets/css/design-system/mensajitos-body.css   ← BODY del piloto
assets/css/design-system/modal-ds.css          ← SHELL del DS nuevo
assets/js/play-v3-mensajitos-lab.js            ← LÓGICA JS del piloto
```

**Solo 2 CSS** tocan el sistema `.aht-modal`:
1. `modal-ds.css` (shell) — geometría, header, X, body slot, responsive.
2. `mensajitos-body.css` (body) — tokens locales, tabs, papers, cards, flags, choices, lab panel.

**Ningún CSS legacy** (`play-v3-*.css`, `modals-secondary-unified.css`, `screens/capas-ds.css`, `modals-shell-lavanda-mobile.css`, `play-v3-mensajitos.css`, `ficha-neni-ref-v1.css`, etc.) escribe **ninguna** propiedad que afecte a `.aht-modal`. Aislado de raíz.

---

## 5. Lo que el saneamiento arregló (FASE 1)

### Bug raíz del piloto: scoping de variables
- **Antes**: variables `--msg-paper-*`, `--msg-border-*`, `--msg-ink*` declaradas en `.aht-msg { … }` (clase inexistente en el DOM).
- **Después**: declaradas en `:root, .aht-modal { … }`. Cualquier descendiente de `.aht-modal` las hereda por cascada.
- **Efecto**: `<article class="aht-msg-card" data-paper="rose">` ahora recibe `background: var(--msg-paper-rose)` = `#f4aac0` real (rosa pastel) en vez de `transparent`.

### Reducción de `!important` redundante
- **Antes**: 4 `!important` en `modal-ds.css` líneas 170-174 (mobile width/max-width/max-height/transform).
- **Después**: 0 `!important` ahí. Source order + `@media` ya garantizan la victoria de la cascada en mobile.
- **Total**: -4 `!important` en la base (16 590 → 16 586).

### Var muerta eliminada
- `--msg-font-serif: "Fraunces", Georgia, serif;` declarada pero **nunca referenciada** en el archivo (0 usos).
- Eliminada. -1 declaración muerta.

### Documentación
- Este archivo (`docs/AHT_ARQUITECTURA_MODALES.md`) establece el mapa "ONE place per concern".
- Comentarios in-file en `mensajitos-body.css` y `modal-ds.css` documentan el "por qué" del cambio.

---

## 6. Lo que NO se tocó (deliberadamente)

- **Tutorial**: `play-v3-tutorial-ds.css`, `play-v3-tutorial-lavanda.css`, parte tutorial de `screens/inicio-views.css`. Excepción explícita de Neni.
- **Relaciones (body)**: las reglas de `.capa-vecinos.is-relaciones`, `.vec-rel-*`, `.capa-ficha .ficha-rel-*` permanecen donde están (en `modals-secondary-unified.css`). Solo se sanea el shell que las envuelve.
- **Ajustes (body)**: `.capa-ajustes .ajustes-link`, `.ajustes-grupo`, `.ajustes-reiniciar`, `.ajustes-diag-btn` permanecen en `modals-secondary-unified.css`.
- **Bodies de modales legacy** (Vecinos, Ficha, Misiones, Parejas, Inventario, Vida, Agenda, Cotilleos, Organizar, Buzón real): intactos. Solo el shell `.capa.ds-migrada` ya saneado en `modals-shell-lavanda-mobile.css` (CANON) los gobierna.
- **`mensajitos-cartas-persona-v1.css` y `mensajitos-carta-regalo-v1.css`**: **NO tocados**. Solo afectan a `.capa-buzon .carta-*` (LEGACY buzón de producción), no a `.aht-modal`. Son irrelevantes para el piloto y se retirarán cuando el buzón real migre al sistema B (próxima fase, post-Neni).
- **`modals-shell-lavanda-mobile.css`** (CANON legacy): **NO tocado**. Regla explícita del cursor rule `modals-shell-canonical.mdc` ("no sobrescribir ni acortar a solo móvil").
- **`modals-secondary-unified.css`**: **NO tocado**. Contiene bodies específicos de Parejas/Ajustes/Relaciones que hay que preservar.

---

## 7. Cómo migrar el siguiente modal al sistema B (guía para FASE 2+)

Cuando Neni apruebe FASE 1, la migración de los siguientes modales al sistema `.aht-modal` sigue este patrón:

1. **Identificar el body**: ¿qué archivo CSS actual define las clases internas del modal? (ej. `play-v3-vecinos.css` para Vecinos).
2. **Adaptar la raíz del HTML** en `play.php` (o donde esté):
   - Sustituir `.capa-vecinos.ds-migrada` por `.aht-modal.vecinos` (o similar namespace).
   - Mover el header viejo (`.vecinos-cab .ds-modal-tit`) a `<header class="aht-modal-header"><h2 class="aht-modal-title">Vecinos</h2></header>`.
   - El body contenedor pasa a `<div class="aht-modal-body">…</div>`.
3. **Crear `<X>-body.css`** (ej. `vecinos-body.css`) siguiendo el patrón de `mensajitos-body.css`:
   - Tokens locales en `:root, .aht-modal.vecinos { --vec-* … }` (scope por namespace para no chocar con otros modales).
   - Resto: clases internas del body.
4. **Cablear en `play.php`**: añadir el `<link>` al `<X>-body.css` justo después de `modal-ds.css`.
5. **Borrar del CSS legacy** las reglas que ahora son redundantes para ese modal. **En el mismo commit** (regla de la auditoría).
6. **Verificar** que el modal sigue funcionando en desktop + mobile.

> Este proceso se replica para cada modal. Cuando todos estén migrados, se podrán borrar los 4 sub-files legacy (`modal-core/skin/header/responsive`) — pero eso queda lejos.

---

## 8. Verificación

Comprobaciones que pasan tras FASE 1:
- [x] `grep -rln '\.aht-modal' assets/css/` → 2 archivos (`modal-ds.css`, `mensajitos-body.css`). Arquitectura hermética.
- [x] `mensajitos-body.css:23-61` → `:root, .aht-modal { … }` resuelve el bug de scoping.
- [x] `modal-ds.css:172-191` → mobile sin `!important` redundante, cascade correcta por source order.
- [x] `--msg-font-serif` eliminado (no estaba referenciado).
- [x] `tokens.css` sigue siendo la única fuente de variables (no se ha añadido ninguna nueva).
- [x] Ningún archivo de bodies legacy (`modals-secondary-unified.css`, `screens/capas-ds.css`, `play-v3-X.css`) se ha tocado.
- [x] Ningún archivo de tutorial se ha tocado.
- [x] `modals-shell-lavanda-mobile.css` (CANON) intacto.

---

## 9. STOP

Esperando revisión visual de Neni en desktop + mobile con `?modal_catalog=1`.

Si lo aprueba, FASE 2 será la migración del primer `.capa.ds-migrada` (recomendado: **Vecinos**, por ser la referencia canónica) al sistema `.aht-modal`, siguiendo la guía de §7.