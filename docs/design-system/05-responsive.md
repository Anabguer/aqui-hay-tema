# 05 · Responsive — mobile-first y estrategia desktop

## 1. Diagnóstico del problema actual

El móvil de `play.php` funciona, pero hereda densidad de escritorio:

- `play-v3-responsive.css` (4.875 líneas, 43 media queries) lleno de `font-size: .52–.72rem !important` (≈8–12 px).
- Targets pequeños, jerarquía plana, textos truncados, módulos apilados sin respiración.
- Corte JS `.phone/.pc` en `<720 px` vs media queries CSS en `768 px` (zona 720–768 con estados mixtos).

## 2. Reglas mobile-first (obligatorias)

1. **Diseñar a 360–390 px.** Si no cabe ahí, no está bien diseñado.
2. Tipos mínimos (regla inequívoca): cuerpo funcional **≥15 px** (normal 15–16 px), secundario ≥13 px, microtexto **solo excepcionalmente** 11–12 px y siempre en MAYÚSCULAS espaciadas, manuscrita narrativa **nunca <16 px**. Avatares con presencia (56 px mínimo en listas).
3. Targets **≥44×44 px**; CTA primario 56 px a ancho completo; tabs 48 px; close 44 px.
4. Una columna = un foco. Secciones apiladas con gap 16–20 px; gutter 16 px.
5. Avatares: 56 px mínimo en listas, 120 px en ficha, 40 px solo en el mapa.
6. Scroll vertical natural; carrusel horizontal solo donde ya existe (EN CURSO).
7. Nada de truncar nombres propios ni timestamps.
8. Modales como hojas casi a pantalla completa (`calc(100% - 24px)`, máx `92dvh`) con close siempre visible.
9. `env(safe-area-inset-*)` y `100dvh` (ya en uso, se mantiene).
10. Scroll-lock con compensación de scrollbar (mecánica actual `syncScrollLock` se conserva).
11. Prohibido `!important` de tamaño sobre texto de contenido; las excepciones se documentan en el archivo de pantalla.
12. **Prohibido trasladar al DS los patrones del responsive legacy**: nada de `font-size: .52rem/.58rem/.62rem/.72rem` (ni equivalente 8–12 px) sobre texto de contenido móvil. La interfaz debe parecer creada PARA móvil (`inicio-mobile.png` es la referencia de escala global), no un desktop reducido.

## 3. Breakpoints del DS

| Rango | Clase JS | Layout |
|---|---|---|
| ≤767 px | `.phone` | Feed 1 columna, sheets completas |
| 768–1023 px | `.phone`/`.pc` (según 720) | Shell compacto, capas ancho completo |
| ≥1024 px | `.pc` | Shell 3 columnas + cajones laterales |

⚠️ **Nota técnica (no tocar en FASE 3 sin decidir)**: el corte JS (720) y el CSS (768) difieren. El DS usa 768/1024 en sus media queries y no modifica `layout()` de `play-v3.js:544`. Unificar cortes es candidato a limpieza en FASE 4 con pruebas en el rango 720–768.

## 4. Estrategia desktop

**Mismo ADN, mejor aprovechado** (referencia `inicio_pc.png`):

1. El shell de 3 columnas se mantiene: izquierda actividad (Mensajitos, vecinos, próximo plan), centro mapa, derecha personas (misiones, en curso, cotilleo, parejas). **Sin dock nuevo (D1)**.
2. Las capas abren como **cajón lateral** `min(460px, 46%)` según `data-capa-origin` (mecánica actual intacta).
3. Escalado tipográfico: títulos +10–20%, cuerpo estable 15–16 px (no agrandar todo: más información visible, no más grande).
4. Composición horizontal cuando aporte: ficha con secciones en 2 columnas, grid de vecinos 3–4 col., RelationshipCards más anchas.
5. Hover solo desktop (lift sutil); el resto de estados idénticos al móvil.
6. Mapa central más grande, mismas proporciones 618:404; sin zonas vacías enormes: las columnas laterales absorben el contenido.
7. **Nunca estirar el móvil a 1440 px**: cada componente define su variante `@media (min-width: 1024px)` en su archivo de pantalla.
8. Evitar "otra aplicación": mismos papeles, cintas, costuras y voz en ambos tamaños.

## 5. Checklist por pantalla antes de dar por migrada

