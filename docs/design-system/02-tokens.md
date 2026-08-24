# 02 · Tokens definitivos

Fuente canónica: `assets/css/design-system/tokens.css` (prefijo `--ds-`).
Los hex están estimados sobre las referencias y se validarán por muestreo de píxel en FASE 3; cualquier ajuste se hace SOLO aquí.

---

## 1. Color

### Papel y superficies

| Token | Valor | Uso |
|---|---|---|
| `--ds-paper-app` | `#F6EFDC` | Fondo general (mesa de papel) |
| `--ds-paper-card` | `#FBF5E6` | Tarjetas |
| `--ds-paper-modal` | `#F8F1DF` | Modales/hojas |
| `--ds-paper-warm` | `#F3E7CE` | Papel secundario, hover, filas alternas |
| `--ds-paper-dim` | `#EFE5CC` | Papel desactivado, pistas |

### Tinta y texto

| Token | Valor | Uso |
|---|---|---|
| `--ds-ink` | `#33261E` | Texto principal, bordes fuertes |
| `--ds-ink-soft` | `#6B5138` | Bordes secundarios, iconos, trazos |
| `--ds-text-muted` | `#75634F` | Texto secundario funcional, placeholders. **Ajustado frente a la propuesta inicial** (`#8A7767`): muestreo de contraste da 3.7:1 sobre papel; `#75634F` da **5.0:1** y cumple la regla 4.5:1 manteniendo el tono cálido |
| `--ds-text-faint` | `#A99A87` | Microtexto pasivo, "?" de bloqueados |

### Marca

| Token | Valor | Uso |
|---|---|---|
| `--ds-coral` | `#E87A5A` | CTA primario, acentos cálidos |
| `--ds-coral-deep` | `#C9563E` | Borde/prensa del coral |
| `--ds-coral-soft` | `#F7C9B9` | Fondo coral suave |
| `--ds-pink` | `#E989A7` | Romance, NUEVO suave (= `--pink` actual) |
| `--ds-pink-deep` | `#C42B4A` | Romance fuerte (= `--pink-deep` actual) |
| `--ds-pink-soft` | `#F9DCE4` | Fondos rosa (ribbons, banner cotilleo) |
| `--ds-lavender` | `#B9A8DC` | Acción secundaria, info |
| `--ds-lavender-deep` | `#7C6BAE` | Texto lavanda legible, enlaces |
| `--ds-lavender-soft` | `#E4DCF4` | Fondos lavanda (chips, hints) |
| `--ds-mustard` | `#E3B04B` | Recompensa, XP, tiempo |
| `--ds-mustard-deep` | `#B98A2F` | Texto mostaza legible |
| `--ds-mustard-soft` | `#F6E3AE` | Tickets, chips caducidad |

### Estados y feedback

| Token | Valor | Uso |
|---|---|---|
| `--ds-green` | `#8FAA84` | Positivo, EN CURSO (= `--emo-alegre`) |
| `--ds-green-deep` | `#5E7A55` | Texto verde legible |
| `--ds-green-soft` | `#D9E4CF` | Fondos verdes |
| `--ds-red-alert` | `#C4453B` | Error, alerta, badge NUEVO |
| `--ds-red-soft` | `#F3D3CE` | Fondo error suave |
| `--ds-warning` | `#E0A93F` | Advertencia |
| `--ds-warning-soft` | `#F7E8C8` | Fondo advertencia |
| `--ds-disabled` | `#C9BFB0` | Componente desactivado |
| `--ds-overlay` | `rgba(40,32,26,.55)` | Velo tras modales |

### Emocionales (se mantienen los actuales del motor)

| Token | Valor | Nota |
|---|---|---|
| `--ds-emo-neutro` | `#C9A06A` | = `--emo-neutro` |
| `--ds-emo-alegre` | `#8FAA84` | = `--emo-alegre` |
| `--ds-emo-triste` | `#8A9AAD` | = `--emo-triste` |
| `--ds-emo-enfadado` | `#C4775A` | = `--emo-enfadado` |

