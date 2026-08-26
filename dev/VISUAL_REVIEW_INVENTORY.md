# Inventario revisión visual completa

Rama: `feat/visual-review-completa`  
Regla: screenshot/prototipo = diseño; runtime = funcionalidad.

## Referencias primarias (`Diseño_Ayuda/`)

| Pantalla runtime | Capa / selector | Referencia móvil | Referencia adicional |
|---|---|---|---|
| Inicio | shell juego | `Diseño_Ayuda/news/inicio-mobile.png` | `inicio_pc.png` |
| Mensajitos | `capa-buzon` | `news/mensajitos-mobile.png` | `Mensajitos.png` |
| Vecinos | `capa-vecinos` tab VECINOS | `news/vecinos-mobile.png` | `vecinos.png` |
| Relaciones | `capa-vecinos` tab RELACIONES | `news/relaciones-mobile.png` | propuestas INDEX 05 |
| Ficha vecino | `capa-ficha` | `news/ficha-vecino-mobile.png` | `Ficha Vecino.png` |
| Estado de ánimo | `capa-ficha` bloque ánimo | `news/estado-animo-mobile.png` | propuestas 07 |
| Diario vecino | `capa-ficha-diario` | `news/diario-mobile.png` | propuestas 08 |
| Cotilleos | `capa-diario` | `news/cotilleos-mobile.png` | `Cotilleos.png` |
| Nuevo plan | `capa-organizar` | `news/nuevo-plan-mobile.png` | `Nuevo Plan.png` |
| Agenda | `capa-agenda` | propuestas 10 | — |
| Misiones | `capa-misiones` | propuestas 11 | inicio feed |
| Vida pueblo | `capa-vida-pueblo` | propuestas 12 | corazón HUD |
| Intervención encuentro | enc-int overlay | propuestas 13 | `play-v3-enc-int.css` |
| Notas mapa | notas-mapa | propuestas 14 | — |
| Tutorial | tutorial DS | `news/tutorial-1..5.png` | `Diseño_Ayuda/tutorial/` |
| Avisos / derrota | toasts + vida-derrota | propuestas 16 | `play-v3-avisos.css` |
| Inventario / regalos | `capa-inventario` | regalos assets | `play-v3-regalos.css` |

## Progreso sesi�n 2026-08-26

### Commits
- 7bf7c8a checkpoint inicial (UI + refs Dise�o_Ayuda)
- db2bde9 pasada transversal shell modales (play-v3-visual-review.css)

### Iteraciones por pantalla (estado)
| Pantalla | Iter | M�vil | Desktop | Notas |
|---|---|---|---|---|
| Shell transversal | 1 | OK browser | OK browser | libreta, X ficha, auto-height |
| Inicio | 0 | previo | previo | inicio-override.css; pendiente re-validar |
| Mensajitos | 1 | OK browser | pendiente | tabs, hint, libreta |
| Vecinos/Rel | 1 | OK browser | OK browser | tabs, buscador, hints |
| Ficha/Diario/�nimo | 0 | pendiente | pendiente | CSS existente + shell |
| Cotilleos | 0 | pendiente | pendiente | |
| Organizar/Agenda | 0 | pendiente | pendiente | |
| Misiones/Vida/Tutorial/Avisos/Inv | 0 | shell only | shell only | |

### Sin deploy a producci�n
