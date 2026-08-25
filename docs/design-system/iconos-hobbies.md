# Iconos de hobbies — lote aprobado e integración preparada

**Estado:** ASSETS + RESOLVER listos. **Pendiente:** consumo definitivo por la Ficha de Vecino del Design System (layout, tamaños y sección AFICIONES finales siguen en manos de la migración DS). Sin deploy, sin cache-buster, sin merge.

---

## 1. Qué es

Lote de **17 iconos SVG aprobados** para todos los hobbies del catálogo funcional `data/catalogos/aficiones.json`.

Estilo (referencias aprobadas: Deporte y Pasear):

- Tinta oscura `#2c261f`, aspecto dibujado a mano (curvas irregulares, caps redondos).
- `viewBox="0 0 32 32"`, diseñados a tamaño real ~29,6px (caja `.ficha-hobby-ico` 1.85rem).
- **Máximo UN pastel del Design System por icono**, en superficie pequeña:
  mostaza `#F6E3AE`, rosa `#F9DCE4`, coral `#F7C9B9`, verde salvia `#D9E4CF`, lila `#E4DCF4`, azul grisáceo `#D8E0E8` (videojuegos, aprobado tal cual).
- **Tipografía SIEMPRE fuera del SVG**: el nombre del hobby sigue pintándose como HTML (Caveat). Prohibido `<text>`, raster, fuentes o referencias externas dentro de los SVG.

## 2. Ubicación canónica y mapping

| Pieza | Ruta |
|---|---|
| SVG canónicos | `assets/icons/hobbies/hobby-<id>.svg` |
| Catálogo fuente de IDs | `data/catalogos/aficiones.json` |
| Resolver (generado) | `assets/js/hobby-icons.js` → `window.AHTHobbyIcons` |
| Generador | `scripts/generar_hobby_icons_js.ps1` |
| Test técnico | `tests/hobby_icons_test.js` |

Correspondencia exacta por **ID canónico** (nunca por label/texto traducido):
`leer, escribir, pasear, correr, cafe_social, manualidades, cocina, musica, cine, videojuegos, copas, baile, bingo, deporte, senderismo, plantas, costura`

Excluidos por legacy/no funcionales: IDs de `data/catalogos/_propuesta_v2/aficiones.json` (`jardineria`, `perros`, `crucigramas`, …).

## 3. Resolver

`window.AHTHobbyIcons` (IIFE sin dependencias):

```js
AHTHobbyIcons.ids()        // ['leer', ...] 17 IDs
AHTHobbyIcons.has(id)      // true/false (clave = ID canónico)
AHTHobbyIcons.get(id)      // inner markup del SVG o null
AHTHobbyIcons.svg(id)      // '<svg class="ficha-hobby-svg" viewBox="0 0 32 32"...>...</svg>' o null
```

Regenerar tras añadir/modificar SVGs (evita drift fichero↔JS):

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/generar_hobby_icons_js.ps1
```

El generador falla si falta algún ID del catálogo, sobra algún SVG, o un archivo contiene `<text>/<image>/fuentes/refs externas`.

## 4. Consumo actual (cambio mínimo)

`play-v3.js` → `svgHobbyIcon(id, texto)`:

1. Consulta `window.AHTHobbyIcons.has(id)` **con guard** (si el script no cargó, todo sigue igual).
2. Si resuelve → devuelve el SVG del lote.
3. Si no → **fallback legacy**: mapa inline `svgHobbyPaths()` (iconos actuales). Un ID desconocido NUNCA rompe la ficha.

`play.php` carga `hobby-icons.js` justo antes de `play-v3.js` (mismo patrón de versión `$ahtUi` que sus vecinos).

Intacto en esta tarea: descubrimiento, número de slots, labels del catálogo, Caveat, interrogaciones/candados de slots bloqueados, backend de hobbies y el bloque pendiente «Le anima / No le gusta».

## 5. Tests

```bash
node tests/hobby_icons_test.js
```

Cubre: 17/17 IDs con archivo correcto · nombres = IDs canónicos · validez básica de cada SVG (viewBox, sin `<text>`/raster/fuentes/externos) · sincronía byte-level resolver↔archivos · fallback de ID desconocido/vacío/null · labels HTML fuera del SVG · slots bloqueados intactos · orden de carga en play.php · mapa legacy conservado.

Validador visual del lote (local, no commiteado como runtime): `dev/prueba-iconos-hobby/` (muestrario, control de coherencia, captura de integración usando el resolver real).

## 6. Pendiente (Design System)

- Sección AFICIONES definitiva: estructura, tamaños y convivencia con retratos/cintas/ánimo.
- Bloque «Le anima / No le gusta»: hoy apagado en ficha; decidir si lleva iconografía propia.
- Nuevos IDs de catálogo en el futuro → añadir `hobby-<id>.svg` + regenerar resolver (el test lo exige).
