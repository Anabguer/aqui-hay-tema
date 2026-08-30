# Mapa Funcional — Aquí Hay Tema

**HEAD verificado:** pendiente de commit
**Fecha auditoría:** 2026-08-30
**Regla:** Solo se documenta lo demostrable por código, tests, commits y configuración.

---

## Leyenda

| Símbolo | Significado |
|---------|-------------|
| 🟢 | LIVE / cerrado E2E |
| 🟡 | Implementado pero no expuesto al jugador / POR VERIFICAR E2E |
| 🟠 | Parcial (funciona, pero incompleto) |
| 🔵 | No desplegado (código existe, no en prod) |
| 🟣 | Desincronizado (docs vs realidad) |
| ⚪ | Apagado por configuración |
| 🟤 | LAB/test only |
| 🔴 | Roto |
| ⚫ | Documentado / no implementado |
| 🗑️ | Obsoleto |

---

## Sistemas

### Encuentros (proponer → aceptar/rechazar → programar → resolver)

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Puede proponer planes (conocerse, quedar, cita, individual). El motor evalúa agenda, voluntad, cooldown, nivel. Resolución automática al avanzar reloj. Resultados: social/romance/conflicto/discovery independientes. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `PropuestaEncuentroEngine.php` + `EncuentroResolver.php` + `EncuentroLifecycle.php` + `EncuentroExperiencia.php`. Tests: `encuentros_test`, `encounter_outcome_facade_test`, `encuentro_resultado_play_test`, `encuentro_proponer_handler_test`, `encuentro_proponer_cooldown_par_test`, `encuentro_cancelar_test`, `encuentro_solape_test`, `encuentro_emocional_asimetrico_test`, ~15 tests más. Deploy confirmado. |

---

### Nuevo Plan (proposición social desde UI)

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Panel organizador funcional: selección de personas, tipo de plan, lugar, hora. Feedback por rechazo/aceptación. Indicador de compatibilidad visible. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `play-v3.js` sección organizar (líneas ~6500-7200). `PropuestaEncuentroEngine::proponer()`. `PropuestaNivel.php` (tipos: conocerse, quedar, primera_cita, cita). Tests: `nuevo_plan_sin_huecos_test`, `org_form_validacion_test`, `org_max_participantes_test`, `org_proponer_feedback_test`. |

---

### Voluntad

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Probabilidad de aceptar/rechazar basada en: score emocional, romance, social, conflicto, compatibilidad. Media geométrica para planes de 2. Rechazos generan cooldown y memoria. Salidas individuales NPC tienen su propio evaluator. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `VoluntadPonderadaEvaluator.php` + `VoluntadSalidaIndividual.php` + `PropuestaCooldown.php` + `RechazoMemoria.php`. Tests: `voluntad_media_geometrica_test`, `a3_voluntad_salida_individual_test`, `decisiones_post_424_test`, `peticion_voluntad_compromiso_test`. |

---

### MENTES (intervención en encuentros en curso)

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Modal real durante encuentros en curso. Celestine elige tema/acción. Feedback efímero en tarjeta plan. Avatares enmarcados canónicamente. Visibilidad sobre velo. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `EncuentroIntervencion.php` + `MentesTemas.php`. Commits: `dcca502` (modal real), `53413a3` (feedback efímero), `fbdb5a6` (avatares), `bf6c292` (visibilidad). Tests: `encuentro_intervencion_test`, `mentes_coherencia_experiencia_test`, `mentes_distribucion_contrafactual_test`, `mentes_iteracion2_test`, `mentes_objetivo_hobby_test`, `mentes_tema_objetivo_test`, `mentes_tono_momento_test`. |

---

