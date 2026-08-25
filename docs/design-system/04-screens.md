# 04 · Mapa pantallas ↔ referencias ↔ componentes DS

Incluye las investigaciones de datos reales (D6 ánimo, D7 diario), el mapa de migración completo (§12) y el orden exacto (§11).

Leyenda de estado: 🔜 pendiente · ✅ migrada · ⛔ fuera de alcance (solo D1).

---

## 1. Inicio (móvil) — D2 APROBADO: feed de 1 columna

**Referencia**: `inicio-mobile.png` · **Estado**: 🚀 **INICIO MOBILE V3.1 — DESPLEGADO Y VALIDADO EN PRODUCCIÓN** (buster `v3-20260825-182940`; posteriormente el deploy concurrente "entender-p0" preservó íntegro el estado DS y avanzó el buster a `v3-20260825-entender-p0-152744`).
**Captura contractual**: `dev/screenshots-ds-piloto-inicio/inicio-mobile-393-v3.1.png`
**Captura de producción validada**: `dev/screenshots-ds-piloto-inicio/inicio-mobile-393-produccion.png`
**Implementación**: `assets/css/design-system/screens/inicio.css` (cargado tras responsive.css) + hunks en `play.php` (3 links) y `play-v3.js` (candado D9).
**Registro de deploy (2026-08-25)**: backup PRE en `dev/_pre_v31_backup/` (play.php 51.651 B · responsive 134.585 B · play-v3.js 219.735 B + manifest SHA256); reconciliación sobre blobs remotos (strip 269 `!important` de Inicio, 0 reglas R08 tocadas, marcadores mensajitoTopPreview/mensajitoTsKey preservados); tests 7/7 OK + sintaxis PHP/JS OK; validación en producción con partida TEST `part_80b2fae4d2b6a81b` (393px: tiles alineados, PLAN, Cotilleo/Misiones/Parejas con datos reales, 0 errores JS, PC humo OK) y **partida TEST borrada** tras validación.
**Patrón a heredar**: marco de familia tinta+costura (radios asimétricos 8 valores), sonda de alineación por píxeles (`dev/sonda_alineacion_tiles.js`), strip de `!important` por módulo (`dev/_ds_strip_inicio_important.js`), regla coral→rosa, capturas a DPR 2.

Estructura aprobada del feed (de arriba a abajo), **solo módulos canónicos reales**:

1. **Cabecera real**: marca + corazón Vida del Pueblo (funcionalidad canónica actual), día·fecha, reloj, `Pasar el rato`.
2. **Fila de accesos**: Mensajitos (con badge), Vecinos (preview 5/46), **CTA + Nuevo Plan** (coral, grande).
3. **Mapa** del pueblo (ilustración + tokens reales). Candados **solo** donde el juego ya calcula "cerrado ahora por horario" (D9: `abierto_ahora`); nunca bloqueo ficticio.
4. **EventBanner** — solo cuando exista evento próximo/en marcha según reglas futuras (EV: no implementar comportamiento).
5. **Tira de cotilleo** del día (nota chinchetada).
6. **Planes en curso** (carrusel horizontal real `.encursos-movil`) + VER TODOS.
7. **Próximos planes** (2 cards punteadas) + VER TODOS.
8. **Misiones** (checklist de papel) + VER TODAS.
9. **Parejas** (estado vacío ilustrado o lista real).

**Prohibido introducir**: nivel, monedas, vida ficticia, avatar de jugadora, HUD inventado. El corazón es el de Vida del Pueblo real.

**Notas técnicas**: el feed reordena el contenido móvil existente (hoy columnas apiladas del shell). Los `data-*` de cada módulo se conservan; cambia el contenedor y el orden visual en móvil. En desktop el shell de 3 columnas se mantiene (sin dock nuevo, D1).

## 2. Inicio (desktop)

**Referencia**: `inicio_pc.png` · **Estado**: 🔜 (ajuste fino, paso 9)

Coincide con el shell real (izq. actividad / centro mapa / der. personas). Se aplica el skin DS. **Sin dock COLECCIÓN/AJUSTES (D1)**.

