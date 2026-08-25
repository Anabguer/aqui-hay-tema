# Plan Maestro de Implementación — Aquí Hay Tema

**Versión:** 2026-08-24 (§19 pendientes/decisiones · §21 consolidación de diseño · §22 Mensajitos 2.0 · §23 Espacio de Celestine — DISEÑO APROBADO, pendiente arquitectura/implementación · §24 llegadas jugables, rituales/calendario y mantra de producto · §25 MI RINCÓN DE CELESTINE — nombre definitivo del espacio, auditoría UI/motores, alcance por fases; móvil NPC APARCADO en §21.3/§22.7; sin dependencia de vínculo Celestine, F12, economía o bypass del motor relacional)

El **~70 %** no infla el playtest: `play.php` sigue con deltas placeholder y el diario de vida está apagado en play. Lo nuevo es desconocidos 0+flag, discovery, desgaste, voluntad, agenda/aforo y laboratorio 3/6/16/32.  
**Punto de partida:** ~8 % (Turno 1) → **~28 %** tras Bloques 0–6 ejecutados en Turno 2

---

## 1. Principios arquitectónicos (revisión ChatGPT)

### Encuentro / Plan = unidad principal
- **`EncuentroEngine`** es la abstracción central; `CitaEngine` es wrapper retrocompatible (`tipo: romantico`).
- Un encuentro puede producir: delta social, delta romance, conflicto, descubrimiento, ninguno, o varios a la vez.
- **`EncuentroResolver`** devuelve canales independientes (`delta_social`, `delta_romance`).

### Intervenciones Celestine vs autonomía NPC
- **`intervenciones_organizadas_max_dia`**: configurable, **null** = sin límite activo; `valor_dev` solo placeholder.
- **NO canonizar «2/día»**. NPC autónomo no consume ese presupuesto.

### Buzón / Diario
- Arquitectura con límites configurables (`max_dia: null`); **cifras 5–10 / 0–5 NO cerradas**.
- Trazabilidad de origen en cada mensaje/entrada.

### RNG centralizado
- **`RngService`**: seed + state en partida; reproducible QA.

### Compatibilidad, química y relación (tres cosas distintas)
- **Identidad visual (P001…P100)** ≠ **residente de una partida**. Ver §16.
- **Compatibilidad** direccional A→B / B→A: encaje de características/preferencias. Estable. No sube con citas.
- **Química**: facilidad inexplicable para conectar (amistad o romance). Persistida, seed, no rerolea.
- **Relación**: lo construido en partida (canales social / romance / conflicto).
- V1 romance: **todos con todos** si edad compatible. **No** hay filtro gay/hetero/bi.
- **Parentesco cercano = veto romántico duro.** La química no lo atraviesa. Tío/primo: BLOQUEADO_DECISION.

### Agenda genérica
- Reservas: trabajo, sueño, recurrente, encuentro, autónomo, temporal.
- La agenda solo sabe ocupación + origen; semántica la define el motor creador.

### Partidas
- **`partida.nueva`**: nuevo `partida_id`.
- **`partida.reiniciar`**: conserva `partida_id`, resetea estado.
- **BLOQUEADO_DECISION producto:** política de IDs en producción multi-dispositivo.

---

## 2. Estado actual del código (post Turno 2)

| Sistema | Archivo(s) | Estado |
|---------|------------|--------|
| Schema v2 + migración | `SchemaMigrator.php` | Funciona |
| RNG | `RngService.php` | Funciona + tests |
| Logging | `GameLogger.php` | Funciona |
| Encuentros | `EncuentroEngine.php`, `EncuentroLifecycle.php`, `EncuentroResolver.php` | Funciona |
| Compatibilidad | `CompatibilityEvaluator` + `CompatibilidadCalculator` + `CompatibilidadOculta` | Encaje A→B/B→A auditable; pesos **provisionales** |
| Química | `QuimicaEngine.php` | Persistida; V1 simétrica; almacén dual |
| Generador residente | `GeneradorResidente.php`, `PerfilPartida.php` | Seed; no reescribe ficha Pxxx |
| Simulador / laboratorio | `SimuladorPueblos.php` | Distribuciones; no escribe partidas |
| Agenda genérica | `AgendaEngine.php` | Funciona |
| Mapa/presencia | `PresenciaEngine.php` | Técnico placeholder |
| Buzón/Diario | `BuzonEngine.php`, `DiarioEngine.php` | Estructura vacía |
| NPC autónomo | `AutonomousPlanner.php` | Arquitectura + dev |
| Economía | `EconomyLedger.php` | Ledger sin cifras |
| UI bucle | `play.php`, `play.js` | Bucle Neni end-to-end (**sin cambios este turno**) |
| Propuesta encuentro | `PropuestaEncuentroEngine.php` | Dominio + API; play sigue en `programar` |
| Peticiones | `PeticionEngine.php` | Estructura + plazo; sin catálogo |
| Catch-up plan | `CatchUpPlanner.php` | Prioridades; no ejecuta eventos |
| Dev gate | `dev_gate.php`, `dev.local.php` | Funciona |
| Tests | `tests/*.php`, `run_all.php` | ALL PASS |

---

## 3. Bloques de implementación

### Bloque 0 — Fundamentos ✅ EJECUTADO
- **+%:** ~2 → ~10 %
- SchemaMigrator, RngService, GameLogger, dev gate, tests modulares

### Bloque 1 — Bucle encuentro jugable ✅ EJECUTADO
- **+%:** ~5 → ~15 %
- EncuentroEngine, lifecycle, resolver placeholder, play.php bucle completo

### Bloque 2 — Mapa técnico ✅ EJECUTADO
- **+%:** ~4 → ~19 %
- PresenciaEngine, panel mapa en play.php, iniciales/candados

### Bloque 3 — Buzón/Diario estructura ✅ EJECUTADO
- **+%:** ~3 → ~22 %
- API listar/crear_dev, trazabilidad origen, UI vacía

### Bloque 4 — Compatibilidad contrato ✅ EJECUTADO
- **+%:** ~3 → ~25 %
- Interface + PlaceholderEvaluator, harness en encuentros_test

### Bloque 5 — NPC autonomía arquitectura ✅ EJECUTADO
- **+%:** ~2 → ~27 %
- AutonomousPlanner + log candidatos/elegido

### Bloque 6 — Economía arquitectura ✅ EJECUTADO
- **+%:** ~1 → ~28 %
- EconomyLedger dinero/fama separados

### Bloque 7 — Catch-up offline eventos ⏸ NO EJECUTADO
- Requiere diseño eventos (B)

### Bloque 8 — Población B/C ⏸ BLOQUEADO
- BLOQUEADO_DECISION: residentes día 1

### Bloque 9 — Contenido producción ⏸ NO EJECUTADO
- Catálogos narrativos, 15 fichas, micros

---

## 4. Clasificación A / B / C

### A — Carlos solo
Bloques 0–6 (hecho), tests, logging, migraciones, UI técnica

### B — Neni + ChatGPT
- B/C día 1, I10 tutorial
- Cifras intervenciones/día, buzón/diario cupos
- Fórmulas compatibilidad, micro-sim
- Catálogos narrativos, economía balanceada
- Aforos mapa

### C — Arquitectura sí, contenido no
- Generadores buzón/diario
- Resultados encuentro narrativos
- NPC autónomo rico
- Catch-up eventos

---

## 5. Estrategia de tests

```
tests/run_all.php       # ejecutor
tests/smoke.php         # integración
tests/rng_test.php
tests/schema_test.php
tests/encuentros_test.php
```

**Invariantes cubiertos:**
- Doble reserva
- Transiciones encuentro válidas
- Social ≠ romance independientes
- RNG reproducible save/load
- Schema migration v1→v2
- Encuentro resuelto al avanzar reloj

**Pendientes:** veto romántico no bloquea amistad (cuando exista HardLayerEvaluator), catch-up, NPC autónomo integrado.

---

## 6. Modo dev (roadmap)

| Herramienta | Estado |
|-------------|--------|
| Avanzar reloj | ✅ |
| Placeholder residente | ✅ |
| Programar encuentro | ✅ |
| Inspeccionar JSON | ✅ |
| Forzar mensaje buzón | ✅ API buzon.crear_dev |
| Plan NPC | ✅ npc.planificar_dev |
| Economía dev | ✅ economia.registrar_dev |
| Reset parcial | Pendiente |
| Reproducir seed | ✅ via partida seed + rng state |

---

## 7. Logging

Tipos: `encuentro_programado`, `agenda_rechazo`, `encuentro_terminado`, `encuentro_resuelto`, `relacion_delta_social`, `relacion_delta_romance`, `npc_autonomo_plan`

Almacenamiento: `logs/partida_*.jsonl` + `event_log[]` en partida (últimas 200).

---

## 8. Estimación % honesta

| Hito | % |
|------|---|
| Turno 1 | ~8 % |
| Tras Bloques 0–6 (Turno 2) | ~28 % |
| Playtest 01 (bucle móvil jugable, placeholder) | **~55 %** |
| Tras motor de vida/relaciones (infra dominio, sin UI) | ~56 % |
| Tras generador + compatibilidad/química (calibración, sin UI) | ~57 % |
| Tras vida relacional / pareja / crisis / memoria (infra, sin UI) | **~58 %** |
| Con contenido mínimo auditado + pueblo vivo en play | ~45–55 % ya cubierto en playtest; el salto jugable real vendrá cuando el flujo proponer→decidir esté en UI |
| V0 completo REGLAS_NO_NEGOCIABLES | ~70 %+ |

El **~58 %** no infla el playtest: `play.php` sigue programando encuentros en directo y los deltas de relación siguen en +1 placeholder. Pareja/crisis/ruptura existen como **hitos de dominio**, no como pueblo autónomo en UI.

---

## 9. BLOQUEADO_DECISION

| Cuestión | Afecta |
|----------|--------|
| B/C día 1 | Población inicial |
| I10 tutorial | Onboarding |
| Cifra intervenciones/día | Balance Celestine |
| Cupos buzón/diario | Ruido vs silencio |
| Reiniciar vs nueva en UX multi-dispositivo | Persistencia |
| Fórmula / pesos de **aceptación por voluntad** | Propuesta de encuentro |
| Cantidades y probabilidades de autonomía NPC | Pueblo vivo |
| Ritmo cualitativo ~3 días / 16 residentes → cifras | Catch-up, peticiones, novedades |
| Probabilidades y umbral de acontecimiento importante en catch-up | Catch-up |
| Fórmula de compatibilidad y deltas reales (sustituir +1 placeholder) | Relaciones |
| Umbrales estabilidad → tensión → crisis → posible ruptura | Fases de relación |
| Nombre UI y coste del escáner / herramienta de compatibilidad | Progresión futura |
| Copy narrativo de rechazos (indisponibilidad vs voluntad) | Personaje / buzón |
| Catálogo narrativo de peticiones y consecuencia si se ignoran | Peticiones |
| Evaluación real de consecuencias (`consequences_enabled`) | Consecuencias |
| Fórmulas de cambio emocional desde eventos | Emociones |
| Pesos definitivos de compatibilidad / química / cita | Calibración JSON |
| Cifras de edad (preferencia ±10, límite duro 25) | Solo hipótesis de simulador |
| Química simétrica vs direccional (V1 simétrica, almacén dual) | Modelo futuro |
| Penalizar plan/lugar que no gusta | PlanAfinidad |
| Ventanas de cooldown por familia de evento | MemoriaEventos |
| Qué se revela el día 1 (1 hobby / 1 rasgo) | Discovery |
| Fórmula de voluntad NPC (UI no decide) | Propuesta |
| Umbrales estabilidad → tensión → crisis → posible ruptura | RelacionFase de canal (NO es crisis de pareja) |
| Días/deltas de desgaste social y de pareja | RelacionDesgaste |
| Presupuesto y pesos del motor diario | AcontecimientoDiario |
| % flechazo / deltas romance de acciones | AccionRomantica |
| Cortes UI de bandas sociales (amigo, rival…) | social.bandas |
| Tío/primo como veto | parentesco.tio_primo_veto |
| Probabilidad de seguir un consejo | ConsejoEngine |
| Base de estabilidad al reconciliar | pareja.base_reconciliacion |
| Pesos de experiencia de cita / carga de azar | EncuentroExperiencia |

---

## 10. Siguiente bloque recomendado

**Esperar revisión Neni + ChatGPT.** No seguir con otro bloque de motorización hasta cerrar pesos/umbrales del laboratorio.

Playtest sigue en `encuentro.programar`. Motor diario `activo_en_play=false`.

---

## 11. RECORTE_V0

Ninguno registrado en este turno. Playtest 01 permanece congelado en UI.

---

## 12. Confirmación restricciones

Sin retratos, sin identidades tocadas, sin fórmulas definitivas, sin economía balanceada, sin narrativa masiva, sin rediseño de `play.php` / hilo visual.

---

## 13. Decisiones de diseño confirmadas (post playtest móvil)

El bucle técnico funciona. El mundo **no depende solo del jugador**. Objetivo de sensación: **pueblo vivo**, sistema **lento y estable** (no telenovela / no “Sálvame Deluxe”).

### Mundo autónomo
Los residentes deben poder coincidir, interactuar, conocerse, mejorar o empeorar relaciones, amistad, interés romántico, iniciar/mantener relaciones, crisis y eventual ruptura. Referencia **cualitativa** (no cifras): tras ~3 días sin entrar, con ~16 residentes, puede haber novedades pequeñas, peticiones, movimientos de relación y *ocasionalmente* un acontecimiento importante.

### Agencia
**El jugador propone. El residente decide.** Puede rechazar. Flujo: proponer → evaluar A → evaluar B → aceptar/rechazar → solo entonces programar. `encuentro.programar` (playtest) se conserva como atajo legacy.

### Rechazos
Distinción técnica: **indisponibilidad** vs **voluntad**. `copy_id` preparado, vacío. Pesos no fijados.

### Relaciones
Canales independientes: social, romance/interés, conflicto/roce. La velocidad **no** es igual para todas las parejas (compatibilidad oculta). Placeholder +1/0 no es la fórmula final.

### Compatibilidad oculta
El jugador no ve porcentajes. Infere por comportamiento. Futuro: herramienta/escáner (nombre y coste pendientes). No mostrar en play.

### Estabilidad
Una pareja consolidada no rompe por una mala interacción aislada. Arquitectura interna: `estable` → `tension` → `crisis` → `posible_ruptura` (nombres **no** son UI final). Umbrales no fijados.

### Peticiones
Residente → necesidad/deseo → petición → buzón → el jugador actúa o ignora → resultado. Plazo y evolución si se ignora (evolución aún sin reglas).

### Catch-up
Entrar y que no haya ocurrido nada es fallo de diseño. Tampoco resolver semanas como telenovela. Plan de prioridades: pequeñas novedades, cambios progresivos, peticiones, relaciones, acontecimiento importante ocasional. Sin cantidades.

### Primer encuentro real (vertical slice dominio)
PROPONER → reacción A/B → aceptación/rechazo → encuentro → resultado → cambio real de relación (hoy: placeholder de canales) → emocional si procede (flag apagado) → consecuencia visible (DTO existente). Sin animaciones ni narrativa final.

---

## 14. Auditoría vs implementación (motor de vida)

