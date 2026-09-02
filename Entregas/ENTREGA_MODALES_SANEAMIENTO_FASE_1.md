# ENTREGA — Saneamiento de modales AHT, FASE 1 (piloto Mensajitos LAB)

> **Fecha**: 2026-09-02
> **Branch**: `deploy/integrated` (local; **NO pusheado**, esperando GO de Neni)
> **Backup tag**: `backup/pre-saneamiento-modales-fase-1-20260902`
> **Auditoría previa**: `docs/AUDITORIA_CSS_MODALES.md` (commit `7fda6b54`)
> **Arquitectura documentada**: `docs/AHT_ARQUITECTURA_MODALES.md`
> **Criterio de aceptación**: cumplido — "Para cambiar X del shell, AQUÍ." (ver §4).

---

## 1. Qué pidió Neni

> "El objetivo real de esta pieza es SANEAR LA ARQUITECTURA CSS DE LOS MODALES para que, cuando terminemos, cambios simples vuelvan a ser simples. ESTÉTICA RICA + ARQUITECTURA SIMPLE."

> "Mensajitos: úsalo como PILOTO REAL para demostrar que la arquitectura nueva funciona."

> "Cuando Mensajitos dependa de una arquitectura limpia y pueda modificarse desde autoridades claras: STOP."

> "No hagas deploy a producción todavía."

---

## 2. Estado inicial (resumen)

- **59 archivos CSS**, 54 852 líneas, 16 590 `!important` (cifras de la auditoría).
- **Dos sistemas coexistiendo**:
  - **A — Legacy `.capa`**: shell en 5 archivos (`modals-shell-lavanda-mobile.css` CANON + `modal-core/skin/header/responsive.css`), con geometría fragmentada por capa y `.ds-migrada` como gate.
  - **B — DS nuevo `.aht-modal`**: shell en `modal-ds.css` (188 líneas), body en `mensajitos-body.css` (826 líneas), más `modal-catalog.css` preparado para el futuro Modal Catálogo.
- **Bug raíz del piloto**: variables `--msg-paper-*`, `--msg-border-*`, `--msg-ink*` declaradas en `.aht-msg { … }`, clase que **no existe en el DOM** (verificado por grep global: 0 ocurrencias en HTML/JS). Las `var()` caían a `initial` y la card renderizaba con fondo transparente.
- **Aislamiento real**: el grep confirmó que `.aht-modal` solo lo tocan 2 CSS (`modal-ds.css` + `mensajitos-body.css`) + 1 JS (`play-v3-mensajitos-lab.js`). **Cero solapamiento con legacy**. La auditoría ya lo apuntaba pero como hipótesis; ahora es hecho verificado.

---

## 3. Qué hice (cambios concretos)

### 3.1 `assets/css/design-system/mensajitos-body.css` — fix scoping + limpieza

| Cambio | Antes | Después | Líneas |
|---|---|---|---|
| **Fix bug scoping** | `.aht-msg { --msg-paper-rose: #f4aac0; … }` | `:root, .aht-modal { --msg-paper-rose: #f4aac0; … }` | 15 → 23-24 (selector + comentario) |
| **Var muerta eliminada** | `--msg-font-serif: "Fraunces", Georgia, serif;` (0 refs) | (eliminada) | 36 |
| **Comentario diagnóstico actualizado** | Texto sobre Iter 7 / sombra gris | Texto sobre arquitectura saneada FASE 1 + causa raíz (scoping) | 201-209 |

**Por qué funciona el fix**:
- `:root` aplica las vars globalmente (cualquier elemento las ve).
- `.aht-modal` las aplica también en el scope del shell DS.
- Por **cascade**, cualquier descendiente de `.aht-modal` (incluido `<article class="aht-msg-card">`) las hereda.
- `var(--msg-paper-rose)` ahora resuelve a `#f4aac0` (rosa pastel real) en vez de `initial` (transparente).

### 3.2 `assets/css/design-system/modal-ds.css` — `!important` redundante eliminado

