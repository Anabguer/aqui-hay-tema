# AHT — Auditoría estructural CSS / Modales (FASE 1)

> **Modo:** lectura. 0 archivos modificados. 0 commits. 0 push. Solo diagnóstico.

---

## 1. VEREDICTO

**C. Problema global de arquitectura CSS** con un caso local que **parece** bug de cascada pero es **bug de scoping de variables**.

Mensajitos LAB no tiene reglas "rivales" que le ganan. **El problema NO está en la cascada** — está en que **las CSS custom properties que el archivo declara nunca se materializan en el DOM**. La card renderiza con `background: transparent`, no con rosa pastel, porque `--msg-paper-rose` no existe en el árbol heredado. La "sombra gris" que ves es la única propiedad que **sí** está declarada con valor literal.

El sistema general **sí** tiene la enfermedad del parcheo en cascada (medible: 16 590 `!important` en 54 852 líneas CSS, 59 archivos, dos sistemas modales coexistiendo con clases distintas `.capa` y `.aht-modal`). Pero el síntoma concreto de Mensajitos no es por eso, sino por una decisión de scoping `.aht-msg` que no aterrizó.

---

## 2. CAUSA EXACTA DE POR QUÉ ITER 6 NO SE VE COMO DECLARA SU CSS

### Card renderizada (DOM real de `?modal_catalog=1`)

```
<aside.aht-modal-overlay#msgLabOverlay>
  ├─ <div.aht-modal-lab-panel>               ← solo visible en lab
  └─ <div.aht-modal role="dialog">
       ├─ <header.aht-modal-header>
       ├─ <nav.aht-msg-nav>                   ← tabs
       ├─ <div.aht-modal-body>
       │    └─ <div#msgLabList.aht-msg-list>
       │         └─ <div.aht-msg-section>
       │              └─ <article.aht-msg-card  data-paper="rose" data-unread="true" ...>
       └─ <div.aht-msg-hint>
```

### Reglas que aplican a `.aht-msg-card` (auditoría exhaustiva)

`grep` en los 59 CSS del repo: **la clase `.aht-msg-card` solo está definida en UN archivo**, `assets/css/design-system/mensajitos-body.css`. No hay ni un solo selector `.aht-msg-card`, `.aht-msg-list`, `.aht-modal`, `.aht-modal-body`, `.aht-modal-overlay` en ningún otro CSS (verificado: `play-v3-mensajitos.css`, `play-v3-responsive.css`, `modals-secondary-unified.css`, `modals-shell-lavanda-mobile.css`, `modal-skin.css`, `modal-header.css`, `modal-responsive.css`, `capas-ds.css`, `modals.css`, `legibilidad-global.css`, `play-v3-inicio-override.css`, `play-v3-visual-*`, `screens/*`, `components.css`, `tokens.css` — **0 coincidencias**).

Tampoco hay selectores globales (`article`, `.card`, `.modal`, `[role="dialog"]`) que afecten al LAB.

**Por tanto no hay cascada rival. La cascada está limpia.**

### Pero las variables no resuelven

`assets/css/design-system/mensajitos-body.css:15-53`:

```css
.aht-msg {
  --msg-ink: var(--ds-ink, #3b3028);
  --msg-ink-soft: var(--ds-ink-soft, #594839);
  ...
  --msg-paper-rose:    #f4aac0;   /* valor literal */
  --msg-paper-blue:    #9bc0e6;
  --msg-paper-green:   #a8d8a0;
  --msg-paper-cream:   #ecc878;
  --msg-paper-lilac:   #b89edc;
  --msg-border-rose:   #b04868;
  --msg-border-blue:   #2a5a8a;
  --msg-border-green:  #3a7838;
  --msg-border-cream:  #8a5a14;
  --msg-border-lilac:  #5e3e90;
}
```

**Búsqueda global del literal `class="aht-msg"` / `className="aht-msg"` / `classList.add('aht-msg')` en `play.php`, `index.php`, `dev/`, `assets/js/`, `src/`, `scripts/`, `tests/`: 0 resultados.**

Únicas clases `aht-msg*` en el HTML/JS son las **compuestas** (`aht-msg-card`, `aht-msg-list`, `aht-msg-section`, `aht-msg-nav`, `aht-msg-tab`, `aht-msg-tab-count`, `aht-msg-mark-all`, `aht-msg-hint`, `aht-msg-thread`, `aht-msg-avatar`, `aht-msg-person`, `aht-msg-gift`, `aht-msg-body`, `aht-msg-from`, `aht-msg-deadline`, `aht-msg-flag`, `aht-msg-read-toggle`, `aht-msg-choice`, `aht-msg-empty`, `aht-msg-btn`, `aht-msg-status`, `aht-msg-content`).

