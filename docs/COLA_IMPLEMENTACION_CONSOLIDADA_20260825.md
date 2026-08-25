# COLA DE IMPLEMENTACIÓN CONSOLIDADA — CIERRE DE FASE DE AUDITORÍAS (2026-08-25)

**Propósito:** consolidar los hallazgos de las auditorías recientes en una cola REAL de implementación, para dejar de acumular «para luego» y empezar a construir.
**Este documento NO crea backlog nuevo:** solo consolida hallazgos ya detectados/documentados, persiste los que faltaban y clasifica cada pieza según dependencias REALES verificadas hoy sobre el árbol (`prepare/meterme-en-sus-cabezas` @ `58c476e`).
**Regla:** a partir de este documento, la fase de auditorías se considera CERRADA. Nueva detección → solo si aparece jugando/implementando, no otra ronda de auditoría preventiva.

---

## 1. Estado de persistencia de las auditorías recientes

### YA persistido (NO duplicado aquí)
| Auditoría / hallazgo | Dónde vive |
|---|---|
| **Vida autónoma no romántica** (H1–H11 + piezas propuestas 1–5) | `docs/HALLAZGOS_AUDITORIA_VIDA_AUTONOMA_NO_ROMANTICA.md` ⚠️ fichero AÚN SIN COMMIT (untracked). Revisado hoy contra código: sigue vigente. Único matiz actualizado: `MotorVidaDiaria.php` y `AcontecimientoDiario.php` YA ESTÁN COMMITTED (limpios) tras el deploy de Romance F1; el WIP vivo está en `InteraccionCasual.php`, `EncuentroResolver.php`, `RelacionEngine.php`, `Voluntad/VoluntadPonderadaEvaluator.php` y la capa UI/API |
| Embudo romance autónomo (diagnóstico estéril, P1–P6 propuesta sin aplicar) | `PLAN_MAESTRO_IMPLEMENTACION.md` §20 |
| Romance Autónomo FASE 1 desplegada + FASE 2 bloqueada | `PLAN_MAESTRO_IMPLEMENTACION.md` (bloque DEPLOY 2026-08-25 09:30) |
| Playtest integral: bugs B01–B32, S01–S11, M01–M13, R01–R07, P01–P07, F01–F02 | `PLAN_POST_AUDITORIA_PLAYTEST.md` |
| Bug B32 `$franja` indefinido | `PLAN_POST_AUDITORIA_PLAYTEST.md` §B32 ⚠️ hunk EN WORKING TREE sin commit (junto con cierre S11 y entrada de índice en `docs/README.md`: WIP de la sesión de auditoría anterior, pendiente de commitar por quien la cerró) |
| Recuperación narrativa/variedad/flechazo | `PLAN_POST_AUDITORIA_PLAYTEST.md` (bitácora) + commit `55e4621` |
| Diseño de eventos comunitarios (producto): §19.5 organizados por Celestine, F4 Mensajitos, §24.2 rituales/calendario, §19.7 personalidad de lugares | `PLAN_MAESTRO_IMPLEMENTACION.md` |

### NO estaba persistido → SE REGISTRA EN ESTE DOCUMENTO (§2)
**Auditoría técnica de EVENTOS DEL PUEBLO** — verificado hoy sobre código:
- No existe motor, DTO ni API real de eventos colectivos (sin `EventoColectivo*` ni equivalente en `src/`).
- `data/eventos/eventos_globales.json`, `eventos_pareja.json`, `microeventos_cita.json` existen en disco pero están HUÉRFANOS: ningún cargador los referencia (verificado por búsqueda global).
- El bloque PRÓXIMO EVENTO del Design System es solo visual/futuro: DOM solo en arnés de captura (`dev/ds_piloto_capturas.js`), CSS en `assets/css/design-system/screens/inicio.css` («EV futuro, sin motor»).
- Infraestructura reutilizable existente: Agenda, Reloj, Encuentros multi-participante (`EncuentroEngine::programar` + resolver por participante), Presencia, Aforo, Lugares, Mensajitos/Buzón, Cotilleos, VidaPueblo.
- Vía propuesta canónica: reutilizar encuentros multi-participante de origen sistema; PROHIBIDO un segundo motor paralelo (coherente con REGLAS_NO_NEGOCIABLES y §22.8).