| Sistema | YA EXISTE | EXISTE COMO SCAFFOLD | FALTA | BLOQUEADO_DECISION |
|---------|-----------|----------------------|-------|--------------------|
| Propuesta de encuentro | Validación de contexto, lugares, agenda | `PropuestaEncuentroEngine` + API `encuentro.proponer` | Cablear UI play (no este turno) | — |
| Aceptación/rechazo | Rechazo agenda `AGENDA_SLOT_OCUPADO` en `programar` | Clases `indisponibilidad` / `voluntad`; `VoluntadPendienteEvaluator`; `copy_id` | Fórmula; copy de personaje | Pesos voluntad; copy |
| Relaciones | Canales social + romance; deltas placeholder +1/0 | Canal `relaciones_conflicto`; `fase` / `estabilidad_acumulada` aditivos | Deltas reales según compatibilidad | Fórmula; umbrales |
| Compatibilidad | Contrato `CompatibilityEvaluator` + `CompatibilidadCalculator` A→B/B→A | Pesos en `calibracion_compatibilidad.json` (`_provisional`); desglose auditable persistido | Fórmula/pesos canónicos; escáner | Pesos; nombre/coste/precisión escáner |
| Identidad vs residente | Pxxx = visual; `perfil_partida` generado | Preferencias +/−; 3 hobbies; 3 rasgos | Anti-clon agresivo; reveal día 1 | Qué se ve el día 1; anti-clon |
| Química | `QuimicaEngine` al coexistir; seed; no rerolea | Almacén A→B y B→A; V1 simétrica | Decisión irreversible simétrica vs direccional | Modelo a largo plazo |
| Resolución encuentro | Snapshot de factores + `por_participante` | Pesos `null`; satisfacción `null` | Fórmula; copy por participante | Fórmula; copy |
| Plan/lugar ↔ hobbies | `PlanAfinidad` detecta hobby relacionado | `aporte=null` | Penalizar plan ajeno | Aporte / penalización |
| Memoria/cooldowns | `MemoriaEventos` registra familia/día/participantes | `cooldowns.por_familia={}` | Ventanas por familia | Cifras de ventana |
| Estabilidad/crisis | — | `RelacionFase` transiciones válidas, **sin auto-paso** | Umbrales y proceso de ruptura | Umbrales; nombres UI |
| Autonomía | `AutonomousPlanner` visita lugares al azar | Coincidencias técnicas ≠ interacción | Interacción autónoma, amistad/romance NPC | Ritmo, cantidades |
| Peticiones | Buzón tipo `peticion` vacío | `PeticionEngine` + API + plazo/caducar | Generación autónoma; catálogo | Catálogo; consecuencia ignorar; ritmo |
| Catch-up | `Reloj` cuenta segundos; `eventos_pendientes=[]` | `CatchUpPlanner` prioridades enganchadas; **no ejecuta** | Simulación controlada | Cantidades, probs, umbral importante |
| Consecuencias | DTO play (social/romance/conflicto/lineas); triggers `enabled=false` | Contratos para propuesta/petición | Evaluación real | Flag + reglas |
| Emociones | `EmotionalStateService` runtime; bridge con flag apagado | Enchufe a propuesta/petición | Cambio emocional de encuentro | Fórmulas |

**Playtest intacto:** `encuentro.programar` → queda programado. No se ha rediseñado `play.php`.

**Saves:** campos aditivos vía `SchemaFields::ensure`, schema **sigue v2**.

---

## 15. Arquitectura mínima (implementada)

```
Jugador ──proponer──► PropuestaEncuentroEngine
                         │  agenda → rechazo indisponibilidad
                         │  VoluntadEvaluator → pendiente | acepta | rechaza
                         ▼
              ambos aceptan ──confirmarSiProcede──► EncuentroEngine::programar
                         │
                         ▼
              EncuentroLifecycle → EncuentroResolver (placeholder)
                         │
                         ▼
              RelacionEngine (social / romance / conflicto + fase)
              CompatibilidadOculta (nunca en DTO play)
              ConsecuenciaReactionSubscriber / EmotionalEventBridge (flags off)

Offline: Reloj::calcularCatchUpPendiente → CatchUpPlanner.plan (no eventos)
Peticiones: PeticionEngine → BuzonEngine → caducar en RelojOperations

Residente: Pxxx (visual) → GeneradorResidente (seed) → perfil_partida
           QuimicaEngine al coexistir; CompatibilidadOculta direccional A→B/B→A
           EncuentroPonderacion (snapshot, sin fórmula) + MemoriaEventos
```

---

## 16. Identidad visual ≠ residente de partida (2026-08-18)

Sustituye la suposición de que P001…P100 arrastran personalidad/hobbies fijos a todas las partidas.

### Qué es fijo (identidad visual)
Sexo/apariencia, rango de edad, pelo, barba/perilla/bigote, gafas, tatuajes, estilo/ropa, retratos y expresiones. **No cambia** entre partidas. No se reescriben fichas aprobadas.

### Qué se genera al entrar en UNA partida
Sobre esa cara se crea el **residente de esa partida** (reproducible por seed, estable mientras dure):

- 3 hobbies
- 3 rasgos de personalidad
- 2 preferencias + y 2 rechazos de personalidad
- 2 preferencias + y 2 rechazos visuales
- indicadores visuales **extraídos** de la ficha (no inventados)
- edad tomada de la identidad visual (política de romance)

En otra partida, la misma cara puede salir con otro interior.

### Romance V1
Cualquier adulto puede tener interés romántico por cualquier otro **compatible por edad**. No hay orientación como filtro. Edad: rango preferente y límite duro **configurables**; hipótesis de simulador ±10 / duro 25; **no canon**.

### Reglas de encaje (cerradas de producto, pesos no)
- Compartir hobby **suma**; no compartir **no resta**.
- Preferencia positiva que B no tiene = 0 (no penaliza).
- Rechazo explícito que B sí tiene = penaliza.
- Compatibilidad **direccional** A→B y B→A. Es potencial, no relación.
- Química ≠ atracción. Puede acabar en amistad. Persistida al coexistir; no se recalcula al cargar.
- V1 química **simétrica en valor**; el almacén guarda ambos sentidos por si más adelante es direccional.
- El pico y pala del jugador actúa sobre **relación/vínculo**, no reescribe compatibilidad.

### Discovery
El jugador no ve la ficha interna completa. Compatibilidad y química permanecen ocultas. **Reveal inicial cerrado:** 1 hobby + 1 rasgo (`hobby:{id}`, `rasgo:{id}`); el resto `???`. Descubrimiento contextual por evento. Conocimiento NPC→NPC en `conocimiento_npc`.

### Laboratorio
`SimuladorPueblos` (p. ej. 1000 pueblos × 16). Pesos en un solo JSON marcado `_provisional`. No anti-clon agresivo: primero medir.

### Propuesta / NPC
`encuentro.propuesta.decidir` es **DEV**. La UI pública no decide por el NPC.

---

## 17. Vida relacional, pareja y memoria (2026-08-18)

### Canales (separados, direccionales)
- Social A→B y B→A (pueden ser amigo vs mejor amigo).
- Romance A→B y B→A (independientes, fluctúan).
- Conflicto propio.
- Química y compatibilidad **no** son relación.
- **Desconocido** = fila visible con social 0 y `conocidos=false`. Tras el primer contacto `conocidos=true` aunque el valor siga en 0. No es lo mismo que 0 después de conocerse.

### Pareja
Nunca `romance > X → pareja`. Hito explícito (declaración u otro) + respuesta de ambos → `estado_pareja=pareja`.
Estabilidad de pareja **compartida** y distinta del romance. No hay barra de “amor compartido”.

### Crisis / ruptura
Nunca por umbral. Acontecimiento explícito. Tras romper: se conservan social, romance, conflicto e historial. Estabilidad operativa se apaga; queda `memoria`. Reconciliación ≠ pareja nueva.

### Motor diario
Presupuesto escala con habitantes (`sqrt`). Huecos 09:00–22:00: se decide CUÁNTOS al empezar el día, no quién ni qué. `activo_en_play=false`. Lab: `lab_vida_activa` + `SimuladorPuebloVivo`.

### Emociones V1
neutro / alegre / triste / enfadado. Un solo estado + caducidad. Modificadores temporales en calibración. Un despido puede poner triste; **no** resta romance.

### Parentesco
Veto romántico duro. Química no lo atraviesa.

### Buzón
Clasificación importante / oportunidad / petición / cotilleo. Estado **en_espera**. No congela el mundo. Escala con población.

---

## 18. Decisiones cerradas post-424fe940 (2026-08-19)

**+% estimado dominio:** ~58 % → **~70 %** (sin inflar play: `play.php` intacto, diario inactivo en play, deltas de encuentro placeholder).

### Implementado
- Desconocidos visibles 0 + flag; bandas conceptuales configurables (pareja ≠ banda).
- Reveal inicial 1 hobby + 1 rasgo; discovery contextual; conocimiento NPC→NPC (sin rumores).
- Compatibilidad no cambia por experiencias (sin tocar `CompatibilidadCalculator` en encuentros).
- Desgaste social hacia 0, nunca cruza 0; fórmula central; calidad de contacto leve/normal/significativo.
- Emociones V1 con caducidad y modificadores; voluntad ponderada (nunca 100 %); rechazos + cooldown horas.
- Huecos de vida 09–22; presupuesto no lineal; agenda conjunta; duración; aforo; coincidencia ≠ interacción; casual leve.
- Lugar autónomo según conocimiento; presentar/proponer/organizar (`PropuestaNivel`); flechazo raro; terceros con freno.
- Escáner de compatibilidad **deprecated** (no desbloqueable).
- Laboratorio `SimuladorPuebloVivo` 3/6/16/32 × 30/100. Cifras `_provisional`.

### Solo preparado / no UI
- Copy final de rechazos (ids banales).
- UI de bandas/relaciones.
- `play.php` / hilo visual.
- Conversaciones grupales.
- Sistema grande de infidelidad.

### BLOQUEADO_DECISION que sigue
- Veto tío/primo.
- Política de IDs multi-dispositivo.
- Química simétrica vs direccional a largo plazo.
- Cifras canónicas (todo el JSON vida es laboratorio).

---

## 19. Pendientes/decisiones de producto — continuidad de partida, marchas y profundidad social (2026-08-24)

Registro de decisiones e ideas. **Solo documentación en este paso; nada implementado todavía.**

### 19.1 Crisis de vida del pueblo / fin de partida
**Decisión:** se abandona el game over seco al alcanzar Vida del Pueblo = 0. El tono del juego prioriza el apego a una partida persistente y a sus residentes.

- Vida = 0 NO expulsa automáticamente de la partida.
- Mostrar evento/modal especial de crisis con decisión explícita del jugador:
  - **Opción A:** intentar salvar el pueblo → genera un acontecimiento extraordinario jugable (fiesta/reencuentro/operación de recuperación u otro evento coherente).
  - **Opción B:** abandonar/reiniciar y empezar una nueva partida.
- Superada la recuperación: colchón pequeño en Vida (**referencia inicial a calibrar: ~20**).
- La crisis puede repetirse en el futuro. No existe una única «segunda oportunidad».
- El jugador puede mantener mucho tiempo el mismo pueblo si está encariñado con sus residentes.

El game over deja de ser castigo automático y pasa a ser una decisión del jugador.

**Resolución S11 (2026-08-24): APROBADA como decisión de producto — opción G1 «crisis forward sin rebobinado»**, coherente con esta sección. La investigación técnica descartó expresamente para esta mecánica: rebobinado a inicio de día, restauración de amanecer/checkpoint, `SnapshotService` como feature de jugador, `.bak` como segunda oportunidad, restore único y cualquier viaje hacia atrás de RNG/reloj (RNG determinista único ⇒ save-scumming reproducible). El colchón **~20 sigue siendo referencia NO definitiva** hasta fase específica de diseño/calibración; las consecuencias/cicatrices quedan **sin definir**. Registro completo en `PLAN_POST_AUDITORIA_PLAYTEST.md` §S11. Nada implementado todavía.

### 19.2 Nueva partida accesible
Debe existir una vía permanente y clara para empezar una nueva partida, no únicamente desde situaciones de final/crisis. No saturar el lateral principal. Ubicación a explorar dentro de Ayuda/Ajustes o sección «Mi partida», con confirmación fuerte para impedir reinicios accidentales. El copy puede mantener el tono humorístico del juego. **Reconciliado en §23.8:** ubicación candidata principal = Espacio de Celestine, como opción secundaria con confirmación fuerte. **Ubicación candidata consolidada en §23.8 (Espacio de Celestine); hoy ya existe `btn-nueva-mesa` en hud-right de play.**

### 19.3 Rechazos comprensibles («caja negra del NO»)
Problema observado en playtest: el jugador recibe con frecuencia rechazos a propuestas y no siempre comprende la causa. **NO asumir que la media geométrica de voluntad es el problema.**

**Antes de recalibrar, medir en la versión canónica actual:**
- aceptación real por tipo de plan;
- separar gates duros (agenda, aforo, horario/lugar, disponibilidad) del rechazo por VOLUNTAD;
- peso real de cada modificador vigente: emoción, relación, recuerdos/rechazos, cooldown, afinidad con plan/lugar/hobby, conflictos, continuidad y cualquier otro;
- detectar si algún modificador domina indebidamente.

**Objetivo de producto:** un NO debe transformarse en información jugable cuando Celestine dispone de conocimiento suficiente.

- La voluntad sigue siendo probabilística; sus números permanecen ocultos. No mostrar porcentajes internos.
- Cuando exista información suficiente conocida por Celestine, los rechazos/propuestas fallidas deben ofrecer explicación narrativa coherente con la causa real: ánimo, afinidad con el plan, relación con la otra persona, cansancio/contexto, rechazo reciente/cooldown, agenda u otros modificadores reales.
- Si Celestine todavía desconoce la causa real, se admite una explicación narrativa incierta.

Ejemplos de copy:
- «Hoy no es Jaime: es el plan. No le apetece nada ir al bar.»
- «Fernando está agotado y prefiere dejarlo para otro día.»
- «Parece que todavía no se siente cómodo quedando a solas con Laura.»

**Refinamiento (2026-08-24) — misterio humano:** un rechazo NO debe revelar necesariamente LA VERDAD MATEMÁTICA COMPLETA. Objetivo: una explicación HUMANA SUFICIENTE que evite la sensación de arbitrariedad, sin transparencia total sobre la causa interna del motor. Lo que el NPC expresa depende de lo que entiende de sí mismo, lo que está dispuesto a admitir, su confianza con Celestine, su personalidad y el conocimiento disponible.

Ejemplo de escalada (verdad interna: cansancio + miedo al rechazo + incomodidad con alguien):
- respuesta superficial: «Hoy no me apetece salir.»
- con mayor confianza: «No es por Fernando, es que estoy agotado.»
- con mucha confianza: «Me da un poco de miedo quedar a solas con él, para qué te voy a mentir.»

Celestine NO necesita conocer siempre toda la fórmula; eso mantiene misterio, personalidad, descubrimiento y humanidad. **Formulación canónica: «Un NO debe resultar humano y suficientemente comprensible, pero no necesariamente transparente respecto a toda la causa interna del motor».**

**Revalidar sobre partida NUEVA**: durante la reconciliación ya se han corregido varios bugs/modificadores de voluntad y puede haber cambiado la percepción del problema.

### 19.4 Marchas — replantear causa principal
Revisar el sistema actual de intenciones de marcha. **Nueva dirección preferida:** la tristeza/enfado/conflicto NO deben ser por sí solos el motivo principal de una marcha.

Priorizar en su lugar:
- falta prolongada de interacción de Celestine;
- falta de arraigo;
- aislamiento social real;
- ausencia de vínculos importantes;
- poca participación en la vida del pueblo.

Un personaje con mucha interacción del jugador no debería querer marcharse simplemente porque atraviesa conflictos o emociones negativas.

Escalada de avisos: primera intención de marcha → segunda intención si continúa sin encontrar su sitio → posible tercera fase más seria. Antes de una salida irreversible: explorar misión/última oportunidad de arraigo.

Se mantiene por ahora el principio de **marchas consentidas** hasta decisión específica posterior.

**Playtest a investigar:** se ha observado una partida de ~54 días sin ninguna intención de marcha. Revisar si la frecuencia/condiciones actuales hacen el sistema excesivamente raro o prácticamente invisible.

**Consecuencias sociales de una marcha [DECIDIDO].** Una marcha no termina en «Marta se ha ido» y desaparecer sin consecuencias: los vínculos reales que deja atrás pueden provocar reacciones. La consecuencia procede DE LAS RELACIONES REALES — nunca tristeza automática de todo el pueblo; cada vecino puede tener perspectiva distinta.
- Vínculo fuerte positivo con quien se va: tristeza, entrada de Diario, posible Mensajito, recuerdo.
- Alguien muy enfadado con quien se va: alivio u otra reacción coherente.
- Si tenía pareja: la marcha es un acontecimiento relacional importante.
- Asuntos pendientes: pueden quedar recuerdos/hilos abiertos.

