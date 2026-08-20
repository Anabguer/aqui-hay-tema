# Patrón de edificio para el mapa PLAY — prueba de 3

Pregunta de aprobación: **¿parece que este edificio siempre ha pertenecido a este mapa?**

Referencia estética: `assets/play-v3/complejos/suelo_calles.png` (mapa actual de PLAY).
Apoyo de cómo se dibuja un pueblo sobre papel: `pueblo-tablero.png`.

## Qué se corrige

Los sprites actuales se leen como piezas de otro juego (maqueta 3D / papercraft) colocadas encima.
El mapa es ilustración artesanal: papel, lápiz/tinta, acuarela, imperfecciones.

## Cámara común (las 3 familias)

- Fachada principalmente visible (frente al sur).
- Tejado leído en ligera visión superior (plano de tejado visible).
- Costado: una franja fina a la derecha. No perspectiva lateral fuerte.
- No alzado frontal plano.
- Luz: arriba-izquierda; sombra corta abajo-derecha.
- Misma escala visual: local de pueblo, no render de ciudad.
- Mismo trazo: tinta fina un poco temblorosa, lavados de acuarela, grano de papel.

## Composición de crecimiento

El núcleo no se mueve. Las alas ocupan huecos transparentes del mismo lienzo.

| Familia | Núcleo (fijo) | Ala 1 | Ala 2 |
|---|---|---|---|
| Café & Libros | Cafetería (centro) | Biblioteca (izquierda) | Tienda (derecha) |
| Cine Game | Cine (izquierda-centro) | Arcade (derecha) | — |
| Gimnasio | Gimnasio (izquierda-centro) | Spa (derecha) | — |

Lienzo común por familia. El PNG base ya reserva el hueco de las alas (transparente).

## Prohibido en el PNG

Personas, tokens, NPC, acera, solar, calle. Eso lo pone el mapa o el motor.

## Esta entrega

Solo 3 familias. No Lola, Mala Idea, Parque, Bloque, 200 tokens, animaciones.