**El selector raíz `.aht-msg` nunca aterriza en ningún elemento.**

Resultado CSS real sobre `<article class="aht-msg-card" data-paper="rose">`:

| Propiedad (línea `mensajitos-body.css`) | Valor declarado | Resuelve a |
|---|---|---|
| `background: var(--msg-paper-rose)` (239) | `--msg-paper-rose` indefinido | `background: initial` = `transparent` |
| `border-color: var(--msg-border-rose)` (240) | `--msg-border-rose` indefinido | `border-color: initial` = `currentColor` |
| `box-shadow: 2px 3px 0 rgba(51,38,30,.18)` (223) | literal, **sin variable** | se aplica tal cual: marrón 18%, 0 blur |
| `border: 2px solid var(--msg-border-soft)` (221) | `--msg-border-soft` indefinido | borde sí existe (2px) pero color = `currentColor` |
| `font: 700 1.4rem ... var(--msg-font-hand)` (349) | `--msg-font-hand` indefinido | `var(--msg-font-hand)` fallback solo si se declaró segundo arg, aquí NO → `font-family: initial` |
| `color: var(--msg-ink)` (350) | `--msg-ink` indefinido | `color: initial` = negro |

**Por qué se ve "casi blanco con sombra gris":**

- `.aht-msg-card` → fondo transparente.
- Padre `.aht-msg-section` → `background: #FFFFFF` (línea 196).
- Padre `.aht-msg-list` → `background-color: #FAFBFE` (línea 187).
- Card se ve blanca porque se monta sobre blanco. **El color no se está "ganando" — nunca existió.**
- La sombra que parece gris es `rgba(51,38,30,.18)` a 2/3 px offset sin blur. Marrón tinta al 18 % sobre blanco lee como gris suave a tamaño pequeño.
- El "Iter 7" que el comentario del archivo (líneas 201-209) presume haber arreglado la sombra gris **no aplica**, porque esa iteración trabajó sobre la hipótesis equivocada (sobra de blur sobre el fondo). El problema raíz está más arriba: las tarjetas nunca llegan a tener fondo asignado.

El comentario del propio archivo es una pista forense del parcheo fallido:

> `mensajitos-body.css:201-209` — *"FIX: SOLO sombra dura de papel (efecto 3D plano), CERO blur."*

Se ajustó el síntoma (la sombra) sin verificar el real (el fondo no se aplica).

---

## 3. ORDEN REAL DE CSS CARGADO POR `play.php` (líneas 32-93)

```
 1. assets/css/play-v3.css
 2. assets/css/play-v3-capas.css
 3. assets/css/play-v3-app.css
 4. assets/css/play-v3-shell-ui.css
 5. assets/css/play-v3-shell-art.css
 6. assets/css/play-v3-capas-shell.css
 7. assets/css/play-v3-mensajitos.css              ← LEGACY buzón (.capa-buzon .carta-msg)
 8. assets/css/play-v3-mapa-canonico.css
 9. assets/css/play-v3-bloques-residencias.css
10. assets/css/play-v3-musica.css
11. assets/css/play-v3-audio.css
12. assets/css/play-v3-regalos.css
13. assets/css/play-v3-lab.css
14. assets/css/play-v3-responsive.css              ← 962 !important, 35 @media
15. assets/css/design-system/tokens.css            ← ÚNICO :root con design tokens
16. assets/css/design-system/components.css
17. assets/css/design-system/screens/inicio-views.css
18. assets/css/design-system/screens/inicio-mobile.css     (4815 !important, 116 @media)
19. assets/css/design-system/screens/inicio-evento-pueblo-mobile.css
20. assets/css/design-system/screens/modals.css
21. assets/css/design-system/screens/capas-ds.css
22. assets/css/play-v3-cotilleos.css
23. assets/css/play-v3-vecinos.css
24. assets/css/play-v3-ficha.css                   (812 !important)
25. assets/css/play-v3-organizar.css
26. assets/css/play-v3-agenda.css
27. assets/css/play-v3-misiones.css
28. assets/css/play-v3-vida.css
29. assets/css/play-v3-enc-int.css
30. assets/css/play-v3-notas-mapa.css
31. assets/css/play-v3-tutorial-ds.css
32. assets/css/play-v3-avisos.css
33. assets/css/play-v3-desktop-shell.css
34. assets/css/play-v3-inicio-override.css
35. assets/css/play-v3-visual-review.css
36. assets/css/play-v3-visual-interior.css
37. assets/css/play-v3-visual-replica.css
38. assets/css/design-system/legibilidad-global.css
39. assets/css/design-system/screens/inicio-desktop.css   (4396 !important, 146 @media)
40. assets/css/design-system/screens/inicio-desktop-cromatica.css
41. assets/css/design-system/screens/inicio-evento-pueblo-desktop.css
42. assets/css/design-system/mensajitos-cartas-persona-v1.css
43. assets/css/design-system/mensajitos-carta-regalo-v1.css
44. assets/css/design-system/ficha-neni-ref-v1.css
45. assets/css/design-system/modal-titles-aht.css
46. assets/css/design-system/modals-secondary-unified.css
47. assets/css/design-system/modals-shell-lavanda-mobile.css
48. assets/css/play-v3-consulta-edificio-v2.css
49. assets/css/play-v3-tutorial-lavanda.css
50. assets/css/design-system/typography-reading.css
51. assets/css/design-system/modal-core.css         ← CANON modal architecture
52. assets/css/design-system/modal-skin.css
53. assets/css/design-system/modal-header.css
54. assets/css/design-system/modal-responsive.css
55. assets/css/design-system/modal-catalog.css     ← solo .aht-catalog*
56. assets/css/design-system/modal-ds.css           ← .aht-modal shell (frame OK)
57. assets/css/design-system/mensajitos-body.css   ← UNICO con .aht-msg-card
58. <style> inline en play.php:94-245              ← playtest-guia, debug, misiones
59. <script condicional> dev/inicio-design-mode.css  (solo si ?design=mobile|desktop)
```

