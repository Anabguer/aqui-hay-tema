# Informe implementación — Turno 1 (bloque A)

**Fecha:** 2026-08-18  
**Alcance:** esqueleto jugable técnico, sin estética final ni contenido inventado.

---

## 1. % implementación ANTES

**~1 %**

Solo landing PHP (`index.php`), plantillas JSON y estructura de assets. Sin motor, persistencia ni UI de partida.

---

## 2. % implementación DESPUÉS

**~8 %**

---

## 3. Criterio del porcentaje

Se cuenta **solo código ejecutable y comprobable**, no documentación de diseño.

| Bloque técnico V0 | Peso relativo | Estado turno 1 |
| --- | ---: | --- |
| Landing / biblioteca | 1 | Ya existía |
| Persistencia partida (crear/guardar/cargar/reiniciar) | 2 | **Funciona** |
| Reloj 1:1 + avance dev | 1 | **Funciona** |
| Catch-up offline | 1 | Estructura `_placeholder`, sin eventos |
| Bloque A (16 viviendas, auto-asignación) | 2 | **Funciona** |
| Runtime residentes + catálogo | 2 | **Funciona** (Rocío; placeholder dev) |
| Agenda 24×1h + capas + plantillas ocupación | 2 | **Funciona** |
| Motor citas (programar/estados/anti-doble-reserva) | 2 | **Funciona** (sin resultado narrativo) |
| Relaciones social + romance (4 variables) | 1 | **Funciona** (scaffold, sin fórmulas) |
| UI partida Bloque A + ficha provisional | 2 | **Funciona** |
| Harness dev | 1 | **Funciona** |
| Mapa jugable | 3 | No empezado |
| Buzón / diario UI | 2 | No empezado |
| Economía / fama runtime | 2 | No empezado |
| Compatibilidad evaluador | 2 | No empezado |
| Vida autónoma NPC | 2 | No empezado |
| Llegadas / marchas | 2 | No empezado |
| Retratos / assets visuales personajes | 2 | Pausado (sin cambios) |
| Resultados de cita / micros | 2 | No empezado |
| 15 fichas restantes en runtime | 3 | No empezado |

**Suma ponderada aproximada:** ~8 / ~100 puntos de implementación total estimada para V0 jugable completo.

---

## 4. Sistemas realmente funcionando

1. **PartidaService** — nueva partida con config `debug_v0`, Rocío en A01, JSON en `data/partidas/`.
2. **Reloj** — hora/día, avance por horas, texto formateado, registro catch-up sin eventos.
3. **BloqueA** — 16 slots A01–A16, primer hueco libre, liberar vivienda.
4. **AgendaEngine** — 24 slots, sueño/trabajo por `ocupacion`, compromisos recurrentes, citas programadas, disponibilidad.
5. **CitaEngine** — programar, cancelar, estados `programado|en_curso|terminado|cancelado`, solo lugares operativos.
6. **RelacionEngine** — upsert social y romance (variables núcleo, `_placeholder_formulas`).
7. **API REST** — `api/index.php` con 18 acciones.
8. **UI `play.php`** — grid Bloque A, ficha residente al clic.
9. **Harness `dev.php`** — reloj, placeholder, citas, relaciones, inspección JSON.

---

## 5. Qué podéis probar directamente

1. Abrir **`play.php`** → se crea/carga partida automática → ver Rocío en A01 → clic → ficha con agenda.
2. Abrir **`dev.php`** → «Crear placeholder dev» → programar cita a las 19:00 (evitar horas laborables del placeholder autónomo 10–18) → inspeccionar JSON.
3. Avanzar reloj +1 día → comprobar cambio de `dia_pueblo`.
4. Guardar/recargar → persistencia en `data/partidas/part_*.json`.
5. CLI: `php tests/smoke.php` → 19 aserciones OK.

---

## 6. Rutas / URLs

Asumiendo servidor web en `W:\juegos` (como landing actual):

