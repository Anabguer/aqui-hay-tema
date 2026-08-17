# Informe implementación — Turno 3 (Carlos)

**Fecha:** 2026-08-18  
**Alcance:** Robustez, QA, preparación arquitectónica (sin decisiones creativas/producto)

---

## 1. % implementación antes / después

| Métrica | Antes (T2) | Después (T3) |
|---------|------------|--------------|
| **Funcionalidad jugable** | ~28 % | ~28 % (sin inflar) |
| **Calidad técnica / preparación** | baseline T2 | +infra QA, API modular, dev lab |

El Turno 3 **no añade sistemas jugables nuevos**; fortalece lo existente y prepara escala.

---

## 2. Commits (Turno 3)

Ver `git log` tras commit. Bloques lógicos:

1. Infra: EventBus, GameError, AuditTrail, FeatureConfig, save atómico
2. API: router + handlers por dominio
3. Dev: laboratorio, time travel, snapshots
4. Tests: cobertura dominio + smoke en run_all
5. Docs: informe T3

---

## 3. Tests PASS / FAIL

**48 OK / 0 FAIL** (`php tests/run_all.php`)

| Suite | Tests |
|-------|-------|
| agenda_test | 5 |
| api_router_test | 2 |
| encuentros_test | 5 |
| persistencia_test | 6 |
| relaciones_test | 6 |
| rng_test | 5 |
| schema_test | 4 |
| smoke.php | 10 |
| viviendas_test | 5 |

---

## 4. Refactors realizados

- **API monolítica → router + handlers** (`api/index.php` solo enruta).
- **Autoload** resuelve `AquiHayTema\Api\*` desde `api/`.
- **Handlers** en un archivo por clase.
- **PartidaRepository:** `tmp → validar → bak (solo JSON válido) → replace`.
- **DomainBootstrap + EventBus** para desacoplar consecuencias futuras.
- **GameError:** código + `mensaje_ui` placeholder.

---

## 5. Herramientas nuevas de dev

`dev.php` (requiere `AHT_DEV=1` o `dev.local.php`):

- Partida: selector, nueva con seed, borrar
- Reloj: +1/+4/+12h, +1d, `reloj.ir_a`
- Inspecciones: residente, agenda, vivienda, relaciones, encuentros, RNG, audit, buzón, diario, mapa
- Snapshots: guardar / restaurar / listar
- Resets: encuentros, relaciones, buzón/diario
- Encuentros/relaciones DEV, stress 100, copiar JSON

---

## 6. Smoke 100 residentes

| Métrica | Valor |
|---------|-------|
| Residentes | 101 |
| Agendas resueltas | 101 |
| Tamaño save | ~71 KB |
| Tiempo | ~166 ms |
| Bloque A ocupadas | 16 |

Sin problema evidente en operaciones básicas a escala 100 placeholders.

---

## 7. Deuda eliminada

- Dominio en API monolítica
- Autoload handlers roto
- `.bak` corrupto por saves inválidos
- Smoke fuera de run_all
- Errores sin `mensaje_ui` (parcial UI)

---

## 8. Deuda nueva

- `PartidaService` centralizado
- Validadores no obligatorios en carga catálogo
- EventBus sin consecuencias narrativas
- A11y básica sin WCAG completo

---

## 9. BLOQUEADO_DECISION

Fórmulas, cupos, economía, narrativa, B/C día 1, I10/I06 — pendiente Neni+ChatGPT.

---

## 10. Qué puede probar Neni

1. `play.php` — bucle encuentro intacto
2. `dev.php` — time travel, snapshots
3. Seed reproducible, copiar JSON para diagnóstico

---

## 11. Turno 4

Cerrar B/C y fórmulas; integrar validadores; EventBus → diario/buzón; reducir PartidaService.

---

## 12. Sin decisiones creativas

Confirmado: no retratos, estilo, identidades, narrativa, economía balanceada, cupos definitivos.