**JS cargado al final** (líneas 1345-1350): `play-v3-mensajitos-lab.js`, `lab-audit.js`, `play-v3-audio.js`, `hobby-icons.js`, `play-v3.js`, `play-v3-lab.js`. **Ningún JS inyecta CSS al `<head>` ni estilos inline relevantes** (verificado `lab-audit.js` completo: solo `console.log`).

---

## 4. TABLA PROPIEDAD → REGLA CANDIDATA → REGLA GANADORA → MOTIVO

Para `<article class="aht-msg-card" data-paper="rose">` dentro del LAB:

| Propiedad | Candidatas | Ganadora | Motivo |
|---|---|---|---|
| `background` | `.aht-msg-card[data-paper="rose"] { background: var(--msg-paper-rose) }` (mensajitos-body.css:239) | **NINGUNA resuelve** — la propiedad cae a su valor inicial (`transparent`) | `--msg-paper-rose` está declarado en `.aht-msg` que **no existe en el DOM** |
| `background` (heredado de padres) | `.aht-msg-section { background-color:#FFF }` (mensajitos-body.css:196) y `.aht-msg-list { background-color:#FAFBFE }` (línea 187) | `#FFFFFF` (visible a través del card transparente) | herencia de `background` no se da en CSS (cada elemento pinta lo suyo); al ser card transparente, se ve el blanco del section inmediato |
| `border` | `.aht-msg-card { border: 2px solid var(--msg-border-soft) }` (línea 221) y `.aht-msg-card[data-paper="rose"] { border-color: var(--msg-border-rose) }` (240) | **el ancho 2px se aplica**, el color cae a `currentColor` (negro por initial de `color`) | `--msg-border-soft` y `--msg-border-rose` también viven solo en `.aht-msg` |
| `box-shadow` | `.aht-msg-card { box-shadow: 2px 3px 0 rgba(51,38,30,.18) }` (línea 223) | **esta gana** — está escrita como literal | valor no envuelto en `var()` |
| `transform` | `.aht-msg-card { transform: rotate(-0.35deg) }` (224) y `.aht-msg-card:nth-child(even) { rotate(.3deg) }` (228) | gana `rotate(-0.35deg)` en odd, `rotate(.3deg)` en even | literal, ok |
| `color` | `.aht-msg-from { color: var(--msg-ink) }` (350) | `color: initial` = `#000` | `--msg-ink` también vive en `.aht-msg` |
| `font-family` | `var(--msg-font-hand)` (349) | `initial` (serif por defecto del navegador) | var sin fallback declarado |
| `data-paper` atributo | inyectado en JS (`renderCard` línea 326: `attrs.push('data-paper="' + paper + '"')`) | atributo **sí presente** (5 valores posibles: `rose/blue/green/cream/lilac`) | OK |
| `[data-paper="rose"]` selector match | sí matchea | sí | OK |

**Conclusión**: la cascada funciona, el atributo se aplica, los selectores `[data-paper="…"]` matchean. Lo que falla es la **definición misma de las variables**. Esto NO es una pelea entre dos reglas: es una variable huérfana.

---

## 5. AUTORIDADES CSS QUE COMPITEN EN MODALES

Hay **dos sistemas de modal conviviendo** sin integrarse:

### Sistema A — Legacy `.capa` (10 modales de producción)

