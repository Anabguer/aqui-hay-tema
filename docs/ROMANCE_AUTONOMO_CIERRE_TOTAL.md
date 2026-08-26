# ROMANCE AUTÓNOMO — CIERRE TOTAL (PLAN)

**Estado de este documento:** PLAN (primera pasada). NO se ha implementado nada todavía.
Tras la aprobación de Neni/ChatGPT («PLAN APROBADO. EJECUTA EL CIERRE TOTAL.»), este mismo
documento se convertirá en el **documento de cierre**: registraré en él arquitectura final,
decisiones, SHAs, tests, simulaciones, calibración, deploys, hashes y estado real de producción.

**Base de auditoría:** producción real post-reconciliación `abb1276`
(`int/recon-romance-f1-a1-pre-f2a-20260825`, deploy atómico 2026-08-25, backup remoto
`/juegos/_backup_pre_recon_romance_20260825_192900`). NO se usa ninguna base histórica sin reconciliar.

---

## 0. ÍNDICE

1. [Auditoría real (FASE 0)](#1-auditoría-real-fase-0)
2. [Qué está terminado](#2-qué-está-terminado)
3. [Qué falta](#3-qué-falta)
4. [Bugs bloqueantes](#4-bugs-bloqueantes)
5. [Plan F2B — Declaración autónoma](#5-plan-f2b--declaración-autónoma)
6. [Plan F2C — Formar pareja + exclusividad](#6-plan-f2c--formar-pareja--exclusividad)
7. [Plan vida autónoma de pareja](#7-plan-vida-autónoma-de-pareja)
8. [Plan crisis](#8-plan-crisis)
9. [Plan reparación / reconciliación](#9-plan-reparación--reconciliación)
10. [Plan ruptura](#10-plan-ruptura)
11. [Post-ruptura y vuelta](#11-post-ruptura-y-vuelta)
12. [Narrativa](#12-narrativa)
13. [Cadencia global / anti-spam](#13-cadencia-global--anti-spam)
14. [Interferencias](#14-interferencias)
15. [Feature gates](#15-feature-gates)
16. [Matriz de tests](#16-matriz-de-tests)
17. [Simulaciones de balance](#17-simulaciones-de-balance)
18. [Bloques de implementación R1–R8](#18-bloques-de-implementación-r1r8)
19. [Estrategia de integración / deploy](#19-estrategia-de-integración--deploy)
20. [Registro de ejecución (se rellena al implementar)](#20-registro-de-ejecución)

---

## 1. AUDITORÍA REAL (FASE 0)

READ-ONLY realizada hoy sobre producción reconciliada. Clasificación por pieza:

### 1.1 Ya implementado y VIVO en producción

| Pieza | Fichero(s) | Evidencia |
|---|---|---|
| Flechazo hito único (delta +28, anti-duplicado) | `AccionRomantica.php`, `RelacionBitacora::FLECHAZO` | guard `tienenHito` + `flechazos[]` direccionales |
| Señal romántica (gate único): flechazo O romance ≥ tilín (8), unilateral | `SenalRomantica.php` | `desdeHacia()`, aviso 1 vez/dirección (`avisos_senal`), evento `SENAL_ROMANTICA` |
| F1 · Primera cita autónoma | `IniciativaRomantica::intentarPrimeraCita` | gates sin RNG (conocerse, parentesco, elegibilidad edad, no pareja/crisis, señal, idempotencia hito+encuentro activo, `PropuestaCooldown`, `MemoriaEventos` familia `romance_accion`) + voluntad AMBOS + media geométrica √(pA·pB) con UNA tirada (canon 20/08) + `RechazoMemoria` + `AgendaConjunta::primeraFranja` (agenda/sueño/trabajo/doble reserva/lugar abierto) + tipo canónico `primera_cita`, `intencion=autonomo_npc` |
| F2A · Continuidad: segundas/siguientes citas | `IniciativaRomantica` (marcadores `continuidad_romantica` + `intentarSiguienteCita` + `procesarContinuidad`), hook en `MotorVidaDiaria::tickHora` (último hook del tick) y `EncuentroResolver::aplicarResultado` (registro post-resolución) | gap mínimo REUTILIZA `cooldowns.por_familia.romance`=48h; nunca crea cita en el tick de resolución; corte por experiencia mal/muy_mal; tipo `cita` SIN cupo Celestine (`programar(..., false)`); anti-duplicado por consumo único de marcador; nunca forma pareja |
| Motor de estados de pareja (hitos explícitos) | `ParejaEngine.php`: `ninguna|pareja|crisis|ex`, `formar/crisis/romper/reconciliar/estado` | `nunca_auto_por_umbral` verificado por `PostGateIntegralRunner`; estabilidad compartida (`estabilidad_inicial`=55, `base_reconciliacion`=32), `fecha_inicio`, `historial_parejas` (con `vuelta` bool), memoria de estabilidad al romper |
| Bitácora de hitos consultable | `RelacionBitacora.php` | tipos `SE_CONOCIERON, PRIMERA_CITA, FLECHAZO, DECLARACION, INICIO_PAREJA, CRISIS, RECONCILIACION, RUPTURA, VUELTA, DISCUSION_FUERTE, APOYO_IMPORTANTE, RECHAZO_IMPORTANTE…`; puente a Cotilleo y Diario |
| Rechazos con memoria | `RechazoMemoria.php` | banal≈0 consecuencias; repetidos (umbral 3) erosionan romance del rechazado (−3), tristeza dirigida, toca estabilidad si son pareja, `RECHAZO_IMPORTANTE`; guard agenda/cooldown (no es rechazo real); ventana 30 días en voluntad |
| Voluntad ponderada | `Voluntad\VoluntadPonderadaEvaluator.php` | score 0–100 → p∈[0.08,0.94] (excelente 0.92); mod social ×0.28, romance ×0.18, conflicto ×3 en citas, emocional vigente con dirección de enfado, consejos, afinidad lugar, bonus recíproca primera cita (+12), continuidad reciente con decay |
| Desgaste de pareja | `RelacionDesgaste::aplicarPareja` (hook `RelojOperations` al cerrar día) | `desgaste_pareja.activo=true`: −2 estabilidad/día tras 2 días sin NADA |
| Estabilidad por encuentros | `EncuentroResolver::aplicarResultado` | siendo PAREJA/CRISIS: promedio social ≥4 → +2; ≤−3 → −3 |
| Canal conflicto con decaimiento y reparación | `RelacionEngine::upsertConflicto`, config `conflicto` (7+7 días, −1 nivel por encuentro bien) | activo en producción |
| Terceros (freno flechazo casual) | `TerceroRomantico.php` consumido SOLO en `InteraccionCasual.php:187` | 0.08 pareja feliz / 0.35 crisis |
| Citas de pareja vía Celestine | `PropuestaNivel::tiposPermitidos/tipoPara` | PAREJA/CRISIS → `[quedar, cita]` (ya vive) |
| Etiquetas ficha/vista | `EtiquetaRelacionPlay::romanceHacia` | Pareja ❤️ / En crisis 💔 / Ex pareja 💔 / Me gusta 💘 |
| Ejecutor de hitos lab-only | `AcontecimientoDiario.php`: `declaracion`, `crisis_pareja`, `ruptura`, `reconciliacion` | funciona en laboratorio; en play lo bloquea `familias_en_play` |
| Gate familias_en_play | `MotorVidaDiaria::filtrarFamiliasEnPlay` + `familias_en_play=[trabajo,ocio,romance,consejo,romance_accion]` | `romance_hito` y `pareja` EXCLUIDOS del hueco de vida en play; bypass solo `lab_vida_activa`; cubierto por `pre_gate_familias_play_test` |
| Debug de parejas | `DebugParejasEngine.php` | crea 2 parejas de prueba (una en crisis) con rollback; scanner `residentesEnParejaActiva()` |

### 1.2 Implementado pero DESCONECTADO (lab-only, muerto en play)

- `declaracion` / `crisis_pareja` / `ruptura` / `reconciliacion` en `AcontecimientoDiario::ejecutar`:
  solo alcanzan play si se añaden sus familias a `familias_en_play`. Hoy imposible por diseño (PRE gate validado).
- Hitos `DISCUSION_FUERTE` y `APOYO_IMPORTANTE` de `RelacionBitacora`: definidos, con cuerpo de diario
  el primero, **sin productor alguno** (nadie los emite). Candidatos naturales para crisis/reparación.
- Claves de calibración **MUERTAS** (nadie las lee en src/, verificado por búsqueda global;
  `PostGateIntegralRunner.php:432-438` afirma explícitamente que NO se consumen):
  `crisis.probabilidad` (0.015), `crisis.bonus_si_estabilidad_baja` (0.04), `ruptura.probabilidad` (0.01),
  `crisis.nunca_auto_por_umbral`, `ruptura.nunca_auto_por_umbral`, `pareja.nunca_auto_por_umbral`.
  → Fueron diseñadas para este cierre y están reservadas; el plan las REVIVE con gates causales (§8/§10).

### 1.3 Código huérfano

- Sin huérfanos relevantes dentro del dominio romance. (`data/eventos/eventos_pareja.json` etc. son
  huérfanos de OTRO bloque — eventos del pueblo EV2, fuera de alcance aquí.)

### 1.4 Deuda real (no bloqueante, se decide en este plan)

| # | Deuda | Decisión en este plan |
|---|---|---|
| D1 | `intentarPrimeraCita` programa con `cuentaComoCelestine=true` (F1) mientras F2A usa `false`; `RELACIONES_Y_CITAS.md` dice «las citas autónomas NPC no gastan el tope» | **Corregir a `false`** en R1 (hunk 1 línea) + test. Impacto real bajo: el límite solo aplica si `intervenciones_organizadas_max_dia` está fijado |
| D2 | `declaracion` (lab) evalúa voluntad tipo legacy `'romantico'` (`mod_tipo` inexistente→0, sin bonus recíproca, sin conflicto_mult) y resuelve con dos tiradas individuales, no media geométrica | F2B NO reutiliza esa ruta tal cual: nueva iniciativa dedicada coherente con F1/F2A (§5). El item de catálogo queda para lab |
| D3 | `SenalRomantica::desbloqueaPrimeraCita`, `avisarSiAplica` e `intentarPrimeraCita/SiguienteCita` miran SOLO el estado del PAR, no si A/B está en pareja con un TERCERO | Gates nuevos en R1 (necesarios ANTES de que existan parejas autónomas) |
| D4 | Sin política sobre citas ya agendadas cuando nace/rompe una pareja | Contrato explícito §6.6/§10.6 |
| D5 | `rechazo_cooldown_neutro_test` desfasado frente al copy-cooldown vivo (deuda ya registrada post-fix individual) | Se actualiza en su bloque si toca ficheros comunes; si no, queda fuera de alcance documentada |

### 1.5 Bug bloqueante (el conocido, CONFIRMADO hoy en código)

**BUG-1 (exclusividad).** `ParejaEngine::formar()` NO comprueba si `$a` o `$b` ya están en
`estado_pareja ∈ {pareja, crisis}` con una tercera persona. Hoy no explota porque en play no hay
ruta autónoma hacia `INICIO_PAREJA` y Debug/Lab eligen vecinos libres; pero al activar F2C sería
posible A–B pareja y A–C pareja simultáneas. **R1 lo cierra antes de cualquier activación.**

---

## 2. QUÉ ESTÁ TERMINADO

El arco hasta «segunda/siguientes citas sin formar pareja» está CERRADO, DESPLEGADO y VALIDADO
en producción real (recon abb1276):

```
se conocen → señales (flechazo/tilín) → iniciativa → primera cita autónoma
→ experiencia → gap 48h → segunda/siguientes citas → voluntad geométrica
→ anti-duplicado → posibilidad de tercera cita → SIN pareja automática
```

Además, TODO EL SISTEMA NARRATIVO y DE ESTADO posterior ya existe y está probado:
bitácora, cotilleos (`pareja`, `drama`, `ruptura`, `romance_mutuo`), diario (cuerpos de
inicio_pareja, vuelta, reconciliación, crisis, discusión fuerte, ruptura y declaración
rechazada), etiquetas de ficha (Pareja/En crisis/Ex), estabilidad, desgaste, historial de parejas.

---

## 3. QUÉ FALTA (alcance del cierre total)

1. **Exclusividad global real** (BUG-1) + gates de tercero (D3) — prerequisito de todo.
2. **F2B Declaración autónoma**: quién, cuándo, con qué evidencia, resolución, memoria.
3. **F2C Formación autónoma de pareja** tras declaración aceptada.
4. **Vida de pareja autónoma**: citas de pareja autónomas, mejora/deterioro continuados.
5. **Crisis causal** (no RNG desnudo).
6. **Reparación** de crisis (puede funcionar, puede fallar, deja memoria).
7. **Ruptura** con autoría, memoria y consecuencias.
8. **Post-ruptura + posible vuelta** con contrato (cooldown, memoria, caps).
9. **Activación por gates** escalonada y reversible, sin tocar `familias_en_play` para la ruta autónoma.
10. **Matriz de tests + simulaciones de balance + integración quirúrgica + documente de cierre.**

---

## 4. BUGS BLOQUEANTES

| ID | Descripción | Bloquea | Solución |
|---|---|---|---|
| BUG-1 | `ParejaEngine::formar()` permite parejas simultáneas de la misma persona | F2C y todo lo posterior | R1: guard de exclusividad centralizado (ver §6.1) |
| BUG-2 (latente) | Ningún gate de iniciativa/cita comprueba pareja con tercero (`D3`) | F2C | R1: `en_pareja_con_otro` en `desbloqueaPrimeraCita`, `avisarSiAplica`, `intentarPrimeraCita`, `intentarSiguienteCita` |
| INC-1 | Primera cita autónoma consume cupo Celestine (contradicción con canon y con F2A) | Coherencia F10 | R1: `cuentaComoCelestine=false` + test (DECISIÓN visible para Neni/ChatGPT) |

No hay más bugs bloqueantes detectados para este alcance. Los hallazgos del embudo histórico
(HORA_PASADA, quedadas casuales, etc.) ya están cerrados por F1/F2A/A1 según el registro del Maestro.

---

## 5. PLAN F2B — DECLARACIÓN AUTÓNOMA

**Principio rector:** la declaración NO es inevitable y NO es un premio automático por acumular
citas: es una DECISIÓN de un residente, con evidencia relacional detrás, evaluada con la misma
voluntad geométrica canónica que citas.

### 5.1 Disparador (sin RNG en la elegibilidad)

Extensión del sistema de continuidad F2A YA VIVO (mismo consumidor `procesarContinuidad`,
mismo patrón de marcador fechado consumido en tick posterior). Tras resolver una cita
(`primera_cita` o `cita`) de un par NO emparejado y elegible, además del marcador de siguiente
cita se evalúa un **marcador de declaración** `declaracion_romantica` con estas condiciones TODAS:

| Condición | Fuente (evidencia, no cifra inventada) |
|---|---|
| Mínimo de citas reales | Hito `PRIMERA_CITA` + ≥ `declaracion.citas_minimas` citas ROMÁNTICAS RESUELTAS del par (derivadas de encuentros reales, ÚNICA fuente de verdad — mismo método que `ultimaCitaResuelta()`). Default **2** (primera cita + 1 continuidad exitosa): demuestra interés sostenido más allá del flechazo. Knob `_provisional`, se calibra en §17 |
| Ventana temporal | La más reciente de esas citas dentro de `declaracion.ventana_horas` (default **336h**, el MISMO valor canónico de `cooldowns.por_familia.romance_hito`). Sin cita reciente no hay declaración |
| Calidad de la última cita | Última experiencia del par ≥ `bien` (corte `mal/muy_mal` ya canónico en F2A) |
| Banda romántica mínima | Romance ≥ `romance.cortes.interes` (**22**, knob EXISTENTE) en AL MENOS la dirección del declarante; banda `pillado`(45)/flechazo suben probabilidad vía voluntad, no via gate |
| Señal romántica viva | `SenalRomantica::desdeHacia` OK en la dirección del declarante (obligatorio) |
| Reciprocidad necesaria | Señal OK también en la dirección inversa (misma regla que `bonus_primera_cita_reciproca` y que el cotilleo `romance_mutuo` ya usan). Sin reciprocidad NO hay declaración autónoma |
| Nadie en pareja con tercero | Gate nuevo R1 (`en_pareja_con_otro`) |
| Cooldowns | `MemoriaEventos` familia `romance_hito` (336h) + `PropuestaCooldown` tipo `declaracion` + sin crisis/ex abierta del par |

### 5.2 Quién toma iniciativa

El de MAYOR `romanceHacia` del par (empate → orden canónico del par). Es el MISMO criterio
ya vivo en `procesarContinuidad` para elegir quién propone la siguiente cita. Cero lógica nueva.

### 5.3 Resolución (aceptación / rechazo)

- Voluntad de AMBOS con tipo NUEVO `'declaracion'` (alias limpio en `PropuestaNivel`):
  - `voluntad.mod_tipo.declaracion` = **−10** (default provisional): declararse cuesta; el
    romance/social/emocional ya aportan lo suyo. Conflicto pesa ×3 como en citas (`conflicto_mult_declaracion=3`).
  - Bonus recíproca aplicable si señal mutua (reutiliza patrón `bonus_primera_cita_reciproca`).
- **p_plan = √(pA·pB), UNA tirada** (canon 20/08, idéntico a F1/F2A). Atribución del rechazo al de menor p.
- Rechazo duro individual pre-tirada (clase cooldown ≠ rechazo; motivo emocional/relacional/banal)
  → mismo tratamiento canónico de F1/F2A.
- **Aceptada** → `ParejaEngine::formar(a, b, true, true, RelacionBitacora::DECLARACION)` (R3).
- **Rechazada** →
  - `RechazoMemoria::registrar(quienRechaza, otro, motivoReal, cal, 'declaracion')` → erosión −3,
    tristeza si procedente, `RECHAZO_IMPORTANTE` si acumula (todo ya vivo);
  - `PropuestaCooldown::marcar(..., 'declaracion')` (6h anti-spam inmediato, knob existente);
  - `MemoriaEventos::registrar(familia='romance_hito')` → **336h hasta que el par pueda volver a
    ser declarable** (cooldown canónico ya existente, no inventamos cifra);
  - Diario: cuerpo `CUERPOS_DECLARACION_RECHAZADA` (YA EXISTE en `DiarioResidenteBridge`);
  - Sin cotilleo `romance_mutuo` (ese puente solo salta en hitos, y no registramos hito DECLARACION al fallar);
  - `iniciativa_romantica_log` con resultado trazable (`declaracion_agendada` /
    `declaracion_rechazada_<motivo>` / `gate_*`), mismo patrón LOG_MAX=500 de F1.

### 5.4 Tras un rechazo

La relación NO muere: las citas de continuidad pueden seguir (gates F2A intactos), pero:
- nueva declaración bloqueada 336h (`romance_hito`);
- cada rechazo previo suma `mod_rechazos` en voluntad (−6×(n−1), cap −20, ya vivo) → cada
  nuevo intento es MENOS probable;
- si el rechazo fue `emocional`/`relacional`, la erosión adicional puede bajar la banda por
  debajo de `interes` → la señal deja de ser recíproca → el par sale naturalmente del carril declarable.

### 5.5 No-inevitabilidad (resumen)

Gate duro multi-condición (citas mínimas + ventana + calidad + banda + reciprocidad) +
voluntad geométrica de ambos con p_max<1 + cooldown 336h + erosión por rechazos acumulados.
Ninguna ruta automática por umbral (canon `nunca_auto_por_umbral` respetado: la declaración
es iniciativa+decisión, no barra).

---

## 6. PLAN F2C — FORMAR PAREJA + EXCLUSIVIDAD

### 6.1 Exclusividad global (solución BUG-1)

Fuente única de verdad: `relaciones_romanticas[].estado_pareja ∈ {pareja, crisis}`.
Nuevo método central:

```php
ParejaEngine::parejaActivaDe(array $partida, string $id): ?string
// devuelve el ID de la pareja activa de $id, o null (escaneo idéntico a
// TerceroRomantico::parejaDe y DebugParejasEngine::residentesEnParejaActiva,
// que pasan a DELEGAR en este método — se deduplica, no se duplica).
```

Guard en `ParejaEngine::formar()` ANTES de cualquier mutación:
```
si parejaActivaDe(a) ∉ {null, b}  ó  parejaActivaDe(b) ∉ {null, a}  → error 'en_pareja_con_otro'
```
- Permite explícitamente: primera formación (ambos null), reconciliación/vuelta del MISMO par
  (`reconciliar()` ya exige estado `ex` o hito RUPTURA).
- Prohíbe: A–B pareja + A–C pareja, en CUALQUIER orden y por CUALQUIER vía
  (autónoma, Celestine, debug, lab, API). **Imposible por construcción**, no por convención de callers.

### 6.2 Gates de tercero (BUG-2)

`en_pareja_con_otro` añadido a: `SenalRomantica::desbloqueaPrimeraCita`,
`SenalRomantica::avisarSiAplica`, `IniciativaRomantica::intentarPrimeraCita`,
`IniciativaRomantica::intentarSiguienteCita` y al evaluador del marcador de declaración.
Resultado: nadie genera INICIATIVAS románticas nuevas hacia/alguien que está en pareja con otro.
(`TerceroRomantico::multiplicador` sigue vivo para el flechazo casual: raro, nunca imposible — canon.)

### 6.3 Contrato de formación (declaración aceptada → pareja)

| Paso | Mecanismo | Estado |
|---|---|---|
| Persistencia | `estado_pareja='pareja'`, `estabilidad_pareja{activa:true,valor:55}`, `fecha_inicio`, push a `historial_parejas` | YA IMPLEMENTADO en `formar()` |
| Bitácora | hito `INICIO_PAREJA` | YA IMPLEMENTADO |
| Cotilleo | `RelacionNarrativaBridge` → familia `pareja` («Oficial: …») | YA IMPLEMENTADO |
| Diario | `CUERPOS_INICIO_PAREJA` | YA IMPLEMENTADO |
| Estado en ficha | `EtiquetaRelacionPlay` → «Pareja ❤️» | YA IMPLEMENTADO |
| Memoria/cooldown | `MemoriaEventos` familia `pareja` (36h) registrado por la iniciativa | nuevo en R3 (1 línea) |
| Vida del pueblo | `VidaPuebloEngine::aplicarAcontecimientoVida` solo aplica vía AcontecimientoDiario; la ruta autónoma NO toca Vida (coherente con F2A: romance autónomo ≠ intervención) | decisión documentada |

### 6.4 Efecto sobre señales anteriores

NO se borra nada. `romance_a_hacia_b/b_hacia_a`, `flechazos[]` y `avisos_senal` persisten
(historial real). Las etiquetas de ficha pasan a mostrar Pareja (prioridad sobre banda, ya vivo
en `EtiquetaRomanceHacia`). El cotilleo `romance_mutuo` no se re-emite (hitos idempotentes).

### 6.5 Interacciones con citas futuras

- Entre la pareja: `PropuestaNivel::tiposPermitidos` ya devuelve `[quedar, cita]` (Celestine).
  La autonomía de citas de pareja es R4 (§7).
- `intentarSiguienteCita`/`intentarPrimeraCita` del par quedan cortados por `ya_pareja_o_crisis` (ya vivo).
- Marcadores de continuidad pendientes del par al formarse: se CONSUMEN y descartan
  (el gate `ya_pareja_o_crisis` los neutraliza; limpieza explícita en R3 para no dejar basura).

### 6.6 Citas pendientes con TERCEROS al formarse la pareja

Política (mundo continuo, sin cirugía retroactiva):
- Encuentros YA agendados con terceros SE RESUELVEN normalmente (son planes hechos).
- NO se generan iniciativas románticas nuevas hacia terceros (§6.2).
- Si de esa cita residual sale flechazo casual con tercero, el freno `terceros.freno_pareja_feliz=0.08`
  (vivo) lo hace rarísimo, y aunque ocurriera, la exclusividad de §6.1 impide segunda pareja.
- Quedadas amistosas con terceros: intactas (la pareja no aísla socialmente por decreto).

---

## 7. PLAN VIDA AUTÓNOMA DE PAREJA

**No se crea ningún motor nuevo.** Se reutiliza la infraestructura viva:

| Necesidad | Infraestructura YA EXISTENTE | Lo que falta |
|---|---|---|
| Seguir haciendo cosas juntos | Encuentros tipo `cita` (canónico), AgendaConjunta, lugares+aforo, casuales, salidas | Que la pareja genere citas autónomas: extender `registrarContinuidadPostCita` para que un par PAREJA también deje marcador (gap = `cooldowns.por_familia.pareja`=36h, knob existente) y que `procesarContinuidad` lo consuma programando tipo `cita` con `intencion=autonomo_npc` sin cupo Celestine |
| Mejorar | Deltas ±estabilidad por encuentro (+2/−3, vivo), canal conflicto se repara con encuentros bien (vivo), `continuidad_reciente` en voluntad (vivo) | Nada |
| Deteriorarse | `desgaste_pareja` −2/día sin contacto (vivo), conflicto sube con malos encuentros (vivo), voluntad penalizada por conflicto/enfado (vivo) | Nada |
| Entrar en crisis | Estados `CRISIS` + bitácora + narrativa (vivos) | Evaluador causal R5 (§8) |

Reglas de la rama de pareja en `procesarContinuidad`:
- `PAREJA` → marcador normal (gap 36h); la cita de pareja usa los mismos pesos de experiencia;
- `CRISIS` → NO se generan citas recreativas automáticas; solo el carril de reparación (§9);
- `EX` → sin citas autónomas (solo vuelta, §11).

---

## 8. PLAN CRISIS

**Canon inviolable:** `crisis.nunca_auto_por_umbral=true`. Nunca «estabilidad < X → crisis».

### 8.1 Modelo de causalidad (elegibilidad determinista + tirada de timing)

Nuevo evaluador `IniciativaPareja::evaluarCrisisDelDia(partida, cal)` enganchado en
`RelojOperations` al cerrar día, JUNTO a `RelacionDesgaste::alCerrarDia` (mismo punto de
integración, mismo coste O(parejas activas)). Para cada par `PAREJA`:

**CAUSAS (todas observables, cada una con memoria real):**
| Causa | Umbral (knob nuevo, default _provisional) | Fuente de datos |
|---|---|---|
| C1 Conflicto alto | `conflicto.intensidad ≥ crisis.conflicto_min (6)` | canal conflicto |
| C2 Racha mala | ≥ `crisis.racha_mala (2)` resultados mal/muy_mal entre los últimos 3 encuentros del par | `memoria_eventos` familia `encuentro` |
| C3 Estabilidad en suelo | `estabilidad ≤ crisis.estabilidad_riesgo (30)` — LLEGÓ ahí por desgaste/malos registrados, no se decreta | `estabilidad_pareja.valor` |
| C4 Abandono | ≥ `desgaste_pareja.dias_sin_interaccion_nada × 3 (6 días)` sin ningún contacto | `ultimo_contacto` social del par |

**Regla de disparo:**
- causas activas `< crisis.causas_minimas (2)` → p = **0 exacto**. Sin causas NO hay crisis jamás.
- causas ≥ 2 → tirada ÚNICA con `p = crisis.probabilidad (0.015, clave MUERTA que se REVIVE)`
  `+ crisis.bonus_si_estabilidad_baja (0.04, clave muerta que se REVIVE)` si C3 activa.
  La tirada decide CUÁNDO llega la crisis dentro del periodo de riesgo; LAS CAUSAS deciden SI hay riesgo.
- Anti-spam: cooldown `crisis` 48h (canon `por_familia.crisis`), máximo 1 crisis abierta por par
  (el propio estado lo impide), y `crisis.max_por_par_mes (2, knob nuevo)`.

### 8.2 Efectos de la crisis

`ParejaEngine::crisis()` (vivo) + añadidos:
- hito `DISCUSION_FUERTE` como FLAVOR cuando la causa dominante sea C1/C2 (hito ya definido, con
  cuerpo de diario ya escrito; le da narrativa comprensible al «por qué»);
- tristeza a ambos (`EmotionalStateService`, duración `emociones_v1.duracion_horas_default.triste`);
- cotilleo familia `drama` + diario `CUERPOS_CRISIS` (vivos);
- etiqueta «En crisis 💔» (viva); freno de terceros baja a 0.35 (vivo);
- voluntad de planes juntos cae por conflicto/emocional (vivo);
- contador `crisia_desde` (dia/hora) en la relación para R6/R7.

---

## 9. PLAN REPARACIÓN / RECONCILIACIÓN (salida de crisis SIN ruptura)

**Infraestructura reutilizada:** el canal conflicto YA decae (7+7 días) y YA se repara 1 nivel
por encuentro bien/muy_bien (config `conflicto`, activo en producción). La reparación de crisis
es la extensión natural, no un sistema nuevo.

### 9.1 Carril de reparación (par en CRISIS)

1. Durante la crisis, un encuentro REAL de la pareja con resultado ≥ `bien` (vía Celestine —
   `tiposPermitidos` ya da `cita` en crisis— o casual/agenda) reduce conflicto y deja memoria buena.
2. Cuando `conflicto.intensidad < crisis.conflicto_min` Y existe ≥1 encuentro ≥bien durante la
   crisis → la pareja vuelve a ser «reparable»: intento de reparación (una vez por ventana,
   marcador fechado como el resto):
   - voluntad de AMBOS (media geométrica, tipo `cita`, penalización emocional ya activa);
   - **Éxito** → `estado_pareja: crisis → pareja`, estabilidad restaurada PARCIALMENTE:
     `min(valor_previo_memoria, valor + reparacion.delta_estabilidad (default 20, knob))`,
     hito `APOYO_IMPORTANTE` (definido y hoy sin productor — le damos el primero),
     diario (banco nuevo pequeño, §12) + cotilleo `amistad_social`/`romance`;
   - **Fallo** → sigue en crisis, `fallos_reparacion++`. Tras `reparacion.max_fallos (2)` o
     `dias_en_crisis ≥ ruptura.dias_crisis_sin_salida (7)` → el par entra en el carril de RUPTURA (§10).

### 9.2 Garantías

- Ni toda crisis acaba en ruptura (reparación viable), ni toda crisis se repara sola
  (requiere encuentro bueno REAL + voluntad de ambos).
- Memoria: los fallos de reparación y los motivos quedan en la relación y alimentan la voluntad
  (conflicto/emocional) y la narrativa (diario/cotilleo drama mientras dure).

---

## 10. PLAN RUPTURA

**Canon:** `ruptura.nunca_auto_por_umbral=true`. Solo hito explícito con causa registrada.

### 10.1 Cuándo puede ocurrir (orígenes únicos)

| Origen | Condición (causal) |
|---|---|
| O1 Crisis sin salida | `estado=crisis` AND (`dias_en_crisis ≥ 7` OR `fallos_reparacion ≥ 2`) → tirada única con `p = ruptura.probabilidad (0.01, clave muerta REVIVIDA) + 0.03×fallos_reparacion (knob)` |
| O2 Golpe duro en pareja estable | combo EXPLÍCITO: muy_mal en la última cita AND `conflicto ≥ crisis.conflicto_min` AND `estabilidad ≤ crisis.estabilidad_riesgo` → misma tirada condicionada |

Sin esas combos → p = 0. Una pareja estable con buen rodaje NO tiene ruptura aleatoria.

### 10.2 Quién rompe y cómo se decide

- Rompe el de MENOR `romanceHacia` (empate → orden canónico). Es una decisión UNILATERAL:
  se evalúa la voluntad SOLO de quien rompe (tirada individual, tipo `ruptura` con
  `mod_tipo.ruptura` negativo default −15: romper no es barato), NO media geométrica.
- Si su tirada falla → no hay ruptura ese día; el riesgo permanece (causas siguen).

### 10.3 Efectos (casi todo ya implementado en `romper()`)

- `estado_pareja='ex'`, estabilidad apagada con `memoria` conservada, `historial_parejas[n].fin`
  + `como_acabo` ('crisis_sin_salida' | 'golpe_duro'), hito `RUPTURA` (vivos).
- Cancelación de encuentros FUTUROS del par con tipo `primera_cita`/`cita` (política nueva, §10.4).
- Emociones: tristeza a ambos; duración mayor al receptor (`ruptura.triste_receptor_horas`,
  default = `triste` 10h; iniciador mitad). Knobs provisionales.
- Cotilleo familia `ruptura` + diario `CUERPOS_RUPTURA` + etiqueta «Ex pareja» — TODO VIVO.
- Social/romance/conflicto residuales NO se borran (memoria del vínculo, ya así).

### 10.4 Citas pendientes, señales y terceros en la ruptura

- Citas futuras del par (cita/primera_cita): CANCELADAS con narrativa neutra («finalmente no
  pudo ser»); quedadas amistosas se mantienen.
- Marcadores de continuidad del par: purgados.
- Señales/flechazos históricos: persisten (memoria), pero sin producir iniciativa (gate `ex` + cooldowns).
- Terceros: nada especial; el mercado romántico sigue sus reglas normales.

---

## 11. POST-RUPTURA Y VUELTA

**Decisión: SÍ puede haber vuelta, con contrato estricto (no telenovela infinita).**

| Regla | Valor | Fuente |
|---|---|---|
| Cooldown mínimo post-ruptura | `cooldowns.por_familia.romance_hito` = **336h** reutilizado (nada de vuelta antes de 14 días) | knob existente |
| Disparador de la vuelta | Misma maquinaria que F2B con requisitos endurecidos: señal MUTUA viva + ≥1 encuentro real ≥`bien` DESPUÉS de la ruptura (los ex deben haberse tratado y no quemado) + voluntad geométrica de ambos con **penalti por vuelta**: `vuelta.penalti_voluntad = −8 × vecesPareja()` (usa `RelacionBitacora::vecesPareja()`, ya implementado) | patrón F2B + helpers vivos |
| Estabilidad de la vuelta | `base_reconciliacion` = **32** (ya implementado en `formar()` vía `reconciliar()` → `vuelta=true`) | knob existente |
| Cap absoluto | `pareja.max_vueltas` = **2** por par. Al alcanzarlo → veto permanente `historia_cerrada` (con memoria y narrativa de «capítulo cerrado»). Evita el rebrote infinito que el cotilleo ya bromea («apuestas sobre rebrote») | knob nuevo |
| Memoria que pesa | rechazos acumulados (voluntad), conflictos históricos (canal), `vecesPareja`, estabilidad `memoria` de la ruptura anterior | todo ya persistido |

La vuelta usa `ParejaEngine::reconciliar()` (existente) tras sus gates, y produce hito `VUELTA`,
cotilleo `pareja` y diario `CUERPOS_VUELTA` — todos vivos.

**Post-ruptura general (independiente de la vuelta):**
- ambos pueden frecuentar otros (gates normales; el ex no bloquea nuevas señales con terceros);
- trato entre exes: quedadas posibles (Celestine/amistad), citas románticas NO autónomas;
- sin mecánicas de celos/infidelidad en este alcance (fuera de alcance documentado, Maestro §18).

---

## 12. NARRATIVA (observar sin omnisciencia)

Auditoría de canales: NO se duplica ninguno. Mapa momento→canal (todo existente salvo 2 bancos copy):

| Momento | Buzón/Cotilleo | Diario residente | Ficha/Vista |
|---|---|---|---|
| Interés (señal) | evento `SENAL_ROMANTICA` + `CopySenalRomantica` (vivos) | — | «Me gusta 💘» |
| Cita | cotilleos de encuentro (`EncuentroCotilleoCopy`) + `amistad_social` en hitos | cuerpos por experiencia | planes/encuentros |
| Declaración rechazada | — (silencio social creíble) | `CUERPOS_DECLARACION_RECHAZADA` (VIVO) | banda romance |
| Pareja | familia `pareja` (VIVO) | `CUERPOS_INICIO_PAREJA` (VIVO) | «Pareja ❤️» |
| Crisis | familia `drama` (VIVO) | `CUERPOS_CRISIS` + `DISCUSION_FUERTE` (VIVOS) | «En crisis 💔» |
| Reparación | `romance`/`amistad_social` | **banco NUEVO `CUERPOS_APOYO_IMPORTANTE`** (4 variantes, estilo existente) | vuelve a «Pareja ❤️» |
| Ruptura | familia `ruptura` (VIVO) | `CUERPOS_RUPTURA` (VIVO) | «Ex pareja 💔» |
| Vuelta | familia `pareja` (VIVO) | `CUERPOS_VUELTA` (VIVO) | «Pareja ❤️» |

Reglas de privacidad respetadas: textos sin números internos, sin scores, atribución canónica
de rechazos (patrón narrativa P2 ya en producción), deduplicación de hitos vía
`narrativa_hitos_publicados` (idempotente). Trabajo narrativo total: **1 banco copy nuevo**
(`APOYO_IMPORTANTE`) y opcionalmente 2–3 líneas en `cotilleo_familias.json` si las sims piden variedad.

---

## 13. CADENCIA GLOBAL / ANTI-SPAM

Mapa completo de respiración temporal (todo knob existente salvo los marcados ★nuevo):

```
flechazo            hito ÚNICO por par y dirección          (guard vivo)
primera_cita        ÚNICA por par                            (guard vivo F1)
cita ↔ cita         gap ≥ 48h                               (cooldowns.por_familia.romance)
declaración         ≥ 2 citas positivas + ventana 336h      ★knobs (defaults = valores canónicos ya vivos)
declaración rechazo cooldown 336h + 6h anti-reintento       (romance_hito + rechazos.cooldown_horas)
formación           ÚNICA; exclusividad global              (R1)
cita de pareja      gap ≥ 36h                               (cooldowns.por_familia.pareja)
crisis              causas ≥2 + p condicionada + 48h + ≤2/mes★
reparación          1 intento por ventana; máx 2 fallos     ★
ruptura             solo orígenes O1/O2                     ★
vuelta              ≥ 336h post-ruptura + cap 2 vueltas     ★
```

Anti-cascada global (★nuevo, 1 knob): `romance_autonomo.max_hitosc_por_dia = 1` — máximo 1 hito
romántico de cualquier tipo por día de pueblo en TODO el pueblo (evita «pareja viernes, crisis
sábado, ruptura domingo»). Los consumidores verifican el cap antes de la tirada (sin RNG si no toca).

Patrón anti-mismo-tick heredado de F2A: ningún hito se crea en el tick en que se resolvió su
causa; todo pasa por marcador fechado consumido en tick posterior.

---

## 14. INTERFERENCIAS (convivencia demostrable)

| Sistema | Interacción | Verificación |
|---|---|---|
| A1 IniciativaSocial | Familias distintas; A1 no mira romance | test `iniciativa_social_autonoma` verde + log A1 intacto en sims |
| Peticiones / R08 | Sin puntos de contacto | `peticiones_autonomas_e2e` + `peticiones_gap_r08` verdes |
| Eventos comunitarios F4 | Multi-participante; los hitos de pareja no participan; cupo Celestine intacto (y INC-1 corregido) | test interferencias + F4 gates |
| Latidos / VidaPueblo | La ruta autónoma NO aplica deltas de Vida (coherente con F2A: autonomía ≠ intervención Celestine) | test explícito «vida_pueblo sin cambios por hito autónomo» |
| Planes/regalos de Celestine | Intactos; Celestine puede proponer citas a parejas (ya vivo); no cobra cupo lo autónomo | `playtest_integral_gate` + tests de cupo |
| Encuentros normales/casuales | Comparten resolver; freno de terceros vivo para flechazo casual | `encuentros_test`, `interaccion_casual_dedup` |
| Trabajo/sueño/agenda/aforo | `AgendaConjunta::primeraFranja` + `ComplejoCatalog::estaAbierto` + `AforoEngine` (ya en F1/F2A) | `fase1_*` verdes |
| **Nunca cobrar como Celestine** | Todo lo autónomo lleva `intencion='autonomo_npc'` y `cuentaComoCelestine=false` | test de cupo específico |

---

## 15. FEATURE GATES

**Decisión estructural:** la declaración/pareja/crisis/ruptura AUTÓNOMAS no entran por
`familias_en_play` (eso solo gobierna el HUECO de vida de `AcontecimientoDiario`, que sigue
cerrado para `romance_hito`/`pareja`). La ruta autónoma usa flags PROPIOS, reversibles e
independientes, en un bloque nuevo de `calibracion_vida.json`:

```json
"romance_autonomo": {
    "_provisional": true,
    "declaracion_activa": false,
    "pareja_activa": false,
    "vida_pareja_activa": false,
    "crisis_activa": false,
    "ruptura_activa": false,
    "vuelta_activa": false,
    "max_hitosc_por_dia": 1,
    "...knobs de cada sección": "..."
}
```

Secuencia de habilitación (cada flag ON solo con sus tests+sim verdes y aprobación):

```
GATE-D (declaración)  → GATE-P (formación)  → GATE-V (vida pareja)
                      → GATE-C (crisis)     → GATE-R (ruptura)  → GATE-W (vuelta)
```

Dependencias duras: P requiere D; V requiere P; C requiere V; R requiere C; W requiere R.
En producción TODO OFF hasta el cierre final. `pre_gate_familias_play_test` debe seguir VERDE
en todo momento (prueba de que el hueco sigue blindado). Cada bloque R incluye test de
«flag OFF ⇒ comportamiento byte-idéntico a producción actual».

Claves muertas que se REVIVEN (con actualización del comentario del runner integral):
`crisis.probabilidad`, `crisis.bonus_si_estabilidad_baja`, `ruptura.probabilidad` — ahora con
gate causal previo (§8/§10). `*_nunca_auto_por_umbral` siguen TRUE y se siguen verificando.

---

## 16. MATRIZ DE TESTS

Nuevos ficheros (patrón de `tests/fase2_continuidad_citas_test.php`: fixtures deterministas,
sin red, RNG controlado, cobertura letra a letra):

| # del encargo | Test | Fichero (nuevo salvo *) |
|---|---|---|
| 1 | primera→segunda→tercera cita | * `tests/fase2_continuidad_citas_test.php` (regresión) |
| 2 | declaración elegible (todas las causas) | `tests/fase2b_declaracion_test.php` |
| 3 | declaración prematura bloqueada (citas<min, ventana, calidad, banda) | ídem |
| 4 | sin reciprocidad bloquea | ídem |
| 5 | rechazo (motivo real, erosión, memoria) | ídem |
| 6 | cooldown declaración (336h + 6h) | ídem |
| 7 | aceptación (voluntad geométrica, 1 tirada, atribución) | ídem |
| 8 | formar pareja (persistencia+bitácora+narrativa+ficha) | `tests/fase2c_formacion_test.php` |
| 9 | **exclusividad global** (A-B luego A-C → error; orden inverso; vías: autónoma/API/debug/lab) | `tests/fase2c_exclusividad_test.php` |
| 10 | tercero no rompe exclusividad (citas/gates/iniciativas) | ídem |
| 11 | pareja estable (sin crisis sin causas, citas de pareja con gap 36h) | `tests/fase3_vida_pareja_test.php` |
| 12 | crisis causal (combos de causas; tirada solo con causas) | `tests/fase4_crisis_test.php` |
| 13 | ausencia de crisis sin causas (p=0 exacto, 0 draws) | ídem |
| 14 | reparación exitosa (estado, estabilidad parcial, hito APOYO) | `tests/fase5_reparacion_test.php` |
| 15 | reparación rechazada (fallos, memoria, escalado a riesgo) | ídem |
| 16 | ruptura (orígenes O1/O2, autoría, efectos completos) | `tests/fase6_ruptura_test.php` |
| 17 | post-ruptura (cancelación de citas, etiquetas, emociones, cooldown) | ídem |
| 18 | vuelta: posible/no posible según contrato (336h, encuentro previo, cap 2, historia_cerrada) | `tests/fase7_vuelta_test.php` |
| 19 | anti-duplicación (marcadores, hitos, narrativa idempotente) | transversal (incluido en 2/8/12/16) |
| 20 | determinismo (misma seed ⇒ mismo save) | `tests/romance_cierre_determinismo_test.php` |
| 21 | RNG (draws exactos y documentados por camino; 0 en gates) | ídem + por-fase |
| 22 | HORA_PASADA=0 (todo futuro estricto) | ídem (patrón `hora_pasada_test`) |
| 23 | Celestine intacta (cupo, planes, regalos) | `tests/romance_interferencias_test.php` |
| 24 | A1 intacta | ídem (* `iniciativa_social_autonoma` en worktree F2A) |
| 25 | Peticiones intactas | * `peticiones_autonomas_e2e` + `gap_r08` (regresión) |
| 26 | F4 intacto | `romance_interferencias_test` |
| 27 | saves legacy (sin `romance_autonomo` en calibración ⇒ flags OFF; relaciones viejas migran por `ensureRomanceCampos`) | `tests/romance_saves_legacy_test.php` |
| 28 | privacidad narrativa (sin scores/omnisciencia; atribución canónica) | `tests/romance_narrativa_privacidad_test.php` |
| 29 | narrativa completa del arco (cotilleo+diario por hito, 1 vez) | ídem |
| 30 | simulación larga (runner 90 días determinista con asserts de invariantes) | `tests/romance_larga_duracion_test.php` |

**Suites de REGRESIÓN obligatorias en cada bloque** (ya existen, deben seguir verdes):
`fase1_iniciativa_primera_cita` · `fase1_primera_cita_narrativa` · `fase1_salida_individual_franja`
· `fase2_continuidad_citas` · `pre_gate_familias_play` · `iniciativa_social_autonoma`
· `relaciones` · `senal_romantica_coherencia_temporal` · `voluntad_media_geometrica`
· `rechazo_clase/copy_coherente/atribucion_canonica` · `hora_pasada` · `rng` · `php74_syntax`
· `vida_relacional` · `visibilidad_relaciones_post_audit` · `playtest_integral_gate`.

---

## 17. SIMULACIONES DE BALANCE

Banco: extensión del patrón `dev/_sim_funnel_romance.php` (headless, avance canónico hora a hora,
`SimFunnelProbe` solo-lectura). Sondas nuevas: `declaracion_ok/rechazada`, `pareja_ok`,
`crisis_ok`, `reparacion_ok/fail`, `ruptura_ok`, `vuelta_ok`, `exclusividad_veto`.

| Parámetro | Valor |
|---|---|
| Horizontes | 30 días y 90 días |
| Seeds | ≥4 por celda (heredadas del banco) |
| Poblaciones | {3, 8, 16, 24} (pool canónico jugable) |
| Flags | corridas con gates progresivos ON y comparación contra baseline OFF |

**Métricas:** flechazos · primeras citas · segundas/terceras · declaraciones (ok/rechazo) ·
parejas formadas · crisis · reparaciones ok/fail · rupturas · vueltas · duración media pareja
(días) · concentración (máx parejas simultáneas por residente — debe ser 1 SIEMPRE) ·
triángulos bloqueados (vetos de exclusividad/gates tercero) · aislados románticos ·
cadencia (horas mínimas observadas entre hitos del mismo par).

**Objetivos (del diagnóstico §20 del Maestro, P6):**
- pop8 ≈ **1 pareja estable entre d30–45** y **3–6 parejas al d100**;
- duración media de pareja > 20 días (no Love Island);
- 0 violaciones de exclusividad; 0 crisis sin causas; 0 hitos en el mismo tick que su causa;
- declaración rechazada ≥ ~⅓ de los intentos (no-inevitabilidad observable);
- pueblos pequeños (3): ≥1 historia completa en 90 días sin saturar.

Cifras finales de knobs (`citas_minimas`, `mod_tipo.declaracion`, probabilidades revival, etc.)
se fijan tras estas sims y quedan registradas en §20 como calibración de cierre.

---

## 18. BLOQUES DE IMPLEMENTACIÓN R1–R8

Todos dentro de ESTE proyecto/agente. Orden estricto, cada bloque termina en commit propio
+ suite verde. Base de trabajo: worktree aislado desde linaje producción real
(`int/recon-romance-f1-a1-pre-f2a-20260825` @ abb1276), NUNCA desde el working tree sucio principal.

### R1 — Exclusividad + gates de tercero + INC-1  *(prerequisito de todo)*
- **Archivos:** `src/Engine/ParejaEngine.php` (nuevo `parejaActivaDe()` + guard `formar()`),
  `src/Engine/TerceroRomantico.php` y `src/Engine/DebugParejasEngine.php` (delegar en el método central),
  `src/Engine/SenalRomantica.php` (gate `en_pareja_con_otro` en `desbloqueaPrimeraCita`/`avisarSiAplica`),
  `src/Engine/IniciativaRomantica.php` (mismo gate en ambos intents + `cuentaComoCelestine=false` en F1),
  `tests/fase2c_exclusividad_test.php` (nuevo).
- **Dependencias:** ninguna. **Riesgo:** MEDIO (toca ficheros F1 vivos → hunks quirúrgicos, tests F1 como gate).
- **Tests:** matriz 9, 10, 24 + regresión F1 completa.

### R2 — F2B Declaración autónoma
- **Archivos:** `src/Engine/IniciativaRomantica.php` (marcador+consumidor declaración),
  `src/Engine/PropuestaNivel.php` (alias tipo `declaracion`),
  `data/configs/calibracion_vida.json` (bloque `romance_autonomo` OFF + `voluntad.mod_tipo.declaracion`),
  `tests/fase2b_declaracion_test.php` (nuevo).
- **Dependencias:** R1. **Riesgo:** MEDIO (motor vivo compartido; todo tras flags OFF).
- **Tests:** matriz 2–7, 19–22, 27.

### R3 — F2C Formación
- **Archivos:** `src/Engine/IniciativaRomantica.php` (aceptación → `ParejaEngine::formar` + memoria `pareja` + purga marcadores),
  `data/configs/calibracion_vida.json` (flag `pareja_activa`),
  `tests/fase2c_formacion_test.php` (nuevo).
- **Dependencias:** R1+R2. **Riesgo:** BAJO-MEDIO (formar() ya existe y está probado en lab).
- **Tests:** matriz 8, 11 (parte estable), 29.

### R4 — Vida de pareja autónoma
- **Archivos:** `src/Engine/IniciativaRomantica.php` (rama PAREJA en continuidad, gap 36h),
  `data/configs/calibracion_vida.json` (`vida_pareja_activa`),
  `tests/fase3_vida_pareja_test.php` (nuevo).
- **Dependencias:** R3. **Riesgo:** BAJO.
- **Tests:** matriz 11, 21–22.

### R5 — Crisis
- **Archivos:** `src/Engine/IniciativaPareja.php` (NUEVO: evaluador diario crisis/reparación/ruptura — un solo evaluador para R5/R6/R7),
  `src/Engine/RelojOperations.php` (hook al cerrar día junto a `RelacionDesgaste`, hunk mínimo),
  `src/Engine/ParejaEngine.php` (campos `crisia_desde`, `fallos_reparacion` vía ensure),
  `src/Engine/DiarioResidenteBridge.php` (banco `CUERPOS_APOYO_IMPORTANTE`),
  `data/configs/calibracion_vida.json` (`crisis_activa` + knobs),
  `tests/fase4_crisis_test.php` (nuevo).
- **Dependencias:** R4. **Riesgo:** MEDIO-ALTO (primer hook diario nuevo; mitigado: patrón idéntico a RelacionDesgaste, flag OFF por defecto, test de ausencia de draws sin causas).
- **Tests:** matriz 12, 13, 19–22.

### R6 — Reparación
- **Archivos:** `src/Engine/IniciativaPareja.php`, `src/Engine/RelacionBitacora.php` (uso `APOYO_IMPORTANTE`, sin cambio de contrato),
  `data/configs/calibracion_vida.json`, `tests/fase5_reparacion_test.php` (nuevo).
- **Dependencias:** R5. **Riesgo:** BAJO.
- **Tests:** matriz 14, 15, 28, 29.

### R7 — Ruptura + post-ruptura
- **Archivos:** `src/Engine/IniciativaPareja.php`, `src/Engine/ParejaEngine.php` (cancelación de citas futuras del par — helper con narrativa neutra),
  `data/configs/calibracion_vida.json` (`ruptura_activa` + knobs),
  `tests/fase6_ruptura_test.php` (nuevo).
- **Dependencias:** R5(+R6 para el carril de fallo). **Riesgo:** MEDIO (cancelación de encuentros: tocar solo estados `programado`, nunca historial).
- **Tests:** matriz 16, 17, 26.

### R8 — Vuelta + balance + calibración final + cierre documental
- **Archivos:** `src/Engine/IniciativaRomantica.php` o `IniciativaPareja.php` (initiative de vuelta),
  `dev/_sim_funnel_romance_cierre.php` (banco de sims, nuevo, basado en el existente),
  `data/configs/calibracion_vida.json` (calibración FINAL de cierre),
  `tests/fase7_vuelta_test.php`, `tests/romance_larga_duracion_test.php`,
  `tests/romance_cierre_determinismo_test.php` (nuevos),
  `docs/ROMANCE_AUTONOMO_CIERRE_TOTAL.md` (este doc → cierre).
- **Dependencias:** R1–R7. **Riesgo:** BAJO (medición, no reglas).
- **Tests:** matriz 18, 20, 30 + todas las regresiones.

---

## 19. ESTRATEGIA DE INTEGRACIÓN / DEPLOY

Doctrina consolidada de los deploys F1/R08/NARRATIVA-P2/RECON (producción real manda):

1. **Producción real manda.** Antes de CADA bloque: fetch PRE de los blobs a tocar con recibos
   SHA256 en `dev/_prod_fetch_romance_cierre/<bloque>-<ts>/{pre,post}/` + MANIFEST.
   Prohibido integrar desde ramas/base históricas sin reconciliar.
2. **Worktree aislado** (`tmp/wt_romance_cierre` o equivalente) desde el linaje de producción
   (`abb1276…` + drift PRE aplicado). Un commit por bloque. El árbol principal está sucio
   (WIP ajeno documentado) y NO es base.
3. **Hunks quirúrgicos**: reemplazos literales con unicidad 1:1 sobre blobs PRE reales
   (patrón `scripts/_r08_reconcile.ps1`). Blob completo solo para fichero NUEVO
   (caso `IniciativaPareja.php` y tests).
4. **Backup PRE** en `backups/pre-romance-cierre-<bloque>-<ts>/` por cada fichero tocado.
5. **Tests sobre blobs EXACTOS candidatos** (staging) antes de GO — incluida la regresión F1/F2A
   y `pre_gate_familias_play` (el hueco sigue blindado).
6. **Deploy atómico** cuando varios ficheros compartan dependencia en el mismo bloque
   (F1 lo demostró con `deploy_f1_atomico.php` tras las reversiones del share `\\amg-arriba`):
   scripts idempotentes, RepoRoot explícito (lección del incidente post-F1).
7. **POST hashes**: verificación byte-idéntica remota por fetch-back; registro SHA256 en §20.
8. **Partida TEST** en producción por bloque (semilla `TEST_romcierre_<bloque>`), validación
   E2E del arco alcanzable, y **limpieza SOLO con IDs explícitos** de partida creada.
   PROHIBIDO cualquier comodín destructivo: `part_*`, `*.json*`, borrados por patrón.
9. **Cache-buster** solo si se tocase JS/CSS (este plan NO toca frontend). Refresh de opcache
   si PHP no sirviera código nuevo (precedente 14:51 narrativa P2).
10. **Flags OFF en producción** hasta que todos los bloques estén integrados, sims aprobadas
    y Neni/ChatGPT den el visto bueno final; entonces un ÚLTIMO deploy de solo-calibración
    activa los gates en orden (§15), con partida TEST de verificación por gate.

---

## 20. REGISTRO DE EJECUCIÓN

*(Se rellena durante la implementación. Este apartado convierte el plan en documento de cierre.)*

### 20.1 Decisiones finales de diseño
- (pendiente)

### 20.2 SHAs de trabajo por bloque
| Bloque | Rama | Commit | Fecha |
|---|---|---|---|
| R1 | int/romance-cierre-total | — | — |

### 20.3 Tests ejecutados (suite → resultado → fecha)
- (pendiente)

### 20.4 Simulaciones (matriz → métricas → cifras de calibración adoptadas)
- (pendiente)

### 20.5 Deploys (PRE hashes → hunks → POST hashes → partida TEST → limpieza)
- (pendiente)

### 20.6 Estado final de producción y deudas FUERA de alcance
- Deuda documentada no bloqueante que queda fuera: `rechazo_cooldown_neutro_test` desfasado (copy-cooldown),
  versionado de clases runtime (B31), árbol principal sucio ajeno a este proyecto.
- (resto pendiente)

---

*FIN DEL PLAN. Pendiente de aprobación de Neni/ChatGPT. No se implementa nada hasta recibir
«PLAN APROBADO. EJECUTA EL CIERRE TOTAL.»*
