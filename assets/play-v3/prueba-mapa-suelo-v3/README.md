# Prueba — mapa / suelo V3 (tablero ilustrado)

Carlos II. Una sola dirección. No integrada en PLAY.

## Pregunta

¿Ahora cafetería C3 y pueblo parecen del mismo juego?

## Entrega PC

1. `suelo_tablero_v3.png` — mapa limpio (sin edificios).
2. `cafe_sobre_mapa_nuevo.png` — mismo `cafe_temprano.png` de C3, sin modificar, en caja PLAY del café (~4% / 4% / 32% / 36%).
3. `comparacion_actual_vs_nuevo.png` — actual (`suelo_calles`) + café VS nuevo suelo + café.

También: `cafe_sobre_mapa_actual.png` (composite oficial de C3 sobre el mapa actual) y `cafe_temprano_REF.png` (copia de la referencia).

## Qué se conserva

- Arquitectura funcional del pueblo (parcelas para 6 complejos, Bloque A, solares B/C, parque/plaza, circulación).
- Edificios frontales hacia la jugadora (no rotados con la calle).
- Gameplay sin cambios.

## Qué cambia

- Lenguaje visual: contorno cómic, calles ilustradas, césped/árboles simplificados, color con personalidad.
- Sensación de tablero ligeramente levantado (no plano satélite).

## Qué NO se ha hecho

- No sustituir `suelo_calles.png` en PLAY.
- No retocar la cafetería C3.
- No encargar los otros 5 complejos.
- No HUD, no motor, no varias propuestas.

## Referencia

`../prueba-cafeteria-ref01/cafe_temprano.png`