- Vecinos, Ficha, Ficha-Relaciones, Ficha-Ánimo, Ficha-Diario, Misiones, Parejas, Buzón (Mensajitos real), Ajustes, Cotilleos, Organizar, Agendar, Mentes
- Marcados como `.capa .capa-X` con gate de migración `.ds-migrada` opcional
- HTML en `play.php:591-944`
- CSS repartido en: `play-v3-app.css`, `play-v3-capas-shell.css`, `play-v3-X.css` (1 por modal), `play-v3-responsive.css`, `play-v3-visual-*`, `play-v3-inicio-override.css`, `capas-ds.css`, `modals.css`, `modals-secondary-unified.css`, `modals-shell-lavanda-mobile.css`, `legibilidad-global.css`, `modal-core.css`, `modal-skin.css`, `modal-header.css`, `modal-responsive.css`, `screens/inicio-desktop.css`, `screens/inicio-desktop-cromatica.css`, `screens/inicio-mobile.css`
- Encabezado común: clases DS `.ds-modal-tit`, `.ds-modal-close`, `.ds-modal-head`, `.ds-pill`, `.ds-modal-sheet`

### Sistema B — DS nuevo `.aht-modal` (piloto, 2 modales)

- **Mensajitos LAB** (piloto): `play.php:1300-1343`, `play-v3-mensajitos-lab.js`, `mensajitos-body.css` + `modal-ds.css`
- **Modal Catálogo**: no montado todavía, CSS `modal-catalog.css` ya preparado
- Estructura propia: `.aht-modal-overlay`, `.aht-modal`, `.aht-modal-header`, `.aht-modal-body`, `.aht-modal-close`, `.aht-modal-title`
- Solo `.aht-modal-lab-panel` se monta en el LAB

### Conflicto medible

- **Búsqueda de `.aht-modal` / `.aht-modal-body` / `.aht-modal-close` / `.aht-modal-header` en los 19 archivos "legacy"**: 0 coincidencias. Los dos sistemas **no colisionan por clase**.
- Sin embargo, **ambos sistemas usan los mismos nombres de variable CSS** (`--modal-shell-bg`, `--modal-content-bg`, `--modal-header-bg`, `--modal-overlay`, etc., definidos en `tokens.css:81-89`). Eso es acierto, no conflicto.
- **El conflicto real es responsabilidad de gobernanza**: cada vez que se "migra" un modal se añade `.ds-migrada` y se escribe una nueva capa CSS (ej. `capas-ds.css`) sin retirar el CSS legacy. Resultado: 5+ archivos definen `.play-v3 .capa-X .header` con reglas contradictorias, y se necesita `!important` para que la nueva gane.

### Selectores con especificidad sospechosa

- `:has(.game-shell) .play-root[data-capa="X"] .capa-X.ds-migrada` aparece en `modal-core.css`, `modal-skin.css`, `modal-header.css`, `modal-responsive.css`, `capas-ds.css`, `modals-secondary-unified.css`, `modals-shell-lavanda-mobile.css`, `play-v3-visual-*`. Patrón repetido que evidencia "el gate se copia en cada archivo nuevo en vez de existir como mezcla única".

---

## 6. ESTADO DE DEUDA CSS

| Métrica | Valor |
|---|---|
| Archivos CSS en `assets/css/` | **59** |
| Líneas totales | **54 852** |
| Apariciones de `!important` | **16 590** (~30 % de las declaraciones relevantes) |
| Top 1 archivo por !important | `inicio-mobile.css` — **4 815** |
| Top 2 | `inicio-desktop.css` — **4 396** |
| `ficha-neni-ref-v1.css` (¡sólo 1 857 líneas!) | **1 023** `!important` |
| `play-v3-responsive.css` | **962** `!important`, **35** `@media` |
| `play-v3-ficha.css` | **812** `!important` |
| `play-v3-tutorial-ds.css` | **487** `!important` en 899 líneas |
| `modals-secondary-unified.css` | **302** `!important` |
| `play-v3-mensajitos-body.css` (la del LAB) | **0** `!important` (¡única!) |
| Estilos inline `style="…"` en `play.php` | **8** (todos dentro del `<style>` del head, no inline-en-elemento) |
| Selectores `@media` totales | **al menos 600+** en 44 archivos |
| `inicio-desktop.css` | **146** media queries |
| `inicio-mobile.css` | **116** media queries |
| `modal-ds.css` (canon nuevo) | **1** media query (limpio) |
| `mensajitos-body.css` | **1** media query (limpio) |
| Archivos que redefinen `background: var(--ds-paper-card, …)` | **20** (se reparten `play-v3-X.css` + `capas-ds.css` + `modals-*` + `screens/*`) |
| Selectores `article` | **0** (bien) |
| Selectores `.modal` o `.modal-body` genéricos | **0** (bien) |
| Mismo componente definido en varios archivos | **vecinos** en 7+ archivos, **ficha** en 8+, **ajustes** en 6+, **mensajitos** (entre LAB y legacy buzón) en 4+ |