## 3. Mensajitos — D3 APROBADO (tabs NUEVOS/TODOS)

**Referencia**: `mensajitos-mobile.png` · **Estado**: 🔜 (paso 4)

- PaperModal con cinta superior + cabecera (sobre + título + badge "N nuevos" + close).
- **Tabs cosidas `NUEVOS (n) | TODOS`**: mejora funcional menor aprobada. El estado de tab vive en `data-buzon-tab` (UI); el render sigue usando los datos reales del buzón (no leídos vs todos). Se conserva "marcar todos leídos" como acción accesible (icono o dentro de TODOS) para no perder función canónica.
- MessageCards con estados NUEVO / caduca / VISTO + ticket XP + CTA ABRIR/VER.
- Hint inferior lavanda.
- **Base real**: `.capa-buzon`, `renderBuzon`, `[data-accion]` intactos.

## 4. Vecinos + Relaciones — D4 APROBADO (una ventana, dos pestañas)

**Referencias**: `vecinos-mobile.png`, `relaciones-mobile.png` · **Estado**: 🔜 (paso 5)

- **Una sola ventana** (`.capa-vecinos`) con pestañas `VECINOS | RELACIONES`. Estado en `data-vecinos-tab` (UI). No hay segunda capa: la arquitectura interna (capas + History API) no cambia.
- **Pestaña VECINOS**: pill contador "5 / 46 conocidos", buscador, grid 2 col. de NeighborCards (anillo emoción, EmotionBadge, barra de relación, "Por conocer" para desconocidos, AlertBadge "!" donde el juego ya lo calcula).
- **Pestaña RELACIONES**: chips-emoji de filtro (solo categorías con datos reales), select de ámbito, RelationshipCards A↔B con filas direccionales reales (lo que ya expone el motor por dirección), nota "DIFERENTE EN CADA SENTIDO" cuando aplique.
- La ficha sigue abriéndose desde ambas pestañas (mismo `[data-residente]`).
- El sub-overlay de relaciones de la ficha puede mantenerse como acceso rápido o converger en la pestaña (decisión fina en FASE 3, sin romper `data-ficha-rel-*`).

## 5. Ficha de vecino — D5 APROBADO (DIARIO + ‹ ›)

**Referencia**: `ficha-vecino-mobile.png` · **Estado**: 🔜 (paso 5)

- Sheet completa con cinta/pin; cabecera con `← VECINOS`, título, close.
- **Navegación ‹ ›** entre vecinos **reales** (la lista ya cargada en `[data-vecinos-list]`); reabre ficha vía el flujo existente (`residente.ficha`).
- **Botón DIARIO** (etiqueta de papel con punto rojo si hay novedades) → abre la pantalla Diario del vecino (§7).
- Perfil: avatar xl con anillo emoción, nombre grande, edad/trabajo/desde (campos reales de `FichaPlayVista`).
- Secciones con ribbons: **Lo que sabes de X** (rasgos con slots descubiertos/bloqueados reales), **Aficiones** (ídem), **Relaciones** (filas compactas + VER TODAS), **Próximos planes** (reales o EmptyState "agenda tranquila").
- CTA final `+ ORGANIZAR PLAN` (abre organizar con preset real existente).

## 6. Estado de ánimo — D6 APROBADO CON DATOS REALES (investigado)

**Referencia**: `estado-animo-mobile.png` · **Estado**: 🔜 parcial (paso 5, solo lo que hay datos)

**Qué expone HOY el motor (verificado en código):**

| Dato de la referencia | Disponibilidad real | Fuente |
|---|---|---|
| Emoción actual | ✅ | `residente.ficha` → `estado_animo` (`FichaPlayVista.php:134`) |
| Pista corta ("Algo reciente le ha sentado mal") | ✅ | `residente.ficha` → `pista_estado` (`EmocionalNarrativa::pistaFicha`) |
| Causa explicada ("Compartió un rato con…") | ✅ ya servida | `partida.refresh` → `vista_play.animo_explicacion.explicacion` (`PartidaService.php:441`, `EmocionalNarrativa::explicacionCompleta`) |
| "Desde hoy / hace N días" | ✅ | `animo_explicacion.desde_texto` |
| Enlace a entrada de diario | ✅ | `animo_explicacion.diario_evento_id` |
| Consecuencias cualitativas ("Puede rechazar algunos planes"…) | ⚠️ NO EXPUESTAS HOY | El motor tiene modificadores REALES (`EstadoEmocional::modificadores`: aceptar_planes, experiencia_encuentro, iniciativa_social/romantica, riesgo_conflicto, peticiones) pero **no llegan al cliente**. |

