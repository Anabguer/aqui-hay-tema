# AQUÍ HAY TEMA

Juego de gestión social y emparejamiento. **Esta fase es solo documentación y estructura de datos.** No hay implementación, no está en el catálogo de producción y no debe desplegarse.

| Dato | Valor |
| --- | --- |
| Nombre definitivo | **Aquí Hay Tema** |
| Carpeta local | `W:\juegos\aqui-hay-tema` |
| Carpeta Drive (única referencia ChatGPT) | `G:\Mi unidad\Aqui Hay Tema` |
| Documento Maestro (Drive) | `DOCUMENTO MAESTRO — AQUÍ HAY TEMA` |
| Instantánea local del Maestro | `docs/FUENTE_DOCUMENTO_MAESTRO.md` |
| Referencia visual | `docs/referencias_visuales/REFERENCIA_VISUAL_01_AQUI_HAY_TEMA.png` |
| Estado | Documentación + puerta en la biblioteca. El bucle aún no se juega. |
| URL pública | https://intocables13.com/juegos/aqui-hay-tema/ |

La carpeta canónica de desarrollo es `W:\juegos\aqui-hay-tema`. La puerta pública del catálogo vive en `W:\juegos\aqui-hay-tema\public-door\`.
| Actualización | 2026-08-17 (decisiones posteriores al Maestro original) |

El título de trabajo *Proyecto Cupido Cutre* está **retirado**. El Google Doc y la carpeta de Drive se renombraron en el sitio; no hay una segunda carpeta activa.

## Principio

**El jugador propone. Los personajes viven.**

No es un sudoku de parejas. No es Los Sims. El jugador es **Celestine**: gestiona vínculos y convivencia de un pueblo que crece con dinero y fama. Visible narrativamente; sin avatar propio.

## Cómo leer esto (Cursor y ChatGPT)

1. `docs/ESTADO_PROYECTO.md` — fotografía actual del proyecto.
2. `PARA_CHATGPT.md` — punto de entrada en Drive.
2. `docs/DECISIONES_CERRADAS.md` — qué ha quedado cerrado.
3. `docs/FASE_DISENO_POOL_16.md` — plantilla y esqueleto.
4. `docs/CONTRATO_PERSONAJE.md` — entrega A+B (Neni ve solo B).
5. `docs/CONTRATO_VISUAL_PERSONAJES.md` — Cursor genera el retrato; Neni aprueba la cara.
6. `docs/CHECKLIST_AUDITORIA_PERSONAJES.md` — 2–3 pilotos; nadie es final sin auditoría.
7. `docs/PENDIENTES_DISENO.md` — huecos que siguen abiertos.
8. `docs/VISION_JUEGO.md` y el resto de `docs/`.
9. `data/personajes/_plantilla.personaje.json` — ficha canónica (alineada al contrato).

Si un documento de `/docs` contradice el Maestro **y** no está listado como decisión posterior en `DECISIONES_CERRADAS.md`, gana el Maestro. Si está listado ahí, gana la decisión posterior.

## Qué no hacer todavía

- No programar mecánicas, UI, simulador ni base de datos.
- No registrar cambios de bucle en `catalog/games.json`.
- No tocar producción ni otros juegos.
- No inventar los 16 para que Neni los firme uno a uno: 2–3 pilotos + auditoría del resto.
- No marcar una ficha `aprobado` sin `CHECKLIST_AUDITORIA_PERSONAJES.md`.
- No fijar economía, umbrales de fama ni duración de temporada.