**Regla de contraste**: sobre papel (`#F6EFDC`–`#FBF5E6`) el texto usa siempre `--ds-ink`, `--ds-text-muted`, o los `-deep` de cada familia. Los colores vivos (`--ds-coral`, `--ds-pink`…) son fondo de texto blanco o borde, nunca color de texto pequeño.

**Notas de contraste validadas (FASE 2)**: `--ds-ink` ≈ 12:1 y `--ds-text-muted` ≈ 5:1 sobre todos los papeles → válidos para cualquier tamaño. `--ds-lavender-deep` (≈4:1) y `--ds-green-deep` (≈4.2:1) solo en texto **≥16 px en negrita o acompañado de emoji/icono** (pills, links Caveat); nunca en cuerpo funcional pequeño. `--ds-text-faint` (≈2.5:1) es **decorativo** ("?" de bloqueados, microtexto pasivo), jamás information-bearing.

## 2. Tipografía

Carga actual (Google Fonts vía `@import` en CSS): Fraunces (serif títulos legacy), **Nunito** (UI), **Caveat** (manuscrita).
**D12**: los títulos usan **Caveat 700**. Tras la primera pantalla real migrada se evalúa si necesita una marker más rotunda; hasta entonces no se añade ninguna fuente.

### Roles

| Rol | Fuente | Móvil | Desktop | Notas |
|---|---|---|---|---|
| `ds-font-display` — título de juego / modal | Caveat 700 MAYÚS. | 30–34 px | 36–44 px | letter-spacing .5–1 px |
| `ds-font-title` — encabezado sección / ribbon | Caveat 700 MAYÚS. | 20–22 px | 22–26 px | |
| `ds-font-name` — nombre de vecino | Caveat 700 | 22–26 px | 26–32 px | ficha: 36–40 px |
| `ds-font-hand` — manuscrita narrativa | Caveat 600 | 17–19 px | 18–20 px | **nunca < 16 px** |
| `ds-font-body` — texto funcional | Nunito 600 | 15–16 px | 15–16 px | base de la UI |
| `ds-font-strong` — énfasis funcional | Nunito 800 | 14–16 px | | |
| `ds-font-button` — CTA primario | Caveat 700 MAYÚS. | 20–22 px | 22–24 px | |
| `ds-font-button-sm` — botón pequeño/chip | Nunito 800 | 13–14 px | | |
| `ds-font-badge` — badges/microtexto | Nunito 800 MAYÚS. | 11–12 px | | mínimo absoluto |
| `ds-font-data` — horas, contadores, XP | Nunito 700–800 | 13–16 px | | tabular-nums |

### Reglas de legibilidad

1. Manuscrita solo ≥ 16 px; jamás en datos críticos pequeños.
2. Cuerpo funcional móvil ≥ 15 px. **Prohibido** el patrón actual `font-size: .52–.72rem !important` (8–12 px) sobre contenido.
3. Nombres propios: nunca truncados; envuelven.
4. `line-height`: manuscrita 1.25–1.35; cuerpo 1.45–1.55.

## 3. Espaciado

Escala: `--ds-space-1..8` = `4 · 8 · 12 · 16 · 20 · 24 · 32 · 40` px.

| Uso | Valor |
|---|---|
| Gutter de pantalla móvil | 16 px |
| Padding tarjeta | 16 px |
| Padding modal (móvil) | 20 px |
| Gap entre tarjetas de lista | 12 px |
| Gap entre secciones | 24–32 px |
| Gap grid vecinos (2 col.) | 12–16 px |
| Icono↔texto en pills/botones | 6–8 px |

## 4. Radios