### Relaciones (social, romance, conflicto — 3 canales)

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Grafo social con 3 canales independientes por par (direccional). Bandas de romance (sin_interes → enamorado). Fases de relación (estable → crisis → posible_ruptura). Desgaste natural. Veto por parentesco y edad. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `RelacionEngine.php` + `RelacionBandas.php` + `RelacionFase.php` + `RelacionGrafo.php` + `RelacionDesgaste.php` + `RelacionBitacora.php` + `RomanceElegibilidad.php`. Tests: `relaciones_test`, `romper_resetea_romance_mapa_test`, `romance_genero_universal_test`, `edad_residente_test`, `senal_romantica_coherencia_temporal_test`. |

---

### Romance (parejas, flechazo, primera cita)

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 CERRADO E2E |
| **Qué tiene el jugador hoy** | Señal romántica unilateral (flechazo o romance ≥ 8). Primera cita desbloqueable. Formación de pareja con estabilidad. Crisis/ruptura/reconciliación. Tercero romántico (interés en tercero mientras en pareja). Reset de romance al romper (B5). |
| **Qué falta** | Nada funcional crítico. |
| **Nota** | Cierre E2E autónomo cerrado: bypass declaración sin primera_cita (familias_en_play + condición primera_cita + evaluator). Cohortes D5-D60: 0 parejas sin PRIMERA_CITA. |
| **Evidencia** | `SenalRomantica.php` + `ParejaEngine.php` + `TerceroRomantico.php` + `IniciativaRomantica.php` + `AccionRomantica.php` + `AcontecimientoElegibilidad.php` + `RelacionBitacora.php`. Commits: `0a1c589` (reset romance al romper), `b4a7cbd` (cierre E2E). Config: `calibracion_vida.json` (familias_en_play: romance_hito, pareja), `acontecimientos.json` (declaracion: primera_cita). Tests: `fase1_iniciativa_primera_cita_test`, `playtest_romance_v1_test`, `debug_parejas_test`, `romance_cierre_e2e_test` (27/27), `romance_cohorte_postfix_test` (D5-D60, 0 sin PC). |

---

### Emociones / Qué le pasa

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Estados emocionales por residente (feliz, triste, enfadado, etc.). Aplicación por encuentros, rechazos, eventos. Recuperación temporal. Visualización en ficha vecino (pill + foto + modal). Coherencia causal emocional (fix `3b3f228`). |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `EmotionalStateService.php` + `EstadoEmocional.php` + `EmotionalEventBridge.php` + `EmotionalRecovery.php`. Commits: `3b3f228` (coherencia causal), `b6e126d` (ficha-animo fix). Tests: `ficha_animo_modal_test`, `emocional_trazabilidad_test`, `coherencia_causal_encuentro_test`, `encuentro_emocional_asimetrico_test`. |

---

### Memoria / Coherencia causal

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Explicaciones emocionales referencian el encuentro correcto (no cross-contamination). 12 detectores de contradicción narrativa (C1-C12). Historial de cambios por relación. Trazabilidad de origen en mensajes. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `NarrativeCoherenceEngine.php` + `RelacionHistorial.php` + `MemoriaEventos.php`. Commits: `3b3f228` (fix coherencia causal). Tests: `coherencia_causal_encuentro_test`, `fase8_test` (C1-C12), `emocional_trazabilidad_test`. |

---

### Diario

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Presentación narrativa v2 restaurada. Entradas por encounters, hitos, cotilleos. Vista diaria. Bridge narrativo. Hitos de relación. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `DiarioEngine.php` + `DiarioHitoEngine.php` + `DiarioNarrativaBridge.php` + `DiarioVista.php`. Commits: `ac893dd` (restaurar presentacion v2). Tests: `diario_vista_test`, `diario_hito_engine_test`, `diario_narrativa_bridge_test`, `diario_fase2_test`, `diario_cotilleo_visibilidad_test`. |

---

