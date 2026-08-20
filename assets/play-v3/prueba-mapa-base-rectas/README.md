# Mapa base vacío — parcelas RECTAS (Carlos II)

**Estado:** PARA revisión Neni + ChatGPT.
**Arquitectura:** 1 mapa vacío + sprites encima (correcta).
**Esta entrega:** SOLO el mapa limpio.

## Archivo

`mapa_base_rectas.png` — canvas 1536×1024

## Qué incluye

- Calles y aceras **rectas / ortogonales** (horizontales y verticales).
- Parcelas **rectangulares**, paralelas a los bordes de pantalla.
- **5** solares vacíos + **1** parque fijo = 6 zonas utilizables.
- Estilo cómic ilustrado.
- Farolas y vegetación en bordes.
- Ningún edificio.
- Ningún overlay de cajas ni medidas.

## Zonas candidatas (sin coordenadas — las marca Neni)

1. Superior izquierda (ancha) → Café & Libros
2. Superior derecha → El Rincón de Lola
3. Medio izquierda → Cine Game
4. Centro → Parque (fijo en el mapa)
5. Medio derecha → La Mala Idea
6. Inferior ancha → Gimnasio & Spa

## Siguiente paso

1. Neni marca encima las cajas **máximas** de cada complejo.
2. C2 convierte marcas → x / y / ancho / alto.
3. Entonces se genera JSON/brief para C3.

## Qué NO es esta entrega

- No es brief C3.
- No hay cajas técnicas.
- No hay integración PLAY.
- Sustituye `prueba-mapa-base-vacio` (parcelas/calles no suficientemente rectas).
## Herramienta de marcado + preview

Abre en el navegador:

`file:///W:/juegos/aqui-hay-tema/assets/play-v3/prueba-mapa-base-rectas/marcar.html`

Incluye:
- cafetería C3 (preview/cafe_temprano.png) con escala y arrastre
- P001 antiguo vs nuevo (52/62 px)
- marcado de cajas máximas → JSON