| Recurso | URL |
| --- | --- |
| Landing | `/juegos/Aqui%20hay%20tema/index.php` |
| Jugar | `/juegos/Aqui%20hay%20tema/play.php` |
| Modo dev | `/juegos/Aqui%20hay%20tema/dev.php` |
| API | `/juegos/Aqui%20hay%20tema/api/index.php?action=partida.nueva` |

---

## 7. Tests realizados

| Test | Resultado |
| --- | --- |
| `php tests/smoke.php` | **PASS** (19/19) |
| Partida nueva + Rocío A01 | PASS |
| Placeholder dev + asignación A02 | PASS |
| Agenda oficina 9h ocupada | PASS |
| Cita 19h + anti-doble-reserva | PASS |
| Save/load JSON | PASS |
| Relaciones social/romance | PASS |
| Ficha Rocío provisional | PASS |

---

## 8. Bugs conocidos

1. **Sin servidor PHP embebido documentado** — requiere Apache/XAMPP o similar apuntando a `W:\juegos`.
2. **Citas en horario laboral** — fallan correctamente si el slot estructural está ocupado; la UI dev no advierte (solo error API).
3. **`partida.reiniciar`** — borra archivo y crea partida **nueva con id distinto** (no conserva id).
4. **Capa autónoma NPC** — no implementada; slots libres no muestran actividad autónoma.
5. **No hay git** — sin commits en repositorio (carpeta no es repo git).

---

## 9. RECORTE_V0

**Ninguno.** Solo cafetería operativa día 1 (decisión cerrada). Lugares no operativos rechazados en motor de citas.

---

## 10. Placeholders existentes

| ID / marcador | Uso |
| --- | --- |
| `per_placeholder_dev_XX` | Residente sintético harness (autonomo) |
| `_placeholder: true` en partida | catch-up offline, residentes dev |
| `_placeholder_resultado` en citas | Sin resultado narrativo |
| `_placeholder_formulas` relaciones romance | Sin evaluador compatibilidad |
| `_placeholder_rechazo_narrativo` agenda | Motivo mecánico sin texto voz |
| `_placeholder_balance` rel. social | Sin balance definitivo |
| `_ui: provisional_v0` ficha | UI no final |
| `buzon[]`, `diario[]` vacíos | Estructura partida |

---

## 11. Decisiones pendientes (Neni + ChatGPT)

- Residentes **B y C** del día 1.
- **I10** en tutorial vs llegada normal.
- Contenido buzón, diario, coletillas, rechazos agenda narrativos.
- Cifras economía/fama, aforos, desbloqueos adicionales.
- Fórmulas compatibilidad y resultados de cita.
- Micro-simulación Fase D.

---

## 12. Siguiente bloque lógico

1. Incorporar B/C cuando se cierren (sin tocar identidades).
2. Mapa técnico mínimo (cafetería operativa + candados visibles).
3. Buzón estructura + UI vacía.
4. Tope 2 citas/día en motor.
5. Transiciones cita `programado → en_curso → terminado` ligadas al reloj.
6. Tests HTTP automatizados si hay CI.

---

## 13. Commits realizados

**Ninguno** — el proyecto no tiene repositorio git inicializado en `W:\juegos\Aqui hay tema`.

---

## 14. Confirmación restricciones

- **NO** se generaron retratos (Dani, Rocío, I10, I06).
- **NO** se modificó identidad de personajes existentes.
- **NO** se canonizó estilo visual.
- **NO** se escribieron las 16 fichas ni se decidió B/C.
- **NO** se inventaron fórmulas de compatibilidad ni economía.
- **NO** se añadió contenido narrativo de catálogo (excusas, micros, coletillas).

---

## Archivos nuevos principales

```
src/Engine/*.php          — motor
src/autoload.php
api/index.php             — API JSON
play.php, dev.php
assets/css/app.css
assets/js/play.js, dev.js
data/partida/_plantilla.partida.json
data/configs/prevalidadas/debug_v0.json
data/partidas/            — saves runtime
tests/smoke.php
docs/PLAN_IMPLEMENTACION_TURNO_1.md
```