### Cotilleos

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Categorías de cotilleo (romance, drama, pueblo, etc.). Cadencia autónoma. Copys asimétricos. Integración con buzón. Señal romántica publicada. Llegadas generan cotilleo. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `CotilleoCategoria.php` + `CotilleoNarrativo.php` + `CotilleoAutonomoCadencia.php` + `CotilleoPatronCadencia.php`. Tests: `cotilleo_copy_v3_test`, `cotilleo_asimetrico_test`, `cotilleo_patron_cadencia_test`, `cotilleo_autonomo_cadencia_test`, `encuentro_cotilleo_test`. |

---

### Mensajitos (buzón de Celestine)

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Buzón funcional con mensajes de tipos variados (peticiones, respuestas, cotilleo, señales). Lectura y resolución de decisiones. Deduplicación por CanalDeduplicador. Idempotencia de resolución (B4). Generación espontánea con cadencia. Voz Celestine. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `BuzonEngine.php` + `MensajitoAcciones.php` + `CanalDeduplicador.php` + `BuzonPlayBridge.php` + `MensajitoGeneradorEspontaneo.php` + `MensajitosCadenciaEngine.php` + `MensajitoVoz.php`. Commit: `7e0fa2f` (evitar doble resolución B4). Tests: `mensajitos_dedup_test`, `mensajitos_interactivo_recuperado_test`, `mensajitos_integridad_test`, `mensajitos_e2e_integracion_test`, `mensajitos_vivos_test`, ~15 tests más. |

---

### Peticiones

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | NPCs piden cosas a Celestine. Catálogo de plantillas (volver_a_ver, etc.). Selector con snapshot de candidatos. Cadencia automática. Feedback por cumplimiento/caducidad. Petición autonomy por pueblo. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `PeticionEngine.php` + `PeticionPuebloEngine.php` + `PeticionFeedback.php` + `PeticionPlantillas.php`. Tests: `peticiones_pueblo_test`, `peticiones_juego_v1_funcional_test`, `peticiones_autonomas_e2e_test`, `peticion_voluntad_compromiso_test`, `mensajitos_celestine_elige_test`. |

---

### Regalos / Inventario

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Regalos como "detallitos sorpresa" al cumplir misiones. Fuente jugable de regalos. Inventario funcional. Recompensa por regalo. Cooldown. Hints. Persistencia. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `RegaloEngine.php` + `RegaloRecompensaEngine.php` + `RegaloHints.php` + `InventarioEngine.php`. Commit: `8b4039e feat(regalos): detallitos sorpresa — fuente jugable de regalos al cumplir misiones`. Tests: `regalo_api_test`, `regalo_cooldown_test`, `regalo_emocion_relacion_test`, `regalo_f2_*_test` (8+ tests), `regalo_f3_cierre_test`, `regalo_inventario_test`, `regalo_resolucion_test`, `detallito_sorpresa_test`. |

---

### Misiones

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Misiones diarias con plantillas. Generación automática al inicio de día. Presupuesto escalado por población. Tutorial integrado (M1-M3). Cumplimiento registra hits. reconciliarMisionesNormales post-tutorial. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `MisionDiariaEngine.php` + `MisionPlantillas.php` + `MisionPlayBridge.php`. Tests: `misiones_diarias_test`, `misiones_r3_dia_ignorado_test`, `dia2_misiones_test`, `tutorial_misiones_visibles_test`. |

---

### Llegadas

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Llegadas como acontecimiento. Curva de pity. Presentación en buzón. Catch-up para offline. Llegadas tutoriales (poblamiento inicial, no bug). Pool de 200+ personajes canónicos. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `CandidatoLlegadaEngine.php` + `LlegadaPresentacionEngine.php` + `PoolJugableCanon.php`. Tests: `llegada_presentacion_test`, `llegadas_curva_v3_test`, `llegadas_pity_legacy_save_test`, `llegadas_catchup_pity_test`, `post_gate_llegadas_tutorial_test`. |

---

