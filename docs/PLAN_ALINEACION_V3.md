# PLAN ALINEACION V3 — Pasada funcional (2026-08-21)

## Incidencias y soluciones

### 1. DEBUG/LAB no aparecía
- **Causas:** (a) `partida.cargar` no emitía `lab_audit` al reutilizar partida guardada; (b) solo un evento `PARTIDA` agregado, sin entradas `[AHT DEBUG NPC]`/`[AHT DEBUG REL]` separadas; (c) `renderHud` podía fallar si `.buzon .badge` era null (rompía refresh); (d) lab usaba `playtest_01` por defecto.
- **Solución:** `LabAudit::eventoSnapshotCargada`, eventos NPC/REL por vecino en nueva partida, fallback `ahtLabAuditLog` en `play-v3.js`, `lab-audit.js` con `console.log` visible, lab con `juego_v1`.

### 2. Tokens mapa ausentes
- **Causa:** `PresenciaEngine` solo colocaba residentes en encuentros/planes autónomos (0 al inicio).
- **Solución:** rutina visual con `lugares_preferentes` del perfil (sin alterar RNG de encuentros).

### 3. Tutorial
- **Decisión:** bienvenida breve (4 pantallas) → al cerrar abre **Hoy en el pueblo**; 3 misiones tutorial iniciales (pendiente implementar lógica dedicada); después misiones diarias normales sin regenerar otras 3.

### 4–14. UI/funcional
- Copy legacy bloque: `TutorialBucle` pistas y recado Rocío.
- Badge Mensajitos: `data-buzon-badge` + HUD null-safe.
- Plan precreado: era `tutorial.sugerencia` confundido con agenda; UI solo muestra encuentros reales (`estado.proximo_encuentro`).
- Lugares: `nombreLugarTitulo()` (Cafetería, etc.).
- Retratos: `pintarOrgCaras()` en fillOrganizar y onChange.
- Reloj: `data-dia-num`, `data-dia-meta`, hora en panel objetivos.
- Horas pasadas: filtro UI en Nuevo Plan.
- Feedback plan: toast descriptivo + DEBUG PLAN en lab.
- Misiones: `renderMisiones` lee `misiones_hoy.misiones`.

## Pendiente (siguiente pasada)
- 3 misiones tutorial dedicadas (`MisionDiariaEngine` flag `tutorial_inicial`).
- Pasada visual (mockups); no tocar en esta pasada.

## Tutorial Primeros Pasos + planes en solitario (cerrado 2026-08-21)

- **Tutorial inicial**: 4 pantallas canónicas solo en partida nueva; reabrible con «¿Cómo va esto?»; textos servidos por TutorialPrimerosPasos::vistaPublica.
- **3 misiones** día 1 (amilia: primeros_pasos): Romper el hielo → Mensajito → plan solo en Cine. Sin misiones diarias normales hasta completarlas; día 2 entran las V3.
- **Pareja M1**: mejor viabilidad real (√(pA·pB) con VoluntadPonderadaEvaluator + compat/química del motor).
- **Planes en solitario**: Nuevo Plan «Con alguien» / «Por su cuenta»; tipo individual en agenda/presencia existentes.
- **LAB**: [AHT DEBUG TUTORIAL] y [AHT DEBUG PLAN SOLO] en ?lab=1.

## Cierre funcional pre-validacion manual (2026-08-21)

- LAB: play.php?lab=1; __AHT_LAB__ + data-lab; POST con lab=1; audit en estado/guardar/cargar/nueva.
- Llegadas 3+5: bloqueadas hasta Primeros pasos; avisos en Cotilleo.
- Retratos: object-position 50% 12% sin redisenar shell.

## 2026-08-22 — Bloqueantes DEBUG real

### Canon romance (todos con todos)
- Eliminados traido_por y etiqueta_orientacion_visible de fichas canónicas (per_i02, per_i03, plantilla, QA).
- IdentidadCanon sanitiza al cargar catálogo; PersonajeValidator rechaza campos legacy.
- RomanceElegibilidad ya no filtra por género; solo parentesco y edad dura.

### Seed / aleatoriedad
- PartidaSchema: si no se pasa seed explícita, usa partida_id único (no seed_sugerida fija).
- juego-v1 en config es referencia documental; cada partida nueva tiene seed propia reproducible.
- UI DEBUG «Nueva partida» envía seed ui-… además.

### Retratos (encaje funcional)
- play-v3-bloques-residencias.css enlazado en play.php.
- Contenedores 	ut-caras / caras-clip con tamaño fijo 52px y overflow.