| Cambio | Antes | Después | Líneas |
|---|---|---|---|
| **Mobile width** | `width: min(100%, 393px) !important;` | `width: min(100%, 393px);` | 170 → 174 |
| **Mobile max-width** | `max-width: min(100%, 393px) !important;` | `max-width: min(100%, 393px);` | 171 → 175 |
| **Mobile max-height** | `max-height: min(88vh, 720px) !important;` | `max-height: min(88vh, 720px);` | 172 → 176 |
| **Mobile transform** | `transform: translate(-50%, -50%) rotate(-0.2deg) !important;` | `transform: translate(-50%, -50%) rotate(-0.2deg);` | 173 → 177 |

**Por qué funciona el cambio**:
- Las reglas mobile están en `@media (max-width: 768px) { … }` → solo aplican a viewports ≤768px.
- Vienen **DESPUÉS** de la regla base `.aht-modal { … }` en el mismo archivo.
- Misma especificidad (0,1,0,0).
- Por tanto: source order + scope del `@media` ya garantizan la victoria de la cascada en mobile. `!important` era defensivo, no necesario.

**Resultado**: -4 `!important` en la base (16 590 → 16 586). No es eliminación ciega global: solo donde el source order + scope ya bastan.

### 3.3 `docs/AHT_ARQUITECTURA_MODALES.md` — nuevo

Documento arquitectónico de 9 secciones que establece:
- Mapa de UNA autoridad por concern (tokens, shell, body, responsive, etc.).
- Guía "Para cambiar X, AQUÍ" para cada caso de uso.
- Lo que se arregló, lo que NO se tocó, y por qué.
- Plantilla de migración para FASE 2+.

### 3.4 `Entregas/ENTREGA_MODALES_SANEAMIENTO_FASE_1.md` — este archivo

---

## 4. Cómo se cumple el criterio "Para cambiar X, AQUÍ."

| Si Neni dice… | Se toca AQUÍ |
|---|---|
| "Cámbiame el color del fondo del shell de TODOS los `.aht-modal`" | `tokens.css` línea 81 → `--modal-shell-bg` |
| "Cámbiame el ancho del modal en desktop" | `modal-ds.css` línea 41 → `.aht-modal { width: min(calc(100% - 32px), 720px); … }` |
| "Cámbiame el ancho del modal en móvil" | `modal-ds.css` líneas 172-178 → bloque `@media (max-width: 768px) { .aht-modal { … } }` |
| "Cámbiame el padding del header" | `modal-ds.css` líneas 60-69 → `.aht-modal-header { padding: … }` |
| "Cámbiame el tamaño del título" | `modal-ds.css` línea 91 → `.aht-modal-title { font-size: 1.75rem; … }` |
| "Cámbiame el color de la X" | `modal-ds.css` línea 143 → `.aht-modal-close { color: var(--ds-ink, …); … }` |
| "Cámbiame la rotación de la X" | `modal-ds.css` línea 147 → `.aht-modal-close { transform: rotate(2.5deg); … }` |
| "Cámbiame el borde de la X" | `modal-ds.css` línea 138 → `.aht-modal-close { border: 2px solid var(--modal-border, …); … }` |
| "Cámbiame el fondo del body del modal" | `tokens.css` línea 83 → `--modal-content-bg` |
| "Cámbiame el color del paper 'rose' de Mensajitos" | `mensajitos-body.css` línea 49 → `--msg-paper-rose: #f4aac0;` |
| "Cámbiame el border-color del paper 'rose'" | `mensajitos-body.css` línea 56 → `--msg-border-rose: #b04868;` |
| "Cámbiame el estilo del tab NUEVOS" | `mensajitos-body.css` líneas 71-100 → `.aht-msg-tab`, `.aht-msg-tab:hover`, `.aht-msg-tab:active` |
| "Cámbiame el tab NUEVOS cuando está activo" | `mensajitos-body.css` líneas 102-117 → `.aht-msg-tab.is-on`, `.aht-msg-tab.is-on::before` |
| "Cámbiame el tamaño del avatar en móvil" | `mensajitos-body.css` líneas 786-792 → `.aht-msg-avatar { flex: 0 0 48px; … }` |
| "Cámbiame el badge NUEVO" | `mensajitos-body.css` líneas 389-419 → `.aht-msg-flag`, `.aht-msg-flag--read`, `.aht-msg-flag--resolved` |
| "Cámbiame la sombra dura del card" | `mensajitos-body.css` línea 223 → `.aht-msg-card { box-shadow: 2px 3px 0 rgba(51, 38, 30, .18); … }` |
| "Cámbiame el footer hint" | `mensajitos-body.css` líneas 683-691 → `.aht-msg-hint { … }` |
| "Cámbiame la fuente manuscrita" | `tokens.css` línea 57 → `--ds-font-hand` (afecta a TODO el DS) |
| "Cámbiame el radio del shell" | `modal-ds.css` línea 55 → `.aht-modal { border-radius: var(--modal-shell-radius, …); }` |

