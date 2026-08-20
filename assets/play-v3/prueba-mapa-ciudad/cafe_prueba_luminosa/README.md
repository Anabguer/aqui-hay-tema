# Café & Libros — revisión luminosa (Neni)

## Rango RGB seguro (antes de regenerar)
Filtro: all>=210 | luma>=230&min>=195 | luma>=225&low_sat → transparente.

Colores claros SEGUROS usados:
- crema (228,200,155) · vainilla (235,205,130) · arena (220,185,130)
- Nunca los 3 canales >=205 a la vez.

## Continuidad
F1 = CAFETERÍA A
F2 = A (mismo módulo) + BIBLIOTECA
F3 = A + BIBLIOTECA + TIENDA

## Medidas
| Fase | tamaño |
|------|--------|
| F1 | 235×150 |
| F2 | 403×161 |
| F3 | 496×180 |

## Validación
- F1: 235x150 holes=0 black_px=0 OK
- F2: 403x161 holes=49 black_px=0 REVISAR
- F3: 496x180 holes=818 black_px=0 REVISAR

Ver `validacion/` (antes vs después del filtro + sobre mapa).
PLAY / otros complejos: no tocados.
