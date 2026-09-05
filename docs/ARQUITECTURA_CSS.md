# Arquitectura CSS — Aquí Hay Tema (contrato canónico)

Documento **prescriptivo**. Antes de tocar CSS de play, modales o pantallas, léelo completo.

## A. Principio rector

- **Una sola generación de modales en producción:** V4 (`aht-screen` + `aht-frame`).
- El DOM de `play.php` **no** usa wrappers `.capa-*` (solo utilidad `capa-scroll` en scroll internos).
- **Prohibido** reintroducir hojas eliminadas (`play-v3-capas*`, `modal-core*`, `modals-shell-lavanda*`, trilogía visual-review, `play-v3-ficha.css`, etc.).
- **No añadir `!important` nuevo** salvo excepción documentada en este archivo (ninguna hoy).

## B. Contrato DOM

| Elemento | Rol |
|----------|-----|
| `.aht-screen[data-aht-screen="…"]` | Contenedor fijo de cada pantalla modal (19 screens). |
| `.aht-frame` | Carcasa (borde, fondo shell, header, body, footer, X). |
| `.aht-velo` / `.velo` | Backdrop; color desde `--aht-overlay`. |
| `data-capa` en `.play-root` | Sigue siendo el **interruptor JS** de visibilidad; CSS V4 mapea `data-capa` → `data-aht-screen`. |

## C. Orden de carga en `play.php`

1. `design-system/tokens.css` + `v4/tokens-v4.css`
2. Layout global: `play-v3.css`, `play-v3-app.css`, inicio-views, shell, mapa, responsive base
3. `design-system/components.css`
4. **`v4/screen-frame.css`** — autoridad de carcasa
5. Cuerpos por pantalla: `play-v3-*.css` (selectores **`.aht-screen[data-aht-screen="…"]`**, no `.capa-*`)
6. DS bodies: `mensajitos-body.css`, `vecinos-body.css`, cartas v1
7. **`v4/screens-secondary.css`** — reglas secundarias migradas (ex secondary-unified)
8. **`v4/screens.css`** — visibilidad + contenido V4 (**última autoridad** de pantallas modales)
9. Stacks inicio móvil/desktop (grandes; no refactorizar sin plan)
10. `typography-reading.css`, `legibilidad-global.css`

No enlazar CSS de capas/modal legacy después de `screens.css`.

## D. Tokens

- **V4:** `assets/css/v4/tokens-v4.css` — `--aht-*`, incl. `--aht-shell-bg: #FCFBFE`.
- **Legacy DS:** `tokens.css` — componentes globales; alias `--modal-shell-bg` apunta a V4 donde aplique.
- Cambios de color de carcasa modal → **tokens-v4** y/o **screen-frame**, no en `play-v3-avisos` ni similares.

## E. Frame (carcasa)

- Archivo: `assets/css/v4/screen-frame.css`
- Marcador de integridad: `AHT-FRAME-CANON-v4`
- `.aht-frame` usa `--aht-shell-bg`, borde `--aht-border`, sombra `--aht-frame-shadow`
- Título, tabs, X: solo clases `.aht-frame-*`

## F. Contenido por pantalla

- Reglas de negocio visual en `play-v3-<pantalla>.css` y DS bodies.
- Selector canónico: `.aht-screen[data-aht-screen="nombre"]` (snake_case como en DOM).
- `v4/screens.css` — layout compartido, visibilidad, piezas mensajitos/buzón migradas.
- `v4/screens-secondary.css` — ajustes finos (parejas, ajustes, ficha relaciones, etc.).

## G. Prohibiciones explícitas

- Fondos scrapbook `#fffaf0`, `libreta_hoja.png` en modales principales.
- Selectores `.capa-*` nuevos en CSS cargado por play (excepto comentarios y clase util `capa-scroll`).
- Duplicar piel modal en `play-v3-visual-*` (eliminados).
- Cargar `!important` wars entre lavanda y capas (lavanda eliminada).

## H. Validación y guardas

- Tras cambios en frame/screens/play links: `scripts/aht_guard_modal_shell.ps1`
- Tests: `node tests/modal_architecture_test.js`, `php tests/tutorial_lavanda_css_test.php`
- Revisión visual final: **intocables13.com** (Neni), no localhost como producto.

### Deuda conocida (honesta)

- Algunas reglas en `screens-secondary.css` conservan `!important` heredado del unified legacy; reducir gradualmente sin regresión visual.
- Consulta edificio / notas mapa pueden tener selectores antiguos menores — auditar si se reabre esa pieza.
- Stacks inicio desktop/mobile siguen siendo voluminosos y separados del V4 frame.