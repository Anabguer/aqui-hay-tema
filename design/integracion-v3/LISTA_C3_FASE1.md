# LISTA_C3 — por fases (Carlos III)

V3 aprobada como dirección. Encargo original de C2: `design/prototipo-pantalla-principal/v3/LISTA_C3.md`.
Esto no lo sustituye: lo parte para no fabricar el catálogo a ciegas.

PNG RGBA, recorte sucio, paleta V3 (arena, rosa #e56b8a / #c42b4a, tinta #2c261f, ticket amarillo). PC y móvil comparten asset.

## FASE 1 — primera integración PLAY (esta entrega)

Suficiente para montar y jugar. No es acabado final.

### Pueblo
| Pieza | Estado | Archivo |
|---|---|---|
| Café & Libros | temprano = cafetería | `01_complejos/cafe_temprano.png` |
| Café & Libros | evolucionado = +biblio +tienda | `01_complejos/cafe_evolucionado.png` |
| Cine Game | temprano = cine + marquesina | `01_complejos/cine_temprano.png` |
| Cine Game | evolucionado = +arcade | `01_complejos/cine_evolucionado.png` |
| El Rincón de Lola | temprano = restaurante | `01_complejos/lola_temprano.png` |
| La Mala Idea | temprano = bar oscuro | `01_complejos/mala_temprano.png` |
| Parque | temprano = parque + fuente | `01_complejos/parque_temprano.png` |
| Gimnasio & Spa | temprano = gimnasio | `01_complejos/gym_temprano.png` |
| Bloque A | 1 edificio 3 plantas | `01_complejos/bloque_a.png` |
| Solar B / C | obra / vacío | `01_complejos/solar_b.png`, `solar_c.png` |
| Suelo + calles | cartón, no mapa ilustrado | `01_complejos/suelo_calles.png` |

Crecimiento a probar en PLAY: **Café** y **Cine** (2 complejos). El resto de alas, Fase 2.

### HUD
Corazón, ticket día, ticket dinero, sobre, lacre OJO.

### Marca «hay tema» (NO es el borde)
`04_marcas/sello_hay_tema.png` — sello/chincheta rosa pequeña, independiente del anillo emocional.
El borde del token lo pinta la UI (neutro / verde / naranja / rojo). El PNG del token no lleva anillo de color.

### Capas esenciales
Libreta (tapa + hoja), bandeja buzón, masthead El Cotilleo, hoja Organizar, celo, chincheta.

### Dock
Cuatro sellos: Pueblo, Diario, Organizar, Vecinos.

### Tokens patrón M
Diámetro útil: **52 px móvil / 62 px PC**. Master 256, círculo, sin anillo horneado.
Lote técnico ~12–15 extremos. **No los 200.**

## FASE 2 — tras revisión de la integración
- Alas evolucionadas de Lola, Mala Idea, Parque, Gym.
- Más recortes de periódico / celo si C2 los pide.
- Lote grande de tokens **solo si** M funciona en PLAY real.

## FASE 3 — no ahora
P001–P200 completos.

## Para Carlos II (anillo vs cotilleo)
- Anillo = ánimo actual (cartón / verde / naranja / rojo). Muted, no feria.
- «Hay tema» = sello rosa encima, esquina del token, no el borde.
- El rosa no significa enamoramiento.
