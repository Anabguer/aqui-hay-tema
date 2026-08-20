# POST_GATE_REPORT

Generado: 2026-08-20T15:09:39+00:00

## Resumen

- PASS: 11
- FAIL: 0
- PARCIAL: 1
- NO_IMPLEMENTADO: 1
- BLOQUEADO_DECISION: 2

## ¿DEJARÍA YA ENTRAR A NENI AL PLAYTEST?

**NO**

- Voluntad de plan (p²) sigue abierta: BLOQUEADO_DECISION_VOLUNTAD
- Marchas sin contrato numérico cerrado.
- El bucle longitudinal básico (3→8 + candidatos) ya se puede castigar en automático.
- Sigue abierto el balance de Organizar (plantones ≈p²) — Neni lo sentiría como bug de diseño.
- Discovery longitudinal y marchas/economía aún no dan una experiencia “completa” de año.

## A. Tutorial 3→8 — PASS

<details><summary>JSON</summary>

```json
{
    "status": "PASS",
    "n_inicial": 3,
    "ids_iniciales": [
        "per_i03",
        "per_p001",
        "per_p002"
    ],
    "n_fin_dia1": 8,
    "tutorial_completado": true,
    "modo_llegadas": "normal",
    "incorporaciones_tutorial": [
        {
            "catalog_id": "per_p003",
            "vivienda_id": "A04",
            "via": "tutorial"
        },
        {
            "catalog_id": "per_p004",
            "vivienda_id": "A05",
            "via": "tutorial"
        },
        {
            "catalog_id": "per_p005",
            "vivienda_id": "A06",
            "via": "tutorial"
        },
        {
            "catalog_id": "per_p006",
            "vivienda_id": "A07",
            "via": "tutorial"
        },
        {
            "catalog_id": "per_p007",
            "vivienda_id": "A08",
            "via": "tutorial"
        }
    ]
}
```
</details>

## B. Llegadas — PASS

<details><summary>JSON</summary>

```json
{
    "status": "PASS",
    "horizonte_dias": 100,
    "seeds": [
        "lg-a",
        "lg-b",
        "lg-c",
        "lg-d",
        "lg-e"
    ],
    "distribucion": {
        "A": {
            "lg-a": {
                "ofrecidos": 8,
                "aceptados": 8,
                "rechazados": 0,
                "expirados": 0,
                "llegados": 8,
                "dias_hasta_primero": 4,
                "pop_final": 16,
                "pop_inicial": 8,
                "cap": 16
            },
            "lg-b": {
                "ofrecidos": 12,
                "aceptados": 8,
                "rechazados": 4,
                "expirados": 0,
                "llegados": 8,
                "dias_hasta_primero": 2,
                "pop_final": 16,
                "pop_inicial": 8,
                "cap": 16
            },
            "lg-c": {
                "ofrecidos": 11,
                "aceptados": 8,
                "rechazados": 3,
                "expirados": 0,
                "llegados": 8,
                "dias_hasta_primero": 3,
                "pop_final": 16,
                "pop_inicial": 8,
                "cap": 16
            },
            "lg-d": {
                "ofrecidos": 9,
                "aceptados": 8,
                "rechazados": 1,
                "expirados": 0,
                "llegados": 8,
                "dias_hasta_primero": 2,
                "pop_final": 16,
                "pop_inicial": 8,
                "cap": 16
            },
            "lg-e": {
                "ofrecidos": 9,
                "aceptados": 8,
                "rechazados": 1,
                "expirados": 0,
                "llegados": 8,
                "dias_hasta_primero": 2,
                "pop_final": 16,
                "pop_inicial": 8,
                "cap": 16
            }
        },
        "A+B": {
            "lg-a": {
                "ofrecidos": 37,
                "aceptados": 22,
                "rechazados": 15,
                "expirados": 0,
                "llegados": 22,
                "dias_hasta_primero": 3,
                "pop_final": 30,
                "pop_inicial": 8,
                "cap": 32
            },
            "lg-b": {
                "ofrecidos": 35,
                "aceptados": 24,
                "rechazados": 11,
                "expirados": 0,
                "llegados": 24,
                "dias_hasta_primero": 1,
                "pop_final": 32,
                "pop_inicial": 8,
                "cap": 32
            },
            "lg-c": {
                "ofrecidos": 30,
                "aceptados": 24,
                "rechazados": 6,
                "expirados": 0,
                "llegados": 24,
                "dias_hasta_primero": 1,
                "pop_final": 32,
                "pop_inicial": 8,
                "cap": 32
            },
            "lg-d": {
                "ofrecidos": 31,
                "aceptados": 24,
                "rechazados": 7,
                "expirados": 0,
                "llegados": 24,
                "dias_hasta_primero": 6,
                "pop_final": 32,
                "pop_inicial": 8,
                "cap": 32
            },
            "lg-e": {
                "ofrecidos": 34,
                "aceptados": 24,
                "rechazados": 10,
                "expirados": 0,
                "llegados": 24,
                "dias_hasta_primero": 1,
                "pop_final": 32,
                "pop_inicial": 8,
                "cap": 32
            }
        },
        "A+B+C": {
            "lg-a": {
                "ofrecidos": 41,
                "aceptados": 27,
                "rechazados": 14,
                "expirados": 0,
                "llegados": 27,
                "dias_hasta_primero": 1,
                "pop_final": 35,
                "pop_inicial": 8,
                "cap": 48
            },
            "lg-b": {
                "ofrecidos": 48,
                "aceptados": 33,
                "rechazados": 15,
                "expirados": 0,
                "llegados": 33,
                "dias_hasta_primero": 2,
                "pop_final": 41,
                "pop_inicial": 8,
                "cap": 48
            },
            "lg-c": {
                "ofrecidos": 48,
                "aceptados": 33,
                "rechazados": 15,
                "expirados": 0,
                "llegados": 33,
                "dias_hasta_primero": 6,
                "pop_final": 41,
                "pop_inicial": 8,
                "cap": 48
            },
            "lg-d": {
                "ofrecidos": 45,
                "aceptados": 30,
                "rechazados": 15,
                "expirados": 0,
                "llegados": 30,
                "dias_hasta_primero": 1,
                "pop_final": 38,
                "pop_inicial": 8,
                "cap": 48
            },
            "lg-e": {
                "ofrecidos": 42,
                "aceptados": 27,
                "rechazados": 15,
                "expirados": 0,
                "llegados": 27,
                "dias_hasta_primero": 1,
                "pop_final": 35,
                "pop_inicial": 8,
                "cap": 48
            }
        }
    },
    "nota": "Un candidato; buzón; aceptar→espera 1–10 min→vivienda; rechazo excluye cara."
}
```
</details>

## C. Voluntad — BLOQUEADO_DECISION

Sistema actual **no modificado** (sigue pA×pB).

### Recomendación lab

`media_geometrica`

- 70/70 se siente razonable (~p individual), no ~0.40.
- 95/20 sigue castigado por el miembro débil (no se comporta como 50/50).
- 20/20 permanece muy bajo.
- 95/95 alto sin llegar a 1.0.
- Mantiene voluntad individual en el cálculo (vía pA y pB), sin promediar scores.

### Matriz media_geometrica vs producto (p_plan)

