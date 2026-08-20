# DIAGNÓSTICO POST-REVISIÓN NENI

Generado: 2026-08-20T16:04:14+00:00

## A. Voluntad

Canonizado **media geométrica** (`voluntad.resolucion_plan`). Tests: `tests/voluntad_media_geometrica_test.php`.

## B–C. Embudo llegadas y crecimiento

| perfil | días | gen | visto | ace | rech | exp | lleg | pop_i→f | días entre (med) |
|---|---|---|---|---|---|---|---|---|---|
| activa | 30 | 8 | 8 | 5 | 3 | 0 | 5 | 8→13 | 3 |
| activa | 30 | 7 | 7 | 5 | 2 | 0 | 5 | 8→13 | 2 |
| activa | 100 | 11 | 11 | 8 | 3 | 0 | 8 | 8→16 | 4.5 |
| activa | 100 | 8 | 8 | 8 | 0 | 0 | 8 | 8→16 | 3 |
| activa | 365 | 11 | 11 | 8 | 3 | 0 | 8 | 8→16 | 2 |
| normal | 30 | 7 | 7 | 5 | 2 | 0 | 5 | 8→13 | 2.5 |
| normal | 30 | 5 | 5 | 4 | 1 | 0 | 4 | 8→12 | 3 |
| normal | 100 | 9 | 9 | 8 | 1 | 0 | 8 | 8→16 | 4 |
| normal | 100 | 9 | 9 | 8 | 1 | 0 | 8 | 8→16 | 2.5 |
| normal | 365 | 10 | 10 | 8 | 2 | 0 | 8 | 8→16 | 9 |
| torpe | 30 | 7 | 5 | 4 | 1 | 2 | 4 | 8→12 | 3.5 |
| torpe | 30 | 10 | 9 | 5 | 4 | 1 | 5 | 8→13 | 2 |
| torpe | 100 | 21 | 13 | 8 | 5 | 8 | 8 | 8→16 | 3 |
| torpe | 100 | 16 | 11 | 6 | 5 | 5 | 6 | 8→14 | 4 |
| torpe | 365 | 17 | 16 | 8 | 8 | 1 | 8 | 8→16 | 3 |
| inactiva | 30 | 8 | 0 | 0 | 0 | 8 | 0 | 8→8 | 3 |
| inactiva | 30 | 7 | 0 | 0 | 0 | 7 | 0 | 8→8 | 4.5 |
| inactiva | 100 | 20 | 1 | 0 | 1 | 18 | 0 | 8→8 | 4 |
| inactiva | 100 | 19 | 1 | 0 | 1 | 18 | 0 | 8→8 | 3.5 |
| inactiva | 365 | 78 | 3 | 1 | 2 | 74 | 1 | 8→9 | 4 |

**Veredicto crecimiento: C (cuello de botella de integración)** + componente B (bots).

El lab B llamaba intentarOfrecer con p diaria completa cada día. Los horizontes usaban avanzarReloj(24h) con una sola tirada a p_hora≈p/24 (bug de composición). Corregido: tick recibe horas y compone 1-(1-p)^(h/24). Además bots inactivos dejan expirar; torpe rechaza más. No se han tocado p_base/p_por_hueco.

Expectativa jugadora humana razonable: Con atención ~85% y aceptación ~70% sobre ofertas, Bloque A (8→16) encaja en ~30–80 días según RNG/cooldown, no en 365 estancado.

## D. Discovery

**BALANCE_PENDIENTE_CATALOGO_PERSONAJES** — no recalibrado.

{
    "residente": "Carmen",
    "snapshots_n": {
        "dia_1": 2,
        "dia_10": 4,
        "dia_30": 4,
        "dia_100": 5
    },
    "BALANCE_PENDIENTE_CATALOGO_PERSONAJES": true,
    "nota": "No recalibrar. Catálogo amplio pendiente; curva longitudinal a diseñar después."
}

## E. Cotilleo

- Pop 8 / 30d: coincidencias=5, dignas_patron=0, publicados=0
- Pop 16 / 30d: coincidencias=28, dignas_patron=0, publicados=0
- Pop 32 / 30d: coincidencias=134, dignas_patron=3, publicados=4

Clasificación: **CONTENIDO/BALANCE** (no bug de duplicados). Filtro `cotilleo_min_dias_par_lugar=3` en ventana 7. `NPC_AUTONOMO_PLAN` → null. Faltan familias publicables (no implementar ahora).

## F. Bugs corregidos

1. Voluntad plan: producto → media geométrica (decisión cerrada).
2. Llegadas: avanzar N horas aplicaba p/24 una sola vez → composición 1-(1-p)^(N/24).

## G. Siguen bloqueados

- **RELACIONES**: PARCIAL
- **MARCHAS**: BLOQUEADO_DECISION
- **ECONOMIA**: NO_IMPLEMENTADO (encargo aparte)
- **DISCOVERY_RITMO**: BALANCE_PENDIENTE_CATALOGO_PERSONAJES
- **COTILLEO_VOLUMEN**: BALANCE/CONTENIDO — filtro patrón ≥3 días; muchas familias de evento no publican
