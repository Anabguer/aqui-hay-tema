# screens/ — CSS por pantalla (FASE 3+)

Aquí vivirán los overrides de selectores heredados, **un archivo por pantalla migrada**:

```
screens/
  shell.css          (HUD + cabecera + tiles)
  inicio.css         (feed móvil D2)
  mensajitos.css
  vecinos.css        (incluye pestaña Relaciones, D4)
  ficha.css          (incluye ánimo, D5/D6)
  nuevo-plan.css
  cotilleos.css
  diario.css         (si se aprueba el puente API, D7)
  desktop.css        (ajuste fino ≥1024px)
```

## Contrato de cada archivo

1. **Se carga EL ÚLTIMO** (después de `components.css`), añadido a `play.php` con el cache-buster estándar.
2. Cabecera obligatoria con: pantalla, referencia visual, decisión asociada (D2-D8), fecha.
3. Solo selectores heredados (`.capa-buzon …`) re-estilados con tokens `--ds-*` y clases `.ds-*` aplicadas al HTML.
4. **Prohibido**: tocar atributos `data-*`, selectores de contrato JS, `!important` salvo excepción documentada en cabecera.
5. Al migrar una pantalla, sus reglas se **eliminan** de los archivos antiguos (`play-v3-responsive.css`, `play-v3-bloques-residencias.css`…) en el mismo paso. Los archivos antiguos se congelan: cero estilos nuevos allí.

## Lecciones del piloto Inicio (FASE 3)

- **El legacy pinta con `border-image`/`mask` decorativos** (bordes de sierra tipo sello en los tiles). Toda superficie DS debe resetear explícitamente `border-image: none`, `mask-image: none` y `clip-path: none` además de declarar `border`/`border-radius`.
- Las reglas `!important` de tamaño/padding/borde de los módulos de la pantalla migrada se retiran con el script `dev/_ds_strip_inicio_important.js` (informe en `dev/_ds_strip_report.txt`); las de piel (tipografía/color/fondo) también. Nunca se responde con otro `!important`.
- Capturas: renderizar a `deviceScaleFactor: 2` (DPR real de móvil). A 1x, el rasterizador de Chromium degrada los bordes dashed/radius del feed y falsifica la validación visual.

## Estado

Piloto Inicio móvil: `inicio.css` (FASE 3, en revisión). Resto vacío.