| scores | producto | geométrica | min_suave |
|---|---|---|---|
| 20/20 | 0.064 | 0.252 | 0.167 |
| 20/50 | 0.129 | 0.358 | 0.196 |
| 20/80 | 0.194 | 0.44 | 0.226 |
| 40/40 | 0.18 | 0.424 | 0.314 |
| 40/70 | 0.289 | 0.538 | 0.363 |
| 50/50 | 0.26 | 0.51 | 0.398 |
| 50/80 | 0.392 | 0.626 | 0.457 |
| 70/70 | 0.465 | 0.682 | 0.584 |
| 70/90 | 0.582 | 0.763 | 0.637 |
| 90/90 | 0.729 | 0.854 | 0.798 |
| 95/95 | 0.805 | 0.897 | 0.855 |
| 95/20 | 0.226 | 0.475 | 0.24 |

<details><summary>JSON</summary>

```json
{
    "status": "BLOQUEADO_DECISION",
    "codigo": "BLOQUEADO_DECISION_VOLUNTAD",
    "sistema_actual": "producto (pA×pB) — NO cambiado",
    "lab": {
        "formulas": {
            "producto": {
                "matriz": [
                    {
                        "scores": [
                            20,
                            20
                        ],
                        "pA": 0.252,
                        "pB": 0.252,
                        "p_plan": 0.064,
                        "tasa_obs": 0.051,
                        "penaliza_min": 0.188
                    },
                    {
                        "scores": [
                            20,
                            50
                        ],
                        "pA": 0.252,
                        "pB": 0.51,
                        "p_plan": 0.129,
                        "tasa_obs": 0.124,
                        "penaliza_min": 0.123
                    },
                    {
                        "scores": [
                            20,
                            80
                        ],
                        "pA": 0.252,
                        "pB": 0.768,
                        "p_plan": 0.194,
                        "tasa_obs": 0.203,
                        "penaliza_min": 0.058
                    },
                    {
                        "scores": [
                            40,
                            40
                        ],
                        "pA": 0.424,
                        "pB": 0.424,
                        "p_plan": 0.18,
                        "tasa_obs": 0.19,
                        "penaliza_min": 0.244
                    },
                    {
                        "scores": [
                            40,
                            70
                        ],
                        "pA": 0.424,
                        "pB": 0.682,
                        "p_plan": 0.289,
                        "tasa_obs": 0.298,
                        "penaliza_min": 0.135
                    },
                    {
                        "scores": [
                            50,
                            50
                        ],
                        "pA": 0.51,
                        "pB": 0.51,
                        "p_plan": 0.26,
                        "tasa_obs": 0.266,
                        "penaliza_min": 0.25
                    },
                    {
                        "scores": [
                            50,
                            80
                        ],
                        "pA": 0.51,
                        "pB": 0.768,
                        "p_plan": 0.392,
                        "tasa_obs": 0.407,
                        "penaliza_min": 0.118
                    },
                    {
                        "scores": [
                            70,
                            70
                        ],
                        "pA": 0.682,
                        "pB": 0.682,
                        "p_plan": 0.465,
                        "tasa_obs": 0.462,
                        "penaliza_min": 0.217
                    },
                    {
                        "scores": [
                            70,
                            90
                        ],
                        "pA": 0.682,
                        "pB": 0.854,
                        "p_plan": 0.582,
                        "tasa_obs": 0.596,
                        "penaliza_min": 0.1
                    },
                    {
                        "scores": [
                            90,
                            90
                        ],
                        "pA": 0.854,
                        "pB": 0.854,
                        "p_plan": 0.729,
                        "tasa_obs": 0.718,
                        "penaliza_min": 0.125
                    },
                    {
                        "scores": [
                            95,
                            95
                        ],
                        "pA": 0.897,
                        "pB": 0.897,
                        "p_plan": 0.805,
                        "tasa_obs": 0.802,
                        "penaliza_min": 0.092
                    },
                    {
                        "scores": [
                            95,
                            20
                        ],
                        "pA": 0.897,
                        "pB": 0.252,
                        "p_plan": 0.226,
                        "tasa_obs": 0.215,
                        "penaliza_min": 0.026
                    }
                ],
                "p_70_70": 0.4651239999999999,
                "p_95_95": 0.8046089999999998,
                "p_95_20": 0.22604399999999997,
                "p_20_20": 0.063504
            },
            "media_geometrica": {
                "matriz": [
                    {
                        "scores": [
                            20,
                            20
                        ],
                        "pA": 0.252,
                        "pB": 0.252,
                        "p_plan": 0.252,
                        "tasa_obs": 0.245,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            20,
                            50
                        ],
                        "pA": 0.252,
                        "pB": 0.51,
                        "p_plan": 0.358,
                        "tasa_obs": 0.358,
                        "penaliza_min": -0.106
                    },
                    {
                        "scores": [
                            20,
                            80
                        ],
                        "pA": 0.252,
                        "pB": 0.768,
                        "p_plan": 0.44,
                        "tasa_obs": 0.446,
                        "penaliza_min": -0.188
                    },
                    {
                        "scores": [
                            40,
                            40
                        ],
                        "pA": 0.424,
                        "pB": 0.424,
                        "p_plan": 0.424,
                        "tasa_obs": 0.427,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            40,
                            70
                        ],
                        "pA": 0.424,
                        "pB": 0.682,
                        "p_plan": 0.538,
                        "tasa_obs": 0.524,
                        "penaliza_min": -0.114
                    },
                    {
                        "scores": [
                            50,
                            50
                        ],
                        "pA": 0.51,
                        "pB": 0.51,
                        "p_plan": 0.51,
                        "tasa_obs": 0.505,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            50,
                            80
                        ],
                        "pA": 0.51,
                        "pB": 0.768,
                        "p_plan": 0.626,
                        "tasa_obs": 0.622,
                        "penaliza_min": -0.116
                    },
                    {
                        "scores": [
                            70,
                            70
                        ],
                        "pA": 0.682,
                        "pB": 0.682,
                        "p_plan": 0.682,
                        "tasa_obs": 0.686,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            70,
                            90
                        ],
                        "pA": 0.682,
                        "pB": 0.854,
                        "p_plan": 0.763,
                        "tasa_obs": 0.763,
                        "penaliza_min": -0.081
                    },
                    {
                        "scores": [
                            90,
                            90
                        ],
                        "pA": 0.854,
                        "pB": 0.854,
                        "p_plan": 0.854,
                        "tasa_obs": 0.856,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            95,
                            95
                        ],
                        "pA": 0.897,
                        "pB": 0.897,
                        "p_plan": 0.897,
                        "tasa_obs": 0.902,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            95,
                            20
                        ],
                        "pA": 0.897,
                        "pB": 0.252,
                        "p_plan": 0.475,
                        "tasa_obs": 0.464,
                        "penaliza_min": -0.223
                    }
                ],
                "p_70_70": 0.6819999999999999,
                "p_95_95": 0.8969999999999999,
                "p_95_20": 0.47544084805578074,
                "p_20_20": 0.252
            },
            "minimo": {
                "matriz": [
                    {
                        "scores": [
                            20,
                            20
                        ],
                        "pA": 0.252,
                        "pB": 0.252,
                        "p_plan": 0.252,
                        "tasa_obs": 0.256,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            20,
                            50
                        ],
                        "pA": 0.252,
                        "pB": 0.51,
                        "p_plan": 0.252,
                        "tasa_obs": 0.242,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            20,
                            80
                        ],
                        "pA": 0.252,
                        "pB": 0.768,
                        "p_plan": 0.252,
                        "tasa_obs": 0.257,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            40,
                            40
                        ],
                        "pA": 0.424,
                        "pB": 0.424,
                        "p_plan": 0.424,
                        "tasa_obs": 0.42,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            40,
                            70
                        ],
                        "pA": 0.424,
                        "pB": 0.682,
                        "p_plan": 0.424,
                        "tasa_obs": 0.403,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            50,
                            50
                        ],
                        "pA": 0.51,
                        "pB": 0.51,
                        "p_plan": 0.51,
                        "tasa_obs": 0.506,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            50,
                            80
                        ],
                        "pA": 0.51,
                        "pB": 0.768,
                        "p_plan": 0.51,
                        "tasa_obs": 0.495,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            70,
                            70
                        ],
                        "pA": 0.682,
                        "pB": 0.682,
                        "p_plan": 0.682,
                        "tasa_obs": 0.721,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            70,
                            90
                        ],
                        "pA": 0.682,
                        "pB": 0.854,
                        "p_plan": 0.682,
                        "tasa_obs": 0.666,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            90,
                            90
                        ],
                        "pA": 0.854,
                        "pB": 0.854,
                        "p_plan": 0.854,
                        "tasa_obs": 0.862,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            95,
                            95
                        ],
                        "pA": 0.897,
                        "pB": 0.897,
                        "p_plan": 0.897,
                        "tasa_obs": 0.894,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            95,
                            20
                        ],
                        "pA": 0.897,
                        "pB": 0.252,
                        "p_plan": 0.252,
                        "tasa_obs": 0.25,
                        "penaliza_min": 0
                    }
                ],
                "p_70_70": 0.6819999999999999,
                "p_95_95": 0.8969999999999999,
                "p_95_20": 0.252,
                "p_20_20": 0.252
            },
            "min_suave": {
                "matriz": [
                    {
                        "scores": [
                            20,
                            20
                        ],
                        "pA": 0.252,
                        "pB": 0.252,
                        "p_plan": 0.167,
                        "tasa_obs": 0.186,
                        "penaliza_min": 0.085
                    },
                    {
                        "scores": [
                            20,
                            50
                        ],
                        "pA": 0.252,
                        "pB": 0.51,
                        "p_plan": 0.196,
                        "tasa_obs": 0.178,
                        "penaliza_min": 0.056
                    },
                    {
                        "scores": [
                            20,
                            80
                        ],
                        "pA": 0.252,
                        "pB": 0.768,
                        "p_plan": 0.226,
                        "tasa_obs": 0.222,
                        "penaliza_min": 0.026
                    },
                    {
                        "scores": [
                            40,
                            40
                        ],
                        "pA": 0.424,
                        "pB": 0.424,
                        "p_plan": 0.314,
                        "tasa_obs": 0.326,
                        "penaliza_min": 0.11
                    },
                    {
                        "scores": [
                            40,
                            70
                        ],
                        "pA": 0.424,
                        "pB": 0.682,
                        "p_plan": 0.363,
                        "tasa_obs": 0.36,
                        "penaliza_min": 0.061
                    },
                    {
                        "scores": [
                            50,
                            50
                        ],
                        "pA": 0.51,
                        "pB": 0.51,
                        "p_plan": 0.398,
                        "tasa_obs": 0.398,
                        "penaliza_min": 0.112
                    },
                    {
                        "scores": [
                            50,
                            80
                        ],
                        "pA": 0.51,
                        "pB": 0.768,
                        "p_plan": 0.457,
                        "tasa_obs": 0.441,
                        "penaliza_min": 0.053
                    },
                    {
                        "scores": [
                            70,
                            70
                        ],
                        "pA": 0.682,
                        "pB": 0.682,
                        "p_plan": 0.584,
                        "tasa_obs": 0.595,
                        "penaliza_min": 0.098
                    },
                    {
                        "scores": [
                            70,
                            90
                        ],
                        "pA": 0.682,
                        "pB": 0.854,
                        "p_plan": 0.637,
                        "tasa_obs": 0.642,
                        "penaliza_min": 0.045
                    },
                    {
                        "scores": [
                            90,
                            90
                        ],
                        "pA": 0.854,
                        "pB": 0.854,
                        "p_plan": 0.798,
                        "tasa_obs": 0.794,
                        "penaliza_min": 0.056
                    },
                    {
                        "scores": [
                            95,
                            95
                        ],
                        "pA": 0.897,
                        "pB": 0.897,
                        "p_plan": 0.855,
                        "tasa_obs": 0.862,
                        "penaliza_min": 0.042
                    },
                    {
                        "scores": [
                            95,
                            20
                        ],
                        "pA": 0.897,
                        "pB": 0.252,
                        "p_plan": 0.24,
                        "tasa_obs": 0.247,
                        "penaliza_min": 0.012
                    }
                ],
                "p_70_70": 0.5844058,
                "p_95_95": 0.8554240499999999,
                "p_95_20": 0.2403198,
                "p_20_20": 0.1671768
            },
            "umbral_dual": {
                "matriz": [
                    {
                        "scores": [
                            20,
                            20
                        ],
                        "pA": 0.252,
                        "pB": 0.252,
                        "p_plan": 0.064,
                        "tasa_obs": 0.06,
                        "penaliza_min": 0.188
                    },
                    {
                        "scores": [
                            20,
                            50
                        ],
                        "pA": 0.252,
                        "pB": 0.51,
                        "p_plan": 0.129,
                        "tasa_obs": 0.146,
                        "penaliza_min": 0.123
                    },
                    {
                        "scores": [
                            20,
                            80
                        ],
                        "pA": 0.252,
                        "pB": 0.768,
                        "p_plan": 0.194,
                        "tasa_obs": 0.188,
                        "penaliza_min": 0.058
                    },
                    {
                        "scores": [
                            40,
                            40
                        ],
                        "pA": 0.424,
                        "pB": 0.424,
                        "p_plan": 0.18,
                        "tasa_obs": 0.19,
                        "penaliza_min": 0.244
                    },
                    {
                        "scores": [
                            40,
                            70
                        ],
                        "pA": 0.424,
                        "pB": 0.682,
                        "p_plan": 0.289,
                        "tasa_obs": 0.28,
                        "penaliza_min": 0.135
                    },
                    {
                        "scores": [
                            50,
                            50
                        ],
                        "pA": 0.51,
                        "pB": 0.51,
                        "p_plan": 0.26,
                        "tasa_obs": 0.244,
                        "penaliza_min": 0.25
                    },
                    {
                        "scores": [
                            50,
                            80
                        ],
                        "pA": 0.51,
                        "pB": 0.768,
                        "p_plan": 0.392,
                        "tasa_obs": 0.396,
                        "penaliza_min": 0.118
                    },
                    {
                        "scores": [
                            70,
                            70
                        ],
                        "pA": 0.682,
                        "pB": 0.682,
                        "p_plan": 0.682,
                        "tasa_obs": 0.677,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            70,
                            90
                        ],
                        "pA": 0.682,
                        "pB": 0.854,
                        "p_plan": 0.763,
                        "tasa_obs": 0.76,
                        "penaliza_min": -0.081
                    },
                    {
                        "scores": [
                            90,
                            90
                        ],
                        "pA": 0.854,
                        "pB": 0.854,
                        "p_plan": 0.854,
                        "tasa_obs": 0.856,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            95,
                            95
                        ],
                        "pA": 0.897,
                        "pB": 0.897,
                        "p_plan": 0.897,
                        "tasa_obs": 0.9,
                        "penaliza_min": 0
                    },
                    {
                        "scores": [
                            95,
                            20
                        ],
                        "pA": 0.897,
                        "pB": 0.252,
                        "p_plan": 0.226,
                        "tasa_obs": 0.226,
                        "penaliza_min": 0.026
                    }
                ],
                "p_70_70": 0.6819999999999999,
                "p_95_95": 0.8969999999999999,
                "p_95_20": 0.22604399999999997,
                "p_20_20": 0.063504
            }
        },
        "recomendacion": {
            "formula": "media_geometrica",
            "motivos": [
                "70\/70 se siente razonable (~p individual), no ~0.40.",
                "95\/20 sigue castigado por el miembro débil (no se comporta como 50\/50).",
                "20\/20 permanece muy bajo.",
                "95\/95 alto sin llegar a 1.0.",
                "Mantiene voluntad individual en el cálculo (vía pA y pB), sin promediar scores."
            ],
            "no_canonizado": true
        },
        "bloqueado": "BLOQUEADO_DECISION_VOLUNTAD"
    },
    "recomendacion": {
        "formula": "media_geometrica",
        "motivos": [
            "70\/70 se siente razonable (~p individual), no ~0.40.",
            "95\/20 sigue castigado por el miembro débil (no se comporta como 50\/50).",
            "20\/20 permanece muy bajo.",
            "95\/95 alto sin llegar a 1.0.",
            "Mantiene voluntad individual en el cálculo (vía pA y pB), sin promediar scores."
        ],
        "no_canonizado": true
    }
}
```
</details>

