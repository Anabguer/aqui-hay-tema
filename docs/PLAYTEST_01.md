# PLAYTEST_01

Versión congelada para el primer playtest real de Neni.

No es producción. No es estética final. No abre sistemas nuevos.

## Cómo volver a esta versión

```
git checkout PLAYTEST_01
```

El hash exacto queda en el tag `PLAYTEST_01` (`git rev-parse PLAYTEST_01`).

## Fecha

2026-08-18

## Schema

`schema_version`: **2**

## Config de partida

`playtest_01` (seed sugerida `playtest-01`)

- Rocío (`per_i03`) — piloto día 1
- QA Valid (`per_qa_valid`) — **NO CANON**, no es B ni C
- Solo cafetería operativa
- Solo Bloque A

`play.php` crea partidas nuevas con esa config.

## Features activas (`data/configs/features.json`)

| Flag | Valor |
| --- | --- |
| mapa_presencia_enabled | true |
| encuentros_enabled | true |
| expression_resolver_enabled | true |
| debug_tools_enabled | true (solo vía `dev.php` + gate) |
| buzon_enabled | false |
| diario_enabled | false |
| npc_autonomy_enabled | false |
| economy_enabled | false |
| offline_events_enabled | false |
| narrative_reactions_enabled | false |
| discovery_enabled | false |
| emotional_state_from_events_enabled | false |
| consequences_enabled | false |

Discovery **de proyección** en ficha sí se usa (políticas). El flag `discovery_enabled` no abre un sistema nuevo de secretos.

## Placeholders conocidos

- Personaje **QA Valid** (nombre técnico a propósito)
- UI marcada `UI provisional · PLAYTEST_01`
- Deltas de encuentro placeholder (`+1` / `0`)
- Retratos: fallback si no hay pack
- Buzón y diario visibles pero vacíos (flags apagados)
- Parque / biblioteca en mapa, no operativos
- Copy de resultado funcional, no narrativo

## Limitaciones conocidas

- Un solo lugar jugable (cafetería)
- Dos residentes, no el trío B/C
- Sin economía, sin compatibilidad real, sin narrativa
- DEV no forma parte del recorrido; diagnóstico solo con gate
- UI densa / fea a propósito: observar qué busca Neni
- `partida.inspeccionar` sigue devolviendo JSON amplio (pestaña red); no es la UI

## Recorrido de Neni

1. `play.php` (o landing → Abrir partida)
2. Nueva partida si hace falta
3. No usar `dev.php`

## Cómo reiniciar

En `play.php`: **Nueva partida** (confirma). Crea un `partida_id` nuevo con config `playtest_01` y seed `playtest-01`.

## Diagnóstico si algo falla

En la UI normal solo está la línea de estado (`schema v2 · part_…`).

Si hay que bajar más: Carlos, con gate DEV (`src/dev.local.php` o `AHT_DEV=1`), abre `dev.php` y exporta diagnóstico. Incluye `partida_id`, seed, schema, reloj y eventos recientes. No forma parte del recorrido de Neni.

## Despliegue (no ejecutado)

Hay **dos carpetas** distintas. No se ha copiado ni sustituido nada:

| Carpeta | Qué es |
| --- | --- |
| `W:\juegos\aqui-hay-tema` | Puerta del catálogo (`status: en-diseno`). «Aún no se juega el bucle». Slug `aqui-hay-tema`. |
| `W:\juegos\Aqui hay tema` | Motor jugable PLAYTEST_01 (esta). |

La URL documentada `/juegos/aqui-hay-tema/` apunta a la **puerta**, no a este motor. No existe una ruta de staging segura ya creada para AQUÍ HAY TEMA (el patrón `47-destellos-staging` es de otro juego).

**No se ha desplegado.** Para subirlo a red (PC + móvil de Neni) hace falta que Neni elija una ruta que **no pise** la puerta ni otros juegos, por ejemplo una carpeta/slug nueva que ella nombre. Hasta entonces, prueba local: `play.php` en esta carpeta.

No copiar `dev.local.php` ni JSON de `data/partidas/` al destino. Crear `data/partidas/` escribible en el servidor.