**Reglas que parecen "muertas" pero están activas:**
- `play-v3-visual-replica.css` (799 líneas, 212 !important) — Replica de vistas
- `play-v3-visual-interior.css`, `play-v3-visual-review.css` — overrides de revisión visual
- `ficha-neni-ref-v1.css` — refactor de ficha con `v1` en el nombre

**Inline `<style>` (play.php:94-245)** contiene reglas de `.tutorial-pista`, `.tut-caras`, `.playtest-guia`, `.playtest-cheats`, `.aht-debug-float`, `.aht-debug-panel`, `.mision-bolita`, `.mision-pp`, `.capa-vecinos .vecino img`… Mezcla debug + tutorial + estilos de modales reales. **No debería vivir en el `<style>` del head**.

---

## 7. COMPARACIÓN MENSAJITOS LAB vs RELACIONES vs AJUSTES

| Aspecto | Mensajitos LAB (sistema B nuevo) | Relaciones (`capa-ficha-relaciones`) | Ajustes (`capa-ajustes`) |
|---|---|---|---|
| Clase modal | `.aht-modal` | `.capa .capa-ficha-relaciones.ds-modal-sheet` | `.capa .capa-ajustes.ds-migrada` |
| Header | `.aht-modal-header` + `.aht-modal-title` | `.ds-modal-tit` + `.cerrar.ds-modal-close` | `.ds-modal-tit` + `.cerrar.ds-modal-close` |
| CSS frame | `modal-ds.css` (1 archivo) | `modals.css` + `modals-secondary-unified.css` + `modal-core.css` + `modal-header.css` + `modal-skin.css` + `modal-responsive.css` + `play-v3-ficha.css` + `play-v3-visual-*` | `modal-core.css` + `modal-skin.css` + `modal-responsive.css` + `modals-secondary-unified.css` + `modals-shell-lavanda-mobile.css` + `play-v3-capas-shell.css` + `play-v3-visual-review.css` |
| Estado de migración | **Piloto DS limpio**, pero con bug de scoping `.aht-msg` | Migrado parcialmente (`.ds-modal-sheet`) | Migrado con `.ds-migrada` |
| Riesgo de cascada | bajo (clases únicas, sin overlaps con `.capa`) | medio (múltiples archivos compiten por `.capa-ficha` y `.ds-modal-tit`) | alto (6+ archivos definen `.capa-ajustes`) |
| Variables | `--msg-*` huérfanas (no aterriza `.aht-msg`) | OK, usa `--ds-*` globales | OK, usa `--ds-*` globales |
| `!important` | **0** | muchos | muchos |

**Patrón encontrado**: el bug de Mensajitos LAB (variables huérfanas por scoping `.aht-msg`) **no se repite** en los otros modales porque los otros modales **no usan CSS variables scoped a una clase inexistente** —usan directamente `var(--ds-…)` definidos en `:root` o literales.

**Pero el patrón sistémico sí se repite**: ambos sistemas resuelven su estilo gracias a que alguien, en algún momento, escribió la regla con el specificity correcto en el archivo correcto. No hay un contrato claro que diga "esta clase solo se puede definir en este archivo y nadie más". Si mañana otra clase DS llega con `--ds-X`, otro archivo la puede pisar sin querer.

---

## 8. RIESGO REAL DE MIGRAR MÁS MODALES AHORA

**Riesgo: alto.**

- Cualquier modal nuevo escrito con el patrón `.aht-modal` (sistema B) **replicará el bug de scoping** si alguien vuelve a poner variables en una clase inexistente. El código actual no tiene ningún test ni lint que lo detecte.
- Cualquier modal "migrado" al sistema A (legacy) con gate `.ds-migrada` **heredará la deuda**: 5+ archivos leen `.capa-X.ds-migrada` con reglas distintas. El orden de carga y el `!important` decidirán quién gana; cada vez que se reordene `play.php`, algo se romperá.
- El sistema responsive está fracturado: `play-v3-responsive.css` (962 `!important`) tiene mobile y desktop en `@media (max-width:768px)` y `@media (min-width:769px)` mezcladas; `screens/inicio-mobile.css` y `screens/inicio-desktop.css` son **archivos separados** con 116 y 146 media queries propias cada uno. Migrar un modal nuevo exigirá tocar al menos 3 archivos responsive.

**El síntoma Mensajitos es local, pero la arquitectura ya no soporta otra iteración de migración sin romperse.**

---