## D. Vida autónoma — PASS

<details><summary>JSON</summary>

```json
{
    "status": "PASS",
    "por_poblacion": {
        "8": {
            "pop": 8,
            "persona_dos_sitios": 0,
            "aforo_fallos": 0,
            "cotilleo_msgs_5d": 0,
            "cap": 16
        },
        "16": {
            "pop": 16,
            "persona_dos_sitios": 0,
            "aforo_fallos": 0,
            "cotilleo_msgs_5d": 0,
            "cap": 16
        },
        "32": {
            "pop": 32,
            "persona_dos_sitios": 0,
            "aforo_fallos": 0,
            "cotilleo_msgs_5d": 0,
            "cap": 32
        },
        "48": {
            "pop": 48,
            "persona_dos_sitios": 0,
            "aforo_fallos": 0,
            "cotilleo_msgs_5d": 0,
            "cap": 48
        }
    },
    "dias": 5
}
```
</details>

## E. Encuentros — PASS

Casos: 30 (ver JSON para BEFORE/DECISION/AFTER completos).

- **Carmen + José** @lug_cafeteria → RECHAZA — Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad
- **José + Raúl** @lug_parque → OK — Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Aceptado y programado; deltas sociales vía resolución de encuentro si el reloj lo consumió.
- **Marta + Dani** @lug_biblioteca → RECHAZA — Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad
- **Raúl + Lucía** @lug_cafeteria → RECHAZA — Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad
- **Lucía + Álex** @lug_parque → OK — Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Aceptado y programado; deltas sociales vía resolución de encuentro si el reloj lo consumió.

