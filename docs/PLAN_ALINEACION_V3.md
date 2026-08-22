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