## 9. ARQUITECTURA LIMPIA RECOMENDADA

### Principios

1. **Una autoridad por capa:**
   - **FRAME**: `modal-frame.css` (overlay, shell, posición, dimensiones, max-height, scrollbar) — solo `.aht-modal-overlay`, `.aht-modal`, sin colores.
   - **SKIN**: `modal-skin.css` (colores del frame, sombras, radios) — solo vars `--modal-*`.
   - **HEADER**: `modal-header.css` (cabecera canónica: title, X, kicker) — clases `.aht-modal-header`, `.aht-modal-title`, `.aht-modal-close`.
   - **BODY**: un archivo por modal (`mensajitos-body.css`, `ajustes-body.css`, `parejas-body.css`…) — sin tocar el shell.
   - **RESPONSIVE**: `modal-responsive.css` único, centralizado, con `@media (max-width:768px)` solo.

2. **Variables scoped a `:root` o a la clase raíz REAL del componente**, nunca a una clase "imaginaria".

3. **El gate de migración legacy → DS** se elimina: o se borra el CSS legacy (preferido) o se conserva el gate `.ds-migrada`, pero **nunca ambos**.

4. **`!important` solo permitido en:**
   - Resets explícitos y justificados (no acumulables).
   - Reglas universales (`.aht-modal * { box-sizing: border-box }`).
   - Comentario obligatorio explicando por qué.

5. **Inline `<style>` en `play.php` → 0**. Se mueve a archivos y se cachea.

6. **El shell debe ignorar completamente el body**: clases `.aht-modal-body` definidas en su CSS frame; las clases internas (`.aht-msg-card`, etc.) **solo en su body CSS**.

7. **Responsive centralizado**: una capa por breakpoint, no por archivo de componente.

8. **Convención de variables**: `--{componente}-{propiedad}-{variante?}`. Las `--msg-*` viven solo en `mensajitos-body.css` (no se exportan a otros archivos).

9. **El "DS tokens" es la ÚNICA fuente de tokens** (`tokens.css` con `:root`). Los componentes no redefinen tokens, los consumen.

### Estructura objetivo

```
assets/css/
├── design-system/
│   ├── tokens.css                       (:root, único)
│   ├── reset.css                        (*, body, html — sin colores)
│   ├── modal-frame.css                  (overlay + shell, solo posición)
│   ├── modal-skin.css                   (vars --modal-* aplicadas)
│   ├── modal-header.css                 (header canónico)
│   ├── modal-responsive.css             (UN solo @media por breakpoint)
│   ├── components.css                   (botones, pills, inputs genéricos)
│   ├── mensajitos-body.css              (mensajitos LAB)
│   ├── ajustes-body.css                 (cuando se migre)
│   ├── parejas-body.css                 (...)
│   └── ...
├── play-v3.css                          (capa de juego legacy, SIN modales)
└── responsive.css                       (responsive del juego, NO de modales)
```

---

## 10. QUÉ DEBE SOBREVIVIR, QUÉ DEBE CONSOLIDARSE, QUÉ DEBE ELIMINARSE

### Sobrevive (esencia canónica)

| Archivo | Función |
|---|---|
| `tokens.css` | única fuente de design tokens en `:root` |
| `modal-frame.css` (a crear) | geometría del shell, sin colores |
| `modal-skin.css` | colores del shell, sombras, radios |
| `modal-header.css` | header unificado |
| `modal-responsive.css` | responsive único |
| `mensajitos-body.css` (corregido) | estilos del body Mensajitos LAB |
| `modal-catalog.css` (futuro) | estilos del body Modal Catálogo |

### Consolida (mezcla en archivos canónicos, eliminar originales)