<details><summary>JSON</summary>

```json
{
    "status": "PASS",
    "n": 30,
    "casos": [
        {
            "antes": {
                "a": "Carmen",
                "b": "José",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_cafeteria",
                "hora": 10
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "José ha rechazado la propuesta: \"Hoy no me da la vida.\"",
                "reacciones": [
                    {
                        "nombre": "Carmen",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    },
                    {
                        "nombre": "José",
                        "decision": "rechaza",
                        "score": null,
                        "p": 0,
                        "motivo": "cooldown_propuesta"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 2,
                "disc_b": 2
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "José",
                "b": "Raúl",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_parque",
                "hora": 11
            },
            "decision": {
                "rechazada": null,
                "clase": null,
                "mensaje_ui": "José y Raúl han quedado el Jueves 20\/08 a las 18:00 en parque.",
                "reacciones": [
                    {
                        "nombre": "José",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    },
                    {
                        "nombre": "Raúl",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    }
                ]
            },
            "despues": {
                "social_ab": 5,
                "social_ba": 5,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 4,
                "disc_b": 2
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Aceptado y programado; deltas sociales vía resolución de encuentro si el reloj lo consumió."
        },
        {
            "antes": {
                "a": "Marta",
                "b": "Dani",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_biblioteca",
                "hora": 12
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "Dani ha rechazado la propuesta: \"Hoy no me da la vida.\"",
                "reacciones": [
                    {
                        "nombre": "Marta",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    },
                    {
                        "nombre": "Dani",
                        "decision": "rechaza",
                        "score": null,
                        "p": 0,
                        "motivo": "cooldown_propuesta"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 2,
                "disc_b": 2
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Raúl",
                "b": "Lucía",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_cafeteria",
                "hora": 13
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "Lucía ha rechazado la propuesta: \"Tengo que poner la lavadora.\"",
                "reacciones": [
                    {
                        "nombre": "Raúl",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    },
                    {
                        "nombre": "Lucía",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 2,
                "disc_b": 2
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Lucía",
                "b": "Álex",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_parque",
                "hora": 14
            },
            "decision": {
                "rechazada": null,
                "clase": null,
                "mensaje_ui": "Lucía y Álex han quedado el Viernes 21\/08 a las 14:00 en parque.",
                "reacciones": [
                    {
                        "nombre": "Lucía",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    },
                    {
                        "nombre": "Álex",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    }
                ]
            },
            "despues": {
                "social_ab": 2,
                "social_ba": 5,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 4,
                "disc_b": 2
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Aceptado y programado; deltas sociales vía resolución de encuentro si el reloj lo consumió."
        },
        {
            "antes": {
                "a": "Dani",
                "b": "Carmen",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_biblioteca",
                "hora": 15
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "Carmen ha rechazado la propuesta: \"Me tengo que pintar las uñas.\"",
                "reacciones": [
                    {
                        "nombre": "Dani",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    },
                    {
                        "nombre": "Carmen",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 2,
                "disc_b": 2
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Álex",
                "b": "Sara",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_cafeteria",
                "hora": 16
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "Sara ha rechazado la propuesta: \"Hoy no me da la vida.\"",
                "reacciones": [
                    {
                        "nombre": "Álex",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    },
                    {
                        "nombre": "Sara",
                        "decision": "rechaza",
                        "score": null,
                        "p": 0,
                        "motivo": "cooldown_propuesta"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 2,
                "disc_b": 2
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Sara",
                "b": "José",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_parque",
                "hora": 17
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "José ha rechazado la propuesta: \"Me tengo que pintar las uñas.\"",
                "reacciones": [
                    {
                        "nombre": "Sara",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    },
                    {
                        "nombre": "José",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 2,
                "disc_b": 4
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Carmen",
                "b": "Raúl",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_biblioteca",
                "hora": 18
            },
            "decision": {
                "rechazada": null,
                "clase": null,
                "mensaje_ui": "Carmen y Raúl han quedado el Domingo 23\/08 a las 18:00 en biblioteca.",
                "reacciones": [
                    {
                        "nombre": "Carmen",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    },
                    {
                        "nombre": "Raúl",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 2,
                "disc_b": 2
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Aceptado y programado; deltas sociales vía resolución de encuentro si el reloj lo consumió."
        },
        {
            "antes": {
                "a": "José",
                "b": "Marta",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_cafeteria",
                "hora": 19
            },
            "decision": {
                "rechazada": null,
                "clase": null,
                "mensaje_ui": "José y Marta han quedado el Domingo 23\/08 a las 19:00 en cafeteria.",
                "reacciones": [
                    {
                        "nombre": "José",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    },
                    {
                        "nombre": "Marta",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 4,
                "disc_b": 2
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Aceptado y programado; deltas sociales vía resolución de encuentro si el reloj lo consumió."
        },
        {
            "antes": {
                "a": "Marta",
                "b": "Lucía",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_parque",
                "hora": 10
            },
            "decision": {
                "rechazada": null,
                "clase": null,
                "mensaje_ui": "Marta y Lucía han quedado el Domingo 23\/08 a las 10:00 en parque.",
                "reacciones": [
                    {
                        "nombre": "Marta",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    },
                    {
                        "nombre": "Lucía",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    }
                ]
            },
            "despues": {
                "social_ab": -4,
                "social_ba": 2,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 4,
                "disc_b": 4
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Aceptado y programado; deltas sociales vía resolución de encuentro si el reloj lo consumió."
        },
        {
            "antes": {
                "a": "Raúl",
                "b": "Álex",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_biblioteca",
                "hora": 11
            },
            "decision": {
                "rechazada": null,
                "clase": null,
                "mensaje_ui": "Raúl y Álex han quedado el Domingo 23\/08 a las 11:00 en biblioteca.",
                "reacciones": [
                    {
                        "nombre": "Raúl",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    },
                    {
                        "nombre": "Álex",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    }
                ]
            },
            "despues": {
                "social_ab": -4,
                "social_ba": 8,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 4,
                "disc_b": 2
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Aceptado y programado; deltas sociales vía resolución de encuentro si el reloj lo consumió."
        },
        {
            "antes": {
                "a": "Lucía",
                "b": "Dani",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_cafeteria",
                "hora": 12
            },
            "decision": {
                "rechazada": null,
                "clase": null,
                "mensaje_ui": "No pueden quedar a esa hora.",
                "reacciones": []
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 4,
                "disc_b": 2
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Aceptado y programado; deltas sociales vía resolución de encuentro si el reloj lo consumió."
        },
        {
            "antes": {
                "a": "Dani",
                "b": "Sara",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_parque",
                "hora": 13
            },
            "decision": {
                "rechazada": null,
                "clase": null,
                "mensaje_ui": "Dani y Sara han quedado el Martes 25\/08 a las 15:00 en parque.",
                "reacciones": [
                    {
                        "nombre": "Dani",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    },
                    {
                        "nombre": "Sara",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    }
                ]
            },
            "despues": {
                "social_ab": 4,
                "social_ba": 4,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 4,
                "disc_b": 2
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Aceptado y programado; deltas sociales vía resolución de encuentro si el reloj lo consumió."
        },
        {
            "antes": {
                "a": "Álex",
                "b": "José",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_biblioteca",
                "hora": 14
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "José ha rechazado la propuesta: \"Hoy no me da la vida.\"",
                "reacciones": [
                    {
                        "nombre": "Álex",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    },
                    {
                        "nombre": "José",
                        "decision": "rechaza",
                        "score": null,
                        "p": 0,
                        "motivo": "cooldown_propuesta"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 2,
                "disc_b": 6
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Sara",
                "b": "Carmen",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_cafeteria",
                "hora": 15
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "Carmen ha rechazado la propuesta: \"Hoy no me da la vida.\"",
                "reacciones": [
                    {
                        "nombre": "Sara",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    },
                    {
                        "nombre": "Carmen",
                        "decision": "rechaza",
                        "score": null,
                        "p": 0,
                        "motivo": "cooldown_propuesta"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 2,
                "disc_b": 4
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Carmen",
                "b": "Marta",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_parque",
                "hora": 16
            },
            "decision": {
                "rechazada": null,
                "clase": null,
                "mensaje_ui": "Carmen y Marta han quedado el Miércoles 26\/08 a las 18:00 en parque.",
                "reacciones": [
                    {
                        "nombre": "Carmen",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    },
                    {
                        "nombre": "Marta",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    }
                ]
            },
            "despues": {
                "social_ab": -4,
                "social_ba": 8,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 6,
                "disc_b": 4
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Aceptado y programado; deltas sociales vía resolución de encuentro si el reloj lo consumió."
        },
        {
            "antes": {
                "a": "José",
                "b": "Lucía",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_biblioteca",
                "hora": 17
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "Lucía ha rechazado la propuesta: \"Hoy no me da la vida.\"",
                "reacciones": [
                    {
                        "nombre": "José",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    },
                    {
                        "nombre": "Lucía",
                        "decision": "rechaza",
                        "score": null,
                        "p": 0,
                        "motivo": "cooldown_propuesta"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 6,
                "disc_b": 4
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Marta",
                "b": "Raúl",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_cafeteria",
                "hora": 18
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "Raúl ha rechazado la propuesta: \"Tengo que dar de comer al gato.\"",
                "reacciones": [
                    {
                        "nombre": "Marta",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    },
                    {
                        "nombre": "Raúl",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 4,
                "disc_b": 4
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Raúl",
                "b": "Dani",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_parque",
                "hora": 19
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "Dani ha rechazado la propuesta: \"Hoy no me da la vida.\"",
                "reacciones": [
                    {
                        "nombre": "Raúl",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    },
                    {
                        "nombre": "Dani",
                        "decision": "rechaza",
                        "score": null,
                        "p": 0,
                        "motivo": "cooldown_propuesta"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 4,
                "disc_b": 4
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Lucía",
                "b": "Sara",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_biblioteca",
                "hora": 10
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "Sara ha rechazado la propuesta: \"Tengo que poner la lavadora.\"",
                "reacciones": [
                    {
                        "nombre": "Lucía",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    },
                    {
                        "nombre": "Sara",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 4,
                "disc_b": 2
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Dani",
                "b": "Álex",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_cafeteria",
                "hora": 11
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "Álex ha rechazado la propuesta: \"Se me hace tarde.\"",
                "reacciones": [
                    {
                        "nombre": "Dani",
                        "decision": "acepta",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_acepta"
                    },
                    {
                        "nombre": "Álex",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 4,
                "disc_b": 2
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Álex",
                "b": "Carmen",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_parque",
                "hora": 12
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "Carmen ha rechazado la propuesta: \"Hoy no me da la vida.\"",
                "reacciones": [
                    {
                        "nombre": "Álex",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    },
                    {
                        "nombre": "Carmen",
                        "decision": "rechaza",
                        "score": null,
                        "p": 0,
                        "motivo": "cooldown_propuesta"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 2,
                "disc_b": 6
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Sara",
                "b": "Marta",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_biblioteca",
                "hora": 13
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "Marta ha rechazado la propuesta: \"Hoy no me da la vida.\"",
                "reacciones": [
                    {
                        "nombre": "Sara",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    },
                    {
                        "nombre": "Marta",
                        "decision": "rechaza",
                        "score": null,
                        "p": 0,
                        "motivo": "cooldown_propuesta"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 2,
                "disc_b": 4
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Carmen",
                "b": "José",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_cafeteria",
                "hora": 14
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "José ha rechazado la propuesta: \"Hoy no me da la vida.\"",
                "reacciones": [
                    {
                        "nombre": "Carmen",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    },
                    {
                        "nombre": "José",
                        "decision": "rechaza",
                        "score": null,
                        "p": 0,
                        "motivo": "cooldown_propuesta"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 6,
                "disc_b": 6
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "José",
                "b": "Raúl",
                "conocen": true,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_parque",
                "hora": 15
            },
            "decision": {
                "rechazada": null,
                "clase": null,
                "mensaje_ui": "Esas dos ya se conocen.",
                "reacciones": []
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 6,
                "disc_b": 4
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Aceptado y programado; deltas sociales vía resolución de encuentro si el reloj lo consumió."
        },
        {
            "antes": {
                "a": "Marta",
                "b": "Dani",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_biblioteca",
                "hora": 16
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "Dani ha rechazado la propuesta: \"Hoy no me da la vida.\"",
                "reacciones": [
                    {
                        "nombre": "Marta",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    },
                    {
                        "nombre": "Dani",
                        "decision": "rechaza",
                        "score": null,
                        "p": 0,
                        "motivo": "cooldown_propuesta"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 4,
                "disc_b": 4
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Raúl",
                "b": "Lucía",
                "conocen": false,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_cafeteria",
                "hora": 17
            },
            "decision": {
                "rechazada": true,
                "clase": "voluntad",
                "mensaje_ui": "Lucía ha rechazado la propuesta: \"Hoy no me da la vida.\"",
                "reacciones": [
                    {
                        "nombre": "Raúl",
                        "decision": "rechaza",
                        "score": 70,
                        "p": 0.6819999999999999,
                        "motivo": "voluntad_rechaza_banal"
                    },
                    {
                        "nombre": "Lucía",
                        "decision": "rechaza",
                        "score": null,
                        "p": 0,
                        "motivo": "cooldown_propuesta"
                    }
                ]
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 4,
                "disc_b": 4
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Rechazo: voluntad"
        },
        {
            "antes": {
                "a": "Lucía",
                "b": "Álex",
                "conocen": true,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_parque",
                "hora": 18
            },
            "decision": {
                "rechazada": null,
                "clase": null,
                "mensaje_ui": "Esas dos ya se conocen.",
                "reacciones": []
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 4,
                "disc_b": 2
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Aceptado y programado; deltas sociales vía resolución de encuentro si el reloj lo consumió."
        },
        {
            "antes": {
                "a": "Dani",
                "b": "Carmen",
                "conocen": true,
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "lugar": "lug_biblioteca",
                "hora": 19
            },
            "decision": {
                "rechazada": null,
                "clase": null,
                "mensaje_ui": "Esas dos ya se conocen.",
                "reacciones": []
            },
            "despues": {
                "social_ab": 0,
                "social_ba": 0,
                "emo_a": "neutro",
                "emo_b": "neutro",
                "disc_a": 4,
                "disc_b": 6
            },
            "por_que_el_motor": "Voluntad individual por participante; plan solo si ambos aceptan (pA×pB). Aceptado y programado; deltas sociales vía resolución de encuentro si el reloj lo consumió."
        }
    ]
}
```
</details>