**Exresidentes — sin visitas físicas por ahora [DESCARTADO POR AHORA].** DESCARTADA la vuelta física de antiguos residentes al mapa como visitantes temporales: con población activa ~8–16 (§19.11), añadir caras temporales fuera de la partida activa introduce ruido sin suficiente jugabilidad («no queremos que Marta vuelva dos días y aparezca como token en el mapa»). Quien se marcha deja de participar en: encuentros actuales, móvil, Mensajitos interactivos ordinarios, relaciones activas del día a día y cualquier sistema que requiera presencia. Puede seguir existiendo como MEMORIA («José todavía echa de menos a Marta»). Principio anti-spam: si una consecuencia sobre un exresidente no ofrece información valiosa, emoción relevante o posibilidad de continuidad, no se convierte en spam. Coherente con F12 APARCADA (§22.3) y objetos de despedida ligados al momento de la marcha (§23.2-bis).

### 19.5 Eventos comunitarios propuestos por Mensajitos / organizados por Celestine
Los residentes pueden proponer acontecimientos del pueblo a Celestine mediante Mensajitos. Ejemplos: cumpleaños, fiesta sorpresa, karaoke, bingo musical, club de lectura, cena, picnic, cine especial, evento del bar, actividades especiales de locales.

**Celestine decide:**
- aceptar/rechazar la propuesta;
- lugar;
- fecha/hora cuando corresponda;
- lista de invitados.

**AFORO:** los eventos tienen aforo limitado. Celestine elige a quién invita; NO se invita automáticamente a todo el pueblo. Esto forma parte de la jugabilidad.

Ejemplo: club de lectura con aforo 5; Celestine selecciona cinco vecinos y puede dejar fuera accidentalmente a alguien a quien le encanta leer. Ese vecino PODRÍA reaccionar después únicamente si resulta coherente: «He visto que hicisteis lo del club de lectura… me habría encantado ir.» Para reaccionar se consideran interés real, si conocía el evento, disponibilidad y personalidad/contexto. Esto incluso puede descubrir al jugador información que desconocía sobre ese vecino.

- La asistencia real respeta agenda, trabajo, sueño, voluntad y otros planes (además de relaciones, personalidad/intereses y contexto).
- Los eventos NO regalan amistad/romance: crean oportunidades extra de interacción, contextos favorables, posibles microeventos, recuerdos e historias posteriores.

### 19.6 Cumpleaños
Añadir cumpleaños a personajes como futura fuente de acontecimientos. Decidir posteriormente si basta día/mes o se usa fecha completa (para permitir envejecimiento). Deben producir jugabilidad y no limitarse a un aviso informativo.

### 19.7 Personalidad social de los lugares
Los lugares no deben ser únicamente decorado/agenda/hobby: cada tipo de lugar puede sesgar los microeventos posibles sin garantizar resultados. Ejemplos orientativos:

| Lugar | Sesgo |
|---|---|
| Cafetería | charla / conocerse |
| Parque | conversación personal / romance tranquilo |
| Restaurante | intimidad / romance |
| Bar | colegueo / coqueteo / desinhibición |
| Gimnasio | compañerismo / pique / admiración |
| Biblioteca | conversación tranquila / afinidad |
| Karaoke | humor / vergüenza compartida / atracción |
| Discoteca | química / impulsividad / celos |
| Bingo | socialización / pique / cotilleo |

El lugar debe convertirse en una decisión estratégica blanda de Celestine.

### 19.8 Historias personales / necesidades narrativas
IDEA A EXPLORAR, NO IMPLEMENTAR TODAVÍA. Los residentes podrían atravesar necesidades narrativas temporales que orienten su comportamiento durante días/semanas: conocer gente, salir más, ampliar amistades, compartir un hobby, recuperar un vínculo, buscar romance, necesitar tranquilidad, sentirse parte del pueblo.

- No mostrarlas como quests/barras RPG.
- Deben manifestarse mediante Mensajitos, decisiones autónomas, peticiones y comportamiento.
- Evaluar primero cuánto puede cubrir Mensajitos 2.0 antes de crear un motor nuevo.

### 19.9 Reputación de Celestine
No añadir de momento otra barra/contador visible. Explorar reputación interna manifestada mediante copy y comportamiento de residentes: si sus consejos suelen funcionar, los vecinos pueden pedirle opinión con mayor confianza; si sus intervenciones producen desastres, pueden bromear/desconfiar de ella. La reputación debe sentirse en cómo el pueblo trata a Celestine, no necesariamente ocupar espacio permanente de UI.

### 19.10 Economía
NO cerrar todavía sistema de dinero/fama. Antes de introducir monedas permanentes, determinar qué decisiones jugables interesantes financiarían; no añadir dinero/fama únicamente porque sea convención de videojuegos. Áreas de exploración: desbloqueo de lugares/bloques, organización de eventos especiales, viajes, herramientas sociales, mejoras del pueblo.

### 19.11 Escala de población
Reevaluar si 46 residentes simultáneos debe ser objetivo habitual. Hipótesis de diseño: **~8–16 residentes** favorecen conocimiento y apego. Las ampliaciones deben permitir pueblos mayores, pero el jugador no debe sentirse obligado a llenar toda la capacidad. Las marchas y nuevas llegadas pueden servir para renovar personajes que no generan interés al jugador sin destruir vínculos con residentes queridos.




## 20. Diagn�stico embudo romance aut�nomo (2026-08-24)

**Rama:** `sim/embudo-romance-autonomo` � **Sin tocar probabilidades** � **Sin calibrar** � **Sin deploy**.
Sondas de observaci�n puras (`SimFunnelProbe`, inertes salvo `meta.sim_funnel=true`; no consumen RNG ni alteran reglas).

### M�todo
Banco headless `dev/_sim_funnel_romance.php`: partidas en memoria (poblaci�n fija {3, 8, 16, 24}, pool can�nico jugable), avance can�nico hora a hora (`avanzarPasoAPaso`), cero intervenci�n de Celestine/jugador. 16 partidas = 4 poblaciones � 4 seeds � 100 d�as, checkpoints d15/30/60/100. Datos y tablas completas: `tmp/sim_funnel/informe_embudo.md`.

### Resultado del embudo (media de seeds, d�a 100)

| pob | conocidos | %pares | se�al | flechazo | 1� cita | citas+ | parejas | declaraciones | crisis | rupturas |
|---|---|---|---|---|---|---|---|---|---|---|
| 3 | 1.5 | 50% | 1 | 1 | 0 | 0 | 0 | 0 | 0 | 0 |
| 8 | 10.5 | 37.5% | 6.8 | 6.8 | 0 | 0 | 0 | 0 | 0 | 0 |
| 16 | 42.8 | 35.6% | 23.8 | 23.8 | 0 | 0 | 0 | 0 | 0 | 0 |
| 24 | 91.3 | 33.1% | 50.3 | 50.3 | 0 | 0 | 0 | 0 | 0 | 0 |

**0 primeras citas, 0 citas posteriores, 0 parejas, 0 declaraciones, 0 crisis, 0 rupturas en las 16/16 partidas y en TODOS los horizontes.** El 100% de los romances queda congelado en exactamente 28 puntos (banda `interes`, delta del flechazo) hasta el d�a 100. Tiempo medio conocerse?flechazo: 6�24 d�as seg�n poblaci�n.

### Cadena de bloqueos (orden de severidad)

1. **BUG � Salidas individuales 100% abortadas (`HORA_PASADA`)**: `MotorVidaDiaria::quizasSalidaIndividual` agenda en la hora actual y `EncuentroEngine::programar` exige futuro estricto (`Reloj::esFuturo`). ~820 intentos fallidos por partida/100 d�as en todas las poblaciones. La capa de oportunidades individuales est� muerta.
2. **Social estancado < 12 ? 0 quedadas NPC-NPC**: los casuales solo dan contacto `leve` y el bridge usa `solo_desconocidos`; la quedada casual exige `soc >= 12` en ambas direcciones, umbral que jam�s se alcanza (0 tiradas de quedada en todas las partidas). Resultado: ning�n encuentro NPC-NPC programado jam�s ? ninguna cita ? nada que resolver como `romantico`/`cita`/`primera_cita`.
3. **Can�nicamente fuera de play**: `familias_en_play = [trabajo, ocio, romance, consejo]` excluye `mandar_flores`/`mandar_mensaje` (`romance_accion`, la �nica v�a que agenda quedadas aut�nomas), `declaracion` (`romance_hito`, �nica puerta a `INICIO_PAREJA`) y toda la familia `pareja` (crisis/ruptura/reconciliaci�n).
4. **Gate de primera cita solo existe en el camino jugador/Celestine**: `PropuestaNivel` + `SenalRomantica::desbloqueaPrimeraCita` viven en `PropuestaEncuentroEngine::proponer`; el motor aut�nomo nunca programa tipo `primera_cita`.
5. **Iniciativa desperdiciada (secundario)**: `elegirEvento` vuelve a escoger `flechazo` para pares que ya lo tienen (23�125 aborts `ya_registrado` por partida) y los cooldowns de familia 48 h abortan 46�128 picks m�s. No cierran el embudo, pero queman presupuesto de vida.
6. **Flechazo v�a hueco ignora p=0.006** (`AccionRomantica::ejecutar(..., forzar=true)`): por eso los flechazos son FRECUENTES (el p=0.006 solo aplica al camino casual �0.0021/tirada, casi nunca alcanzado).

### Clasificaci�n solicitada

- **Falta de oportunidades**: PARCIAL � bug HORA_PASADA mata la capa individual; las coincidencias por agenda s� funcionan (~30�38% de pares conocidos al d100, saturan hacia d15).
- **Voluntad rechaza**: NUNCA OCURRE en el camino aut�nomo (los NPCs no pasan por voluntad ponderada; solo la declaraci�n la evaluar�a y nunca llega a existir).
- **Agenda/trabajo/sue�o**: NO es bloqueo (0 casos `sin_disponibles_agenda`, 0 quedadas con `sin_hueco_agenda`).
- **Compatibilidad**: no bloquea (vetos parentesco/edad correctos, sin falsos positivos observados).
- **Cooldown**: secundario (desperdicio de iniciativa, no causa ra�z).
- **Ausencia de iniciativa aut�noma**: NO � hay 60�300 huecos de familia romance ejecutados por partida; la iniciativa existe y sobra.
- **Gates de primera cita**: CAUSA RA�Z estructural (puntos 2+3+4): no existe camino aut�nomo hacia `primera_cita` ni hacia formar pareja.

### Veredicto: �sigue habiendo "romance demasiado raro"?

**Ya no es "demasiado raro": ahora es EST�RIL.** El problema cambi� de forma respecto al playtest viejo (reglas anteriores):
- Entrada f�cil: flechazos abundantes y tempranos (pop8: ~4.5 al d15, ~7 al d100) porque el camino hueco fuerza el flechazo sin tirar p=0.006.
- Desarrollo imposible: tras el flechazo no existe mec�nica aut�noma alguna que convierta romance en citas o pareja; todo se congela en 28.
- Con Celestine el embudo puede completarse (camino propuesta s� tiene gates y voluntad), lo que explica que el juego no se note roto en play manual.

### Propuesta de calibraci�n / ajuste � SIN APLICAR (para decisi�n)

- **P1 (bug, sin tuning)**: permitir a las salidas individuales agendar en franja futura v�lida (o tratar la hora actual como inicio v�lido). No cambia probabilidades.
- **P2 (canon pendiente)**: decidir e incluir en `familias_en_play`: `romance_accion` (flores/mensaje), `romance_hito` (declaraci�n) y pol�tica de familia `pareja` (crisis/ruptura) � sin ellas no hay historia rom�ntica aut�noma posible.
- **P3 (umbral social)**: revisar coherencia contacto `leve` ? gate `soc >= 12` de quedada casual (subir delta social del casual o bajar umbral).
- **P4 (primera cita aut�noma)**: cuando exista se�al y agenda conjunta, que la quedada de un par con se�al sea tipo `primera_cita` (una vez por par), reutilizando `SenalRomantica::desbloqueaPrimeraCita` como criterio can�nico.
- **P5 (rendimiento de iniciativa)**: en `elegirEvento`, descartar pares con FLECHAZO ya registrado y respetar cooldown de familia antes del pick ponderado.
- **P6 (recalibraci�n posterior a P1�P5, con objetivos expl�citos)**: ajustar `flechazo.probabilidad`/peso familia `romance` para apuntar a: pop8 ? ~1 pareja estable entre d30�45 y 3�6 al d100; mantener canon p_plan = 0.85 y flechazo unilateral/no crea pareja.
- **NO tocar todav�a**: `flechazo.probabilidad=0.006`, `pesos_familias`, cortes til�n/interes, `bonus_primera_cita_reciproca`. Primero reparar estructura (P1�P5), luego medir de nuevo con este mismo banco.

### Artefactos
`dev/_sim_funnel_romance.php` (banco) � `dev/_sim_funnel_report.php` (agregador) � `src/Engine/SimFunnelProbe.php` + sondas en MotorVidaDiaria/AcontecimientoDiario/InteraccionCasual/AccionRomantica/EncuentroResolver/SenalRomantica � datos crudos y `informe_embudo.md` en `tmp/sim_funnel/`.

---

## 21. Consolidación de diseño — intención de Celestine, canales narrativos y móvil de los vecinos (2026-08-24)

SOLO DOCUMENTACIÓN/DISEÑO. Sin runtime, sin deploy, sin cache-buster.

Reconciliación: la revalidación de la «caja negra del NO» se consolida en **§19.3**; los eventos propuestos/organizados por Celestine (aforo, invitados, reacciones) se consolidan en **§19.5**. Este apartado añade lo nuevo sin duplicarlos.

### 21.1 Principio de intención de Celestine
Celestine NO dispone como núcleo de juego de herramientas diseñadas para:
- sabotear relaciones;
- sembrar rumores;
- contar secretos sin permiso;
- provocar daño deliberadamente;
- romper parejas.

Su papel es ayudar, orientar, aconsejar, mediar, organizar, observar e intentar conectar a la gente.

El drama puede surgir como CONSECUENCIA emergente de consejos equivocados, incompatibilidades, celos, decisiones autónomas, errores del jugador, encuentros que salen mal, eventos y personalidad — pero no por una mecánica explícita de «casamentera diabólica».

### 21.2 Canales narrativos diferentes
Separación canónica:
- **MENSAJITOS** = vecino → Celestine (peticiones, dudas, agradecimientos, reproches, consejos, propuestas de eventos…).
- **COTILLEOS** = información pública que circula por el pueblo.
- **MÓVIL DEL VECINO** = microinteracciones privadas NPC → NPC (**APARCADO**, ver §21.3).
- **DIARIO** = memoria/perspectiva personal.

No duplicar automáticamente un mismo hecho con el mismo contenido en varios canales. Un mismo evento CANÓNICO puede originar distintas perspectivas, pero cada canal debe aportar valor diferente.

Ejemplo válido:
- Cotilleos: «Jaime y David llevan toda la tarde juntos 👀»
- Móvil de David (si algún día llegara a existir): Jaime → ❤️🐱
- Mensajitos de Jaime a Celestine, posteriormente: «Bueno… lo de David no ha ido mal 😳»

(No repetir cuatro veces: «Jaime estuvo con David».)

**Refinamiento de producto (2026-08-24):**
- **Mensajitos = solo comunicación con valor:** Celestine tiene que decidir, puede responder/actuar, recibe información personal realmente relevante o cierre/seguimiento de algo en lo que intervino. NO es feed general: «se miraron / se encontraron / hablaron / se volvieron a mirar» no se notifican (pertenecen a otros sistemas o no necesitan exposición). Ejemplos buenos: «¿Crees que debería intentarlo con José?», «¿Montamos algo el viernes?», «Me gustaría ir al cine con Jaime», «No sé si termino de encontrar mi sitio aquí», «Gracias por lo del otro día, al final salió genial». Las llegadas importantes pueden generar comunicación específica. Canon completo en §22.1.
- **Cotilleos = información pública, no bandeja de tareas:** lo que se comenta/percibe en el pueblo (encuentros, situaciones curiosas, romance perceptible, discusiones públicas, comportamientos llamativos); no requiere acción de Celestine. Si un hecho ya está contado como Cotilleo, NO se convierte automáticamente en Mensajito.
- **Diario = memoria personal con utilidad clara:** permite reconstruir qué le ocurrió a ESA persona días después («¿Cómo había acabado la última cita de Marta con José?»), aunque el Cotilleo haya desaparecido del feed reciente. Se MANTIENE; deriva de hechos canónicos y no los contradice.
- **Regla transversal (economía narrativa): UN HECHO NO TIENE DERECHO AUTOMÁTICO A APARECER EN TODOS LOS CANALES.** Existe el HECHO CANÓNICO y después se decide qué canales aportan valor. Cita privada muy buena: Diario quizá sí; Mensajito solo si alguien quiere contárselo a Celestine; Cotilleos quizá NO; recuerdo permanente solo si es hito. Pelea pública: Cotilleos sí; Diario de implicados sí; Mensajito solo si alguien pide ayuda; otro canal únicamente si aporta algo distinto. Objetivo: evitar spam narrativo (un hecho ≠ Mensajito + Cotilleo + Diario + móvil + recuerdo + notificación). Mecanismo técnico previsto en §22.6.