| Fuentes a fusionar | Destino |
|---|---|
| `modal-core.css` + `modal-skin.css` + `modal-header.css` + `modal-responsive.css` + `modals-secondary-unified.css` + `modals-shell-lavanda-mobile.css` + `modals.css` + `screens/capas-ds.css` | `modal-frame.css` + `modal-skin.css` + `modal-header.css` + `modal-responsive.css` (versión única limpia) |
| `play-v3-vecinos.css` + `play-v3-ficha.css` + `play-v3-misiones.css` + `play-v3-organizar.css` + `play-v3-agenda.css` + `play-v3-mensajitos.css` (legacy buzón) + `play-v3-cotilleos.css` + `play-v3-vida.css` + `play-v3-enc-int.css` + `play-v3-avisos.css` | un archivo por body de modal migrado (`ajustes-body.css`, `parejas-body.css`, etc.) o se mantienen como legado si el modal no se migra todavía |
| `play-v3-bloques-residencias.css` + `play-v3-notas-mapa.css` + `play-v3-regalos.css` | módulos de juego, no de modal → mantener separados |
| `play-v3-visual-review.css` + `play-v3-visual-interior.css` + `play-v3-visual-replica.css` | **borrar** si la revisión visual ya pasó; eran overrides de QA |
| `screens/inicio-mobile.css` + `screens/inicio-desktop.css` + `screens/inicio-desktop-cromatica.css` + `screens/inicio-evento-pueblo-mobile.css` + `screens/inicio-evento-pueblo-desktop.css` + `screens/inicio.css` + `screens/inicio-views.css` | un `inicio.css` único con sus media queries, dividido por componente interno |
| `play-v3-inicio-override.css` | **borrar** (es un parche encima de inicio, evidencia de deuda) |
| `play-v3-consulta-edificio-v2.css` (191 !important) | refactorizar cuando se migre consulta a sistema B |
| `play-v3-ficha.css` + `ficha-neni-ref-v1.css` | consolidar (1 023 `!important` en `ficha-neni-ref-v1.css` indica v1 no era el plan final) |
| `play-v3-tutorial-ds.css` + `play-v3-tutorial-lavanda.css` + `screens/inicio-views.css` (parte tutorial) | `tutorial.css` único |
| `mensajitos-cartas-persona-v1.css` + `mensajitos-carta-regalo-v1.css` | fusionar en `mensajitos-body.css` (estado v1 ya pasó) |
| `modal-titles-aht.css` | fusionar en `modal-header.css` |
| Inline `<style>` en `play.php:94-245` | mover a archivos: `.tut-caras` y `.tut-intro` → `tutorial.css`, `.playtest-*` → archivo debug, `.aht-debug-*` → archivo debug, `.capa-vecinos .vecino img` (líneas 222-234) → `play-v3-vecinos.css` (donde ya existen reglas equivalentes en líneas 248+) |
| `play-v3-mensajitos.css` (legacy buzón, `.capa-buzon .carta-msg`) | mantener hasta migrar Mensajitos real; **no afecta al LAB** porque las clases son distintas |

### Elimina (reglas muertas / obsoletas)

- `play-v3-inicio-override.css` (nombre evidencia parche)
- `play-v3-visual-*` (3 archivos, todos overrides de revisión)
- `ficha-neni-ref-v1.css` cuando `play-v3-ficha.css` lo absorba
- `mensajitos-cartas-persona-v1.css` y `mensajitos-carta-regalo-v1.css` (v1 → consolidado en body)
- `modal-titles-aht.css` (consolidado en header)
- Todas las reglas `play-v3-X.css` cuando su modal haya sido migrado al sistema B

### Bug de Mensajitos LAB a corregir al migrar

- Mover el bloque `.aht-msg { --msg-paper-rose: #f4aac0; … }` a `:root` (en `tokens.css`) **o** cambiar el selector a `.aht-modal` (clase que sí existe). **El cambio mínimo es sustituir `.aht-msg {` por `:root, .aht-modal {` en `mensajitos-body.css:15`**.

---

## 11. PLAN DE SANEAMIENTO POR FASES

### Fase A — Identificar autoridad real (sin tocar producción)

- **A.1** Congelar cualquier iteración nueva sobre Mensajitos.
- **A.2** Inventariar todos los archivos CSS que se cargan en `play.php` (59 archivos ya contados) y mapearlos contra modales concretos.
- **A.3** Etiquetar cada archivo como: `legacy-only` / `ds-only` / `shared` / `parche` / `muerto`.
- **A.4** Buscar con `grep` todas las variables CSS declaradas en clases que **no existen en el DOM** (replica del bug Mensajitos). Si hay más, documentarlas.
- **A.5** Decisión de Neni: ¿se mantiene sistema A y sistema B en paralelo, o se apuesta por B?

### Fase B — Crear contrato único shell

- **B.1** Definir `modal-frame.css` (posición, dimensiones, scrollbar, max-height). Sin colores.
- **B.2** Definir `modal-skin.css` (vars `--modal-*` aplicadas al frame).
- **B.3** Definir `modal-header.css` (header unificado: title + cierre).
- **B.4** Definir `modal-responsive.css` único con 2-3 breakpoints (`@media (max-width:768px)`, `@media (min-width:769px and max-width:1100px)`).
- **B.5** El shell ignora el body. Ningún selector del shell debe atravesar `.aht-modal-body`.

### Fase C — Aislar piloto Mensajitos

- **C.1** Corregir el bug de scoping (mover vars `.aht-msg` a `:root` o a `.aht-modal`).
- **C.2** Crear `mensajitos-body.css` limpio partiendo del actual pero con vars globales. Borrar `mensajitos-cartas-persona-v1.css`, `mensajitos-carta-regalo-v1.css`.
- **C.3** El LAB ahora usa solo `modal-frame.css` + `modal-skin.css` + `modal-header.css` + `mensajitos-body.css` + `modal-responsive.css`. El resto de CSS del sistema A no le afecta.
- **C.4** Test visual: card rosa real, borde sólido real, sin sombras espurias.