## F. Emociones — PASS

<details><summary>JSON</summary>

```json
{
    "status": "PASS",
    "nota": "Emoción individual canónica."
}
```
</details>

## G. Discovery — PASS

<details><summary>JSON</summary>

```json
{
    "status": "PASS",
    "residente": "Carmen",
    "total_descubrible_aprox": {
        "hobbies_ficha": 0,
        "rasgos_ficha": 0,
        "reveal_inicial_esperado": 2
    },
    "snapshots": {
        "dia_1": {
            "residente": "Carmen",
            "n": 2,
            "por_categoria": {
                "hobby": 1,
                "rasgo": 1
            },
            "items": [
                {
                    "id": "disc_a7141325",
                    "residente_id": "per_p001",
                    "campo": "hobby:cafe_social",
                    "valor": "cafe_social",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 17
                    }
                },
                {
                    "id": "disc_4971e3bc",
                    "residente_id": "per_p001",
                    "campo": "rasgo:observador",
                    "valor": "observador",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 17
                    }
                }
            ]
        },
        "dia_10": {
            "residente": "Carmen",
            "n": 4,
            "por_categoria": {
                "hobby": 2,
                "rasgo": 2
            },
            "items": [
                {
                    "id": "disc_a7141325",
                    "residente_id": "per_p001",
                    "campo": "hobby:cafe_social",
                    "valor": "cafe_social",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 17
                    }
                },
                {
                    "id": "disc_4971e3bc",
                    "residente_id": "per_p001",
                    "campo": "rasgo:observador",
                    "valor": "observador",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 17
                    }
                },
                {
                    "id": "disc_7903690e",
                    "residente_id": "per_p001",
                    "campo": "hobby:costura",
                    "valor": "costura",
                    "estado": "descubierto",
                    "origen": "encuentro",
                    "evento_relacionado": null,
                    "correlacion_id": "enc_43999c4e",
                    "ts_juego": {
                        "dia": 5,
                        "hora": 17
                    }
                },
                {
                    "id": "disc_fc5cd9eb",
                    "residente_id": "per_p001",
                    "campo": "rasgo:nervioso",
                    "valor": "nervioso",
                    "estado": "descubierto",
                    "origen": "encuentro",
                    "evento_relacionado": null,
                    "correlacion_id": "enc_43999c4e",
                    "ts_juego": {
                        "dia": 5,
                        "hora": 17
                    }
                }
            ]
        },
        "dia_30": {
            "residente": "Carmen",
            "n": 4,
            "por_categoria": {
                "hobby": 2,
                "rasgo": 2
            },
            "items": [
                {
                    "id": "disc_a7141325",
                    "residente_id": "per_p001",
                    "campo": "hobby:cafe_social",
                    "valor": "cafe_social",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 17
                    }
                },
                {
                    "id": "disc_4971e3bc",
                    "residente_id": "per_p001",
                    "campo": "rasgo:observador",
                    "valor": "observador",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 17
                    }
                },
                {
                    "id": "disc_7903690e",
                    "residente_id": "per_p001",
                    "campo": "hobby:costura",
                    "valor": "costura",
                    "estado": "descubierto",
                    "origen": "encuentro",
                    "evento_relacionado": null,
                    "correlacion_id": "enc_43999c4e",
                    "ts_juego": {
                        "dia": 5,
                        "hora": 17
                    }
                },
                {
                    "id": "disc_fc5cd9eb",
                    "residente_id": "per_p001",
                    "campo": "rasgo:nervioso",
                    "valor": "nervioso",
                    "estado": "descubierto",
                    "origen": "encuentro",
                    "evento_relacionado": null,
                    "correlacion_id": "enc_43999c4e",
                    "ts_juego": {
                        "dia": 5,
                        "hora": 17
                    }
                }
            ]
        },
        "dia_100": {
            "residente": "Carmen",
            "n": 5,
            "por_categoria": {
                "hobby": 3,
                "rasgo": 2
            },
            "items": [
                {
                    "id": "disc_a7141325",
                    "residente_id": "per_p001",
                    "campo": "hobby:cafe_social",
                    "valor": "cafe_social",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 17
                    }
                },
                {
                    "id": "disc_4971e3bc",
                    "residente_id": "per_p001",
                    "campo": "rasgo:observador",
                    "valor": "observador",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 17
                    }
                },
                {
                    "id": "disc_7903690e",
                    "residente_id": "per_p001",
                    "campo": "hobby:costura",
                    "valor": "costura",
                    "estado": "descubierto",
                    "origen": "encuentro",
                    "evento_relacionado": null,
                    "correlacion_id": "enc_43999c4e",
                    "ts_juego": {
                        "dia": 5,
                        "hora": 17
                    }
                },
                {
                    "id": "disc_fc5cd9eb",
                    "residente_id": "per_p001",
                    "campo": "rasgo:nervioso",
                    "valor": "nervioso",
                    "estado": "descubierto",
                    "origen": "encuentro",
                    "evento_relacionado": null,
                    "correlacion_id": "enc_43999c4e",
                    "ts_juego": {
                        "dia": 5,
                        "hora": 17
                    }
                },
                {
                    "id": "disc_fb144b65",
                    "residente_id": "per_p001",
                    "campo": "hobby:deporte",
                    "valor": "deporte",
                    "estado": "descubierto",
                    "origen": "casual",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 67,
                        "hora": 17
                    }
                }
            ]
        },
        "dia_365": {
            "residente": "Carmen",
            "n": 5,
            "por_categoria": {
                "hobby": 3,
                "rasgo": 2
            },
            "items": [
                {
                    "id": "disc_a7141325",
                    "residente_id": "per_p001",
                    "campo": "hobby:cafe_social",
                    "valor": "cafe_social",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 17
                    }
                },
                {
                    "id": "disc_4971e3bc",
                    "residente_id": "per_p001",
                    "campo": "rasgo:observador",
                    "valor": "observador",
                    "estado": "descubierto",
                    "origen": "reveal_inicial",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 1,
                        "hora": 17
                    }
                },
                {
                    "id": "disc_7903690e",
                    "residente_id": "per_p001",
                    "campo": "hobby:costura",
                    "valor": "costura",
                    "estado": "descubierto",
                    "origen": "encuentro",
                    "evento_relacionado": null,
                    "correlacion_id": "enc_43999c4e",
                    "ts_juego": {
                        "dia": 5,
                        "hora": 17
                    }
                },
                {
                    "id": "disc_fc5cd9eb",
                    "residente_id": "per_p001",
                    "campo": "rasgo:nervioso",
                    "valor": "nervioso",
                    "estado": "descubierto",
                    "origen": "encuentro",
                    "evento_relacionado": null,
                    "correlacion_id": "enc_43999c4e",
                    "ts_juego": {
                        "dia": 5,
                        "hora": 17
                    }
                },
                {
                    "id": "disc_fb144b65",
                    "residente_id": "per_p001",
                    "campo": "hobby:deporte",
                    "valor": "deporte",
                    "estado": "descubierto",
                    "origen": "casual",
                    "evento_relacionado": null,
                    "correlacion_id": null,
                    "ts_juego": {
                        "dia": 67,
                        "hora": 17
                    }
                }
            ]
        }
    },
    "nota": "Discovery crece con interacciones."
}
```
</details>

