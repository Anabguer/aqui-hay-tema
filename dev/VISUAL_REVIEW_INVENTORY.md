# Inventario revisiÃ³n visual completa

Rama: `feat/visual-review-completa`  
Regla: screenshot/prototipo = diseÃ±o; runtime = funcionalidad.

## Referencias primarias (`DiseÃ±o_Ayuda/`)

| Pantalla runtime | Capa / selector | Referencia mÃ³vil | Referencia adicional |
|---|---|---|---|
| Inicio | shell juego | `DiseÃ±o_Ayuda/news/inicio-mobile.png` | `inicio_pc.png` |
| Mensajitos | `capa-buzon` | `news/mensajitos-mobile.png` | `Mensajitos.png` |
| Vecinos | `capa-vecinos` tab VECINOS | `news/vecinos-mobile.png` | `vecinos.png` |
| Relaciones | `capa-vecinos` tab RELACIONES | `news/relaciones-mobile.png` | propuestas INDEX 05 |
| Ficha vecino | `capa-ficha` | `news/ficha-vecino-mobile.png` | `Ficha Vecino.png` |
| Estado de Ã¡nimo | `capa-ficha` bloque Ã¡nimo | `news/estado-animo-mobile.png` | propuestas 07 |
| Diario vecino | `capa-ficha-diario` | `news/diario-mobile.png` | propuestas 08 |
| Cotilleos | `capa-diario` | `news/cotilleos-mobile.png` | `Cotilleos.png` |
| Nuevo plan | `capa-organizar` | `news/nuevo-plan-mobile.png` | `Nuevo Plan.png` |
| Agenda | `capa-agenda` | propuestas 10 | â |
| Misiones | `capa-misiones` | propuestas 11 | inicio feed |
| Vida pueblo | `capa-vida-pueblo` | propuestas 12 | corazÃ³n HUD |
| IntervenciÃ³n encuentro | enc-int overlay | propuestas 13 | `play-v3-enc-int.css` |
| Notas mapa | notas-mapa | propuestas 14 | â |
| Tutorial | tutorial DS | `news/tutorial-1..5.png` | `DiseÃ±o_Ayuda/tutorial/` |
| Avisos / derrota | toasts + vida-derrota | propuestas 16 | `play-v3-avisos.css` |
| Inventario / regalos | `capa-inventario` | regalos assets | `play-v3-regalos.css` |

## Commits sesiÃ³n 2026-08-26

- `7bf7c8a` checkpoint inicial (UI + refs DiseÃ±o_Ayuda)
- `db2bde9` pasada transversal shell modales (`play-v3-visual-review.css`)
- `770e22e` log progreso inventario
- `533fe47` pasada interior (`play-v3-visual-interior.css`)

## Tabla final de revisiÃ³n

| Pantalla | Referencia | Iter. mÃ³vil | Iter. desktop | Estado | Diferencias restantes |
|---|---|---:|---:|---|---|
| Shell transversal | â | 1 | 1 | OK | â |
| Inicio | inicio-mobile / inicio_pc | 2 | 2 | OK | Encoding local `?` en textos (no CSS); misiones/parejas con poco contenido en partida vacÃ­a |
| Mensajitos | mensajitos-mobile | 2 | 2 | OK | VacÃ­o sin mensajes; hint lavanda OK |
| Vecinos | vecinos-mobile | 2 | 2 | OK | Sin vecinos en partida DEBUG local; tabs/buscador/hints OK |
| Relaciones | relaciones-mobile | 1 | 1 | OK | Sin datos de parejas; estructura tabs OK |
| Ficha vecino | ficha-vecino-mobile | 2 | 2 | OK | Slots vacÃ­os sin residentes; secciones/CTA/Diario OK |
| Estado de Ã¡nimo | estado-animo-mobile | 1 | 1 | OK | Modal no abierto (sin Ã¡nimo activo); estilos en `play-v3-ficha.css` verificados |
| Diario vecino | diario-mobile | 2 | 2 | OK | Filtros TODO/PLANES/REL/CAMBIOS + buscador OK; sin entradas |
| Cotilleos | cotilleos-mobile | 2 | 1 | OK | Filtros ocultos sin cotilleos (funcional); libreta/tÃ­tulo OK |
| Nuevo plan | nuevo-plan-mobile | 2 | 1 | OK | Pasos 1-4 + Solo/AcompaÃ±ado + CTA rosa OK |
| Agenda | propuesta 10 | 2 | 1 | OK | VacÃ­o Â«Nada en agendaÂ»; icono calendario OK |
| Misiones | propuesta 11 | 2 | 1 | OK | Â«Hoy en el puebloÂ» + vacÃ­o OK |
| Vida pueblo | propuesta 12 | 2 | 1 | OK | Medidor 0/100 + caja lavanda explicativa OK |
| IntervenciÃ³n | propuesta 13 | 1 | 1 | OK | CSS `play-v3-enc-int.css` + markup JS revisados; sin encuentro activo en local |
| Notas mapa | propuesta 14 | 1 | 1 | OK | Consulta lugar (CafeterÃ­a) en runtime; X unificada en interior CSS |
| Tutorial | tutorial-1..5 | 2 | 1 | OK | Intro libreta + Saltar; alturas DS restauradas |
| Avisos / derrota | propuesta 16 | 2 | 1 | OK | Derrota Â«Se nos va de las manosÂ» + CTA rosa OK |
| Inventario | regalos assets | 2 | 1 | OK | Cabecera/subtÃ­tulo OK; min-height aÃ±adido en interior pass |

### Sin deploy a producciÃ³n

ComparaciÃ³n visual realizada en `http://127.0.0.1:8765/play.php` (mÃ³vil 393px + desktop 1280px) contra PNGs en `DiseÃ±o_Ayuda/news/` y propuestas INDEX.
