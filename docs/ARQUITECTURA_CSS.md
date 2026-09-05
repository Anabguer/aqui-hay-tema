# Arquitectura CSS — Aquí Hay Tema (contrato canónico)

Documento **prescriptivo**. Antes de tocar CSS de play, modales, inicio o pantallas, léalo completo.

## A. Principio rector

- **Modales:** V4 única autoridad (`aht-screen` + `aht-frame` + `screens.css`).
- **Inicio:** stack unificado en `assets/css/inicio/` (sin `inicio-mobile.css` legacy en design-system/screens).
- **Prohibido** reintroducir hojas eliminadas (capas, modal-core, lavanda shell, visual-review, screens-secondary, mensajitos-cartas-*-v1, etc.).
- **Política cero `!important` (obligatoria):** en `assets/css/**` y en bloques `<style>` inline de `play.php` el total debe ser **0**. Sin excepciones por debug, decoración, ocultar playtest o “deuda” temporal. Si hace falta prioridad, usar orden de carga, especificidad y tokens — nunca `!important`.

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
5. DS: **`design-system/mensajitos-body.css`** (mensajitos/buzón), `vecinos-body`
6. **`v4/bodies/*.css`** — parejas, ajustes, relaciones, misc (ex screens-secondary)
7. **`v4/screens.css`** — visibilidad + inventario + última autoridad modal
8. **Inicio:** `inicio/tokens-inicio.css` → `inicio-base.css` → `inicio-mobile.css` → `inicio-desktop.css` → `inicio-cromatica-desktop.css` → `inicio-responsive.css`
9. `legibilidad-global.css`, `typography-reading.css`
10. `play-v3-lab.css` solo con `?lab=1`

### Mensajitos (autoridad)

- **Única hoja:** `assets/css/design-system/mensajitos-body.css`.
- Eliminadas como legacy muerto (selectores `.carta-*`, no enlazadas en `play.php`): `mensajitos-cartas-persona-v1.css`, `mensajitos-carta-regalo-v1.css`. No reintroducir.

## D. Tokens

- V4: `v4/tokens-v4.css` (`--aht-shell-bg: #FCFBFE`).
- Inicio: `inicio/tokens-inicio.css` + cromática desktop en `inicio-cromatica-desktop.css` (sin `!important`; orden de carga sustituye blindaje).

## E. Frame

- `v4/screen-frame.css` — marcador `AHT-FRAME-CANON-v4`.

## F. Guardas

- `scripts/aht_guard_css_architecture.ps1` (alias `aht_guard_modal_shell.ps1`)
- `node tests/css_architecture_test.js`
- **Umbral:** fallo si `assets/css` contiene cualquier `!important` o si `play.php` tiene `!important` en CSS inline (objetivo **total 0** en ambos).
- Revisión visual: **intocables13.com** (Neni).

## G. Prohibiciones

- Selectores wrapper `.capa-vecinos`, `.capa-parejas`, etc.
- Enlazar `screens-secondary.css` o stacks inicio legacy bajo `design-system/screens/inicio-*.css` (salvo `inicio-views.css`).
- Cualquier `!important` nuevo en CSS de play o modales.

## H. FTP / prod

Tras cambios de arquitectura, borrar remotos listados en `scripts/aht_ftp_delete_legacy_css.ps1` (incluye hojas mensajitos v1 retiradas del repo).

## Capa de reconstrucci�n visual � stack INICIO

Responsabilidades del stack `assets/css/inicio/*` (orden en `play.php`):

1. `tokens-inicio.css` � tokens locales del stage (`--ds-*`) sin pisar V4.
2. `inicio-base.css` � layout compartido del stage y utilidades comunes.
3. `inicio-mobile.css` � **solo** `.inicio-stage .inicio-mobile �` (cabecera grid, tiles, feed).
4. `inicio-desktop.css` � grid de columnas desktop (estructura, sin crom�tica).
5. `inicio-cromatica-desktop.css` � paleta lavanda aprobada (`#F3EFF7`, tarjetas blancas) en desktop activo.
6. `inicio-responsive.css` � toggles de visibilidad m�vil/desktop del stage.

Reglas migradas desde `play-v3-responsive.css` que afecten cabecera INICIO (`game-top`, `pasar-rato`, `top-reloj`) viven en el stack INICIO, no en responsive global.

**Cero `!important`:** prohibido en todo el stack y en overrides de INICIO.

## Shell modal can�nico (V4)

Autoridad: `tokens-v4.css` + `screen-frame.css`.

Componentes de cabecera:

| Slot | Clase | Notas |
|------|-------|-------|
| Atr�s (opcional) | `.aht-frame-back` | Izquierda; misma familia visual que cerrar |
| T�tulo | `.aht-frame-title` | Centrado; decoraci�n `?` v�a pseudo-elementos |
| Cerrar | `.aht-frame-close` | Derecha |

El **footer** (`.aht-frame-footer`) no debe crear barra visual cuando est� vac�o (`:empty` sin padding/borde).

No reintroducir hojas modales legacy ni `.capa-*` en CSS nuevo.


