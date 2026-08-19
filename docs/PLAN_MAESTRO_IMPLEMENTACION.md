# Plan Maestro de Implementación — Aquí Hay Tema

**Versión:** 2026-08-19 (decisiones post-424fe940: desconocidos, discovery, vida diaria)

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