**Decisión aplicada (D6 cerrado)**: el modal se migra con banner de emoción + causa (`¿QUÉ HA PASADO?` = `explicacion`) + chip "Desde…" + `VER EN SU DIARIO` (datos ya servidos). La sección "MIENTRAS SIGA ASÍ" (consecuencias) **solo se renderiza cuando existan datos reales expuestos**; hoy eso requiere un puente de vista pequeño (exponer modificadores), que queda como candidato pendiente de aprobación propia. **Mientras no haya datos, la sección se omite: nunca se inventan consecuencias.**

## 7. Diario del vecino — D7 APROBADO CON CONDICIÓN (investigado)

**Referencia**: `diario-mobile.png` (SIN anillas) · **Estado**: 🔜 (paso 8, tras puente API)

**Qué existe HOY (verificado):**

- `partida['diario']` con entradas: `id, dia, tipo, titulo, texto, consecuencias, actores, origen{evento_id, tipo_evento, es_narrativo}, fecha_corta, fecha_iso, dia_semana_ui`.
- `DiarioEngine::listarPorResidente(partida, residenteId)` — **ya implementado, solo lectura, más reciente primero** (`DiarioEngine.php`).
- Tipos reales: `encuentro · hito_relacion · llegada · rechazo · trabajo` (+ `origen.tipo_evento: estado_emocional_cambiado`).
- Escritura alimentada por `DiarioResidenteBridge` (hitos: flechazo, pareja, ruptura, crisis, discusión, declaración rechazada, trabajo, rechazos…).
- ⚠️ Precisión de API: el endpoint existente `DiarioHandler::listar` (`api/index.php:291`) expone `DiarioEngine::listarPorDia` — es el **diario del pueblo** que alimenta el modal de Cotilleos, NO el por-residente. Falta exponer `listarPorResidente`.

**Lo que falta (puente de lectura, NO simulación nueva):**

- Una acción de API que exponga `listarPorResidente` (p. ej. `diario.residente {residente_id}` en `DiarioHandler`, o incluirlo en el payload de `residente.ficha`). Decisión de implementación para FASE 3.

**Veredicto**: ✅ viable sin inventar gameplay. UI: timeline con nodos por tipo, cabeceras de día (usa `fecha_corta`/`dia_semana_ui` reales), filtros TODO/PLANES/RELACIONES/CAMBIOS mapeando los tipos reales, buscador por texto, tag de categoría, botón DIARIO en ficha (D5) como entrada. El FAB "IR A FECHA" queda **aplazado** (no hay saltos por fecha en datos; se documenta como futuro).

## 8. Nuevo Plan

**Referencia**: `nuevo-plan-mobile.png` (sin anillas) · **Estado**: 🔜 (paso 6)

Mapeo casi 1:1 con el organizar real:

| Ref | Real |
|---|---|
| Segmented SOLO / ACOMPAÑADO | chips `[data-org-modo]` |
| Paso 1 ¿Quiénes van? + picker + "Resultado incierto" | `[data-org-picker]` + hint existente |
| Paso 2 ¿Qué harán? (cards tipo) | `[data-org-tipos]` |
| Paso 3 ¿Dónde? (card lugar con horario) | `[data-org-dd-lugar]` + `[data-org-lugar-horario]` |
| Paso 4 ¿Cuándo? (fecha/hora + hint compatible) | `[data-org-dd-dia/hora]` + `[data-org-horas-hint]` (`agenda.slots_compatibles`) |
| Resumen "TU PLAN" + CTA CREAR PLAN | `[data-org-aviso]` + `[data-org-go]` → `encuentro.proponer` |