| Token | Valor | Uso |
|---|---|---|
| `--ds-radius-card` | 20 px | Tarjetas (24 en piezas grandes) |
| `--ds-radius-modal` | 28 px | Modales/hojas |
| `--ds-radius-btn` | 16 px | Botones |
| `--ds-radius-chip` | 12 px | Chips, tickets |
| `--ds-radius-pill` | 999 px | Pills, avatares, tabs |

## 5. Bordes y sombras

| Token | Valor |
|---|---|
| `--ds-border` | `1.5px solid var(--ds-ink-soft)` |
| `--ds-border-strong` | `2px solid var(--ds-ink)` |
| `--ds-shadow-rest` | `0 4px 10px rgba(70,50,30,.10)` |
| `--ds-shadow-raised` | `0 8px 20px rgba(70,50,30,.16)` |
| `--ds-shadow-cta` | `0 6px 14px rgba(70,50,30,.16), inset 0 -3px 0 rgba(0,0,0,.14)` |
| `--ds-shadow-cta-press` | `inset 0 -1px 0 rgba(0,0,0,.14)` |
| `--ds-stitch-color` | `rgba(255,255,255,.75)` (color de la costura interior punteada de CTA/tab activa; se usa como `border: 2px dashed var(--ds-stitch-color)`) |

## 6. Táctil y tamaños clave

| Token | Valor |
|---|---|
| `--ds-touch-min` | 44 px (mínimo target) |
| `--ds-cta-h` | 56 px (CTA primario) |
| `--ds-tab-h` | 48 px |
| `--ds-close-size` | 44 px |
| `--ds-avatar-token` | 40 px (mapa) |
| `--ds-avatar-sm` | 48 px (parejas, planes) |
| `--ds-avatar-md` | 56 px (listas, mensajes) |
| `--ds-avatar-lg` | 64 px (picker) |
| `--ds-avatar-xl` | 120 px (ficha) |

## 7. Z-index

| Token | Valor | Uso |
|---|---|---|
| `--ds-z-map` | 1 | Capas del mapa |
| `--ds-z-hud` | 30 | Cabecera |
| `--ds-z-velo` | 40 | Velo |
| `--ds-z-capa` | 50 | Capas/modales |
| `--ds-z-submodal` | 60 | Sub-modales (ánimo, relaciones) |
| `--ds-z-toast` | 70 | Toasts |
| `--ds-z-debug` | 200 | Lab/debug |

## 8. Motion

| Token | Valor |
|---|---|
| `--ds-ease` | `cubic-bezier(.22,.9,.3,1.2)` (leve rebote físico) |
| `--ds-dur-fast` | 120 ms (press) |
| `--ds-dur` | 200 ms (aperturas) |
| `--ds-dur-slow` | 320 ms (capas) |

Press de botón: `transform: scale(.97)` + `--ds-shadow-cta-press`. Respetar `prefers-reduced-motion`.

## 9. Breakpoints

| Token | Valor | Uso |
|---|---|---|
| `--ds-bp-phone` | ≤ 767 px | Mobile (referencia principal) |
| `--ds-bp-tablet` | 768–1023 px | Shell compacto |
| `--ds-bp-desktop` | ≥ 1024 px | Shell 3 columnas |

⚠️ Nota técnica: el JS alterna `.phone/.pc` en `<720 px` y el CSS histórico usa `768 px`. El DS usa 768/1024 en sus media queries y **no toca** el corte de JS en FASE 3 (riesgo documentado en `05-responsive.md` §4).

## 10. Puente con los tokens legacy (FASE 3)

`tokens.css` incluye, comentada, una sección **PUENTE LEGACY** que remapea las variables actuales al nuevo sistema:

```css
/* --paper: var(--ds-paper-app);  --ink: var(--ds-ink);  --pink: var(--ds-pink); ... */
```

Se activa en el paso 1 de migración para que todo el CSS heredado beba de los nuevos valores sin reescribirlo. Mientras el archivo no esté enlazado desde `play.php`, no tiene efecto alguno.