### Fase D — Eliminar CSS legacy que compite SOLO para ese piloto

- **D.1** Confirmar con DevTools que `play-v3-mensajitos.css` (legacy buzón), `play-v3-capas-shell.css`, `play-v3-responsive.css`, `modals-secondary-unified.css`, `modals-shell-lavanda-mobile.css`, `play-v3-tutorial-ds.css`, `play-v3-tutorial-lavanda.css`, `play-v3-visual-*`, `capas-ds.css`, `modals.css`, `play-v3-inicio-override.css`, `ficha-neni-ref-v1.css`, `play-v3-X.css` (vecinos, ficha, misiones, etc.) **no escriben ninguna propiedad que afecte al LAB**.
- **D.2** Si alguno sí afecta (poco probable dado el grep), aislar el LAB con `.aht-modal` como gate de herencia o cargar su CSS en orden último.

### Fase E — Validar desktop/mobile

- **E.1** Test manual en `?modal_catalog=1` desktop y móvil.
- **E.2** Confirmar que la cascada de `--modal-*` no cambia entre breakpoints (las vars son globales; los valores aplicados pueden cambiar dentro del `@media`, pero las vars son las mismas).
- **E.3** Confirmar que no aparece ningún `!important` necesario en el LAB.

### Fase F — Migrar siguientes modales

- **F.1** Elegir el segundo modal candidato (recomendación: el más estable visualmente, p. ej. Ajustes o Parejas).
- **F.2** Replicar el contrato del shell. Crear `ajustes-body.css` o `parejas-body.css` desde cero.
- **F.3** Añadir `.aht-modal.ajustes` y `.aht-modal.parejas` como scope para que las vars y reglas del body no se pisen con Mensajitos.
- **F.4** Borrar el CSS legacy correspondiente **el mismo commit**.
- **F.5** Repetir hasta vaciar el sistema A.

### Fase G — Saneamiento final

- **G.1** Eliminar `play-v3-visual-*`, `play-v3-inicio-override.css`, `modal-titles-aht.css`, `mensajitos-cartas-*-v1.css`, `ficha-neni-ref-v1.css` (si quedó).
- **G.2** Eliminar el `<style>` inline de `play.php:94-245`.
- **G.3** Auditoría final de `!important` restantes (objetivo: < 500 en toda la base, solo en reset y en overrides de reset de navegador).
- **G.4** Convención de variables CSS públicas en `tokens.css` documentada.

---

## 12. ARCHIVOS QUE HABRÍA QUE TOCAR DESPUÉS DEL GO DE NENI

> Solo enumeración, ningún cambio aplicado.

### Fix mínimo del bug Mensajitos (cambio quirúrgico, ~5 líneas)

- `assets/css/design-system/mensajitos-body.css` — línea 15 cambiar selector `.aht-msg {` por `:root, .aht-modal {` (las vars se hacen globales y se siguen aplicando por cascada). Eso es todo. **Pero Neni tiene que decidir si quiere este fix mínimo como "curita" o si quiere entrar en el plan de saneamiento.**

### Saneamiento completo (orden propuesto)

1. `play.php:94-245` — mover inline `<style>` a archivos.
2. `play.php:32-93` — reordenar CSS: tokens + reset primero, después frame/skin/header, después body de cada modal, al final responsive único.
3. `assets/css/design-system/` — crear `modal-frame.css`, fusionar `modal-core.css` + `modal-skin.css` + `modal-header.css` + `modal-responsive.css` + `modals-secondary-unified.css` + `modals-shell-lavanda-mobile.css` + `screens/capas-ds.css` en los 4 archivos canónicos.
4. `assets/css/design-system/mensajitos-body.css` — reescribir con vars en `:root`, eliminar duplicados de cartas.
5. Borrar: `play-v3-inicio-override.css`, `play-v3-visual-*`, `ficha-neni-ref-v1.css`, `mensajitos-cartas-*-v1.css`, `modal-titles-aht.css`.
6. Consolidar `screens/inicio-*.css` en `inicio.css`.
7. Por cada modal migrado, fusionar su `play-v3-X.css` en `X-body.css` del sistema B y borrar el original.
8. `play-v3-responsive.css` — fusionar con `modal-responsive.css` y reescribir sin `!important`.

---

## STOP

Esperando revisión de Neni + ChatGPT.
No se ha modificado ningún archivo.
No se ha hecho commit.
No se ha hecho push.