### 21.3 Móvil personal de los vecinos
> **CAMBIO DE DECISIÓN (2026-08-24, reconciliado con la revisión de cierre de Mensajitos 2.0 §22.7): APARCADO / REEVALUABLE.** NO implementar el móvil NPC↔NPC por ahora. Motivo: dudas sobre su VALOR JUGABLE DIFERENCIAL — puede acabar siendo simplemente otra representación de la misma interacción social que ya producen coincidencias, charlas, citas, continuidad, Cotilleos y Diario (José → ❤️ / Manuel → 😳 puede ser gracioso, pero si solo añade otro pequeño delta relacional no justifica otro sistema + otra UI + otra fuente narrativa + más persistencia + más información que leer). **Puerta de reentrada:** antes de recuperarlo habrá que responder «¿Qué puede HACER o ENTENDER mejor el jugador gracias al móvil que NO consiga ya mediante encuentros, Diario, Cotilleos, Mensajitos o continuidad?». Si no hay respuesta clara, no se construye. El resto del apartado se conserva como referencia de diseño para esa futura reevaluación; nada de él describe diseño próximo ni aprobado para implementación.

#### Concepto
Cada vecino tiene dentro de SU FICHA un pequeño apartado 📱 Móvil. NO existe un feed global de conversaciones de todo el pueblo. La vista principal es «RECIBIDOS POR ESTE VECINO»: mensajes/reacciones RECIENTES que ese vecino HA RECIBIDO de otros.

Ejemplo visual conceptual (ficha de Marta):

```
José      ❤️
Carlos    😤🐶
Jaime     👀
Laura     😂
Fernando  💛
```

Permite entender de un vistazo cómo se están relacionando los demás con Marta. No hay bandeja tradicional: ni enviados, ni borradores, ni conversaciones completas, ni historial infinito. Internamente el motor sí conoce emisor y receptor para resolver respuestas y direccionalidad.

#### Regla de conocimiento
Solo pueden enviarse microinteracciones digitales entre vecinos que YA SE CONOCEN. Un desconocido no puede enviar espontáneamente corazones/emojis a otro desconocido. Reutilizar la comprobación canónica de conocimiento entre NPC.

#### Quién manda los mensajes
Por defecto los mensajes nacen AUTÓNOMAMENTE de los NPC. Celestine NO controla «José manda ❤️ a Manuel»: rompería la filosofía «el jugador propone. Los personajes viven». El móvil actúa como equivalente DIGITAL de los encuentros casuales del mapa; los personajes siguen relacionados entre encuentros sin intervención directa del jugador.

#### Por qué envían algo
El envío NO es azar puro: deriva de sistemas existentes — social A→B, romance A→B, compatibilidad, emociones, flechazos, pareja, encuentros recientes, resultado de una cita, continuidad, conflicto, futuros celos (cuando exista el sistema), personalidad/rasgos, recuerdos significativos.

Ejemplos:
- Tras cita muy buena: ❤️ / 🥰 / 🐱❤️
- Tras interacción divertida: 😂 / meme/sticker
- Amistad: 💛 / 🙌 / 😂 / 🍻
- Interés todavía incierto: 👀 / 😳
- Enfado: 😤 / 🙄 / 💥
- Futuros celos: 👀 / sticker tipo «te estoy vigilando»

Los emojis/stickers son REPRESENTACIÓN narrativa de un estado real; no deben crear arbitrariamente ese estado.

#### Respuesta del receptor
El receptor NO tiene obligación de contestar: puede responder positivo/neutral/negativo, dejarlo en visto o devolver otro emoji/sticker. La respuesta se calcula desde SU estado hacia el emisor; A→B y B→A siguen siendo direccionales.

Ejemplo: José → Manuel: ❤️ (José siente interés romántico). Manuel responde ❤️ si hay reciprocidad; 😳 si curiosidad/timidez; 💛/🙂 si lo vive como amistad; «visto» si no quiere responder; 🙄 si existe rechazo/conflicto coherente. No revelar porcentajes al jugador.

#### Efecto en gameplay
Las microinteracciones digitales producen CONSECUENCIAS PEQUEÑAS: pueden influir moderadamente en social, amistad, romance, emoción, continuidad y conflicto. NUNCA deben por sí solas formar pareja, romper pareja, provocar flechazo artificial, transformar enemigos en mejores amigos, sustituir encuentros presenciales ni generar grandes saltos. Son MICROEMPUJONES; los encuentros/citas continúan siendo el principal vehículo relacional.

#### Para qué sirve
A) **CONTINUIDAD** — evita que entre dos encuentros parezca que los NPC dejan de existir mutuamente.
B) **PERCEPCIÓN** — hace visibles pequeñas señales de lo que el motor ya procesa (antes de descubrir que Jaime quiere a David, Celestine pudo ver Jaime → David: 👀, luego 😂, luego ❤️).
C) **VIDA AUTÓNOMA** — refuerza la sensación de que el pueblo sigue viviendo sin que Celestine tenga que organizar todo.

#### UI del móvil
Ubicación preferida: ficha del vecino → icono/apartado 📱 Móvil. Pequeña señal cuando el vecino tenga una interacción reciente relevante recibida. Al abrir: cara/nombre del emisor, emoji/sticker enviado, respuesta del residente si existe, información mínima contextual cuando ayude. No hacer un chat completo. Debe provocar «Uy… ¿qué le ha mandado Jaime a Marta?», NO «tengo 84 conversaciones sin leer».

#### Retención / persistencia
NO guardar eternamente cada emoji individual. Mostrar únicamente los últimos ~5–8 recibidos relevantes en la ficha; descartar/compactar los viejos. Si ocurre algo narrativamente importante, guardar un resumen canónico: «José escribió a Manuel después de la cita y Manuel respondió positivamente». Conservar el HECHO relevante, no un historial infinito de stickers. Diseñar teniendo en cuenta el problema ya detectado de crecimiento de persistencia.

#### Cadencia (anti-spam)
A estudiar: cooldown por pareja, límite diario global, límite/relevancia por residente, anti-repetición, prioridad por acontecimientos recientes, probabilidad real de que no ocurra nada. Una cita importante de hace dos horas puede elevar mucho la probabilidad de contacto; dos conocidos sin relación especial desde hace veinte días pueden no escribirse nunca.

#### Relación con eventos
Eventos y encuentros pueden generar microinteracciones posteriores:
- Después del karaoke: José → Manuel: 😂🎤
- Después de una cita excelente: Laura → Ana: ❤️
- Después de una cita incómoda: Jaime → David: 😬; David → Jaime: visto
- Después de un cumpleaños: varios asistentes pueden mandar 💛/🎂 al protagonista

Esto aporta continuidad sin necesitar un texto largo para todo.

#### Antes de implementar
Crear primero una auditoría/diseño técnico acotado para comprobar cómo reutilizar relaciones direccionales, romance, emociones, encuentros, continuidad, conocimiento entre NPC, memoria_eventos, Diario y descubrimientos. NO crear un segundo motor relacional paralelo: el móvil debe ser una CAPA DE VIDA/PERCEPCIÓN sobre motores existentes.

---

## 22. Mensajitos 2.0 — diseño global cerrado: familias, consecuencias, memoria y fases (2026-08-24)

SOLO DOCUMENTACIÓN/DISEÑO. Sin runtime, sin deploy, sin recalibración. Consolida el diseño global del canal **vecino → Celestine** y lo reconcilia con lo ya decidido: §19.3 (caja negra del NO), §19.4 (marchas), §19.5 (eventos comunitarios), §19.6 (cumpleaños), §19.8 (necesidades narrativas), §19.9 (reputación), §21 (intención, canales y móvil). Amplía `BUZON_DE_CELESTINE.md` y R01/R06/R07 de `PLAN_POST_AUDITORIA_PLAYTEST.md`; no los sustituye.

**Revisión de cierre (24/08/2026):** reconciliación final de producto — `vinculo_celestine` sale de primera hornada (APARCADO), confidencias simplificadas sin SecretosEngine, Móvil NPC↔NPC pasa a APARCADO/REEVALUABLE (actualiza el estado de §21.3), F12/F13 fuera de Fase B, F11 reformulada (sin reducción directa de conflicto) y nueva regla «Mensajitos no es un feed general».

### 22.1 Definición
Mensajitos es un sistema de jugabilidad, no una bandeja de avisos. Es el canal directo VECINO → CELESTINE. Los residentes pueden pedir cosas, preguntar, pedir consejo, expresar dudas, reaccionar a decisiones anteriores, proponer planes y eventos, hablar de otros vecinos, comunicar necesidades o estados personales, hacer seguimiento de encargos y plantearse su permanencia. La respuesta de Celestine produce empujones PEQUEÑOS y coherentes sobre motores reales existentes (voluntad, emoción, social/romance/conflicto, continuidad, conocimiento, satisfacción, comportamiento futuro). PROHIBIDO el «botón mágico»: ningún mensaje crea parejas, rompe relaciones ni produce saltos grandes. Celestine orienta; los personajes deciden (§21.1).

**Mensajitos NO es un feed general.** Solo tienden a publicarse situaciones donde: Celestine puede responder, decidir o actuar; un vecino comunica algo personal realmente relevante; o se cierra/seguimenta algo en lo que Celestine intervino. Los micro-hechos («se miraron», «coincidieron», «hablaron», «volvieron a mirarse») NO entran en Mensajitos si no hay nada que Celestine deba saber o pueda hacer: pueden ser Cotilleos si son socialmente relevantes, ir al Diario personal, o no necesitar exposición narrativa alguna.

### 22.2 Contrato obligatorio por familia
Toda familia define y CIERRA el circuito:

`DISPARADOR → MENSAJE → RESPUESTAS POSIBLES → EFECTO PEQUEÑO → MEMORIA → POSIBLE SEGUIMIENTO`

Prohibido publicar mensajes «importantes» que el juego olvida. **F9 es prioritario:** una intervención de Celestine no muere al pulsar una respuesta — todo consejo, encargo o evento organizado debe tener cierre posterior. Reutilización obligatoria antes de crear estado nuevo:
- **Opinión/consejo** → `ConsejoEngine` (`partida['inclinaciones_consejo']`; ampliar vocabulario `consejo_id` y empezar a escribir `sigue_consejo`, campo diseñado y hoy nunca rellenado).
- **Continuidad/cierre** → `RelacionBitacora` (hitos) + `memoria_eventos` (familias/cooldowns).
- **Reacción emocional** → `EmotionalStateService` (duración corta, origen etiquetado).
- **Encargos** → pipeline B4 existente (`PeticionPuebloEngine` + `PeticionFeedback`): ampliar plantillas, seguimiento y proporcionalidad al peso.
- **Conocimiento** → `DiscoveryEngine`/`ConocimientoNpc`. Una confidencia es información PRIVADA vecino→Celestine: nunca revela variables internas del motor ni pasa a Cotilleos.

### 22.3 Catálogo de familias (diseño cerrado AHORA; implementación por fases)

| # | Familia | Disparador | Ejemplo | Respuestas Celestine | Consecuencia (motor real) | Memoria / seguimiento | Fase |
|---|---|---|---|---|---|---|---|
| F1 | Opinión / dilema social | pregunta sobre relación propia con otro | «Laura me cae genial, ¿tú crees que yo le gusto?» | animar / orientar a amistad / no mojarse | consejo ±inclinación en voluntad del QUE PREGUNTA; microdelta emocional posible; NUNCA crea pareja | consejo registrado + resultado posterior | A |
| F2 | Orientación entre dos | atracción repartida entre dos personas | «¿Debería intentarlo con Jaime o con Fernando?» | elegir uno / conocer mejor a los dos / ninguna clara | inclinación con objetivo elegido (ConsejoEngine); el no elegido queda intacto; decide el NPC | callback «tenías razón / vaya ojo tienes 😂» | A |
| F3 | Petición personal / encargo | necesidad real (aislamiento, lugar, persona, rutina) | «Me apetece ir al bar con David» | cumplir organizando / ignorar | pipeline B4: Vida ±, feedback proporcional al peso, decepción/tristeza SOLO si tiene sentido | recuerdo persistente de ayuda o decepción; jamás desaparece sin eco | A |
| F4 | Evento colectivo propuesto | grupo con ganas de plan (hobby, motivo, cumpleaños) | «Somos unos cuantos que queremos ir al karaoke» | aceptar (lugar/día/invitados dentro de aforo) / declinar | evento REAL en agenda; asistencia INDIVIDUAL real (agenda, sueño, trabajo, voluntad, contexto); crea oportunidades, NO regala relaciones | balance post-evento → agradecimiento o desastre; flechazos/discusiones solo vía motores normales. Canon completo en §19.5 | B |
| F5 | Presentación / conexión social | deseo de conocer a alguien concreto o con perfil | «Me gustaría conocer a alguien que también lea» | presentar candidato concreto / ahora no | propuesta PRESENTAR (nivel existente) con tercero conservando voluntad; respeta conocimiento y compatibilidad | si cuaja, refuerzo de vínculo; extiende B4 `conocer_a_alguien` con objetivo parametrizable | A |
| F6 | Confidencia personal (simple) | momento/personalidad; necesidad de contarlo | «Creo que me gusta Jaime» · «Últimamente estoy bastante preocupado» · «No sé qué hacer con Laura» | escuchar / dar tono / ofrecer consejo si procede | eco narrativo + emoción breve; puede abrir después F2/F11; SIN SecretosEngine ni subsistema de secretos | se conserva con hilo/memoria_eventos existentes cuando aporta; JAMÁS a Cotilleos (§22.8) | B |
| F7 | Alerta vecinal | observación de terceros apagados/distantes | «Últimamente veo a Jaime bastante apagado» | investigar / intervenir / no meterse | dirige la atención; pista legítima de riesgo (aislamiento, emoción, marcha §19.4); NO misión obligatoria | alerta recordada hasta resolverse o caducar | A |
| F8 | Duda de permanencia / marcha | trayectoria real: poco arraigo, ignorado, aislado | «No sé si termino de encontrar mi sitio aquí» | animar a quedarse / reconocer su preocupación / darle libertad | modifica predisposición INTERNA; ignorada repetidamente escala hacia intención real (progresión §19.4); sin motor paralelo | progresión registrada; la marcha definitiva sigue requiriendo decisión explícita de Celestine | B |
| F9 | Reacción / seguimiento | algo que REALMENTE ocurrió (encargo, consejo, evento, cita) | «¡Al final David vino al bar!» / «Creo que me emocioné demasiado con lo que me dijiste 😂» | leer / responder breve | cierre emocional + vínculo ±; el mundo demuestra que recuerda | cierra OBLIGATORIAMENTE el hilo del hecho original | A |
| F10 | Ritual contextual recurrente | fecha/situación especial (cumpleaños §19.6, aniversario, acontecimiento del pueblo) | felicitación, invitación de cumpleaños | participar / organizar / ignorar | variante de F3/F4 con motivo especial; encariñamiento | cadencia controlada, anti-spam estricto | B |

Familias adicionales propuestas por Cursor (diseño):