### Marchas

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | NPC manifiesta intención de marcha. Celestine decide si se queda o no. Mensajito asociado. Narrativa de marcha/presentación. Archivado no reaparece en partida. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `MarchaEngine.php` + `MarchaPresentacionEngine.php`. Tests: `marcha_narrativa_test`, `marcha_presentacion_test`. |

---

### Población / Capacidad

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Pool 200+ canónicos. 16 simultáneos (Bloque A). Viviendas automáticas. Capacidad configurable. Población V3. Diversidad de catálogo verificada. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `PoblacionV3.php` + `CapacidadViviendas.php` + `PresenciaEngine.php` + `PoolJugableCanon.php`. Tests: `capacidad_pueblo_16_test`, `viviendas_pool_v3_test`, `viviendas_test`, `poblacion_ritmo_sim_test`, `diversidad_catalogo_test`, `pool_200_canon_test`. |

---

### Tutorial

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Tutorial Primeros Pasos: M1 (presentar pareja), M2 (leer mensajito), M3 (plan individual). Garantía pedagógica determinista (B2 verificado). Tipo vacío manejado. CSS unificado (lavanda). Transición a misiones normales. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `TutorialPrimerosPasos.php` + `TutorialBucle.php` + `TutorialIncorporaciones.php`. Commits: `1c50da0` (tipo vacío), `7bff700` (action guarantee), `a336c96` (CSS unificado). Tests: `tutorial_primeros_pasos_test`, `tutorial_garantia_acciones_test`, `tutorial_m1_propuesta_garantizada_test`, `tutorial_m1_tipo_vacio_test`, `tutorial_transicion_misiones_test`, `tutorial_primer_encuentro_mentes_test`, ~10 tests más. |

---

### Eventos del Pueblo

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Motor de eventos colectivos funcional (bingo como MVP). Planificador, anuncio por mensajito, selección de asistentes (elegibles corregido en B1), resolución por infraestructura de encuentros existente. Próximo evento en UI. Cierre post-evento. Catálogo dual. Fallback asistentes. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `EventosPuebloEngine.php` + `EventosPuebloAnuncioEngine.php` + `EventosPuebloCierreEngine.php` + `EventosPuebloBridge.php`. Commits: `73734f2` (B1 fix elegibles), `1911502`, `a84767b`, `39cd0ec` (quitar aforo UI). Tests: `eventos_pueblo_seleccion_asistentes_test`, `eventos_pueblo_bingo_test`, `eventos_pueblo_anuncio_mensajito_test`, `eventos_pueblo_apuntar_test`, `eventos_pueblo_api_apuntar_test`, `eventos_pueblo_buzon_global_test`, `eventos_pueblo_catalogo_dual_test`, `eventos_pueblo_fallback_asistentes_test`, `eventos_pueblo_pack_inicial_test`, `eventos_pueblo_proximo_ui_test`, `evento_pueblo_flujo_completo_test`. |

---

### Agenda

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Reservas por franja (trabajo, sueño, recurrente, encuentro, autónomo, temporal). Agenda conjunta para citar. Disponibilidad verificada antes de proponer. Templates. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `AgendaEngine.php` + `AgendaConjunta.php` + `AgendaTemplates.php` + `DisponibilidadEngine.php`. Tests: `disponibilidad_test`, `disponibilidad_sueno_planes_test`, `slots_ui_compatibles_test`. |

---

### Autonomía NPC

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Planes autónomos NPC. Iniciativa social (quedadas deliberadas). Salidas individuales. Coincidencias. Acontecimientos diarios (flechazo, declaracion, crisis, ruptura, reconciliación). Motor de vida diaria. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `IniciativaSocial.php` + `AcontecimientoDiario.php` + `MotorVidaDiaria.php` + `AutonomousPlanner.php` + `CoincidenciasEngine.php` + `InteraccionCasual.php`. Tests: `iniciativa_social_test`, `vida_pueblo_autonomia_test`, `vida_pueblo_gameplay_test`, `coincidencias_npc_test`, `coincidencias_interaccion_test`. |

---

