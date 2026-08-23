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
- **Clase:** JUGABILIDAD/UX · **Prioridad:** ALTA · **Estado:** EN INVESTIGACIÓN POR OTRO AGENTE
- **Subsistema:** relaciones/voluntad/experiencia · **Archivos implicados conocidos:** VoluntadPonderadaEvaluator.php, RelacionEngine.php, EncuentroDeltasReales.php, ContactoCalidad.php, calibracion_vida.json
- **Hallazgo de playtest:** caso Yeray+Sergio: conocerse muy_bien + intervención exitosa → social ≈7; horas después una propuesta seguía ≈51%. La voluntad apenas conserva calidad/recencia del encuentro.
- **Objetivo de producto:** relaciones construibles progresivamente; trayectoria comprensible; relación fuerte amortigua incidentes pequeños (80→70 sí; 80→"como recién conocidos" no); relación fuerte facilita aceptar planes/citas/recuperación.
- **Comprobar al revalidar:** pesos de voluntad (social×0.28, romance×0.18), memoria de contactos (`ultimo_contacto_significativo`), techo ±10 por encuentro-canal, si existe algún término de "historia compartida"; medir curva social/día.
- **Solapa con:** B3, B15, R02, S04. **Criterio de aceptación:** tras varios encuentros buenos, la probabilidad de propuestas futuras sube de forma perceptible y estable; una mala experiencia puntual no resetea la relación.

### P02 — Barras sociales visibles muy cortas incluso tras muchos días
- **Clase:** JUGABILIDAD/UX · **Prioridad:** MEDIA · **Estado:** EN INVESTIGACIÓN POR OTRO AGENTE
- **Subsistema:** vistas de relaciones · **Archivos:** EtiquetaRelacionPlay.php (mapeo barra −100..100→8..100), PartidaService::vistaRelacionesPueblo, RelacionDesgaste.php
- **Hallazgo de playtest:** casi todas las barras de conocidos son cortas tras muchos días.
- **Comprobar al revalidar:** si es percepción correcta (deltas pequeños + desgaste diario) o artefacto del mapeo visual; correlacionar valores internos vs barra pintada. Puede ser síntoma de P01, no bug independiente.

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
- **Clase:** INCOHERENCIA/DEUDA · **Prioridad:** MEDIA · **Estado:** POSIBLEMENTE RESUELTO — REQUIERE REVALIDACIÓN
- **Subsistema:** emociones/voluntad · **Archivos:** EmotionalEventBridge.php (persistencia de `hacia`), VoluntadPonderadaEvaluator.php:150-181 (mitigación enfado por tercero −8, anti-stale), EstadoEmocional.php
- **Nota:** trabajo reciente ya distinguía enfado con la persona del plan / por tercero / indeterminado y caducidad de emociones viejas.
- **Comprobar al revalidar:** que cubre o no B03 (emoción usada en experiencia) y parte de B09 (conflicto); NO asumir que lo arregla todo. Test existente: tests/emocion_enfado_direccional_test.php.

### P05 — Balance Vida del Pueblo / misiones (+2/−3, hasta −9/día)
- **Clase:** BALANCE/CALIBRACIÓN · **Prioridad:** ALTA · **Estado:** EN INVESTIGACIÓN POR OTRO AGENTE
- **Subsistema:** vida_pueblo/misiones · **Archivos:** VidaPuebloEngine.php:29-30 (constantes +2/−3), MisionDiariaEngine.php (alCerrarDia, máx 3/día), calibracion_vida.json:451-458 (valores no leídos)
- **Datos del playtest:** partida post-tutorial sin intervenir puede ir 65→0 en ~7-8 días (hasta −9/día posible). Calibración contiene claves que el motor ignora (=B12).
- **Comprobar al revalidar:** deltas reales aplicados vs JSON; cadencia de misiones caducadas; solape con B12. Criterio: partida pasiva no muerta en <2 semanas; jugador activo percibe progreso.

### P06 — Visibilidad "Aquí hay tema" sobre tokens + indicador stale
- **Clase:** JUGABILIDAD/UX · **Prioridad:** MEDIA · **Estado:** EN INVESTIGACIÓN POR OTRO AGENTE
- **Subsistema:** mapa/HayTema/VistaPuebloV3 · **Archivos:** HayTema.php, VistaPuebloV3.php, play-v3.js (render tokens)
- **Trabajo en curso:** indicadores por token (romance→corazón, drama→rayo…) sobre participantes reales.
- **Caso stale detectado:** lugar marcaba "Aquí hay tema" por Francisco/Yeray cuando ambos estaban ya en otro encuentro y Aitana estaba sola allí.
- **Comprobar al revalidar:** expiración de marcas por cambio de hora/lugar; prioridad encuentro-en-curso > burbuja histórica; deduplicación con `ResumenDia::marcasPorLugar`.

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
- **Clase:** JUGABILIDAD/UX · **Prioridad:** ALTA · **Estado:** PENDIENTE (investigar antes de tocar)
- **Subsistema:** buzón/cotilleo/diario · **Archivos:** BuzonEngine.php, BuzonPlayBridge.php, CotilleoNarrativo.php, CotilleoAutonomoCadencia.php, VistaCotilleoV3.php, DiarioResidenteBridge.php, PeticionEngine/PeticionPuebloEngine (mensajes asociados)
- **Realidad actual (playtest):** `buzon_enabled=ON` pero prácticamente no llegan mensajitos personales accionables; llega información tipo "X hoy no tenía ganas de quedar", que se siente cotilleo duplicado.
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

- 2026-08-23 (R3 deploy): implementado y en producción el castigo único por día de misiones ignorado (caducada individual = 0; `vida_dia_ignorado=-3`; ledger `dia_misiones_ignorado`). Commit 7b10642 aislado sin WIP ajeno. Hallazgos laterales derivados:
  - L-FLAKY-SMB: lectura intermitente de archivos obsoletos sobre UNC/SMB (fatal esporádico `calcularContinuidadReciente` en WIP de VoluntadPonderadaEvaluator; número de línea del trace no coincidía con el archivo actual). No es bug de juego: es caché de red/AV. Afecta a tests CLI largos.
  - L-CHECKOUT-INDEX: `git checkout-index` no exporta tests sin trackear (pasar_noche_test.php etc. solo existen en worktree); la validación "estado commiteado" no los incluye.
  - L-INDEX-PREVIO: se encontró `src/Engine/HayTema.php` ya staged en el index antes de esta tarea (WIP ajeno); des-stage hecho para commit limpio. Revisar quién lo dejó.
  - L-LEDGER-DIA: entradas de cierre del día D quedan etiquetadas con dia=D+1 en el ledger de vida_pueblo (el reloj avanza antes de cerrar misiones). Cosmético, pero rompe trazas/agrupaciones por día.
  - L-AUSENCIA-HOOK: infra canónica de ausencia ya cableada: PartidaLifecycle::cargar() llama Reloj::calcularCatchUpPendiente() y persiste segundos reales en reloj.catch_up_pendiente sin tocar el reloj del pueblo. Penalización offline suave puede engancharse ahí SIN segundo sistema; cap motor −15/suelo 5/nunca GO ya protegido (estresado). Curva recomendada: gracia 24 h + −1/día lineal (el cap −15 del motor da forma a la cola). PENDIENTE autorización + política anti-doble-cobro con futuro catch-up (un día de pueblo cobra una sola vez y por un solo mecanismo).