| # | Familia | Disparador | Ejemplo | Respuestas Celestine | Consecuencia (motor real) | Memoria / seguimiento | Fase |
|---|---|---|---|---|---|---|---|
| F11 | Mediación / reparación | conflicto activo entre dos conocidos | «Jaime y yo nos hemos enfadado por una tontería…» | aconsejar hablar / sugerir dejar espacio / crear una oportunidad de encuentro / escuchar / no intervenir | Celestine NO reduce directamente el conflicto con una respuesta: la reparación real procede de las acciones posteriores de los NPC y de los motores relacionales canónicos (canal conflicto, hitos apoyo/discusión existentes) | resultado recordado por ambos; puede iniciar reconciliación o distanciamiento | B |
| F12 | Carta del que se fue — **APARCADA** (retirada como familia activa) | ex-residente ya marchado | «¿Cómo va todo por allí?» | — | Cuando un residente se marcha deja de participar en la vida activa: sin Mensajitos ordinarios posteriores, sin móvil, sin retorno físico como visitante y sin hilos abiertos sin posibilidad de actuación. Lo que SÍ puede existir: despedida, reacción de personas vinculadas, objeto dejado, recuerdo, Diario/memoria de quienes permanecen | memoria/diario de quienes se quedan | APARCADA |
| F13 | Plan discreto — **APARCADA** | petición que pide privacidad | «Organízame algo con X, pero que no se enteren» | — | Idea creativa pendiente SOLO si demuestra jugabilidad distinta; evita introducir secretos artificiales, cotilleo provocado ni mecánicas de ocultación innecesarias | — | APARCADA |
| F14 | Testigo / promesa | compromiso personal del NPC | «Si vuelvo a quejarme de Laura, recuérdamelo» | aceptar ser testigo | callback cuando reincide: continuidad con humor, coste mínimo, sin sistema nuevo complejo | promesa activa hasta cierre | A |
| F15 | Curiosidad por Celestine | cercanía narrativa; SIN requisito de vínculo numérico | «¿Tú alguna vez te has enamorado?» · «¿Tú con quién te llevarías bien de aquí?» | responder con tono | SOLO variedad afectiva/narrativa: hace sentir que los vecinos también se interesan por Celestine. Sin puntos, premio, barra ni vínculo 0–100 (Celestine sin avatar, canon DECISIONES_CERRADAS) | tono evoluciona con la cercanía narrativa | A |

### 22.4 Vínculo vecino ↔ Celestine — APARCADO (idea futura)
**NO se implementa ahora y NO es dependencia del MVP:** Mensajitos 2.0 funciona sin este motor; no se añade otra estadística interna únicamente para Mensajitos. Queda como IDEA FUTURA A EXPLORAR solo si más adelante hace falta modelar confianza, cuánto se abre un vecino con Celestine o niveles de sinceridad. En ese caso sería un escalar oculto por residente (complemento individual de la reputación agregada de §19.9), con movimiento acotado y expresión por copy/comportamiento, nunca barra visible. F15 (curiosidad por Celestine) no lo requiere.

### 22.5 Cadencia y anti-saturación
- Presupuesto diario global escalando con población (√n; referencia cualitativa 3–5/día con ~16 residentes — R06/R07 medían 1,65).
- Cooldown por vecino para espontáneos/opiniones (~36–48 h) y cooldown por TEMA reutilizando `MemoriaEventos::enCooldown` con familias nuevas.
- Prioridad: importante > petición > opinión/dilema > espontáneo. Sin hueco → cola al día siguiente, no pérdida.
- Al volver tras larga ausencia: entregar top 3–4 por importancia + DIGEST compacto del resto (evitar efecto «WhatsApp tras 3 días»).
- Poda de guardado: recortar mensajes antiguos resueltos (patrón `PersistenciaCaps`) conservando hilos con seguimiento vivo.

### 22.6 Deduplicación técnica de canales (implementa la regla de §21.2)
- Causa raíz actual verificada: doble escritura estructural en `RelacionBitacora::registrar()` (→ mensajito `cotilleo_hito` Y entrada `hito_relacion` de diario) y suscriptores paralelos sobre `ENCUENTRO_TERMINADO`; NO existe deduplicación cruzada entre canales (pendiente declarado en ESTADO_PROYECTO «Evitar triplicar el mismo hecho»).
- Mecanismo previsto: registro `partida['canales_publicados'][evento_id][canal]` + tabla de PERMISOS por `tipo_evento` (qué canales permite cada hecho y cuál tiene prioridad) + guards en la creación de ambos stores. Un mismo `(hecho, canal)` nunca se publica dos veces; cruzar canales exige perspectiva diferente con valor propio (regla §21.2). Diario personal ≠ Cotilleos: intacto (`visible_en_cotilleo=false`).
- **Economía narrativa (decisión transversal, referencia §21.2):** UN HECHO CANÓNICO NO APARECE AUTOMÁTICAMENTE EN TODOS LOS CANALES; cada acontecimiento selecciona los canales que mejor lo expresan. Ejemplo — petición personal cumplida: mensajito de seguimiento SÍ · diario quizá · cotilleo solo si fue un hecho público relevante. Evitar mensajito + cotilleo + diario + notificación duplicada diciendo lo mismo.

### 22.7 Clasificación de alcance (FINAL, tras revisión de cierre)
- **A) SÍ — primera hornada (sin `vinculo_celestine`):** dedup de canales (§22.6) · F1 opinión/dilema · F2 orientación · F3 petición/encargo · F5 presentación/conexión · F7 alerta vecinal · F9 seguimiento · F14 promesa/testigo · F15 curiosidad por Celestine · cadencia/anti-spam (§22.5) · memoria/hilo y seguimiento (§22.2). Investigación prioritaria ya registrada: caja negra del NO (§19.3).
- **B) SÍ diseño, implementación posterior:** F4 eventos colectivos (canon §19.5) · F8 duda de permanencia/marcha (puente §19.4) · F10 rituales/contextuales (con §19.6) · confidencias personales SIMPLES sin SecretosEngine (F6).
- **APARCADO / REEVALUABLE:** Móvil NPC↔NPC (actualiza §21.3: falta demostrar jugabilidad diferencial; pregunta de puerta: «¿Qué puede hacer o entender mejor el jugador gracias al móvil que no consiga ya con los otros sistemas?») · F12 cartas/retorno de exresidentes · F13 plan discreto · cualquier sistema de secretos explotables · vínculo Celestine numérico.
- **C) NO — dirección descartada:** §22.8.

Nota de interpretación: F11 (mediación/reparación, reformulada en §22.3) forma parte del diseño SÍ pero al no estar listada en la primera hornada se clasifica en B.

### 22.8 Descartado (dirección de producto C)
Sabotear relaciones deliberadamente; sembrar dudas o rumores maliciosos; contar/difundir secretos sin permiso; herramientas explícitas para romper parejas; casamentera diabólica (consolida §21.1); control directo constante de los NPC; crear parejas mediante una respuesta de Mensajitos; grandes saltos relacionales por un mensaje; chat tipo WhatsApp completo con historial infinito; duplicación automática del mismo hecho entre Mensajitos/Móvil/Cotilleos; motores relacionales nuevos paralelos a los existentes; dinero/economía como requisito de este sistema (coherente con §19.10).

### 22.9 Archivos previsiblemente afectados (cuando toque implementar)
**Nuevos:** motor de cadencia de Mensajitos, seguimiento de consejos (cierre de `sigue_consejo`), eventos colectivos (§19.5), catálogos JSON de Mensajitos (separación MOTOR≠DATOS≠NARRATIVA, REGLAS_NO_NEGOCIABLES §9). Confidencias: SIN motor nuevo (reutilizan hilo/memoria existentes); sin SecretosEngine, sin `secretos[]`, sin vínculo Celestine (aparcados, §22.4/§22.7). **Modificados:** `BuzonEngine`, `MensajitoAcciones`, `ConsejoEngine`, `PeticionPlantillas`/`PeticionPuebloEngine`/`PeticionFeedback`, `RelojOperations` (hooks nocturnos), `DomainEvents`/`DomainBootstrap`, API `buzon.*`, `assets/js/play-v3.js` (acciones_ui genéricas + respuestas A/B/C/D), calibración (bloques `mensajitos.*` — cifras siempre en laboratorio, nunca canon por defecto).

---

## 23. Espacio de Celestine — cajón, regalos, experiencias y recuerdos (2026-08-24, rev. corrección final)

SOLO DOCUMENTACIÓN/DISEÑO + INSPECCIÓN TÉCNICA. Sin runtime, sin deploy, sin UI definitiva.
**Estado: DISEÑO APROBADO / PENDIENTE ARQUITECTURA E IMPLEMENTACIÓN.**
Consolida la idea del espacio personal de Celestine reconciliándola con lo existente: §19.2 (nueva partida), §19.4 (marchas/despedidas), §19.5 (eventos comunitarios), §19.6 (cumpleaños), §19.10 (economía), §21.1/§21.2 (intención y canales), §22.5 (cadencia), §22.6 (dedup canales). No crea un segundo motor relacional ni una economía.
**Nota de naming (2026-08-24):** el espacio pasa a llamarse definitivamente **«MI RINCÓN DE CELESTINE»**; este §23 sigue siendo canónico para cajón/experiencias/recuerdos/nueva partida/detallitos/caps, y la ampliación de secciones + auditoría + fases viven en §25.
**Sin dependencia de (corrección final):** vínculo Celestine numérico (§22.4 **APARCADO/REEVALUABLE**, fuera del MVP de Mensajitos 2.0), familia F12 «carta del que se fue» (**APARCADA**), móvil NPC (§21.3), economía, o bypass del motor relacional canónico.

### 23.0 Resultado de la inspección técnica (qué ya existe y se reutiliza)

| Necesidad | Sistema REAL encontrado | Estado |
|---|---|---|
| Hito «regalo» | `RelacionBitacora::REGALO` (`src/Engine/RelacionBitacora.php:16`) | Constante definida, **sin escritores hoy** → reutilizar tal cual |
| Hobbies y hobbies negativos | `PerfilPartida` (3 hobbies, 3 rasgos, `preferencias.hobbies_pos/hobbies_neg/personalidad_pos/neg`) | Funciona |
| Catálogo de aficiones extensible | `data/catalogos/aficiones.json` (categoria/tags/lugar_ids/individual/social; campos opcionales `hooks_eventos`, `afinidades`, `incompatibilidades`) | Funciona; patrón MOTOR≠DATOS |
| Conocimiento de Celestine | `DiscoveryEngine`/`descubrimientos` (cap 400); NPC→NPC usa `ConocimientoNpc` con campos `gusto_{tipo}:{id}` / `rechazo_{tipo}:{id}` ya diseñados | Funciona |
| Emociones | `EmotionalStateService` V1 (neutro/alegre/triste/enfadado + caducidad + origen etiquetado) | Funciona |
| Contexto reciente | `MemoriaEventos::recientes()` + cooldowns `por_familia` | Funciona |
| Entrega narrativa vecino→Celestine | `BuzonEngine` (clasificaciones importante/oportunidad/peticion/cotilleo, origen trazable) + familias Mensajitos 2.0 (§22.3: F9 reacción, F10 ritual) | Estructura lista |
| Recuerdos relacionales | `bitacora_relaciones` (hitos), `memoria_eventos`, `diario` (idempotente por evento_id), `historial_relaciones` (cap 2000, protegido último por par), `RelacionEngine.recuerdos_compartidos=[]` (vacío) | Existen como datos |
| Recompensas de misiones | Hoy solo `vida_pueblo`/Latido (`cuenta_latido`); `PeticionEsquemas` B4 (canon E3); `PeticionFeedback` al cerrar encargo | Sin objetos: gancho libre para detallitos |
| Eventos/experiencias en lugares | Lugares canónicos incl. spa/gimnasio/cine/karaoke; aforo (`AforoEngine`); agenda conjunta; §19.5 eventos organizados por Celestine | Base suficiente |
| Límites de persistencia | Patrón `PersistenciaCaps` + `data/configs/persistencia.json` (`*_cap` + archivar_al_recortar); SchemaFields aditivo; schema v2 | Patrón a copiar |
| Nueva partida | `PartidaLifecycle::nueva/reiniciar`; UI `btn-nueva-mesa` en hud-right (play.php:461) + debug | Motor existe; ubicación a decidir |

**No existe hoy:** inventario, objetos, tienda, álbum de recuerdos, escapadas/viajes. Greenfield salvo los ganchos anteriores.

### 23.1 Concepto y acceso único
UN acceso discreto de Celestine (icono/avatar tipo Cupido). PROHIBIDO llenar la interfaz con botones separados por función. Contenido provisional del espacio:
- **Mi cajón** (objetos recibidos, §23.2–23.4)
- **Experiencias disponibles** (§23.5)
- **Recuerdos de la partida** (§23.7)
- **Nueva partida** (opción secundaria, confirmación fuerte, §23.8)

Inspección de ubicaciones reales (play.php): dock inferior con 4 sellos (Pueblo/Diario/Organizar/Vecinos, play.php:465-470); hud-right en mesa (dinero/buzón/Organizar/Nueva partida, play.php:453-462); lateral izquierdo (Mensajitos, «Celestine apunta», Próximo plan); debug flotante abajo-IZQUIERDA (`aht-debug-float`).
- **A EXPLORAR (preferente según producto):** icono único abajo-derecha, zona hoy libre en desktop.
- **Alternativa:** 5º sello en el dock (coherencia de metáfora visual).
Decisión final de UI pendiente; ninguna implementada.

### 23.2 Cajón de Celestine (objetos)
- Los objetos LLEGAN por causas narrativas, no se compran (sin tienda/dinero/XP/farming, coherente con §19.10): agradecimiento por ayuda, petición resuelta (pipeline B4), eventos comunitarios (§19.5), cumpleaños (§19.6), hitos del pueblo, sorpresas/detallitos (§23.6) y despedidas (§23.2-bis).
- **23.2-bis Objetos de despedida:** durante LA PROPIA despedida/marcha (§19.4), un residente puede dejar a Celestine un objeto, recuerdo o detalle con procedencia emocional («No me cabe en la maleta. Y además creo que aquí tendrá mejor vida.»). Una vez FUERA del pueblo, el exresidente NO entra de forma ordinaria en Mensajitos ni genera cartas posteriores que alimenten el cajón: los legados quedan ligados al momento de la marcha. La familia F12 «carta del que se fue» queda APARCADA y NO es fuente aprobada de objetos.
- Ejemplo canon: Fernando escribe «Por lo del otro día. No sabía qué regalarte, así que he elegido esto.» → Celestine recibe un libro → queda en el cajón hasta que ella decide a quién regalárselo.
- **Procedencia narrativa opcional** (origen + frase + día, p. ej. «La planta de Marta» cuando Marta marcha). Solo cuando aporte valor emocional; NO trazabilidad completa.

### 23.3 Regalar también es gameplay
Bucle: **CONOZCO → DECIDO → PRUEBO → VEO SU REACCIÓN → APRENDO.**
- La evaluación usa SOLO lo que Celestine sabe (`descubrimientos`): hobby/rasgo no descubierto no puntúa; regalar puede CONFIRMAR un gusto o revelar un rechazo (aprendizaje real, patrón discovery §16).
- Entradas del evaluador (todas existentes y canónicas): hobbies + hobbies_neg (`PerfilPartida.preferencias`), rasgos, emoción actual (`EmotionalStateService`), contexto reciente (`MemoriaEventos::recientes`), relaciones/sistemas canónicos ya existentes y procedencia del objeto si existe. **NO introduce ningún contador tipo vínculo Celestine 0–100** (§22.4 aparcado; si algún día se reevalúa, será por su propia vía, no por la de los regalos).
- Bandas narrativas provisionales: **acertado / coherente / tibio / gracioso**. Tibio ≠ castigo: reacción narrativa/graciosa («Gracias… 😅 Seguro que encuentro dónde ponerlo.») con efecto NEUTRO O MÍNIMO. Nada de castigo fijo ni «regalo correcto = +15».
- Consecuencias PEQUEÑAS y acotadas (mismo contrato pequeño-efecto que Mensajitos §22.1): emoción temporal corta, microdelta social acotado, posible descubrimiento, Mensajito posterior (familia F9 §22.3), recuerdo resumido si importa (§23.7), entrada de Diario SOLO si tiene importancia (dedup §22.6).
- Hito `REGALO` en `RelacionBitacora` (constante existente sin uso) registra el hecho consultable por reglas.
- PROHIBIDO: crear pareja/romance directo, saltos grandes, convertir el regalo en moneda social farmeable.

