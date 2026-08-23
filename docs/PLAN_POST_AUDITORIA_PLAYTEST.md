# PLAN POST-AUDITORÍA — PLAYTEST FUNCIONAL (BACKLOG VIVO)

**Origen:** AUDITORÍA GENERAL COMPLETA (2026-08-23) + hallazgos del playtest real en curso.
**Propósito:** fuente única de seguimiento de bugs, incoherencias, deuda, balance, jugabilidad e ideas derivadas de la auditoría. NO sustituye al informe original; lo desglosa para ejecución incremental.
**Regla de oro:** nada se marca RESUELTO sin revalidar el código real. Nada se implementa solo porque esté aquí: cada fix requiere autorización y comprobación de colisión con trabajos paralelos.

---

## CÓMO USAR ESTE DOCUMENTO

1. Cada entrada tiene un **ID estable** (B/S/M/N/P/R/F). No renumerar nunca; los muertos se marcan DESCARTADO.
2. Estados válidos:
   - `PENDIENTE`
   - `EN INVESTIGACIÓN POR OTRO AGENTE` (no duplicar trabajo)
   - `POSIBLEMENTE RESUELTO — REQUIERE REVALIDACIÓN`
   - `CONFIRMADO VIVO`
   - `DESCARTADO CON JUSTIFICACIÓN`
   - `RESUELTO Y VERIFICADO`
3. Clases: `BUG CONFIRMADO` · `INCOHERENCIA/DEUDA` · `BALANCE/CALIBRACIÓN` · `JUGABILIDAD/UX` · `SISTEMA INFRAUTILIZADO` · `IDEA FUTURA`.
4. Prioridad: `ALTA` / `MEDIA` / `BAJA`. Riesgo de implementar: `BAJO` / `MEDIO` / `ALTO`.
5. Al cerrar una entrada: actualizar `Estado`, añadir fecha + evidencia (commit/test), y mover el detalle si procede a docs históricos.

**Contexto técnico vigente al crear el plan:** HEAD = `50a0a3d`, con WIP extenso sin commitear (31 ficheros modificados, 4 tests borrados en working tree: ver B31). Producción = config `juego_v1` (vida/misiones/peticiones/buzón/autonomía ON).

---

## SECCIÓN 0 — TRABAJOS PARALELOS ACTIVOS (NO DUPLICAR)

Estas entradas están siendo trabajadas por otros agentes AHORA. Cuando terminen, volver aquí, **comprobar el código real** (no confiar en el informe del agente) y revalidar las entradas relacionadas de este backlog.

### P01 — Continuidad relacional / crecimiento de relaciones
- **Clase:** JUGABILIDAD/UX · **Prioridad:** ALTA · **Estado:** IMPLEMENTADO LOCALMENTE (M2+) / PENDIENTE DE INTEGRACIÓN Y REVALIDACIÓN — bloqueado por decisión de balance en extremos (ver INCIDENTE, hallazgo >0.85) + compatibilidad P2
- **Subsistema:** relaciones/voluntad/experiencia · **Archivos implicados conocidos:** VoluntadPonderadaEvaluator.php, RelacionEngine.php, RelacionDesgaste.php, EncuentroResolver.php, EncuentroDeltasReales.php, ContactoCalidad.php, calibracion_vida.json
- **Hallazgo de playtest:** caso Yeray+Sergio: conocerse muy_bien + intervención exitosa → social ≈7; horas después una propuesta seguía ≈51%. La voluntad apenas conserva calidad/recencia del encuentro.
- **Objetivo de producto:** relaciones construibles progresivamente; trayectoria comprensible; relación fuerte amortigua incidentes pequeños (80→70 sí; 80→"como recién conocidos" no); relación fuerte facilita aceptar planes/citas/recuperación.
- **Implementado (M2+, modelo aprobado):** `mod_continuidad_reciente` por PAR (memoria_eventos, muy_bien +10/bien +5, dos buenos 48h +3, half-life 12h, cap 12, corte por mal/muy_mal posterior del mismo par; tercero NO corta); consolidación por niveles (40/62/82 → desgaste base 0.8/0.4/0.2/0.1); protección progresiva de daño `max(0.3, 1−0.005×social)`; nivel 3 primer muy_mal max −5; delta positivo ×1.15; `mod_social_factor` configurable. Caso Yeray+Sergio verificado: p_plan 0.51 → **0.596**.
- **Comprobar al revalidar:** curva social/día con juego real; percepción de consolidación; que continuidad nunca dispare aceptación automática (pendiente: decisión sobre extremos primera_cita).
- **Solapa con:** B3, B15, R02, S04. **Criterio de aceptación:** tras varios encuentros buenos, la probabilidad de propuestas futuras sube de forma perceptible y estable; una mala experiencia puntual no resetea la relación.

### P02 — Barras sociales visibles muy cortas incluso tras muchos días
- **Clase:** JUGABILIDAD/UX · **Prioridad:** MEDIA · **Estado:** IMPLEMENTADO PARCIALMENTE (M2+ acelera crecimiento ×1.15 + consolidación frena desgaste) / PENDIENTE DE VALIDACIÓN JUGANDO
- **Subsistema:** vistas de relaciones · **Archivos:** EtiquetaRelacionPlay.php (mapeo barra −100..100→8..100), PartidaService::vistaRelacionesPueblo, RelacionDesgaste.php
- **Hallazgo de playtest:** casi todas las barras de conocidos son cortas tras muchos días.
- **Comprobar al revalidar:** si M2+ (delta ×1.15 + desgaste por nivel) basta para percibir progreso jugando, o si queda artefacto del mapeo visual (EtiquetaRelacionPlay). NO cerrar sin sesión de juego.

### P03 — Hobbies/gustos no dan ventaja coherente en intervenciones
- **Clase:** BUG CONFIRMADO (por playtest) · **Prioridad:** ALTA · **Estado:** ✅ RESUELTO Y VERIFICADO EN PRODUCCIÓN (2026-08-23)
- **Subsistema:** intervención/experiencia/discovery · **Archivos:** EncuentroIntervencion.php, EncuentroExperiencia.php, EncuentroPonderacion.php, PlanAfinidad.php, EmotionalEventBridge.php, FichaPlayVista.php, EncuentroResultadoVista.php, EncuentroCotilleoCopy.php, calibracion_vida.json (`intervencion_tema`), play.php, play-v3.js
- **Casos reales originales:** Dolores disfruta BINGO → cita en el bingo → Dolores enfadada. Alba disfruta COSTURA → encuentro con costura → Alba triste/"hecha polvo" y Sergio contento.
- **Causa raíz demostrada por auditoría:** la resolución leía `$plan['hobby_match']`, clave que `PlanAfinidad` nunca devolvió (código muerto); el tema elegido no se evaluaba contra gustos de nadie; además doble signo (`factorAccion('mal') = -1.1` × delta ya negativo ⇒ social POSITIVO en tiradas malas) y bug `plan_a/plan_b` (todos los participantes evaluados contra el plan del segundo).
- **Sub-fixes entregados y verificados en producción (deploy 2026-08-23 18:29/18:33, snapshot remoto byte-idéntico al worktree):**
  1. Tema elegido evaluado POR PARTICIPANTE (`tema_cargas` individual consumido por `EncuentroExperiencia`). ✔
  2. Gustos/rechazos favorecen o perjudican a la persona correcta (precedencia rechazo > gusto > propio). ✔
  3. Doble signo corregido: muy_mal/mal JAMÁS suman social (regresión en tests). ✔
  4. plan_A usado por A y plan_B por B (orden fijado antes del bucle de cargas). ✔
  5. `plan_lugar_match` (afinidad lugar↔hobby propio) separado de `tema_match` (afinidad residente↔tema); legacy `hobby_match` mantenido como alias. ✔
  6. Delta romance visible en vista/debug (ver también B14, cerrado con esto). ✔
  7. Cotilleo de primera cita coherente con el resultado (mala ⇒ DRAMA «…aunque la cosa ha acabado bastante tensa.»; neutra ⇒ incertidumbre; buena ⇒ ROMANCE). ✔
  8. Descubrimientos/pistas visibles en ficha («Lo que sabes»: ❤️ Le anima / 💢 No le gusta, solo estado `descubierto`; personalidad sigue en sus slots propios). ✔
- **CASO REAL CONCEPTUAL CUBIERTO Y EN PRODUCCIÓN:** Dolores + Bingo → favorece a Dolores (+5.4 pts p(bien+)); Sandra + Bingo → perjudica a Sandra (−8.4 pts). Cubierto por `tests/intervencion_tema_test.php` (47 asserts, incl. casos A–G direccionalidad, gating de no-descubiertos e independencia lugar≠tema) y verificado byte-a-byte contra producción post-deploy.
- **Knobs nuevos (calibracion_vida.json → `intervencion_tema`):** `carga_propio 0.18`, `carga_gusto 0.22`, `carga_rechazo -0.28`, `max_abs 0.35`. Perceptible, nunca determinista.
- **Solapaba con:** B03 (emoción A→B — SIGUE VIVO, no tocado), B14 (cerrado aquí), B15 (pesos fantasma — sigue vivo). **Criterio de aceptación:** CUMPLIDO para hobbies/gustos de hobby; gustos de rasgos/personalidad quedan para su entrada correspondiente.

### P04 — Enfado direccional / anti-stale emocional
- **Clase:** INCOHERENCIA/DEUDA · **Prioridad:** MEDIA · **Estado:** IMPLEMENTADO LOCALMENTE / PENDIENTE DE INTEGRACIÓN Y REVALIDACIÓN (bloqueado con P01/P2 por el hallazgo de extremos; knob −8 restaurado en config local)
- **Subsistema:** emociones/voluntad · **Archivos:** EmotionalEventBridge.php (persistencia de `hacia`), VoluntadPonderadaEvaluator.php:150-181 (mitigación enfado por tercero −8, anti-stale), EstadoEmocional.php
- **Nota:** trabajo reciente ya distinguía enfado con la persona del plan / por tercero / indeterminado y caducidad de emociones viejas. `tests/emocion_enfado_direccional_test.php` en 0 fails tras restaurar el knob.
- **Comprobar al revalidar:** que cubre o no B03 (emoción usada en experiencia) y parte de B09 (conflicto); NO asumir que lo arregla todo. Test existente: tests/emocion_enfado_direccional_test.php.

### P05 — Balance Vida del Pueblo / misiones (+2/−3, hasta −9/día)
- **Clase:** BALANCE/CALIBRACIÓN · **Prioridad:** ALTA · **Estado:** EN INVESTIGACIÓN POR OTRO AGENTE
- **Subsistema:** vida_pueblo/misiones · **Archivos:** VidaPuebloEngine.php:29-30 (constantes +2/−3), MisionDiariaEngine.php (alCerrarDia, máx 3/día), calibracion_vida.json:451-458 (valores no leídos)
- **Datos del playtest:** partida post-tutorial sin intervenir puede ir 65→0 en ~7-8 días (hasta −9/día posible). Calibración contiene claves que el motor ignora (=B12).
- **Comprobar al revalidar:** deltas reales aplicados vs JSON; cadencia de misiones caducadas; solape con B12. Criterio: partida pasiva no muerta en <2 semanas; jugador activo percibe progreso.

