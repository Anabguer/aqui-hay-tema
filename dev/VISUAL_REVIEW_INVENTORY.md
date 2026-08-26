# Inventario revisiÃÂ³n visual completa

Rama: `feat/visual-review-completa`  
Regla: screenshot/prototipo = diseÃÂ±o; runtime = funcionalidad.

## Referencias primarias (`DiseÃÂ±o_Ayuda/`)

| Pantalla runtime | Capa / selector | Referencia mÃÂ³vil | Referencia adicional |
|---|---|---|---|
| Inicio | shell juego | `DiseÃÂ±o_Ayuda/news/inicio-mobile.png` | `inicio_pc.png` |
| Mensajitos | `capa-buzon` | `news/mensajitos-mobile.png` | `Mensajitos.png` |
| Vecinos | `capa-vecinos` tab VECINOS | `news/vecinos-mobile.png` | `vecinos.png` |
| Relaciones | `capa-vecinos` tab RELACIONES | `news/relaciones-mobile.png` | propuestas INDEX 05 |
| Ficha vecino | `capa-ficha` | `news/ficha-vecino-mobile.png` | `Ficha Vecino.png` |
| Estado de ÃÂ¡nimo | `capa-ficha` bloque ÃÂ¡nimo | `news/estado-animo-mobile.png` | propuestas 07 |
| Diario vecino | `capa-ficha-diario` | `news/diario-mobile.png` | propuestas 08 |
| Cotilleos | `capa-diario` | `news/cotilleos-mobile.png` | `Cotilleos.png` |
| Nuevo plan | `capa-organizar` | `news/nuevo-plan-mobile.png` | `Nuevo Plan.png` |
| Agenda | `capa-agenda` | propuestas 10 | Ã¢ÂÂ |
| Misiones | `capa-misiones` | propuestas 11 | inicio feed |
| Vida pueblo | `capa-vida-pueblo` | propuestas 12 | corazÃÂ³n HUD |
| IntervenciÃÂ³n encuentro | enc-int overlay | propuestas 13 | `play-v3-enc-int.css` |
| Notas mapa | notas-mapa | propuestas 14 | Ã¢ÂÂ |
| Tutorial | tutorial DS | `news/tutorial-1..5.png` | `DiseÃÂ±o_Ayuda/tutorial/` |
| Avisos / derrota | toasts + vida-derrota | propuestas 16 | `play-v3-avisos.css` |
| Inventario / regalos | `capa-inventario` | regalos assets | `play-v3-regalos.css` |

## Commits sesiÃÂ³n 2026-08-26

- `7bf7c8a` checkpoint inicial (UI + refs DiseÃÂ±o_Ayuda)
- `db2bde9` pasada transversal shell modales (`play-v3-visual-review.css`)
- `770e22e` log progreso inventario
- `533fe47` pasada interior (`play-v3-visual-interior.css`)

## Tabla final de revisiÃÂ³n