## H. Relaciones — PARCIAL

<details><summary>JSON</summary>

```json
{
    "status": "PARCIAL",
    "implementado": [
        "social",
        "romance",
        "contacto",
        "desgaste_diario",
        "pareja_por_hito"
    ],
    "RELACIONES_NO_IMPLEMENTADO": [
        "crisis_automatica_por_umbral (explícitamente nunca_auto_por_umbral)",
        "ruptura_automatica_por_probabilidad JSON (huérfana)",
        "trayectoria completa conocidos→ruptura sin hitos manuales"
    ],
    "BLOQUEADO_DECISION": [
        "Cuándo\/cómo se dispara crisis\/ruptura en play",
        "Uso de crisis.probabilidad \/ ruptura.probabilidad del JSON"
    ]
}
```
</details>

## I. Marchas — BLOQUEADO_DECISION

<details><summary>JSON</summary>

```json
{
    "status": "BLOQUEADO_DECISION",
    "codigo": "BLOQUEADO_DECISION_MARCHAS",
    "documentado_cerrado": [
        "Intención sí; salida solo con OK Celestine",
        "Quédate = siempre se queda",
        "Nunca offline",
        "Antiguo archivado fuera del pool de esa partida"
    ],
    "falta_decidir": [
        "¿Marchas en 1.ª prueba jugable?",
        "Canonizar protección ~30 días (hoy hipótesis)",
        "Fórmula\/umbrales de “quiere irse”",
        "Tope de arcos post-quédate"
    ],
    "NO_IMPLEMENTADO": "MarchaEngine \/ aviso marcha cableado a roster"
}
```
</details>

