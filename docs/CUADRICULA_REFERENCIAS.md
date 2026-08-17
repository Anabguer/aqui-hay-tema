# Cuadrícula obligatoria en referencias de mapa

Cualquier mockup o referencia visual **importante del mapa** debe existir en dos versiones de la **misma** imagen:

1. Imagen limpia (la que se ve como juego).
2. La misma, con cuadrícula y coordenadas superpuestas, sin tapar el diseño.

La referencia 01 ya lo hace en un solo PNG (mitad inferior). Las siguientes pueden ser dos archivos o un díptico, pero la proporción y el encuadre tienen que coincidir.

## Para qué

Para que se pueda dirigir el mapa con frases inequívocas:

- «mueve la biblioteca de B3 a D5»
- «la carretera entre F7 y F10 es demasiado ancha»

Sin cuadrícula, Cursor reproduce el tono pero no las posiciones.

## Estándar

Tomado de `REFERENCIA_VISUAL_01` y generalizable cuando el mapa crezca:

| Eje | Convención |
| --- | --- |
| Columnas | Números: 1, 2, 3… de izquierda a derecha |
| Filas | Letras: A, B, C… de arriba a abajo |
| Celda | Una unidad de tablero. Coordenada tipo `B3` = fila B, columna 3 |
| Tramo | Dos celdas: `F7–F10` |

En la referencia 01 el grid de ejemplo es **filas A–J** y **columnas 1–16**, con leyenda «cada casilla es 1 unidad». Ejemplos anotados ahí (solo de esa maqueta, no del mapa final):

- Biblioteca B2
- Cafetería B5
- Parque (centro) B9
- Arcade B13
- Plaza (fuente) E7
- Bingo H6

El mapa definitivo será **más grande**: el mismo sistema de coordenadas, más letras/números. No reutilizar esas celdas como layout cerrado.

## Reglas de dibujo

- Misma proporción y recorte entre versión limpia y versionada.
- Líneas de cuadrícula visibles pero finas; no esconder edificios ni cartelitos.
- Etiquetas de fila/columna en el margen, no solo en el centro.
- Si un edificio ocupa varias celdas, se documenta la celda ancla (esquina o centro) y el tamaño en unidades.
- Calles también se pueden nombrar por celdas («carretera F7–F10»).

## Nomenclatura de archivos (cuando haya más)

```text
docs/referencias_visuales/
  REFERENCIA_VISUAL_01_AQUI_HAY_TEMA.png          # díptico actual
  REFERENCIA_VISUAL_02_MAPA.png                   # limpia (futuro)
  REFERENCIA_VISUAL_02_MAPA_CUADRICULA.png        # misma + grid (futuro)
```

No generar todavía la 02: esta fase no dibuja el mapa grande.
