# Design System — Aquí Hay Tema

Sistema de diseño oficial del juego. Documento vivo: manda sobre estimaciones, no sobre la funcionalidad canónica.

- **Referencias aprobadas**: `Diseño_Ayuda/news/` (10 imágenes, mobile-first + 1 desktop).
- **Pantalla canónica**: `play.php` (shell v3). Motor y contratos `data-*` **intocables**.
- **Estado**: FASE 2 — sistema definido, **sin migración visual aplicada todavía**.

---

## 0. Regla global: CERO ANILLAS

No forman parte del lenguaje visual de Aquí Hay Tema, en ninguna pantalla ni componente:

- ❌ libretas de anillas · ❌ agujeros laterales · ❌ espirales · ❌ encuadernaciones falsas.

Aparecen en algunas referencias generadas (`diario-mobile`, `nuevo-plan-mobile`, tarjeta Misiones de `inicio-mobile`) y **deben ignorarse siempre**.

Sí forman parte del ADN: papel, cintas adhesivas, post-its, ribbons, costuras punteadas, rayitas de énfasis, doodles controlados, chinchetas cuando tengan sentido.

## 1. Decisiones aprobadas (registro)

| ID | Decisión | Veredicto |
|---|---|---|
| D1 | Dock PC con COLECCIÓN / AJUSTES | **Fuera de alcance.** No existen como funciones. |
| D2 | Inicio móvil → feed de 1 columna mobile-first | **Aprobado**, conservando funciones canónicas. Prohibido inventar HUD (nivel, monedas, vida ficticia, avatar de jugadora). |
| D3 | Mensajitos con tabs NUEVOS / TODOS | **Aprobado** (mejora funcional menor asociada a la migración). |
| D4 | Vecinos + Relaciones en UNA ventana con pestañas `VECINOS \| RELACIONES` | **Aprobado** como decisión de producto, manteniendo la arquitectura interna (capas + contratos). |
| D5 | Ficha: botón DIARIO + navegación ‹ › entre vecinos reales | **Aprobado**. Solo datos reales. |
| D6 | Modal de ánimo (causa + consecuencias) | **Aprobado con datos reales**: causa desde `vista_play.animo_explicacion.explicacion` y "desde" desde `.desde_texto` (ya servidos, `PartidaService.php:441`). Consecuencias **solo si existen datos reales expuestos**; hoy el motor no los publica al cliente → sección omitida hasta aprobar el puente de vista. Nunca se inventan. Ver `04-screens.md` §6. |
| D7 | Diario del vecino | **Aprobado, pantalla futura**: el motor ya calcula (`DiarioEngine::listarPorResidente`, `DiarioEngine.php`) y `partida['diario']` existe; el endpoint actual `DiarioHandler::listar` expone el diario **por día** (alimenta Cotilleos), falta el puente de lectura **por residente** (pequeño, sin simulación). Ver `04-screens.md` §7. |
| D8 | Guardar cotilleos (⭐) | **Aprobado como feature posterior, separada de la primera migración visual.** Habrá persistencia server-side siguiendo el patrón existente de `cotilleoVisto` (`DiarioHandler.php`). La primera migración de Cotilleos va SIN ⭐ ni filtro GUARDADOS; el componente ya reserva su sitio (ver `03-components.md` §GossipCard). |
| D9 | Candados en edificios del mapa | **Aprobado con significado real**: candado = **lugar cerrado AHORA por horario** (datos que ya llegan: `abierto_ahora` en `play-v3.js:1676`, `lug.operativo/candado` en `play.js:649`). Nunca bloqueo progresivo ficticio. |
| D10 | Pantallas sin referencia (vida del pueblo, tutorial, toasts, notas de mapa, derrota, lab) | **No se implementan a ciegas**: cuando llegue su turno se diseñan siguiendo el ADN y se presentan como referencia antes de implementarse. El lab/debug permanece técnico. |
| D12 | Tipografía de título | **Caveat 700 primero.** No añadir otra fuente hasta evaluar la primera pantalla real. |
| EV | Banner de EVENTO (próximo / en marcha) | **Componente futuro documentado** en `03-components.md` §EventCard/EventBanner. Sin motor, endpoints ni comportamiento. Desaparece completamente cuando no hay evento. |

## 2. Estructura

```
docs/design-system/
  README.md            ← este archivo (índice + decisiones)
  01-foundations.md    ← ADN visual, principios, decoración, voz
  02-tokens.md         ← color, tipografía, espaciado, radios, sombras, motion
  03-components.md     ← catálogo de componentes + estados + EventBanner (futuro)
  04-screens.md        ← mapa pantalla↔referencia↔componentes + investigación D6/D7
  05-responsive.md     ← reglas mobile-first + estrategia desktop

assets/css/design-system/
  tokens.css           ← tokens --ds-* (se cargará tras play-v3-app.css en FASE 3)
  components.css       ← componentes base .ds-* opt-in (se cargará el ÚLTIMO)
  screens/             ← un CSS por pantalla migrada (FASE 3+)
```

**En FASE 2 estos CSS existen pero NO están enlazados desde `play.php`.** El cableado (2 `<link>`) se hace en FASE 3 con el primer paso de migración. Cero impacto en producción hasta entonces.

**Convivencia con el CSS legacy** (`play-v3-responsive.css`, `bloques-residencias.css` y su `!important`): estrategia completa en `05-responsive.md` §6. Resumen: el CSS nuevo va SIEMPRE después de `responsive.css`, sin `!important`, un archivo por pantalla en `screens/`, y cada pantalla migrada **elimina** sus reglas antiguas en vez de taparlas. Los archivos legacy quedan congelados: prohibido acumular "batch N" al final de `responsive.css`.

## 3. Principios no negociables

1. **Esto es un JUEGO, no una web decorada.** Riqueza gráfica, jerarquía marcada, objetos físicos (papel, cintas, sellos, botones táctiles).
2. **Reforma visual, no reconstrucción.** Se preservan: motor, estado, encuentros, planes, relaciones, misiones, tutorial, mensajitos, eventos, endpoints, handlers, atributos `data-*`, comportamiento responsive funcional.
3. **La referencia no manda sobre la funcionalidad real.** Si una imagen muestra algo que el juego no hace, manda el juego (o se decide como producto antes).
4. **Mobile-first.** Móvil es la referencia visual principal; desktop escala el mismo ADN.
5. **La decoración nunca perjudica la legibilidad.** Contraste mínimo 4.5:1 en texto funcional; máx. 2 elementos decorativos por tarjeta; nada decorativo dentro de formularios.
6. **Prohibido el "web minimalista con cuatro colores parecidos"** que aplane el diseño.

## 4. Flujo de fases

| Fase | Alcance | Estado |
|---|---|---|
| 1 | Inspección + análisis + propuesta | ✅ Aprobada |
| 2 | Documentación + tokens + componentes base (sin aplicar) | ✅ Esta entrega |
| 3 | Migración por pantallas (orden en `04-screens.md` §10) | ⏳ Pendiente de autorización |
| 4 | Desktop fino + limpieza deuda CSS V2 + QA | ⏳ |

Cada fase de migración: un commit por pantalla, tests antes de commit, cache-buster solo al deployar (reglas de `.cursor/rules/`).