### 23.4 Mapeo objeto ↔ gustos (catálogo, no PHP)
Nuevo catálogo `data/catalogos/objetos.json` (patrón catálogos extensibles): libro, planta, puzzle, taza, peluche, caja de dulces, objeto deportivo, kit creativo, vinilo, patito de goma… Cada objeto referencia ids/categorías/tags de `aficiones.json` (leer→libro, gimnasio→objeto deportivo…) usando campos ya previstos (categoria/tags/afinidades/incompatibilidades). Añadir objetos no exige tocar motor.

### 23.5 Experiencias como recompensas
- **COLISIÓN DE NOMBRE detectada:** `EncuentroExperiencia` YA significa calidad del resultado de un encuentro (muy_mal…muy_bien). Las recompensas-experiencia necesitan término técnico propio (propuesta interna: `plan_regalado` / familia memoria `experiencia_regalada`); nombre UI libre.
- Catálogo conceptual: escapada individual / escapada para dos / cine / picnic preparado / cena especial / spa / taller / concierto / excursión / experiencia de local.
- NO otorgan romance/amistad automática y NO modifican directamente: compatibilidad, voluntad, señales, elegibilidad ni hitos románticos. Permiten a Celestine CREAR CONTEXTOS ESPECIALES de convivencia/encuentro reencaminando por motores existentes (agenda, lugares, aforo; canon de eventos organizados = §19.5). Celestine decide destinatario(s): p. ej. regalar la escapada para dos a José y Manuel.
- Usables también individualmente, entre amigos, en grupos y en contextos NO románticos. Durante la experiencia pueden acercarse… o no encajar; NUNCA garantizan cita, romance ni resultado positivo: las consecuencias las resuelve siempre el motor social canónico (voluntad, compatibilidad oculta, química, emociones), nunca el objeto.
- **Reconciliación con §20:** el diagnóstico del embudo autónomo estéril es contexto de fondo del proyecto, pero las experiencias NO son una «vía de fabricar primeras citas»: no tocan probabilidades, gates ni señales; cualquier romance surge únicamente por los caminos canónicos ya existentes.
- Límite: pocas activas simultáneas (hipótesis ~3; cifra final en laboratorio).

### 23.6 Detallitos sorpresa
- Frecuencia RARA orientada a ilusión, no farming: hipótesis cualitativa inicial «~1 cada varios días jugados + hitos especiales»; cifras exactas en calibración `_provisional` (laboratorio primero, patrón calibracion_vida.json). No todas las misiones dan objeto (la mayoría sigue dando Latido).
- Ganchos naturales inspeccionados: cierre de encargo en `PeticionFeedback` (B4), patrón flag de `MisionDiariaEngine` (`cuenta_latido` → análogo `puede_soltar_detallito`), hitos de bitácora, balance post-evento §19.5.
- Copy tipo: «Hoy te has ganado un detallito.» → pequeña caja → objeto o experiencia al espacio. Entrega por cadencia §22.5 (no satura buzon).

### 23.7 Recuerdos de la partida
- Álbum NO consumible: sin moneda, sin buffs, sin coleccionismo obligatorio. Memoria emocional de ESA partida («reconocer TU pueblo a d50–100»).
- **Fuente primaria = sistemas existentes**: derivar/curar desde `bitacora_relaciones` (primera_cita, inicio_pareja, crisis…), `memoria_eventos` (primer karaoke), `diario` y `descubrimientos`. El recuerdo es un RESUMEN compacto `{id, dia, titulo, texto_corto, ref_hito/evento}`, opcionalmente reutilizando retrato/token existente (no se generan imágenes nuevas).
- Dedup obligatoria vía registro `canales_publicados` previsto en §22.6 (un hecho → un recuerdo, no cuatro entradas).
- Cap estricto (~24–30; podado estilo `PersistenciaCaps` protegiendo los marcados importantes). Conservar HECHOS relevantes, no telemetría.

### 23.8 Nueva partida dentro del espacio
Consolida §19.2: el Espacio de Celestine es ubicación candidata principal. Opción secundaria abajo del todo, con confirmación fuerte (doble paso explícito) y tono: «¿De verdad abandonamos a esta panda y empezamos otro pueblo?». Usa `PartidaLifecycle::nueva/reiniciar` existentes; la política de IDs multi-dispositivo sigue BLOQUEADO_DECISION (§9). **Dirección preferida:** al implementarse el espacio, ABSORBER el acceso de nueva partida dentro de él, retirando/evitando duplicar botones visibles (`btn-nueva-mesa` en hud-right). Momento exacto del cambio A EXPLORAR.

### 23.9 Persistencia y límites
Reutiliza el patrón `PersistenciaCaps` + claves nuevas en `persistencia.json`. **Los tres caps son HIPÓTESIS DE LABORATORIO, no cifras definitivas** (validar/ajustar antes de canonizar):
- `cajon_cap`: ~8–10 objetos utilizables.
- `experiencias_activas_cap`: ~3.
- `recuerdos_cap`: ~24–30 resúmenes compactos.
- **NO existe historial infinito de regalos**: queda el hito en bitácora (limitado por vida relacional) + opcional contador compacto por residente. Sin snapshots nuevos, sin duplicar audit/event_log/diario, SchemaFields aditivo, schema sigue v2.
- Cajón lleno ante nueva llegada: respuesta narrativa elegante (rechazo amable o sustitución guiada); jamás pérdida silenciosa. Mecánica exacta A EXPLORAR.

### 23.10 Clasificación de decisiones

**DECIDIDO (nivel diseño):**
- Un único acceso de Celestine; nada de botones separados por función.
- Sin dinero/XP/monedas/farming; los objetos llegan por causas narrativas (coherente §19.10).
- Objetos (cajón) ≠ experiencias; las experiencias crean CONTEXTOS ESPECIALES, nunca relaciones automáticas ni modificaciones de compatibilidad/voluntad/señales/elegibilidad/hitos románticos.
- Regalar es bucle de conocimiento con reacciones pequeñas y aprendizaje (discovery).
- **Regalo tibio:** reacción narrativa/graciosa con efecto neutro o mínimo; sin castigo fijo.
- **Experiencia rechazada antes de empezar: NO se consume. Ya iniciada: SÍ se consume**, aunque el resultado sea malo.
- Reutilización obligatoria de motores canónicos existentes; prohibido motor relacional paralelo.
- Hito `RelacionBitacora::REGALO` como registro canónico del regalo.
- Caps como HIPÓTESIS DE LABORATORIO con patrón PersistenciaCaps (cifras no definitivas).
- Nueva partida como opción secundaria dentro del espacio, confirmación fuerte; dirección preferida = absorber el acceso y evitar botones duplicados.
- Objetos de despedida ligados al momento de la marcha; sin comunicaciones posteriores del exresidente alimentando el cajón (F12 aparcada).
- Recuerdos visibles como sección secundaria del Espacio de Celestine cuando exista; resúmenes derivados de bitácora/memoria/diario, no duplicación.
- Procedencia narrativa solo cuando aporta valor emocional.
- **Principio anti-devolución absurda:** evitar regalar inmediatamente el mismo objeto a quien acaba de entregarlo; a resolver más adelante con una guardia simple/contextual, sin regla matemática compleja cerrada ahora.
- **Sin dependencia de:** vínculo Celestine numérico (aparcado), F12 (aparcada), móvil NPC, economía o bypass del motor relacional.

**A EXPLORAR (antes de implementar):**
- Ubicación definitiva del acceso (abajo-derecha vs 5º sello dock).
- Validación en laboratorio de caps y frecuencia de detallitos; tamaño de bandas de reacción (`_provisional`).
- Fórmula/ponderadores del evaluador de regalo usando SOLO sistemas canónicos (hobbies/emociones/discovery/memoria/bitácora/relaciones); sin vínculo Celestine.
- Si un regalo exitoso registra descubrimiento formal (`DiscoveryEngine`) y con qué campo (`gusto_objeto:{id}` estilo `ConocimientoNpc`).
- Ruta técnica de experiencias: vía `PropuestaEncuentroEngine` (presentar/organizar) vs evento §19.5 vs híbrido.
- Comportamiento exacto de cajón lleno; guardia contextual anti-devolución (principio ya registrado arriba).
- Si recuerdos son vista derivada pura o lista curada persistida.
- Nombre técnico/UI del conjunto («Espacio de Celestine» provisional).

**DESCARTADO:** tienda convencional; dinero/monedas/XP; farming de recompensas; inventario infinito o historial completo de objetos; trazabilidad total de procedencia; motor relacional paralelo; fotos nuevas generadas para recuerdos; que regalos alimenten mecánicamente vida_pueblo/Latido.

### 23.11 Riesgos identificados
| Riesgo | Mitigación |
|---|---|
| Spam de entregas saturando Mensajitos | Cadencia/presupuesto §22.5; detallitos raros |
| Inventario basura acumulable | Caps + rareza + sin bonus por acumular |
| Duplicación del mismo hecho en cajón/diario/recuerdo/mensajito | Registro dedup `canales_publicados` (§22.6) + regla perspectiva §21.2 |
| Farming encubierto vía Latido | Los regalos NO tocan vida_pueblo; latidos quedan como hoy |
| Crecimiento de save | Todo capped (PersistenciaCaps), resúmenes compactos |
| Colisión conceptual `EncuentroExperiencia` | Término técnico distinto para la recompensa (§23.5) |
| «Regalo correcto» trivializable si se ven gustos | Evaluación solo con conocimiento descubierto; compatibilidad interna jamás visible |

### 23.12 Archivos previsiblemente afectados (cuando toque implementar)
**Nuevos:** `data/catalogos/objetos.json`; motor cajón/entrega (`CajonCelestine` u equivalente); evaluador de regalo; vista de recuerdos; panel/acceso único en UI.
**Modificados:** `RelacionBitacora` (primer escritor del hito REGALO), `PeticionFeedback`/`MisionDiariaEngine` (ganchos detallito), `BuzonPlayBridge`/cadencia (entrega), `play.php`/`play-v3.js` (acceso + absorción de nueva partida), `persistencia.json` (caps nuevos), calibración (bloque `celestine_espacio.*` `_provisional`).

### 23.13 Dudas de producto — estado tras la corrección final

**RESUELTAS (registradas en §23.10):**
1. Regalo tibio → efecto neutro o mínimo, humor, sin castigo fijo.
2. Consumo de experiencias → rechazada antes de empezar NO se consume; ya iniciada SÍ (detalle técnico pendiente: qué marca exactamente «iniciada», probablemente el comienzo real del plan).
3. Nueva partida → dirección preferida: absorberla en el espacio evitando botones duplicados; queda decidir el momento de retirar `btn-nueva-mesa`.
4. Recuerdos → sección secundaria del espacio cuando exista; queda microdecisión de si el panel vacío muestra un mensaje motivador desde el día 1.
5. Legados de marchas → ligados a la despedida misma; NO requieren F12 (aparcada).

**ABIERTAS / A VALIDAR EN LABORATORIO:**
6. ¿Los objetos pueden regalarse a cualquiera, incluido quien los dio? Principio anti-devolución inmediata ya registrado (guardia simple/contextual posterior; sin matemática compleja).
7. Validar caps 8–10 / ~3 / 24–30 en laboratorio antes de canonizar.
8. Momento exacto de retirar `btn-nueva-mesa` del hud-right.

---

## 24. Decisiones reconciliadas — llegadas jugables, rituales/calendario del pueblo y mantra de producto (2026-08-24)

SOLO DOCUMENTACIÓN/DISEÑO. Sin runtime, sin deploy, sin cache-buster. Reconcilia decisiones cerradas tras revisar ideas externas. Actualiza en sitio §19.2/§19.3/§19.4 y §21.2/§21.3; Mensajitos con valor, Cotilleos, Diario y economía narrativa quedan consolidados en §21.2 + §22; regalos/experiencias/recuerdos/nueva partida ya están en §23 — aquí no se duplican.

### 24.1 Llegadas — convertirlas en jugabilidad real [DECIDIDO]
Problema: hoy una llegada puede sentirse pasiva (aparece solicitud → Celestine aprueba → entra el residente → el jugador puede no darse cuenta bien de quién ha llegado).

**A) Perfil previo.** Antes de aceptar definitivamente la incorporación, Celestine consulta una ficha/perfil visual del candidato: conceptualmente un pequeño perfil social tipo Instagram adaptado al tono AHT. Enseña ÚNICAMENTE información legítimamente conocible en ese momento — cara, nombre, edad, algún hobby/interés visible, alguna información pública permitida, pequeño texto de presentación. NO revela compatibilidad oculta, preferencias ocultas, sentimientos, rasgos aún por descubrir ni información interna del motor. Objetivo: que el jugador pueda razonar «Ah, le gusta el deporte; quizá José pueda recibirlo porque también es deportista». Aceptar una llegada deja de ser pulsar OK.

**B) Elegir quién lo recibe.** Al aceptar al nuevo residente, Celestine elige un VECINO DE BIENVENIDA / ACOMPAÑANTE («¿Quién le enseña Villaborde a Marta?» → José). **ES UNA DECISIÓN DIRECTA DE CELESTINE:** José NO puede responder después «No me apetece». No hay otra tirada de voluntad para esto. Solo se respetan imposibilidades objetivas previas (p. ej., no ofrecer como candidato a alguien que trabajará durante toda la franja necesaria).

**C) Franja de bienvenida (~3–4 horas iniciales).** Acompañante y recién llegado pasan juntos esa franja: quedan ocupados; Celestine no puede programar al acompañante en otro plan; aparecen juntos/presentes de forma coherente; pueden producirse varias microinteracciones naturales, conocerse, generar primera impresión, descubrir afinidades, y llevarse bien, normal o regular. La elección NO garantiza amistad: Celestine crea la oportunidad; el motor decide qué ocurre entre ellos.

**D) Llegada muy visible.** Una llegada debe sentirse como un pequeño acontecimiento («Ha llegado Marta») sin quedar enterrada entre Mensajitos de citas u otros avisos. Estudiar el canal/UI más adecuado sin producir spam.

### 24.2 Rituales y calendario del pueblo [DECIDIDO como dirección de diseño]
APROBADA la dirección de RITUALES / ACTIVIDADES RECURRENTES como parte del calendario del pueblo. Ejemplos: mercadillo en el parque (martes 10:00–12:00), sesión de cine de terror (viernes), desayuno comunitario (domingo); también bingo especial, karaoke, paseo, club de lectura o evento recurrente de un local.

- El jugador puede conocerlas CON ANTELACIÓN y UTILIZARLAS: Celestine sabe que el martes hay mercadillo, que a Marta le encanta ese tipo de actividades, y puede programarla/animarla a ir.
- La actividad genera estado positivo si encaja, oportunidades de interacción, presencia conjunta, descubrimientos e historias. PROHIBIDO el atajo «mercadillo = +10 amistad»: el motor debe resolver qué ocurre.
- **Calendario dentro del Espacio de Celestine:** el acceso único de Celestine (§23.1) gana una sección candidata más — CALENDARIO — junto a Mi cajón, Experiencias, Recuerdos y Nueva partida. Debe mostrar con claridad cumpleaños (§19.6), eventos organizados (§19.5), rituales recurrentes, actividades especiales y planes relevantes si procede. No convertirlo en agenda administrativa saturada: debe responder «¿Qué pasa hoy o esta semana en Villaborde?». **Confirmado como pieza de Mi Rincón de Celestine (§25): lectura primero (R1), organización plena cuando existan §19.5/F4, §19.6 y rituales (§25.7).**

### 24.3 Mantra central de producto [DECIDIDO]
La mejora más importante NO es añadir veinte actividades: es conseguir que cada decisión importante pueda producir una PEQUEÑA HISTORIA legible, recordable, coherente y susceptible de continuar días después.

Unidad de diversión deseada: **DECISIÓN → CONSECUENCIA → MEMORIA → POSIBLE CONTINUIDAD.**

Ejemplo: Celestine elige quién recibe a Marta → Marta y José pasan cuatro horas juntos → algo ocurre → dos días después Marta recuerda aquello → una semana después esa primera impresión puede seguir teniendo eco. Eso es preferible a generar cinco notificaciones inmediatas diciendo lo mismo.

---

## DEPLOY SELECTIVO 2026-08-24 23:39 - COTILLEO BACKEND IMPORTANTE (DEPLOY 1 controlado)

**COTILLEO BACKEND: INTEGRADO/DESPLEGADO EN PRODUCCION (3/4 ficheros, verificado) - 2026-08-24 23:23-23:43.**