### Offline / Catch-up

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 CERRADO E2E |
| **Qué tiene el jugador hoy** | CatchUpEngine ejecuta al reconectar: avanza reloj en chunks que respetan medianoche. Misiones caducan (-2 vida/día). Relaciones decaen. Encuentros programados se resuelven. Batch NPC offline registra actividad plausible (3 eventos/día, 1 salida/día) sin ejecutar motores reales. Ausencia NO destruye la partida (cap -15, suelo 5, sin game over offline). Resumen al regreso vía `CatchUpEngine::resumenRegreso()`. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `CatchUpEngine.php` + `CatchUpPlanner.php` + `MotorVidaDiaria::catchUpBatchDia()` + `RelojOperations.php` (catch_up guards + batch wiring) + `VidaPuebloEngine::aplicarAusencia()`. Config: `calibracion_vida.json` (catch_up: umbral 60s, max 90d, eventos_por_dia 3, salidas_por_dia 1). Tests: `catch_up_offline_test` (26/26), `catch_up_completo_test` (A-O, 43/43), `llegadas_catchup_pity_test`. Feature flag: `offline_events_enabled` (ON en juego_v1.json). |

---

### Discovery (descubrimiento de rasgos)

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Descubrimiento progresivo de rasgos ocultos de NPC. Proyección visible (qué puede ver el jugador). Reveal (cuándo se descubre). Visibilidad política. Regalos dan discovery. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `DiscoveryEngine.php` + `DiscoveryProjection.php` + `DiscoveryReveal.php` + `DiscoveryVisibilityPolicy.php` + `DiscoveryVisibilityResolver.php`. Tests: `discovery_test`, `discovery_projection_test`, `discovery_policy_test`, `discovery_visibility_resolver_test`, `regalo_f2_discovery_test`. |

---

### Hobbies / Rasgos

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Hobbies accionables (plan affinindad). Hobby-emoción match. Rasgos ocultos con preferencias (pos/neg/dealbreakers). Copy de hobby animo. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `HobbyAccionable.php` + `HobbyAnimoCopy.php` + `HobbyEmocionDev.php` + `PlanAfinidad.php` + `SeleccionSocialPeso.php`. Tests: `hobby_emocion_test`, `regalo_f2_hints_test`. |

---

### Ficha (vecino)

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Ficha de vecino con retrato, nombre, pill emocional, modal animo. VistaRelationPlay para relaciones. Etiquetas de relación (social/romance). Ocultación de valores numéricos al jugador. Ficha_OCULTO vs ficha legible. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `FichaPlayVista.php` + `EtiquetaFicha.php` + `EtiquetaRelacionPlay.php` + `AprecioCelesteVista.php` + `RelacionVistaJugador.php`. Commits: `b6e126d` (ficha-animo fix), `50fdf4e` (pulir ficha). Tests: `ficha_animo_modal_test`, `debug_blackbox_test` (Gate C). |

---

### Mapa / Edificios

| Campo | Valor |
|-------|-------|
| **Estado** | 🟢 |
| **Qué tiene el jugador hoy** | Mapa del pueblo con edificios. VistaPuebloV3. Bottom nav funcional. Iniciales/candados. Layout desktop y mobile. |
| **Qué falta** | Nada funcional crítico. |
| **Evidencia** | `VistaPuebloV3.php` + `LugaresCanonicos.php` + `LugarAtributos.php`. Commits: `2d56182`, `d46d8c5`, `d78c0c4`, `e6a619d`, `8e99f35`, `52544e4` (desktop fixes), `dd2187f` (inicio-movil fix). Tests: `vista_pueblo_v3_test`, `mapa_encuentros_test`, `horario_lugar_ui_test`, `lugares_preferentes_backfill_test`. |

---

### Audio / PWA