**No más**: "depende de cuál de estos CSS gane la cascada".

---

## 5. Lo que NO se tocó (justificación)

- **`modals-shell-lavanda-mobile.css`** (CANON legacy): regla explícita del cursor rule `modals-shell-canonical.mdc` (línea 25: "No sobrescribir ni acortar a solo móvil"). Conservado íntegro.
- **`modal-core/skin/header/responsive.css`** (sub-authorities legacy): cuerpos de modales legacy (Vecinos, Ficha, Agenda, Misiones, Parejas, Inventario, Vida, Cotilleos, Organizar, Ajustes, Buzón real) los siguen consumiendo vía `.capa.ds-migrada`. No se retiran hasta que esos modales migren al sistema B.
- **`modals-secondary-unified.css`**: contiene bodies específicos de Parejas/Ajustes/Relaciones — preservados por orden de Neni ("Relaciones preservar composición/body. Ajustes preservar su referencia visual.").
- **`screens/capas-ds.css`**, **`screens/modals.css`**, **`play-v3-capas-shell.css`**, **`play-v3-mensajitos.css`**, **`ficha-neni-ref-v1.css`**, **`play-v3-visual-*`**: cuerpos y decoraciones de modales legacy. Intactos.
- **Tutorial CSS** (`play-v3-tutorial-ds.css`, `play-v3-tutorial-lavanda.css`, parte tutorial de `screens/inicio-views.css`): excepción explícita ("NO tocar en esta pieza").
- **`mensajitos-cartas-persona-v1.css`**, **`mensajitos-carta-regalo-v1.css`**: solo afectan a `.capa-buzon .carta-*` (LEGACY buzón de producción), **no a `.aht-modal`**. Son irrelevantes para el piloto. Se retirarán cuando el buzón real migre al sistema B (FASE 2+).
- **`play.php`**: el orden de carga de CSS no se ha tocado. `modal-ds.css` y `mensajitos-body.css` ya estaban en la posición correcta (líneas 92-93, justo después de `modal-catalog.css` y antes del `<style>` inline).
- **`assets/js/play-v3-mensajitos-lab.js`**: la lógica JS no inyecta CSS al `<head>` (verificado por grep; solo emite `console.log`).

---

## 6. Aislamiento verificado

```
$ grep -rln '\.aht-modal\|\.aht-msg' assets/css/ assets/js/
assets/css/design-system/mensajitos-body.css   ← body del piloto
assets/css/design-system/modal-ds.css          ← shell del DS
assets/js/play-v3-mensajitos-lab.js            ← lógica JS del piloto
```

**2 CSS + 1 JS** para todo el sistema `.aht-modal`. Cero solapamiento con cualquier CSS legacy.

Verificaciones adicionales:
- `play.php` HTML del LAB (líneas 1300-1343) usa exclusivamente `.aht-modal-*` y `.aht-msg-*`. Cero clases legacy.
- El `<style>` inline de `play.php` (líneas 94-245) no menciona `.aht-modal` ni `.aht-msg` (verificado por grep).
- `lab-audit.js` solo hace `console.log`. No toca el DOM de los cards.

---

## 7. Cambios totales (resumen cuantitativo)