- SHA fuente: `task/cotilleo-importante-backend` @ `8fe33de` (blobs exactos via git cat-file; commit documental a9195d3 NO usado).
- Subidos y verificados SHA256 remoto=local: `api/handlers/DiarioHandler.php`, `src/Engine/VistaCotilleoV3.php`, `src/Engine/VidaNarrativaBridge.php`.
- **RETENIDO `api/index.php`**: produccion sirve una variante WIP no versionada (presente solo en backup forense `60b4fc29`) con 3 rutas vivas ausentes en 8fe33de: `residente.diario`, `relacion.vista_pueblo` (ambas llamadas por el JS en vivo ea20996) y `debug.resumen_partida`. Subir el blob las habria eliminado. La ruta `diario.cotilleo_visto` YA existe en produccion identica a 8fe33de, por lo que el circuito del badge queda completo sin subir este fichero. PENDIENTE: reconciliar api/index.php (base 8fe33de + re-alta versionada de esas rutas) antes de cualquier deploy que lo toque.
- Backup PRE (rollback 4/4): `dev/_prod_fetch_cotilleo_backend_pre/20260824-233530` (+ hashes remotos previos en log).
- Sin cache-buster (solo PHP); buster en prod intacto: v3-20260824-movil-cards-proxplanes. Sin JS/CSS/play.php/tests/docs/configs.
- Tests previos: cotilleo_importante_badge_test.php 16/16 TODO OK; cotilleo_aviso_ui_test.js 16/16 TODO OK (contra arbol 8fe33de); php -l 4/4 limpio.
- Smoke post: play.php HTTP 200 sin fatal; diario.listar ok=true exponde `importantes_sin_ver`; E2E sobre partida TEST debug_v0 (part_f4eb5fbbc13c0fa6, borrable): cotilleo_visto marcados=1 -> importantes_sin_ver 1->0 persistido.
- Logs: logs/deploy-cotilleo-backend-importante-20260824-233530.log y -233949.log. Prueba humana opcional pendiente: badge fucsia visible en partida real.

---

## DEPLOY SELECTIVO 2026-08-25 00:40 - LLEGADAS SIN NOMBRES DUPLICADOS (NOMBRES RESERVADOS POR PARTIDA)

**PIEZA VERSIONADA Y DESPLEGADA EN PRODUCCION (4/4 ficheros, verificado). Fuente: rama `fix/llegadas-nombres-reservados` @ `4511e96` (base `int/canonical-20260824-consolidada` @ `28baede`).**

- **Que despliega:** regla global de partida sin nombres duplicados entre personajes. Clave doble canonica: exclusion por ID via `HistorialPersonajesPartida::ya_aparecieron` (ofrecido/aceptado/rechazado/expirado/llegado jamas reaparece como ID) + reserva de nombre normalizado (trim+lowercase UTF-8) para quien ha residido (residentes activos, antiguos residentes, llegadas 'llegado'). Rechazado/expirado saca su ID del pool pero NO reserva nombre (contrato canonical). Cubre caso Alba (`per_p014`/`per_p104`, mismo nombre visible, IDs distintos).
- **Archivos subidos (SHA256 remoto=local verificado post-upload):** `src/Engine/NombresReservadosPartida.php` (4ed88ef2...), `src/Engine/CandidatoLlegadaEngine.php` (d33e3a95...), `src/Engine/PoblacionV3.php` (ddec2015...), `src/Engine/TutorialIncorporaciones.php` (b77c3317...).
- **Precheck:** los 3 ficheros existentes en produccion eran byte-identicos a la base pre-pieza (cero arrastre, port limpio). `NombresReservadosPartida.php` YA existia en produccion antes de este deploy (arrastrado por un deploy incremental anterior desde el WIP sin trackear; blob identico a 4511e96), pero estaba HUERFANO: los 3 consumidores seguian en version base y la pieza NO estaba activa.
- **Backup PRE:** `dev/_prod_fetch_llegadas_nombres_reservados/20260825-003932-pre/` (+ `_BACKUP_OK.txt` con SHA256). Rollback = restaurar los 3 ficheros base desde ahi (el cuarto ya existia pre-deploy).
- **Tests previos:** nombres_duplicados_candidato, llegadas_curva_v3, viviendas_pool_v3, capacidad_pueblo_46, post_gate_llegadas_tutorial, pool_jugable_canonico, excluidos_seleccion_p020, mensajitos_acciones -> 8/8 OK; php -l 4/4 limpio (PHP 8.2 CLI local).
- **Smoke E2E en produccion (partidas TEST borrables, ninguna partida real tocada):** T1 `debug_v0`: candidato `per_p162` ofrecido (no residente), aceptar -> en_camino espera 10 min -> tick incorporo con vivienda A02 + mensajitos candidato_en_camino/llegada_efectiva correctos; segundo candidato `per_p022` rechazado -> save con `ya_aparecieron={per_p162,per_p022}` e historial llegado/rechazado; 12 dias adicionales sin reaparicion del ID rechazado. T2 `juego_v1`: PoblacionV3 en vivo eligio `per_p104` (Alba) como residente y excluyo a la otra Alba (`per_p014`) de residentes y cola tutorial (3+5, nombres unicos). Ambas partidas TEST eliminadas (incluido .bak de rotacion).
- **Sin cache-buster, sin JS/CSS/play.php/api/index.php, sin deploy completo.** URL publica verificada: play.php HTTP 200.
- **Deuda documentada (para cuando Design System libere frontend):** el nombre del candidato se extrae en UI parseando el TEXTO visible del Mensajito (regex sobre "X quiere..." en assets/js/play-v3.js ~L704-706 y cuerpoMensajito ~L729). El backend deberia exponer campos estructurados (`candidato_catalog_id` ya viaja; anadir nombre/edad/perfil publico) y el frontend debe dejar de depender del copy. No tocar hasta cerrar DS.

---

## ICONOS HOBBIES - LOTE 17 APROBADO E INTEGRACION PREPARADA SIN DEPLOY (2026-08-25)

**SOLO infraestructura de assets + resolver. Sin deploy, sin cache-buster, sin merge a canonica. Pendiente consumo definitivo por la Ficha de Vecino del Design System (layout/tamanos/seccion AFICIONES finales).**

- **Lote 17 aprobado por diseno:** tinta #2c261f dibujada a mano, viewBox 0 0 32 32 (~29,6px reales), maximo UN pastel DS por icono (mostaza/rosa/coral/verde salvia/lila + azul grisaceo #D8E0E8 en videojuegos), tipografia SIEMPRE fuera del SVG: el nombre sigue pintandose como HTML/Caveat en la ficha.
- **Assets canonicos:** assets/icons/hobbies/hobby-<id>.svg con correspondencia 1:1 a data/catalogos/aficiones.json (leer, escribir, pasear, correr, cafe_social, manualidades, cocina, musica, cine, videojuegos, copas, baile, bingo, deporte, senderismo, plantas, costura). IDs legacy de _propuesta_v2 (jardineria, perros...) excluidos.
- **Mapping/resolver generado:** assets/js/hobby-icons.js expone window.AHTHobbyIcons (ids/has/get/svg); clave = ID canonico, NUNCA label/texto traducido. Regenerable sin drift con scripts/generar_hobby_icons_js.ps1 (falla si falta/sobra SVG o hay <text>/raster/fuentes).
- **Consumo minimo actual:** play-v3.js svgHobbyIcon(id) consulta el resolver con guard y cae al mapa legacy inline si no hay SVG para el ID (ID desconocido NO rompe la ficha). play.php carga hobby-icons.js antes de play-v3.js.
- **Intacto en esta pieza:** descubrimiento, numero de slots, labels del catalogo, Caveat, interrogaciones/candados de slots bloqueados, backend de hobbies y bloque pendiente "Le anima / No le gusta".
- **Tests:** node tests/hobby_icons_test.js -> cobertura 17/17, nombres = IDs canonicos, validez de cada SVG, sincronia resolver-ficheros, fallback de ID desconocido/vacio, labels HTML fuera del SVG, slots intactos, orden de carga en play.php. Regresion ficha: ficha_prefs_quitadas_ui_test y ficha_pistas_ui_test OK; php -l play.php limpio.
- **Validacion visual del lote (local):** dev/prueba-iconos-hobby/ (muestrario completo, control de coherencia y captura de integracion usando el resolver real con demo de fallback).
- **Doc DS:** docs/design-system/iconos-hobbies.md.


## ROMANCE AUTÓNOMO FASE 1 — DESPLEGADO (2026-08-25 09:30)

- **SHA fuente:** f240fd2 (rama int/romance-autonomo-fase1)
- **Reconciliación quirúrgica** contra producción actual (que ya contenía Peticiones Autónomas R07 + otros deploys incrementales):
  - `src/Engine/IniciativaRomantica.php` — NUEVO fichero, blob completo de F1.
  - `src/Engine/AccionRomantica.php` — parche: en bloque `flechazo_ya_registrado` se añade llamada a `IniciativaRomantica::intentarPrimeraCita` (preserva SimFunnelProbe y trazabilidad ya vivos).
  - `src/Engine/AcontecimientoDiario.php` — parche: tras `intentarAgendarQuedada` en mandar_flores/mandar_mensaje se añade llamada a iniciativa autónoma.
  - `src/Engine/MotorVidaDiaria.php` — parche: fix HORA_PASADA. Nueva método privado `siguienteFranjaFutura` (+48h máx, ventana 09-22, lugar abierto, agenda libre) y salida individual programada SIEMPRE a futuro; historial registra dia/hora de decisión + programado_dia/hora.
  - `data/configs/calibracion_vida.json` — merge semántico: solo se añade `romance_accion` a `familias_en_play` + nota FASE 1. TODO lo demás (R07 peticiones, conflicto/reparación, enfadado_ajeno, continuidad_reciente, ventana_voluntad_dias, bonus_primera_cita_reciproca, mod_social_factor...) ya estaba vivo en producción y NO se tocó.
- **FASE 2 SIGUE BLOQUEADA**: romance_hito (declaracion) y pareja (crisis/ruptura) siguen EXCLUIDAS de familias_en_play. Sin parejas autónomas. Pendiente playtest humano de Fase 1 esta noche.
- **Backup PRE:** backups/pre-romance-f1-20260825_085359/ (AccionRomantica, AcontecimientoDiario, MotorVidaDiaria, calibracion_vida). IniciativaRomantica no existía (fichero nuevo).
- **Tests OK:** fase1_iniciativa_primera_cita (30/30), fase1_primera_cita_narrativa (13/13), fase1_salida_individual_franja (11/11), peticiones_autonomas_e2e (45/45), peticiones_cadencia_r07 (13/13), rechazo_cooldown_neutro, relaciones_test, encuentros_test, senal_romantica_coherencia_temporal, hora_pasada_test, vida_pueblo_gameplay, php -l x4, JSON válido.
- **Prueba TEST (partida dev borrada):** flechazo forzado → hito+señal → iniciativa autónoma dispara → primera cita AGENDADA (intencion=autonomo_npc, lug_cafeteria, franja futura estricta d1h12 > ahora d1h11, ventana 09-22 OK, par coherente) → HORA_PASADA=0 → peticiones autónomas siguen naciendo → cooldown intacto.
- **Hashes deployados (md5):** IniciativaRomantica fd322942..., AccionRomantica 0cba28c7..., AcontecimientoDiario 723a9360..., MotorVidaDiaria 5697d0de..., calibracion_vida 8502ad78...
- **NOTA ENTORNO:** el share \\amg-arriba revirtió ficheros varias veces durante la sesión (edits perdidos). Se re-aplicó todo con script atómico idempotente (`deploy_f1_atomico.php`). Si algo vuelve a revertirse, re-ejecutar ese script + suite F1.

---

## NARRATIVA PASADA 2 — PREPARADA Y RETENIDA (2026-08-25, sesión deploy controlado)

**Estado: RETENIDO — sin deploy. Bloqueo: FTP de producción inaccesible durante toda la sesión.**

- **Fuente:** rama `task/narrativa-peticiones-rechazos`; funcional `6ad35d6b02b8d924ef1d3877351f764d7eef849f`, test `1340e56`, docs `d3e4fb4`.
- **Precheck:** intentos de descarga FTP (82.194.68.83:21) fallidos repetidamente: TCP 21/22 inalcanzable, 80 timeout, 443 TLS roto desde esta red. Web OK para usuarios (no verificable por SSL local). Precheck remoto REAL pendiente de reconexión.
- **Análisis local completado (base = working tree, linaje vivo):**
  - `PeticionPlantillas.php`: sin drift vivo → blob completo narrativa (`47640f4`) seguro.
  - `cotilleo_familias.json`: delta 1 línea (se_conocieron deja de predecir «hasta quedarán») → blob narrativa (`8798742`) seguro.
  - `PeticionPuebloEngine.php`: COLISIÓN resuelta quirúrgicamente. Vivo contiene R08 gap nacimientos (`estaEnGap`, `ultima_nace_abs`) + metadato `generacion` que la rama NO tiene. Solo se aplica hunk cableado `PeticionPlantillas::copyPara()` (5+/1−). Sustituir blob revertiría R08.
  - `CopyRechazoPropuesta.php`: COLISIÓN resuelta quirúrgicamente. Vivo contiene atribución canónica (`rechazoCanonico` en hablanteRechazo/lineaOtroDispuesto) y `mensajeCooldownPar` (usado por PropuestaEncuentroEngine desplegado; blob de rama lo ELIMINA = fatal en prod). Solo se aplican hunks CAUSAS_HUMANAS (9−/21+: emocional, relacional, banal 16→20, cansancio).
  - Fix cooldown ≠ rechazo PRESERVADO íntegro: RechazoMemoria guard agenda|cooldown, mensajeCooldownPar, CopyVoluntad UI neutra — fuera del diff.
- **Staging reconciliado verificado:** `dev\_prod_fetch_narrativa_p2_pre\staging/` + copia en `C:\Users\agl03\AppData\Local\Temp\opencode\narrp2\staging`. Hashes git: PeticionPlantillas `47640f4`, cotilleos `8798742`, PPE reconciliado `18e04c8`, CRP reconciliado `83024c3`. Diffs vs vivo = SOLO hunks narrativa. php -l OK x3, JSON válido.
- **Tests sobre blobs EXACTOS candidatos (18/18 VERDE):** narrativa_invariantes_peticiones_rechazos (38 checks), peticion_quedar_copy_variedad, rechazo_banal_copy_variedad, se_conocieron_sin_predicciones, cotilleo_se_conocieron_variedad, narrativa_variedad, rechazo_clase, rechazo_copy_coherente, rechazo_atribucion_canonica, **rechazo_cooldown_neutro (GATE: VERDE)**, peticiones_cadencia_r07, mensajitos_acciones, mensajitos_vivos, relaciones, senal_romantica_coherencia_temporal, utf8_text, content_validation. NOTA peticiones_juego_v1_funcional: flaky PREEXISTENTE documentado (baseline sin narrativa: 1 OK/1 FAIL/1 excepción JsonFile por contención SMB+RNG; con narrativa 3/3 OK). Sin rebajar assertions.
- **Script deploy listo:** `scripts/deploy_narrativa_p2.ps1` (patrón PRE/GO, marcadores anti-WIP incl. texto prohibido "hasta quedarán", verificación SHA256 4/4, sin cache-buster, sin JS/CSS).
- **Working tree restaurado byte-exact** tras el swap de pruebas (hashes verificados contra backup).
- **Reanudación:** 1) recuperar red FTP; 2) `-Phase PRE` (backup remoto + comparar hashes reales); 3) si remoto ≡ vivo esperado → `-Phase GO`; 4) PARTIDA TEST muestras reales (≥3 quedar_con_x, ≥3 banal, 1 emocional, 2 se_conocieron, reintento cooldown no registra) + borrar partida; 5) registrar SHA/hora aquí.

## ROMANCE AUTÓNOMO FASE 1 — DESPLEGADO (2026-08-25 09:30)