### P06 — Visibilidad "Aquí hay tema" sobre tokens + indicador stale
- **Clase:** JUGABILIDAD/UX — **Prioridad:** MEDIA — **Estado:** RESUELTO Y VERIFICADO EN PRODUCCIÓN (2026-08-23 19:58)
- **Subsistema:** mapa/HayTema/VistaPuebloV3 — **Archivos:** HayTema.php, VistaPuebloV3.php, play-v3.js (render tokens), play-v3-app.css (indicador por-token)
- **Regla canónica final:** "AQUÍ HAY TEMA" en el mapa/lugar solo se muestra si los PROTAGONISTAS REALES del tema (actores estructurados del mensaje/patrón) están ACTUALMENTE PRESENTES en ese lugar. Solo los protagonistas reciben badge individual.
- **Caso Aitana (resuelto):** cotilleo en Cine con actores=[Francisco,Yeray] (protagonistas) y Aitana solo como testigo → Aitana NO marcada; Cine sin "Aquí hay tema" por Francisco/Yeray ausentes. El cotilleo sigue en El Cotilleo/Diario.
- **Caso Francisco+Yeray (Restaurante, ambos presentes):** patrón coin_patron activo (≥3 días en 7 días en Restaurante) + ambos presentes → ambos llevan ❤️; Restaurante muestra "AQUÍ HAY TEMA · Romance".
- **Francisco se va / Yeray queda solo:** tema desaparece del Restaurante (requiere AMBOS presentes).
- **Ambos cambian de lugar:** tema desaparece del lugar anterior; si generan patrón allí, aparece en el nuevo.
- **Indicador visual (deploy 19:52/19:58, cache-buster v3-20260823-195213):**
  - Desktop 24px / Móvil 19px; esquina sup-dcha del token; único por protagonista.
  - Romance: corazón rosa lleno (#e0688f fill) + halo rosa (box-shadow 3 capas); Drama: rayo ámbar; Relación/Coincidencias: burbuja azul; sin badge de grupo.
- **Tests:** hay_tema_test.php (34/34 OK, casos Aitana/Francisco/Yeray); mapa_tema_exterior_ui_test.js (26/26 OK); regresiones pasar_noche_ui, pasar_rato_movil, play_flujo_encuentro, slots_ui, disponibilidad_futuro, vida_relacional OK.
- **Deploy:** selectivo (play-v3.js, play-v3-app.css, cache-buster, HayTema.php) via deploy_manual -Mode Files; HTTP 200; cache-buster v3-20260823-195213; remoto verificado (JS tema-hab + CSS tema-hab--romance).### P07 — Reorganización UX: selector único de temas en «Plan en curso»
- **Clase:** JUGABILIDAD/UX · **Prioridad:** MEDIA · **Estado:** IMPLEMENTADO EN WORKTREE (2026-08-23) — NO DESPLEGADO (bloqueo por WIP concurrente, ver abajo)
- **Subsistema:** UI intervención · **Archivos:** `assets/js/play-v3.js` (htmlIntervencionEncuentro, handler delegado body, helper cerrarSelectorTemas), `assets/css/play-v3-shell-art.css` (bloque nuevo `.enc-int-temas*`)
- **Origen:** derivado directo del cierre P03: con los descubrimientos vivos, «Plan en curso» puede mostrar 15–20 botones de tema simultáneos (nube de botones que no escala).
- **Qué cambia (SOLO presentación):**
  1. Acciones principales visibles = intervenciones generales disponibles según gates (`hablar`, `broma`, `personal`, `coquetear`, `beso`); las no disponibles siguen sin pintarse (mismo gate `a.disponible` de `EncuentroIntervencion::accionesDisponibles`).
  2. Todos los temas/hobbies se agrupan bajo un único control «💬 Elegir un tema…» que abre un panel pequeño con la MISMA lista que ya devolvía el motor (`vista.acciones[hobby].hobbies`, deduplicada por id).
  3. El selector NO revela buena/mala elección: sin colores de afinidad, sin recomendados, sin porcentajes. La decisión sigue dependiendo de «Lo que sabes» en fichas.
  4. Al elegir un tema, el disparo es IDÉNTICO al flujo anterior: mismos atributos `data-enc-int-accion="hobby"` + `data-hobby-id` + `data-residente-id`, mismo handler delegado, mismo payload a `encuentro.intervencion.ejecutar`, ejecución inmediata SIN paso extra de confirmación. El toggle muestra brevemente el tema elegido («Costura ▾») si la ejecución falla; en éxito la vista pasa al resultado como siempre.
- **Qué NO cambia (verificado):** tema_match/cargas, gustos/rechazos, descubrimientos, deltas, probabilidades, reglas/gates de intervención. Cero líneas PHP tocadas; `tests/intervencion_tema_test.php` sigue en OK tras el cambio.
- **Móvil/overflow:** panel anclado al ancho de la polaroid con `width:min(300px,100%,calc(100vw - 32px))`, scroll interno (`max-height:min(264px,44vh)` + `overscroll-behavior:contain`) para 15–20 temas; polaroid ya es `overflow:visible` así que el popover no se recorta.
- **⚠️ Bloqueo de deploy:** `play-v3.js` contiene AHORA MISMO WIP ajeno activo de otro agente (iconos de mapa P06 en ~1524 + marcadores `tema-hab`/`hobbyIconKey` en ~2160/~4180). Subir el fichero arrastraría ese trabajo no validado → aplica REGLA OPERATIVA §DEPLOYS: **NO deploy** hasta coordinación/liberación. `play-v3-shell-art.css` está limpio, pero subirlo solo no tiene efecto útil sin el JS.
- **Verificación pendiente para cerrar:** smoke manual desktop+móvil del selector (abrir/cerrar, elegir tema, gates ocultos cuando `sin_hobby_conocido`, resultado tras éxito).

---

## 📦 RECUPERACIÓN — Auditoría narrativa + variedad + fix flechazo (2026-08-24)

**Commit funcional:** `f64c925`  
**Rama aislada:** `task/narrativa-variedad-flechazo` (worktree `C:/Users/agl03/AppData/Local/Temp/opencode/wt/narrativa-variedad`)  
**Base:** `stable/recuperacion-20260823` @ `daf1962`

### Recuperado dentro de esta tarea
- **Fix flechazo duplicado** (`AccionRomantica.php`): idempotencia vía `RelacionBitacora::tienenHito()` — TESTS OK
- **CopyVariante** (nuevo): helper anti-repetición determinista (`crc32` + rotación, estado `partida['copy_ultimo']`)
- **CopyRechazoPropuesta**: pools ampliados (banal/emocional/relacional/cansancio/otro_dispuesto) + integración CopyVariante + `&$partida` en `linea/fraseCausaHumana/lineaOtroDispuesto/mensajeRechazo`
- **CopySenalRomantica**: pools flechazo/tilín/histórico ampliados (+3 cada uno); marcadores históricos en minúsculas
- **DiarioResidenteBridge** (nuevo): cuerpos narrativos variados por acontecimiento (títulos estables, 15 familias: flechazo, inicio_pareja, vuelta, reconciliación, ruptura, crisis, discusión, declaración, llegada, trabajo, rechazo_repetido, encuentro muy_mal/mal/calentado)
- **DiarioEngine**: idempotencia por `evento_id` + defaults `titulo/consecuencias/actores` + `entradaPorEvento`
- **PropuestaEncuentroEngine**: `&$partida` en `anotarRechazoNarrativo` (persistir `copy_ultimo`)
- **RelacionBitacora + DomainBootstrap**: wiring `DiarioResidenteBridge::alHito/register()`
- **cotilleo_familias.json**: pools ampliados romance/drama/pareja/ruptura/amistad/se_conocieron (7/6/6/6/6/10)

### Tests recuperados
- `narrativa_variedad_test.php` (9 casos): **TODO OK** (37/37) — case 5 adaptado a marcador literal `'voluntad_ok_pero_plan_rechazado'`
- `cotilleo_se_conocieron_variedad_test.php`: **TODO OK** (14/14)
- `diario_residente_test.php`: **FAIL** (línea 88) — requiere `EmocionalNarrativa::explicacionCompleta()` **AUSENTE en daf1962** (trabajo ajeno)
- Baseline daf1962: `rechazo_copy_coherente`, `senal_romantica_coherencia_temporal`, `vida_relacional` — **TODO OK**

### Expresamente AJENO / preexistente (NO recuperado en esta tarea)
- ❌ `EmocionalNarrativa::explicacionCompleta()` — falta en daf1962, impide completar `diario_residente_test`
- ❌ `CopyRechazoPropuesta::mensajeCooldownPar` / `COOLDOWN_GENERICO` — agente cooldown (test `rechazo_cooldown_neutro_test.php`)
- ❌ `CopyRechazoPropuesta::hablanteRechazo` → `rechazoCanonico` — agente atribución canónica (`rechazo_atribucion_canonica_test.php`)
- ❌ `PropuestaEncuentroEngine` hunks cooldown/B4/peticiones — solo `&$partida` recuperado
- ❌ `DiarioEngine::listarPorResidente` — agente ResidentesHandler
- ❌ `tests/copy_rechazo_causas_test.php` — no existe en donante ni en daf1962
- ❌ B4 (mensajitos/peticiones) — NO pertenece a este stream

### Producción y deploy histórico
- Deploy ~22:15 (2026-08-23) **incompleto/no determinista** (timeout 10 min, sin cache-buster). Producción actual contiene streams posteriores a daf1962. Fuente de integración para ESTA tarea: **exclusivamente commit `f64c925`**. No marcado como verificado en producción.

### Estado
- Auditoría/variedad narrativa: **RECUPERADA EN RAMA AISLADA / TESTS ESPECÍFICOS OK / PENDIENTE INTEGRACIÓN Y PLAYTEST**
- Fix flechazo duplicado: **RECUPERADO EN RAMA AISLADA / TESTS OK / PENDIENTE INTEGRACIÓN**
- CopyVariante / anti-repetición narrativa: **recuperado**
- Ampliación de pools: **rechazos, señales románticas, diario y cotilleos**
- DiarioResidenteBridge + idempotencia por evento: **recuperados dentro de esta tarea**

---

## ⚠️ INCIDENTE — DEPLOY CON WIP CONCURRENTE (2026-08-23)

**Qué pasó:** durante el cierre de P03 (hobbies/intervenciones), el mecanismo canónico `deploy_canonical` subió a producción **96 ficheros**, incluidos archivos con WIP sin commitear de otro agente (voluntad/emociones/calibración). Verificado post-deploy comparando por hash un snapshot remoto contra el worktree local.

**Estado comprobado en PRODUCCIÓN (post-deploy 18:29/18:33):**

**P2 — REGRESIÓN CONFIRMADA EN PRODUCCIÓN / PENDIENTE DE RECONCILIACIÓN**
- `voluntad.mod_tipo.primera_cita`: esperado **4** → producción **0**
- `bonus_primera_cita_reciproca`: esperado **12** → **ausente** (config) y **eliminado del código** de VoluntadPonderadaEvaluator (0 referencias)
- `conflicto_mult_cita`: esperado **3** → **ausente** (config) y **eliminado del código**

**Enfado direccional — PARCIALMENTE REGRESIONADO EN PRODUCCIÓN**
- `resolverEnfado` (dirigida/ajena/indeterminada): sigue presente ✔
- Anti-stale (`EstadoEmocional::vencido` → mods neutros): sigue presente ✔
- Knob `emociones_v1.enfadado_ajeno_aceptar_planes = -8`: **ausente** → 'ajena' cae hoy al fallback −16 (mitigación inoperante; el código consulta el knob pero la config no lo define)

**M2+/continuidad — ACTIVO EN PRODUCCIÓN / PENDIENTE DE REVALIDACIÓN CON P2 Y ENFADO DIRECCIONAL**
- `voluntad.continuidad_reciente {activo:true, bonus_muy_bien:10, bonus_bien:5, bonus_dos_buenos_48h:3, decay_halflife_horas:12, max_bonus:12}` desplegada y cableada en `desglose()` (`mod_continuidad_reciente`), junto a `mod_social_factor=0.28` y `delta_multiplier_positivo=1.15`
- Fue desplegada como parte del WIP concurrente antes del cierre formal de integración

**Impacto en tests:** `tests/primera_cita_balance_p2_test.php` (9 fails) y `tests/emocion_enfado_direccional_test.php` (12 fails) reproducen idéntico contra producción (worktree ≡ remoto byte-a-byte en los 21 ficheros auditados). Son fallos del subsistema voluntad/emociones, NO del cierre P03.

**Propiedad:** reconciliación exclusivamente del agente propietario del WIP de voluntad/continuidad. El cierre P03 queda aislado y validado.

### ✅ RESOLUCIÓN LOCAL DE LA RECONCILIACIÓN (2026-08-23, fase posterior al incidente)

**Estado local (worktree, SIN deploy):** P2 restaurado quirúrgicamente SOBRE el código M2+ actual (sin revertir nada):

1. **Config (`calibracion_vida.json`)** — merge quirúrgico, knobs M2+ intactos:
   - `voluntad.mod_tipo.primera_cita: 0 → 4`
   - `voluntad.bonus_primera_cita_reciproca: 12` (+notas) — restaurado
   - `voluntad.conflicto_mult_cita: 3` (+notas) — restaurado
   - `emociones_v1.enfadado_ajeno_aceptar_planes: -8` (+nota) — restaurado (se había perdido con el checkout del config en sesión anterior)
2. **Código (`VoluntadPonderadaEvaluator.php`)** — sobre la versión M2+ vigente:
   - `conflicto_mult_cita`: solo `primera_cita`/`cita`; quedar/conocerse/pareja/romantico-legacy = ×1
   - `bonus_primera_cita_reciproca`: solo `primera_cita` + señal canónica MUTUA vía `SenalRomantica::desdeHacia` ambas direcciones; visible en desglose
   - M2+ intacto: `mod_continuidad_reciente`, decay half-life 12h, cap 12, corte por malo del par
   - Enfado direccional intacto: dirigida −16 / ajena −8 / indeterminada −16 conservador / vencida 0

**Tests bloqueadores tras reconciliación (LOCAL):** `primera_cita_balance_p2_test.php` ✅ 0 fails · `emocion_enfado_direccional_test.php` ✅ 0 fails.
**Regresiones:** voluntad_media_geometrica ✅ · encuentros ✅ · encuentro_resultado_play ✅ · relaciones ✅ · vida_relacional ✅ · emocional_trazabilidad ✅ · encuentro_intervencion ✅. Fails PREEXISTENTES de WIP ajeno verificados contra baseline (stash): `rechazo_atribucion_canonica` (2 fails toasts), `rechazo_cooldown_neutro` (fatal `CopyRechazoPropuesta::mensajeCooldownPar` inexistente).

**Tabla A–F motor real (quedar/cita):** A=0.51 · B(soc7+muybien2h)=0.587 · C(1ªcita mutua)=0.673 · D(+muybien)=0.751 · E(enfado dirigido)=0.581 · F(enfado ajeno)=0.62. Orden **E < C/F < D cumple**.

**⚠️ HALLAZGO ABIERTO QUE BLOQUEA EL DEPLOY — extremos >0.85 (PARA Y REPORTADO):**
Simulación de extremos (72 combinaciones: soc 20–80 × rom medio/alto × conf 0/leve/fuerte × neutro/alegre/enfadado, SIEMPRE primera_cita + señal mutua + muy_bien reciente): **32/72 (44%) superan p_plan 0.85**, máximo **0.920** (= cap `p_excelente`, score≥88). Causas apiladas: P2 (+4 tipo, +12 recíproco) + M2+ continuidad (+9) suben muchos casos neutrales por encima del umbral excelente 88 que antes requería estado alegre. El caso real jugable D queda en 0.751 (<0.85 ✔), pero el frame favorable-sistemático cruza el techo con demasiada frecuencia. **DECISIÓN PENDIENTE DEL USUARIO:** aceptar 0.92 como techo diseñado para el stack perfecto, o recalibrar (p.ej. cap de suma recíproco+continuidad, bajar bonus, o subir `score_excelente`). NO se ha tocado nada silenciosamente. **DEPLOY EN ESPERA hasta decisión.**

---

## 🔒 REGLA OPERATIVA — DEPLOYS CON WIP CONCURRENTE

Mientras exista WIP concurrente en el worktree:

1. **PROHIBIDO** usar deploy general/canónico que suba automáticamente todo el árbol modificado (`deploy_canonical` incremental sube TODO lo dirty).
2. Los agentes deben:
   - identificar EXACTAMENTE sus runtime files;
   - hacer deploy **SELECTIVO** solo si esos archivos no contienen WIP ajeno;
   - si un archivo contiene WIP ajeno no validado → **NO DEPLOY** y escalar a coordinación.
3. **Un deploy de 96 archivos para una tarea localizada es señal de STOP.**
4. Antes de cerrar cualquier tarea con deploy: verificación post-deploy obligatoria (snapshot/hash de los ficheros críticos + suites afectadas), documentando qué se subió realmente.

---

## SECCIÓN 0b — INVESTIGACIONES PRIORITARIAS REGISTRADAS (sin implementar)

### R01 — Mensajitos ≠ Cotilleos: separación de canales [INVESTIGACIÓN PRIORITARIA]
- **Clase:** JUGABILIDAD/UX · **Prioridad:** ALTA · **Estado:** PARCIALMENTE RESUELTO Y VERIFICADO (2026-08-23: pipeline B4 personal vivo en producción; ver R05/R06) · Pendiente: reparto canal-a-canal completo de emisores no-B4
- **Subsistema:** buzón/cotilleo/diario · **Archivos:** BuzonEngine.php, BuzonPlayBridge.php, CotilleoNarrativo.php, CotilleoAutonomoCadencia.php, VistaCotilleoV3.php, DiarioResidenteBridge.php, PeticionEngine/PeticionPuebloEngine (mensajes asociados)
- **Realidad actual (playtest):** `buzon_enabled=ON` pero prácticamente no llegan mensajitos personales accionables; llega información tipo "X hoy no tenía ganas de quedar", que se siente cotilleo duplicado.
  - **ACTUALIZACIÓN 2026-08-23 (fix B4 desplegado):** los mensajitos personales B4 ya nacen con variedad real (5/7 plantillas activas en pasivo; antes 2), el peticionario ya no rechaza su propia petición exacta, y cumplida/caducada/rechazo-de-tercero tienen feedback diferenciado (`peticion_resultado`). La métrica de salud B4 cuenta SOLO clasificación PETICION + resultados asociados, separada de cotilleos y respuestas de plan.
- **Definición objetivo de producto:**
  - COTILLEO = Celestine se entera de algo OCURRIDO (rechazo, discusión, cita, flechazo, alguien raro).
  - MENSAJITO = UN vecino SE DIRIGE a Celestine con intención personal (petición, duda, decisión) y acción cuando tenga sentido ("quiero ir al cine", "¿me lanzo?", "¿puede venir X?").
  - Coherencia obligatoria con estado real: nada de "me gusta Sergio" sin señal/historia; nada de "quiero volver a quedar con X" sin motivo previo.
  - Cadencia escalando con población (~10 residentes: no pasar jornadas sin ningún mensajito personal; números exactos: decidir midiendo, no fijar aún).
- **Qué investigar (checklist):**
  1. Inventario real de emisores hacia buzon: qué tipos existen hoy (llegada, marcha, señal_romantica, petición, rechazo…), cuáles tienen emisor vivo y cuáles muertos (ver B18/B19).
  2. Qué mensajes actuales son en realidad cotilleo mal clasificado (canal `cotilleo` vs clasificaciones).
  3. Cadencia medida por población (usar SimuladorPuebloVivo/PlayLab para volumen esperado).
  4. Qué sistemas diseñados quedaron sin conexión (framework `MensajitoAcciones` solo lo usan llegada/marcha).
  5. Propuesta de reparto canal-a-canal SIN implementar.
- **Dependencias:** B18, B19, S08, R03. **Criterio de aceptación de la investigación:** tabla emisores×cadencia×clasificación propuesta, validada contra simulación, lista para decidir implementación.

### R02 — Romance autónomo: balance pendiente de revalidación
- **Clase:** BALANCE/CALIBRACIÓN · **Prioridad:** ALTA · **Estado:** PENDIENTE (bloqueado por P01/P03/R01)
- **Hallazgo auditoría + playtest:** día ~54 sin ninguna pareja consolidada. Flechazo casual prob efectiva ≈0.21%; familia romance peso 0.12 vs ocio 1.1; señales casi nunca aparecen ⇒ primera_cita raramente desbloqueable.
- **Regla:** NO subir RNG primero. Orden correcto: continuidad relacional (P01) → hobbies/gustos (P03) → citas OK → señales coherentes → recalibrar.
- **Objetivo:** el pueblo genera historias románticas solas ocasionalmente; una señal debe sentirse especial (ni ausente ni constante).
- **Comprobar:** cadencia medida de flechazos/declaraciones por 100 días-simulado; % de parejas nacidas autónomas vs por intervención.

### R03 — Análisis: consecuencias reales de un rechazo hoy
- **Clase:** INCOHERENCIA/DEUDA (análisis previo a diseño) · **Prioridad:** MEDIA · **Estado:** PENDIENTE
- **Qué documentar para cada caso** (banal / emocional / relacional / repetido / primera_cita / cita / beso / propuesta normal):
  - emoción aplicada; Δsocial; Δromance; conflicto; memoria (RechazoMemoria/PropuestaCooldown 6h); efecto en voluntad futura (penalizaciones n≥2); cotilleo generado (sí/no/canál); mensajito (sí/no); entrada de diario (sí/no); Vida del Pueblo (sí/no).
- **Base conocida (auditoría):** banal = solo registro; emocional/relacional o n≥3 → romance −3 + tristeza 10h + cotilleo; pareja → estabilidad −2; n≥4 → hito RECHAZO_IMPORTANTE. Beso rechazado: RECHAZO_IMPORTANTE + contactos −2/−1 + conflicto 2 + enfadado 3h.
- **Después:** proponer cómo hacer PERCEPTIBLE lo que ya existe (cotilleo/mensajito/diario) SIN convertir cada NO en castigo grande.
- **Dependencias:** R01 (canal), P01 (voluntad).

### R04 — Diseño mínimo de ciclo de vida del conflicto (B09)
- **Clase:** INCOHERENCIA/DISEÑO · **Prioridad:** ALTA · **Estado:** PENDIENTE (no implementar: toca relaciones/emociones en trabajo)
- **Comportamiento deseado acordado:**
  - roce pequeño no dura eternamente; decae o se resuelve;
  - conflictos graves duran más;
  - experiencias positivas posteriores pueden reparar;
  - relación fuerte amortigua incidentes pequeños (coherente con P01);
  - conflictos repetidos acumulan peso real.
- **Comprobar antes de diseñar:** interacción con desgaste social (nunca cruza 0), con bandas negativas (B02), con fases tension/crisis (RelacionFase manual).

### R05 — Política de retención de datos ANTES de caps arbitrarios (B01)
- **Clase:** DEUDA/ARQUITECTURA · **Prioridad:** ALTA · **Estado:** PENDIENTE (diseñar primero, implementar después)
- **Principio de producto:** no guardar para siempre microeventos. Distinguir: datos necesarios para mecánicas futuras / datos narrativos / debug / ruido descartable.
- **Referencias iniciales a estudiar (no definitivas):**
  - Cotilleos: ventana ~3 días o últimas N relevantes.
  - Mensajitos: activos/importantes + cola reciente razonable.
  - Diario: ya es selección narrativa → referencia para decidir qué merece memoria larga.
  - Historial técnico: audit_trail/domain_events/event_log (ya capados) vs `_casual_intentos`, `acontecimientos_log`, `memoria_eventos`, `npc_autonomo.historial_eventos` (sin cap = B1).
- **PROTEGIDO (no purgar jamás sin análisis):** relaciones, rechazos, marchas, señales, misiones, peticiones, decisiones, historial de personajes/partida, viviendas.
- **Entregable de la investigación:** matriz campo→clase(retención)→ventana→riesgo de perder mecánica. Después sí implementar caps.

### R06 — B4 Mensajitos VIVOS: variedad, compromiso del peticionario y feedback
- **Clase:** JUGABILIDAD/UX + INCOHERENCIA · **Prioridad:** ALTA · **Estado:** RESUELTO Y VERIFICADO EN PRODUCCIÓN (2026-08-23) — EXCEPTO cadencia por población → ver R07 (ABIERTA)
- **Subsistema:** peticiones B4 / propuestas / buzon · **Archivos:** PeticionPuebloEngine.php, PeticionFeedback.php (nuevo), PropuestaEncuentroEngine.php, VoluntadPonderadaEvaluator.php, calibracion_vida.json (`peticiones_pueblo`), tests/mensajitos_vivos_test.php
- **Resuelto y verificado en producción (marcadores remotos + URL 200):**
  - VARIEDAD: anti-repetición por `historial_plantillas` (ventana 3, penalización 25). Antes: salir_de_casa 73%, 5 plantillas al 0%. Después: conocer 29% / quedar 23% / salir 20% / volver 17% / ir_al_lugar 10% / algo_distinto 2%; rachas máximas 1–2; primera_cita respeta su gate romántico.
  - COMPROMISO: si Celestine organiza EXACTAMENTE lo pedido, el peticionario no re-tira RNG (`compromiso_peticion_propia`). Con compañía añadida NO pedida: bonus configurable `bonus_nucleo_modificado=30`, sin garantía. Terceros con voluntad normal. Agenda/cooldown siguen mandando.
  - FEEDBACK: cumplida → eco positivo + mensajito `resuelto`; caducada/ignorada → eco negativo + original `leido`; tercero rechaza → petición SIGUE ABIERTA + copy "yo sí quería, pero X no".
- **FRECUENCIA OBJETIVO POR POBLACIÓN: PENDIENTE** → "variedad corregida; frecuencia objetivo por población pendiente". Hoy ~1,65/día con 8 residentes (objetivo producto 4-5/día). Matriz 3/5/8/10/16/24 × pasivo/activo medida 2026-08-23 (dev/_audit_mensajitos_matrix.php); propuesta de curva entregada, NO implementada aún.
- **Criterio de cierre total:** 8–10 residentes produciendo 4–5 mensajitos personales/día sin spam ni cap excesivo, validado con la misma matriz.

### R07 — Cadencia B4 por población (curva orgánica)
- **Clase:** BALANCE/CALIBRACIÓN · **Prioridad:** ALTA · **Estado:** CONFIRMADO VIVO / ABIERTA (propuesta entregada, pendiente autorización)
- **Dato que la mantiene abierta:** 8–10 residentes siguen produciendo ~1,65/día frente a objetivo 4–5/día.
- **Cuello de botella medido:** cap simultáneo = ceil(n·0.33) estrangula el recambio (con cap lleno el spawn es nulo; a 8 res el cap está lleno ~43% del tiempo; a 3 res cap=1 → techo teórico ~0,7/día).
- **NO implementar hasta autorización. Ver R07 en informe de matriz 2026-08-23 para opciones A/B/C/D con números.**

---

## SECCIÓN 1 — BUGS E INCOHERENCIAS CONFIRMADOS POR AUDITORÍA

> IDs heredados del informe (B01-B31). Todos requieren re-validación contra HEAD+WIP antes de arreglarse (el WIP paralelo puede haber tocado estos archivos).

### B01 — Inflación sin techo del save (riesgo partida inutilizable)
- **Clase:** BUG CONFIRMADO · **Prioridad:** ALTA · **Riesgo fix:** BAJO · **Estado:** CONFIRMADO VIVO
- **Subsistema:** persistencia · **Archivos:** PersistenciaCaps.php:110-115 (solo 6 listas capadas); InteraccionCasual.php:206-212 (`_casual_intentos` crece sin fin); CotilleoAutonomoCadencia.php:205-211 (cotilleos de días anteriores no se purgan); campos sin cap: buzon, diario, memoria_eventos, acontecimientos_log, npc_autonomo.historial_eventos; límite duro 8 MiB SQL: PartidaPersistenceConfig.php:12, assert en PartidaJgProgressStorage.php:38, 413 en carga bootstrap.php:75-77.
- **Hoy:** partidas largas engordan sin control; al superar 8 MiB (SQL/usuario logueado) toda escritura lanza y la carga responde 413.
- **Por qué problema:** partida perdida silenciosa a medio plazo + latencia creciente en UNC desde antes.
- **Comprobar:** tamaño real de saves largos existentes en data/partidas; qué campos dominan el peso.
- **Dependencias:** R05 (política de retención ANTES de caps). **Criterio de aceptación:** simulación de 100+ días no supera fracción segura del límite; sin pérdida de mecánicas protegidas.

### B02 — Bandas sociales negativas inalcanzables ('muy_mala'/'enemigo' jamás)
- **Clase:** BUG CONFIRMADO · **Prioridad:** ALTA · **Riesgo fix:** BAJO · **Estado:** CONFIRMADO VIVO
- **Archivos:** RelacionBandas.php:57-70 (deCortesDesc conserva la ÚLTIMA coincidencia); data/configs/calibracion_vida.json:26-30 ({cae_mal:-1, muy_mala:-35, enemigo:-70}).
- **Hoy:** cualquier social negativo acaba en 'cae_mal' (−90 cumple los tres cortes y gana el último).
- **Por qué problema:** gradiente interno perdido; cortes letra muerta; futuro uso (marchas/conflictos/copys) hereda error. Las vistas colapsan igual hoy (EtiquetaRelacionPlay.php:25-26) así que el jugador aún no lo sufre.
- **Fix mínimo propuesto:** iterar del corte más severo al más suave y parar en la primera coincidencia. **Test nuevo:** social −50→muy_mala, −90→enemigo, −5→cae_mal.
- **Dependencias:** revisar colisión con agente de relaciones (P01). Candidato a fix temprano cuando se libere el subsistema.

### B03 — Experiencia de encuentro usa la emoción de A para ambos
- **Clase:** BUG CONFIRMADO · **Prioridad:** ALTA · **Riesgo fix:** BAJO · **Estado:** CONFIRMADO VIVO (solapa parcialmente con P04)
- **Archivos:** EncuentroExperiencia.php:107 (`cargaDe` lee siempre `emocional_a`); versión correcta solo en `desgloseCarga`:172-176.
- **Hoy:** la resolución REAL usa la emoción de A para B; narrativa/diagnóstico usan la correcta → incoherencia resultado-vs-relato.
- **Por qué problema:** asimetría injusta (B enfadado no penaliza su experiencia) y sospechosa de contribuir a P03 (quién sale "hecho polvo").
- **Comprobar al revalidar:** si el trabajo P04 la tocó; replicar lógica de desgloseCarga en cargaDe + test simétrico.
- **Candidato a fix temprano** (con permiso, cuando P03/P04 liberen).

### B04 — Endpoints de relación expuestos sin puerta dev/lab
- **Clase:** BUG CONFIRMADO (exposición/seguridad de contrato) · **Prioridad:** MEDIA · **Riesgo fix:** BAJO · **Estado:** CONFIRMADO VIVO
- **Archivos:** api/index.php:200-218; RelacionesHandler.php:12-70 (`listar` vuelca bolsas crudas: romance numérico, flechazos, avisos_senal, estabilidad; `social/romance/fase` escriben barras arbitrarias).
- **Por qué problema:** trampa trivial vía consola que rompe "el jugador no ve números"; escrituras fuera de flujo.
- **Fix:** gate `requireDev()`/labActiva como debug.*, o retirar del router público. Verificar que play.js/dev.js (legacy) no dependan de ellas antes.

### B05 — Botón «Abrir laboratorio» muerto
- **Clase:** BUG CONFIRMADO (herramienta) · **Prioridad:** ALTA (fase de playtest) · **Riesgo fix:** MÍNIMO · **Estado:** CONFIRMADO VIVO
- **Archivos:** play.php:192 (#btn-debug-lab-open); play-v3-lab.js:290 (solo escucha evento custom `aht-play-lab-open` que nadie despacha).
- **Fix:** bind click → dispatchEvent. Sin dependencias conocidas.

### B06 — Modo taller descabezado por CSS
- **Clase:** BUG CONFIRMADO (herramienta) · **Prioridad:** ALTA (fase de playtest) · **Riesgo fix:** BAJO · **Estado:** CONFIRMADO VIVO
- **Archivos:** play.php:43-45 (oculta .playtest-guia/.playtest-cheats/.tiempo-juego con !important para todo body.play-v3); nadie asigna body[data-taller="1"].
- **Impacto:** playtest.php (?taller=1&lab=1&config=playtest_01) no muestra guía PlaytestGuia ni cheats.
- **Comprobar:** si el WIP de UI paralelo ya cambió esos selectores. **Fix:** condicionar hide a atributo real o poblar atributo desde querystring.

### B07 — Contador de intervenciones organizadas diarias sin reseteo
- **Clase:** INCOHERENCIA/DEUDA (latente) · **Prioridad:** MEDIA · **Riesgo fix:** BAJO · **Estado:** CONFIRMADO VIVO (inocuo hoy: límite null)
- **Archivos:** EncuentroEngine.php:187-190 (incrementa), :201-209 (límite null ⇒ no-op), PartidaDevService.php:21 (único reset, dev).
- **Riesgo:** si algún día se configura límite, bloqueo permanente tras N días. **Fix:** resetear junto a `encuentros_usados_hoy` en Reloj::avanzarHoras.

### B08 — Parejas reconciliadas fuera del sistema de estabilidad para siempre
- **Clase:** BUG CONFIRMADO · **Prioridad:** MEDIA · **Riesgo fix:** MEDIO · **Estado:** CONFIRMADO VIVO
- **Archivos:** ParejaEngine.php:47-50 (guarda base_reconciliacion, nadie la lee); RelacionDesgaste.php:102-105 (is_numeric salta estabilidad null); EncuentroResolver.php:222 (encuentros no mueven estabilidad null).
- **Impacto:** vueltas rotas eternamente sin drama ni recuperación de estabilidad.
- **Comprobar:** casos reales en saves; decidir semilla de estabilidad al reconciliar (usa base_reconciliacion guardada).

### B09 — Conflicto sin ciclo de vida (eterno)
- **Clase:** INCOHERENCIA/DISEÑO PRIORITARIO · **Prioridad:** ALTA · **Estado:** CONFIRMADO VIVO — diseño pendiente en R04 (NO implementar aún)
- **Archivos:** upsertConflicto únicos puntos de escritura (EncuentroResolver.php:209,265; EncuentroIntervencion.php:494,589); sin decremento/borrado en todo src; fases solo vía endpoint manual relacion.fase.
- **Impacto:** un roce de hace 20 días pesa igual hoy en voluntad (×3 en citas) y en la tirada de experiencia.
- **Ver R04** para comportamiento deseado y dependencias.

### B10 — Gating inconsistente de acontecimientos horarios
- **Clase:** INCOHERENCIA/DEUDA · **Prioridad:** MEDIA · **Riesgo fix:** MEDIO (toca calibración de vida) · **Estado:** CONFIRMADO VIVO
- **Archivos:** RelojOperations.php:40-50 (OR con encuentros_enabled arranca MotorVidaDiaria aunque npc_autonomy off); MotorVidaDiaria.php:148-154 (familias_en_play solo filtra si npc_autonomy_enabled && !lab).
- **Hoy en juego_v1 ambos flags ON ⇒ filtro aplica;** la trampa aparece en configs tipo debug_v0. Comprobar intención antes de cambiar; documentar el gate real.

### B11 — Cooldown de llegadas V3 congela el crecimiento tardío
- **Clase:** BALANCE/CALIBRACIÓN · **Prioridad:** MEDIA · **Estado:** PENDIENTE (decisión de producto pendiente: ¿intencional?)
- **Archivos:** CandidatoLlegadaEngine.php:383-387 (gapMin=2+floor((n−8)*1.25)+jitter ⇒ ~48 días cerca de n=45), p≤0.30/día (:389-393).
- **Impacto:** a partir de ~40 residentes las llegadas desaparecen; solo marchas liberan huecos (y son raras: S05).
- **Decidir con diseño:** ¿meseta deseada? Si no, aplanar curva (p.ej. gap máx configurable).

### B12 — Calibración de misiones ignorada por el código
- **Clase:** INCOHERENCIA/DEUDA · **Prioridad:** MEDIA · **Estado:** CONFIRMADO VIVO — absorbido por P05 (balance en curso); revalidar ahí
- **Archivos:** calibracion_vida.json:456-457 (+1/−2 declarados) vs VidaPuebloEngine.php:29-30 (+2/−3 constantes); MisionPlayBridge.php:21 pasa $cal=[] mientras PeticionPlayBridge carga la real.
- **Acción:** unificar con el trabajo P05; después, decidir fuente de verdad (JSON) y test que falle si diverge.

### B13 — Consejos eternos y deterministas
- **Clase:** BUG CONFIRMADO · **Prioridad:** MEDIA · **Riesgo fix:** BAJO · **Estado:** CONFIRMADO VIVO
- **Archivos:** ConsejoEngine.php:29 (duración 5d definida), :39-52 (activas() no filtra fecha); probabilidad_seguir=0.55 sin consumidor (grep).
- **Impacto:** consejo aplica ±10 a voluntad indefinidamente y sin azar de seguimiento.
- **Fix mínimo:** filtrar por fecha en activas() + tirar probabilidad_seguir al evaluar; o documentar como decisión.

### B14 — «Destello romántico» jamás visible (formato legacy en vista)
- **Clase:** BUG CONFIRMADO (presentación) · **Prioridad:** MEDIA-ALTA · **Riesgo fix:** BAJO · **Estado:** ✅ RESUELTO Y VERIFICADO EN PRODUCCIÓN (2026-08-23, dentro del cierre P03)
- **Archivos:** EncuentroResultadoVista.php (`canalRomance` ahora lee también `a_hacia_b`/`b_hacia_a`, formato real del resolver) + EncuentroCotilleoCopy::elegirExtra reordenado.
- **Fix entregado:** vista proyecta el delta real (26→24 ⇒ −2 visible en UI/debug); tests: `encuentro_resultado_play_test` (contrato actualizado y determinizado) e `intervencion_tema_test`.
- **Nota:** la parte de "destello positivo jamás contado" queda cubierta por esta lectura; el balance de cadencia romántica sigue en R02/M01.

### B15 — Pesos fantasma historial(0.06)/azar(0.10) diluyen la experiencia
- **Clase:** INCOHERENCIA/BALANCE · **Prioridad:** MEDIA · **Estado:** CONFIRMADO VIVO
- **Archivos:** calibracion_vida.json:187-199 (declara los pesos) vs EncuentroExperiencia.php:83-118 (historial sin rama, azar v=0; ambos suman denominador).
- **Impacto:** ~16% del denominador no aporta ⇒ todos los demás factores pesan menos que en diseño.
- **Comprobar con P03/P01 antes de tocar** (misma función). Opción: quitar claves del JSON o darles contenido real.

### B16 — resolverFranja hardcodea 'conocerse'
- **Clase:** INCOHERENCIA menor · **Prioridad:** BAJA · **Estado:** CONFIRMADO VIVO
- **Archivo:** PropuestaEncuentroEngine.php:912 (busca hueco alternativo siempre como 'conocerse' aunque sea cita).
- **Impacto:** slots considerados compatibles pueden diferir del tipo real. Fix: propagar tipo.

### B17 — RNG adelantado sin efecto en media geométrica
- **Clase:** DEUDA menor · **Prioridad:** BAJA · **Estado:** CONFIRMADO VIVO
- **Archivo:** VoluntadPonderadaEvaluator.php:81-83 (nextFloat() sacado y descartado). Adelanta cursor sin uso. Fix trivial o documentar.

### B18 — Evento DISCUSION sin emisor
- **Clase:** DEUDA/código muerto · **Prioridad:** BAJA · **Estado:** CONFIRMADO VIVO
- **Archivos:** DomainEvents.php:24 (definido), BuzonPlayBridge.php:157-169 (handler), CotilleoCategoria.php:64-66 (DRAMA★), HayTema.php:15 — ningún emisor en repo; tests lo fabrican a mano.
- **Decidir:** eliminar rama o darle emisor (relacionado con R01/R04).

### B19 — NPC_AUTONOMO_PLAN muerto + AutonomousPlanner semi-conectado
- **Clase:** DEUDA/código muerto · **Prioridad:** BAJA · **Estado:** CONFIRMADO VIVO
- **Archivos:** BuzonPlayBridge.php:127-129 (handler devuelve null); AutonomousPlanner invocado solo por DevHandler.php:115-129 y tests; planes dev quedan 'programado' eternos ocupando agenda/aforo/presencia.
- **Decidir:** purgar o terminar de conectar. En play el array queda vacío (inocuo).

### B20 — compromisos recurrentes pueden pisar sueño/trabajo
- **Clase:** BUG LATENTE · **Prioridad:** BAJA · **Estado:** CONFIRMADO CÓDIGO / impacto actual nulo (ningún personaje del catálogo define compromisos_recurrentes — grep data/personajes vacío)
- **Archivo:** AgendaEngine.php:100 (condición solo protege capa 'programado').
- **Fix barato cuando se toque agenda:** proteger también capas estructural/temporal.

### B21 — Card MISIONES sin punto de entrada directo
- **Clase:** JUGABILIDAD/UX · **Prioridad:** MEDIA · **Riesgo fix:** MÍNIMO · **Estado:** CONFIRMADO VIVO
- **Archivos:** play.php:779-788 (div no clicable, sin data-open); setCapa('misiones') solo en tutorial-fin (:483) y acción de misión (:1003).
- **Impacto:** fuera de esos dos momentos, el modal "Hoy en el pueblo" es inaccesible. Fix: hacer card botón con badge de pendientes.

### B22 — Frontend muerto (varias piezas)
- **Clase:** DEUDA frontend · **Prioridad:** BAJA · **Estado:** CONFIRMADO VIVO (no rompe nada hoy)
- **Inventario:** placeHab() referencia SLOTS inexistente (play-v3.js:2262-2271); applyFases()/pintarEdificios() (.complejo ya no existe); fillSelect/renderProximoCaras/pintarOrgPick; popup completo de mensajitos ([data-mensajitos-trigger/pop/preview...] binds inertes 4848-4876); data-buzon-preview, data-diario-tab, data-org-row-b, data-res-busca, .taller-msg; dock HTML oculto por CSS (app.css:116 + responsive.css:246).
- **Acción recomendada:** purga planificada en una pasada dedicada (no mezclar con fixes de juego).

### B23 — Cotilleos «nuevos» fantasma
- **Clase:** PROBABLE bug UI · **Prioridad:** BAJA · **Estado:** PROBABLE (reproducir antes de arreglar)
- **Archivos:** VistaCotilleoV3.php:618 (nuevo depende de leido del mensaje) vs play-v3.js:3618-3630 (marca lectura vía diario.cotilleo_visto con ids de feed, sin tocar leido).
- **Repro sugerido:** ver un cotilleo destacado y reabrir: comprobar si sigue marcado nuevo.

### B24 — CalibracionConfig/Catalog releídos sin caché por request
- **Clase:** DEUDA rendimiento · **Prioridad:** MEDIA · **Riesgo fix:** BAJO · **Estado:** CONFIRMADO VIVO
- **Archivos:** CalibracionConfig.php:10-26 (2 JSONs por llamada); llamadores calientes: RelojOperations.php:33,39 ×N horas (noche salta 9 pasos), CoincidenciasInteraccionBridge.php:69-73, new Catalog repetidos (RelojOperations:34,48,60; HayTema.php:129).
- **Impacto:** latencia perceptible en táctil sobre UNC. **Fix:** memo estático por request (PHP-FPM: por petición) — bajo riesgo, alta ganancia.

### B25 — Estados de petición: atendida/resuelta conviven; transiciones redundantes ok
- **Clase:** DEUDA · **Prioridad:** BAJA · **Estado:** CONFIRMADO VIVO
- **Archivo:** PeticionEngine.php:13 (ESTADOS), :223 (permite re-transición al mismo estado devolviendo ok). Decidir: limpiar estados heredados o endurecer transiciones.

### B26 — Cuatro conteos duplicados de residentes activos
- **Clase:** DEUDA · **Prioridad:** BAJA · **Estado:** CONFIRMADO VIVO
- **Archivos:** TutorialIncorporaciones.php:197-209, CapacidadViviendas.php:102-114, MisionDiariaEngine.php:625-635, PeticionPuebloEngine.php:686-696. Unificar cuando se toque alguno de esos subsistemas.

### B27 — Documentación raíz obsoleta (README/ESTADO_PROYECTO dicen "no hay motor")
- **Clase:** DEUDA docs · **Prioridad:** MEDIA · **Estado:** CONFIRMADO VIVO
- **Archivos:** README.md:3-13,42-48; docs/ESTADO_PROYECTO.md:4,320-335 («Motor de juego: No», ~28% implementación — realidad: motor completo).
- **Impacto real:** confunde a colaboradores humanos e IA (este propio proyecto lo ha sufrido). Acción: reescritura corta apuntando a docs vivos + este plan.

### B28 — CSS responsive frágil (pila de parches)
- **Clase:** DEUDA UI · **Prioridad:** BAJA-MEDIA · **Estado:** CONFIRMADO estructura
- **Evidencia:** play-v3-responsive.css (~4.000 líneas, batches 10-15 con !important contradictorios, p.ej. altura tiles batch12b vs batch13), dependencia `:has()` para layout móvil, régimen mixto 720–768px (layout() JS usa <720; media query CSS 768).
- **Acción:** consolidación incremental SOLO tras estabilizar gameplay; probar navegadores sin :has().

### B29 — Salto nocturno 23→08 sin ventana de intervención
- **Clase:** DECISIÓN DE DISEÑO cuestionable (JUGABILIDAD) · **Prioridad:** MEDIA · **Estado:** PENDIENTE decisión
- **Archivos:** play-v3.js:2135 (HORA_NOCHE_DESDE=23), :4555 (salto a 08:00). Internamente es paso-a-paso (los encuentros SÍ se resuelven), pero el jugador no puede intervenir planes nocturnos (discoteca 22–04).
- **Opciones a decidir:** (a) permitir +1h de noche si hay encuentro propio en curso; (b) notificar resultado nocturno al amanecer; (c) mantener y aceptar. Ver M03/S01.

### B30 — Marchas/llegadas sin eco en Vida del Pueblo ni consecuencias amplias
- **Clase:** JUGABILIDAD/consecuencias · **Prioridad:** MEDIA · **Estado:** CONFIRMADO VIVO (grep: MarchaEngine no llama VidaPuebloEngine)
- **Impacto:** decisiones grandes sin consecuencia medible; el corazón no respira con demografía.
- **Comprobar con P05** (balance Vida en curso) antes de añadir deltas.

### B31 — Estado Git: WIP extenso sin commit + 4 tests borrados
- **Clase:** GESTIÓN/riesgo operativo · **Prioridad:** ALTA (higiene) · **Estado:** CONFIRMADO VIVO (foto 2026-08-23)
- **Detalle:** 31 ficheros modificadas sin commit; borrados sin commit: tests/buzon_leido_test.php, coincidencias_npc_test.php, cotilleo_autonomo_cadencia_test.php, pool_200_canon_test.php; sin trackear: DiarioResidenteBridge.php, CopyVariante.php, NombresReservadosPartida.php (¡código de producción sin versionar!), +~150 scripts dev/.
- **Acción:** consolidar por partes con sus agentes; restaurar o justificar tests borrados; versionar Engine nuevos. NO hacerlo en este plan (regla §12).

---

## SECCIÓN 2 — SISTEMAS EXISTENTES INFRAUTILIZADOS (S01-S11)

> Estado inicial de todas: PENDIENTE DE ESTUDIO. Ninguna es requisito; son oportunidades sobre código YA escrito.

### S01 — Intervención de Celestine poco visible
- **QUÉ EXISTE:** motor completo (6 acciones, requisitos, efectos al momento + sesgo final) cableado a UI (play-v3.js:1242-1298).
- **POR QUÉ NO SE PERCIBE:** solo vive dentro de la polaroid «Próximo plan»; ventana estrecha (en_curso); imposible de noche (B29).
- **CAMBIO MÍNIMO:** toast/badge al iniciar plan propio (reutiliza plan-notif) + noche parcial (B29a). **Estado sugerido:** ACEPTADA PARA ESTUDIO. Solapa: M03.

### S02 — Contrapropuestas tras rechazo sin acción
- **EXISTE:** franja alternativa generada cuando el rechazo es "ahora no" (aceptariaSocialmente) con copy propio.
- **NO SE PERCIBE:** llega como texto; no hay botón «Aceptar su contraoferta». **MÍNIMO:** reproponer con franja pre-rellena (dato ya en respuesta). **ACEPTADA PARA ESTUDIO.**

### S03 — Consejo de Celestine sin UI
- **EXISTE:** ConsejoEngine (inclinaciones ±10 voluntad) + acontecimiento consejo_celestine.
- **NO SE PERCIBE:** el jugador no puede aconsejar; efecto invisible. **MÍNIMO:** acción «Aconsejarle…» en ficha. Depende de B13 (arreglar duración/seguimiento). **INTERESANTE PERO FUTURA** hasta estabilizar voluntad (P01).

### S04 — Señales románticas/flechazos casi nunca disparan
- **EXISTE:** cadena flechazo→aviso único→gate primera_cita completa con copies.
- **CADENCIA:** flechazo casual ≈0.21% efectivo; peso familia romance 0.12. **Ver R02** (balance tras P01/P03). **REQUIERE PLAYTEST/MEDICIÓN.**

### S05 — Marchas casi inexistentes y sin eco
- **EXISTE:** sistema completo intención→mensajito→decisión.
- **CADENCIA:** ≥2 señales sostenidas + prob ≤0.35 + máx 1 pendiente ⇒ rarísimo. Sin eco en Vida (B30). **REQUIERE PLAYTEST** (medir antes de bajar umbrales).

### S06 — Lugares nocturnos decorativos
- **EXISTE:** discoteca 22–04/bingo/bar con planes válidos (hay test). **NO SE PERCIBE:** avance normal se salta la noche (B29). Ligado a S01/M03.

### S07 — Ausencia offline sin efecto (aplicarAusencia sin wiring)
- **EXISTE:** decay offline capado (−15, suelo 5, nunca game over) con tests; CatchUpPlanner registra pero no ejecuta.
- **MÍNIMO:** llamar aplicarAusencia en cargar() si offline > umbral. **DECISIÓN DE PRODUCTO pendiente** (¿queremos castigo por ausencia?). **INTERESANTE PERO FUTURA.**

### S08 — Economía visible-muerta (HUD dinero «—»)
- **EXISTE:** HUD, EconomyLedger (dev-only), fama placeholder; economy_enabled=false.
- **MÍNIMO:** ocultar slot hasta decidir, o alimentarlo con recompensas de misiones. **ACEPTADA PARA ESTUDIO** (al menos ocultarlo).

### S09 — 5 lugares zombi (plaza/arcade/tienda_ropa/mirador)
- **EXISTE:** catálogo+aforos fallback; candados cosméticos. **JAMÁS ABREN:** ComplejoCatalog::estaAbierto=false eterno (:83-99). Horarios meta de lugares.json contradicen PartidaSchema/LugaresCanonicos.
- **DECIDIR:** incluirlos por fases (desbloqueo barato vía config) o retirarlos del selector. **ACEPTADA PARA ESTUDIO.**

### S10 — Resumen de avance oculta actividad autónoma individual
- **EXISTE:** AvanceResumen.php:71-73 filtra deliberadamente salidas individuales autónomas.
- **EFECTO:** días "vacíos" percibidos aunque el pueblo se movió. **MÍNIMO:** línea agregada «Tres vecinos salieron por ahí». Parte de M01.

### S11 — SnapshotService + PlaytestInvariantes infrautilizados
- **EXISTE:** snapshots guardar/restaurar y auditor de invariantes (duplicados, dobles viviendas, aforos, NaN…).
- **SOLO DEV/playtest_01** + taller roto (B06). **MÍNIMO:** botones en panel DEBUG. **ACEPTADA PARA ESTUDIO** (barato y útil ya).

---

## SECCIÓN 3 — MEJORAS PROPUESTAS POR LA AUDITORÍA (M01-M13)

> NO canonizadas. Estado según §11: ACEPTADA PARA ESTUDIO / INTERESANTE PERO FUTURA / REQUIERE PLAYTEST / POSIBLEMENTE DESCARTABLE.

| ID | Mejora | Clase | Estado | Prioridad | Notas |
|---|---|---|---|---|---|
| M01 | Contar consecuencias existentes: B14 destello romántico + B03 coherencia + S10 resumen | JUGABILIDAD/UX | ACEPTADA PARA ESTUDIO | ALTA | Máximo retorno por coste; depende de liberarse B14/B03 |
| M02 | Tuning cadencias (flechazo/señales/marchas/declaraciones) solo-calibración | BALANCE | REQUIERE PLAYTEST | ALTA | Bloqueado por R02 (primero P01/P03); medir, no tunear a ojo |
| M03 | Notificación plan propio iniciado + noche parcial | JUGABILIDAD/UX | ACEPTADA PARA ESTUDIO | ALTA | Reutiliza plan-notif; decide B29 |
| M04 | Ledger de Vida legible en modal corazón (tail ya existe: PartidaService.php:549) | JUGABILIDAD/UX | ACEPTADA PARA ESTUDIO | MEDIA | Barato; complementa P05 |
| M05 | Arreglos herramientas playtest (B05+B06+B21) | UX/dev tools | ACEPTADA | ALTA | Son herramientas de ESTA fase |
| M06 | Caps persistencia (B01) | Deuda | ACEPTADA (tras R05) | ALTA | Política primero |
| M07 | Cache request-level configs/catálogos (B24) | Perf | ACEPTADA PARA ESTUDIO | MEDIA | Bajo riesgo |
| M08 | Consejo activo Celestine (S03) | JUGABILIDAD | INTERESANTE PERO FUTURA | MEDIA | Tras P01+ B13 |
| M09 | Contraofertas aceptables (S02) | JUGABILIDAD | INTERESANTE PERO FUTURA | MEDIA | Dato ya en respuesta |
| M10 | Decaimiento/mediación de conflictos | DISEÑO | REQUIERE PLAYTEST+diseño | ALTA | Es R04; no implementar aún |
| M11 | Higiene: reset contador (B07) + alinear deltas misión (B12) | Deuda | ACEPTADA (tras P05) | MEDIA | Coordinar con balance Vida |
| M12 | Eco demográfico en Vida (B30) | JUGABILIDAD | REQUIERE PLAYTEST | MEDIA | Con P05 |
| M13 | Consolidación CSS responsive | Deuda | POSIBLEMENTE DESCARTABLE (ahora) | BAJA | Tras estabilizar gameplay |

---

## SECCIÓN 4 — IDEAS FUTURAS (N-series + refinadas)

> Registro conservativo. Ninguna aprobada; ninguna descartada salvo nota expresa.

### F01 — Recap semanal de Celestine (refina N03)
- **IDEA FUTURA · INTERESANTE PERO FUTURA (bien valorada).**
- Cada ~7 días: mensaje narrativo con hitos de la semana (romances, citas, conflictos, marchas/llegadas, amistades, momentos raros). Reutiliza RelacionBitacora/DiarioEngine/BuzonEngine.
- **Cuándo estudiarlo:** cuando motor base estable (post P01/P03/P05) y tras R01 (para no chocar con rediseño de canales).
- **Criterio de estudio:** prototipo de agregador sin tocar motor; medir si mejora percepción de progreso.

### F02 — Celos dirigidos DÉBILES y contextuales (refina N01)
- **IDEA FUTURA · INTERESANTE PERO FUTURA, CON CONDICIÓN DE DISEÑO DURA.**
- **Condición innegociable:** celos iniciales débiles/contextuales. Un avistamiento casual produce como mucho: pequeña incomodidad, micro-emoción temporal, curiosidad, modificador leve. NUNCA crisis automática ni destrucción de relaciones largas por un avistamiento.
- Intensidad dependiente de: romance existente, estabilidad, historia, señales, REPETICIÓN, contexto realmente romántico. Solo acumulación o eventos fuertes → conflicto serio.
- Reutilizaría: SenalRomantica, CoincidenciasEngine, EmotionalStateService, cotilleos. Estudiar post-P01.

### N01..N07 restantes del informe original (resumen de estado)

| ID | Idea | Estado propuesto |
|---|---|---|
| N01 | Celos dirigidos | → fusionada en F02 (condiciones endurecidas) |
| N02 | Rumores exagerados | REQUIERE PLAYTEST — riesgo de dañar fiabilidad del cotilleo; estudiar copy antes |
| N03 | Recap semanal | → fusionada en F01 |
| N04 | Cartas de marchados | INTERESANTE PERO FUTURA — barata, gran sabor; tras R01 |
| N05 | Cadenas de favores | INTERESANTE PERO FUTURA — profundiza peticiones (mejor decisión actual); necesita métricas de cadencia B4 |
| N06 | Fiesta del pueblo | INTERESANTE PERO FUTURA — coste/riesgo medio-alto; requiere noche jugable (B29/M03) antes |
| N07 | Secretos revelables | POSIBLEMENTE DESCARTABLE tal cual — el contrato de rasgos ocultos está forzado a []; replantear cuando discovery madure |

---

## SECCIÓN 5 — RESUMEN DE ESTADO (foto al crear el plan)

| Clase | Entradas |
|---|---|
| BUG CONFIRMADO | B01,B02,B03,B04,B05,B06,B08,B13,B14,P03 (+B20 latente) |
| INCOHERENCIA/DEUDA | B07,B09(R04),B10,B12(P05),B15,B16,B17,B18,B19,B22,B23,B24,B25,B26,B28,P04,R03,R05 |
| BALANCE/CALIBRACIÓN | B11,B12,P05,R02,M02,M12 |
| JUGABILIDAD/UX | B21,B29,B30,S01-S10,M01,M03,M04,M05,P01,P02,P06,R01 |
| SISTEMA INFRAUTILIZADO | S01-S11 (todas) |
| IDEA FUTURA | F01,F02,N02,N04,N05,N06,N07,M08,M09,S07 |
| GESTIÓN | B31,B27 |

**Total entradas registradas: 75** (31 B + 11 S + 13 M + 6 P + 5 R + 2 F + ajuste N01/N03 fusionadas).

---

## SECCIÓN 6 — ORDEN PROPUESTO CUANDO SE LIBEREN LOS SUBSISTEMAS

*(candidatos a PRIMEROS fixes; ninguno se ejecuta sin validar colisiones con los agentes activos)*

1. **B05 + B06 + B21** (herramientas playtest: botón Lab, taller CSS, card Misiones) — sin solapamiento conocido, máximo beneficio inmediato para esta fase.
2. **B02** (bandas negativas) — fix pequeño; coordinar con agente de relaciones.
3. **B14** (destello romántico visible) — presentación pura, alto retorno perceptual.
4. **B03** (emoción A→B en cargaDe) — tras confirmar con P03/P04 que no choca.
5. **B01** caps — PERO solo tras entregar la política de retención R05.
6. **B24** cache — perf global, riesgo bajo.
7. **B13** consejos (fecha + probabilidad_seguir) — pequeño y autocontenido.
8. **B07** reset contador — trivial.
9. **B04** gate endpoints relación — tras verificar dependencias de play.js/dev.js.
10. **B27** reescritura breve de README/ESTADO_PROYECTO.

Re-evaluar esta lista cuando P01-P06 cierren.

---

## BITÁCORA DE CAMBIOS DEL PLAN

- 2026-08-23: creación del documento a partir de la auditoría general + contextos de playtest (P01-P06), investigaciones (R01-R05) e ideas refinadas (F01-F02). Foto Git: HEAD 50a0a3d + WIP sin commitear.
- 2026-08-23 (cierre P03): **P03 → RESUELTO Y VERIFICADO EN PRODUCCIÓN** (tema por participante, signo, plan_A/B, plan_lugar_match≠tema_match, vista romance [cierra B14], cotilleo cita mala coherente, pistas en ficha «Lo que sabes»; caso Dolores+Bingo/Sandra+Bingo cubierto por tests/intervencion_tema_test y presente en producción). **B14 → RESUELTO Y VERIFICADO.** Registrado INCIDENTE deploy-con-WIP-concurrente (96 ficheros; regresiones P2 y mitigación enfado 'ajena' en producción; continuidad_reciente activa) + REGLA OPERATIVA de deploy selectivo con WIP concurrente. B03 y B15 siguen vivos (no tocados).

- 2026-08-23 (B4 Mensajitos VIVOS + revalidación): **R06 → RESUELTO Y VERIFICADO EN PRODUCCIÓN** (variedad anti-rep; compromiso del peticionario exacto; bonus núcleo modificado; feedback cumplida/caducada/rechazo-tercero con petición abierta; deploy selectivo `deploy_mensajitos_vivos.ps1` con calibración merge-sobre-remota; verificación remota 6/6 marcadores + URL 200). **R01 → PARCIAL** (canal B4 personal vivo; reparto completo de emisores pendiente). **R07 creada ABIERTA** (cadencia por población: "variedad corregida; frecuencia objetivo por población pendiente"). Estado remoto revalidado: R3 ✓ completo y activo; M2+/continuidad_reciente ✓ ACTIVA (`voluntad.continuidad_reciente.activo=true` + código); P2 ❌ SIGUE REGRESADA en remoto (mod_tipo.primera_cita=0 en vez de 4; bonus_primera_cita_reciproca ausente en vez de 12; conflicto_mult_cita ausente en vez de 3) — colisión del incidente ya registrado, NO corregida en esta tarea; enfado direccional código ✓ pero knob `emociones_v1.enfadado_ajeno_aceptar_planes` AUSENTE en remoto ⇒ 'ajena' sin mitigación (-16 en vez de -8). Lateral nuevo: whitelist de `SimuladorPeticionesPueblo::contarExtraVida` no conoce causa `dia_misiones_ignorado` ⇒ test "lab mini sin farming" falso-positivo de farming hasta que R3 y lab se sincronicen. Tests emocion_enfado_direccional (13 FAILs) y primera_cita_balance_p2 (12 FAILs) fallan por el mismo WIP concurrente, no por B4 (verificado aislando deltas).

- 2026-08-23 (R3 deploy): implementado y en producción el castigo único por día de misiones ignorado (caducada individual = 0; `vida_dia_ignorado=-3`; ledger `dia_misiones_ignorado`). Commit 7b10642 aislado sin WIP ajeno. Hallazgos laterales derivados:
  - L-FLAKY-SMB: lectura intermitente de archivos obsoletos sobre UNC/SMB (fatal esporádico `calcularContinuidadReciente` en WIP de VoluntadPonderadaEvaluator; número de línea del trace no coincidía con el archivo actual). No es bug de juego: es caché de red/AV. Afecta a tests CLI largos.
  - L-CHECKOUT-INDEX: `git checkout-index` no exporta tests sin trackear (pasar_noche_test.php etc. solo existen en worktree); la validación "estado commiteado" no los incluye.
  - L-INDEX-PREVIO: se encontró `src/Engine/HayTema.php` ya staged en el index antes de esta tarea (WIP ajeno); des-stage hecho para commit limpio. Revisar quién lo dejó.
  - L-LEDGER-DIA: entradas de cierre del día D quedan etiquetadas con dia=D+1 en el ledger de vida_pueblo (el reloj avanza antes de cerrar misiones). Cosmético, pero rompe trazas/agrupaciones por día.
  - L-AUSENCIA-HOOK: infra canónica de ausencia ya cableada: PartidaLifecycle::cargar() llama Reloj::calcularCatchUpPendiente() y persiste segundos reales en reloj.catch_up_pendiente sin tocar el reloj del pueblo. Penalización offline suave puede engancharse ahí SIN segundo sistema; cap motor −15/suelo 5/nunca GO ya protegido (estresado). Curva recomendada: gracia 24 h + −1/día lineal (el cap −15 del motor da forma a la cola). PENDIENTE autorización + política anti-doble-cobro con futuro catch-up (un día de pueblo cobra una sola vez y por un solo mecanismo).

- 2026-08-23 (P07 UX): registrada e implementada en worktree la **reorganización UX del selector de temas** en «Plan en curso» (P07 arriba): intervenciones generales como acciones principales + todos los hobbies agrupados bajo «💬 Elegir un tema…» con panel/scroll; handlers, payload y gates intactos; cero cambios PHP (`intervencion_tema_test` OK post-cambio). **NO DEPLOY**: `play-v3.js` arrastra WIP concurrente activo de P06 (iconos mapa + marcadores hay_tema) → REGLA OPERATIVA §DEPLOYS aplicada; pendiente coordinación y smoke manual desktop/móvil para marcar RESUELTO.

- 2026-08-23 (limpieza ficha: fuera LE GUSTA / NO LE GUSTA): retiradas de la FICHA DE VECINO las secciones legacy «Le gusta» / «No le gusta» («Gente: ? · ?», slots de personalidad interpersonal) en desktop y móvil (mismo DOM, flujo normal ⇒ «Lo que sabes» y Relaciones suben sin huecos). **Solo presentación**: `FichaPlayVista` sigue exponiendo `gusta_en_gente`/`no_gusta_en_gente` (motor/API intactos, consumibles por otras mecánicas); el fill JS queda como no-op seguro (guard nulo); CSS `.ficha-seccion-prefs`/`.ficha-pref-line` se conserva (lo reutiliza «Lo que sabes»). Origen verificado: markup en `play.php` + único consumidor en `play-v3.js` (líneas ~3397-3400 prod). Deploy SELECTIVO de UN solo archivo (`scripts/deploy_ficha_prefs_off_only.ps1`, con salvaguardas anti-regresión en staging): fuente real remota descargada por WinSCP → parche → subida solo de `play.php`; NO se tocó `play-v3.js` ni cache-buster (innecesario: HTML dinámico). Verificación post-deploy remota OK: hooks retirados ausentes; `data-ficha-sabes(-body)`, hobbies, relaciones, planes, org y `data-animo-overlay` (P03) presentes; URL 200. Tests: nuevo `tests/ficha_prefs_quitadas_ui_test.js` OK (22 asserts); `intervencion_tema_test.php` y `relacion_coherencia_fichas_test.php` OK. ⚠️ NOTA OPERATIVA: `tests/ficha_pistas_ui_test.js` sigue FALLANDO en worktree por desync PREEXISTENTE (el árbol local aún no tiene el trabajo P03 de «Lo que sabes» en su `play.php`/`play-v3.js`; ese trabajo vive solo en producción). Consecuencia directa: **NO lanzar deploy general/incremental desde este worktree** — sobrescribiría el `play.php`/`play-v3.js` remotos con versiones sin P03 (mismo patrón que el INCIDENTE de WIP concurrente). Pendiente: sincronizar a worktree el estado P03+P06/P07 antes de cualquier deploy masivo.

- 2026-08-23 (reconciliación M2+ + P2 + enfado — LOCAL, SIN DEPLOY): restauración quirúrgica de P2 sobre el evaluador M2+ vigente (detalle completo en el INCIDENTE, sección «RESOLUCIÓN LOCAL»). Knobs config restaurados (`mod_tipo.primera_cita=4`, `bonus_primera_cita_reciproca=12`, `conflicto_mult_cita=3`, `enfadado_ajeno_aceptar_planes=-8`); código: conflicto ×3 solo primera_cita/cita, bonus recíproco solo primera_cita con señal mutua canónica; M2+ y enfado direccional intactos. Tests bloqueadores 0 fails; regresiones OK salvo dos fallos PREEXISTENTES de WIP ajeno verificados contra baseline (stash): **L-WIP-COPYTOAST**: `rechazo_atribucion_canonica_test` 2 fails («toast transmite que Dolores sí parecía dispuesta») — copy de toasts en WIP ajeno; **L-WIP-COOLDOWNPAR**: `rechazo_cooldown_neutro_test` FATAL `CopyRechazoPropuesta::mensajeCooldownPar()` inexistente (llamado desde PropuestaEncuentroEngine.php:97) — método prometido por el WIP ajeno no llegó al worktree. Ambos NO son de esta tarea; escalar a su agente. Simulación A–F motor real correcta (orden E<C/F<D). **BLOQUEO ACTIVO:** simulación de extremos arroja 44% de combinaciones favorables-sistemáticas >0.85 (máx 0.920 = cap p_excelente) para primera_cita+señal mutua+muy_bien reciente → decisión de balance pendiente del usuario antes de deploy. Scripts evidencia: `dev/_sim_reconciliacion_af.php`, `dev/_sim_extremos_p2_m2.php`.

## INVENTARIO POST-RESTAURACION (2026-08-23 noche) - regresiones visuales observadas por la jugadora

Contexto: produccion restaurada 20:19 (play.php=C22165B4..., play-v3.js=DB31B877..., buster v3-20260823-201952). Backup PRE del estado regresado: dev/_prod_fetch_prerestore/20260823-201923/. Causa raiz ADICIONAL identificada en esta investigacion: una sesion FTP SIN LOG (~19:42-19:46) subio a produccion responsive.css/shell-art.css (y quizas mas CSS) en version POST-stash vieja (mtimes remotos MLST: responsive=19:08:49, shell-art=19:42:16, app=19:43:49). El stash@{0} "aht-tmp-verify" (creado 19:08:49) contiene las ULTIMAS versiones buenas de esos CSS: responsive=122540 B, shell-art=64312 B, play-v3.js=205489 B, play.php=47918 B.

- INV-AHT-ICONOS - REGRESION RECUPERABLE (parche minimo): el play-v3.js restaurado emite `class="tema-hab mapa-tema--<cat>"` pero el CSS aprobado (ya en prod, play-v3-app.css L887-985) define `.tema-hab--romance` (#e0688f + halo 3 capas), `.tema-hab--drama` (#f0a94f ambar), `.tema-hab--relacion/--coincidencias` (#3f5a86 azul) sobre svg fill/stroke currentColor => los iconos salen NEGROS sin glow por mismatch de clase. La version que se vio bien (19:52-20:19) era la del JS regresionado (E64EA6D3). Accion futura: alinear la emission de clases en el JS bueno (1 linea) o anadir alias CSS; NO tocar app.css.
- INV-VEC-REL-MODAL - REGRESION RECUPERABLE: el fix del modal de Relaciones (grande/centrada/sin Vecinos detras/scroll/«<- Vecinos del pueblo»/X) vivia en play-v3-responsive.css (deploys 15:48 [114905 B], 16:26 [118781 B], canonical 18:33 [119826 B]); produccion actual tiene 97102 B SIN ninguna regla vec-rel. Copia buena: responsive.css del STASH@{0} (122540 B, vec-rel x20). Restaurar fichero completo tras diff contra remoto.
- INV-PASAR-RATO-NOCHE - REGRESION RECUPERABLE: mismo vehiculo. Diseno movil aprobado (reloj junto a hora, Pasar el rato secundario, luna nocturna, keyframes) SOLO en responsive.css del stash (pasar-rato x15, noche x11, luna x3, keyframes x2). Remoto: 0. Velo parcialmente presente (remoto velo=12).
- INV-FEEDBACK-INTERVENCION - REGRESION RECUPERABLE: seccion "FEEDBACK DEL RESULTADO DE INTERVENCION" (.enc-int-result / .enc-int-result-txt estilo polaroid: centrado, .88rem, bordes irregulares, rotate(-.35deg), ::after puntos, margen 1.9rem antes de NUEVO PLAN) SOLO en responsive.css del stash (x13). El JS en prod ya pinta enc-int-result x3; la logica tono bien|neutral|mal (EncuentroIntervencion::tonoDe) no se toca. Test de referencia: tests/enc_result_ui_test.js (define el estado deseado).
- INV-EN-CURSO-DESKTOP-DUPLICADO - REGRESION RECUPERABLE (mismo vehiculo): el bloque derecho "En curso" en desktop es la seccion movil `<section class="encursos-movil">` (play.php L777-784) filtrandose al desktop porque el responsive regresionado no contiene sus reglas (0 menciones enc-movil). Con el responsive del stash desaparece el duplicado; la caja canonica izquierda `obj-proximo-polaroid` (L383-391, con data-curso-prev/data-curso-next y data-proximo-plan) ya es el componente correcto. NO eliminar markup.
- INV-ENCUENTROS-SIMULTANEOS - PARCIALMENTE EN PROD / PENDIENTE DE VERIFICACION: integrado en prod: carrusel desktop (data-curso-prev/next) + carrusel movil encima de Cotilleos (renderEncursosMovil x6, encMovPaso x3, htmlEncursoCardMovil, fuente canonica unica L1226/L1359). Indicador 1/N explicito NO localizado. El agente parado dejo verificacion funcional Playwright lista: dev/verify_encursos_movil.js (node+php -S 8765) con screenshots en dev/screenshots-encursos-movil. Estado: PENDIENTE DE INTEGRACION/VERIFICACION hasta ejecutar ese oracle; no dar por terminado.
- INV-FICHA-NAV - PENDIENTE DE INTEGRACION (nunca desplegado): navegarFicha/syncFichaNav existen en el JS de prod (queries L3443-3444 `[data-ficha-nav-prev|next]`) pero NINGUNA copia de play.php (restaurada C22165B4, stash 47918, ni HTML publicado) contiene esos botones. Falta el hunk de markup (candidato: cabecera de aside.capa-ficha L562). Implementacion funcional completa = JS actual + hunk de botones por crear.
- INV-VARIEDAD-FRASES - MIXTO, NO ASUMIR RESUELTO: (A) Llego a produccion: P2 atribucion (deploy 15:40), enfado direccional (16:24), canonical 18:33 (CopyVariante NUEVO, DiarioResidenteBridge NUEVO, VistaCotilleoV3, EncuentroCotilleoCopy, EmocionalNarrativa...), mensajitos B4 (18:43). (B) WIP local SIN deploy: diffs M vs HEAD en CopyRechazoPropuesta/CopySenalRomantica/EmocionalNarrativa/etc.; fails documentados L-WIP-COPYTOAST (rechazo_atribucion_canonica_test, 2 fails) y L-WIP-COOLDOWNPAR (PropuestaEncuentroEngine llama CopyRechazoPropuesta::mensajeCooldownPar, metodo que faltaba en worktree - verificar pareja coherente en prod antes de tocar nada). (C) Nunca terminado: todo lo no cubierto por A/B (pools/anti-repeticion adicionales).
- INV-REVALIDACION-CORE (2026-08-23 20:4x, contra REMOTO): P03 intervencion_tema=true en calibracion remota (CD4DCD3B...); R3 vida_dia_ignorado presente (x2) y misiones_diarias ok; B4 p_nacer_hora_base=0.058 + anti_rep_ventana=3 (merge 18:43 intacto); HayTema radar protagonistas en remoto (E1B654CC..., actoresDeMensaje/Radar espacial presentes); Lo que sabes restaurado. TODO PRESENTE.
- REGLA PARA LA FASE DE RECUPERACION: cada item en rama/worktree separado, commit OBLIGATORIO antes de integrar, UN unico agente autorizado a integrar/deployar. Items 2/3/4/5 comparten vehiculo: restaurar play-v3-responsive.css desde stash@{0} (tras diff contra el remoto 97102 para confirmar que no se pierde nada posterior) + nuevo cache-buster + verificacion por marcadores (vec-rel/noche/luna/pasar-rato/FEEDBACK DEL RESULTADO) y tests enc_result_ui_test.js, pasar_rato_movil_ui_test.js, pasar_noche_ui_test.js, vec_rel_layout_ui_test.js. Nada se marca RESUELTO hasta deploy + verificacion remota + smoke visual.

## AUDITORIA DE SEGURIDAD POST-RESTAURACION (2026-08-23 noche, fase 2)

Metodo: lectura integra de este plan + descarga REMOTA de los 46 ficheros src/Engine+api desplegados el 18:33/18:43 y comparacion tamano-a-tamano contra los logs WinSCP de aquellos deploys + barrido de marcadores de mecanica + claves profundas de calibracion remota (CD4DCD3B...) + MLST de mtimes + diff semantico selector-a-selector del responsive stash vs remoto.

RESULTADO GLOBAL: 45/46 Engine/API identicos a lo desplegado antes del accidente => toda la mecanica backend (P03, B14, P06-logica, R06/B4, R01-parcial, R3, M2+, enfado direccional, limpieza ficha) SIGUE CABLEADA EN PRODUCCION. La regresion real se limita a la capa visual (responsive.css + shell-art.css sustituidos por versiones viejas via sesion FTP SIN LOG ~19:42-19:46) y al mismatch de clase del indicador AHT tras la restauracion.

HALLAZGOS NUEVOS DE ESTA AUDITORIA:
- H1: api/handlers/EncuentrosHandler.php en produccion (11908 B) NO es el del canonical 18:33 (11752 B): es la version worktree 18:46 con `VidaPuebloEngine::rechazoSiPerdida` + exposicion `encuentros_en_curso` (WIP del agente de encuentros simultaneos). Subida SIN LOG. Dependencia verificada presente en el VidaPuebloEngine remoto (sin fatal). Clasificar como WIP-ajeno-en-prod; NO revertir hasta integrar item varios-encuentros.
- H2: calibracion remota HOY contiene los knobs P2 (mod_tipo.primera_cita=4, bonus_primera_cita_reciproca=12, conflicto_mult_cita=3) y enfadado_ajeno_aceptar_planes=-8. Mejor de lo que esta bitacora registro tras el incidente (entonces ausentes). PERO el codigo remoto VoluntadPonderadaEvaluator sigue SIN leer bonus_primera_cita_reciproca/conflicto_mult_cita/delta_multiplier_positivo/mod_social_factor (0 refs) => knobs inertes; M2+ (mod_continuidad_reciente + calcularContinuidadReciente) SI cableada. Estado = exactamente el pre-accidente: reconciliacion P2 sigue LOCAL/SIN DEPLOY y bloqueada por extremos >0.85.
- H3: diff semantico responsive stash(122540) vs remoto(97102): el stash ANADE ~109 selectores agrupados por feature (REL-MODAL +13, FEEDBACK-INTERV +11 con ::before bien/neutral/mal, RATO-NOCHE +14 con .es-noche/.es-noche-luna, ENCURSO-MOVIL +25, BUZON +9 sello-estado/acciones, COTILLEO +9 badge-pulso/aviso-importante/star, FICHA +6 INCLUYE .ficha-nav/.ficha-nav-next/.ficha-btn-diario, OTROS +20, prefers-reduced-motion +1) y SOLO se perderian 2 selectores de buzon phone (.carta-msg:has(.sello-estado) .cuerpo / .msg-leido-toggle variante phone) cuyas reglas equivalentes existen en el stash con otra forma => riesgo bajo; anadir esos 2 selectores como hunk de merge tras restaurar.
- H4: shell-art.css tambien requiere restauracion desde stash (64312 vs remoto 62874; noche x10/luna/velo solo en stash).
- H5: produccion contiene multiples ficheros BUENA-COPIA-UNICA sin versionar en Git (play-v3.js 208017, play.php 48033-ficha-fix, EncuentrosHandler 11908, responsive/shell-art stash-era tras recuperar): sincronizar/commitear OBLIGATORIO antes de cualquier nuevo deploy masivo.

CLASIFICACION POST-ACCIDENTE (entradas que constaban terminadas/activas):
- INTACTO EN PRODUCCION: P03 (tema_cargas en Intervencion/Experiencia + knobs 0.18/0.22/-0.28/0.35), B14 (canalRomance a_hacia_b/b_hacia_a), P06-logica (radar actoresDeMensaje), R06/B4 (historial_plantillas/anti_rep/compromiso en PropuestaEncuentro/peticion_resultado/knobs 30-25-0.058), R01-parcial, R3 (vida_dia_ignorado/dia_misiones_ignorado), limpieza Le/No-gusta (play.php restaurado), enfado direccional (hacia/vencido/enfadado_ajeno + knob -8).
- PARCIALMENTE REGRESIONADO: P06-indicador visual (mismatch clase mapa-tema-- vs tema-hab--, parche 1 linea).
- REGRESIONADO (recuperable 100% via stash@{0}): capa visual responsive+shell-art (modal Relaciones, pasar-rato/noche movil, velo, feedback polaroid intervencion, ocultacion encursos-movil en desktop, badges cotilleo, sellos buzon).
- YA ERA PENDIENTE ANTES DEL ACCIDENTE: P2 reconciliada-local (bloqueo extremos >0.85), knob-enfado (resuelto en config actual), R07 cadencia B4, B09/R04 conflicto, B31 higiene Git (AGRAVADA: ver H5).
- REQUIERE PRUEBA FUNCIONAL: M2+ continuidad (revalidacion conjunta con P2 cuando se despliegue), varios encuentros simultaneos (oracle dev/verify_encursos_movil.js pendiente de ejecutar).

INCIDENCIA DE PROCESO (REGLA OPERATIVA ampliada - NO parchear deploy_lib todavia):
- deploy_lib_aht.ps1 (Enhance-AhtNormalDeployFiles) AUTOANADE play.php a cualquier deploy con assets/api/src: provoco parte del accidente de las 19:52. ARREGLAR ANTES de volver al flujo normal.
- PROHIBIDO desplegar desde worktree dirty sin listar explicitamente cada fichero y su contenido aprobado.
- PROHIBIDO usar stash como mecanismo de coordinacion entre agentes (stash@{0} sustrajo el trabajo desplegado y provoco la regresion).
- PROHIBIDO sesiones FTP manuales sin log en repo (causa de H1/H3/H4).
- PRODUCCION NO PUEDE SER LA UNICA COPIA BUENA: todo estado desplegado debe existir en un commit (H5).

- 2026-08-23 noche (FASE RECUPERACION 1 y 2 - NUEVO PROTOCOLO GIT, SIN DEPLOY): creadas ramas+worktrees aislados fuera del webroot (C:\Users\agl03\AppData\Local\Temp\opencode\wt\). Stash@{0} NO consumido (solo lectura de blobs via checkout <sha> -- path). Worktree compartido intacto.
  * rec/css-responsive-stash-restore -> commits cc6ef26 + 0c03827. Ficheros: play-v3-responsive.css (+1109/-) desde blob stash 122540 B; play-v3-shell-art.css (+343) desde blob 64312 B; merge phone-buzon (6 bloques sello-estado/msg-leido-toggle preservados del remoto 97102). Recupera: modal Relaciones, pasar-rato movil, noche/luna/velo (.es-noche/.noche-activa), FEEDBACK polaroid intervencion, ocultacion encursos-movil desktop, badges cotilleo, ficha-nav styles, prefers-reduced-motion. NOTA: el tooltip data-tip de filtros vec-rel ya vive en bloques-residencias.css de produccion (se retiro reconstruccion redundante en 0c03827 para evitar doble ::after).
    Tests (worktree + fuentes prod restauradas temporales donde el test las exige): enc_result_ui_test 12/12 · pasar_rato_movil_ui_test 31/31 TODO OK · pasar_noche_ui_test 54/54 TODO OK · vec_rel_layout_ui_test 21/21 TODO OK.
  * rec/aht-iconos-clase -> commit 48a5b47. Ficheros: assets/js/play-v3.js (base = produccion restaurada DB31B877..., no versionada; cambio de 1 linea: class "tema-hab mapa-tema--<cat>" => "tema-hab tema-hab--<cat>") + tests/mapa_tema_exterior_ui_test.js actualizado al contrato correcto (el anterior codificaba el nombre bug). Colores/halo del app.css INTOCADOS.
    Tests: todos los asserts estaticos JS+CSS OK con app.css de produccion (corazon rosa visible, drama rayo, halo/currentColor/movil19px/anclado). Pendientes de arnes completo (php -S+playwright+motor P06 sin versionar): sin-tema x2, resuelto; es-noche depende del shell-art de la otra rama.
  * ESTADO: ambas recuperaciones COMMITTEADAS y verificadas estaticamente. NO DEPLOY (deploy_lib_aht.ps1 BLOQUEADO por auto-play.php hasta sus guardas). Siguiente: integrador autorizado decide merge a estable + deploy con backup PRE; luego qa/encursos-simultaneos-oracle.

## AUDITORIA FORENSE DE SESIONES HISTORICAS OPENCODE (2026-08-23 noche, fase 3 - SOLO LECTURA)

Fuente: opencode.db (SQLite, modo read-only; 80 sesiones del proyecto) + almacen snapshot interno de OpenCode
(C:\Users\agl03\.local\share\opencode\snapshot\0688781b.../fab7a385.../ = repos Git de contenido con 9.116 objetos,
sin commits; minado por marcadores -> _forense-sesiones/blobs_reporte.txt). Digestos por sesion en
_forense-sesiones/ses_*_digesto.txt. Exportaciones en _forense-sesiones/candidatos/. NADA aplicado a produccion ni worktree.

HALLAZGOS CLAVE:
- F1 FICHA-NAV: la sesion ses_fd22d7 ("Ficha vecino anterior y siguiente", 10:52-13:43) implemento COMPLETA la navegacion (< FICHA DE VECINO >, botones data-ficha-nav-prev/next + SVG + boton Diario) tocando play.php/play-v3.js/bloques/responsive; termino FINALIZADO con pulido CSS. El markup existio en play.php (lineage ~18:48-19:04: blob snapshot 6f7739c1/1adb0496/61b05ed1 ~49.6KB con ficha-nav-row + obj-curso-btn x2 + data-es-noche+luna) pero fue perdido del arbol por las reversiones concurrentes; produccion actual (restaurada) NO lo tiene. RECUPERABLE: candidatos exportados.
- F2 PLANES SIMULTANEOS 1/N: sesion ses_fd08b7 (18:29-21:06) termino "implementacion local + tests" SIN commit/deploy (bloqueo E2E MySQL): fuente canonica 0..N en ResumenDia/PartidaService, carrusel movil sobre Cotilleos, nav desktop "< 1/N >" sobre polaroid unica, identidad por encuentro_id. Su estado JS mas avanzado EXISTE como blob snapshot b0cc319f (210507 B; data-curso-prev + enc-mov-card + renderEncursosMovil + encMovPaso + "/ N") SUPERIOR al restaurado 208017 (que carece de data-curso-prev). El handler EncuentrosHandler 11908 ya en prod pertenece a esta linea (dependencia rechazoSiPerdida verificada presente).
- F3 BLOQUE MOVIL ENCURSOS: sesion ses_fd0a30 reporta su implementacion REVERTIDA por sesion concurrente 19:41-19:46 (coincide con la ventana FTP-sin-log identificada); patch nunca generado; oracle Playwright dev/verify_encursos_movil.js nunca llego a pasar. Partes vivas hoy via js restaurado (renderEncursosMovil etc.).
- F4 FEEDBACK INTERVENCION + FIX RELACIONES: sesion ses_fd155c creo el CSS polaroid bien/neutral/mal y el fix vec-rel, DEPLOYADOS a las 15:48/16:26 (logs deploy-rel-fix citados dentro de la propia sesion) -> ya recuperados en rama rec/css-responsive-stash-restore.
- F5 AHT ICONOS/P06: sesion ses_fd23b8 (modelo nemotron, 10:37-21:05) cerro P06 con despliegues documentados buster 195213/195847 y actualizo el Plan Maestro; el mismatch de clase detectado tras nuestra restauracion se explica porque esa sesion trabajo sobre la linea del JS regresado. Fix ya commiteado en rec/aht-iconos-clase (48a5b47).
- F6 P2/M2+/ENFADO: sesiones fd15d6 (P2 atribucion+balance), fd0fda (M2+ continuidad), fd189a (rechazo Sandra-Dolores) confirman exactamente el estado ya auditado: deploy 15:40/16:24 para atribucion/enfado; M2+ activa; reconciliacion P2 LOCAL sin deploy bloqueada por extremos>0.85.
- F7 VARIEDAD NARRATIVA: sesion ses_fd122e (nemotron, "continua" x2) termino EJECUTANDO el script canonical de deploy (~21:05 segun digesto) PERO verificado contra produccion: CERO cambios post-restauracion (buster 201952 intacto, play.php C22165B4, responsive 97102; sin logs nuevos). Posible ejecucion fallida/interrumpida. Marcar como sospechosa de reintentar; vigilar.
- F8 NOCHE/RATO/CABECERA: sesiones fd1b85/fd112d/fd0ea3/fd23cf/256d generaron el diseno aprobado (luna SVG, papelito, batch30, es-noche) presente en stash responsive (ya en rama rec/css) + play.php restaurado (data-es-noche/luna OK en tests).
- F9 P07 SELECTOR TEMAS: sesion fd0666 quedo en investigacion/post-incidente sin implementar en prod (coincide con plan).

CONTRADICCIONES SESION vs REALIDAD: ninguna sesion FINALIZADO implica estado actual: fd22d7 (ficha-nav) y fd0a30 (encursos) dicen terminado y NO esta en prod; fd122e apunta deploy canonical no materializado. Regla mantenida: solo cuenta codigo+produccion verificados.

CANDIDATOS EXPORTADOS (forense, NO aplicar sin rama propia):
- candidatos/play_php_candidato_49668_6f7739c1.php (+variantes 49620/49466): base ~18:48 CON ficha-nav/curso/es-noche PERO aun con secciones legacy Le/No-gusta => usar SOLO como donante de hunks sobre play.src.php restaurado.
- candidatos/play_v3_js_candidato_210507_b0cc319f.js (+209385/209277): linea planes-simultaneos completa (carrusel+1/N+ficha-nav queries); requiere fix tema-hab-- (mismo parche 1 linea) antes de integrar.
- candidatos/responsive_candidato_77160_935333dc.css (+75054): variante consolidada con FEEDBACK/noche/ficha-nav; alternativa a estudiar frente al stash 122540.

RECOMENDACION POST-INTEGRACION (tras mergear rec/css y rec/iconos):
1. feat/ficha-nav-markup: hunks de play_php_candidato_49668 (ficha-nav-row + obj-curso-btn) sobre play.src.php restaurado.
2. feat/planes-simultaneos-1N: partir de js candidato 210507 + fix tema-hab-- + tests planes_simultaneos_ui_test.js/resumen_dia_encuentros_en_curso_test.php (ya existen en shared tests) + ejecutar dev/verify_encursos_movil.js.
3. Vigilar posible reintento de deploy canonical por sesion nemotron (F7).
- 2026-08-23 noche (FASE RECUPERACION 3): rama feat/ficha-nav-markup (worktree aislado, apilada sobre rec/aht-iconos-clase). Injerto quirurgico UNICO bloque ficha-nav-row (prev/Diario/next con SVG) desde snapshot blob 6f7739c1 (lineage ses_fd22d7 FINALIZADO) sobre play.php restaurado C22165B4. Descartados del donante: secciones legacy Le/No-gusta, .ficha-sabes-ico margin, linea vida-derrota-pie. Cero JS (wiring ya presente), cero CSS aqui (vive en rec/css). php -l OK + 12/12 aserciones especificas. NO DEPLOY.
- 2026-08-23 noche (BASE DE INTEGRACION): rama int/recuperaciones-20260823 (worktree aislado wt/integracion) desde base 29c4732. Merges --no-ff en orden: rec/css-responsive-stash-restore (8e9e9b2), rec/aht-iconos-clase (4392100), feat/ficha-nav-markup (dc0dc7c). CERO conflictos (ficheros disjuntos por diseno). Validacion: php -l OK; coherencia marcadores completa (ficha-nav markup prev/next/diario=1/1/1; js wiring navegarFicha x2 + tema-hab-- emitido x1 / residual mapa-tema--=0; responsive stash vec-rel=20 FEEDBACK=1 es-noche=8 pasar-rato=15 .ficha-nav-row=1). Tests con overlays temporales de app.css/bloques (ya correctos en produccion, nada nuevo a desplegar): enc_result TODO OK, pasar_rato_movil TODO OK, pasar_noche TODO OK, vec_rel TODO OK, mapa_tema 3 fallos residuales documentados (arnes php-S+playwright+motor P06 sin versionar: sin-tema x2, resuelto). Leccion: al copiar tests desde el arbol compartido se sobrescribio la version corregida del test mapa_tema commiteada; detectado y revertido (usar SIEMPRE la version del commit de la rama). Estado final dc0dc7c limpio. NO DEPLOY; pendiente decision de integrador sobre despliegue conjunto (incluye restaurar responsive/shell-art + js + play.php y buster nuevo).
- 2026-08-23 noche (FASE RECUPERACION 4): rama feat/planes-simultaneos-1N (worktree aislado, base dc0dc7c) -> commit d3ecb37. JS: 4 hunks desde snapshot b0cc319f (cursoSelId estable por id; renderShellPanels consume encuentrosEnCursoAhora 0..N; nav < 1/N > con contador data-curso-cont; moverCursoSeleccion+bindCursoNav sin acciones ni API) + guard typeof para arneses estaticos. PHP recuperado de snapshots (nunca llego a produccion, donantes verificados como prod+adiciones puras sin eliminaciones): ResumenDia blob 7b05b614 (encuentrosEnCurso+duracion_minutos), PartidaService blob e1890e97 (1 linea exposicion canonica, ASSERT resultado==donante), handler versionado byte-exacto al superviviente 11908/36162AEC (regla H5). Rechazado: hunk que revertia fix tema-hab--. tests/planes_simultaneos_ui_test.js commiteado. Resultados: planes_simultaneos 29/29 TODO OK (1 activo=1/1; 2+ navegables sin mezclar datos; movil conserva bloque sobre Cotilleos; escritura dirigida por encuentro_id; organizar/intervencion intactos). Regresiones: enc_result/pasar_rato/pasar_noche/vec_rel TODO OK; mapa_tema 3 fallos documentados de arnes. php -l x3 + node --check OK. Pendiente E2E real verify_encursos_movil.js. NO DEPLOY; no merge a int/ hasta autorizacion.
- 2026-08-23 noche (INTEGRACION PLANES-1N): d3ecb37 integrado en int/recuperaciones-20260823 -> merge --no-ff 1135046 SIN conflictos. Verificacion previa +164 lineas PartidaService: confirmado baseline valida = linaje de produccion 18:33 ya desplegado y verificado (vistaRelacionesPueblo/dirVistaRelacion/relevanciaDirRelacion = P02 barras sociales; 0 restos dev/debug; php -l OK) + 458 CRLF normalizados a LF (commit==donante-normalizado: True) + la 1 linea de la feature. Resultado commit == donante normalizado ASSERT True. Validacion rama integrada: php -l x4 OK; ficha-nav 1/1/1; responsive vec-rel=20 FEEDBACK=1; fix tema-hab--=1 residual=0; encuentros_en_curso service=1 resumen(func)=1 handler>=2; nav data-curso-nav/cont=1/1 moverCursoSeleccion=3; planes_simultaneos_ui_test TODO OK (29/29). Estado limpio. NO DEPLOY.
- 2026-08-23 noche (CIERRE E2E): decisiones usuaria aplicadas sobre int/recuperaciones-20260823 -> commit (ver SHA en bitacora git): (1) gate CSS desktop @media min-width:769px .play-v3 .encursos-movil{display:none!important} en responsive; (2) arnes verify_encursos_movil.js VERSIONADO con expectativa E corregida a semantica estable por cursoSelId (2/2 legitimo). E2E real 28/28 TODO OK; planes_simultaneos_ui_test TODO OK. Cero cambios de JS de producto. NO DEPLOY.
- 2026-08-23 noche (DEPLOY AUTORIZADO de int/recuperaciones-20260823 @ daf1962): backup PRE remoto en _forense-sesiones/predeploy/20260823-225318 (+copia duradera dev/_prod_fetch_predeploy/20260823-225318, hashes-PRE.txt). Subida selectiva explicita de 7 ficheros daf1962 + buster v3-20260823-225321 (sin deploy_lib/auto-play.php; solo helpers de credenciales). Verificacion: 8/8 byte-IDENTICO contra commit; HTTP 200 x4; HTML servido con ficha-nav(1/1/1)+sabes+curso-nav/cont; JS servido tema-hab--=1 mapa-tema--=0 cursoSelId/moverCursoSeleccion presentes; responsive vec-rel-overlay=7 FEEDBACK=1 gate-769 presente; shell-art noche; ResumenDia.encuentrosEnCurso+duracion_minutos; PartidaService inyeccion encuentros_en_curso x1; handler x4. Incidente mecanico previo al deploy real (rutas '/' en WinSCP -> 0 bytes transferidos) resuelto con backslash; produccion nunca afectada por el intento fallido. Siguiente: smoke visual humano + decidir sincronizacion/versionado H5 del nuevo estado.
- 2026-08-23 noche (CIERRE RECUPERACION - NUEVA BASE CANONICA): SMOKE HUMANO APROBADO en produccion (movil: corazones/relaciones, Pasar el rato, encuentro+intervencion, Mensajitos, Vecinos, relaciones + navegacion < >, Nuevo Plan, lugar/dia, Cotilleos, ficha vecino, navegacion general). Recuperacion historica CERRADA. SHA desplegado y aprobado: daf1962. NUEVA BASE ESTABLE CANONICA: rama stable/recuperacion-20260823 (=daf1962) + tag recuperacion-aprobada-20260823. TODA rama/worktree futuro nace de ahi. playtest-01-php74 queda CONGELADO en 29c4732 con su WIP antiguo intacto y sin incorporar (no se movera su ref mientras este checked-out en el arbol compartido sucio; su WIP se preserva para triaje posterior por sus agentes). Preservados sin borrar: rec/css-responsive-stash-restore, rec/aht-iconos-clase, feat/ficha-nav-markup, feat/planes-simultaneos-1N, int/recuperaciones-20260823, stash@{0} (c3ed3c90), backups PRE (predeploy 225318 + prerestore 201923). Regla vigente: deploy solo desde commit de stable/, nunca dirty, con backup PRE y buster nuevo.

---
## NUEVA HERRAMIENTA: AHT DEBUG RESUMEN PARTIDA (2026-08-24)

**Objetivo:** Permitir analizar partidas largas sin necesitar el DEBUG completo de cada NPC. Endpoint de diagnóstico/playtest que devuelve un resumen estructurado de 7 secciones.

**Endpoint:** `GET /api/index.php?action=debug.resumen_partida&partida_id=XXX&limite=20`

**Secciones:**
1. **Header** — día, hora, temporada, vecinos_actuales, llegadas_total, marchas_total
2. **Parejas** — actuales, creadas_total, rupturas, primera_pareja_dia
3. **Romance** — flechazos, primeras_citas, citas_realizadas, citas_rechazadas, planes_autonomos, relaciones_mas_avanzadas, relaciones_estancadas
4. **Vida** — trabajos_actuales, trabajos_perdidos, trabajos_encontrados, acontecimientos_relevantes, estados_emocionales_activos
5. **Jugador** — misiones_completadas, misiones_falladas, peticiones_recibidas, peticiones_atendidas, peticiones_caducadas, dias_consecutivos_sin_mision
6. **Equilibrio** — valor_actual, minimo_historico, umbral_derrota, penalizaciones_acumuladas, causa_exacta_final, dia_estado_critico
7. **Historial** — últimos N eventos (domain_events + acontecimientos_log + event_log, deduplicados y ordenados)

**Archivos:**
- `src/Engine/DebugResumenPartida.php` (nuevo)
- `api/handlers/DevHandler.php` (método `resumenPartida`)
- `tests/DebugResumenPartida_test.php` (58 asserts cubriendo A–F)

**Validación:**
- php -l: OK en 3 ficheros
- Tests unitarios: 58/58 pass
- Test real con partida poblada: OK (devuelve JSON válido con 7 secciones)
- Compatibilidad saves antiguos: OK (valores default null/0/[] sin inventar histórico)

**Limitaciones históricas detectadas:**
- `minimo_historico` = valor actual si ledger vacío (el save no guarda histórico previo al cap)
- `causa_exacta_final` y `dia_estado_critico` solo informados si `llego_a_cero=true` o `dias_en_critico>0`
- `relaciones_estancadas` usa heurística (última cita >14 días + fase no null)
- `trabajos_actuales` cuenta huecos_vida tipo 'trabajo' (puede no reflejar estado real si hay desync)

**Estado:** IMPLEMENTADO EN RAMA AISLADA + TESTS OK / PENDIENTE INTEGRACIÓN

**Commit atómico:** DebugResumenPartida.php + DevHandler.php + DebugResumenPartida_test.php + PLAN_POST_AUDITORIA_PLAYTEST.md