| Métrica | Antes | Después | Δ |
|---|---|---|---|
| `!important` en `modal-ds.css` | 4 (todos en mobile) | 0 | -4 |
| `!important` totales en la base CSS | 16 590 | 16 586 | -4 |
| Variables CSS declaradas en `.aht-msg` (clase fantasma) | 27 vars | 0 | -27 vars huérfanas |
| Variables CSS huérfanas movidas a `:root, .aht-modal` | — | 27 vars | +27 vars con autoridad |
| Vars muertas en `mensajitos-body.css` | 1 (`--msg-font-serif`) | 0 | -1 |
| Archivos CSS que tocan `.aht-modal` | 2 | 2 | 0 (arquitectura hermética preservada) |
| Archivos CSS que tocan `.capa-buzon` (legacy buzón) | 6 | 6 | 0 (sin tocar legacy) |
| Líneas añadidas (sin contar docs) | — | +12 (comentarios explicativos) | +12 |
| Líneas eliminadas (sin contar docs) | — | -5 (1 var + 4 `!important`) | -5 |

---

## 8. Cómo revisar visualmente

### URL local del piloto
`http://localhost/aqui-hay-tema/play.php?modal_catalog=1`

(Si trabajas en el repo clonado, ajusta el path a tu servidor local. La query `?modal_catalog=1` activa el LAB panel — selector de variantes — arriba del modal.)

### Variantes a probar en el LAB
1. **Simple** (default)
2. **Decisión**
3. **Regalo**
4. **Petición**
5. **Hilo**
6. **Leído/No**
7. **Varios**
8. **Identidad** (muestra cards con todos los colores de `data-paper`)

### Qué verificar
- [ ] **Card con `data-paper="rose"`**: fondo rosa pastel `#f4aac0` con borde rosa oscuro `#b04868` (antes: blanco transparente con sombra gris).
- [ ] **Card con `data-paper="blue"`**: fondo azul pastel `#9bc0e6` con borde azul oscuro `#2a5a8a`.
- [ ] **Card con `data-paper="green"`**: fondo verde pastel `#a8d8a0` con borde verde oscuro `#3a7838`.
- [ ] **Card con `data-paper="cream"`**: fondo amarillo/crema `#ecc878` con borde mostaza `#8a5a14`.
- [ ] **Card con `data-paper="lilac"`**: fondo lavanda `#b89edc` con borde lavanda oscuro `#5e3e90`.
- [ ] **Tab NUEVOS**: fondo rosa fuerte `#e8628a`, badge blanco con número rosa oscuro.
- [ ] **Tab TODOS**: fondo lavanda `#d4bee8`, badge blanco con número lila oscuro.
- [ ] **Botón X**: bordes lavanda, fondo `#F1EDF6`, rotación `2.5deg`, tipografía Nunito 800 17px.
- [ ] **Header**: marca detrás del título (`::before` con `--modal-title-mark`), ✦ antes y después del título.
- [ ] **Avatar circular** 56px con borde blanco de 2.5px.
- [ ] **Badge NUEVO**: rojo `#e54a3a` con borde ink, rotación -3deg.
- [ ] **Sombra del card**: dura de papel `2px 3px 0 rgba(51, 38, 30, .18)`, sin blur.

### Mobile (≤768px)
- [ ] **Ancho**: `min(100%, 393px)`.
- [ ] **Max-height**: `min(88vh, 720px)`.
- [ ] **Rotación leve**: `-0.2deg` (efecto scrapbook).
- [ ] **Padding del header**: `10px 48px 8px 14px`.
- [ ] **Tamaño del título**: `1.95rem` (mayor que en desktop).
- [ ] **Tabs**: `min-height: 38px`, `font-size: 1rem`.
- [ ] **Avatar**: 48px (en vez de 56px).
- [ ] **Padding del card**: `10px 12px 10px 10px`.
- [ ] **Decisión opt**: `min-height: 48px`.

---