- **SHA fuente:** f240fd2 (rama int/romance-autonomo-fase1)
- **Reconciliación quirúrgica** contra producción actual (que ya contenía Peticiones Autónomas R07 + otros deploys incrementales):
  - `src/Engine/IniciativaRomantica.php` — NUEVO fichero, blob completo de F1.
  - `src/Engine/AccionRomantica.php` — parche: en bloque `flechazo_ya_registrado` se añade llamada a `IniciativaRomantica::intentarPrimeraCita` (preserva SimFunnelProbe y trazabilidad ya vivos).
  - `src/Engine/AcontecimientoDiario.php` — parche: tras `intentarAgendarQuedada` en mandar_flores/mandar_mensaje se añade llamada a iniciativa autónoma.
  - `src/Engine/MotorVidaDiaria.php` — parche: fix HORA_PASADA. Nueva método privado `siguienteFranjaFutura` (+48h máx, ventana 09-22, lugar abierto, agenda libre) y salida individual programada SIEMPRE a futuro; historial registra dia/hora de decisión + programado_dia/hora.
  - `data/configs/calibracion_vida.json` — merge semántico: solo se añade `romance_accion` a `familias_en_play` + nota FASE 1. TODO lo demás (R07 peticiones, conflicto/reparación, enfadado_ajeno, continuidad_reciente, ventana_voluntad_dias, bonus_primera_cita_reciproca, mod_social_factor...) ya estaba vivo en producción y NO se tocó.
- **FASE 2 SIGUE BLOQUEADA**: romance_hito (declaracion) y pareja (crisis/ruptura) siguen EXCLUIDAS de familias_en_play. Sin parejas autónomas. Pendiente playtest humano de Fase 1 esta noche.
- **Backup PRE:** backups/pre-romance-f1-20260825_085359/ (AccionRomantica, AcontecimientoDiario, MotorVidaDiaria, calibracion_vida). IniciativaRomantica no existía (fichero nuevo).
- **Tests OK:** fase1_iniciativa_primera_cita (30/30), fase1_primera_cita_narrativa (13/13), fase1_salida_individual_franja (11/11), peticiones_autonomas_e2e (45/45), peticiones_cadencia_r07 (13/13), rechazo_cooldown_neutro, relaciones_test, encuentros_test, senal_romantica_coherencia_temporal, hora_pasada_test, vida_pueblo_gameplay, php -l x4, JSON válido.
- **Prueba TEST (partida dev borrada):** flechazo forzado → hito+señal → iniciativa autónoma dispara → primera cita AGENDADA (intencion=autonomo_npc, lug_cafeteria, franja futura estricta d1h12 > ahora d1h11, ventana 09-22 OK, par coherente) → HORA_PASADA=0 → peticiones autónomas siguen naciendo → cooldown intacto.
- **Hashes deployados (md5):** IniciativaRomantica fd322942..., AccionRomantica 0cba28c7..., AcontecimientoDiario 723a9360..., MotorVidaDiaria 5697d0de..., calibracion_vida 8502ad78...
- **NOTA ENTORNO:** el share \\amg-arriba revirtió ficheros varias veces durante la sesión (edits perdidos). Se re-aplicó todo con script atómico idempotente (`deploy_f1_atomico.php`). Si algo vuelve a revertirse, re-ejecutar ese script + suite F1.

---

## ROMANCE F1 — GATE FAMILIAS PLAY CORREGIDO — PRE FASE 2A (2026-08-25, rama feat/romance-autonomo-fase2a)

**Estado: CORREGIDO EN RAMA — pendiente de integración a canónica. SHA rama: 0951557 (sobre reconciliación 7b0b91a, base 6b2ce56).**

- **Causa exacta:** el filtro `familias_en_play` de `MotorVidaDiaria::ejecutarHuecoVida` exigía además `npc_autonomy_enabled=true` (flag GLOBAL a false en features.json), mientras el tick horario vive por `encuentros_enabled=true` (RelojOperations.php:42-49). Resultado: filtro MUERTO en play y las familias `romance_hito`/`pareja` (declaracion/crisis_pareja/ruptura/reconciliacion) eran alcanzables por hueco de vida (pesos 0.04/0.08), CONTRARIANDO la nota `_nota_familias_fase1` del despliegue F1.
- **Solución mínima:** el criterio pasa a ser únicamente lab vs no-lab (`lab_vida_activa`). Nuevo helper público puro `MotorVidaDiaria::filtrarFamiliasEnPlay(items, familiasPlay, esLab)`; fuera de laboratorio SOLO entran las familias del contrato; en laboratorio catálogo completo; config ausente/vacía = legacy sin filtro. NO se activan flags globales; NO se tocan probabilidades ni pesos; fix HORA_PASADA preservado íntegro.
- **Tests nuevos:** `tests/pre_gate_familias_play_test.php` — unidad del contrato + integración PLAY determinista (45 partidas × 34 h con pipeline canónico: cero nacimientos prohibidos; romance_accion/trabajo/ocio/romance/consejo vivos y observados) + control LAB (declaracion sigue alcanzable en lab → la exclusión en play es contrato, no imposibilidad).
- **Hallazgo estructural corregido en la misma rama (commit 7b0b91a):** la base versionada era internamente inconsistente (PropuestaEncuentroEngine llamaba `CopyRechazoPropuesta::mensajeCooldownPar` inexistente → fatal; evaluador de voluntad sin bonus_primera_cita_reciproca/continuidad_reciente; SimFunnelProbe/DiarioResidenteBridge/PeticionFeedback sin versionar aunque producción los ejecuta). Se versionaron BYTES DE PRODUCCIÓN exactos como commit de reconciliación.

## ROMANCE AUTÓNOMO FASE 2A — IMPLEMENTADA / PENDIENTE DE INTEGRACIÓN (2026-08-25, misma rama)

**Estado: IMPLEMENTADA Y VERDE EN RAMA `feat/romance-autonomo-fase2a`. SHA funcional: 129efc8. SIN deploy, SIN merge a canónica, calibracion_vida.json SIN CAMBIOS.**

- **Producto:** tras una primera cita resuelta razonablemente, el par puede VOLVER A QUEDAR por iniciativa propia. No forma parejas. Historia con recorrido: flechazo → primera cita → continuidad → segundas/siguientes citas → (más adelante 2B/2C) declaración/pareja.
- **Arquitectura (reutilización, sin motor paralelo):**
  - `IniciativaRomantica::intentarSiguienteCita()` — gates sin RNG: par válido / conocidos / parentesco_veto / RomanceElegibilidad / no-pareja-crisis ENTRE ELLOS / hito PRIMERA_CITA existente / sin cita activa del par (cualquier intención) / última experiencia NO mal-muy_mal / gap mínimo cumplido / señal viva del iniciador / PropuestaCooldown tipo 'cita'. Voluntad de AMBOS con tipo `cita` y resolución canónica media geométrica √(pA·pB), UNA tirada; rechazo atribuido al de menor p vía motivoRechazoPublic + RechazoMemoria.
  - Agenda: `AgendaConjunta::primeraFranja` (3 días, 9-22, lugares desbloqueados, apertura verificada) → `EncuentroEngine::programar(tipo CANÓNICO 'cita', cuentaComoCelestine=false)` → `intencion='autonomo_npc'`.
  - Disparo: al RESOLVERSE una cita romántica (primera_cita o cita), `EncuentroResolver::aplicarResultado` registra MARCADOR de continuidad idempotente por par con `desde_abs = fin_última_cita + gap`. El consumidor `IniciativaRomantica::procesarContinuidad()` corre al final de `MotorVidaDiaria::tickHora` (post-casuales): consume cada marcador UNA vez, inicia quien más romance siente (empate → orden canónico). NUNCA crea la cita en el tick de la resolución anterior. Sin contadores artificiales: el historial se deriva de encuentros terminados reales.
  - **Anti-aceleración:** REUTILIZA `cooldowns.por_familia.romance = 48h` como gap mínimo entre citas románticas resueltas del par (`IniciativaRomantica::gapMinimoCitas`). No se introdujo ninguna cifra nueva.
  - **Freno natural:** experiencia mal/muy_mal del último encuentro BLOQUEA la continuidad (`gate_continuidad_ultima_experiencia_mala`, coherente con `continuidad_reciente.corte_si_ultimo_malo`). Continuidad buena suma vía `voluntad.continuidad_reciente` ya existente.
  - **Celestine:** nuevo parámetro opcional `cuentaComoCelestine` en `EncuentroEngine::validarContexto/programar` (default true, retrocompatible): la cita autónoma no consume ni depende del cupo diario de Celestine; `MisionDiariaEngine::esEncuentroCelestine=false`.
- **Narrativa:** reutiliza copy canónico existente para citas (EncuentroCotilleoCopy «han tenido una cita…» vía ENCUENTRO_TERMINADO) — sin UI nueva, sin tocar play.php/play-v3.js/CSS/Design System.
- **Tests:** `tests/fase2_continuidad_citas_test.php` — cobertura A-T completa (gates B,C,D,E,F,G,H,I,J; antiduplicación J/K/L/M; nunca-pareja N; cupo O; HORA_PASADA P; RNG exacto T=4 pasos LCG en éxito y 0 en gates puros). Regresión verde: 19 suites incluidas fase1 ×3, balance P2, media geométrica, cooldown neutro, señales, relaciones, encuentros, solapes, disponibilidad, peticiones R07/e2e, debug parejas. php -l ×4 + JSON calibración válido.
- **Fallos PREEXISTENTES demostrados A/B (no tocados, heredados de producción):** `vida_relaciones_test` (2 fails: hora resultante ≠11, copy_id preparado y vacío) y `playtest_voluntad_tipo_test` (1 fail: primera cita vs bonus conocerse) fallan IDÉNTICAMENTE en el árbol canónico.

### Pendiente tras 2A
- **FASE 2B = declaración autónoma** con historial compartido (≥N citas derivadas de encuentros reales), banda romance mínima, reciprocidad (señal ambas direcciones), ventana temporal desde primera cita, unificación de resolución geométrica con el handler actual.
- **FASE 2C = formación de pareja + exclusividad.**
- **FASE 3 = crisis/ruptura/reconciliación** (motor y handlers YA existen; falta disparador con sentido y probabilidades hoy huérfanas).

### BUG BLOQUEANTE DE 2C (registrado, NO arreglar dentro de 2A)
`ParejaEngine::formar()` (src/Engine/ParejaEngine.php:20-70) solo veta parentesco y exige ambos-sí: NO comprueba si `a` o `b` ya tienen `estado_pareja ∈ {pareja,crisis}` con un TERCERO → el motor permite hoy DOS parejas simultáneas para una misma persona vía dos declaraciones. Además `TerceroRomantico::parejaDe()` devuelve solo la primera relación activa (ambigüedad con poli-parejas). Requiere gate de exclusividad + política de rebote antes de activar 2C.

---

## RECONCILIACIÓN ROMANCE PRODUCCIÓN 2026-08-25 — F1 + A1 + PRE + F2A RESTAURADAS SOBRE PRODUCCIÓN REAL

**Estado: DESPLEGADO EN PRODUCCIÓN Y VERIFICADO (7/7 hashes byte-idénticos). Rama `int/recon-romance-f1-a1-pre-f2a-20260825`.**

**Causa del incidente:** el deploy selectivo de F2A de la sesión anterior (~15:04-15:28) nunca llegó a persistir en
`/juegos/aqui-hay-tema` (sin POST-fetch que lo acreditara). Las olas concurrentes posteriores (rechazo-individual,
latidos-l1, f4-paso-2, r08, narrativa-p2, loquesabes, p0-entender) desplegaron cada una desde su propia base y
dejaron producción en un linaje mixto SIN F2A, SIN IniciativaRomantica.php, SIN IniciativaSocial.php y con los
ficheros de romance en blobs pre-F1 (`int/canonical-20260824-consolidada`): regresión total del subsistema romance.

**Método (sin sustituciones a ciegas):** snapshot remoto completo PRE (`pre_20260825_184830` + espejo recursivo
517 ficheros) → hunks semánticos F1/A1/PRE/F2A reconstruidos SOBRE los blobs remotos actuales → staging único
espejo de producción → gates baseline-vs-candidato → backup PRE remoto → upload atómico único → FETCH POST 7/7.

- **F1 restaurado:** `IniciativaRomantica.php` (recreado, blob validado 45b33227 con glue `opts=['intencion'=>'autonomo_npc']`);
  hook 1-línea en `AccionRomantica` (bloque flechazo_ya_registrado); hook 1-línea tras `intentarAgendarQuedada` en
  `AcontecimientoDiario`; fix HORA_PASADA `siguienteFranjaFutura` (+programado_dia/hora) en `MotorVidaDiaria`;
  calibración: `romance_accion` en familias_en_play + `_nota_familias_fase1`.
- **A1 restaurada:** `IniciativaSocial.php` (blob validado sin telemetría SimFunnelProbe), hook tick en MVD
  (`$out['iniciativa_social']`), config `/iniciativa_social` + cooldown por par 72h.
- **PRE gate:** `filtrarFamiliasEnPlay(items, familiasPlay, esLab)` público en MVD; fuera de LAB solo contrato;
  romance_hito/pareja siguen excluidos; flags técnicos dejan de condicionar.
- **F2A:** marcador post-cita en `EncuentroResolver` (tras MemoriaEventos::registrar); consumidor
  `procesarContinuidad` como ÚLTIMO hook de `tickHora`; gap = cooldowns.por_familia.romance 48h;
  cupo Celestine cubierto nativamente por intencion≠celeste_organizado (mecanismo F4 ya presente).
- **Preservado sin tocar:** EncuentroEngine (F4), RelojOperations, CoincidenciasEngine, EncuentroLifecycle,
  EventosComunitario/*, LatidosProgresionEngine, DomainBootstrap/PartidaService (latidos), RechazoMemoria +
  PropuestaEncuentroEngine (rechazo-individual), CopyRechazoPropuesta (P2), peticiones R07/R08, features.json,
  buster p0. Variante `max(0,...)` del peso social del remoto se conserva. Telemetría SimFunnelProbe NO restaurada
  (su clase ya no existe en remoto; impacto conductual cero). `/conflicto` de calibración no restaurado (sin lector).
- **Orden final de hooks en MotorVidaDiaria::tickHora:** hueco_vida (con PRE gate) → salida individual (fix F1)
  → casuales → **iniciativa_social (A1)** → **continuidad romántica (F2A)** → persistencia RNG. Sin dobles ejecuciones.

**Gates (staging espejo de remoto, baseline vs candidato):**
- VERDE NUEVO: fase1_iniciativa_primera_cita 30/30 · fase1_primera_cita_narrativa 13/13 ·
  fase1_salida_individual_franja 11/11 · primera_cita_balance_p2 23/23 · senal_romantica_coherencia_temporal 8/8 ·
  pre_gate_familias_play 10/10 · **fase2_continuidad_citas 51/51 (A–T)** · **iniciativa_social_autonoma 59/59** ·
  voluntad_media_geometrica 7/7.
- IDÉNTICO AL BASELINE (preexistentes, no de esta pieza): rechazo_cooldown_neutro 48/5F · peticiones_pueblo 47/1F ·
  vida_relaciones 75/2F (documentado) · playtest_voluntad_tipo 12/1F (documentado) · eventos_comunitarios_core/ciclo
  fatal idéntico (evaluador de lab no desplegado, dominio F4).
- REVERTIDO A ESTADO DOCUMENTADO: encuentro_resultado_play 38/1F «conocerse sin conflicto» — fallo preexistente
  documentado de la era F1+A1; el baseline regresado lo pasaba solo porque A1 no corría.

**E2E en producción real (API HTTP, juego_v1):** pid `part_adc2ae26d95a832a` (seed TEST_rr2_194306_4, ELIMINADA):
primera cita autónoma programada y resuelta `bien` → **gap 49h ≥ 48h** → segunda cita tipo=cita autónoma resuelta
muy_bien → tercera cita programada SIN formación de pareja · anti-dup (nunca >1 cita activa del par) ·
cupo Celestine intacto · F4 flag OFF sin interferencias · 0 HORA_PASADA · partida.refresh OK (R08).
Freno natural demostrado aparte: experiencia mal/muy_mal ⇒ continuidad bloqueada. A1 viva aparte:
pid `part_5e8535a3bdf62498` (ELIMINADA): iniciativa_social_log con intento autónomo y voluntad real (p≈0.49).
Limpieza: 7 partidas TEST + sus JSONL borradas por ruta literal individual (14/14, sin wildcards).
Backup PRE remoto: `/juegos/_backup_pre_recon_romance_20260825_192900` (+manifiesto).