| Pantalla | Referencia | Iter. mÃÂ³vil | Iter. desktop | Estado | Diferencias restantes |
|---|---|---:|---:|---|---|
| Shell transversal | Ã¢ÂÂ | 1 | 1 | OK | Ã¢ÂÂ |
| Inicio | inicio-mobile / inicio_pc | 2 | 2 | OK | Encoding local `?` en textos (no CSS); misiones/parejas con poco contenido en partida vacÃÂ­a |
| Mensajitos | mensajitos-mobile | 2 | 2 | OK | VacÃÂ­o sin mensajes; hint lavanda OK |
| Vecinos | vecinos-mobile | 2 | 2 | OK | Sin vecinos en partida DEBUG local; tabs/buscador/hints OK |
| Relaciones | relaciones-mobile | 1 | 1 | OK | Sin datos de parejas; estructura tabs OK |
| Ficha vecino | ficha-vecino-mobile | 2 | 2 | OK | Slots vacÃÂ­os sin residentes; secciones/CTA/Diario OK |
| Estado de ÃÂ¡nimo | estado-animo-mobile | 1 | 1 | OK | Modal no abierto (sin ÃÂ¡nimo activo); estilos en `play-v3-ficha.css` verificados |
| Diario vecino | diario-mobile | 2 | 2 | OK | Filtros TODO/PLANES/REL/CAMBIOS + buscador OK; sin entradas |
| Cotilleos | cotilleos-mobile | 2 | 1 | OK | Filtros ocultos sin cotilleos (funcional); libreta/tÃÂ­tulo OK |
| Nuevo plan | nuevo-plan-mobile | 2 | 1 | OK | Pasos 1-4 + Solo/AcompaÃÂ±ado + CTA rosa OK |
| Agenda | propuesta 10 | 2 | 1 | OK | VacÃÂ­o ÃÂ«Nada en agendaÃÂ»; icono calendario OK |
| Misiones | propuesta 11 | 2 | 1 | OK | ÃÂ«Hoy en el puebloÃÂ» + vacÃÂ­o OK |
| Vida pueblo | propuesta 12 | 2 | 1 | OK | Medidor 0/100 + caja lavanda explicativa OK |
| IntervenciÃÂ³n | propuesta 13 | 1 | 1 | OK | CSS `play-v3-enc-int.css` + markup JS revisados; sin encuentro activo en local |
| Notas mapa | propuesta 14 | 1 | 1 | OK | Consulta lugar (CafeterÃÂ­a) en runtime; X unificada en interior CSS |
| Tutorial | tutorial-1..5 | 2 | 1 | OK | Intro libreta + Saltar; alturas DS restauradas |
| Avisos / derrota | propuesta 16 | 2 | 1 | OK | Derrota ÃÂ«Se nos va de las manosÃÂ» + CTA rosa OK |
| Inventario | regalos assets | 2 | 1 | OK | Cabecera/subtÃÂ­tulo OK; min-height aÃÂ±adido en interior pass |

### Sin deploy a producciÃÂ³n

ComparaciÃÂ³n visual realizada en `http://127.0.0.1:8765/play.php` (mÃÂ³vil 393px + desktop 1280px) contra PNGs en `DiseÃÂ±o_Ayuda/news/` y propuestas INDEX.

## Validacion con contenido real (2026-08-26, fase 2)

Arnes: `dev/visual_validate_content.php` + `VisualApiContextFactory` (refresh PHP sin API/DB local).
Partidas: `e2erit-part_5af4821` (principal), `part_64d94bdea0acca34` (cotilleos), fixture `dev/screenshots-ds-propuestas/mensajitos-validate-fixture.html` (carta accionable).

| Pantalla | Contenido probado | Movil | Desktop | Correcciones necesarias |
|---|---|---|---|---|
| Inicio | Dia 8, 8 vecinos, badge mensajitos, plan Raul-Sara Parque HOY | OK | OK | Ninguna |
| Mensajitos | 21 mensajes (13 nuevos), tabs+badge, marcar leido; +1 accionable; fixture candidato | OK | OK | Ninguna |
| Vecinos | Grid 8 residentes, buscador, tabs | OK | OK | Ninguna |
| Relaciones | 6 parejas en save; pestana RELACIONES | OK | OK | Ninguna |
| Ficha completa | Perfil residente con retrato, hobbies, relaciones, planes | OK | OK | Ninguna |
| Estado de animo | Sin animo activo en saves locales escaneados | N/P | N/P | No poblable sin alterar datos; CSS OK fase 1 |
| Diario vecino | Sin entradas diario en residentes locales | N/P | N/P | No poblable sin alterar datos; filtros OK fase 1 |
| Cotilleos | 8 entradas (llegadas) en part_64d94bdea0acca34 | OK | OK | Ninguna |
| Agenda | 1 plan HOY 09:00 Raul-Sara Parque | OK | OK | Ninguna |
| Misiones | 3 misiones pendientes con copy real | OK | OK | Ninguna |
| Intervencion | Sin encuentro en_curso en saves locales | N/P | N/P | Markup/CSS revisados fase 1; no poblable |
| Inventario | celeste.inventario vacio en saves escaneados | N/P | N/P | No poblable sin alterar datos; shell OK fase 1 |

**NO DEPLOY.** Revision final de Neni sigue siendo en intocables13.com tras autorizacion.