---

## 2. REGISTRO NUEVO — Eventos del pueblo (hallazgos técnicos EV1–EV4 + MVP)

> Fuente: auditoría reciente de funcionalidad (2026-08-25), antes solo en conversación; ahora persistida aquí.

- **EV1 · Sin motor/DTO/API de eventos colectivos.** Lo más cercano son los acontecimientos diarios individuales/de pareja (`AcontecimientoDiario`) y los rituales como idea (§24.2). Clase: DEUDA FUNCIONAL. Prioridad ALTA (es la puerta de F4/§19.5/§24.2).
- **EV2 · Catálogos de eventos huérfanos.** `data/eventos/*.json` no cargados por nadie. Decidir: adoptarlos como base del catálogo cargable del MVP o sustituirlos por catálogo nuevo bajo `data/catalogos/` (patrón catálogos extensibles). Clase: DEUDA FUNCIONAL.
- **EV3 · PRÓXIMO EVENTO (DS Inicio) es maqueta.** Solo CSS+arnés; sin fuente de datos real. Clase: JUGABILIDAD/UX.
- **EV4 · Piezas reutilizables confirmadas.** Agenda (reserva conjunta), Reloj (anuncio/resolución por franja), Encuentros multi-participante con origen sistema, Presencia/Aforo/Lugares, Buzón/Mensajitos (anuncio), Cotilleos/Diario/VidaPueblo (consecuencias). La resolución del evento debe ser la infraestructura de encuentros existente, NO una nueva.

### MVP propuesto (coincide con lo acordado en auditoría)
1. **Noche de bingo** (`lug_bingo`, hobby `bingo` ya canon) como primer evento colectivo.
2. **Catálogo cargable** de eventos (MOTOR≠DATOS≠NARRATIVA).
3. **Planificador mínimo**: decide CUÁNDO (franja futura válida) y anuncia con antelación.
4. **Anuncio por Mensajito** (canon §19.5: Celestine decide lugar/fecha/invitados dentro de aforo; asistencia individual real vía agenda/voluntad).
5. **PRÓXIMO EVENTO real** en Inicio (fuente: estado del evento programado).
6. **Resolución** mediante infraestructura de encuentros multi-participante existente (intención propia p. ej. `evento_pueblo`, excluida de peticiones/Vida como Celestine-directo).

---

## 3. CLASIFICACIÓN 🟢🟡🔴 (verificada sobre git status hoy)

Leyenda: 🟢 implementable ya, sin colisión con WIP ajeno · 🟡 implementable con reconciliación quirúrgica (toca ficheros dirty/WIP activo) · 🔴 dependencia REAL impide implementarla ahora.