- [ ] Legible a 360 px (cuerpo ≥15 px, sin truncados).
- [ ] Targets ≥44 px y CTA 56 px.
- [ ] Jerarquía clara a 3 metros (título rotulador domina).
- [ ] Estados NUEVO/bloqueado/vacío presentes con datos reales.
- [ ] Decoración ≤2 elementos por tarjeta y sin tocar formularios.
- [ ] Sin anillas/espirales (regla global).
- [ ] Contraste ≥4.5:1 en texto funcional.
- [ ] `data-*` y comportamiento intactos (prueba de humo: abrir/cerrar, history back, scroll-lock).
- [ ] Desktop ≥1024 px sin zonas vacías ni estiramientos.

## 6. Estrategia anti-legacy (convivencia y salida de la deuda)

**El problema**: `play-v3-responsive.css` (~4.875 líneas, 43 media queries) y `play-v3-bloques-residencias.css` (~117 KB) sostienen el móvil actual con `font-size: .52–.72rem !important` y parches acumulados ("batch 12, 13, 14, 15…"). El DS no puede ganar una guerra de especificidad: la estrategia es **cargar después, no pelear, y borrar por pantalla**.

### 6.1 Orden de carga (contrato futuro de `play.php`)

Orden actual (play.php:24-36): `play-v3.css → capas → app → shell-ui → shell-art → capas-shell → mensajitos → mapa-canonico → bloques-residencias → musica → audio → lab → responsive`.

Orden futuro — el DS se añade **al final, tras `responsive.css`**, en este orden fijo:

```
... play-v3-responsive.css            (legacy, congelado)
    design-system/tokens.css          (variables + PUENTE LEGACY)
    design-system/components.css      (primitivas .ds-*)
    design-system/screens/<pantalla>.css   (uno por pantalla migrada)
```

Con esto el DS gana por **orden de cascada a igual especificidad**, sin `!important`. El PUENTE LEGACY de `tokens.css` remapea `--paper/--ink/--pink/...` y debe declararse después de cualquier definición legacy de esas variables → por eso tokens va al final, no tras `app.css`.

### 6.2 Política de `!important`

1. **Prohibido en CSS nuevo** (tokens/components/screens). Excepción única: override puntual sobre un `!important` legacy imposible de eliminar aún; debe documentarse en la cabecera del archivo con motivo y paso de eliminación previsto.
2. **Regla de oro**: nunca se responde a un `!important` antiguo añadiendo otro. Si un legacy gana, o se elimina esa regla (pantalla ya migrada) o se espera el turno de la pantalla.
3. Preferir selectores por atributos de contrato existentes (`[data-capa="mensajitos"]`, `[data-coti-filtro]`) — especificidad natural alta sin trucos.

### 6.3 Migración por pantalla (procedimiento)

1. Crear `screens/<pantalla>.css` con cabecera (pantalla, referencia, decisión D*, fecha).
2. Aplicar clases `.ds-*` al HTML existente **sin tocar contratos** (`data-*`, History API, `setCapa`).
3. **Eliminar del legacy** las reglas de esa pantalla que quedan sustituidas (mismo commit o el inmediato): cada regla borrada en `responsive.css`/`bloques-residencias.css` es deuda saldada.
4. Tests de humo + commit por pantalla. Nada de mezclar dos pantallas en un commit.

### 6.4 ¿Eliminar o tapar? (criterio)

- **Eliminar**: la regla legacy contradice al DS y su pantalla ya está migrada → se borra. Es la opción por defecto.
- **Dejar viva**: la regla afecta a pantallas aún no migradas → se conserva hasta su turno (por eso el orden de §11 importa).
- **Prohibido**: tapar con un override permanente en el DS algo que puede borrarse del legacy. El override es solución temporal, nunca estado final.

### 6.5 Anti-acumulación

- `play-v3-responsive.css`, `bloques-residencias.css` y demás legacy quedan **congelados**: cero estilos nuevos (solo eliminaciones o bugfix crítico justificado en el mensaje de commit).
- **Prohibido** añadir "batch N" al final de `responsive.css`: todo estilo nuevo nace en el DS.
- Meta medible: cada paso de migración reduce líneas legacy; el estado por pantalla se anota en `04-screens.md` §12. El destino de la deuda es que `responsive.css` tienda a 0 y desaparezca del `<head>`.