Todos los `data-org-*` se conservan; el formulario se re-estila como pasos numerados 1-4.

## 9. Cotilleos — D8 diferido a feature posterior

**Referencia**: `cotilleos-mobile.png` (SIN anillas; los puntos rojos laterales son ritmo de timeline, se conservan como decoración) · **Estado**: 🔜 (paso 7)

- Cabecera ilustrada (megáfono) + badge "N nuevos" + close.
- Filtros con icono: TODO / LLEGADAS / RELACIONES (+ categorías reales de `CotilleoCategoria`).
- **Sin filtro GUARDADOS ni botón ⭐ en esta primera migración (D8)**: es una feature posterior ya aprobada (persistencia por el patrón `cotilleoVisto`) que se implementará separada de la migración visual. El GossipCard reserva su hueco (ver `03-components.md`).
- Cabeceras de fecha ribbon (usa marcas temporales reales), GossipCards tintadas por categoría con cinta y NUEVO.
- **Base real**: `.capa-diario.coti-modal-papel`, `[data-coti-filtro]`, `cotilleoVisto`.

## 10. Pantallas sin referencia (D10) — diseño previo obligatorio

Vida del pueblo (modal) · Tutorial intro/finale · Toasts/plan-notif · Notas de mapa (selector/quién) · Derrota · Lab/debug (permanece técnico). **Cuando llegue su turno se diseñan siguiendo el ADN y se presentan como referencia antes de implementarse**; nada se implementa a ciegas.

## 11. Orden exacto de migración (FASE 3)

| # | Entrega | Contenido |
|---|---|---|
| 0 | Cableado | 2 `<link>` en `play.php` (tokens + components DESPUÉS de `play-v3-responsive.css`) + PUENTE LEGACY activado + fuentes a `<link>` con preconnect (R6) |
| 1 | Fundaciones visuales | Papel de fondo, textura, tipografías base, scrollbars, foco |
| 2 | Shell/HUD | Cabecera, tickets día/hora, corazón, botones audio/música, tiles |
| 3 | Inicio móvil (D2) | Feed 1 columna + componentes base en sus módulos + candados cerrado-ahora (D9) |
| 4 | Mensajitos (D3) | Tabs + MessageCards + hint |
| 5 | Vecinos+Relaciones (D4) y Ficha (D5) + Ánimo (D6 parcial) | Una ventana, ficha, navegación ‹ ›, botón DIARIO (maqueta sin destino hasta paso 8) |
| 6 | Nuevo Plan | Pasos 1-4 sobre `data-org-*` |
| 7 | Cotilleos | Filtros + GossipCards (sin ⭐; D8 va después) |
| 7b | **D8 Guardar cotilleo** | Feature separada: endpoint + persistencia (patrón `cotilleoVisto`) + ⭐ + filtro GUARDADOS |
| 8 | Diario vecino (D7) | Requiere puente API de lectura aprobado |
| 9 | Desktop fino | Cajones, densidad, composición horizontal |
| 10 | Limpieza | CSS V2 muerto (`.mesa`, `capa-residencias`, pops legacy) + QA tests + deploy con buster |

Cada paso: rama propia, commit descriptivo, tests, sin mezclar hilos (reglas `.cursor/rules/`).

## 12. Mapa de migración consolidado

Columnas: pantalla · referencia · componentes DS · archivos legacy que hoy la pintan · cambios funcionales aprobados · riesgo · orden.