| Campo | Valor |
|-------|-------|
| **Estado** | 🟡 |
| **Qué tiene el jugador hoy** | PWA con manifest y service worker. Title corregido (mojibake fix). Audio de romance existe en JS (`play-v3-audio.js`). Efectos de sonido configurados. |
| **Qué falta** | Integración completa de audio en todos los flujos. PWA offline completo (sin service worker funcional verificado). |
| **Evidencia** | `play-v3-audio.js` + PWA manifest. Commits: `01a5eb9` (title mojibake fix). Tests: `pwa_standalone_test`. |

---

## COLA REAL DE CIERRE

Solo sistemas que NO estén 🟢. Ordenados por dependencia técnica.

| # | Sistema | Estado | Qué falta | Dependencia |
|---|---------|--------|-----------|-------------|
| 1 | Romance autónomo | 🟡 POR VERIFICAR E2E | Cierre E2E autónomo: funnel social→romántico, primeras citas, declaraciones, parejas, D10-D60, productores de hitos, configuración, cuellos de botella | Independiente |
| 2 | Offline / Catch-up | 🟢 | Catch-up completo: reloj avanzado, misiones caducan, batch NPC offline, resumen regreso, sin destrucción. | `catch_up_offline_test`, `catch_up_completo_test` |
| 3 | Audio / PWA | 🟡 | Integración audio en flujos + PWA offline funcional. | Ninguna (independiente) |

---

## WIP SOCIAL PRESERVADO — PENDIENTE DE RECONCILIACIÓN

Commits en `work/aht-functional-queue` que NO deben integrarse todavía.

| Commit | Propósito | Archivos | En origin/deploy/integrated | Relación con sistemas |
|--------|-----------|----------|----------------------------|----------------------|
| `10a0745` | SocialOutcome engine + tests — resultado social atómico | `src/Engine/SocialOutcome.php`, `tests/social_outcome_coherencia_test.php`, `src/Engine/EmotionalEventBridge.php`, `src/Engine/DiarioHitoEngine.php`, `src/Engine/EncuentroResolver.php`, `tests/p4_baseline_20seeds.php`, `tests/p4_control_activo.php`, `tests/p4_red_social_detalle.php` | NO | RelacionEngine / Emociones / Diario / Encuentros |
| `d689fe2` | Memoria social — modContinuidadReciente influye en predisposición | `src/Engine/Voluntad/VoluntadPonderadaEvaluator.php`, `tests/p2_baseline_memoria_social.php`, `tests/p2_debug_filter.php`, `tests/p2_focus_characters.php`, `tests/p2_playtest_10seeds.php`, `tests/p2_recencia_direccionalidad.php`, `tests/p2_tests_continuidad_reciente.php` | NO | Voluntad / Autonomía NPC / Relaciones |
| `e6e2955` | Resultado social atómico — SocialOutcome como base para densidad | `src/Engine/SocialOutcome.php`, `src/Engine/Voluntad/VoluntadPonderadaEvaluator.php` | NO | SocialOutcome fundacional para P1/P2/P4 |

**Regla:** NO borrar. NO integrar. NO desplegar. NO rebasear. NO modificar. Pendiente de decisión sobre reconciliación.

---

## COMMITS DE TEST — SEPARADOS

| Commit | Contenido | En origin/deploy/integrated | Acción |
|--------|-----------|----------------------------|--------|
| `827e881` | Test fix B3: limpiar cooldown stale en control case | NO | Conservar por ahora; verificar si sigue necesario sobre HEAD canónico |
| `2b63438` | Cache buster post B3 test fix | NO | NO integrar — cambio exclusivamente de test no requiere cache-buster |

---

## CERRADOS — NO REABRIR SIN EVIDENCIA

Sistemas completamente cerrados que NO deben ser re-diagnosticados como pendientes por futuros agentes.

