# Dirección artística

Ancla de mapa/UI: `docs/referencias_visuales/REFERENCIA_VISUAL_01_AQUI_HAY_TEMA.png` (ver `REFERENCIA_VISUAL.md`).

## Personajes

No realistas. Caricatura amable de juego social/casual (**v1 provisional**). **Ancla de retrato:** `assets/referencias_visuales/personajes/REFERENCIA_MAESTRA_PERSONAJES_v1.png`. El mapa/UI sigue anclado en las cabezas de chincheta de `REFERENCIA_VISUAL_01`, no un retrato semirrealista.

Contrato: `CONTRATO_VISUAL_PERSONAJES.md`. Reglas v1: `REGLAS_VISUALES_PERSONAJES_v1.md`. Pipeline: `PIPELINE_CABEZA_MAESTRA_EXPRESIONES.md`. 06b en pausa.

Rasgos diferenciados, edades adultas variadas, peinados distintos, gafas, pecas, narices diferentes, diversidad, siluetas reconocibles a tamaño de chincheta. Simpáticos e imperfectos. **No embellecer.** Variedad real de atractivo (atractivos, normales, peculiares, mayores).

Encuadre vigente v1: **cabeza maestra de frente**, cuello, nacimiento de hombros, ropa simple del look base. Identidad = ficha + cabeza maestra; la lámina es solo estilo.

Cursor genera el PNG. ChatGPT no manda imágenes sueltas. Neni aprueba o pide regenerar. Laboratorio actual: `assets/personajes/_laboratorio/` (no canon).

## UI y mapa

Blanco, limpio, sencillo. Tablero, calles simples, edificios reconocibles, cabezas en el mapa, tarjetas de pareja, estados claros, coletillas, dinero y fama.

Cutrez intencionada, no descuido. Demasiadas estadísticas matan el encanto: la barra 72/100 de la maqueta es decoración, no el HUD interno.

## Cuadrícula

Toda referencia importante de mapa: versión limpia + la misma con coordenadas. `CUADRICULA_REFERENCIAS.md`.

## Móvil + PC

Mobile-first en acciones. Escritorio: mapa más grande, panel. Sin depender de drag & drop.

## Assets

```text
assets/referencias_visuales/personajes/REFERENCIA_MAESTRA_PERSONAJES_v1.png  ← estilo v1
assets/personajes/aprobados/<SLOT>_<Nombre>/   ← solo Neni APROBADO (vacío)
assets/personajes/revision/                    ← Dani 06b histórico; no mezclar
assets/personajes/_laboratorio/LAB_##_Nombre/  ← pruebas NO CANON
```

Placeholders valen para programar el bucle si aún no hay cara aprobada. Ropa: look base en ficha; tienda fuera de V0.
Looks de cuerpo entero: más adelante. Ahora solo cabeza maestra + expresiones.