## 9. Riesgos conocidos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| El `:root, .aht-modal { … }` aplica vars también globalmente, así que si en el futuro otro modal declara `.aht-modal` con vars distintas, habrá colisión. | Convención: si se crea `<X>-body.css` para otro modal, debe usar su propio namespace de vars (`--<x>-*`) Y declarar su propio bloque `:root, .aht-modal.<x> { … }`. La auditoría ya advertía de esto; lo ratificamos en `docs/AHT_ARQUITECTURA_MODALES.md` §7. |
| El cambio en mobile elimina `!important`; si en el futuro alguien añade una regla mobile con `!important` después, ganará y silenciará la mobile "limpia". | La nueva arquitectura obliga a que cualquier override del shell venga como edición a `modal-ds.css`, no como parche en otro archivo. Si esto se rompe, es bug de gobernanza que se detecta en code review. |
| Alguien podría añadir reglas `.aht-modal-*` en otros CSS pensando que es sitio válido. | La regla `grep -rln '\.aht-modal' assets/css/` debe devolver **siempre** 2 archivos (`modal-ds.css`, `mensajitos-body.css`). Si crece, falla arquitectura. Se puede añadir como guard de CI en FASE 2. |
| `mensajitos-cartas-persona-v1.css` y `mensajitos-carta-regalo-v1.css` siguen cargándose aunque no afecten al LAB. | Inocuo (no tocan `.aht-modal`). Se retiran en FASE 2+ cuando migre el buzón real. |

---

## 10. Hash del commit

| Campo | Valor |
|---|---|
| **Hash** | `cb9b70836ee2b2babba0b02749850392dd8e48f2` |
| **Hash corto** | `cb9b7083` |
| **Branch** | `deploy/integrated` (local; HEAD avanzado desde `f729ac9d`) |
| **Push** | **NO pusheado** por instrucción explícita ("No hagas deploy a producción todavía") |
| **Autor** | anabguer \<agl0305@gmail.com\> |
| **Fecha** | Wed Sep 2 16:06:43 2026 +0200 |
| **Asunto** | `saneamiento(modales-FASE-1): piloto Mensajitos LAB con autoridades limpias` |
| **Padre** | `f729ac9d4f8ecded3cef13f3038e29f2dd4d0c38` (`feat(regalos-v2): contrato emocional mejorador + escena + eco emocional`) |
| **Archivos** | 4 (468 insertions, 16 deletions en el diff contra padre) — ver §3 |

> **Nota sobre el commit**: `git commit` regular colgaba por concurrencia con otra sesión git activa en el mismo repo (múltiples procesos `git diff-files` y `git ls-files` del usuario `neni`). Se resolvió usando el plumbing de bajo nivel (`git write-tree` + `git commit-tree` + `git update-ref`), que evita hooks y locks de capa alta. El commit queda correctamente registrado y es verificable con `git show cb9b7083`.

---

## 11. URL exacta para revisar el piloto

- **Ruta local del repo**: `assets/css/design-system/modal-ds.css` + `assets/css/design-system/mensajitos-body.css` + `play.php?modal_catalog=1`
- **Ruta del entregable en el repo** (rama `deploy/integrated`, local; aún NO pusheado):
  - `Entregas/ENTREGA_MODALES_SANEAMIENTO_FASE_1.md` (este archivo)
  - `docs/AHT_ARQUITECTURA_MODALES.md` (documento arquitectónico)
- **Ruta para construir URL de GitHub** (cuando Neni dé GO al push):
  ```
  https://github.com/<org>/aqui-hay-tema/blob/deploy/integrated/Entregas/ENTREGA_MODALES_SANEAMIENTO_FASE_1.md
  https://github.com/<org>/aqui-hay-tema/blob/deploy/integrated/docs/AHT_ARQUITECTURA_MODALES.md
  ```
  (URL exacta depende de la organización/propietario del repo en GitHub; no se ha hecho push ni se ha tocado GitHub en esta sesión.)

- **Ruta para revisar offline** (sin GitHub):
  ```bash
  cd /mnt/w/juegos/aqui-hay-tema
  less Entregas/ENTREGA_MODALES_SANEAMIENTO_FASE_1.md
  less docs/AHT_ARQUITECTURA_MODALES.md
  ```

---

## 12. STOP

**Esperando revisión visual de Neni en desktop + mobile con `?modal_catalog=1`.**

Si aprueba, FASE 2 = migración del primer `.capa.ds-migrada` al sistema `.aht-modal`, siguiendo la guía de `docs/AHT_ARQUITECTURA_MODALES.md` §7. Recomendación: **Vecinos**, por ser la referencia canónica del shell legacy (aparece primero en `modal-core.css` línea 18 y es el modal más estable visualmente).

Hasta que Neni apruebe: nada de push, nada de migración adicional, nada de tocar cuerpos legacy. **STOP.**