| Sistema | Estado | Razón de cierre | Última evidencia |
|---------|--------|-----------------|------------------|
| Encuentros | 🟢 | E2E funcional: proponer → aceptar/rechazar → programar → resolver | ~15 tests pasan, deploy confirmado |
| Nuevo Plan | 🟢 | Panel organizador funcional con feedback | Tests `org_*`, deploy confirmado |
| Voluntad | 🟢 | Media geométrica + salidas individuales + cooldown + memoria | Tests `voluntad_*`, `decisiones_post_424` |
| MENTES | 🟢 | Modal real + feedback efímero + avatares + visibilidad | 7 tests, commits `dcca502`→`bf6c292` |
| Relaciones | 🟢 | 3 canales independientes + grafo + fases + desgaste | Tests `relaciones_test`, `romper_*`, `edad_*` |
| Emociones | 🟢 | Estados + aplicación + recuperación + ficha visual | Commits `3b3f228`, `b6e126d`, tests `ficha_*` |
| Memoria/Coherencia | 🟢 | Coherencia causal + 12 detectores + trazabilidad | Tests `coherencia_causal_*`, `fase8_test` |
| Diario | 🟢 | Presentación v2 + hitos + narrativa | Commit `ac893dd`, tests `diario_*` |
| Cotilleos | 🟢 | Categorías + cadencia + copys + integración buzón | Tests `cotilleo_*` |
| Mensajitos | 🟢 | Buzón + deduplicación + idempotencia + generación espontánea | 15+ tests, commit `7e0fa2f` |
| Peticiones | 🟢 | Plantillas + selector + cadencia + feedback + autonomy | Tests `peticiones_*`, `mensajitos_celestine_elige_*` |
| Regalos | 🟢 | Detallitos sorpresa por misiones + inventario + recompensa | Commit `8b4039e`, 10+ tests |
| Misiones | 🟢 | Diarias + plantillas + generación + tutorial integrado | Tests `misiones_*`, `tutorial_*` |
| Llegadas | 🟢 | Curva pity + presentación buzón + catch-up + pool 200+ | Tests `llegadas_*` |
| Marchas | 🟢 | Intención + decisión Celestine + mensajito + archivado | Tests `marcha_*` |
| Población | 🟢 | 200+ pool + 16 simultáneos + viviendas + diversidad | Tests `capacidad_*`, `viviendas_*`, `pool_*` |
| Tutorial | 🟢 | Primeros Pasos M1-M3 + garantía pedagógica + CSS unificado | 10+ tests, commits `1c50da0`, `7bff700`, `a336c96` |
| Eventos Pueblo | 🟢 | Motor colectivo + planificador + anuncio + selección + cierre | 10+ tests, commits `73734f2` et al. |
| Agenda | 🟢 | Reservas + conjunta + disponibilidad + templates | Tests `disponibilidad_*`, `slots_*` |
| Autonomía NPC | 🟢 | Planes + iniciativa social + salidas + coincidencias + vida | Tests `iniciativa_*`, `vida_pueblo_*`, `coincidencias_*` |
| Discovery | 🟢 | Descubrimiento progresivo + proyección + reveal + visibilidad | Tests `discovery_*` |
| Hobbies | 🟢 | Accionables + emotioón match + rasgos ocultos | Tests `hobby_*` |
| Ficha | 🟢 | Vecino + retrato + pill emocional + modal + relaciones | Commits `b6e126d`, `50fdf4e`, tests `ficha_*` |
| Mapa | 🟢 | Edificios + bottom nav + desktop + mobile | Commits `2d56182` et al., tests `vista_pueblo_*` |
| B5 Breakup Romance | 🟢 | Reset romance direccional al romper = sin contradicción mapa | Commit `0a1c589`, test `romper_resetea_romance_mapa_test` |
| B3 Voluntad | 🟢 | Rechazo genuino no se volteó por obligación + tirada | Test `propuesta_resolucion_obligacion_rechazo_test` |
| B4 Mensajitos | 🟢 | Doble resolución bloqueada (idempotencia) | Test `mensajitos_interactivo_recuperado_test` |
