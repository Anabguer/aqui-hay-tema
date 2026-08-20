# Especificación de integración V3 → PLAY

Dirección visual V3 **aprobada**. PLAY vivo: `play.php`. PLAY anterior: `play-provisional.php`.

Pack C3 Fase 1: `docs/carlos-iii/fase1-play/` → servido en `assets/play-v3/`.
Anillo: `docs/carlos-iii/fase1-play/NOTAS_ANILLO_C2.md`.

No se tocan economía, autonomía, relaciones ni peticiones salvo leer sus datos.

---

## Qué se mantiene (UX cerrada)

- 6 complejos; mapa = observar/consultar; Organizar = crear planes.
- Móvil: pueblo 2×3, sin scroll del pueblo. Mismo juego en PC.
- HUD de objetos. Dock: Pueblo · Diario · Organizar · Vecinos.
- Diario abre **El Cotilleo**. Buzón desde el sobre.
- Selector si el complejo tiene varios destinos operativos; si hay uno, se consulta quién hay.
- Máx. 5 caras visibles / complejo +N. El +N abre la lista completa de ese complejo.
- Escala M: 52 móvil / 62 PC. PNG sin anillo horneado.
- Crecimiento físico: Café y Cine tienen PNG evolucionado. El resto, temprano (Fase 2 C3).

---

## Datos del motor (no duplicar)

| UI | Fuente |
|---|---|
| Quién está dónde | `mapa.presencia` → `PresenciaEngine` |
| Destinos abiertos | `celeste.lugares_desbloqueados` |
| Complejo ↔ destino | `ComplejoCatalog` |
| Marca “hay tema” | `hay_tema` (bool) del motor: `HayTema` vía `VistaPuebloV3`. Independiente de emoción, romance y de encuentro próximo/en curso. Opcional: `tema_id` para abrir el hecho. |
| Estado emocional | `runtime.estado_emocional.id` (neutro, alegre, triste, enfadado) |
| Vida / corazón | `vida_pueblo.corazon_pct` |
| Reloj | `reloj` / `reloj_vista` |
| Dinero | `economia.dinero.balance` (si null: «—») |
| Buzón | `buzon.listar`. Importante = `clasificacion=importante` → lacre OJO |
| El Cotilleo | diario real (hoy / ayer / viejos) |
| Vecinos / ficha | `partida.inspeccionar` + `residente.ficha` |
| Organizar | `encuentro.tipos_permitidos` + `encuentro.proponer`. `tipo_sugerido`, `causa`, `mensaje_ui`, `candidatos_a` / `candidatos_b` (nunca la misma persona). |
| Tutorial | `estado.tutorial` (`activo`, `paso`, `zona`, `pista`, `sugerencia`). Se completa en la misma partida. |

Presentación: `VistaPuebloV3`. No decide quién se mueve.

---

## Tokens: dos señales distintas

El PNG **no** lleva borde. C2 pinta el anillo (NOTAS_ANILLO_C2):

| estado_emocional | anillo |
|---|---|
| neutro | cartón `#e8d7c0` |
| alegre (positivo) | verde `#7a9e6a` |
| triste (incómodo) | naranja `#d4894a` |
| enfadado | rojo `#c42b4a` |

Otro id → neutro.

**Hay tema:** `assets/play-v3/marcas/sello_hay_tema.png` en la esquina del token. No es el anillo. No es enamoramiento.

El motor expone `hay_tema: bool` por persona en el complejo (y `tema_id` si hay un hecho concreto). PLAY no lo calcula. No se infiere de `estado_emocional` ni de scores de romance ni de cita/encuentro próximo o en curso.

Se activa por un acontecimiento cotilleable real asociado a esa persona en ese contexto: cotilleo/discusión/acercamiento de hoy en El Cotilleo, o patrón de coincidencia significativa (varios días, y ahora mismo hay compañía de ese patrón en el sitio). Una salida cotidiana sola no basta.

Lote: 14 cabezas en `assets/personajes/tokens-m/`. Si el pack visual tiene PNG neutral, se usa ese.

---

## Movimiento (arquitectura, sin animar)

DOM: `data-residente` `data-complejo` `data-destino` `data-fase="en_destino"` `data-emocion` `data-hay-tema`

Hoy el motor no expone casa ni trayecto. Quien no está en un destino de `PresenciaEngine` no se dibuja.

---

## Crecimiento

`fase` en la vista = PNG que se muestra.
`fase_motor` = si hay anexo desbloqueado, aunque C3 aún no tenga el PNG.

playtest_01: cafetería + biblioteca + parque → Café evolucionado; Cine temprano hasta `lug_arcade`.

---

## Huecos (no resolver inventando)

1. Casa → destino: el motor no lo da.
2. Conocerse cupo 1: motor pide 2 IDs. Organizar envía 2.
3. Lote técnico ≠ identidad.
4. Diario vacío = Cotilleo vacío.
5. Dinero null → «—».
6. “Hay tema” no es un flag de cotilleo genérico: se usa el encuentro marcado del lugar. Si hace falta otra señal, contrato concreto a C1.
7. Alas evolucionadas de Lola, Mala Idea, Parque, Gym: no hay PNG. No se fingen.

---

## Playtest Neni (sistemas)

Entrada: `playtest.php` → `play.php?taller=1&lab=1&config=playtest_01&seed=playtest-01`.

- Config: `playtest_01` (8 vecinos P001–P008, café+parque+biblio). Sin tutorial `juego_v1`.
- Controles taller: Nueva partida, Guardar, +1h, +8h, +1 día, Ir al próximo. Panel `data-taller-debug` muestra `resumen_avance` tras avanzar.
- El Cotilleo: `VistaCotilleoV3` lee canal `cotilleo` del buzón (no solo `diario`).
- Misiones/peticiones: en `partida.estado`, aún no hay panel dedicado en PLAY V3 (contrato C2).


---

## Nueva partida (jugador) vs taller

- Experiencia jugador: `play.php`. Config `juego_v1`. No usa `playtest_01`.
- Taller: `play.php?taller=1` enseña +1h / +8h / +1 día / Ir al próximo / UI anterior. Laboratorio 8 vecinos: `play.php?taller=1&lab=1`.
- `estado.tutorial`: si `activo`, C2 señala `zona` (`buzon` | `vecinos` | `organizar`) con `pista` (copy placeholder). `sugerencia` es un plan que el motor real admite. Al completar, `activo=false` en la misma partida.
- Organizar: no enviar la misma persona dos veces. `causa` `misma_persona` | `aun_no_se_conocen` | `ya_se_conocen` | `todavia_no`. El motor trae `mensaje_ui` placeholder; C2 puede reescribir voz.

