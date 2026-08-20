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
| Organizar | `encuentro.tipos_permitidos` + `encuentro.proponer` |

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

Fase 1: hay tema = participante de encuentro `proximo` o `en_curso` en ese lugar. No se lee romance.

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