### DEBUG copiar
- API partida.debug_export + buffer cliente htDebugSessionLog.
- Botones «Copiar debug» / «Copiar estado» en panel 🧪 DEBUG.

### Misiones Primeros pasos
- capa-misiones visible en CSS (faltaba en selectores).
- Misiones con ccion navegable: Nuevo Plan / Mensajitos / plan solo.

## 2026-08-22 - Canon población: 46 vecinos simultáneos

- **Decisión:** capacidad máxima de producto = **46** (no 24). Los 46 personajes del catálogo pueden convivir en una misma partida si ninguno se marcha.
- **Motor:** CapacidadViviendas::CAP_PRODUCTO = 46. Pool lógico: A01–A16 + B01–B08 (legacy) + C01–C16 + D01–D06.
- **Migración aditiva:** partidas con iviendas.slots de 24 se expanden en ensure() vía expandirPoolAdditivo() sin tocar ocupantes A/B.
- **Llegadas:** CandidatoLlegadaEngine usa CAP_PRODUCTO (sin tope artificial en 24). gapMin escala hasta N=45.
- **UI:** Celestine → En el pueblo N / 46 leyendo celeste.vivienda_capacidad_max.
- **No reintroducir 24** como límite de población en código, tests ni documentación de producto.

## 2026-08-22 - Curva llegadas post-8 (cap 46)

Fórmulas en CandidatoLlegadaEngine (modo normal V3):

- gapMin(n) = 2 + floor((n-8)*1.25) con n en [8, 45]
- pDiaV3(n) = min(0.30, 0.04 + 0.015 * (46 - n)) — horizonte H = 46-N (antes H = 24-N con N máx 23)

Espíritu conservado:

- Bloque N=8..23: ~245 días esperados acumulados (igual que la curva original calibrada).
- N=24..45: gap y E[T] crecen; p cae hasta 0.055 en N=45.
- Horizonte 8→46 completo: ~1172 días esperados (relleno progresivamente lento).

No reintroducir tope 24 en clamps ni UI.

## 2026-08-22 - PENDIENTE tests pre-checkpoint (clasificación, sin re-ejecutar run_all)

Estado según últimos resultados ya disponibles en terminal (no revalidado en esta pasada).

### Último `run_all.php` con log completo (~05:30)
**3 ficheros con exit ≠ 0:**

| Test | Aserciones FAIL | Clasificación | Bloquea checkpoint presencia/UTF-8 |
|------|-----------------|---------------|-------------------------------------|
| `tutorial_misiones_visibles_test.php` | `jugable completado`; `finale pendiente tras tercera` | **Otro bloque pendiente** (ronda tutorial/finale, agente funcional) | **NO** |
| `dia2_misiones_test.php` | `sin misiones normales dia 2` | **Otro bloque pendiente** (transición tutorial → misiones día 2) | **NO** |
| `slots_ui_compatibles_test.php` | `hay horas para cine` | **Otro bloque pendiente** (slots UI / DisponibilidadEngine; resto del test OK) | **NO** |

Re-ejecución puntual ~07:05 de `tutorial_misiones_visibles_test`: **mismos 2 FAIL** (sin cambio).

### UTF-8 / presencia (este bloque)
- `utf8_text_test.php`, `utf8_mapa_presencia_test.php`, `presencia_encuentros_test.php`: **OK** (ejecución focalizada post-fix `iniciales`).
- Scripts `tests/_diag_utf8_*`: **diagnóstico temporal** usado solo durante investigación; **eliminados** del repo. **Nunca entraron en `run_all`** (glob `*_test.php`).
- **No hay regresión UTF-8 pendiente** en tests canónicos de este bloque.

### Capacidad 46 (bloque aparte)
- `capacidad_pueblo_46_test.php`: ejecución aislada ~06:32 con exit 1 en bucle `relleno hasta 46` (spam de FAIL; aserciones iniciales migración/n=25 **OK**). **No atribuido al fix UTF-8/presencia**. Pendiente de otro pase si el checkpoint incluye cap 46.

### Último `run_all` ~07:13
Exit 1 sin listado de ficheros en log (filtro `FAIL ` no captura `FAIL:`). No usar como fuente principal.

### Commit: excluir
- Cualquier `tests/_diag_utf8_*` o `tests/_quick_check_*` (temporales; ya borrados).
- `data/partidas/*.json` de prueba local.
- `dev/_run_all_*.txt` si existen.

### Commit: incluir (tests canónicos de este bloque)
- `tests/utf8_text_test.php`
- `tests/utf8_mapa_presencia_test.php`
- `tests/presencia_encuentros_test.php`
- Código motor/API presencia + `src/Engine/Utf8Text.php`