| Pantalla | Referencia | Componentes DS | Archivos legacy actuales | Cambios funcionales aprobados | Riesgo | Orden |
|---|---|---|---|---|---|---|
| Inicio móvil | `inicio-mobile` | PaperCard, PlanCard, MissionCard, EventCard (futuro), EmptyState, NotificationBadge, PrimaryButton, AvatarGroup, candado D9 | `shell-art`, `app`, `responsive`, `capas-shell` | **D2**: feed 1 columna (reordenar DOM de `play.php`) | Medio: reordenado DOM + módulos con `data-*` vivos | 3 |
| Inicio PC | `inicio_pc` | Mismos + densidad 3 columnas | `shell-ui`, `shell-art`, `responsive` | Ninguno (sin dock, D1) | Bajo | 9 |
| Mensajitos | `mensajitos-mobile` | PaperModal, Tab, MessageCard, Ticket, Hint, CloseButton, Badge | `mensajitos.css`, `capas`, `responsive` | **D3**: tabs NUEVOS/TODOS (`data-buzon-tab`) | Medio: toque en `renderBuzon` | 4 |
| Vecinos | `vecinos-mobile` | PaperModal, Tab, NeighborCard, EmotionBadge, Progress, SearchField, AlertBadge, UnknownState | `bloques-residencias`, `responsive` | **D4**: pestaña en `.capa-vecinos` | Alto: archivo de 117 KB con `!important` | 5 |
| Relaciones | `relaciones-mobile` | Tab, ChipEmoji, RelationshipCard, Progress, Select | `bloques-residencias` (overlay), `responsive` | **D4**: convergen en pestaña (sin capa nueva) | Alto (mismo archivo) | 5 |
| Ficha vecino | `ficha-vecino-mobile` | Sheet full, Ribbon, Avatar xl, Locked/Unknown, RelationshipCard compacta, PlanCard, EmptyState, StepNav ‹ › | `bloques-residencias`, `responsive` | **D5**: flechas ‹ › + botón DIARIO (sin destino hasta paso 8) | Alto + R5 (recorte avatar) | 5 |
| Estado de ánimo | `estado-animo-mobile` | Sheet card, EmotionBanner, InfoNote causa, Hint | `bloques-residencias` | **D6**: causa/desde/enlace-diario ya servidos; consecuencias solo con datos reales | Bajo-medio | 5 |
| Nuevo Plan | `nuevo-plan-mobile` | Sheet full, StepNumber, Chip modo, Select, PlanCard resumen, CTA sticky | `bloques-residencias`, `responsive` | Ninguno (`data-org-*` intactos) | Medio: formulario largo + sticky | 6 |
| Cotilleos | `cotilleos-mobile` | PaperModal, Chip, GossipCard, Ribbon día, Flag NUEVO | `shell-art`, `app`, `responsive` | Ninguno en migración visual (**D8** después, paso 7b) | Bajo | 7 (+7b) |
| Diario de vecino | `diario-mobile` (sin anillas) | Sheet full, Timeline, Chip filtro, SearchField, Ribbon día | — (pantalla nueva) | **D7**: pantalla nueva + puente API lectura por residente | Alto: pieza nueva end-to-end | 8 |
| Planes en curso | `inicio-*` | PlanCard EN CURSO, carrusel `.encursos-movil` | `shell-art`, `responsive` | Ninguno | Bajo | 3 |
| Próximos planes | `inicio-*` | PlanCard punteada, Chip HOY·20:00, Ticket | `shell-art`, `responsive` | Ninguno | Bajo | 3 |
| Misiones | `inicio-*` (tarjeta SIN anillas) | MissionCard checklist | `shell-art`, `responsive` | Ninguno | Bajo | 3 |
| Parejas | `inicio-*` (`#mob-parejas`) | EmptyState doodle / AvatarGroup | `shell-art`, `responsive` | Ninguno | Bajo | 3 |
| Vida del pueblo | ⚠️ sin ref | — | `shell-art` | **D10**: diseño previo antes de implementar | Medio | post-9 |
| Tutoriales | ⚠️ sin ref | — | inline `play.php` | **D10**: diseño previo | Medio (copy canónico) | post-9 |
| Toasts/avisos | ⚠️ sin ref | Toast | `app` | **D10**: diseño previo | Bajo | post-9 |
| Notas de mapa | ⚠️ sin ref | InfoNote, PaperCard | `app` | **D10**: diseño previo | Bajo | post-9 |
| Derrota | ⚠️ sin ref | — | `shell-art`/inline | **D10**: diseño previo | Bajo | post-9 |

Los archivos legacy de cada fila se listan como **deuda a eliminar** en el paso correspondiente (ver `05-responsive.md` §6): al migrar una pantalla, sus reglas se quitan de esos archivos en el mismo paso, no se tapan.