## J. Economía — NO_IMPLEMENTADO

<details><summary>JSON</summary>

```json
{
    "status": "NO_IMPLEMENTADO",
    "economy_enabled": false,
    "principio_cerrado": "El dinero no sirve para sobrevivir; abre diversión (edificios, objetos, viajes…). Citas normales no generan dinero.",
    "lab_existe": true,
    "BLOQUEADO_DECISION": [
        "gates_B_C",
        "renta_offline",
        "parque_inicial",
        "fama_en_desbloqueos",
        "latido_paga"
    ],
    "nota": "No se enciende economy_enabled ni se inventan precios."
}
```
</details>

## K. Aforos — PASS

<details><summary>JSON</summary>

```json
{
    "status": "PASS",
    "techos": {
        "cafe_libros": 10,
        "rincon_lola": 10,
        "cine_game": 12,
        "mala_idea": 12,
        "parque": 16,
        "gimnasio_spa": 10
    },
    "mala_idea_suma_destinos": 20,
    "mala_idea_techo": 12,
    "techo_impide_20": true,
    "cabe_4_en_disco_con_bar_8": true,
    "cabe_5_en_disco_con_bar_8": false,
    "bajo_poblacion_32": {
        "aforo_fallos_3d": 0
    }
}
```
</details>

## L. Buzón/Cotilleo — PASS

<details><summary>JSON</summary>

```json
{
    "status": "PASS",
    "por_poblacion_30d": {
        "8": {
            "buzon_total_30d": 4,
            "cotilleo_total_30d": 1,
            "cotilleo_por_dia": 0.03,
            "buzon_por_dia": 0.13
        },
        "16": {
            "buzon_total_30d": 8,
            "cotilleo_total_30d": 1,
            "cotilleo_por_dia": 0.03,
            "buzon_por_dia": 0.27
        },
        "32": {
            "buzon_total_30d": 9,
            "cotilleo_total_30d": 1,
            "cotilleo_por_dia": 0.03,
            "buzon_por_dia": 0.3
        }
    }
}
```
</details>

## M. Integración — PASS

<details><summary>JSON</summary>

```json
{
    "status": "PASS",
    "save_load_tutorial": true,
    "api_llegada": [
        "llegada.estado",
        "llegada.aceptar",
        "llegada.rechazar"
    ]
}
```
</details>

## N. Invariantes — PASS

<details><summary>JSON</summary>

```json
{
    "status": "PASS",
    "fallos": []
}
```
</details>

## O. Trazas humanas — PASS

### Traza pueblo (extracto)

- D1: Partida: 3 iniciales + tutorial en curso/completado.
- D1: Marta se mudó (tutorial).
- D1: Raúl se mudó (tutorial).
- D1: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D2: Lucía se mudó (tutorial).
- D2: Dani se mudó (tutorial).
- D2: Álex se mudó (tutorial).
- D3: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D5: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D7: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D9: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D11: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D13: Rocío y Carmen aceptaron plan en cafetería.
- D15: Rocío y Carmen aceptaron plan en cafetería.
- D17: Rocío y Carmen aceptaron plan en cafetería.
- D19: Rocío y Carmen aceptaron plan en cafetería.
- D21: Rocío y Carmen aceptaron plan en cafetería.
- D23: Rocío y Carmen aceptaron plan en cafetería.
- D25: Rocío y Carmen aceptaron plan en cafetería.
- D27: Rocío y Carmen aceptaron plan en cafetería.
- D29: Rocío y Carmen aceptaron plan en cafetería.
- D31: Rocío y Carmen aceptaron plan en cafetería.
- D33: Rocío y Carmen aceptaron plan en cafetería.
- D35: Rocío y Carmen aceptaron plan en cafetería.
- D37: Rocío y Carmen aceptaron plan en cafetería.

