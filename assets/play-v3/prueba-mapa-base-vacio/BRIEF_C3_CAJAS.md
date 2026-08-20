# Brief C3 — cajas de complejos sobre mapa base vacío

**Arquitectura:** 1 mapa vacío (capa 1) + sprites independientes (capa 2).
Al evolucionar se **sustituye el sprite**, no el mapa.

**Canvas:** 1536 × 1024 px
**Origen:** esquina superior izquierda (x → derecha, y → abajo).
**Archivo mapa:** `mapa_base_vacio.png`

Cada sprite F-máx debe caber en `ancho_max` × `alto_max`.
Posición de colocación del PNG: esquina superior izquierda = (x, y).

## CAFE — Café & Libros

- **x:** 48
- **y:** 44
- **ancho_max:** 470
- **alto_max:** 240
- **crecimiento:** derecha
- **fases:** cafeteria, biblioteca, tienda
- **apron_frontal_px:** 48
- **notas:** Nucleo F1 a la izquierda; biblio+tienda a la derecha dentro de ancho_max. Escala ref C3 cafe_temprano.

## LOLA — El Rincón de Lola

- **x:** 1000
- **y:** 48
- **ancho_max:** 460
- **alto_max:** 270
- **crecimiento:** abajo-derecha
- **fases:** restaurante, bingo
- **apron_frontal_px:** 48
- **notas:** Bingo volumen secundario abajo-derecha.

## PARQUE — Parque

- **x:** 540
- **y:** 200
- **ancho_max:** 400
- **alto_max:** 420
- **crecimiento:** picnic SO + mirador NE (overlays)
- **fases:** base_en_mapa, picnic_overlay, mirador_overlay
- **apron_frontal_px:** 0
- **capa:** 1 (en el mapa). Overlays:
  - picnic: x=555 y=485 150×100
  - mirador: x=790 y=230 120×130
- **notas:** CAPA 1 permanente. Picnic/mirador = sprites opcionales encima.

## CINE — Cine Game

- **x:** 40
- **y:** 580
- **ancho_max:** 400
- **alto_max:** 250
- **crecimiento:** derecha
- **fases:** cine, arcade
- **apron_frontal_px:** 48
- **notas:** Arcade a la derecha; cabe en ancho_max.

## MALA — La Mala Idea

- **x:** 1090
- **y:** 380
- **ancho_max:** 390
- **alto_max:** 350
- **crecimiento:** derecha y abajo-derecha
- **fases:** bar, discoteca, karaoke
- **apron_frontal_px:** 48
- **notas:** Bar arriba-izq; disco derecha; karaoke abajo-derecha.

## GYM — Gimnasio & Spa

- **x:** 540
- **y:** 740
- **ancho_max:** 460
- **alto_max:** 210
- **crecimiento:** derecha
- **fases:** gimnasio, spa
- **apron_frontal_px:** 40
- **notas:** Spa ala derecha más baja/clara.

## Qué NO hacer

- No redibujar el mapa al evolucionar.
- No generar pueblo inicial / pueblo evolucionado como dos mapas.
- No dibujar edificios dentro de `mapa_base_vacio.png`.
- No integrar en PLAY todavía.