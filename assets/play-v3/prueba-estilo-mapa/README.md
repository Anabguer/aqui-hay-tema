# Prueba de estilo — 3 familias sobre el mapa PLAY

V3 dirección general: aprobada.
Esto **no** es producción del catálogo. Solo café, cine y gym, para cerrar el lenguaje visual **en el mapa real**.

## Pregunta de revisión

¿Parece que este edificio siempre ha pertenecido a este mapa?

Si los tres pasan: se autoriza el resto.
Si no: se corrige cámara/trazo, no se fabrica Lola/Mala/Parque/200 tokens.

## Qué hay

`sprites/` — PNG transparentes, sin personas, sin acera.

| Archivo | Fase |
|---|---|
| `cafe_temprano.png` | Cafetería |
| `cafe_biblioteca.png` | + Biblioteca (izquierda) |
| `cafe_evolucionado.png` | + Tienda (derecha) = final |
| `cine_temprano.png` | Cine |
| `cine_evolucionado.png` | + Arcade (derecha) |
| `gym_temprano.png` | Gimnasio (rehecho, sin NPC) |
| `gym_evolucionado.png` | + Spa (derecha) |

`laminas/mapa_temprano.png` y `mapa_evolucionado.png` = las tres piezas **sobre `suelo_calles.png`** (mapa actual de PLAY), en las cajas aproximadas de C2.

## Para Carlos II

No hace falta cambiar motor. Si se quiere verlo vivo en PLAY:

1. Copiar los `*_temprano.png` / `*_evolucionado.png` de esta carpeta sobre `assets/play-v3/complejos/` **después de backup**.
2. Gym pleno: PLAY aún no tiene `gym_evolucionado` enchufado; se puede dejar solo la base o añadir el `fachada-pleno` cuando toque.
3. Café tiene **tres** fases; PLAY hoy solo conmuta temprano/pleno. Para esta prueba, pleno = `cafe_evolucionado` (las tres naves). La fase intermedia `cafe_biblioteca` queda lista.

No duplicar lógica. No tocar economía.

## No hecho (a propósito)

Lola, Mala Idea, Parque, Bloque, solares, 200 tokens, animación, resto de expansiones.