### Bloque A — Vida autónoma no romántica
| # | Pieza (ataca) | Estado | Prioridad | Valor jugable | Archivos probables | Colisiones | Worktree recomendado |
|---|---|---|---|---|---|---|---|
| A1 | IniciativaSocial: quedada deliberada NPC→NPC no romántica (H2) | 🟢 | ALTA | MUY ALTO: amistades visibles, cotilleos/diario/memoria sin Celestine | NUEVO `src/Engine/IniciativaSocial.php`; gatillo en `MotorVidaDiaria.php` o `AcontecimientoDiario.php` (ambos CLEAN); `MisionDiariaEngine.php` (lista intenciones no-Celestine, CLEAN); `calibracion_vida.json` (merge semántico patrón F1); tests nuevos | Ninguna conocida. NO reutilizar blob completo de `VoluntadPonderadaEvaluator.php` (dirty): usar subconjunto/patrón | Nuevo worktree desde HEAD actual (58c476e); patrón `tmp/wt_romance_fase1` |
| A2 | Selección social firmada + evitación por conflicto (H1) | 🟡 | ALTA | ALTO: deja de premiar enemistades en casuales | `InteraccionCasual.php` (⚠️ DIRTY), `MotorVidaDiaria.php:244` (CLEAN), tests distribución | WIP ajeno activo en InteraccionCasual → hunk quirúrgico + coordinación | Worktree propio; integrar tras liberar o parchear sobre blob vivo |
| A3 | Voluntad para salidas individuales (H3) | 🟢 | MEDIA-ALTA | MEDIO-ALTO: salidas coherentes con ánimo/ganas | `MotorVidaDiaria.php` (CLEAN), score local barato (subconjunto voluntad), `calibracion_vida.json` | Evitar tocar `VoluntadPonderadaEvaluator.php` (dirty) implementando evaluador local; si se exige reutilizar el evaluator completo → pasa a 🟡 | Worktree nuevo |
| A4 | Memoria/afinidad aprendida por lugar (H9) | 🟡 | MEDIA | MEDIO: variedad de rutinas, menos monocultivo de sitios | `EncuentroResolver.php` (⚠️ DIRTY, escritura), `LugarAutonomo.php` (CLEAN, lectura), runtime residente, `InteraccionCasual.php` (⚠️ DIRTY, patrón vía horaria) | Dos ficheros dirty | Tras A1–A3, con hunks quirúrgicos |
| A5 | Reconciliación autónoma de amistad (H10) | 🟡 | MEDIA | MEDIO-ALTO: historias de reparación | `data/catalogos/acontecimientos.json`, `AcontecimientoDiario.php` (CLEAN), `RelacionEngine.php` (⚠️ DIRTY, upsertConflicto negativo) | RelacionEngine dirty | Tras A2 (misma zona relacional) |
| A6 | Activar o retirar anti_aislamiento (H11) | 🟢 | BAJA | BAJO-MEDIO | SOLO `calibracion_vida.json` (código consumidor ya existe) | Ninguna (config limpia; requiere microdecisión de cifras en laboratorio) | Cualquiera |

### Bloque B — Eventos del pueblo (MVP bingo)
| # | Pieza | Estado | Prioridad | Valor jugable | Archivos probables | Colisiones | Worktree recomendado |
|---|---|---|---|---|---|---|---|
| B1 | Núcleo evento: DTO/catálogo cargable + planificador mínimo + creación encuentro multi-participante origen sistema + resolución por infraestructura existente | 🟢 | ALTA | ALTO (base de todo el bloque) | NUEVO `src/Engine/EventosPueblo.php` (+catálogo JSON), gatillo en `RelojOperations`/`MotorVidaDiaria` (CLEAN), `calibracion_vida.json` | Ninguna conocida en backend | Worktree nuevo desde HEAD |
| B2 | Anuncio por Mensajito + cierre post-evento | 🟡 | ALTA | ALTO (el evento se entera el pueblo) | `BuzonPlayBridge.php` (⚠️ DIRTY) o emisión directa vía `BuzonEngine` (CLEAN) + plantilla | Preferible emitir vía BuzonEngine/DomainEvents para evitar el bridge dirty; si toca bridge → hunk quirúrgico | Mismo worktree que B1 |
| B3 | PRÓXIMO EVENTO real en Inicio (UI DS) | 🟡 | MEDIA-ALTA | ALTO visible para Neni | `play.php` + `play-v3.js` (⚠️ MM staged+WIP ajeno activo), `inicio.css` DS (untracked), cache-buster | UI con WIP concurrente documentado (P06/P07 lineage) → REGLA OPERATIVA deploys | Esperar a liberar capa UI o integrador autorizado |

