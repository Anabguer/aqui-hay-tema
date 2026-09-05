# Arquitectura CSS — Aquí Hay Tema (contrato canónico)

Documento **prescriptivo**. Antes de tocar CSS de play, modales, inicio o pantallas, léalo completo.

## A. Principio rector

- **Modales:** V4 única autoridad (`aht-screen` + `aht-frame` + `screens.css`).
- **Inicio:** stack unificado en `assets/css/inicio/` (sin `inicio-mobile.css` legacy en design-system/screens).
- **Prohibido** reintroducir hojas eliminadas (capas, modal-core, lavanda shell, visual-review, screens-secondary, etc.).
- **No añadir `!important` nuevo** salvo excepciones documentadas (§I).

## B. Contrato DOM

| Elemento | Rol |
|----------|-----|
| `.aht-screen[data-aht-screen="…"]` | Pantalla modal (19 screens). |
| `.aht-frame` | Carcasa modal V4. |
| `data-capa` en `.play-root` | Interruptor JS de visibilidad. |
| `.capa-scroll`, `.capa-cerrar-pestaña` | **Utilidades** de scroll/cierre (no wrappers `.capa-*`). |

## C. Orden de carga en `play.php`

1. `tokens.css` + `v4/tokens-v4.css`
2. Global play-v3 + inicio-views (flash dual-view)
3. `v4/screen-frame.css`
4. Cuerpos `play-v3-*.css` (selectores `.aht-screen[…]`)
5. DS: mensajitos-body, vecinos-body, cartas v1
6. **`v4/bodies/*.css`** — parejas, ajustes, relaciones, misc (ex screens-secondary)
7. **`v4/screens.css`** — visibilidad + inventario + última autoridad modal
8. **Inicio:** `inicio/tokens-inicio.css` → `inicio-base.css` → `inicio-mobile.css` → `inicio-desktop.css` → `inicio-cromatica-desktop.css` → `inicio-responsive.css`
9. `legibilidad-global.css`, `typography-reading.css`
10. `play-v3-lab.css` solo con `?lab=1`

## D. Tokens

- V4: `v4/tokens-v4.css` (`--aht-shell-bg: #FCFBFE`).
- Inicio: `inicio/tokens-inicio.css` + cromática desktop en `inicio-cromatica-desktop.css` (sin `!important`; orden de carga sustituye blindaje).

## E. Frame

- `v4/screen-frame.css` — marcador `AHT-FRAME-CANON-v4`.

## F. Guardas

- `scripts/aht_guard_css_architecture.ps1` (alias `aht_guard_modal_shell.ps1`)
- `node tests/css_architecture_test.js`
- Revisión visual: **intocables13.com** (Neni).

## G. Prohibiciones

- Selectores wrapper `.capa-vecinos`, `.capa-parejas`, etc.
- Enlazar `screens-secondary.css` o stacks inicio legacy bajo `design-system/screens/inicio-*.css` (salvo `inicio-views.css`).

## H. FTP / prod

Tras deploy fase 2, borrar remotos listados en `scripts/aht_ftp_delete_legacy_css.ps1`.

## I. `!important` legítimo restante

| Ubicación | Motivo |
|-----------|--------|
| `play.php` inline (4 reglas) | Debug / ocultar playtest y tiempo en prod |
| `mensajitos-cartas-persona-v1.css`, `mensajitos-carta-regalo-v1.css` | Cartas DS aún no migradas a autoridad V4 (fase cartas pendiente) |

Objetivo inicio + v4: **0 `!important`** (cumplido en fase 2).