### Residentes

#### Rocío

- D1: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D3: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D5: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D7: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D9: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D11: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D13: Rocío y Carmen aceptaron plan en cafetería.
- D15: Rocío y Carmen aceptaron plan en cafetería.
- D17: Rocío y Carmen aceptaron plan en cafetería.
- D19: Rocío y Carmen aceptaron plan en cafetería.
- D21: Rocío y Carmen aceptaron plan en cafetería.
- D23: Rocío y Carmen aceptaron plan en cafetería.
- D25: Rocío y Carmen aceptaron plan en cafetería.
- D27: Rocío y Carmen aceptaron plan en cafetería.
- D29: Rocío y Carmen aceptaron plan en cafetería.

#### Carmen

- D1: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D3: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D5: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D7: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D9: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D11: Rocío propuso plan con Carmen → RECHAZO (voluntad)
- D13: Rocío y Carmen aceptaron plan en cafetería.
- D15: Rocío y Carmen aceptaron plan en cafetería.
- D17: Rocío y Carmen aceptaron plan en cafetería.
- D19: Rocío y Carmen aceptaron plan en cafetería.
- D21: Rocío y Carmen aceptaron plan en cafetería.
- D23: Rocío y Carmen aceptaron plan en cafetería.
- D25: Rocío y Carmen aceptaron plan en cafetería.
- D27: Rocío y Carmen aceptaron plan en cafetería.
- D29: Rocío y Carmen aceptaron plan en cafetería.

#### José


<details><summary>JSON</summary>

```json
{
    "status": "PASS",
    "horizonte": 120,
    "poblacion_final": 9,
    "traza_pueblo": [
        {
            "dia": 1,
            "hecho": "Partida: 3 iniciales + tutorial en curso\/completado."
        },
        {
            "dia": 1,
            "hecho": "Marta se mudó (tutorial)."
        },
        {
            "dia": 1,
            "hecho": "Raúl se mudó (tutorial)."
        },
        {
            "dia": 1,
            "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
        },
        {
            "dia": 2,
            "hecho": "Lucía se mudó (tutorial)."
        },
        {
            "dia": 2,
            "hecho": "Dani se mudó (tutorial)."
        },
        {
            "dia": 2,
            "hecho": "Álex se mudó (tutorial)."
        },
        {
            "dia": 3,
            "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
        },
        {
            "dia": 5,
            "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
        },
        {
            "dia": 7,
            "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
        },
        {
            "dia": 9,
            "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
        },
        {
            "dia": 11,
            "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
        },
        {
            "dia": 13,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 15,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 17,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 19,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 21,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 23,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 25,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 27,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 29,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 31,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 33,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 35,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 37,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 39,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 39,
            "hecho": "Candidato en buzón: Sara"
        },
        {
            "dia": 39,
            "hecho": "Celestine aceptó candidato; llegó tras espera."
        },
        {
            "dia": 41,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 43,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 45,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 47,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 49,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 51,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 53,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 55,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 57,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 59,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 61,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 63,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 65,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 67,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 69,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 71,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 73,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 75,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 77,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 79,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 81,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 83,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 85,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 87,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 89,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 91,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 93,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 95,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 97,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 99,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 101,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        },
        {
            "dia": 103,
            "hecho": "Rocío y Carmen aceptaron plan en cafetería."
        }
    ],
    "residentes": [
        {
            "nombre": "Rocío",
            "eventos": [
                {
                    "dia": 1,
                    "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
                },
                {
                    "dia": 3,
                    "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
                },
                {
                    "dia": 5,
                    "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
                },
                {
                    "dia": 7,
                    "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
                },
                {
                    "dia": 9,
                    "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
                },
                {
                    "dia": 11,
                    "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
                },
                {
                    "dia": 13,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 15,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 17,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 19,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 21,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 23,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 25,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 27,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 29,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 31,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 33,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 35,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 37,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 39,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 41,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 43,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 45,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 47,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 49,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 51,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 53,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 55,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 57,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 59,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 61,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 63,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 65,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 67,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 69,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 71,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 73,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 75,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 77,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 79,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                }
            ]
        },
        {
            "nombre": "Carmen",
            "eventos": [
                {
                    "dia": 1,
                    "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
                },
                {
                    "dia": 3,
                    "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
                },
                {
                    "dia": 5,
                    "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
                },
                {
                    "dia": 7,
                    "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
                },
                {
                    "dia": 9,
                    "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
                },
                {
                    "dia": 11,
                    "hecho": "Rocío propuso plan con Carmen → RECHAZO (voluntad)"
                },
                {
                    "dia": 13,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 15,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 17,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 19,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 21,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 23,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 25,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 27,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 29,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 31,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 33,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 35,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 37,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 39,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 41,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 43,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 45,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 47,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 49,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 51,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 53,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 55,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 57,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 59,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 61,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 63,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 65,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 67,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 69,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 71,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 73,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 75,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 77,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                },
                {
                    "dia": 79,
                    "hecho": "Rocío y Carmen aceptaron plan en cafetería."
                }
            ]
        },
        {
            "nombre": "José",
            "eventos": []
        }
    ],
    "parejas_potenciales": [
        {
            "a": "Rocío",
            "b": "Carmen"
        },
        {
            "a": "Carmen",
            "b": "José"
        }
    ]
}
```
</details>

## Horizontes

- `h1-activa-d30` activa 30d pop=8 planes=13 llegadas_hist=0 inv=0
- `h2-activa-d30` activa 30d pop=8 planes=16 llegadas_hist=0 inv=0
- `h1-normal-d30` normal 30d pop=8 planes=6 llegadas_hist=0 inv=0
- `h2-normal-d30` normal 30d pop=9 planes=9 llegadas_hist=1 inv=0
- `h1-torpe-d30` torpe 30d pop=9 planes=9 llegadas_hist=1 inv=0
- `h2-torpe-d30` torpe 30d pop=8 planes=14 llegadas_hist=0 inv=0
- `h1-inactiva-d30` inactiva 30d pop=8 planes=2 llegadas_hist=0 inv=0
- `h2-inactiva-d30` inactiva 30d pop=8 planes=1 llegadas_hist=1 inv=0
- `h1-activa-d100` activa 100d pop=9 planes=39 llegadas_hist=1 inv=1
- `h2-activa-d100` activa 100d pop=8 planes=44 llegadas_hist=0 inv=0
- `h1-normal-d100` normal 100d pop=9 planes=23 llegadas_hist=1 inv=0
- `h2-normal-d100` normal 100d pop=9 planes=19 llegadas_hist=1 inv=0
- `h1-torpe-d100` torpe 100d pop=9 planes=29 llegadas_hist=2 inv=0
- `h2-torpe-d100` torpe 100d pop=9 planes=30 llegadas_hist=2 inv=0
- `h1-inactiva-d100` inactiva 100d pop=8 planes=2 llegadas_hist=4 inv=0
- `h2-inactiva-d100` inactiva 100d pop=8 planes=3 llegadas_hist=1 inv=0
- `y1-activa-d365` activa 365d pop=11 planes=171 llegadas_hist=3 inv=0
- `y1-normal-d365` normal 365d pop=13 planes=85 llegadas_hist=5 inv=0
- `y1-torpe-d365` torpe 365d pop=10 planes=114 llegadas_hist=4 inv=2
- `y1-inactiva-d365` inactiva 365d pop=8 planes=18 llegadas_hist=5 inv=0
