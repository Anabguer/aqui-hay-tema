# Documento Maestro V3 — Aquí Hay Tema

> Versión consolidada en repo para ejecución de alineación V3 (2026-08-21).
> Fuente: contenido recibido de Neni + decisiones cerradas 19–21/08/2026.
> **Addendum canon V3** al final supersede cualquier contradicción con textos anteriores.

## Identidad del producto

Aquí Hay Tema es un juego social de gestión de relaciones en un pueblo ficticio.
Celestine (la jugadora) observa, organiza encuentros y descubre a los vecinos progresivamente.

## Población

- Máximo **24 vecinos** en producto.
- Arranque: **3 personajes aleatorios** al iniciar partida.
- Tras tutorial día 1: **5 incorporaciones aleatorias** → total **8**.
- Llegadas posteriores: un candidato activo, buzón, aceptar/rechazar; espaciado creciente (curva V3).
- **Sin pantalla Residencias** ni bloques A/B/C visibles. Celestine apunta → **Vecinos** (`N de 24 vecinos`).

## Identidad vs personalidad

- **Identidad Pxxx** (catálogo): retrato, asset visual, género, edad base. Fija por personaje.
- **Personalidad de partida**: hobbies, rasgos, preferencias, estilo social, etc. **Generada por seed al entrar en la partida** y persistida en `runtime.perfil_partida`.
- **No** fijar hobbies/carácter/preferencias sociales en la ficha Pxxx del catálogo.
- **Runtime evolutivo**: emoción, relaciones, discovery — separado de personalidad base.

## Descubrimiento

- Progresivo: Celestine no ve ficha completa al inicio.
- Sin contador tipo Pokédex.

## Mapa y lugares

### Los 9 lugares canónicos (lista cerrada)

1. Cafetería · 2. Biblioteca · 3. Gimnasio · 4. Restaurante · 5. Parque · 6. Bar · 7. Cine · 8. Discoteca · 9. Bingo

- Mapa = **una ilustración fija** con **9 zonas** clicables (`mapa_zonas.json`).
- **Todos disponibles desde el inicio**. Sin compra, sin desbloqueo, sin progresión EN OBRAS.
- Tokens de presencia visibles en zonas (emoción, hay_tema).

### Legacy descartado como destinos

Tienda, Arcade/Recreativo, Karaoke, Spa, Picnic, Mirador — **no** destinos jugables, desbloqueables ni comprables.
Asset EN OBRAS puede existir en repo; **no** es mecánica de producto.

## Economía

- **Sin dinero** ni economía visible en producto V3.
- Sin compra de edificios ni bloques de vivienda.

## Vida del Pueblo

- Motor activo en partida canónica.
- Corazón en HUD + efectos de estado del pueblo.

## Buzón vs Diario

- **MENSAJITOS (buzón)**: decisiones importantes, candidatos de llegada, propuestas.
- **Cotilleo/Diario**: rumores, eventos sociales, registro narrativo.

## Encuentros y relaciones

- Resultado **individual** por participante.
- Emociones: ALEGRE, NEUTRAL, TRISTE, ENFADADO.
- Planes/organizar encuentros integrados con mapa de 9 destinos.
- Aforo y horarios por destino (solo los 9 canónicos).

## Misiones diarias V3

- **Permanecen** como objetivos sociales opcionales (hasta 3/día).
- **Sin dinero** ni encargos extra económicos (legacy documental, no implementar).
- Recompensa principal: pequeño aporte a **Vida del Pueblo** (`min(3, 1+floor(exigencia/50))`, cap +4/día).
- Caducada/ignorada: **sin castigo** (0 Vida).
- Discovery opcional ~30% en familias elegibles.
- Distinto de peticiones/historias multi-día (B4).

## Herramientas dev

- Fuera de la UI del jugador normal (`?lab=1`, playtest interno).

## Pantalla canónica

- `play.php` — producto real. No demos paralelos.

---

## Addendum canon V3 (21/08/2026 — supersede textos anteriores)

| Tema antiguo (supersedido) | Canon V3 vigente |
|---|---|
| 6 complejos / 14 destinos | Solo **9 lugares** planos en mapa único |
| Compra/desbloqueo lugares, EN OBRAS progresión | **Eliminado** — 9 siempre abiertos |
| Economía, dinero HUD, compra bloques A/B/C | **Eliminado** — cap 24 fija, pool opaco |
| Residencias UI, tabs A/B/C | **Eliminado** — solo Vecinos |
| Capacidad 48 (16×3) | **24** vecinos máximo |
| Roster fijo juego_v1 | 3+5 aleatorios → 8, luego curva llegadas |
| Hobbies fijos en Pxxx | Personalidad generada por partida |
| Encargos extra económicos | **No implementar** |
| Penalización misión caducada | **0** — sin castigo |

**Jerarquía:** este addendum + `docs/PLAN_ALINEACION_V3.md` > textos Maestro anteriores > semilla Cupido Cutre (tono válido; cifras sustituidas).