### Bloque C — Otras piezas de las auditorías ya registradas (referencia, no re-registradas)
| Pieza | Estado | Motivo |
|---|---|---|
| Romance Autónomo FASE 2 (romance_hito + familia pareja en play) | 🔴 | Dependencia REAL documentada: playtest humano de FASE 1 (esta noche) + decisión de canon de familias. No es «mejor después»: hay gate explícito |
| Reconciliación P2/extremos >0.85 (deploy voluntad) | 🔴 | Dependencia REAL: decisión de balance del usuario documentada en PLAN_POST_AUDITORIA (INCIDENTE). Código local listo pero deploy en espera |
| R07 cadencia B4 por población | 🔴 | Autorización explícita requerida (documentado: «NO implementar hasta autorización», opciones A–D ya entregadas) |
| H7 riesgo_conflicto con/sin consumidor | 🟡 | Implicados `EncuentroExperiencia.php`/`InteraccionCasual.php` (dirty). Requiere microdecisión: consumirlo o retirarlo de config |
| H8 max_historias_grandes con/sin consumidor | 🟢 (BAJA) | Hosts limpios (`AcontecimientoDiario`/`MotorVidaDiaria`); requiere decidir aplicar cap o borrar clave |
| H5 cablear ConocimientoNpc en planes acompañados | — | Se resuelve DENTRO de A1 (al existir planes acompañados autónomos, pasar `$otro`) |
| H6 peso de lugares_preferentes en LugarAutonomo | 🟢 (BAJA) | `LugarAutonomo.php`/`PerfilPartida.php` limpios; mantener contrato visual/RNG |

---

## 4. ORDEN DE IMPLEMENTACIÓN PROPUESTO

### IMPLEMENTAR AHORA
1. **A1 · IniciativaSocial (quedada deliberada autónoma)** — 🟢, prioridad ALTA, valor visible máximo del bloque vida autónoma.
2. **B1 · Núcleo eventos pueblo (DTO+catálogo+planificador+resolución)** — 🟢, sin colisiones; deja el MVP casi jugable en backend.
3. **A3 · Voluntad salidas individuales** — 🟢 (evaluador local, sin tocar el evaluator en WIP).
4. **A2 · Selección social firmada** — 🟡 pero ALTA: preparar hunk quirúrgico sobre `InteraccionCasual` vivo (parche pequeño, testeable).
5. **B2 · Anuncio Mensajito del evento** — 🟡 vía BuzonEngine directo si es posible.
6. **B3 · PRÓXIMO EVENTO en Inicio** — 🟡, en cuanto la capa UI libere (o por el integrador autorizado).

### DESPUÉS
7. A4 memoria por lugar · 8. A5 reconciliación de amistad · 9. H8/H6/A6 (pequeñas decisiones con hosts limpios).

### BLOQUEADO POR (dependencia real, no preferencia)
- Romance Autónomo FASE 2 → gate de validación de FASE 1 + decisión de familias_en_play.
- Deploy reconciliación voluntad P2/M2+ → decisión de usuario sobre extremos >0.85.
- R07 cadencia B4 → autorización expresa pendiente.

**Criterio transversal:** cada pieza en worktree/rama aislada, commit obligatorio antes de integrar, deploy selectivo (REGLA OPERATIVA vigente), backup PRE + verificación remota si toca producción. Calibraciones nuevas siempre `_provisional`/laboratorio.

---

## 5. Nota de higiene docs (para quien corresponda)
Quedan SIN commit del stream de auditoría anterior: `docs/HALLAZGOS_AUDITORIA_VIDA_AUTONOMA_NO_ROMANTICA.md` (untracked), hunks de B32/S11 en `PLAN_POST_AUDITORIA_PLAYTEST.md` e índice en `docs/README.md`. Este documento NO los arrastra: su commit incluye únicamente este fichero.

---

*Fin. Fase de auditorías CERRADA. Siguiente paso: implementar A1.*
